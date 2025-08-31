<?php
// api/live-device-data.php
// Live-Gerätedaten für Auto-Refresh ohne Seitenreload

require_once '../config/database.php';
require_once '../config/session.php';

// Nur GET-Requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

// Login erforderlich
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authentifiziert']);
    exit;
}

$userId = Auth::getUserId();

try {
    // Alle Tasmota-Geräte des Benutzers laden
    $devices = Database::fetchAll(
        "SELECT id, name, wattage, tasmota_ip, last_tasmota_reading 
         FROM devices 
         WHERE user_id = ? AND tasmota_enabled = 1 AND is_active = 1",
        [$userId]
    );

    if (empty($devices)) {
        echo json_encode(['success' => true, 'devices' => []]);
        exit;
    }

    $deviceIds = array_column($devices, 'id');
    $placeholders = str_repeat('?,', count($deviceIds) - 1) . '?';

    // Neueste Tasmota-Messdaten laden
    $readings = Database::fetchAll(
        "SELECT device_id, voltage, current, power, energy_today, energy_yesterday, 
                energy_total, timestamp,
                TIMESTAMPDIFF(MINUTE, timestamp, DATE_ADD(NOW(), INTERVAL 2 HOUR)) as minutes_ago
         FROM tasmota_readings 
         WHERE device_id IN ({$placeholders})
         AND timestamp >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
         ORDER BY device_id, timestamp DESC",
        $deviceIds
    );

    // Neueste Messung pro Gerät gruppieren
    $latestByDevice = [];
    foreach ($readings as $reading) {
        $deviceId = $reading['device_id'];
        if (!isset($latestByDevice[$deviceId])) {
            $latestByDevice[$deviceId] = $reading;
        }
    }

    // Response für jedes Gerät vorbereiten
    $deviceData = [];
    
    foreach ($devices as $device) {
        $deviceId = $device['id'];
        $reading = $latestByDevice[$deviceId] ?? null;
        
        $status = 'unknown';
        if ($reading) {
            $minutesAgo = (int)$reading['minutes_ago'];
            if ($minutesAgo <= 10) {
                $status = 'online';
            } elseif ($minutesAgo <= 60) {
                $status = 'warning';
            } else {
                $status = 'offline';
            }
        }
        
        $deviceData[$deviceId] = [
            'name' => $device['name'],
            'configured_wattage' => (int)$device['wattage'],
            'status' => $status,
            'last_update' => $device['last_tasmota_reading'],
            'minutes_ago' => $reading ? (int)$reading['minutes_ago'] : null,
            'power' => $reading ? (float)$reading['power'] : null,
            'current' => $reading ? [
                'power' => number_format((float)$reading['power'], 0),
                'voltage' => number_format((float)$reading['voltage'], 0),
                'current' => number_format((float)$reading['current'], 2),
                'energy_today' => number_format((float)$reading['energy_today'], 3),
                'timestamp' => $reading['timestamp']
            ] : null
        ];
    }

    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'devices' => $deviceData,
        'total_devices' => count($devices),
        'devices_with_data' => count($latestByDevice)
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Live device data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Serverfehler beim Laden der Live-Daten']);
}
