# Benachrichtigungssystem - Setup-Anleitung

## 🔔 Zählerstand-Erinnerungen für Stromtracker

Das Benachrichtigungssystem erinnert Benutzer automatisch daran, ihre Zählerstände zu erfassen. Diese Anleitung führt Sie durch die komplette Installation und Einrichtung.

## 📋 Voraussetzungen

- Stromtracker bereits installiert und funktionsfähig
- PHP 7.4+ mit aktivierter `mail()`-Funktion
- Cron-Job Zugriff auf dem Server
- MySQL/MariaDB Datenbankzugriff

## 🚀 Installation

### 1. Datenbank-Updates ausführen

```bash
# Benachrichtigungstabellen erstellen
mysql -u username -p stromtracker < sql/notifications.sql

# Falls API-Keys noch nicht installiert
mysql -u username -p stromtracker < sql/api-key.sql
```

### 2. Dateien prüfen

Stellen Sie sicher, dass folgende Dateien vorhanden sind:
```
includes/NotificationManager.php     # ✅ Erstellt
scripts/send-reminders.php          # ✅ Erstellt  
scripts/test-notifications.php      # ✅ Erstellt
sql/notifications.sql               # ✅ Erstellt
profil.php                          # ✅ Erweitert
dashboard.php                       # ✅ Erweitert
```

### 3. Logs-Ordner erstellen

```bash
mkdir -p logs
chmod 755 logs
touch logs/reminders.log
chmod 644 logs/reminders.log
```

### 4. Installation testen

```bash
# Test-Script ausführen
php scripts/test-notifications.php install

# Sollte alle grüne Häkchen zeigen:
# ✓ Tabelle 'notification_settings' gefunden
# ✓ Tabelle 'notification_log' gefunden  
# ✓ NotificationManager-Klasse geladen
# ✓ Logs-Ordner beschreibbar
# ✓ Cron-Script gefunden
```

## ⚙️ Konfiguration

### 1. Benutzereinstellungen

Benutzer können ihre Benachrichtigungseinstellungen unter **Profil > Benachrichtigungen** konfigurieren:

- ✅ **E-Mail-Benachrichtigungen aktivieren**
- ✅ **Monatliche Zählerstand-Erinnerung** (1-10 Tage vor Monatsende)
- ⚠️ **Hoher Verbrauch-Alarm** (optional)
- 💰 **Kostenalarm** (optional)

### 2. E-Mail-Konfiguration

Für E-Mail-Versand PHP's `mail()`-Funktion konfigurieren:

```php
// In php.ini oder per Code
ini_set('sendmail_from', 'noreply@ihredomain.de');
ini_set('SMTP', 'ihr-smtp-server.de');
ini_set('smtp_port', '587');
```

**Alternative: Erweiterte E-Mail-Klasse verwenden**

```php
// includes/NotificationManager.php erweitern
// Für PHPMailer oder SwiftMailer Integration
```

## 🕐 Automatisierung (Cron-Jobs)

### Cron-Job einrichten

```bash
# Cron-Tabelle bearbeiten
crontab -e

# Täglich um 18:00 Uhr prüfen
0 18 * * * /usr/bin/php /pfad/zu/stromtracker/scripts/send-reminders.php

# Oder alle 6 Stunden
0 */6 * * * /usr/bin/php /pfad/zu/stromtracker/scripts/send-reminders.php

# Logs prüfen (optional, wöchentlich)
0 9 * * 1 /usr/bin/find /pfad/zu/stromtracker/logs -name "*.log" -size +10M -delete
```

### Cron-Job-Pfade anpassen

**Wichtig:** Vollständige Pfade verwenden!

```bash
# Richtig ✅
0 18 * * * /usr/bin/php /home/user/public_html/stromtracker/scripts/send-reminders.php

# Falsch ❌  
0 18 * * * php scripts/send-reminders.php
```

## 🧪 Tests

### 1. Grundfunktionen testen

```bash
# Installation prüfen
php scripts/test-notifications.php install

# Benutzer prüfen die Erinnerungen benötigen
php scripts/test-notifications.php check

# Test-E-Mail an Benutzer ID 1 senden
php scripts/test-notifications.php send 1

# Statistiken anzeigen
php scripts/test-notifications.php stats
```

### 2. Manuelle Cron-Job Simulation

```bash
# Erinnerungen manuell verarbeiten
php scripts/send-reminders.php

# Ausgabe sollte etwa so aussehen:
# [18:00:00] [INFO] === Reminder-Job gestartet ===
# [18:00:01] [SUCCESS] ✅ 2 Erinnerungen erfolgreich versendet
# [18:00:01] [INFO] === Reminder-Job beendet ===
```

