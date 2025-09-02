# Stromtracker
![Logo - Stromtracker](https://rnu.ovh/8w "Logo")
Eine einfache Web-Anwendung zur Verwaltung des Stromverbrauchs mit PHP und MySQL.

## Funktionen

**Verbrauchsverwaltung**
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

## Login

**Standard-Account:**
- E-Mail: `admin@test.com`
- Passwort: `password123`

## Systemanforderungen

**Server:**
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx Webserver
- GD-Extension für Bildverarbeitung

**Browser (für OCR):**
- Chrome 60+, Firefox 55+, Safari 11+, Edge 79+
- JavaScript aktiviert
- Kamera/Dateizugriff für Bildupload

## Technologie

**Backend:**
- PHP mit PDO
- MySQL Datenbank
- Session-Management mit CSRF-Schutz

**Frontend:**
- Bootstrap 5 (responsive UI)
- Chart.js (Diagramme)
- **🆕 Tesseract.js (OCR-Engine)**
- Bootstrap Icons

**Features:**
- PWA (Service Worker, Manifest)
- Dark/Light Theme Switch
- Mobile-optimiert

## Ordnerstruktur

```
stromtracker/
├── config/          # Datenbankverbindung, Session
├── includes/        # Header, Footer, Navigation  
├── css/            # Stylesheets
├── js/             # JavaScript-Dateien
├── sql/            # Datenbankstruktur
├── uploads/        # Profilbilder
├── api/            # API-Endpoints (Tasmota)
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

**🆕 OCR & Smart Features**
- **Zählerstand per Foto erkennen**
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

## Lizenz

Open Source - kann frei verwendet und angepasst werden.

---

**Version 2.1** - Mit OCR-Zählerstandserkennung | Build <?= date('Ymd') ?>