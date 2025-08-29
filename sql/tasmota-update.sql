-- ============================================
-- Tasmota Integration - Datenbank Erweiterung
-- ============================================

-- Devices Tabelle um Tasmota-Felder erweitern
ALTER TABLE devices ADD COLUMN IF NOT EXISTS tasmota_ip VARCHAR(15) NULL COMMENT 'IP-Adresse der Tasmota-Steckdose';
ALTER TABLE devices ADD COLUMN IF NOT EXISTS tasmota_name VARCHAR(50) NULL COMMENT 'Tasmota Gerätename';
ALTER TABLE devices ADD COLUMN IF NOT EXISTS tasmota_enabled BOOLEAN DEFAULT FALSE COMMENT 'Automatische Datenerfassung aktiv';
ALTER TABLE devices ADD COLUMN IF NOT EXISTS tasmota_interval INT DEFAULT 300 COMMENT 'Abfrage-Intervall in Sekunden';
ALTER TABLE devices ADD COLUMN IF NOT EXISTS last_tasmota_reading TIMESTAMP NULL COMMENT 'Letzte automatische Ablesung';

-- Neue Tabelle für Tasmota-Rohdaten (optional, für Debugging)
CREATE TABLE IF NOT EXISTS tasmota_readings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id INT NOT NULL,
    user_id INT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    voltage DECIMAL(6,2) NULL COMMENT 'Spannung in V',
    current DECIMAL(8,3) NULL COMMENT 'Stromstärke in A', 
    power DECIMAL(8,2) NULL COMMENT 'Leistung in W',
    apparent_power DECIMAL(8,2) NULL COMMENT 'Scheinleistung in VA',
    reactive_power DECIMAL(8,2) NULL COMMENT 'Blindleistung in VAR',
    power_factor DECIMAL(4,3) NULL COMMENT 'Leistungsfaktor',
    energy_today DECIMAL(10,3) NULL COMMENT 'Energie heute in kWh',
    energy_yesterday DECIMAL(10,3) NULL COMMENT 'Energie gestern in kWh', 
    energy_total DECIMAL(12,3) NULL COMMENT 'Gesamtenergie in kWh',
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_device_timestamp (device_id, timestamp),
    INDEX idx_user_timestamp (user_id, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index für bessere Performance
CREATE INDEX IF NOT EXISTS idx_devices_tasmota ON devices(tasmota_enabled, tasmota_ip);

-- Demo Tasmota-Gerät hinzufügen (falls noch keine Geräte vorhanden)
INSERT IGNORE INTO devices (user_id, name, category, wattage, tasmota_ip, tasmota_name, tasmota_enabled, is_active) 
SELECT 1, 'Tasmota Testgerät', 'Smart Home', 50, '192.168.1.100', 'tasmota-test', TRUE, TRUE
WHERE NOT EXISTS (SELECT 1 FROM devices WHERE tasmota_enabled = TRUE);

SELECT 'Tasmota Integration erfolgreich installiert!' as status;