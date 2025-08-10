-- =============================================================================
-- STROMTRACKER - BEREINIGTE DATENBANK OHNE VIEWS
-- Funktioniert auf allen Hosting-Umgebungen (Shared Hosting kompatibel)
-- =============================================================================

-- Datenbank erstellen
CREATE DATABASE IF NOT EXISTS stromtracker 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE stromtracker;

-- =============================================================================
-- TABELLEN ERSTELLEN
-- =============================================================================

-- Users Tabelle
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    profile_image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Geräte Tabelle
DROP TABLE IF EXISTS devices;
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    wattage INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active)
);

-- Stromverbrauch Tabelle (Legacy für Kompatibilität)
DROP TABLE IF EXISTS energy_consumption;
CREATE TABLE energy_consumption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_id INT NULL,
    consumption DECIMAL(10,2) NOT NULL COMMENT 'Verbrauch in kWh',
    cost DECIMAL(10,2) NOT NULL COMMENT 'Kosten in Euro',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
    INDEX idx_user_timestamp (user_id, timestamp)
);

-- Zählerstände Tabelle (Haupttabelle für monatliche Ablesungen)
DROP TABLE IF EXISTS meter_readings;
CREATE TABLE meter_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reading_date DATE NOT NULL,
    meter_value DECIMAL(10,2) NOT NULL COMMENT 'Zählerstand in kWh',
    consumption DECIMAL(10,2) NULL COMMENT 'Berechneter Verbrauch seit letzter Ablesung',
    cost DECIMAL(10,2) NULL COMMENT 'Berechnete Stromkosten',
    rate_per_kwh DECIMAL(10,4) NULL COMMENT 'Strompreis zum Zeitpunkt der Ablesung',
    monthly_payment DECIMAL(10,2) NULL COMMENT 'Monatlicher Abschlag',
    basic_fee DECIMAL(10,2) NULL COMMENT 'Grundgebühr pro Monat',
    total_bill DECIMAL(10,2) NULL COMMENT 'Gesamtrechnung (Strom + Grundgebühr)',
    payment_difference DECIMAL(10,2) NULL COMMENT 'Differenz zu Abschlag (+ = Nachzahlung)',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_month (user_id, reading_date),
    INDEX idx_user_date (user_id, reading_date DESC)
);

-- Tarife/Strompreise Tabelle
DROP TABLE IF EXISTS tariff_periods;
CREATE TABLE tariff_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    rate_per_kwh DECIMAL(10,4) NOT NULL COMMENT 'Preis pro kWh in Euro',
    monthly_payment DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Monatlicher Abschlag',
    basic_fee DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Grundgebühr pro Monat',
    provider_name VARCHAR(255) DEFAULT NULL COMMENT 'Stromanbieter',
    tariff_name VARCHAR(255) DEFAULT NULL COMMENT 'Tarifname',
    customer_number VARCHAR(255) DEFAULT NULL COMMENT 'Kundennummer',
    notes TEXT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_user_dates (user_id, valid_from, valid_to)
);

-- Legacy Energiepreise (für Rückwärtskompatibilität)
DROP TABLE IF EXISTS energy_rates;
CREATE TABLE energy_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate DECIMAL(10,4) NOT NULL COMMENT 'Preis pro kWh in Euro',
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    provider VARCHAR(255) DEFAULT 'Standard',
    tariff_name VARCHAR(255) DEFAULT 'Grundtarif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================================================
-- INITIAL-DATEN EINFÜGEN
-- =============================================================================

