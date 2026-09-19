<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require 'config.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');

$headers = getallheaders();
$auth = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
$userRole = ''; $userPlant = '';
if ($auth && preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
    $token = $conn->real_escape_string($m[1]);
    $res = $conn->query("SELECT role, plant_id FROM users WHERE auth_token = '$token' LIMIT 1");
    if ($res && $res->num_rows > 0) { $u = $res->fetch_assoc(); $userRole = $u['role']; $userPlant = $u['plant_id']; }
}
if (empty($userRole)) {
    $urlToken = isset($_GET['token']) ? $conn->real_escape_string($_GET['token']) : '';
    if ($urlToken) {
        $res = $conn->query("SELECT role, plant_id FROM users WHERE auth_token = '$urlToken' LIMIT 1");
        if ($res && $res->num_rows > 0) { $u = $res->fetch_assoc(); $userRole = $u['role']; $userPlant = $u['plant_id']; }
    }
}

$tab = isset($_GET['tab']) ? $conn->real_escape_string($_GET['tab']) : 'inv_vcb';
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : 'daily';
$date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : date('Y-m-d');
$plant = isset($_GET['plant']) ? trim($conn->real_escape_string($_GET['plant'])) : 'all';
$chartMode = isset($_GET['chart']) ? true : false;
if ($plant === '') $plant = 'all';

if ($userRole && $userRole !== 'admin') { if ($plant !== $userPlant) $plant = $userPlant; }
$plantClause = ($plant !== 'all' && $plant !== '') ? " AND plant_id = '$plant'" : "";

