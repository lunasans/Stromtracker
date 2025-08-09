-- sql/setup.sql
-- Stromtracker Datenbank-Setup

-- Datenbank erstellen
CREATE DATABASE IF NOT EXISTS stromtracker 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE stromtracker;

-- Users Tabelle
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Geräte Tabelle
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    wattage INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Stromverbrauch Tabelle
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

-- Strompreise Tabelle
CREATE TABLE energy_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate DECIMAL(10,4) NOT NULL COMMENT 'Preis pro kWh in Euro',
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Test-User erstellen (Passwort: password123)
INSERT INTO users (email, name, password) VALUES 
('admin@test.com', 'Admin User', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Test-Geräte
INSERT INTO devices (user_id, name, category, wattage) VALUES 
(1, 'Kühlschrank', 'Küchengerät', 150),
(1, 'Waschmaschine', 'Haushaltsgerät', 2000),
(1, 'LED-TV 55"', 'Unterhaltung', 120),
(1, 'Geschirrspüler', 'Küchengerät', 1800),
(1, 'Computer + Monitor', 'Büro', 300);

-- Test-Verbrauchsdaten
INSERT INTO energy_consumption (user_id, device_id, consumption, cost, timestamp) VALUES 
(1, 1, 3.2, 0.96, '2025-01-01 08:00:00'),
(1, 2, 2.1, 0.63, '2025-01-01 10:30:00'),
(1, 3, 1.8, 0.54, '2025-01-01 20:00:00'),
(1, 1, 3.1, 0.93, '2025-01-02 08:00:00'),
(1, 4, 2.5, 0.75, '2025-01-02 19:00:00');

-- Aktueller Strompreis
INSERT INTO energy_rates (rate, valid_from, is_active) VALUES 
(0.30, '2025-01-01', TRUE);

-- Nützliche Views für Reports
CREATE VIEW monthly_consumption AS
SELECT 
    u.name as user_name,
    d.name as device_name,
    d.category,
    YEAR(ec.timestamp) as year,
    MONTH(ec.timestamp) as month,
    SUM(ec.consumption) as total_kwh,
    SUM(ec.cost) as total_cost,
    COUNT(*) as readings
FROM energy_consumption ec
JOIN users u ON ec.user_id = u.id
LEFT JOIN devices d ON ec.device_id = d.id
GROUP BY u.id, d.id, YEAR(ec.timestamp), MONTH(ec.timestamp)
ORDER BY year DESC, month DESC;

CREATE VIEW daily_consumption AS
SELECT 
    u.name as user_name,
    DATE(ec.timestamp) as date,
    SUM(ec.consumption) as total_kwh,
    SUM(ec.cost) as total_cost,
    COUNT(*) as readings
FROM energy_consumption ec
JOIN users u ON ec.user_id = u.id
GROUP BY u.id, DATE(ec.timestamp)
ORDER BY date DESC;