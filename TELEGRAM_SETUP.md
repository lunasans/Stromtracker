# Telegram Bot für Stromtracker - Setup Guide

## 🤖 Telegram Bot erstellen

### 1. Bot bei BotFather erstellen

1. **Telegram öffnen** und nach `@BotFather` suchen
2. **Chat starten** mit `/start`
3. **Neuen Bot erstellen:**
   ```
   /newbot
   ```
4. **Bot-Namen eingeben:**
   ```
   Stromtracker Benachrichtigungen
   ```
5. **Bot-Username eingeben:**
   ```
   ihr_stromtracker_bot
   ```
6. **Bot-Token kopieren** (sieht aus wie: `123456789:ABCdefGHijKLmnopQRstuvwxyz`)

### 2. Bot-Optionen konfigurieren (optional)

```
/setdescription
```
Beschreibung:
```
🔌 Erhalten Sie Erinnerungen für Zählerstand-Ablesungen direkt in Telegram! 

Verbinden Sie diesen Bot mit Ihrem Stromtracker-Account für automatische Benachrichtigungen.
```

```
/setabouttext
```
```
Stromtracker Bot für intelligente Verbrauchserinnerungen ⚡
```

```
/setcommands
```
```
start - Bot starten und Chat-ID anzeigen
help - Hilfe und Anweisungen
status - Verbindungsstatus prüfen
```

## ⚙️ Stromtracker konfigurieren

### 1. Datenbank-Updates

```bash
# Telegram-Erweiterungen installieren
mysql -u username -p stromtracker < sql/telegram.sql
```

### 2. Bot-Token konfigurieren

**Option A: Über Datenbank**
```sql
UPDATE telegram_config 
SET bot_token = 'IHR_BOT_TOKEN_HIER',
    bot_username = 'ihr_bot_username',
    is_active = TRUE
WHERE id = 1;
```

**Option B: Über PHP (admin.php)**
```php
// Falls Admin-Interface vorhanden
$botToken = 'YOUR_BOT_TOKEN_HERE';
$botUsername = 'your_bot_username';
```

## 👤 Benutzer-Setup

### 1. Chat-ID ermitteln

**Benutzer startet Bot:**
1. Bot in Telegram suchen: `@ihr_stromtracker_bot`
2. `/start` senden
3. Bot antwortet mit Chat-ID

**Manuell Chat-ID finden:**
```
https://api.telegram.org/bot<BOT_TOKEN>/getUpdates
```

### 2. Telegram in Stromtracker aktivieren

1. **Profil öffnen** → **Benachrichtigungen**
2. **Telegram-Bereich**:
   - ✅ Telegram aktivieren
   - Chat-ID eingeben
   - **Verifizierung starten**
3. **Code aus Telegram eingeben**
4. **Einstellungen speichern**

## 🧪 Tests

### 1. System-Tests

```bash
# Telegram-System prüfen
php scripts/test-notifications.php telegram

# Test-Nachricht senden
php scripts/test-telegram.php send 1

# Bot-Info anzeigen  
php scripts/test-telegram.php info
```

### 2. Manueller Test

**Über Profil:**
- Telegram aktivieren
- Test-Nachricht senden
- Verifizierung prüfen

**Über Cron:**
```bash
php scripts/send-reminders.php
```

## 📱 Bot-Befehle

### Für Benutzer

- `/start` - Bot starten, Chat-ID anzeigen
- `/help` - Hilfe und Anweisungen
- `/status` - Verbindungsstatus mit Stromtracker

### Für Admins (optional)

- `/stats` - Nutzungsstatistiken
- `/broadcast` - Nachricht an alle Benutzer
- `/config` - Bot-Konfiguration

## 🔒 Sicherheit

### Bot-Token schützen

```bash
# .env Datei erstellen
echo "TELEGRAM_BOT_TOKEN=YOUR_TOKEN_HERE" > .env
chmod 600 .env
```

```php
// config/telegram.php
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
```

### Chat-ID Validierung

```php
// Nur verifizierte Chat-IDs akzeptieren
if (!$telegramSettings['telegram_verified']) {
    throw new Exception('Telegram nicht verifiziert');
}
```