try {
class SimpleWSClient {
    private $socket;
    public function connect($host, $port) {
        $address = ($port === 5001 ? 'ssl://' : '') . $host;
        $this->socket = @fsockopen($address, $port, $errno, $errstr, 3);
        if (!$this->socket) return false;
        stream_set_timeout($this->socket, 10);
        stream_set_blocking($this->socket, false);
        $key = base64_encode(random_bytes(16));
        $headers = "GET / HTTP/1.1\r\nHost: {$host}:{$port}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n";
        fwrite($this->socket, $headers);
        $response = ''; $start = time();
        while (time() - $start < 3) {
            $line = fgets($this->socket);
            if ($line === false) { usleep(10000); continue; }
            $response .= $line;
            if ($line === "\r\n") break;
        }
        return strpos($response, '101') !== false;
    }
    public function sendText($payload) {
        $len = strlen($payload); $frame = chr(0x81); $mask = random_bytes(4);
        if ($len <= 125) $frame .= chr(0x80 | $len);
        elseif ($len <= 65535) { $frame .= chr(0x80 | 126) . pack('n', $len); }
        else { $frame .= chr(0x80 | 127) . pack('NN', 0, $len); }
        $frame .= $mask;
        for ($i = 0; $i < $len; $i++) $frame .= $payload[$i] ^ $mask[$i % 4];
        fwrite($this->socket, $frame);
    }
    public function readFrame() {
        $data = $this->readExactly(2);
        if (!$data || strlen($data) < 2) return null;
        $byte1 = ord($data[0]); $byte2 = ord($data[1]);
        $opcode = $byte1 & 0x0F; $len = $byte2 & 0x7F;
        if ($len === 126) { $ext = $this->readExactly(2); if (!$ext) return null; $len = unpack('n', $ext)[1]; }
        elseif ($len === 127) { $ext = $this->readExactly(8); if (!$ext) return null; $u = unpack('N2', $ext); $len = ($u[1] << 32) | $u[2]; }
        $masked = ($byte2 >> 7) & 0x01;
        if ($masked) { $mask = $this->readExactly(4); if (!$mask) return null; }
        $payload = ''; if ($len > 0) { $payload = $this->readExactly($len); if (!$payload) return null; }
        if (!empty($mask)) for ($i = 0; $i < $len; $i++) $payload[$i] = $payload[$i] ^ $mask[$i % 4];
        if ($opcode === 0x08) return ['opcode' => 'close', 'payload' => $payload];
        if ($opcode === 0x09) { $this->sendPong($payload); return ['opcode' => 'ping', 'payload' => '']; }
        if ($opcode === 0x0A) return ['opcode' => 'pong', 'payload' => ''];
        return ['opcode' => 'text', 'payload' => $payload];
    }
    private function readExactly($n) {
        $buffer = ''; $start = time();
        while (strlen($buffer) < $n && time() - $start < 2) {
            $chunk = @fread($this->socket, $n - strlen($buffer));
            if ($chunk === false || $chunk === '') { if (feof($this->socket)) return null; usleep(1000); continue; }
            $buffer .= $chunk;
        }
        return strlen($buffer) === $n ? $buffer : null;
    }
    private function sendPong($payload) {
        $len = strlen($payload); $frame = chr(0x8A); $mask = random_bytes(4);
        if ($len <= 125) $frame .= chr(0x80 | $len) . $mask;
        else { $frame .= chr(0x80 | 126) . pack('n', $len) . $mask; }
        for ($i = 0; $i < $len; $i++) $frame .= $payload[$i] ^ $mask[$i % 4];
        fwrite($this->socket, $frame);
    }
    public function close() { if ($this->socket) { fclose($this->socket); $this->socket = null; } }
}

function wsNumericValue($value) {
    if (is_array($value)) {
        foreach (['value','val','reading','data','result','current','last'] as $key) {
            if (array_key_exists($key, $value)) {
                $nested = wsNumericValue($value[$key]);
                if ($nested !== null) return $nested;
            }
        }
        return null;
    }
    if ($value === null || $value === '') return null;
    $clean = str_replace(',', '', (string)$value);
    return is_numeric($clean) ? (float)$clean : null;
}

function walkLiveWeatherValues($node, $context = [], &$weather = null, &$seen = null, $depth = 0) {
    if ($weather === null) $weather = ['radiation'=>null,'panel_temp'=>null,'ambient_temp'=>null,'wind_speed'=>null,'humidity'=>null];
    if ($seen === null) $seen = [];
    if (!is_array($node) || $depth > 7) return;
    foreach ($node as $key => $value) {
        $name = strtolower(preg_replace('/[_\-.]+/', ' ', trim((string)$key)));
        $ctx = strtolower(implode(' ', array_merge($context, [$name])));
        if (is_array($value)) {
            walkLiveWeatherValues($value, array_merge($context, [$name]), $weather, $seen, $depth + 1);
            continue;
        }
        $num = wsNumericValue($value);
        if ($num === null) continue;
        if (preg_match('/radiat|irradiance|pyran|raw data/', $ctx)) $weather['radiation'] = $num;
        if (preg_match('/pannel|panel|module/',$ctx) && preg_match('/temp|temperature/',$ctx)) $weather['panel_temp'] = $num;
        if (preg_match('/ambient/',$ctx) && preg_match('/temp|temperature/',$ctx)) $weather['ambient_temp'] = $num;
        if (preg_match('/wind|windspeed|wind speed|wind velocity|velocity|anemometer/',$ctx)) $weather['wind_speed'] = $num;
        if (preg_match('/humidity|relative humidity/',$ctx)) $weather['humidity'] = $num;
    }
}

function fetchLiveData($plant) {
    $ws = new SimpleWSClient();
    if (!$ws->connect('vinobasolar.scadahub.in', 5001)) {
        return ['error' => 'SCADA WebSocket connect failed'];
    }
    $ws->sendText(json_encode(['type' => 'get_devices', 'unit_id' => $plant]));
    $ws->sendText(json_encode(['type' => 'subscribe', 'unit_id' => $plant]));
$frames = []; $start = time();
    while (time() - $start < 6) {
        $frame = $ws->readFrame();
        if ($frame && isset($frame['payload']) && $frame['opcode'] === 'text') {
            $j = json_decode($frame['payload'], true);
            if ($j && is_array($j) && (
                isset($j['unit_id']) || isset($j['task']) || isset($j['device']) || isset($j['deviceName']) ||
                isset($j['data']) || isset($j['values']) || isset($j['payload']) || isset($j['result'])
            )) $frames[] = $j;
        }
        usleep(2000);
    }
    $ws->close();

    $latest = ['vcb'=>[],'trafo'=>[],'wmos'=>[]];
    $invRaw = [];
    foreach ($frames as $f) {
        $task = strtolower($f['task'] ?? $f['pageName'] ?? $f['type'] ?? '');
        $dev = strtolower($f['device'] ?? $f['deviceName'] ?? $f['sensor'] ?? '');
        $v = $f['values'] ?? [];
        if (!is_array($v) && isset($f['data']) && is_array($f['data'])) $v = $f['data'];
        $weather = ['radiation'=>null,'panel_temp'=>null,'ambient_temp'=>null,'wind_speed'=>null,'humidity'=>null];
        $weatherSeen = [];
        $frameUnit = trim((string)($f['unit_id'] ?? $f['unitId'] ?? ''));
        $isWeatherTask = ($task === 'wmos');
        $isWeatherDevice = preg_match('/pyran|pyrimeter|pannel|panel|ambient|wind|humid|radiat|irradiance|anemometer|velocity|windspeed/i', $dev.' '.$task);
        if (($frameUnit === '' || $frameUnit === $plant) && ($isWeatherTask || $isWeatherDevice)) {
            walkLiveWeatherValues($f, [$dev, $task], $weather, $weatherSeen);
            foreach ($weather as $weatherKey => $weatherValue) {
                if ($weatherValue !== null) $latest['wmos'][$weatherKey] = $weatherValue;
            }
        }
        if (($f['unit_id'] === $plant || $plant === 'all') && ($task === 'inverter' || strpos($dev, 'inverter') !== false)) {
            $actualName = $f['device'] ?? 'Unknown Inverter';
            if (!isset($invRaw[$actualName])) $invRaw[$actualName] = [];
            foreach ($v as $vk => $vv) {
                $vkl = strtolower($vk);
                if (preg_match('/active.*power|ac.*power|power.*ac|a\.c\..*power/', $vkl) && !preg_match('/reactive|apparent|3\.phase/', $vkl)) {
                    $invRaw[$actualName]['kw'] = floatval($vv);
                }
                if (preg_match('/daily.*generation|daily.*gen/', $vkl)) $invRaw[$actualName]['kwh'] = floatval($vv);
                if (preg_match('/internal.*temp|ambient.*temp|control.*temp/', $vkl)) $invRaw[$actualName]['temp'] = floatval($vv);
            }
        }
        if (($f['unit_id'] === $plant || $plant === 'all') && ($task === 'vcb' || $dev === 'vcb')) {
            foreach ($v as $vk => $vv) {
                $vkl = strtolower($vk);
                if (strpos($vkl, 'active power') !== false && (strpos($vkl, 'total') !== false || strpos($vkl, '3 phase') !== false)) $latest['vcb']['kw'] = floatval($vv);
                if (strpos($vkl, 'export') !== false && strpos($vkl, 'reactive') === false) $latest['vcb']['kwh_exp'] = floatval($vv);
            }
            if (isset($f['virtualTags']['vcb-today'])) {
                $latest['vcb']['today'] = floatval($f['virtualTags']['vcb-today']['value']);
            }
        }
        if (($f['unit_id'] === $plant || $plant === 'all') && ($task === 'transformer')) {
            foreach ($v as $vk => $vv) {
                $vkl = strtolower($vk);
                if (strpos($vkl, 'oil') !== false) $latest['trafo']['oil'] = floatval($vv);
                if (strpos($vkl, 'winding') !== false) $latest['trafo']['winding'] = floatval($vv);
            }
        }
    }
    $invNames = array_keys($invRaw);
    sort($invNames);
    foreach ($invNames as $idx => $inm) {
        $latest['inv' . ($idx + 1)] = $invRaw[$inm];
    }
    return ['success' => true, 'frames' => count($frames), 'latest' => $latest, 'inv_names' => $invNames];
}

if (isset($_GET['live']) && $_GET['live'] === '1') {
    $liveResult = fetchLiveData($plant);
    echo json_encode($liveResult);
    exit;
}

function buildBuckets($type, $date, $hourly = false, $invCount = 2) {
    $buckets = [];
    $interval = $hourly ? 3600 : 900;
    if ($type === 'daily') {
        $start = strtotime($date . ($hourly ? " 05:00:00" : " 05:30:00"));
        $defaultEnd = strtotime($date . ($hourly ? " 19:00:00" : " 19:30:00"));
        $today = date('Y-m-d');
        if ($date === $today) {
            $now = time();
            $nowRounded = ceil($now / $interval) * $interval;
            $end = min($nowRounded, $defaultEnd);
            if ($end < $start) $end = $start;
        } else {
            $end = $defaultEnd;
        }
        while ($start <= $end) {
            $t = date('H:i', $start);
            $row = [
                'time_label'   => $t,
                'radiation' => null,
                'panel_temp' => null,
                'ambient_temp' => null,
                'wind_speed' => null,
                'humidity' => null,
                'inv_total_kwh'=> 0,
                'vcb_kwh'      => 0,
                'vcb_kw'       => 0,
                'tx_loss'      => 0,
                'ot'           => 0,
                'wt1'          => 0
            ];
            for ($i = 1; $i <= $invCount; $i++) {
                $row['inv' . $i . '_kwh'] = 0;
                $row['inv' . $i . '_kw'] = 0;
                $row['inv' . $i . '_temp'] = 0;
            }
            $buckets[$t] = $row;
            $start += $interval;
        }
    } else {
        $days = date('t', strtotime($date . '-01'));
        for ($i = 1; $i <= $days; $i++) {
            $label = str_pad($i, 2, '0', STR_PAD_LEFT) . '-' . date('m-Y', strtotime($date . '-01'));
            $row = [
                'time_label'   => $label,
                'radiation' => null,
                'panel_temp' => null,
                'ambient_temp' => null,
                'wind_speed' => null,
                'humidity' => null,
                'inv_total_kwh'=> 0,
                'vcb_kwh'      => 0,
                'vcb_kw'       => 0,
                'tx_loss'      => 0,
                'ot'           => 0,
                'wt1'          => 0
            ];
            for ($j = 1; $j <= $invCount; $j++) {
                $row['inv' . $j . '_kwh'] = 0;
                $row['inv' . $j . '_kw'] = 0;
                $row['inv' . $j . '_temp'] = 0;
            }
            $buckets[$label] = $row;
        }
    }
    return $buckets;
}

function to15min($t) { $p = explode(':', $t); return $p[0] . ':' . str_pad(floor($p[1] / 15) * 15, 2, '0', STR_PAD_LEFT); }
function toHour($t) { $p = explode(':', $t); return $p[0] . ':00'; }

function ensureWmosTable($conn) {
    @$conn->query("CREATE TABLE IF NOT EXISTS `weather_readings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `plant_id` VARCHAR(50) NOT NULL,
        `radiation` DECIMAL(8,2) DEFAULT 0,
        `panel_temp` DECIMAL(5,1) DEFAULT 0,
        `ambient_temp` DECIMAL(5,1) DEFAULT 0,
        `wind_speed` DECIMAL(5,2) DEFAULT 0,
        `humidity` DECIMAL(5,1) DEFAULT 0,
        `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_wx_plant` (`plant_id`),
        INDEX `idx_wx_time` (`recorded_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $res = @$conn->query("SHOW COLUMNS FROM `weather_readings` LIKE 'ambient_temp'");
    if ($res && $res->num_rows === 0) {
        @$conn->query("ALTER TABLE `weather_readings` ADD `ambient_temp` DECIMAL(5,1) DEFAULT 0 AFTER `panel_temp`");
    }
    $res = @$conn->query("SHOW COLUMNS FROM `weather_readings` LIKE 'humidity'");
    if ($res && $res->num_rows === 0) {
        @$conn->query("ALTER TABLE `weather_readings` ADD `humidity` DECIMAL(5,1) DEFAULT 0 AFTER `wind_speed`");
    }
}

ensureWmosTable($conn);

// Determine inverter device names
$invNames = [];
$dnRes = $conn->query("SELECT DISTINCT device_name FROM inverter_readings WHERE 1=1 $plantClause AND device_name NOT LIKE 'VCB%' AND device_name NOT LIKE 'Transformer%' ORDER BY device_name ASC");
if ($dnRes) while ($dnRow = $dnRes->fetch_assoc()) $invNames[] = $dnRow['device_name'];
if (empty($invNames)) {
    $invNames = ['INV-1', 'INV-2', 'INV-3', 'INV-4', 'INV-5', 'INV-6'];
}
$invCount = max(2, count($invNames));

$timeBuckets = buildBuckets($type, $date, $chartMode, $invCount);
$isToday = ($date === date('Y-m-d'));
$bucketFn = $chartMode ? 'toHour' : 'to15min';

if ($type === 'daily') {
    // 1. Fetch WMOS data
    $wmosPlantClause = $plantClause;
    $wmosRows = [];
    
    // Try weather_readings first; never fall back to another plant.
    try {
        $wmosRes = $conn->query("SELECT DATE_FORMAT(recorded_at,'%H:%i') as bTime, radiation, panel_temp, ambient_temp, wind_speed, humidity FROM weather_readings WHERE DATE(recorded_at)='$date' $wmosPlantClause ORDER BY recorded_at ASC");
if ($wmosRes) while ($row = $wmosRes->fetch_assoc()) $wmosRows[] = $row;
    } catch (Exception $e) {
        // Fallback with basic columns if table doesn't have all columns yet
        try {
            $wmosRes = $conn->query("SELECT DATE_FORMAT(recorded_at,'%H:%i') as bTime, radiation, panel_temp, NULL as ambient_temp, wind_speed, NULL as humidity FROM weather_readings WHERE DATE(recorded_at)='$date' $wmosPlantClause ORDER BY recorded_at ASC");
            if ($wmosRes) while ($row = $wmosRes->fetch_assoc()) $wmosRows[] = $row;
        } catch (Exception $e2) {}
    }

    foreach ($wmosRows as $row) {
        $bt = $bucketFn($row['bTime']);
        if (isset($timeBuckets[$bt])) {
            $rad = (float)($row['radiation'] ?? 0);
            $ptemp = (float)($row['panel_temp'] ?? 0);
            $atemp = (float)($row['ambient_temp'] ?? 0);
            $wind = (float)($row['wind_speed'] ?? 0);
            $hum = (float)($row['humidity'] ?? 0);

            if ($rad > 0) $timeBuckets[$bt]['radiation'] = $rad;
            if ($ptemp > 0) $timeBuckets[$bt]['panel_temp'] = $ptemp;
            if ($atemp > 0) $timeBuckets[$bt]['ambient_temp'] = $atemp;
            if ($wind > 0) $timeBuckets[$bt]['wind_speed'] = $wind;
            if ($hum > 0) $timeBuckets[$bt]['humidity'] = $hum;
        }
    }

    // 2. Fetch Inverter Data for each inverter
    foreach ($invNames as $idx => $inm) {
        $n = $idx + 1;
        $inmEsc = $conn->real_escape_string($inm);
        $q = "SELECT DATE_FORMAT(recorded_at,'%H:%i') as bTime, daily_generation as kwh, ac_active_power as kw, internal_temp as temp FROM inverter_readings WHERE DATE(recorded_at)='$date' AND device_name = '$inmEsc' $plantClause ORDER BY recorded_at ASC";
        $res = $conn->query($q);
        if ($res) while ($row = $res->fetch_assoc()) {
            $bt = $bucketFn($row['bTime']);
            if (isset($timeBuckets[$bt])) {
                $timeBuckets[$bt]['inv' . $n . '_kwh'] = (float)$row['kwh'];
                $timeBuckets[$bt]['inv' . $n . '_kw']  = (float)$row['kw'];
                $timeBuckets[$bt]['inv' . $n . '_temp']= (float)$row['temp'];
            }
        }
    }

    // 3. Fetch VCB Data
    $q = "SELECT DATE_FORMAT(recorded_at,'%H:%i') as bTime, active_power_total as kw, active_total_export as kwh_exp, today_energy FROM vcb_readings WHERE DATE(recorded_at)='$date' $plantClause ORDER BY recorded_at ASC";
    $res = $conn->query($q);
    $baseExport = 0;
    $minRes = $conn->query("SELECT active_total_export FROM vcb_readings WHERE DATE(recorded_at)='$date' $plantClause ORDER BY recorded_at ASC LIMIT 1");
    if ($minRes && $minRes->num_rows > 0) { $r = $minRes->fetch_assoc(); $baseExport = (float)$r['active_total_export']; }
    if ($res) while ($row = $res->fetch_assoc()) {
        $bt = $bucketFn($row['bTime']);
        if (isset($timeBuckets[$bt])) {
            $timeBuckets[$bt]['vcb_kw'] = (float)$row['kw'];
            if (isset($row['today_energy']) && (float)$row['today_energy'] > 0) {
                $timeBuckets[$bt]['vcb_kwh'] = (float)$row['today_energy'];
            } else {
                $timeBuckets[$bt]['vcb_kwh'] = max(0, ((float)$row['kwh_exp'] - $baseExport) / 1000);
            }
        }
    }

    // 4. Transformer temps
    $q = "SELECT DATE_FORMAT(recorded_at,'%H:%i') as bTime, oil_temp, winding_temp FROM transformer_readings WHERE DATE(recorded_at)='$date' $plantClause ORDER BY recorded_at ASC";
    $res = $conn->query($q);
    if ($res) while ($row = $res->fetch_assoc()) {
        $bt = $bucketFn($row['bTime']);
        if (isset($timeBuckets[$bt])) {
            if ($row['oil_temp'] !== null) $timeBuckets[$bt]['ot'] = (float)$row['oil_temp'];
            if ($row['winding_temp'] !== null) $timeBuckets[$bt]['wt1'] = (float)$row['winding_temp'];
        }
    }

    // Calculate row sums & TX Loss & fallback weather if missing
    foreach ($timeBuckets as $bt => &$bRow) {
        $sumInv = 0;
        $sumKw = 0;
        for ($i = 1; $i <= $invCount; $i++) {
            $sumInv += $bRow['inv' . $i . '_kwh'];
            $sumKw  += ($bRow['inv' . $i . '_kw'] ?? 0);
        }
        $bRow['inv_total_kwh'] = $sumInv;
        $bRow['tx_loss'] = max(0, $sumInv - ($bRow['vcb_kwh'] ?? 0));
    }
    unset($bRow);

} else if ($type === 'monthly') {
    $monthStart = date('Y-m-01', strtotime($date . '-01'));
    $nextMonthStart = date('Y-m-01', strtotime($monthStart . ' +1 month'));

    // 1. Monthly WMOS aggregates
    $wmosMonthly = [];
    try {
        $wmosQuery = "SELECT DATE(recorded_at) report_day, AVG(radiation) radiation, MAX(panel_temp) panel_temp, MAX(ambient_temp) ambient_temp, AVG(wind_speed) wind_speed, AVG(humidity) humidity FROM weather_readings WHERE recorded_at >= '$monthStart 00:00:00' AND recorded_at < '$nextMonthStart 00:00:00' $plantClause GROUP BY DATE(recorded_at)";
        $wmosRes = $conn->query($wmosQuery);
if ($wmosRes) while ($row = $wmosRes->fetch_assoc()) $wmosMonthly[$row['report_day']] = $row;
    } catch (Exception $e) {}

    if (empty($wmosMonthly)) {
        try {
            $wmosQuery = "SELECT DATE(recorded_at) report_day, AVG(radiation) radiation, MAX(panel_temp) panel_temp, MAX(ambient_temp) ambient_temp, AVG(wind_speed) wind_speed, AVG(humidity) humidity FROM weather_readings WHERE recorded_at >= '$monthStart 00:00:00' AND recorded_at < '$nextMonthStart 00:00:00' $plantClause GROUP BY DATE(recorded_at)";
            $wmosRes = $conn->query($wmosQuery);
if ($wmosRes) while ($row = $wmosRes->fetch_assoc()) $wmosMonthly[$row['report_day']] = $row;
        } catch (Exception $e2) {}
    }

    // 2. Monthly Inverter aggregates
    $monthlyInvData = [];
    $monthlyInvQuery = "SELECT DATE(recorded_at) report_day, plant_id, device_name, MAX(daily_generation) kwh, MAX(ac_active_power) kw FROM inverter_readings WHERE recorded_at >= '$monthStart 00:00:00' AND recorded_at < '$nextMonthStart 00:00:00' $plantClause AND device_name NOT LIKE 'VCB%' AND device_name NOT LIKE 'Transformer%' GROUP BY DATE(recorded_at), plant_id, device_name ORDER BY device_name ASC, report_day ASC";
    $monthlyInvRes = $conn->query($monthlyInvQuery);
    if ($monthlyInvRes) {
        while ($row = $monthlyInvRes->fetch_assoc()) {
            $dev = $row['device_name'];
            $day = $row['report_day'];
            if (!isset($monthlyInvData[$day])) $monthlyInvData[$day] = [];
            if (!isset($monthlyInvData[$day][$dev])) $monthlyInvData[$day][$dev] = ['kwh' => 0, 'kw' => 0];
            $monthlyInvData[$day][$dev]['kwh'] += (float)$row['kwh'];
            $monthlyInvData[$day][$dev]['kw'] += (float)$row['kw'];
        }
    }

    // 3. Monthly VCB aggregates
    $monthlyVcbToday = [];
    $vcbTodayRes = $conn->query("SELECT report_day, SUM(kwh) kwh FROM (SELECT DATE(recorded_at) report_day, plant_id, MAX(today_energy) kwh FROM vcb_readings WHERE recorded_at >= '$monthStart 00:00:00' AND recorded_at < '$nextMonthStart 00:00:00' $plantClause GROUP BY DATE(recorded_at), plant_id) daily_vcb GROUP BY report_day");
    if ($vcbTodayRes) while ($row = $vcbTodayRes->fetch_assoc()) $monthlyVcbToday[$row['report_day']] = (float)$row['kwh'];

    $days = date('t', strtotime($date . '-01'));
    for ($i = 1; $i <= $days; $i++) {
        $d = date('Y-m', strtotime($date . '-01')) . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
        $label = str_pad($i, 2, '0', STR_PAD_LEFT) . '-' . date('m-Y', strtotime($date . '-01'));

        if (isset($wmosMonthly[$d])) {
            $timeBuckets[$label]['radiation']    = round((float)$wmosMonthly[$d]['radiation'], 1);
            $timeBuckets[$label]['panel_temp']   = round((float)$wmosMonthly[$d]['panel_temp'], 1);
            $timeBuckets[$label]['ambient_temp'] = round((float)$wmosMonthly[$d]['ambient_temp'], 1);
            $timeBuckets[$label]['wind_speed']   = round((float)$wmosMonthly[$d]['wind_speed'], 1);
            $timeBuckets[$label]['humidity']     = round((float)$wmosMonthly[$d]['humidity'], 1);
        }

        $rowInvSum = 0;
        foreach ($invNames as $idx => $dev) {
            $n = $idx + 1;
            $daily = $monthlyInvData[$d][$dev] ?? ['kwh' => 0, 'kw' => 0];
            $kwh = (float)$daily['kwh'];
            $timeBuckets[$label]['inv' . $n . '_kwh'] = $kwh;
            $timeBuckets[$label]['inv' . $n . '_kw']  = (float)$daily['kw'];
            $rowInvSum += $kwh;
        }
        $timeBuckets[$label]['inv_total_kwh'] = $rowInvSum;

        $vcbVal = $monthlyVcbToday[$d] ?? 0;
        $timeBuckets[$label]['vcb_kwh'] = $vcbVal;
        $timeBuckets[$label]['tx_loss'] = max(0, $rowInvSum - $vcbVal);
    }
}

$response = [
    "success" => true,
    "meta" => [
        "tab" => $tab,
        "type" => $type,
        "date" => $date,
        "plant" => $plant,
        "inv_names" => $invNames,
        "generated_at" => date('Y-m-d H:i:s')
    ],
    "data" => array_values($timeBuckets)
];

echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Server error: " . $e->getMessage(),
        "data" => []
    ]);
}
?>
