#!/bin/bash
# quick-setup.sh - Express-Installation für vorbereiteten Raspberry Pi

echo "🚀 Tasmota-Bridge Express Setup"
echo "================================"

# Farben
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

# =============================================================================
# 1. ARBEITSVERZEICHNIS ERSTELLEN
# =============================================================================

echo -e "\n${BLUE}📁 Arbeitsverzeichnis einrichten...${NC}"

cd ~
mkdir -p tasmota-bridge/{config,logs,web} 
cd tasmota-bridge

# Basis-Logs erstellen
touch logs/tasmota-bridge.log
touch logs/cron.log

echo -e "${GREEN}✅ Verzeichnisse erstellt${NC}"

# =============================================================================
# 2. TASMOTA-GERÄTE SCANNEN
# =============================================================================

echo -e "\n${BLUE}🔍 Scanne nach EIGHTREE/Tasmota-Geräten...${NC}"

# Netzwerk ermitteln
LOCAL_IP=$(hostname -I | awk '{print $1}')
NETWORK=$(echo $LOCAL_IP | cut -d. -f1-3)

echo "Pi-IP: $LOCAL_IP"
echo "Suche in: ${NETWORK}.100-120"

# Gefundene Geräte sammeln
echo "# Gefundene Tasmota-Geräte" > config/discovered-devices.txt

for i in {100..120}; do
    IP="${NETWORK}.$i"
    
    # Schneller Test
    if timeout 2 curl -s "http://$IP/cm?cmnd=Status%208" >/dev/null 2>&1; then
        # Detailtest für Tasmota
        RESPONSE=$(timeout 3 curl -s "http://$IP/cm?cmnd=Status%208" 2>/dev/null)
        
        if echo "$RESPONSE" | grep -q "ENERGY\|StatusSNS"; then
            # Gerätename extrahieren
            NAME=$(echo "$RESPONSE" | grep -o '"FriendlyName":\["[^"]*"' | cut -d'"' -f4)
            NAME=${NAME:-"tasmota_${i}"}
            NAME=$(echo "$NAME" | tr ' ' '_' | tr '[:upper:]' '[:lower:]')
            
            echo -e "  ${GREEN}✅ $IP → $NAME${NC}"
            echo "'$NAME' => '$IP'," >> config/discovered-devices.txt
            
            # Aktuelle Leistung anzeigen
            POWER=$(echo "$RESPONSE" | grep -o '"Power":[0-9.]*' | cut -d: -f2)
            if [ -n "$POWER" ]; then
                echo "     Aktuelle Leistung: ${POWER}W"
            fi
        fi
    fi
done

if [ -s config/discovered-devices.txt ] && [ $(wc -l < config/discovered-devices.txt) -gt 1 ]; then
    echo -e "\n${GREEN}🎯 Gefundene Geräte:${NC}"
    cat config/discovered-devices.txt
else
    echo -e "\n${YELLOW}⚠️ Keine Tasmota-Geräte gefunden${NC}"
    echo "Manuell konfigurieren in config/config.php"
fi

# =============================================================================
# 3. KONFIGURATIONSDATEI ERSTELLEN
# =============================================================================

echo -e "\n${BLUE}⚙️ Konfiguration erstellen...${NC}"

cat > config/config.php << 'EOF'
<?php
// Tasmota-Bridge Konfiguration

return [
    // =================================================================
    // WEB-API EINSTELLUNGEN (Ihre Internet-Webseite)
    // =================================================================
    'web_api_url' => 'https://IHRE-DOMAIN.de/api/receive-tasmota.php',
    'api_key' => 'SETZEN_SIE_HIER_IHREN_GEHEIMEN_SCHLUESSEL',
    'user_id' => 1,
    
    // =================================================================  
    // TASMOTA-GERÄTE (automatisch gefunden - anpassen nach Bedarf)
    // =================================================================
    'devices' => [
        // HIER DIE GEFUNDENEN GERÄTE EINTRAGEN:
EOF

# Gefundene Geräte in Konfiguration einfügen
if [ -s config/discovered-devices.txt ]; then
    grep -v "^#" config/discovered-devices.txt >> config/config.php
fi

cat >> config/config.php << 'EOF'
        
        // Manuell weitere Geräte hinzufügen:
        // 'gerätename' => '192.168.1.XXX',
    ],
    
    // =================================================================
    // ERWEITERTE EINSTELLUNGEN
    // =================================================================
    'collection' => [
        'timeout' => 5,
        'delay_between' => 500,
        'log_level' => 'info'
    ]
];
EOF

echo -e "${GREEN}✅ Basis-Konfiguration erstellt${NC}"

# =============================================================================
# 4. HAUPTPROGRAMM ERSTELLEN
# =============================================================================

echo -e "\n${BLUE}🛠️ Tasmota-Collector installieren...${NC}"

cat > tasmota-collector.php << 'EOF'
#!/usr/bin/env php
<?php
/**
 * Tasmota-Collector für Raspberry Pi
 * Sammelt Daten und sendet sie an Internet-Webseite
 */

