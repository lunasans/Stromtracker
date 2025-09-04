-- ============================================
-- TELEGRAM LOG TABELLE
-- Für das benutzerbasierte Bot-System
-- ============================================

-- Telegram Nachrichten-Log (benutzerbezogen)
CREATE TABLE IF NOT EXISTS telegram_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chat_id VARCHAR(50) NOT NULL,
    message_type ENUM('notification', 'verification', 'command', 'test') DEFAULT 'notification',
    message_text TEXT NOT NULL,
    telegram_message_id INT NULL COMMENT 'Telegram Message ID',
    status ENUM('sent', 'failed', 'delivered') DEFAULT 'sent',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_chat_id (chat_id),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
) COMMENT='Telegram Bot Nachrichten-Log für Benutzer-Bots';

-- Optional: Webhook-Statistiken (falls gewünscht)
CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(100) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    
    INDEX idx_endpoint (endpoint),
    INDEX idx_timestamp (timestamp),
    INDEX idx_success (success)
) COMMENT='Webhook-Aufrufe Statistiken';

-- Erfolgsmeldung
SELECT 'Telegram Log-Tabellen erfolgreich erstellt!' as status;
SELECT 'Bot-System kann jetzt Nachrichten loggen!' as info;
