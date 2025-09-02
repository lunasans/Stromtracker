-- ============================================
-- TELEGRAM BENACHRICHTIGUNGEN (USER-BASIERT)
-- Jeder Benutzer kann seinen eigenen Bot verwenden
-- ============================================

-- notification_settings um benutzerspezifische Bot-Daten erweitern
ALTER TABLE notification_settings 
ADD COLUMN telegram_enabled BOOLEAN DEFAULT FALSE COMMENT 'Telegram Benachrichtigungen aktiviert',
ADD COLUMN telegram_bot_token VARCHAR(100) NULL COMMENT 'Persönlicher Bot-Token des Benutzers',
ADD COLUMN telegram_bot_username VARCHAR(50) NULL COMMENT 'Bot-Username für Anzeige',
ADD COLUMN telegram_chat_id VARCHAR(50) NULL COMMENT 'Chat-ID des Benutzers mit seinem Bot',
ADD COLUMN telegram_verified BOOLEAN DEFAULT FALSE COMMENT 'Bot und Chat-ID verifiziert';

-- Index für schnelle Suche
CREATE INDEX idx_telegram_bot_token ON notification_settings(telegram_bot_token);
CREATE INDEX idx_telegram_chat_id ON notification_settings(telegram_chat_id);

-- Telegram Nachrichten-Log (benutzerbezogen)
CREATE TABLE IF NOT EXISTS telegram_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chat_id VARCHAR(50) NOT NULL,
    bot_token_used VARCHAR(20) NULL COMMENT 'Erste 20 Zeichen vom verwendeten Token',
    message_type ENUM('notification', 'verification', 'command', 'test') DEFAULT 'notification',
    message_text TEXT NOT NULL,
    telegram_message_id INT NULL COMMENT 'Telegram Message ID',
    status ENUM('sent', 'failed', 'delivered') DEFAULT 'sent',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_chat_id (chat_id),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- SYSTEM-EINSTELLUNGEN (optional)
-- ============================================

-- Globale Telegram-Einstellungen (für Rate-Limiting, etc.)
CREATE TABLE IF NOT EXISTS telegram_system (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Standard-Einstellungen
INSERT IGNORE INTO telegram_system (setting_key, setting_value, description) VALUES 
('rate_limit_per_minute', '20', 'Max. Nachrichten pro Minute pro Bot'),
('max_retries', '3', 'Anzahl Wiederholungen bei fehlgeschlagenen Nachrichten'),
('verification_code_length', '6', 'Länge der Verifizierungscodes'),
('system_enabled', '1', 'Telegram-System global aktiviert');

-- ============================================
-- ERFOLGSMELDUNG
-- ============================================

SELECT 'Benutzerbezogenes Telegram-System installiert!' as status;
SELECT 'Jeder Benutzer kann jetzt seinen eigenen Bot konfigurieren!' as info;
