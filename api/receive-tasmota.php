<?php
// api/receive-tasmota.php  
// Web-API Endpoint der Tasmota-Daten vom lokalen Collector empfängt

require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Nur POST-Requests erlaubt']);
    exit;
}

// JSON-Daten einlesen
$jsonInput = file_get_contents('php://input');
$data = json_decode($jsonInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültiges JSON']);
    exit;
}

class TasmotaWebReceiver {
    
    private $validApiKeys = [
        'iuagsfggiguifOUHFOÖihafoöGBADigÖFILUGiöfugh', // Gleicher Key wie im Collector
        // Weitere Keys für verschiedene Haushalte/Benutzer
    ];
    
    /**
     * Empfangene Tasmota-Daten verarbeiten
     */
    public function processReceivedData($data) {
        // API-Key validieren
        if (!$this->validateApiKey($data['api_key'] ?? '')) {
            http_response_code(401);
            return ['error' => 'Ungültiger API-Key'];
        }
        
        $userId = (int)($data['user_id'] ?? 0);
        
        if ($userId <= 0) {
            return ['error' => 'Ungültige User-ID'];
        }
        
        // Benutzer existiert prüfen
        $user = Database::fetchOne("SELECT id FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return ['error' => 'Benutzer nicht gefunden'];
        }
        
        $devices = $data['devices'] ?? [];
        
        if (empty($devices)) {
            return ['error' => 'Keine Gerätedaten empfangen'];
        }
        
        $processed = 0;
        $errors = [];
        
        foreach ($devices as $deviceData) {
            try {
                $result = $this->processDeviceData($userId, $deviceData);
                if ($result['success']) {
                    $processed++;
                } else {
                    $errors[] = $result['error'];
                }
            } catch (Exception $e) {
                $errors[] = "Gerät {$deviceData['device_name']}: " . $e->getMessage();
            }
        }
        
        // Erfolgs-Log speichern
        $this->logReceive($userId, count($devices), $processed, $data['collector_info'] ?? []);
        
        return [
            'success' => true,
            'processed' => $processed,
            'total' => count($devices),
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Einzelnes Gerät verarbeiten
     */
    private function processDeviceData($userId, $deviceData) {
        $deviceName = $deviceData['device_name'] ?? '';
        $deviceIP = $deviceData['device_ip'] ?? '';
        $energyData = $deviceData['energy_data'] ?? [];
        $timestamp = $deviceData['timestamp'] ?? date('Y-m-d H:i:s');
        
        if (empty($deviceName) || empty($energyData)) {
            return ['success' => false, 'error' => 'Unvollständige Gerätedaten'];
        }
        
        // Gerät in Datenbank finden oder erstellen
        $device = $this->findOrCreateDevice($userId, $deviceName, $deviceIP);
        
        if (!$device) {
            return ['success' => false, 'error' => 'Gerät konnte nicht erstellt werden'];
        }
        
        // Energiedaten speichern
        $this->saveEnergyData($device['id'], $userId, $energyData, $timestamp);
        
        // Geräte-Info aktualisieren
        Database::execute(
            "UPDATE devices SET 
             last_tasmota_reading = ?, 
             tasmota_ip = COALESCE(tasmota_ip, ?),
             wattage = COALESCE(NULLIF(wattage, 0), ?)
             WHERE id = ?",
            [
                $timestamp,
                $deviceIP,
                max(1, (int)($energyData['power'] ?? 0)),
                $device['id']
            ]
        );
        
        return ['success' => true];
    }
    
    /**
     * Gerät finden oder automatisch erstellen
     */
    private function findOrCreateDevice($userId, $deviceName, $deviceIP) {
        // Zuerst nach Name suchen
        $device = Database::fetchOne(
            "SELECT * FROM devices WHERE user_id = ? AND name = ?",
            [$userId, $deviceName]
        );
        
        if ($device) {
            return $device;
        }
        
        // Nach IP suchen
        if ($deviceIP) {
            $device = Database::fetchOne(
                "SELECT * FROM devices WHERE user_id = ? AND tasmota_ip = ?",
                [$userId, $deviceIP]
            );
            
            if ($device) {
                // Name aktualisieren falls unterschiedlich
                if ($device['name'] !== $deviceName) {
                    Database::execute(
                        "UPDATE devices SET name = ? WHERE id = ?",
                        [$deviceName, $device['id']]
                    );
                    $device['name'] = $deviceName;
                }
                return $device;
            }
        }
        
        // Neues Gerät erstellen
        $deviceId = Database::insert('devices', [
            'user_id' => $userId,
            'name' => $deviceName,
            'category' => 'Smart Home',
            'wattage' => 100, // Default, wird später aus Messdaten aktualisiert
            'tasmota_ip' => $deviceIP,
            'tasmota_enabled' => 1,
            'tasmota_name' => $deviceName,
            'is_active' => 1
        ]);
        
        if ($deviceId) {
            return [
                'id' => $deviceId,
                'name' => $deviceName,
                'user_id' => $userId
            ];
        }
        
        return null;
    }
    
    /**
     * Energiedaten in Datenbank speichern
     */
    private function saveEnergyData($deviceId, $userId, $energyData, $timestamp) {
        // Rohdaten in tasmota_readings speichern
        Database::execute(
            "INSERT INTO tasmota_readings (device_id, user_id, timestamp, voltage, current, power, 
             apparent_power, reactive_power, power_factor, energy_today, energy_yesterday, energy_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $deviceId, $userId, $timestamp,
                $energyData['voltage'], $energyData['current'], $energyData['power'],
                $energyData['apparent_power'], $energyData['reactive_power'], $energyData['power_factor'],
                $energyData['energy_today'], $energyData['energy_yesterday'], $energyData['energy_total']
            ]
        );
        
        // Tageseintrag in meter_readings prüfen/aktualisieren
        $today = date('Y-m-d', strtotime($timestamp));
        
        $existingReading = Database::fetchOne(
            "SELECT * FROM meter_readings 
             WHERE user_id = ? AND device_id = ? AND DATE(reading_date) = ?
             ORDER BY reading_date DESC LIMIT 1",
            [$userId, $deviceId, $today]
        );
        
        // Aktuellen Strompreis holen
        $tariff = Database::fetchOne(
            "SELECT rate_per_kwh FROM tariff_periods 
             WHERE user_id = ? AND is_active = 1 ORDER BY valid_from DESC LIMIT 1",
            [$userId]
        ) ?: ['rate_per_kwh' => 0.32];
        
        $todayConsumption = (float)($energyData['energy_today'] ?? 0);
        
        if ($existingReading && $todayConsumption > 0) {
            // Update wenn sich Verbrauch geändert hat
            $oldConsumption = (float)$existingReading['consumption'];
            
            if (abs($todayConsumption - $oldConsumption) > 0.001) { // 1Wh Mindestdifferenz
                Database::execute(
                    "UPDATE meter_readings SET 
                     consumption = ?, cost = ?, rate_per_kwh = ?, reading_date = ?
                     WHERE id = ?",
                    [
                        $todayConsumption,
                        $todayConsumption * $tariff['rate_per_kwh'],
                        $tariff['rate_per_kwh'],
                        $timestamp,
                        $existingReading['id']
                    ]
                );
            }
        } elseif ($todayConsumption > 0) {
            // Neuer Tageseintrag
            Database::execute(
                "INSERT INTO meter_readings (user_id, device_id, reading_date, meter_value, 
                 consumption, cost, rate_per_kwh) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId, $deviceId, $timestamp,
                    $energyData['energy_total'] ?? $todayConsumption,
                    $todayConsumption,
                    $todayConsumption * $tariff['rate_per_kwh'],
                    $tariff['rate_per_kwh']
                ]
            );
        }
    }
    
    /**
     * API-Key validieren
     */
    private function validateApiKey($apiKey) {
        return in_array($apiKey, $this->validApiKeys, true);
    }
    
    /**
     * Empfang protokollieren
     */
    private function logReceive($userId, $totalDevices, $processedDevices, $collectorInfo) {
        // Einfaches Log in Datei
        $logEntry = sprintf(
            "[%s] User %d: %d/%d Geräte verarbeitet. Collector: %s\n",
            date('Y-m-d H:i:s'),
            $userId,
            $processedDevices,
            $totalDevices,
            $collectorInfo['collector_ip'] ?? 'unknown'
        );
        
        file_put_contents('logs/tasmota-receive.log', $logEntry, FILE_APPEND | LOCK_EX);
        
        // Optional: In Datenbank-Tabelle speichern
        /*
        Database::execute(
            "INSERT INTO tasmota_receive_log (user_id, total_devices, processed_devices, 
             collector_ip, collector_version) VALUES (?, ?, ?, ?, ?)",
            [
                $userId, $totalDevices, $processedDevices,
                $collectorInfo['collector_ip'] ?? null,
                $collectorInfo['version'] ?? null
            ]
        );
        */
    }
}

// =============================================================================
// AUSFÜHRUNG
// =============================================================================

try {
    $receiver = new TasmotaWebReceiver();
    $result = $receiver->processReceivedData($data);
    
    if (isset($result['error'])) {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server-Fehler: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // Fehler protokollieren
    error_log("Tasmota Receive Error: " . $e->getMessage());
}

// =============================================================================
// SETUP-HINWEISE
// =============================================================================

/*

1. Log-Ordner erstellen:
   mkdir logs
   chmod 755 logs

2. API-Key anpassen:
   - Ändern Sie 'IHR_GEHEIMER_API_SCHLUESSEL' zu einem sicheren Key
   - Gleichen Key im local-collector.php verwenden

3. Für mehrere Benutzer:
   - Verschiedene API-Keys für verschiedene Haushalte
   - User-ID entsprechend anpassen

4. Optional: Log-Tabelle erstellen:
   CREATE TABLE tasmota_receive_log (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT NOT NULL,
       total_devices INT NOT NULL,
       processed_devices INT NOT NULL,
       collector_ip VARCHAR(45),
       collector_version VARCHAR(20),
       received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

*/