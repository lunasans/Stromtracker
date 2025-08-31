-- ============================================
-- TELEGRAM BENACHRICHTIGUNGEN
-- Erweitert das Benachrichtigungssystem um Telegram
-- ============================================

-- notification_settings Tabelle um Telegram erweitern
ALTER TABLE notification_settings 
ADD COLUMN telegram_enabled BOOLEAN DEFAULT FALSE COMMENT 'Telegram Benachrichtigungen aktiviert',
ADD COLUMN telegram_chat_id VARCHAR(50) NULL COMMENT 'Telegram Chat-ID des Benutzers',
ADD COLUMN telegram_verified BOOLEAN DEFAULT FALSE COMMENT 'Telegram Chat-ID verifiziert';

-- Index für schnelle Chat-ID Suche
CREATE INDEX idx_telegram_chat_id ON notification_settings(telegram_chat_id);

-- Telegram Bot Konfiguration (System-weite Einstellungen)
CREATE TABLE IF NOT EXISTS telegram_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_token VARCHAR(100) NOT NULL COMMENT 'Telegram Bot Token',
    bot_username VARCHAR(50) NULL COMMENT 'Bot Username für Anzeige',
    webhook_url VARCHAR(255) NULL COMMENT 'Webhook URL (optional)',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Telegram Nachrichten-Log
CREATE TABLE IF NOT EXISTS telegram_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    chat_id VARCHAR(50) NOT NULL,
    message_type ENUM('notification', 'verification', 'command', 'error') DEFAULT 'notification',
    message_text TEXT NOT NULL,
    telegram_message_id INT NULL COMMENT 'Telegram Message ID',
    status ENUM('sent', 'failed', 'delivered') DEFAULT 'sent',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_chat_id (chat_id),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- DEMO-KONFIGURATION
-- ============================================

-- Beispiel Bot-Konfiguration (Token muss angepasst werden)
INSERT IGNORE INTO telegram_config (bot_token, bot_username, is_active) VALUES 
('YOUR_BOT_TOKEN_HERE', 'stromtracker_bot', FALSE);

-- ============================================
-- ERFOLGSMELDUNG
-- ============================================

SELECT 'Telegram-Benachrichtigungen erfolgreich installiert!' as status;
SELECT 'Bot-Token in telegram_config Tabelle konfigurieren!' as next_step;
