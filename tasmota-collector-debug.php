#!/usr/bin/env php
<?php
/**
 * Tasmota-Collector DEBUG VERSION - ✅ Lokale Zeit für Raspberry Pi
 * ✅ Vereinfacht für Fehlerdiagnose
 */

$config = require __DIR__ . '/config/config.php';

class TasmotaCollector {
    private $config;
    private $logFile;
    
    public function __construct($config) {
        $this->config = $config;
        $this->logFile = __DIR__ . '/logs/tasmota-bridge.log';
        
        // Debug-Info beim Start
        $this->log("=== COLLECTOR DEBUG VERSION (LOKALE ZEIT) ===");
        $this->log("PHP Version: " . phpversion());
        $this->log("Zeitzone: " . date_default_timezone_get());
        $this->log("Lokale Zeit: " . date('Y-m-d H:i:s'));
        $this->log("UTC Zeit: " . gmdate('Y-m-d H:i:s'));
    }
    
    public function run($action = 'collect') {
        switch ($action) {
            case 'scan':
                return $this->scanDevices();
            case 'test':
                return $this->testDevices();
            default:
                return $this->collectAndSend();
        }
    }
    
    private function collectAndSend() {
        $this->log("=== Sammlung gestartet ===");
        
        // Konfiguration prüfen
        $this->log("API URL: " . ($this->config['web_api_url'] ?? 'NICHT GESETZT'));
        $this->log("API Key: " . (empty($this->config['api_key']) ? 'NICHT GESETZT' : 'GESETZT'));
        $this->log("User ID: " . ($this->config['user_id'] ?? 'NICHT GESETZT'));
        
        $devices = $this->config['devices'];
        if (empty($devices)) {
            $this->log("❌ FEHLER: Keine Geräte konfiguriert");
            return 0;
        }
        
        $this->log("Anzahl Geräte: " . count($devices));
        
        $allData = [];
        $success = 0;
        
        // ✅ KORRIGIERT: Lokale Zeit verwenden statt UTC
        $currentTimeLocal = date('Y-m-d H:i:s');
        $this->log("Lokale Zeitstempel: $currentTimeLocal");
        
        foreach ($devices as $name => $ip) {
            $this->log("→ $name ($ip)...", false);
            
            $data = $this->queryDevice($ip);
            if ($data) {
                $this->log(" ✅ Antwort erhalten");
                
                if ($this->hasEnergyData($data)) {
                    $energyData = $this->extractEnergyData($data);
                    
                    $allData[] = [
                        'device_name' => $name,
                        'device_ip' => $ip,
                        'energy_data' => $energyData,
                        'timestamp' => $currentTimeLocal  // ✅ LOKALE Zeit verwenden
                    ];
                    
                    $power = $energyData['power'] ?? 0;
                    $today = $energyData['energy_today'] ?? 0;
                    $this->log("   Power: {$power}W, Heute: {$today}kWh");
                    $success++;
                } else {
                    $this->log(" ❌ Keine Energiedaten");
                    $this->log("   Debug-Daten: " . json_encode($data, JSON_PRETTY_PRINT));
                }
            } else {
                $this->log(" ❌ Keine Antwort vom Gerät");
            }
            
            usleep(500000); // 0.5s Pause
        }
        
        $this->log("Gesammelte Geräte: $success");
        
        // An Webseite senden
        if ($allData) {
            $this->log("📤 Sende " . count($allData) . " Geräte an Server...");
            $sent = $this->sendToWeb($allData);
            $this->log("📤 Server-Antwort: $sent Geräte verarbeitet");
        } else {
            $this->log("⚠️ Keine Daten zum Senden");
        }
        
        $this->log("=== Fertig: $success OK ===");
        return $success;
    }
    
    private function queryDevice($ip) {
        $url = "http://$ip/cm?cmnd=Status%208";
        $this->log("   URL: $url");
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'TasmotaCollector/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $this->log("   HTTP-Fehler: " . error_get_last()['message'] ?? 'Unbekannt');
            return null;
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("   JSON-Fehler: " . json_last_error_msg());
            return null;
        }
        
