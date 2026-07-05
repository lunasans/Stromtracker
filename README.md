# Stromtracker
![Logo - Stromtracker](https://rnu.ovh/8w "Logo")

Eine einfache Web-Anwendung zur Verwaltung des Stromverbrauchs mit PHP und MySQL.

## 🆕 Neue Features

### 🤖 **Telegram Bot Integration**
- **Automatische Zählerstandserfassung** direkt über Telegram
- **Intelligente Texterkennung** - Bot versteht verschiedene Eingabeformate  
- **Sofortige Berechnungen** - Verbrauch, Kosten, Trends in Echtzeit
- **Smart Commands** - Status, Statistiken, Korrekturen per Chat

**Bot-Nutzung:**
```
Sie: 12450
Bot: ✅ Zählerstand erfasst!
     📊 Neuer Stand: 12.450 kWh
     ⚡ Verbrauch: 125 kWh
     💰 Kosten: 31,25 €

Sie: Status
Bot: 📊 Aktuelle Übersicht...

Sie: Verbrauch  
Bot: 📈 Monatsstatistiken...
```

**Bot-Setup:** Bot bei @BotFather erstellen → Token im Profil eingeben → Chat-ID verifizieren → Fertig! 🎉

## Funktionen

**Verbrauchsverwaltung**
- 🤖 **Telegram Bot** - Zählerstände per Chat erfassen
- Monatliche Zählerstände erfassen (manuell oder per Foto)
- **🆕 OCR-Zählerstandserkennung** - Automatische Texterkennung aus Zählerbildern
- Automatische Verbrauchsberechnung
- Kostenermittlung basierend auf Tarifen
- Intelligente Plausibilitätsprüfung

**Geräte-Verwaltung**
- Elektrische Geräte registrieren
- Kategorisierung nach Geräteart
- Leistungsaufnahme verfolgen
- Tasmota-Integration für Smart-Geräte

**Tarif-Verwaltung**
- Stromtarife mit Arbeitspreis und Grundgebühr
- Monatliche Abschlagsverwaltung
- Mehrere Tarif-Perioden möglich
- Differenz-Berechnung (Guthaben/Nachzahlung)

**Auswertungen**
- Verbrauchstrends als Diagramme
- Jahresvergleiche
- Kostenentwicklung visualisiert
- Statistische Übersichten

**Profil & Einstellungen**
- Benutzerprofile mit Profilbild
- 🔔 **Telegram-Benachrichtigungen** - E-Mail & Bot-Alerts
- Dark/Light Theme
- Responsive Design für Mobile
- API-Schlüssel für externe Geräte

**🆕 OCR-Features**
- **Bildbasierte Zählerstandserkennung** mit Tesseract.js
- Unterstützt deutsche Zahlenformate (12.345,67)
- Erkennt verschiedene Zählertypen (HELIOWATT, etc.)
- Automatische Werteübernahme ins Formular
- Fallback auf manuelle Eingabe bei Erkennungsfehlern

## Installation

1. **Dateien hochladen**
   ```bash
   # Alle Dateien in das Webverzeichnis kopieren
   ```

2. **Datenbank erstellen**
   ```sql
   # sql/stromtracker.sql importieren
   mysql -u username -p < sql/stromtracker.sql
   
   # 🤖 Telegram-Features (optional):
   mysql -u username -p < sql/telegram-log-tables.sql
   ```

3. **Datenbankverbindung konfigurieren**
   ```php
   // config/database.php anpassen
   define('DB_HOST', 'localhost');
   define('DB_USER', 'username');
   define('DB_PASS', 'password');
   define('DB_NAME', 'stromtracker');
   ```

4. **Upload-Ordner berechtigen**
   ```bash
   chmod 755 uploads/profile/
   ```

5. **🆕 OCR-Funktionalität** 
   - Keine zusätzliche Server-Installation nötig
   - Tesseract.js wird automatisch über CDN geladen
   - Funktioniert auf allen modernen Browsern

6. **🤖 Telegram Bot konfigurieren (optional)**
   ```bash
   # Als Admin Webhooks registrieren
   php scripts/setup-telegram-webhook-userbot.php
   
   # System testen
   php scripts/test-telegram-bot.php
   ```

## Login

**Standard-Account:**
- E-Mail: `admin@test.com`
- Passwort: `password123`

## 🤖 Telegram Bot Setup

## Onboarding für Entwickler (Kurz)

Für Teammitglieder, die lokal entwickeln möchten, bitte kurz Husky aktivieren, damit Commit‑Hooks laufen:

```bash
cd v4
npm install
# falls nötig:
npx husky install
npx husky add .husky/commit-msg 'sh ../.githooks/commit-msg "$1"'

# Test-Commit
git commit --allow-empty -m "fix(ci): test husky"
```

Eine ausführliche Checkliste gibt es in `tasks/husky_readme.md`.


### **3 einfache Schritte:**

1. **Bot erstellen:**
   - Telegram öffnen → @BotFather suchen
   - `/newbot` senden → Bot-Namen eingeben
   - Bot-Token kopieren (123456789:ABCdef...)

2. **Bot konfigurieren:**
   - `profil.php` öffnen → "Telegram-Benachrichtigungen"  
   - Bot-Token einfügen → Speichern

3. **Bot aktivieren:**
   - Bot in Telegram suchen → `/start` senden
   - Verifizierungscode im Profil eingeben → Fertig! 🎉

### **Bot-Nutzung:**
Der Bot versteht alle Formate:
- `12450` (einfach)
- `Stand: 12450` (mit Text)
- `Zählerstand 12450 kWh` (ausführlich)
- `12.450` oder `12,450` (formatiert)
- `/stand 12450` (Bot-Befehl)

**Erweiterte Funktionen:**
- `Status` - Aktuelle Übersicht
- `Verbrauch` - Monatsstatistiken  
- `Tarif` - Preise & Kosten
- `Korrektur: 12500` - Werte korrigieren
- `/help` - Vollständige Hilfe

## Systemanforderungen

**Server:**
- PHP 7.4+ (8.0+ empfohlen)
- MySQL 5.7+
- Apache/Nginx Webserver
- GD-Extension für Bildverarbeitung
- **HTTPS erforderlich** für Telegram Webhooks

**Browser (für OCR):**
- Chrome 60+, Firefox 55+, Safari 11+, Edge 79+
- JavaScript aktiviert
- Kamera/Dateizugriff für Bildupload

## Technologie

**Backend:**
- PHP mit PDO
- MySQL Datenbank
- Session-Management mit CSRF-Schutz
- **🤖 Telegram Bot API** - Webhook-basierte Kommunikation

**Frontend:**
- Bootstrap 5 (responsive UI)
- Chart.js (Diagramme)
- **🆕 Tesseract.js (OCR-Engine)**
- Bootstrap Icons

**Features:**
- PWA (Service Worker, Manifest)
- Dark/Light Theme Switch
- Mobile-optimiert
- **🤖 Telegram-Integration**

## Ordnerstruktur

```
stromtracker/
├── config/          # Datenbankverbindung, Session
├── includes/        # Header, Footer, Navigation
│   ├── TelegramBotHandler.php    # 🤖 Bot-Logik
│   └── TelegramManager.php       # 🤖 Telegram-Integration
├── api/             # API-Endpoints
│   └── telegram-webhook.php      # 🤖 Telegram Webhook
├── scripts/         # Admin-Tools  
│   ├── setup-telegram-webhook-userbot.php  # 🤖 Bot-Setup
│   └── test-telegram-bot.php               # 🤖 Bot-Tests
├── css/            # Stylesheets
├── js/             # JavaScript-Dateien
├── sql/            # Datenbankstruktur
│   ├── stromtracker.sql          # Hauptstruktur
│   └── telegram-log-tables.sql   # 🤖 Bot-Logging
├── uploads/        # Profilbilder
└── *.php           # Hauptseiten
```

## Features

**✅ Grundfunktionen**
- Responsive Design
- Dark/Light Theme
- CSRF-Schutz
- Profilbild-Upload
- Chart-Visualisierungen
- PWA-fähig
- Mobile-optimiert

**🆕 Smart Features**
- **🤖 Telegram Bot** - Automatische Zählerstandserfassung
- **📸 Zählerstand per Foto erkennen**
- Deutsche Zahlenformat-Unterstützung
- Intelligente Texterkennung
- Automatische Wertübertragung
- Tasmota Smart-Device Integration
- API für externe Geräte

## Nutzung der OCR-Funktion

1. **Zählerstand erfassen** → Modal öffnen
2. **"Zählerbild auswählen"** klicken
3. **Foto aufnehmen** oder aus Galerie wählen
4. **Automatische Erkennung** abwarten
5. **Erkannten Wert überprüfen** und ggf. korrigieren
6. **Speichern**

**📸 Tipps für beste Erkennung:**
- Foto frontal und gerade aufnehmen
- Gute Beleuchtung verwenden
- Nur Zählerziffern im Bildbereich
- Kontrast zwischen Ziffern und Hintergrund

## API-Integration

**🤖 Telegram Bot:**
```
Webhook: POST /api/telegram-webhook.php
Verarbeitet automatisch:
- Zählerstände (12450, Stand: 12450, etc.)
- Bot-Commands (/start, /help, /status)
- Korrekturen (Korrektur: 12500)
- Status-Abfragen (Status, Verbrauch, Tarif)
```

**Tasmota Smart Plugs:**
```json
POST /api/receive-tasmota.php
{
  "api_key": "st_xxxxxxxxx...",
  "device_name": "Steckdose-Wohnzimmer",
  "energy_today": 2.5,
  "energy_total": 157.3,
  "power": 850
}
```

## 🆕 Was ist neu?

**Version 3.0 - Telegram:**
- 🤖 **Vollständiger Telegram Bot** - Zählerstände per Chat
- 🧠 **Intelligente Texterkennung** - Versteht natürliche Sprache  
- 📊 **Erweiterte Statistiken** - Trends und Prognosen
- 🔄 **Korrektur-System** - Einfache Fehlerbehandlung
- 👥 **Multi-User Support** - Jeder Benutzer eigener Bot

**Version 2.1 - OCR & Smart Features:**
- 📸 **OCR-Zählerstandserkennung** - Fotos automatisch auswerten
- 🏠 **Tasmota-Integration** - Smart Home Geräte einbinden
- 📱 **PWA-Support** - App-ähnliches Erlebnis
- 🌙 **Dark Mode** - Augenschonende Darstellung

## Lizenz

Open Source - kann frei verwendet und angepasst werden.

---

**Version 3.0** - Mit Telegram Bot System | Build 2025-09

🤖 **Der Stromtracker ist jetzt noch smarter - mit Telegram Bot wird Energiemanagement zum Kinderspiel!** ⚡
