-- ============================================================================
-- TELEGRAM UPDATE - NUR ÄNDERUNGEN
-- Für bestehende Systeme die bereits telegram-setup.sql ausgeführt haben
-- ============================================================================

USE stromtracker;

-- ============================================================================
-- REPARATUR: telegram_log Tabelle ohne Foreign Key Probleme
-- ============================================================================

-- Backup erstellen
CREATE TABLE IF NOT EXISTS telegram_log_backup AS SELECT * FROM telegram_log;

-- Neue, funktionierende Tabelle
DROP TABLE IF EXISTS telegram_log;

CREATE TABLE telegram_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chat_id VARCHAR(50) NOT NULL,
    bot_token_used VARCHAR(30) NULL,
    message_type VARCHAR(30) DEFAULT 'verification',
    message_text TEXT NULL,
    telegram_message_id INT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_verification (user_id, message_type, status)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backup-Daten zurück laden (falls vorhanden)
INSERT IGNORE INTO telegram_log 
SELECT * FROM telegram_log_backup WHERE id IS NOT NULL;

-- ============================================================================
-- FEHLENDE SPALTEN HINZUFÜGEN (falls noch nicht vorhanden)
-- ============================================================================

-- notification_settings erweitern
ALTER TABLE notification_settings 
ADD COLUMN IF NOT EXISTS telegram_bot_username VARCHAR(100) NULL COMMENT 'Bot-Username (@xyz_bot)' AFTER telegram_bot_token;

-- ============================================================================
-- SYSTEM-EINSTELLUNGEN AKTUALISIEREN
-- ============================================================================

INSERT IGNORE INTO telegram_system (setting_key, setting_value, description) VALUES
('version', '2.1', 'Telegram-System Version (Update)'),
('last_update', NOW(), 'Letztes Update ausgeführt');

-- ============================================================================
-- AUFRÄUMEN & OPTIMIEREN
-- ============================================================================

-- Alte Verifizierungscodes löschen
DELETE FROM telegram_log 
WHERE message_type = 'verification' 
AND status IN ('pending', 'expired')
AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Performance optimieren
OPTIMIZE TABLE notification_settings;
OPTIMIZE TABLE telegram_log;

-- ============================================================================
-- TEST & VALIDIERUNG
-- ============================================================================

-- Test-Insert
INSERT INTO telegram_log (user_id, chat_id, message_type, message_text, status) 
VALUES (1, 'UPDATE_TEST', 'verification', '999999', 'pending')
ON DUPLICATE KEY UPDATE message_text = '999999';

-- Ergebnis
SELECT 'TELEGRAM UPDATE ERFOLGREICH!' as status;
SELECT COUNT(*) as telegram_log_entries FROM telegram_log;
SELECT COUNT(*) as users_with_settings FROM notification_settings;