        return $decoded;
    }
    
    private function hasEnergyData($data) {
        $hasEnergy = isset($data['StatusSNS']['ENERGY']);
        $this->log("   Energiedaten vorhanden: " . ($hasEnergy ? 'JA' : 'NEIN'));
        return $hasEnergy;
    }
    
    private function extractEnergyData($data) {
        $energy = $data['StatusSNS']['ENERGY'];
        
        $extracted = [
            'voltage' => $energy['Voltage'] ?? null,
            'current' => $energy['Current'] ?? null,
            'power' => $energy['Power'] ?? null,
            'power_factor' => $energy['Factor'] ?? null,
            'energy_today' => $energy['Today'] ?? null,
            'energy_yesterday' => $energy['Yesterday'] ?? null,
            'energy_total' => $energy['Total'] ?? null,
            'apparent_power' => $energy['ApparentPower'] ?? null,
            'reactive_power' => $energy['ReactivePower'] ?? null,
        ];
        
        $this->log("   Extrahiert: " . json_encode($extracted, JSON_UNESCAPED_UNICODE));
        return $extracted;
    }
    
    private function sendToWeb($data) {
        $payload = [
            'api_key' => $this->config['api_key'],
            'user_id' => $this->config['user_id'],
            'devices' => $data,
            'collector_info' => [
                'version' => '1.1-debug-local',
                'hostname' => gethostname(),
                'collector_ip' => trim(shell_exec("hostname -I | awk '{print \$1}'")),
                'timezone' => date_default_timezone_get(),
                'sent_at_local' => date('Y-m-d H:i:s')  // ✅ Lokale Zeit
            ]
        ];
        
        $this->log("📤 Payload erstellt (" . strlen(json_encode($payload)) . " Bytes)");
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'User-Agent: TasmotaCollector/1.1-debug-local'
                ],
                'content' => json_encode($payload),
                'timeout' => 15
            ]
        ]);
        
        $this->log("📤 HTTP POST an: " . $this->config['web_api_url']);
        
        $response = @file_get_contents($this->config['web_api_url'], false, $context);
        
        if ($response === false) {
            $error = error_get_last()['message'] ?? 'Unbekannt';
            $this->log("❌ HTTP-Fehler beim Senden: $error");
            return 0;
        }
        
        $this->log("📤 Server-Antwort erhalten (" . strlen($response) . " Bytes): " . substr($response, 0, 200));
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("❌ JSON-Dekodierung fehlgeschlagen: " . json_last_error_msg());
            return 0;
        }
        
        if (isset($result['error'])) {
            $this->log("❌ Server-Fehler: " . $result['error']);
            return 0;
        }
        
        $processed = $result['processed'] ?? 0;
        $this->log("✅ Server verarbeitete: $processed Geräte");
        
        return $processed;
    }
    
    private function testDevices() {
        $this->log("=== Gerätetest ===");
        
        foreach ($this->config['devices'] as $name => $ip) {
            $this->log("Test $name ($ip)...", false);
            
            $data = $this->queryDevice($ip);
            if ($data && $this->hasEnergyData($data)) {
                $energy = $this->extractEnergyData($data);
                $this->log(" ✅ {$energy['power']}W");
            } else {
                $this->log(" ❌ Keine Verbindung/Daten");
            }
        }
        return true;
    }
    
    private function scanDevices() {
        $this->log("=== Netzwerk-Scan ===");
        
        $network = trim(shell_exec("hostname -I | awk '{print \$1}' | cut -d. -f1-3"));
        $found = 0;
        
        for ($i = 100; $i <= 120; $i++) {
            $ip = "$network.$i";
            
            $data = $this->queryDevice($ip);
            if ($data && $this->hasEnergyData($data)) {
                $name = $data['Status']['FriendlyName'][0] ?? "device_$i";
                $this->log("✅ Gefunden: $name ($ip)");
                $found++;
            }
        }
        
        $this->log("$found Geräte gefunden");
        return $found;
    }
    
    private function log($message, $newline = true) {
        $timestamp = date('H:i:s');
        $line = "[$timestamp] $message";
        
        if ($newline) {
            echo $line . "\n";
            file_put_contents($this->logFile, $line . "\n", FILE_APPEND);
        } else {
            echo $line;
        }
    }
}

// Ausführung
if (php_sapi_name() === 'cli') {
    $collector = new TasmotaCollector($config);
    $collector->run($argv[1] ?? 'collect');
} else {
    echo "Nur CLI-Ausführung möglich\n";
}
