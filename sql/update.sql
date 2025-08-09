-- sql/update_schema.sql
-- Vereinfachtes Schema für monatliche Zählerstände

USE stromtracker;

-- Neue Tabelle für Zählerstände
CREATE TABLE IF NOT EXISTS meter_readings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reading_date DATE NOT NULL,
    meter_value DECIMAL(10,2) NOT NULL COMMENT 'Zählerstand in kWh',
    consumption DECIMAL(10,2) NULL COMMENT 'Berechneter Verbrauch seit letzter Ablesung',
    cost DECIMAL(10,2) NULL COMMENT 'Berechnete Kosten',
    rate_per_kwh DECIMAL(10,4) NULL COMMENT 'Strompreis zum Zeitpunkt der Ablesung',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_month (user_id, reading_date),
    INDEX idx_user_date (user_id, reading_date DESC)
);

-- Strompreise vereinfachen
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

-- Alte energy_consumption Tabelle optional behalten für Backup
-- DROP TABLE energy_consumption;

-- Geräte-Tabelle kann bleiben für Referenz/Planung
-- devices Tabelle bleibt unverändert

-- Aktuellen Strompreis einfügen
INSERT INTO energy_rates (rate, valid_from, is_active, provider, tariff_name) VALUES 
(0.32, '2024-01-01', TRUE, 'Stadtwerke', 'Haushaltsstrom'),
(0.28, '2023-01-01', FALSE, 'Stadtwerke', 'Haushaltsstrom Alt');

-- Test-Zählerstände einfügen
INSERT INTO meter_readings (user_id, reading_date, meter_value) VALUES 
(1, '2024-01-01', 1000.00),
(1, '2024-02-01', 1150.50),
(1, '2024-03-01', 1280.75),
(1, '2024-04-01', 1420.25),
(1, '2024-05-01', 1550.80),
(1, '2024-06-01', 1680.45),
(1, '2024-07-01', 1820.30),
(1, '2024-08-01', 1950.60);

-- Verbrauch und Kosten für bestehende Einträge berechnen
UPDATE meter_readings mr1 
JOIN (
    SELECT 
        mr2.id,
        mr2.meter_value - COALESCE(mr_prev.meter_value, 0) as calculated_consumption
    FROM meter_readings mr2
    LEFT JOIN meter_readings mr_prev ON mr_prev.user_id = mr2.user_id 
        AND mr_prev.reading_date = (
            SELECT MAX(reading_date) 
            FROM meter_readings mr3 
            WHERE mr3.user_id = mr2.user_id 
            AND mr3.reading_date < mr2.reading_date
        )
    WHERE mr2.user_id = 1
) calc ON mr1.id = calc.id
SET 
    mr1.consumption = calc.calculated_consumption,
    mr1.cost = calc.calculated_consumption * 0.32,
    mr1.rate_per_kwh = 0.32
WHERE mr1.user_id = 1 AND mr1.consumption IS NULL;

-- Nützliche Views für Reports
CREATE OR REPLACE VIEW monthly_consumption_view AS
SELECT 
    u.name as user_name,
    mr.reading_date,
    YEAR(mr.reading_date) as year,
    MONTH(mr.reading_date) as month,
    MONTHNAME(mr.reading_date) as month_name,
    mr.meter_value,
    mr.consumption,
    mr.cost,
    mr.rate_per_kwh,
    CASE 
        WHEN LAG(mr.consumption) OVER (PARTITION BY mr.user_id ORDER BY mr.reading_date) IS NULL THEN 0
        ELSE mr.consumption - LAG(mr.consumption) OVER (PARTITION BY mr.user_id ORDER BY mr.reading_date)
    END as consumption_diff
FROM meter_readings mr
JOIN users u ON mr.user_id = u.id
WHERE mr.consumption IS NOT NULL
ORDER BY mr.user_id, mr.reading_date DESC;

-- Jahresübersicht
CREATE OR REPLACE VIEW yearly_consumption_view AS
SELECT 
    u.name as user_name,
    YEAR(mr.reading_date) as year,
    COUNT(*) as readings_count,
    SUM(mr.consumption) as total_consumption,
    SUM(mr.cost) as total_cost,
    AVG(mr.consumption) as avg_monthly_consumption,
    MIN(mr.consumption) as min_monthly_consumption,
    MAX(mr.consumption) as max_monthly_consumption
FROM meter_readings mr
JOIN users u ON mr.user_id = u.id
WHERE mr.consumption IS NOT NULL
GROUP BY mr.user_id, YEAR(mr.reading_date)
ORDER BY mr.user_id, year DESC;