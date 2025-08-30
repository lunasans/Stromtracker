#!/bin/bash
# debug-tasmota-time.sh
# Tasmota-Zeit vs. System-Zeit vergleichen

echo "🔍 Tasmota vs. System Zeit Debug"
echo "================================"

IP="192.168.0.236"  # Ihr Tasmota-Gerät

echo "📅 System-Zeit (Raspberry Pi):"
echo "  Lokale Zeit: $(date)"
echo "  UTC Zeit:    $(date -u)"
echo "  Zeitzone:    $(date +%Z)"
echo ""

echo "📡 Tasmota-Gerät ($IP):"
if ping -c 1 -W 2 $IP > /dev/null 2>&1; then
    # Status von Tasmota abrufen
    STATUS=$(curl -s "http://$IP/cm?cmnd=Status%200" 2>/dev/null)
    
    if [ ! -z "$STATUS" ]; then
        echo "✅ Tasmota antwortet"
        
        # Zeit extrahieren
        TASMOTA_TIME=$(echo "$STATUS" | grep -o '"Time":"[^"]*"' | cut -d'"' -f4)
        echo "  Tasmota Zeit: $TASMOTA_TIME"
        
        # Zeitzone extrahieren
        TIMEZONE=$(echo "$STATUS" | grep -o '"Timezone":[^,}]*' | cut -d':' -f2)
        echo "  Zeitzone:     $TIMEZONE"
        
        # Weitere Zeit-Infos
        echo ""
        echo "📋 Vollständiger Tasmota Status:"
        echo "$STATUS" | jq -r '.StatusTIM' 2>/dev/null || echo "JSON-Parsing fehlgeschlagen"
        
    else
        echo "❌ Keine Antwort von Tasmota"
    fi
else
    echo "❌ Tasmota nicht erreichbar"
fi

echo ""
echo "🔧 Debug-Befehle für Tasmota-Konsole:"
echo "  Time         # Aktuelle Zeit anzeigen"
echo "  Timezone     # Aktuelle Zeitzone anzeigen" 
echo "  Status 0     # Vollständiger Status"
echo ""
echo "🔧 Zeitzone-Korrektur-Befehle:"
echo "  Timezone 99"
echo "  TimeStd 0,0,10,1,3,60"
echo "  TimeDst 0,0,3,1,2,120"
