# 🔌 Stromtracker - Smart Energy Management

![Logo - Stromtracker](https://rnu.ovh/8w "Logo")

Eine moderne Web-Anwendung zur **intelligenten Stromverbrauchsverwaltung** mit PHP, MySQL und **Telegram Bot Integration**! ⚡

## ✨ Neue Features

### 🤖 **Telegram Bot - Game Changer!**
- **Automatische Zählerstandserfassung** direkt über Telegram
- **Intelligente Texterkennung** - Bot versteht verschiedene Eingabeformate
- **Sofortige Berechnungen** - Verbrauch, Kosten, Trends in Echtzeit  
- **Smart Commands** - Status, Statistiken, Korrekturen per Chat
- **Multi-User Support** - Jeder Benutzer hat seinen eigenen Bot

### 📸 **OCR-Zählerstandserkennung**
- **Zählerstand per Foto** automatisch erkennen
- **Deutsche Zahlenformate** vollständig unterstützt
- **Verschiedene Zählertypen** (HELIOWATT, Digital, Analog)
- **Intelligente Fehlerkorrektur** mit Fallback-Optionen

### 🏠 **Smart Home Integration**
- **Tasmota-Geräte** nahtlos einbinden
- **Echtzeit-Monitoring** von Smart Plugs
- **Live-Diagramme** für Stromverbrauch
- **API-Integration** für externe Systeme

---

## 🚀 Hauptfunktionen

### **📊 Verbrauchsverwaltung**
- **🤖 Telegram Bot** - Zählerstände per Chat erfassen
- **📸 OCR-Erkennung** - Automatische Texterkennung aus Fotos  
- **📱 Mobile Erfassung** - Responsive Web-Interface
- **🔢 Flexible Eingabe** - Verschiedene Zahlenformate unterstützt
- **⚡ Sofortberechnung** - Verbrauch und Kosten in Echtzeit
- **🛡️ Plausibilitätsprüfung** - Intelligente Validierung

### **🏠 Geräte-Management**
- **📋 Geräteregister** - Alle Elektrogeräte verwalten
- **🏷️ Kategorisierung** - Nach Räumen und Gerätetypen
- **📈 Verbrauchstracking** - Leistungsaufnahme überwachen
- **🔌 Tasmota-Integration** - Smart Plugs automatisch einbinden
- **📊 Live-Monitoring** - Echtzeitdaten von Smart-Geräten

### **💰 Tarif-Verwaltung**
- **📋 Multi-Tarif-Support** - Verschiedene Stromtarife verwalten
- **💶 Arbeitspreis & Grundgebühr** - Vollständige Kostenrechnung
- **📅 Zeitbasierte Tarife** - Gültigkeitszeiträume definieren
- **💳 Abschlagsverwaltung** - Monatliche Vorauszahlungen
- **📊 Differenz-Berechnung** - Guthaben/Nachzahlung ermitteln

### **📈 Auswertungen & Analytics**
- **📊 Interaktive Charts** - Verbrauchstrends visualisiert
- **📅 Jahresvergleiche** - Langzeit-Entwicklung analysieren
- **💹 Kostenanalyse** - Detaillierte Ausgabenübersicht  
- **🎯 Verbrauchsprognosen** - KI-basierte Vorhersagen
- **📋 Export-Funktionen** - Daten für Excel/CSV

### **👤 Profil & Settings**
- **🖼️ Profilbilder** - Persönliche Avatare
- **🌙 Dark/Light Theme** - Augenschonende Modi
- **📱 PWA-Support** - App-ähnliches Erlebnis
- **🔐 API-Schlüssel** - Für externe Integrationen
- **🔔 Benachrichtigungen** - E-Mail & Telegram-Alerts

---

## 🤖 Telegram Bot - Vollständige Anleitung

### **🛠️ Bot-Setup (3 Schritte)**

#### **1. Bot erstellen bei @BotFather**
```
1. Telegram öffnen → @BotFather suchen
2. /newbot senden
3. Bot-Name: "Mein Stromtracker Bot"  
4. Username: "mein_stromtracker_bot"
5. 🔑 Bot-Token kopieren: 123456789:ABCdef...
```

#### **2. Bot im Profil konfigurieren**
```
1. stromtracker.neuhaus.or.at/profil.php öffnen
2. "Telegram-Benachrichtigungen" → Bot-Token einfügen
3. Speichern ✅
4. Chat-ID automatisch verifizieren
```

#### **3. Bot aktivieren**
```
1. Ihren Bot in Telegram suchen (@mein_stromtracker_bot)
2. /start senden
3. Verifizierungscode eingeben (Website)
4. 🎉 Bot ist bereit!
```

### **💬 Bot-Nutzung - So einfach!**

#### **📊 Zählerstand erfassen:**
```
Sie: 12450
Bot: ✅ Zählerstand erfasst!
     📊 Neuer Stand: 12.450 kWh
     ⚡ Verbrauch: 125 kWh
     💰 Kosten: 31,25 €
     📈 Tagesverbrauch: 4,2 kWh/Tag
```

#### **📈 Status & Statistiken:**
```
Sie: Status
Bot: 📊 Status für Max Mustermann
     🔢 Letzter Stand: 12.450 kWh
     📅 Erfasst am: 03.09.2025  
     📈 Jahresverbrauch: 3.250 kWh

Sie: Verbrauch
Bot: 📊 Dieser Monat: 180 kWh
     📈 Hochrechnung: 540 kWh
     📉 Letzter Monat: 165 kWh
```

#### **🔧 Erweiterte Funktionen:**
```
Sie: Korrektur: 12500
Bot: ✅ Zählerstand korrigiert!
     🔄 Alt: 12.450 kWh → Neu: 12.500 kWh

Sie: Tarif
Bot: 💰 Preis: 0,3200 €/kWh
     🏠 Grundgebühr: 12,50 €/Monat

Sie: /help
Bot: 🤖 Vollständige Anleitung...
```

### **🎯 Flexible Eingabeformate:**
Der Bot versteht alles:
- **Einfach:** `12450`
- **Mit Text:** `Stand: 12450` oder `Zählerstand 12450 kWh`
- **Formatiert:** `12.450` oder `12,450`
- **Bot-Befehl:** `/stand 12450`

### **⚡ Smart Features:**
- **🔍 Automatische Erkennung** - Verschiedene Eingabeformate
- **✅ Plausibilitätsprüfung** - Verhindert Eingabefehler
- **🔄 Einfache Korrekturen** - Fehler schnell beheben
- **📊 Sofortige Berechnungen** - Verbrauch, Kosten, Trends
- **📈 Intelligente Statistiken** - Hochrechnungen & Vergleiche

---

## 🔧 Installation & Setup

### **🗄️ Datenbank-Setup**
```sql
# 1. Datenbank erstellen
CREATE DATABASE stromtracker;

# 2. SQL-Datei importieren
mysql -u username -p stromtracker < sql/stromtracker.sql

# 3. Telegram-Log-Tabellen (optional)
mysql -u username -p stromtracker < sql/telegram-log-tables.sql
```

### **⚙️ Konfiguration**
```php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'stromtracker');
```

### **📁 Verzeichnisse berechtigen**
```bash
chmod 755 uploads/profile/
chmod 755 logs/
```

### **🤖 Telegram Bot konfigurieren**
```bash
# Als Admin Webhooks registrieren
php scripts/setup-telegram-webhook-userbot.php

# System testen
php scripts/test-telegram-bot.php
```

---

## 🔐 Standard-Login

**Admin-Account:**
- **E-Mail:** `admin@test.com`
- **Passwort:** `password123`

**❗ Wichtig:** Passwort nach dem ersten Login ändern!

---

## 🖥️ Systemanforderungen

### **Server:**
- **PHP:** 7.4+ (8.0+ empfohlen)
- **MySQL:** 5.7+ oder MariaDB 10.3+
- **Webserver:** Apache/Nginx
- **Extensions:** GD, PDO, JSON, cURL
- **HTTPS:** Erforderlich für Telegram Webhooks

### **Browser (für OCR & PWA):**
- **Chrome:** 60+
- **Firefox:** 55+  
- **Safari:** 11+
- **Edge:** 79+
- **Features:** JavaScript, FileAPI, ServiceWorker

---

## 🏗️ Technologie-Stack

### **Backend:**
- **PHP 8.0+** - Moderne Server-Logik
- **MySQL/MariaDB** - Zuverlässige Datenhaltung
- **RESTful APIs** - Für externe Integrationen
- **Telegram Bot API** - Webhook-basierte Kommunikation

### **Frontend:**
- **Bootstrap 5** - Responsive UI-Framework
- **Chart.js** - Interaktive Diagramme
- **Tesseract.js** - Client-seitige OCR-Engine
- **Service Worker** - PWA-Funktionalität
- **Bootstrap Icons** - Moderne Icon-Bibliothek

### **Integration:**
- **Telegram API** - Bot-Funktionalität
- **Tasmota API** - Smart Home Integration
- **OCR-Engine** - Automatische Texterkennung
- **Webhook-System** - Echtzeit-Kommunikation

---

## 📂 Projektstruktur

```
stromtracker/
├── 📁 config/              # Konfiguration
│   ├── database.php        # Datenbankverbindung
│   └── session.php         # Session & Auth
├── 📁 includes/            # PHP-Includes
│   ├── TelegramBotHandler.php  # Bot-Logik ⭐
│   ├── TelegramManager.php     # Telegram-Integration
│   └── NotificationManager.php # Benachrichtigungen
├── 📁 api/                 # API-Endpoints
│   ├── telegram-webhook.php   # Telegram Webhook ⭐
│   └── tasmota.php           # Smart Device API
├── 📁 scripts/             # Admin-Tools
│   ├── setup-telegram-webhook-userbot.php  # Bot-Setup ⭐
│   └── test-telegram-bot.php               # Bot-Tests
├── 📁 sql/                 # Datenbank
│   ├── stromtracker.sql        # Hauptstruktur
│   └── telegram-log-tables.sql # Bot-Logging ⭐
├── 📁 css/ js/ uploads/    # Assets & Uploads
└── 📄 *.php               # Hauptseiten (Dashboard, Profil, etc.)
```

---

## 🎯 Features im Detail

### **🔥 Highlights**
- **⚡ Sofortige Zählerstandserfassung** über Telegram
- **🧠 KI-basierte OCR-Erkennung** aus Fotos
- **📊 Echtzeit-Verbrauchsmonitoring** mit Smart Devices  
- **💰 Präzise Kostenberechnung** mit Multi-Tarif-Support
- **📱 Progressive Web App** - Funktioniert offline
- **🌙 Dark/Light Mode** - Augenschonend rund um die Uhr

### **🚀 Performance & Sicherheit**
- **⚡ Optimierte Datenbank** - Schnelle Queries mit Indizierung
- **🛡️ CSRF-Protection** - Schutz vor Angriffen
- **🔐 Sichere API-Integration** - Token-basierte Authentifizierung
- **📱 Responsive Design** - Perfekt auf allen Geräten
- **🔄 Webhook-basiert** - Echtzeit-Updates ohne Polling

### **🎨 Benutzerfreundlichkeit**
- **🎯 Intuitive Navigation** - Sofort verständlich
- **📊 Interaktive Charts** - Daten visuell erfassen
- **🔔 Smart Notifications** - E-Mail & Telegram-Alerts
- **💾 Automatische Backups** - Daten sicher gespeichert
- **📱 Mobile-First** - Optimiert für Smartphone-Nutzung

---

## 📚 API-Documentation

### **🤖 Telegram Bot API**
```php
// Webhook-Endpoint
POST /api/telegram-webhook.php

// Automatische Verarbeitung von:
// - Zählerständen (12450, Stand: 12450, etc.)
// - Bot-Commands (/start, /help, /status)
// - Korrekturen (Korrektur: 12500)
// - Status-Abfragen (Status, Verbrauch, Tarif)
```

### **🏠 Tasmota Smart Device API**
```json
POST /api/receive-tasmota.php
{
  "api_key": "st_xxxxxxxxx...",
  "device_name": "Steckdose-Wohnzimmer", 
  "energy_today": 2.5,
  "energy_total": 157.3,
  "power": 850,
  "voltage": 230.1,
  "current": 3.7
}
```

### **📊 Chart Data API**
```json
GET /api/tasmota-chart-data.php?device_id=1&timerange=60&type=power

Response:
{
  "success": true,
  "chart": {
    "type": "Leistung",
    "unit": "W",
    "labels": ["14:00", "14:01", ...],
    "data": [850, 832, 801, ...]
  }
}
```

---

## 🆕 Was ist neu?

### **Version 3.0 - Telegram Revolution** 🎉
- **🤖 Vollständiger Telegram Bot** - Zählerstände per Chat
- **🧠 Intelligente Texterkennung** - Versteht natürliche Sprache  
- **📊 Erweiterte Statistiken** - Verbrauchstrends und Prognosen
- **🔄 Korrektur-System** - Einfache Fehlerbehandlung
- **👥 Multi-User Support** - Jeder Benutzer sein eigener Bot

### **Version 2.1 - OCR & Smart Features**
- **📸 OCR-Zählerstandserkennung** - Fotos automatisch auswerten
- **🏠 Tasmota-Integration** - Smart Home Geräte einbinden
- **📱 PWA-Support** - App-ähnliches Erlebnis
- **🌙 Dark Mode** - Augenschonende Darstellung
- **⚡ Performance-Optimierung** - Schnellere Ladezeiten

---

## 📞 Support & Community

### **🐛 Issues & Feedback**
- **GitHub Issues:** Fehler melden und Features vorschlagen
- **Community:** Erfahrungen austauschen und Tipps teilen
- **Updates:** Regelmäßige Verbesserungen und neue Features

### **📖 Weiterführende Links**
- **Telegram Bot API:** https://core.telegram.org/bots/api
- **Tasmota Documentation:** https://tasmota.github.io/docs/
- **Bootstrap 5:** https://getbootstrap.com/
- **Chart.js:** https://www.chartjs.org/

---

## 📄 Lizenz

**Open Source** - Kann frei verwendet, verändert und weitergegeben werden.

**MIT License** - Kommerzielle Nutzung erlaubt.

---

## 🎉 Fazit

**Stromtracker 3.0** ist mehr als nur eine Web-App - es ist Ihr **persönlicher Energie-Assistent**! 

Mit dem **Telegram Bot** wird die Zählerstandserfassung so einfach wie eine Textnachricht. Die **OCR-Funktion** macht Fotos zu Daten, und die **Smart Home Integration** bringt alles zusammen.

**🚀 Nie war Energiemanagement so einfach und effizient!**

---

**Version 3.0** - Mit revolutionärem Telegram Bot System | Build 2025-09

⭐ **Powered by Intelligence, Driven by Simplicity** ⭐
