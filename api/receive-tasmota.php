<?php
// api/receive-tasmota.php  
// Web-API Endpoint der Tasmota-Daten vom lokalen Collector empfängt
// ✅ SICHER: API-Keys aus Datenbank validieren (nicht hardcodiert!)

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
    
    /**
     * ✅ SICHER: API-Key gegen Datenbank validieren
     */
    private function validateApiKey($providedKey) {
        if (empty($providedKey)) {
            return false;
        }
        
        // API-Key Format prüfen (st_xxxxxxxxx...)
        if (!preg_match('/^st_[a-f0-9]{60}$/', $providedKey)) {
            return false;
        }
        
        // In Datenbank suchen
        $user = Database::fetchOne(
            "SELECT id, name, email FROM users WHERE api_key = ? AND api_key IS NOT NULL", 
            [$providedKey]
        );
        
        return $user !== false ? $user : false;
    }
    
    /**
     * Empfangene Tasmota-Daten verarbeiten
     */
    public function processReceivedData($data) {
        $providedApiKey = $data['api_key'] ?? '';
        
        // API-Key validieren und Benutzer ermitteln
        $keyOwner = $this->validateApiKey($providedApiKey);
        if (!$keyOwner) {
            $this->logUnauthorizedAttempt($providedApiKey, $_SERVER['REMOTE_ADDR']);
            http_response_code(401);
            return ['error' => 'Ungültiger API-Key'];
        }
        
        // Benutzer-ID aus API-Key-Validierung verwenden (sicherer!)
        $userId = $keyOwner['id'];
        
        // Optionale user_id aus Request validieren (falls angegeben)
        $requestUserId = (int)($data['user_id'] ?? 0);
        if ($requestUserId > 0 && $requestUserId !== $userId) {
            $this->logSuspiciousActivity($userId, 'User-ID mismatch', $data);
            return ['error' => 'User-ID stimmt nicht mit API-Key überein'];
        }
        
        $devices = $data['devices'] ?? [];
        
        // Legacy-Format unterstützen (einzelnes Gerät)
        if (empty($devices) && isset($data['device_name'])) {
            $devices = [$data]; // Einzelnes Gerät als Array
        }
        
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
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $keyOwner['name'] ?? 'Unbekannt'
        ];
    }
    
    /**
     * Einzelnes Gerät verarbeiten
     */
    private function processDeviceData($userId, $deviceData) {
        $deviceName = $deviceData['device_name'] ?? '';
        $deviceIP = $deviceData['device_ip'] ?? '';
        
        if (empty($deviceName)) {
            return ['success' => false, 'error' => 'Gerätename fehlt'];
        }
        
        // Gerät in Datenbank finden oder erstellen
        $device = Database::fetchOne(
            "SELECT id FROM devices WHERE user_id = ? AND (name = ? OR tasmota_ip = ?) LIMIT 1",
            [$userId, $deviceName, $deviceIP]
        );
        
        if (!$device) {
            // Neues Gerät automatisch erstellen
            $deviceId = Database::insert('devices', [
                'user_id' => $userId,
                'name' => $deviceName,
                'category' => 'Smart Home',
                'wattage' => (int)($deviceData['power'] ?? 0),
                'tasmota_ip' => $deviceIP,
                'tasmota_name' => $deviceName,
                'tasmota_enabled' => true,
                'is_active' => true
            ]);
        } else {
            $deviceId = $device['id'];
            
            // Gerät-Info aktualisieren
            Database::update('devices', [
                'tasmota_ip' => $deviceIP,
                'last_tasmota_reading' => date('Y-m-d H:i:s'),
                'wattage' => (int)($deviceData['power'] ?? 0)
            ], 'id = ?', [$deviceId]);
        }
        
        // Tasmota-Rohdaten speichern (falls Tabelle existiert)
        if (Database::tableExists('tasmota_readings')) {
            try {
                $readingData = [
                    'device_id' => $deviceId,
                    'user_id' => $userId,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'voltage' => $deviceData['voltage'] ?? null,
                    'current' => $deviceData['current'] ?? null,
                    'power' => $deviceData['power'] ?? null,
                    'apparent_power' => $deviceData['apparent_power'] ?? null,
                    'reactive_power' => $deviceData['reactive_power'] ?? null,
                    'power_factor' => $deviceData['power_factor'] ?? null,
                    'energy_today' => $deviceData['energy_today'] ?? null,
                    'energy_yesterday' => $deviceData['energy_yesterday'] ?? null,
                    'energy_total' => $deviceData['energy_total'] ?? null
                ];
                
                Database::insert('tasmota_readings', $readingData);
            } catch (Exception $e) {
                error_log("Tasmota readings insert error: " . $e->getMessage());
            }
        }
        
        return [
            'success' => true,
            'device_id' => $deviceId,
            'device_name' => $deviceName
        ];
    }
    
    /**
     * Erfolgreiche Datenübertragung loggen
     */
    private function logReceive($userId, $deviceCount, $processed, $collectorInfo) {
        try {
            Database::insert('api_logs', [
                'user_id' => $userId,
                'endpoint' => 'receive-tasmota',
                'method' => 'POST',
                'status' => 'success',
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'request_data' => json_encode([
                    'device_count' => $deviceCount,
                    'processed' => $processed,
                    'collector' => $collectorInfo
                ])
            ]);
        } catch (Exception $e) {
            // Log-Tabelle existiert nicht - ignorieren
        }
    }
    
    /**
     * Unbefugte Zugriffe loggen
     */
    private function logUnauthorizedAttempt($providedKey, $ip) {
        error_log("STROMTRACKER SECURITY: Unauthorized API attempt from {$ip} with key: " . 
                 substr($providedKey, 0, 10) . "...");
        
        try {
            Database::insert('security_logs', [
                'event_type' => 'unauthorized_api_access',
                'ip_address' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'details' => json_encode([
                    'provided_key' => substr($providedKey, 0, 10) . '...',
                    'endpoint' => 'receive-tasmota.php'
                ])
            ]);
        } catch (Exception $e) {
            // Security-Log-Tabelle existiert nicht - Error-Log verwenden
        }
    }
    
    /**
     * Verdächtige Aktivitäten loggen
     */
    private function logSuspiciousActivity($userId, $reason, $data) {
        error_log("STROMTRACKER SECURITY: Suspicious activity from User {$userId}: {$reason}");
        
        try {
            Database::insert('security_logs', [
                'user_id' => $userId,
                'event_type' => 'suspicious_api_activity',
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'details' => json_encode([
                    'reason' => $reason,
                    'request_data' => $data
                ])
            ]);
        } catch (Exception $e) {
            // Log-Tabelle existiert nicht - ignorieren
        }
    }
}

// =============================================================================
// REQUEST PROCESSING
// =============================================================================

try {
    $receiver = new TasmotaWebReceiver();
    $result = $receiver->processReceivedData($data);
    
    // Erfolgreiche Antwort
    http_response_code(200);
    echo json_encode($result);
    
} catch (Exception $e) {
    // Fehler-Antwort
    http_response_code(500);
    echo json_encode([
        'error' => 'Server-Fehler beim Verarbeiten der Daten',
        'details' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // Fehler loggen
    error_log("STROMTRACKER API ERROR: " . $e->getMessage());
}
?>