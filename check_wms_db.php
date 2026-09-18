<?php
require 'config.php';
$conn = getDbConnection();
$res = $conn->query("SELECT COUNT(*) as cnt, MIN(recorded_at) as min_d, MAX(recorded_at) as max_d FROM weather_readings");
$row = $res ? $res->fetch_assoc() : null;
$wmsRes = $conn->query("SELECT COUNT(*) as cnt, MIN(recorded_at) as min_d, MAX(recorded_at) as max_d FROM wms_readings");
$wmsRow = $wmsRes ? $wmsRes->fetch_assoc() : null;
header('Content-Type: application/json');
echo json_encode(['weather_readings' => $row, 'wms_readings' => $wmsRow]);
