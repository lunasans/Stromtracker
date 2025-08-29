<?php
// api/tasmota-chart-data.php
// API-Endpoint für Tasmota Verlaufsdiagramm-Daten

require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Login erforderlich
if (!Auth::isLoggedIn()) {
    jsonResponse(['error' => 'Nicht eingeloggt'], 401);
}

$userId = Auth::getUserId();

// Parameter
$deviceId = (int)($_GET['device_id'] ?? 0);
$timeRange = $_GET['timerange'] ?? '60'; // 5, 60, 720 (Minuten)
$dataType = $_GET['type'] ?? 'power'; // power, voltage, current, energy

if ($deviceId <= 0) {
    jsonResponse(['error' => 'Ungültige Gerät-ID'], 400);
}

// Gerät prüfen (Berechtigung)
$device = Database::fetchOne(
    "SELECT * FROM devices WHERE id = ? AND user_id = ? AND tasmota_enabled = 1",
    [$deviceId, $userId]
);

if (!$device) {
    jsonResponse(['error' => 'Gerät nicht gefunden oder keine Berechtigung'], 404);
}

// Zeitraum berechnen
switch ($timeRange) {
    case '5':
        $minutes = 5;
        $interval = '1 MINUTE';
        $groupBy = "DATE_FORMAT(timestamp, '%H:%i')";
        $dateFormat = '%H:%i';
        break;
    case '60':
        $minutes = 60;
        $interval = '2 MINUTE';
        $groupBy = "DATE_FORMAT(timestamp, '%H:%i')";
        $dateFormat = '%H:%i';
        break;
    case '720':
        $minutes = 720; // 12 Stunden
        $interval = '15 MINUTE';
        $groupBy = "DATE_FORMAT(timestamp, '%H:%i')";
        $dateFormat = '%H:%i';
        break;
    default:
        $minutes = 60;
        $interval = '2 MINUTE';
        $groupBy = "DATE_FORMAT(timestamp, '%H:%i')";
        $dateFormat = '%H:%i';
}

// SQL basierend auf Datentyp
switch ($dataType) {
    case 'power':
        $valueColumn = 'AVG(power) as value';
        $unit = 'W';
        $color = '#f59e0b'; // Energy-Farbe
        break;
    case 'voltage':
        $valueColumn = 'AVG(voltage) as value';
        $unit = 'V';
        $color = '#3b82f6'; // Blau
        break;
    case 'current':
        $valueColumn = 'AVG(current) as value';
        $unit = 'A';
        $color = '#ef4444'; // Rot
        break;
    case 'energy':
        $valueColumn = 'MAX(energy_total) as value';
        $unit = 'kWh';
        $color = '#10b981'; // Grün
        break;
    default:
        $valueColumn = 'AVG(power) as value';
        $unit = 'W';
        $color = '#f59e0b';
}

// Daten abfragen
$chartData = Database::fetchAll(
    "SELECT 
        $groupBy as time_label,
        DATE_FORMAT(timestamp, '$dateFormat') as formatted_time,
        $valueColumn,
        MAX(timestamp) as latest_timestamp
     FROM tasmota_readings
     WHERE device_id = ? 
     AND timestamp >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
     AND timestamp <= NOW()
     GROUP BY $groupBy
     ORDER BY latest_timestamp ASC",
    [$deviceId, $minutes]
) ?: [];

// Aktuelle Werte (neuester Datensatz)
$currentData = Database::fetchOne(
    "SELECT power, voltage, current, energy_today, energy_total, timestamp,
            TIMESTAMPDIFF(SECOND, timestamp, NOW()) as seconds_ago
     FROM tasmota_readings
     WHERE device_id = ?
     ORDER BY timestamp DESC
     LIMIT 1",
    [$deviceId]
) ?: [];

// Chart-Daten formatieren
$labels = [];
$values = [];
$timestamps = [];

foreach ($chartData as $row) {
    $labels[] = $row['formatted_time'];
    $values[] = round((float)($row['value'] ?? 0), 2);
    $timestamps[] = $row['latest_timestamp'];
}

// Antwort zusammenstellen
$response = [
    'success' => true,
    'device' => [
        'id' => $device['id'],
        'name' => $device['name'],
        'ip' => $device['tasmota_ip']
    ],
    'timerange' => [
        'minutes' => $minutes,
        'label' => $timeRange == '5' ? '5 Minuten' : 
                  ($timeRange == '60' ? '1 Stunde' : '12 Stunden')
    ],
    'chart' => [
        'type' => $dataType,
        'unit' => $unit,
        'color' => $color,
        'labels' => $labels,
        'data' => $values,
        'timestamps' => $timestamps,
        'data_points' => count($values)
    ],
    'current' => [
        'power' => round((float)($currentData['power'] ?? 0), 1),
        'voltage' => round((float)($currentData['voltage'] ?? 0), 0),
        'current' => round((float)($currentData['current'] ?? 0), 3),
        'energy_today' => round((float)($currentData['energy_today'] ?? 0), 3),
        'energy_total' => round((float)($currentData['energy_total'] ?? 0), 3),
        'timestamp' => $currentData['timestamp'] ?? null,
        'seconds_ago' => (int)($currentData['seconds_ago'] ?? 999),
        'is_live' => ((int)($currentData['seconds_ago'] ?? 999)) < 300 // < 5 Min = Live
    ],
    'meta' => [
        'generated_at' => date('Y-m-d H:i:s'),
        'query_time_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2)
    ]
];

jsonResponse($response, 200);
?>