# Stromtracker
![Logo - Stromtracker](https://rnu.ovh/8w "Logo")
Eine einfache Web-Anwendung zur Verwaltung des Stromverbrauchs mit PHP und MySQL.

## Funktionen

**Verbrauchsverwaltung**
- Monatliche Zählerstände erfassen
- Automatische Verbrauchsberechnung
- Kostenermittlung basierend auf Tarifen

**Geräte-Verwaltung**
- Elektrische Geräte registrieren
- Kategorisierung nach Geräteart
- Leistungsaufnahme verfolgen

**Tarif-Verwaltung**
- Stromtarife mit Arbeitspreis und Grundgebühr
- Monatliche Abschlagsverwaltung
- Mehrere Tarif-Perioden möglich

**Auswertungen**
- Verbrauchstrends als Diagramme
- Jahresvergleiche
- Kostenentwicklung visualisiert

**Profil & Einstellungen**
- Benutzerprofile mit Profilbild
- Dark/Light Theme
- Responsive Design für Mobile

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

## Login

**Standard-Account:**
- E-Mail: `admin@test.com`
- Passwort: `password123`

## Systemanforderungen

- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx Webserver
- GD-Extension für Bildverarbeitung

## Technologie

- **Backend:** PHP mit PDO
- **Frontend:** Bootstrap 5, Chart.js
- **Datenbank:** MySQL
- **PWA:** Service Worker, Manifest

## Ordnerstruktur

```
stromtracker/
├── config/          # Datenbankverbindung, Session
├── includes/        # Header, Footer, Navigation  
├── css/            # Stylesheets
├── js/             # JavaScript-Dateien
├── sql/            # Datenbankstruktur
├── uploads/        # Profilbilder
└── *.php           # Hauptseiten
```

## Features

- ✅ Responsive Design
- ✅ Dark/Light Theme
- ✅ CSRF-Schutz
- ✅ Profilbild-Upload
- ✅ Chart-Visualisierungen
- ✅ PWA-fähig
- ✅ Mobile-optimiert

## Lizenz

Open Source - kann frei verwendet und angepasst werden.
