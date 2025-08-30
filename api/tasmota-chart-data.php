<?php
// api/tasmota-chart-data.php
// Einfache Chart-API für Tasmota-Geräte

require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// Login erforderlich
if (!Auth::isLoggedIn()) {
    echo json_encode(['error' => 'Nicht authentifiziert']);
    exit;
}

$userId = Auth::getUserId();
$deviceId = (int)($_GET['device_id'] ?? 0);
$timeRange = $_GET['timerange'] ?? '60';
$chartType = $_GET['type'] ?? 'power';

if ($deviceId <= 0) {
    echo json_encode(['error' => 'Ungültige Gerät-ID']);
    exit;
}

// Gerät prüfen
$device = Database::fetchOne(
    "SELECT * FROM devices WHERE id = ? AND user_id = ? AND tasmota_enabled = 1",
    [$deviceId, $userId]
);

if (!$device) {
    echo json_encode(['error' => 'Gerät nicht gefunden']);
    exit;
}

// Zeitraum
$minutes = (int)$timeRange;
$startTime = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));

// Chart-Daten laden
$readings = Database::fetchAll(
    "SELECT timestamp, voltage, current, power, energy_today
     FROM tasmota_readings
     WHERE device_id = ? AND timestamp >= ?
     ORDER BY timestamp ASC",
    [$deviceId, $startTime]
);

// Aktuelle Werte
$currentReading = Database::fetchOne(
    "SELECT * FROM tasmota_readings
     WHERE device_id = ?
     ORDER BY timestamp DESC
     LIMIT 1",
    [$deviceId]
);

// Chart-Konfiguration
$config = [
    'power' => ['unit' => 'W', 'color' => '#f59e0b', 'label' => 'Leistung'],
    'voltage' => ['unit' => 'V', 'color' => '#3b82f6', 'label' => 'Spannung'],
    'current' => ['unit' => 'A', 'color' => '#ef4444', 'label' => 'Stromstärke'],
    'energy' => ['unit' => 'kWh', 'color' => '#10b981', 'label' => 'Energie']
][$chartType] ?? ['unit' => 'W', 'color' => '#f59e0b', 'label' => 'Leistung'];

// Daten formatieren
$labels = [];
$data = [];

foreach ($readings as $reading) {
    $labels[] = date('H:i', strtotime($reading['timestamp']));
    
    switch ($chartType) {
        case 'power':
            $data[] = (float)$reading['power'];
            break;
        case 'voltage':
            $data[] = (float)$reading['voltage'];
            break;
        case 'current':
            $data[] = (float)$reading['current'];
            break;
        case 'energy':
            $data[] = (float)$reading['energy_today'];
            break;
        default:
            $data[] = (float)$reading['power'];
    }
}

// Response
echo json_encode([
    'success' => true,
    'chart' => [
        'type' => $config['label'],
        'unit' => $config['unit'],
        'color' => $config['color'],
        'labels' => $labels,
        'data' => $data,
        'datapoints' => count($data)
    ],
    'current' => $currentReading ? [
        'power' => number_format((float)$currentReading['power'], 0),
        'voltage' => number_format((float)$currentReading['voltage'], 0),
        'current' => number_format((float)$currentReading['current'], 2),
        'energy_today' => number_format((float)$currentReading['energy_today'], 3)
    ] : null
]);
