<?php
require 'config.php';
header('Content-Type: application/json');

$res = $conn->query("SELECT COUNT(*) as cnt, MIN(recorded_at) as min_d, MAX(recorded_at) as max_d FROM weather_readings");
$row = $res ? $res->fetch_assoc() : null;

echo json_encode(['weather_readings' => $row]);
