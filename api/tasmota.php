<?php
// api/tasmota.php
// Tasmota Smart-Steckdosen API Integration

require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// CORS für lokale Anfragen
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class TasmotaAPI {
    
    /**
     * Tasmota-Gerät über HTTP abfragen
     */
    public static function queryDevice($ip, $command = 'Status 8') {
        $url = "http://{$ip}/cm?cmnd=" . urlencode($command);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return ['error' => 'Verbindung fehlgeschlagen', 'ip' => $ip];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Ungültige JSON-Antwort', 'raw' => $response];
        }
        
        return $data;
    }
    
    /**
     * Energiedaten aus Tasmota-Antwort extrahieren
     */
    public static function parseEnergyData($data) {
        if (isset($data['StatusSNS']['ENERGY'])) {
            $energy = $data['StatusSNS']['ENERGY'];
            
            return [
                'voltage' => $energy['Voltage'] ?? null,
                'current' => $energy['Current'] ?? null,
                'power' => $energy['Power'] ?? null,
                'apparent_power' => $energy['ApparentPower'] ?? null,
                'reactive_power' => $energy['ReactivePower'] ?? null,
                'power_factor' => $energy['Factor'] ?? null,
                'energy_today' => $energy['Today'] ?? null,
                'energy_yesterday' => $energy['Yesterday'] ?? null,
                'energy_total' => $energy['Total'] ?? null,
                'period' => $energy['Period'] ?? null
            ];
        }
        
        return null;
    }
    
    /**
     * Alle aktiven Tasmota-Geräte abfragen
     */
    public static function collectAllDevices($userId) {
        $devices = Database::fetchAll(
            "SELECT * FROM devices WHERE user_id = ? AND tasmota_enabled = 1 AND is_active = 1",
            [$userId]
        );
        
        $results = [];
        
        foreach ($devices as $device) {
            if (empty($device['tasmota_ip'])) continue;
            
            $data = self::queryDevice($device['tasmota_ip']);
            $energyData = self::parseEnergyData($data);
            
            if ($energyData) {
                // In Datenbank speichern
                self::saveReading($device['id'], $userId, $energyData);
                
                // Letzten Abruf aktualisieren
                Database::execute(
                    "UPDATE devices SET last_tasmota_reading = NOW() WHERE id = ?",
                    [$device['id']]
                );
            }
            
            $results[] = [
                'device' => $device,
                'data' => $energyData,
                'error' => $data['error'] ?? null
            ];
        }
        
        return $results;
    }
    
    /**
     * Tasmota-Messung in Datenbank speichern
     */
    private static function saveReading($deviceId, $userId, $energyData) {
        // Rohdaten speichern (für Debugging)
        Database::execute(
            "INSERT INTO tasmota_readings (device_id, user_id, voltage, current, power, 
             apparent_power, reactive_power, power_factor, energy_today, energy_yesterday, energy_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $deviceId, $userId,
                $energyData['voltage'], $energyData['current'], $energyData['power'],
                $energyData['apparent_power'], $energyData['reactive_power'], $energyData['power_factor'],
                $energyData['energy_today'], $energyData['energy_yesterday'], $energyData['energy_total']
            ]
        );
        
        // Prüfen ob heute schon ein Eintrag für dieses Gerät existiert
        $existingReading = Database::fetchOne(
            "SELECT * FROM meter_readings 
             WHERE user_id = ? AND device_id = ? AND DATE(reading_date) = CURDATE()
             ORDER BY reading_date DESC LIMIT 1",
            [$userId, $deviceId]
        );
        
        // Aktuellen Strompreis holen
        $tariff = Database::fetchOne(
            "SELECT rate_per_kwh FROM tariff_periods 
             WHERE user_id = ? AND is_active = 1 ORDER BY valid_from DESC LIMIT 1",
            [$userId]
        ) ?: ['rate_per_kwh' => 0.32];
        
        if ($existingReading) {
            // Update: Nur wenn sich energy_today geändert hat
            $newConsumption = (float)$energyData['energy_today'];
            $oldConsumption = (float)$existingReading['consumption'];
            
            if (abs($newConsumption - $oldConsumption) > 0.001) { // 1Wh Mindestdifferenz
                Database::execute(
                    "UPDATE meter_readings SET 
                     consumption = ?, cost = ?, rate_per_kwh = ?, reading_date = NOW()
                     WHERE id = ?",
                    [
                        $newConsumption,
                        $newConsumption * $tariff['rate_per_kwh'],
                        $tariff['rate_per_kwh'],
                        $existingReading['id']
                    ]
                );
            }
        } else {
            // Neuer Eintrag für heute
            if ($energyData['energy_today'] > 0) {
                Database::execute(
                    "INSERT INTO meter_readings (user_id, device_id, reading_date, meter_value, 
                     consumption, cost, rate_per_kwh) VALUES (?, ?, NOW(), ?, ?, ?, ?)",
                    [
                        $userId, $deviceId,
                        $energyData['energy_total'] ?? $energyData['energy_today'],
                        $energyData['energy_today'],
                        $energyData['energy_today'] * $tariff['rate_per_kwh'],
                        $tariff['rate_per_kwh']
                    ]
                );
            }
        }
    }
    
    /**
     * Tasmota-Gerät ein/ausschalten
     */
    public static function setPower($ip, $state) {
        $command = $state ? 'Power ON' : 'Power OFF';
        return self::queryDevice($ip, $command);
    }
}

// API Endpunkte
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    $action = $_GET['action'] ?? '';
    $userId = Auth::getUserId();
    
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Nicht authentifiziert']);
        exit;
    }
    
    switch ($action) {
        case 'test':
            // Einzelnes Gerät testen
            $ip = $_GET['ip'] ?? '';
            if (!$ip) {
                echo json_encode(['error' => 'IP-Adresse fehlt']);
                break;
            }
            
            $data = TasmotaAPI::queryDevice($ip);
            $energy = TasmotaAPI::parseEnergyData($data);
            
            echo json_encode([
                'success' => true,
                'raw_data' => $data,
                'energy_data' => $energy,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'collect':
            // Alle Geräte abfragen
            $results = TasmotaAPI::collectAllDevices($userId);
            echo json_encode([
                'success' => true,
                'results' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'power':
            // Gerät ein/ausschalten
            $ip = $_GET['ip'] ?? '';
            $state = $_GET['state'] ?? '1';
            
            if (!$ip) {
                echo json_encode(['error' => 'IP-Adresse fehlt']);
                break;
            }
            
            $result = TasmotaAPI::setPower($ip, $state === '1');
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['error' => 'Unbekannte Aktion']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Automatische Datenerfassung (für Cron-Jobs)
    $userId = $_POST['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['error' => 'User ID fehlt']);
        exit;
    }
    
    $results = TasmotaAPI::collectAllDevices($userId);
    
    echo json_encode([
        'success' => true,
        'collected' => count($results),
        'results' => $results
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
}