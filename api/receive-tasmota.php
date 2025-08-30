<?php
// api/receive-tasmota.php  
// ✅ FINALE VERSION - Direkte lokale Zeit (keine Konvertierung)

require_once '../config/database.php';
require_once '../config/session.php';

error_reporting(E_ALL);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Logs-Ordner erstellen
$logsDir = '../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Debug-Logging
function debugLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}";
    if ($data !== null) {
        $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $logEntry .= PHP_EOL;
    file_put_contents('../logs/tasmota-debug.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// ✅ FIX: Check if function exists to prevent redeclaration
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Basic request validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("ERROR: Non-POST request", ['method' => $_SERVER['REQUEST_METHOD']]);
    jsonResponse(['error' => 'Nur POST-Requests erlaubt'], 405);
}

// JSON parsing
$jsonInput = file_get_contents('php://input');
debugLog("Raw JSON input received", ['length' => strlen($jsonInput)]);

$data = json_decode($jsonInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    debugLog("ERROR: JSON decode failed", ['error' => json_last_error_msg()]);
    jsonResponse(['error' => 'Ungültiges JSON'], 400);
}

debugLog("Parsed JSON data", $data);

class TasmotaWebReceiver {
    
    /**
     * API-Key validieren
     */
    private function validateApiKey($providedKey) {
        if (empty($providedKey)) {
            debugLog("ERROR: Empty API key provided");
            return false;
        }
        
        if (!preg_match('/^st_[a-f0-9]{60}$/', $providedKey)) {
            debugLog("ERROR: Invalid API key format", ['key' => substr($providedKey, 0, 10) . '...']);
            return false;
        }
        
        $user = Database::fetchOne(
            "SELECT id, name, email FROM users WHERE api_key = ? AND api_key IS NOT NULL", 
            [$providedKey]
        );
        
        if ($user) {
            debugLog("API key validated successfully", ['user_id' => $user['id'], 'user_name' => $user['name']]);
        } else {
            debugLog("ERROR: API key not found in database", ['key' => substr($providedKey, 0, 10) . '...']);
        }
        
        return $user !== false ? $user : false;
    }
    
    /**
     * Hauptverarbeitung
     */
    public function processReceivedData($data) {
        debugLog("Starting data processing", ['data_keys' => array_keys($data)]);
        
        // API-Key validieren
        $providedApiKey = $data['api_key'] ?? '';
        $keyOwner = $this->validateApiKey($providedApiKey);
        if (!$keyOwner) {
            $this->logUnauthorizedAttempt($providedApiKey, $_SERVER['REMOTE_ADDR']);
            return ['error' => 'Ungültiger API-Key'];
        }
        
        $userId = $keyOwner['id'];
        debugLog("User validated", ['user_id' => $userId]);
        
        // Geräte-Daten extrahieren
        $devices = $data['devices'] ?? [];
        if (empty($devices) && isset($data['device_name'])) {
            $devices = [$data];
        }
        
        if (empty($devices)) {
            debugLog("ERROR: No device data received", $data);
            return ['error' => 'Keine Gerätedaten empfangen'];
        }
        
        debugLog("Processing devices", ['device_count' => count($devices)]);
        
        $processed = 0;
        $errors = [];
        
        foreach ($devices as $index => $deviceData) {
            debugLog("Processing device {$index}", $deviceData);
            
            try {
                $result = $this->processDeviceData($userId, $deviceData);
                if ($result['success']) {
                    $processed++;
                    debugLog("Device {$index} processed successfully");
                } else {
                    $errors[] = $result['error'];
                    debugLog("Device {$index} processing failed", ['error' => $result['error']]);
                }
            } catch (Exception $e) {
                $errors[] = "Gerät {$deviceData['device_name']}: " . $e->getMessage();
                debugLog("Device {$index} processing exception", ['error' => $e->getMessage()]);
            }
        }
        
        $this->logReceive($userId, count($devices), $processed, $data['collector_info'] ?? []);
        
        $result = [
            'success' => true,
            'processed' => $processed,
            'total' => count($devices),
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $keyOwner['name'] ?? 'Unbekannt'
        ];
        
        debugLog("Processing completed", $result);
        return $result;
    }
    
    /**
     * Einzelnes Gerät verarbeiten
     */
    private function processDeviceData($userId, $deviceData) {
        $deviceName = $deviceData['device_name'] ?? '';
        $deviceIP = $deviceData['device_ip'] ?? '';
        
        debugLog("Processing single device", ['name' => $deviceName, 'ip' => $deviceIP]);
        
        if (empty($deviceName)) {
            return ['success' => false, 'error' => 'Gerätename fehlt'];
        }
        
        // Gerät finden oder erstellen
        $device = $this->findOrCreateDevice($userId, $deviceName, $deviceIP);
        if (!$device) {
            return ['success' => false, 'error' => 'Gerät konnte nicht erstellt werden'];
        }
        
        $deviceId = $device['id'];
        debugLog("Device found/created", ['device_id' => $deviceId]);
        
        // ✅ FINAL: Tasmota sendet deutsche Zeit - direkt verwenden
        $timestamp = $deviceData['timestamp'] ?? $deviceData['reading_time'] ?? date('Y-m-d H:i:s');
        
        debugLog("Using timestamp from Tasmota", [
            'timestamp' => $timestamp
        ]);
        
        // Gerät aktualisieren
        $updateData = [
            'last_tasmota_reading' => $timestamp
        ];
        
        if (!empty($deviceIP)) {
            $updateData['tasmota_ip'] = $deviceIP;
        }
        
        $energyData = $deviceData['energy_data'] ?? $deviceData;
        if (isset($energyData['power']) && $energyData['power'] > 0) {
            $updateData['wattage'] = max(1, (int)$energyData['power']);
        }
        
        Database::update('devices', $updateData, 'id = ?', [$deviceId]);
        debugLog("Device updated", $updateData);
        
        // Tasmota-Rohdaten speichern
        $this->saveTasmotaReadings($deviceId, $userId, $deviceData, $timestamp);
        
        return [
            'success' => true,
            'device_id' => $deviceId,
            'device_name' => $deviceName,
            'timestamp' => $timestamp
        ];
    }
    
    /**
     * Tasmota-Rohdaten speichern
     */
    private function saveTasmotaReadings($deviceId, $userId, $deviceData, $timestamp) {
        try {
            $exists = Database::fetchOne("SHOW TABLES LIKE 'tasmota_readings'");
            if (!$exists) {
                debugLog("WARNING: tasmota_readings table does not exist");
                return;
            }
        } catch (Exception $e) {
            debugLog("WARNING: Could not check tasmota_readings table", ['error' => $e->getMessage()]);
            return;
        }
        
        $energyData = $deviceData['energy_data'] ?? $deviceData;
        debugLog("Saving tasmota readings", ['energy_data' => $energyData, 'timestamp' => $timestamp]);
        
        try {
            $readingData = [
                'device_id' => $deviceId,
                'user_id' => $userId,
                'timestamp' => $timestamp, // ✅ Tasmota sendet bereits deutsche Zeit
                'voltage' => $this->extractFloat($energyData, 'voltage'),
                'current' => $this->extractFloat($energyData, 'current'),
                'power' => $this->extractFloat($energyData, 'power'),
                'apparent_power' => $this->extractFloat($energyData, 'apparent_power'),
                'reactive_power' => $this->extractFloat($energyData, 'reactive_power'),
                'power_factor' => $this->extractFloat($energyData, 'power_factor'),
                'energy_today' => $this->extractFloat($energyData, 'energy_today'),
                'energy_yesterday' => $this->extractFloat($energyData, 'energy_yesterday'),
                'energy_total' => $this->extractFloat($energyData, 'energy_total')
            ];
            
            $insertId = Database::insert('tasmota_readings', $readingData);
            debugLog("Tasmota readings saved", ['insert_id' => $insertId]);
            
        } catch (Exception $e) {
            debugLog("ERROR: Exception saving tasmota readings", ['error' => $e->getMessage()]);
        }
    }
    
    private function extractFloat($data, $key) {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        return (float)$value;
    }
    
    private function findOrCreateDevice($userId, $deviceName, $deviceIP) {
        debugLog("Finding or creating device", ['name' => $deviceName, 'ip' => $deviceIP]);
        
        // Nach Name suchen
        $device = Database::fetchOne(
            "SELECT * FROM devices WHERE user_id = ? AND name = ?",
            [$userId, $deviceName]
        );
        
        if ($device) {
            debugLog("Device found by name", ['device_id' => $device['id']]);
            return $device;
        }
        
        // Nach IP suchen
        if (!empty($deviceIP)) {
            $device = Database::fetchOne(
                "SELECT * FROM devices WHERE user_id = ? AND tasmota_ip = ?",
                [$userId, $deviceIP]
            );
            
            if ($device) {
                debugLog("Device found by IP", ['device_id' => $device['id']]);
                return $device;
            }
        }
        
        // Neues Gerät erstellen
        $deviceData = [
            'user_id' => $userId,
            'name' => $deviceName,
            'category' => 'Tasmota',
            'wattage' => 50,
            'tasmota_enabled' => 1,
            'tasmota_ip' => $deviceIP ?: null,
            'tasmota_name' => $deviceName,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'last_tasmota_reading' => date('Y-m-d H:i:s')
        ];
        
        $deviceId = Database::insert('devices', $deviceData);
        
        if ($deviceId) {
            debugLog("New device created", ['device_id' => $deviceId]);
            return Database::fetchOne("SELECT * FROM devices WHERE id = ?", [$deviceId]);
        }
        
        debugLog("ERROR: Failed to create device");
        return null;
    }
    
    private function logReceive($userId, $deviceCount, $processed, $collectorInfo) {
        if (function_exists('logMessage')) {
            logMessage(
                "Tasmota API: User {$userId} - {$processed}/{$deviceCount} Geräte verarbeitet",
                'info',
                'tasmota-api.log'
            );
        }
    }
    
    private function logUnauthorizedAttempt($providedKey, $ip) {
        debugLog("SECURITY: Unauthorized access attempt", ['ip' => $ip, 'key' => substr($providedKey, 0, 10) . '...']);
    }
}

// =============================================================================
// MAIN PROCESSING
// =============================================================================

try {
    debugLog("=== NEW REQUEST STARTING ===", ['ip' => $_SERVER['REMOTE_ADDR']]);
    
    $receiver = new TasmotaWebReceiver();
    $result = $receiver->processReceivedData($data);
    
    if (isset($result['error'])) {
        debugLog("Request failed with error", ['error' => $result['error']]);
        jsonResponse($result, 400);
    } else {
        debugLog("Request completed successfully", $result);
        jsonResponse($result, 200);
    }
    
} catch (Exception $e) {
    debugLog("FATAL ERROR: Exception in main processing", [
        'error' => $e->getMessage(), 
        'trace' => $e->getTraceAsString()
    ]);
    
    jsonResponse([
        'error' => 'Server-Fehler beim Verarbeiten der Daten',
        'timestamp' => date('Y-m-d H:i:s')
    ], 500);
}

debugLog("=== REQUEST FINISHED ===");
?>