## 🚀 Produktiv-Betrieb

### 1. Webhook konfigurieren (optional)

```php
// Für Live-Server mit HTTPS
$webhookUrl = 'https://yourdomain.com/api/telegram-webhook.php';
$result = file_get_contents("https://api.telegram.org/bot{$botToken}/setWebhook?url={$webhookUrl}");
```

### 2. Cron-Job erweitern

```bash
# Bestehenden Cron-Job um Telegram erweitern
# (automatisch durch erweiterten NotificationManager)
0 18 * * * /usr/bin/php /pfad/zu/stromtracker/scripts/send-reminders.php
```

### 3. Monitoring

```bash
# Telegram-Logs überwachen
tail -f logs/telegram.log

# Fehlerhafte Nachrichten finden
grep "failed" logs/telegram.log

# Statistiken prüfen
php scripts/test-telegram.php stats
```

## ⚡ Vorteile vs E-Mail

### Telegram-Vorteile:
- ✅ Sofortige Zustellung (Push-Notification)
- ✅ Höhere Öffnungsrate als E-Mail
- ✅ Rich-Text Formatierung (HTML/Markdown)
- ✅ Interactive Buttons möglich
- ✅ Kostenlos und zuverlässig
- ✅ Mobile-first, überall verfügbar

### E-Mail-Vorteile:
- ✅ Universelle Verfügbarkeit
- ✅ Professioneller Standard
- ✅ Längere Nachrichten möglich
- ✅ Keine zusätzliche App nötig

## 🔧 Troubleshooting

### Bot antwortet nicht
```bash
# Bot-Token prüfen
curl "https://api.telegram.org/bot<TOKEN>/getMe"

# Bot-Status in Datenbank
SELECT * FROM telegram_config WHERE is_active = 1;
```

### Chat-ID ungültig
```bash
# Chat-ID manuell testen
curl "https://api.telegram.org/bot<TOKEN>/getChat?chat_id=<CHAT_ID>"
```

### Nachrichten kommen nicht an
```bash
# Telegram-Logs prüfen
tail -f logs/telegram.log

# API-Rate-Limits prüfen
# Telegram: 30 Nachrichten/Sekunde max
```

## 📋 Beispiel-Nachrichten

### Zählerstand-Erinnerung
```
⚡ Stromtracker Erinnerung

Hallo Max!

📊 Zählerstand erfassen
Ihr letzter Zählerstand ist vom 15.07.2024 (vor 28 Tagen).

💡 Vorgeschlagenes Datum: 01.08.2024

🔗 Jetzt erfassen: [Zum Stromtracker](https://domain.de/zaehlerstand.php)
⚙️ Einstellungen: [Profil](https://domain.de/profil.php)
```

### Hoher Verbrauch-Alarm
```
⚠️ Hoher Verbrauch-Alarm

Hallo Max!

📈 Ihr Stromverbrauch ist überdurchschnittlich hoch:

🔸 Aktueller Verbrauch: 215.5 kWh
🔸 Ihr Grenzwert: 180.0 kWh  
🔸 Überschreitung: +35.5 kWh

💡 Prüfen Sie Ihre Geräte auf ungewöhnliche Aktivität.

📊 [Zur Auswertung](https://domain.de/auswertung.php)
```

## ✅ Checkliste

- [ ] Bot bei BotFather erstellt
- [ ] Bot-Token kopiert und gesichert
- [ ] `sql/telegram.sql` importiert
- [ ] Bot-Token in `telegram_config` eingetragen
- [ ] Bot-Befehle konfiguriert (/setcommands)
- [ ] Test-Nachricht erfolgreich gesendet
- [ ] Benutzer-Verifizierung funktioniert
- [ ] Cron-Job sendet Telegram-Nachrichten
- [ ] Logs und Monitoring aktiviert

---

🎉 **Telegram-Benachrichtigungen sind jetzt aktiv!**

Benutzer erhalten Erinnerungen wahlweise per **E-Mail UND/ODER Telegram** - für maximale Zuverlässigkeit!
