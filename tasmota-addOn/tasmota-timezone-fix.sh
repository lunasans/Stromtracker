#!/bin/bash
# tasmota-timezone-fix.sh
# Tasmota-Geräte auf deutsche Zeit konfigurieren

echo "🇩🇪 Tasmota-Geräte auf deutsche Zeit (MEZ/MESZ) konfigurieren"
echo "=============================================================="

# Ihre Tasmota-Geräte IPs hier eintragen:
TASMOTA_IPS=(
    "192.168.0.236"
    "192.168.1.100"  # Weitere IPs hier hinzufügen
    # "192.168.1.101"
)

echo "Gefundene Geräte in config/config.php scannen..."

# Versuche IPs aus der Konfiguration zu extrahieren
if [ -f "config/config.php" ]; then
    echo "Extrahiere IPs aus Konfiguration..."
    FOUND_IPS=$(grep -oE '[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}' config/config.php)
    for ip in $FOUND_IPS; do
        echo "  Gefunden: $ip"
        TASMOTA_IPS+=($ip)
    done
fi

echo ""
echo "Konfiguriere ${#TASMOTA_IPS[@]} Tasmota-Geräte..."

for ip in "${TASMOTA_IPS[@]}"; do
    echo ""
    echo "📡 Konfiguriere Gerät: $ip"
    echo "-----------------------------"
    
    # Prüfen ob Gerät erreichbar ist
    if ! ping -c 1 -W 2 $ip > /dev/null 2>&1; then
        echo "❌ Gerät $ip nicht erreichbar - überspringe"
        continue
    fi
    
    echo "✅ Gerät $ip ist online"
    
    # Zeitzone auf Deutschland setzen
    echo "  🌍 Setze Zeitzone auf Deutschland..."
    curl -s "http://$ip/cm?cmnd=Timezone%2099" > /dev/null
    sleep 1
    
    # Standardzeit (MEZ) konfigurieren - Ende Oktober, 1. Sonntag, 3:00 Uhr
    echo "  🕐 Konfiguriere Winterzeit (MEZ)..."
    curl -s "http://$ip/cm?cmnd=TimeStd%200,0,10,1,3,60" > /dev/null
    sleep 1
    
    # Sommerzeit (MESZ) konfigurieren - Ende März, 1. Sonntag, 2:00 Uhr  
    echo "  ☀️ Konfiguriere Sommerzeit (MESZ)..."
    curl -s "http://$ip/cm?cmnd=TimeDst%200,0,3,1,2,120" > /dev/null
    sleep 1
    
    # Deutsche NTP-Server setzen
    echo "  🌐 Setze deutsche NTP-Server..."
    curl -s "http://$ip/cm?cmnd=NtpServer1%20de.pool.ntp.org" > /dev/null
    curl -s "http://$ip/cm?cmnd=NtpServer2%20europe.pool.ntp.org" > /dev/null
    sleep 1
    
    # Zeit neu synchronisieren
    echo "  🔄 Synchronisiere Zeit..."
    curl -s "http://$ip/cm?cmnd=Time" > /dev/null
    sleep 2
    
    # Aktuelle Zeit abfragen
    echo "  ✅ Prüfe neue Zeit..."
    CURRENT_TIME=$(curl -s "http://$ip/cm?cmnd=Time" | grep -o '"Time":"[^"]*"' | cut -d'"' -f4)
    if [ ! -z "$CURRENT_TIME" ]; then
        echo "      Neue Zeit: $CURRENT_TIME"
    fi
    
    echo "  ✅ Gerät $ip konfiguriert!"
done

echo ""
echo "🎯 Konfiguration abgeschlossen!"
echo "================================"
echo ""
echo "📝 Wichtige Hinweise:"
echo "• Alle Geräte sollten jetzt deutsche Zeit (MEZ/MESZ) senden"
echo "• Die Umstellung erfolgt automatisch (Sommer-/Winterzeit)"
echo "• Warten Sie ~5 Minuten und prüfen Sie die Webseite"
echo "• Bei Problemen: Geräte neu starten"
echo ""
echo "🔧 Manuelle Konfiguration über Tasmota-Konsole:"
echo "   Timezone 99"
echo "   TimeStd 0,0,10,1,3,60"
echo "   TimeDst 0,0,3,1,2,120"
echo ""
echo "⚡ Starten Sie den Tasmota-Collector neu:"
echo "   sudo systemctl restart tasmota-collector"