### 3. Web-Interface testen

1. **Dashboard aufrufen** - Erinnerungen werden oben angezeigt
2. **Profil > Benachrichtigungen** - Einstellungen konfigurieren
3. **Test-Erinnerung auslösen** - Letzten Zählerstand auf altes Datum setzen

## 📊 Überwachung

### Log-Dateien

```bash
# Reminder-Logs ansehen
tail -f logs/reminders.log

# Fehlerhafte E-Mails finden
grep "ERROR" logs/reminders.log

# Erfolgreiche Sendungen zählen
grep "erfolgreich versendet" logs/reminders.log | wc -l
```

### Dashboard-Übersicht

- **Dashboard** zeigt Erinnerungsstatus und Quick-Actions
- **Profil** zeigt Benachrichtigungsstatistiken  
- **Benachrichtigungslog** in der Datenbank für Debugging

### Datenbank-Abfragen

```sql
-- Benutzer mit aktiven Erinnerungen
SELECT u.name, u.email, ns.reading_reminder_days 
FROM users u 
JOIN notification_settings ns ON u.id = ns.user_id 
WHERE ns.reading_reminder_enabled = 1;

-- Benachrichtigungsstatistiken
SELECT 
    notification_type,
    status,
    COUNT(*) as count 
FROM notification_log 
GROUP BY notification_type, status;

-- Fehlgeschlagene Benachrichtigungen
SELECT * FROM notification_log 
WHERE status = 'failed' 
ORDER BY created_at DESC;
```

## 🔧 Troubleshooting

### Problem: Keine E-Mails ankommen

1. **PHP mail() testen:**
```bash
php -r "mail('test@example.com', 'Test', 'Test-Nachricht');"
```

2. **SMTP-Logs prüfen:**
```bash
tail -f /var/log/mail.log
```

3. **Spam-Ordner prüfen** 

4. **Alternative E-Mail-Bibliothek verwenden:**
```bash
composer require phpmailer/phpmailer
```

### Problem: Cron-Job läuft nicht

1. **Cron-Service prüfen:**
```bash
sudo systemctl status cron
```

2. **Cron-Logs prüfen:**
```bash
grep CRON /var/log/syslog
```

3. **Pfade und Berechtigungen prüfen:**
```bash
ls -la scripts/send-reminders.php
which php
```

### Problem: Falsche Erinnerungslogik

```bash
# Debug-Modus aktivieren
php scripts/test-notifications.php check

# Zeigt genau welche Benutzer Erinnerungen benötigen und warum
```

## 📝 Anpassungen

### Erinnerungslogik ändern

In `includes/NotificationManager.php` die Funktion `needsReadingReminder()` anpassen:

```php
// Beispiel: Erinnerung nur am 25. des Monats
if (date('j') !== 25) {
    return ['needed' => false];
}
```

### E-Mail-Templates anpassen

In `NotificationManager::sendReminderEmail()`:

```php
$message = "Individuelle E-Mail-Vorlage hier...";
```

### Zusätzliche Benachrichtigungsarten

```sql
-- Neue Benachrichtigungsart hinzufügen
ALTER TABLE notification_log 
MODIFY notification_type ENUM(
    'reading_reminder',
    'high_usage_alert', 
    'cost_alert',
    'tariff_reminder',
    'system_notification',
    'maintenance_reminder'  -- NEU
) NOT NULL;
```

## ✅ Checkliste für Go-Live

- [ ] Datenbank-Updates ausgeführt (`sql/notifications.sql`)
- [ ] Test-Script erfolgreich (`php scripts/test-notifications.php install`)
- [ ] Cron-Job eingerichtet und getestet
- [ ] Test-E-Mail gesendet und empfangen
- [ ] Logs-Ordner erstellt und beschreibbar
- [ ] Benutzereinstellungen im Profil verfügbar
- [ ] Dashboard zeigt Erinnerungen an
- [ ] E-Mail-Konfiguration funktioniert
- [ ] Überwachung eingerichtet (Log-Rotation)

## 🎉 Fertig!

Das Benachrichtigungssystem ist jetzt einsatzbereit. Benutzer erhalten automatisch Erinnerungen zur Zählerstand-Erfassung und können alle Einstellungen selbst verwalten.

**Nächste Schritte:**
- Benutzer über neue Funktionen informieren
- Cron-Job-Performance überwachen  
- Feedback sammeln und weitere Features entwickeln

---

*Bei Fragen oder Problemen: Test-Scripts verwenden und Log-Dateien prüfen.*
