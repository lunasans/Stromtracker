#!/bin/bash
# tasmota-timezone-status.sh  
# Tasmota-Geräte Zeitzone-Status prüfen

echo "🇩🇪 Tasmota-Geräte Zeitzone-Status prüfen"
echo "=========================================="

# Ihre Tasmota-Geräte IPs hier eintragen:
TASMOTA_IPS=(
    "192.168.0.236"
    # Weitere IPs hier hinzufügen
)

if [ -f "config/config.php" ]; then
    FOUND_IPS=$(grep -oE '[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}' config/config.php)
    for ip in $FOUND_IPS; do
        TASMOTA_IPS+=($ip)
    done
fi

# Duplikate entfernen
TASMOTA_IPS=($(printf "%s\n" "${TASMOTA_IPS[@]}" | sort -u))

echo "Prüfe ${#TASMOTA_IPS[@]} Tasmota-Geräte..."
echo ""

for ip in "${TASMOTA_IPS[@]}"; do
    echo "📡 Gerät: $ip"
    echo "-------------------"
    
    if ! ping -c 1 -W 2 $ip > /dev/null 2>&1; then
        echo "❌ Nicht erreichbar"
        echo ""
        continue
    fi
    
    # Status abrufen
    STATUS=$(curl -s "http://$ip/cm?cmnd=Status%200" 2>/dev/null)
    
    if [ $? -eq 0 ] && [ ! -z "$STATUS" ]; then
        echo "✅ Online"
        
        # Zeit extrahieren
        CURRENT_TIME=$(echo "$STATUS" | grep -o '"Time":"[^"]*"' | cut -d'"' -f4)
        if [ ! -z "$CURRENT_TIME" ]; then
            echo "🕐 Aktuelle Zeit: $CURRENT_TIME"
        fi
        
        # Zeitzone prüfen
        TIMEZONE=$(echo "$STATUS" | grep -o '"Timezone":[^,}]*' | cut -d':' -f2)
        if [ ! -z "$TIMEZONE" ]; then
            echo "🌍 Zeitzone: $TIMEZONE"
            if [ "$TIMEZONE" = "99" ]; then
                echo "✅ Deutsche Zeitzone konfiguriert"
            else
                echo "⚠️  UTC-Zeitzone - sollte konfiguriert werden!"
            fi
        fi
    else
        echo "❌ Keine Antwort vom Gerät"
    fi
    echo ""
done

echo "🎯 Status-Prüfung abgeschlossen!"
echo ""
echo "📝 Ergebnis interpretieren:"
echo "• Zeitzone 99 = Deutsche Zeit (MEZ/MESZ) ✅"
echo "• Zeitzone 0 = UTC-Zeit ⚠️"  
echo "• Zeit sollte der aktuellen deutschen Zeit entsprechen"
echo ""
echo "🔧 Bei Problemen: ./tasmota-timezone-fix.sh ausführen"
