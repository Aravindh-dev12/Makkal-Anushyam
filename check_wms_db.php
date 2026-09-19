<?php
require 'config.php';
$conn = getDbConnection();
$res = $conn->query("SELECT COUNT(*) as cnt, MIN(recorded_at) as min_d, MAX(recorded_at) as max_d FROM weather_readings");
$row = $res ? $res->fetch_assoc() : null;
$WMOSRes = $conn->query("SELECT COUNT(*) as cnt, MIN(recorded_at) as min_d, MAX(recorded_at) as max_d FROM weather_readings");
$WMOSRow = $WMOSRes ? $WMOSRes->fetch_assoc() : null;
header('Content-Type: application/json');
echo json_encode(['weather_readings' => $row, 'weather_readings' => $WMOSRow]);