$config = require __DIR__ . '/config/config.php';

class TasmotaCollector {
    private $config;
    private $logFile;
    
    public function __construct($config) {
        $this->config = $config;
        $this->logFile = __DIR__ . '/logs/tasmota-bridge.log';
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
        
        $devices = $this->config['devices'];
        if (empty($devices)) {
            $this->log("WARNUNG: Keine Geräte konfiguriert");
            return 0;
        }
        
        $allData = [];
        $success = 0;
        
        foreach ($devices as $name => $ip) {
            $this->log("→ $name ($ip)...", false);
            
            $data = $this->queryDevice($ip);
            if ($data && $this->hasEnergyData($data)) {
                $energyData = $this->extractEnergyData($data);
                
                $allData[] = [
                    'device_name' => $name,
                    'device_ip' => $ip,
                    'energy_data' => $energyData,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                $power = $energyData['power'] ?? 0;
                $today = $energyData['energy_today'] ?? 0;
                $this->log(" ✅ {$power}W, {$today}kWh");
                $success++;
            } else {
                $this->log(" ❌ Fehler");
            }
            
            usleep(500000); // 0.5s Pause
        }
        
        // An Webseite senden
        if ($allData) {
            $sent = $this->sendToWeb($allData);
            $this->log("📤 $sent von " . count($allData) . " gesendet");
        }
        
        $this->log("=== Fertig: $success OK ===");
        return $success;
    }
    
    private function queryDevice($ip) {
        $url = "http://$ip/cm?cmnd=Status%208";
        $context = stream_context_create([
            'http' => ['timeout' => 5]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        return $response ? json_decode($response, true) : null;
    }
    
    private function hasEnergyData($data) {
        return isset($data['StatusSNS']['ENERGY']);
    }
    
    private function extractEnergyData($data) {
        $energy = $data['StatusSNS']['ENERGY'];
        return [
            'voltage' => $energy['Voltage'] ?? null,
            'current' => $energy['Current'] ?? null,
            'power' => $energy['Power'] ?? null,
            'power_factor' => $energy['Factor'] ?? null,
            'energy_today' => $energy['Today'] ?? null,
            'energy_yesterday' => $energy['Yesterday'] ?? null,
            'energy_total' => $energy['Total'] ?? null
        ];
    }
    
    private function sendToWeb($data) {
        $payload = [
            'api_key' => $this->config['api_key'],
            'user_id' => $this->config['user_id'],
            'devices' => $data,
            'collector_info' => [
                'version' => '1.0-raspi',
                'hostname' => gethostname(),
                'collector_ip' => trim(shell_exec("hostname -I | awk '{print \$1}'"))
            ]
        ];
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($payload),
                'timeout' => 15
            ]
        ]);
        
        $response = @file_get_contents($this->config['web_api_url'], false, $context);
        $result = json_decode($response, true);
        
        return $result['processed'] ?? 0;
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
EOF

chmod +x tasmota-collector.php

echo -e "${GREEN}✅ Collector installiert${NC}"

# =============================================================================
# 5. WEB-INTERFACE ERSTELLEN (BONUS)
# =============================================================================

echo -e "\n${BLUE}🌐 Web-Interface erstellen...${NC}"

# Nginx-Konfiguration prüfen
NGINX_ROOT="/var/www/html"
if [ -d "/var/www/html" ]; then
    NGINX_ROOT="/var/www/html"
elif [ -d "/usr/share/nginx/html" ]; then
    NGINX_ROOT="/usr/share/nginx/html"
fi

# Web-Interface erstellen
sudo mkdir -p "$NGINX_ROOT/tasmota"

sudo tee "$NGINX_ROOT/tasmota/index.php" > /dev/null << 'EOF'
<?php
/**
 * Tasmota-Bridge Web-Interface
 * Einfache Übersicht über Status und Logs
 */

$bridgeDir = '/opt/tasmota-bridge';
$configFile = "$bridgeDir/config/config.php";
$logFile = "$bridgeDir/logs/tasmota-bridge.log";

// Konfiguration laden
$config = [];
if (file_exists($configFile)) {
    $config = include $configFile;
}

// Letzte Logs laden
$logs = [];
if (file_exists($logFile)) {
    $logs = array_slice(file($logFile, FILE_IGNORE_NEW_LINES), -20);
}

// Aktion ausführen
$message = '';
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'collect':
            $output = shell_exec("cd $bridgeDir && php tasmota-collector.php collect 2>&1");
            $message = "Sammlung gestartet:\n" . $output;
            break;
        case 'test':
            $output = shell_exec("cd $bridgeDir && php tasmota-collector.php test 2>&1");
            $message = "Test durchgeführt:\n" . $output;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🥧 Tasmota-Bridge Control</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .button:hover { background: #0056b3; }
        .logs { background: #000; color: #0f0; padding: 15px; font-family: monospace; font-size: 12px; border-radius: 5px; height: 300px; overflow-y: scroll; }
        .config-info { background: #e9ecef; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { white-space: pre-wrap; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>🥧 Raspberry Pi Tasmota-Bridge</h1>
        
        <?php if ($message): ?>
        <div class="card">
            <h3>🔄 Ausführung:</h3>
            <pre><?= htmlspecialchars($message) ?></pre>
        </div>
        <?php endif; ?>
        
        <div class="grid">
            <div>
                <div class="card">
                    <h3>⚡ Aktionen</h3>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="collect">
                        <button type="submit" class="button">📊 Jetzt sammeln</button>
                    </form>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="test">
                        <button type="submit" class="button">🧪 Geräte testen</button>
                    </form>
                    <button class="button" onclick="location.reload()">🔄 Aktualisieren</button>
                </div>
                
                <div class="card">
                    <h3>🔧 Konfiguration</h3>
                    <?php if (empty($config)): ?>
                        <p class="status-error">❌ Keine Konfiguration gefunden</p>
                        <p><code><?= $configFile ?></code></p>
                    <?php else: ?>
                        <p class="status-ok">✅ Konfiguration geladen</p>
                        <div class="config-info">
                            <strong>Web-API:</strong> <?= htmlspecialchars($config['web_api_url'] ?? 'Nicht konfiguriert') ?><br>
                            <strong>User-ID:</strong> <?= $config['user_id'] ?? 'Nicht gesetzt' ?><br>
                            <strong>Geräte:</strong> <?= count($config['devices'] ?? []) ?> konfiguriert
                        </div>
                        
                        <?php if (!empty($config['devices'])): ?>
                        <h4>📱 Konfigurierte Geräte:</h4>
                        <ul>
                            <?php foreach ($config['devices'] as $name => $ip): ?>
                            <li><strong><?= htmlspecialchars($name) ?></strong> → <?= htmlspecialchars($ip) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <div class="card">
                    <h3>📋 Live-Logs</h3>
                    <div class="logs">
                        <?php if (empty($logs)): ?>
                            <div>Keine Logs gefunden oder noch keine Aktivität.</div>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <div><?= htmlspecialchars($log) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <h3>ℹ️ System-Info</h3>
                    <div class="config-info">
                        <strong>Hostname:</strong> <?= gethostname() ?><br>
                        <strong>IP-Adresse:</strong> <?= trim(shell_exec("hostname -I | awk '{print \$1}'")) ?><br>
                        <strong>PHP-Version:</strong> <?= phpversion() ?><br>
                        <strong>Zeitzone:</strong> <?= date_default_timezone_get() ?><br>
                        <strong>Letzte Aktualisierung:</strong> <?= date('d.m.Y H:i:s') ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h3>📖 Hilfe</h3>
            <p><strong>SSH-Kommandos:</strong></p>
            <ul>
                <li><code>cd ~/tasmota-bridge && php tasmota-collector.php</code> - Manuell sammeln</li>
                <li><code>cd ~/tasmota-bridge && php tasmota-collector.php test</code> - Geräte testen</li>
                <li><code>tail -f ~/tasmota-bridge/logs/tasmota-bridge.log</code> - Live-Logs</li>
                <li><code>crontab -e</code> - Automatisierung einrichten</li>
            </ul>
        </div>
    </div>
    
    <script>
        // Auto-refresh alle 30 Sekunden
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
EOF

sudo chown www-data:www-data "$NGINX_ROOT/tasmota/index.php"

# Web-Zugriff anzeigen
PI_IP=$(hostname -I | awk '{print $1}')
echo -e "${GREEN}✅ Web-Interface erstellt${NC}"
echo -e "${BLUE}🌐 Zugriff: http://$PI_IP/tasmota/${NC}"

# =============================================================================
# FERTIG
# =============================================================================

echo -e "\n${GREEN}🎉 EXPRESS-SETUP ABGESCHLOSSEN!${NC}"
echo -e "${GREEN}=================================${NC}"

echo -e "\n${YELLOW}🔧 NÄCHSTE SCHRITTE:${NC}"
echo "1. Konfiguration anpassen:"
echo "   nano ~/tasmota-bridge/config/config.php"
echo ""
echo "2. Ersten Test:"  
echo "   cd ~/tasmota-bridge"
echo "   php tasmota-collector.php test"
echo ""
echo "3. Manuell sammeln:"
echo "   php tasmota-collector.php collect"
echo ""
echo "4. Web-Interface öffnen:"
echo "   http://$PI_IP/tasmota/"
echo ""
echo "5. Cron-Job aktivieren:"
echo "   crontab -e"
echo "   */5 * * * * /usr/bin/php /home/pi/tasmota-bridge/tasmota-collector.php collect"

echo -e "\n${BLUE}📱 Status-Befehle:${NC}"
echo "  tail -f ~/tasmota-bridge/logs/tasmota-bridge.log  # Live-Logs"
echo "  php ~/tasmota-bridge/tasmota-collector.php scan   # Netzwerk scannen"

echo -e "\n${GREEN}System bereit für Tasmota-Datensammlung! 🚀${NC}"
