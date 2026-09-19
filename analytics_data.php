<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/config.php';

$plant = trim((string)($_GET['plant'] ?? $currentPlant ?? ''));
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));

if ($plant === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Plant is required.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date.']);
    exit;
}

if (($user['role'] ?? '') !== 'admin' && !empty($user['plant_id']) && $user['plant_id'] !== $plant) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Plant access denied.']);
    exit;
}

try {
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $conn->set_charset('utf8mb4');

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM weather_readings");
    if ($result) {
        while ($column = $result->fetch_assoc()) {
            $columns[(string)$column['Field']] = true;
        }
    }

    $hasAmbient = isset($columns['ambient_temp']);
    $hasHumidity = isset($columns['humidity']);
    $hasDevice = isset($columns['device_name']);

    $select = "recorded_at, radiation, panel_temp, wind_speed";
    $select .= $hasAmbient ? ", ambient_temp" : ", NULL AS ambient_temp";
    $select .= $hasHumidity ? ", humidity" : ", NULL AS humidity";
    $select .= $hasDevice ? ", device_name" : ", '' AS device_name";

    $start = $date . ' 00:00:00';
    $end = date('Y-m-d H:i:s', strtotime($date . ' +1 day'));

    $stmt = $conn->prepare(
        "SELECT $select
         FROM weather_readings
         WHERE plant_id = ? AND recorded_at >= ? AND recorded_at < ?
         ORDER BY recorded_at ASC
         LIMIT 20000"
    );
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare weather history query.');
    }

    $stmt->bind_param('sss', $plant, $start, $end);
    $stmt->execute();
    $rows = [];
    $stmt->bind_result($recordedAt, $radiation, $panelTemp, $ambientTemp, $windSpeed, $humidity, $deviceName);

    while ($stmt->fetch()) {
        $rows[] = [
            'recorded_at' => $recordedAt,
            'radiation' => $radiation !== null ? (float)$radiation : null,
            'panelTemp' => $panelTemp !== null ? (float)$panelTemp : null,
            'ambientTemp' => $ambientTemp !== null ? (float)$ambientTemp : null,
            'windSpeed' => $windSpeed !== null ? (float)$windSpeed : null,
            'humidity' => $humidity !== null ? (float)$humidity : null,
            'device_name' => $deviceName ?? ''
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'plant' => $plant,
        'date' => $date,
        'rows' => $rows,
        'count' => count($rows)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load WMOS database data.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
?>