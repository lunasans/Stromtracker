<?php
// api/receive-tasmota.php  
// ✅ DEBUG-VERSION: Web-API Endpoint für Tasmota-Daten mit ausführlichem Logging

require_once '../config/database.php';
require_once '../config/session.php';

// Debug-Logging aktivieren
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

// Debug-Logging Funktion
function debugLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}";
    if ($data !== null) {
        $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $logEntry .= PHP_EOL;
    
    file_put_contents('../logs/tasmota-debug.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("ERROR: Non-POST request", ['method' => $_SERVER['REQUEST_METHOD']]);
    jsonResponse(['error' => 'Nur POST-Requests erlaubt'], 405);
}

// JSON-Daten einlesen
$jsonInput = file_get_contents('php://input');
debugLog("Raw JSON input received", ['length' => strlen($jsonInput), 'content' => substr($jsonInput, 0, 500)]);

$data = json_decode($jsonInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    debugLog("ERROR: JSON decode failed", ['error' => json_last_error_msg()]);
    jsonResponse(['error' => 'Ungültiges JSON'], 400);
}

debugLog("Parsed JSON data", $data);

class TasmotaWebReceiver {
    
    /**
     * ✅ SICHER: API-Key gegen Datenbank validieren
     */
    private function validateApiKey($providedKey) {
        if (empty($providedKey)) {
            debugLog("ERROR: Empty API key provided");
            return false;
        }
        
        // API-Key Format prüfen (st_xxxxxxxxx...)
        if (!preg_match('/^st_[a-f0-9]{60}$/', $providedKey)) {
            debugLog("ERROR: Invalid API key format", ['key' => substr($providedKey, 0, 10) . '...']);
            return false;
        }
        
        // In Datenbank suchen
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
     * Empfangene Tasmota-Daten verarbeiten
     */
    public function processReceivedData($data) {
        debugLog("Starting data processing", ['data_keys' => array_keys($data)]);
        
        $providedApiKey = $data['api_key'] ?? '';
        
        // API-Key validieren und Benutzer ermitteln
        $keyOwner = $this->validateApiKey($providedApiKey);
        if (!$keyOwner) {
            $this->logUnauthorizedAttempt($providedApiKey, $_SERVER['REMOTE_ADDR']);
            return ['error' => 'Ungültiger API-Key'];
        }
        
        // Benutzer-ID aus API-Key-Validierung verwenden (sicherer!)
        $userId = $keyOwner['id'];
        debugLog("User validated", ['user_id' => $userId]);
        
        // Optionale user_id aus Request validieren (falls angegeben)
        $requestUserId = (int)($data['user_id'] ?? 0);
        if ($requestUserId > 0 && $requestUserId !== $userId) {
            debugLog("ERROR: User ID mismatch", ['api_user_id' => $userId, 'request_user_id' => $requestUserId]);
            $this->logSuspiciousActivity($userId, 'User-ID mismatch', $data);
            return ['error' => 'User-ID stimmt nicht mit API-Key überein'];
        }
        
        // Geräte-Daten extrahieren
        $devices = $data['devices'] ?? [];
        
        // Legacy-Format unterstützen (einzelnes Gerät)
        if (empty($devices) && isset($data['device_name'])) {
            debugLog("Converting legacy format to devices array");
            $devices = [$data]; // Einzelnes Gerät als Array
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
        
        // Erfolgs-Log speichern
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
            debugLog("ERROR: Device name is empty");
            return ['success' => false, 'error' => 'Gerätename fehlt'];
        }
        
        // Gerät in Datenbank finden oder erstellen
        $device = $this->findOrCreateDevice($userId, $deviceName, $deviceIP);
        
        if (!$device) {
            debugLog("ERROR: Could not find or create device");
            return ['success' => false, 'error' => 'Gerät konnte nicht erstellt werden'];
        }
        
        $deviceId = $device['id'];
        debugLog("Device found/created", ['device_id' => $deviceId]);
        
        // Gerät-Info aktualisieren
        $updateData = [
            'last_tasmota_reading' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($deviceIP)) {
            $updateData['tasmota_ip'] = $deviceIP;
        }
        
        // ✅ FIX: Power aus energy_data extrahieren
        $energyData = $deviceData['energy_data'] ?? $deviceData;
        if (isset($energyData['power']) && $energyData['power'] > 0) {
            $updateData['wattage'] = max(1, (int)$energyData['power']);
        }
        
        Database::update('devices', $updateData, 'id = ?', [$deviceId]);
        debugLog("Device updated", $updateData);
        
        // Tasmota-Rohdaten speichern (falls Tabelle existiert)
        $this->saveTasmotaReadings($deviceId, $userId, $deviceData);
        
        // ❌ ENTFERNT: updateMeterReadings() 
        // Grund: Einzelgeräte gehören NICHT in meter_readings!
        // meter_readings ist nur für Gesamthaushalt-Zählerstände
        debugLog("Skipping meter_readings update - only individual device data saved");
        
        return [
            'success' => true,
            'device_id' => $deviceId,
            'device_name' => $deviceName
        ];
    }
    
    /**
     * ✅ KORRIGIERT: Tasmota-Rohdaten mit verschachtelter energy_data speichern
     */
    private function saveTasmotaReadings($deviceId, $userId, $deviceData) {
        // Prüfen ob Tabelle existiert
        if (!Database::tableExists('tasmota_readings')) {
            debugLog("WARNING: tasmota_readings table does not exist");
            return;
        }
        
        // ✅ FIX: energy_data Objekt extrahieren
        $energyData = $deviceData['energy_data'] ?? $deviceData;
        
        debugLog("Extracting energy data", ['energy_data_available' => isset($deviceData['energy_data']), 'energy_data' => $energyData]);
        
        try {
            $readingData = [
                'device_id' => $deviceId,
                'user_id' => $userId,
                'timestamp' => date('Y-m-d H:i:s'),
                // ✅ FIX: Aus energy_data Objekt lesen statt direkt aus deviceData
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
            
            debugLog("Attempting to save tasmota readings", $readingData);
            
            $insertId = Database::insert('tasmota_readings', $readingData);
            
            if ($insertId) {
                debugLog("Tasmota readings saved successfully", ['insert_id' => $insertId]);
            } else {
                debugLog("ERROR: Failed to save tasmota readings - Database insert returned false");
            }
            
        } catch (Exception $e) {
            debugLog("ERROR: Exception saving tasmota readings", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }
    
    /**
     * Sicher Float-Werte aus Array extrahieren
     */
    private function extractFloat($data, $key) {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        return (float)$value;
    }
    
    /**
     * Gerät finden oder automatisch erstellen
     */
    private function findOrCreateDevice($userId, $deviceName, $deviceIP) {
        debugLog("Finding or creating device", ['name' => $deviceName, 'ip' => $deviceIP]);
        
        // Zuerst nach Name suchen
        $device = Database::fetchOne(
            "SELECT * FROM devices WHERE user_id = ? AND name = ?",
            [$userId, $deviceName]
        );
        
        if ($device) {
            debugLog("Device found by name", ['device_id' => $device['id']]);
            return $device;
        }
        
        // Nach IP suchen (falls angegeben)
        if (!empty($deviceIP)) {
            $device = Database::fetchOne(
                "SELECT * FROM devices WHERE user_id = ? AND tasmota_ip = ?",
                [$userId, $deviceIP]
            );
            
            if ($device) {
                debugLog("Device found by IP", ['device_id' => $device['id']]);
                // Name aktualisieren falls unterschiedlich
                if ($device['name'] !== $deviceName) {
                    Database::update('devices', [
                        'name' => $deviceName
                    ], 'id = ?', [$device['id']]);
                    $device['name'] = $deviceName;
                    debugLog("Device name updated", ['new_name' => $deviceName]);
                }
                return $device;
            }
        }
        
        // Neues Gerät erstellen
        debugLog("Creating new device");
        $deviceId = Database::insert('devices', [
            'user_id' => $userId,
            'name' => $deviceName,
            'category' => 'Smart Home',
            'wattage' => 100, // Default, wird später aus Messdaten aktualisiert
            'tasmota_ip' => $deviceIP,
            'tasmota_enabled' => true,
            'tasmota_name' => $deviceName,
            'is_active' => true
        ]);
        
        if ($deviceId) {
            debugLog("New device created successfully", ['device_id' => $deviceId]);
            return [
                'id' => $deviceId,
                'name' => $deviceName,
                'user_id' => $userId
            ];
        }
        
        debugLog("ERROR: Failed to create new device");
        return null;
    }
    
    /**
     * ❌ DEAKTIVIERT: updateMeterReadings()
     * 
     * Diese Funktion war fehlerhaft und wurde entfernt.
     * 
     * GRUND:
     * - meter_readings ist für Gesamthaushalt-Zählerstände (manuell vom Hauptzähler)
     * - tasmota_readings ist für Einzelgerät-Verbrauch (automatisch von Smart-Steckdosen)
     * 
     * PROBLEM:
     * Die Funktion hat fälschlicherweise Einzelgerätedaten (z.B. PC: 0.47 kWh) 
     * in meter_readings geschrieben, was die Gesamthaushalts-Bilanz verfälscht hat.
     * 
     * LÖSUNG:
     * - Tasmota-Daten bleiben in tasmota_readings
     * - meter_readings nur für manuelle Zählerstände
     * - Korrekte Trennung der Datenquellen
     */
    /*
    private function updateMeterReadings($userId, $deviceId, $deviceData) {
        // DIESE FUNKTION IST PERMANENT DEAKTIVIERT
        // Siehe Kommentar oben für Details
    }
    */
    
    /**
     * Erfolgreiche Datenübertragung loggen
     */
    private function logReceive($userId, $deviceCount, $processed, $collectorInfo) {
        logMessage(
            "Tasmota API: User {$userId} - {$processed}/{$deviceCount} Geräte verarbeitet",
            'info',
            'tasmota-api.log'
        );
    }
    
    /**
     * Unbefugte Zugriffe loggen
     */
    private function logUnauthorizedAttempt($providedKey, $ip) {
        debugLog("SECURITY: Unauthorized access attempt", ['ip' => $ip, 'key' => substr($providedKey, 0, 10) . '...']);
        logMessage(
            "SECURITY: Unauthorized API attempt from {$ip} with key: " . 
            substr($providedKey, 0, 10) . "...",
            'security',
            'security.log'
        );
    }
    
    /**
     * Verdächtige Aktivitäten loggen
     */
    private function logSuspiciousActivity($userId, $reason, $data) {
        debugLog("SECURITY: Suspicious activity", ['user_id' => $userId, 'reason' => $reason]);
        logMessage(
            "SECURITY: Suspicious activity from User {$userId}: {$reason}",
            'security',
            'security.log'
        );
    }
}

// =============================================================================
// REQUEST PROCESSING
// =============================================================================

try {
    debugLog("=== NEW REQUEST STARTING ===", ['ip' => $_SERVER['REMOTE_ADDR'], 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']);
    
    $receiver = new TasmotaWebReceiver();
    $result = $receiver->processReceivedData($data);
    
    if (isset($result['error'])) {
        // Fehler-Antwort
        debugLog("Request failed with error", ['error' => $result['error']]);
        jsonResponse($result, 400);
    } else {
        // Erfolgreiche Antwort
        debugLog("Request completed successfully", $result);
        jsonResponse($result, 200);
    }
    
} catch (Exception $e) {
    // Server-Fehler
    debugLog("FATAL ERROR: Exception in main processing", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    logMessage("Tasmota API Error: " . $e->getMessage(), 'error');
    
    jsonResponse([
        'error' => 'Server-Fehler beim Verarbeiten der Daten',
        'timestamp' => date('Y-m-d H:i:s')
    ], 500);
}

debugLog("=== REQUEST FINISHED ===");
?>