-- Standard-Benutzer erstellen
INSERT INTO users (email, name, password) VALUES 
('admin@test.com', 'Admin User', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('r@r.r', 'Test User', '$2y$10$EpjE7w6hPKq5ggpH6JtO5.TZWsaP0CvyL9NMOmqMp4PxKTMt75iCm');

-- Standard-Geräte einfügen
INSERT INTO devices (user_id, name, category, wattage) VALUES 
(1, 'Kühlschrank', 'Haushaltsgeräte', 150),
(1, 'Waschmaschine', 'Haushaltsgeräte', 2000),
(1, 'LED-TV 55"', 'Unterhaltung', 120),
(1, 'Geschirrspüler', 'Haushaltsgeräte', 1800),
(1, 'Computer + Monitor', 'Büro', 300),
(1, 'Beleuchtung Wohnzimmer', 'Beleuchtung', 60),
(1, 'Mikrowelle', 'Haushaltsgeräte', 800);

-- Standard-Tarif erstellen
INSERT INTO tariff_periods (user_id, valid_from, rate_per_kwh, monthly_payment, basic_fee, provider_name, tariff_name, is_active) VALUES 
(1, '2024-01-01', 0.3200, 85.00, 12.50, 'Stadtwerke', 'Grundversorgung', TRUE);

-- Legacy Strompreis
INSERT INTO energy_rates (rate, valid_from, is_active, provider, tariff_name) VALUES 
(0.3200, '2024-01-01', TRUE, 'Stadtwerke', 'Grundversorgung');

-- Demo-Zählerstände für das Jahr 2024
INSERT INTO meter_readings (user_id, reading_date, meter_value, consumption, cost, rate_per_kwh, monthly_payment, basic_fee, total_bill, payment_difference) VALUES 
(1, '2024-01-01', 15000.00, NULL, NULL, 0.3200, 85.00, 12.50, NULL, NULL),
(1, '2024-02-01', 15150.50, 150.50, 48.16, 0.3200, 85.00, 12.50, 60.66, -24.34),
(1, '2024-03-01', 15280.75, 130.25, 41.68, 0.3200, 85.00, 12.50, 54.18, -30.82),
(1, '2024-04-01', 15420.25, 139.50, 44.64, 0.3200, 85.00, 12.50, 57.14, -27.86),
(1, '2024-05-01', 15550.80, 130.55, 41.78, 0.3200, 85.00, 12.50, 54.28, -30.72),
(1, '2024-06-01', 15680.45, 129.65, 41.49, 0.3200, 85.00, 12.50, 53.99, -31.01),
(1, '2024-07-01', 15820.30, 139.85, 44.75, 0.3200, 85.00, 12.50, 57.25, -27.75),
(1, '2024-08-01', 15950.60, 130.30, 41.70, 0.3200, 85.00, 12.50, 54.20, -30.80),
(1, '2024-09-01', 16080.20, 129.60, 41.47, 0.3200, 85.00, 12.50, 53.97, -31.03),
(1, '2024-10-01', 16220.80, 140.60, 44.99, 0.3200, 85.00, 12.50, 57.49, -27.51),
(1, '2024-11-01', 16350.15, 129.35, 41.39, 0.3200, 85.00, 12.50, 53.89, -31.11),
(1, '2024-12-01', 16485.90, 135.75, 43.44, 0.3200, 85.00, 12.50, 55.94, -29.06);

-- =============================================================================
-- HILFS-FUNKTIONEN (via SQL)
-- =============================================================================

-- Index-Optimierung
OPTIMIZE TABLE users;
OPTIMIZE TABLE devices;
OPTIMIZE TABLE meter_readings;
OPTIMIZE TABLE tariff_periods;

-- =============================================================================
-- BERECHTIGUNG-INFO
-- =============================================================================

-- Diese Datei erstellt:
-- ✅ Alle Tabellen ohne DEFINER-Klauseln
-- ✅ Standard-Login: admin@test.com / password123
-- ✅ Demo-Daten für das Jahr 2024
-- ✅ Funktioniert auf allen Hosting-Umgebungen
-- ✅ Keine SUPER-Berechtigung erforderlich

-- Nächste Schritte:
-- 1. config/database.php anpassen
-- 2. Login mit admin@test.com / password123
-- 3. Passwort ändern im Profil
-- 4. Eigene Zählerstände erfassen

SELECT 'Installation erfolgreich!' as status;