#!/bin/bash
# timezone-config.sh
# Zeitzonenkonfiguration für Raspberry Pi (Tasmota-Integration)

echo "🕐 Zeitzonenkonfiguration für Deutschland (MEZ/MESZ)"
echo "================================================="

# Aktuelle Systemzeit anzeigen
echo "Aktuelle Systemzeit:"
echo "Lokale Zeit: $(date)"
echo "UTC Zeit:    $(date -u)"
echo "Zeitzone:    $(timedatectl show -p Timezone --value)"

# Zeitzone auf Deutschland setzen
echo ""
echo "Setze Zeitzone auf Europe/Berlin..."
sudo timedatectl set-timezone Europe/Berlin

# PHP-Konfiguration anpassen
echo ""
echo "Konfiguriere PHP für deutsche Zeitzone..."

# Für PHP CLI
PHP_INI_CLI=$(php --ini | grep "Loaded Configuration File" | cut -d: -f2 | xargs)
if [ -n "$PHP_INI_CLI" ] && [ -f "$PHP_INI_CLI" ]; then
    echo "PHP CLI config: $PHP_INI_CLI"
    sudo sed -i 's/^;date.timezone =.*/date.timezone = Europe\/Berlin/' "$PHP_INI_CLI"
    sudo sed -i 's/^date.timezone =.*/date.timezone = Europe\/Berlin/' "$PHP_INI_CLI"
fi

# Für Apache/Web-PHP (falls vorhanden)
WEB_PHP_INI="/etc/php/8.2/apache2/php.ini"
if [ -f "$WEB_PHP_INI" ]; then
    echo "PHP Web config: $WEB_PHP_INI"
    sudo sed -i 's/^;date.timezone =.*/date.timezone = Europe\/Berlin/' "$WEB_PHP_INI"
    sudo sed -i 's/^date.timezone =.*/date.timezone = Europe\/Berlin/' "$WEB_PHP_INI"
fi

# Collector-Konfiguration aktualisieren
echo ""
echo "Aktualisiere Tasmota-Collector Konfiguration..."

COLLECTOR_CONFIG="config/config.php"
if [ -f "$COLLECTOR_CONFIG" ]; then
    # Backup erstellen
    cp "$COLLECTOR_CONFIG" "${COLLECTOR_CONFIG}.backup.$(date +%Y%m%d_%H%M%S)"
    
    # Zeitzonenkonfiguration hinzufügen
    if ! grep -q "timezone" "$COLLECTOR_CONFIG"; then
        sed -i "/'collection' => \[/a\\
    'timezone' => [\\
        'system' => 'Europe/Berlin',\\
        'send_as_utc' => false,\\
        'auto_convert' => true\\
    ]," "$COLLECTOR_CONFIG"
    fi
fi

# System-Services neu starten
echo ""
echo "Starte relevante Services neu..."

# Apache neu starten (falls vorhanden)
if systemctl is-active --quiet apache2; then
    sudo systemctl restart apache2
    echo "✅ Apache2 neu gestartet"
fi

# NTP-Synchronisation prüfen
if command -v timedatectl >/dev/null; then
    sudo timedatectl set-ntp true
    echo "✅ NTP-Synchronisation aktiviert"
fi

# Ergebnis anzeigen
echo ""
echo "🎯 Zeitzonenkonfiguration abgeschlossen"
echo "======================================"
echo "Neue Systemzeit:"
echo "Lokale Zeit: $(date)"
echo "UTC Zeit:    $(date -u)"
echo "Zeitzone:    $(timedatectl show -p Timezone --value)"

# PHP-Zeitzone testen
echo ""
echo "PHP-Zeitzonenkonfiguration:"
php -r "echo 'PHP Zeitzone: ' . date_default_timezone_get() . \"\n\";"
php -r "echo 'PHP Lokale Zeit: ' . date('Y-m-d H:i:s T') . \"\n\";"
php -r "echo 'PHP UTC Zeit: ' . gmdate('Y-m-d H:i:s T') . \"\n\";"

echo ""
echo "✅ Konfiguration erfolgreich!"
echo "📝 Hinweis: Raspberry Pi sollte neu gestartet werden für vollständige Aktivierung"
echo "   sudo reboot"
echo ""
echo "🔄 Tasmota-Daten werden jetzt automatisch von UTC zu MEZ/MESZ konvertiert"
