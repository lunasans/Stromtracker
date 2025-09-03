-- NOTFALL-SQL: Manuelle Tabellenerstellung für Telegram
-- Führen Sie diese Befehle einzeln aus, falls das Setup-Script nicht funktioniert

USE stromtracker;

-- 1. notification_settings Tabelle (Basis-Version)
CREATE TABLE IF NOT EXISTS notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_notifications TINYINT(1) DEFAULT 1,
    reading_reminder_enabled TINYINT(1) DEFAULT 1,
    reading_reminder_days INT DEFAULT 5,
    high_usage_alert TINYINT(1) DEFAULT 0,
    high_usage_threshold DECIMAL(10,2) DEFAULT 200.00,
    cost_alert_enabled TINYINT(1) DEFAULT 0,
    cost_alert_threshold DECIMAL(10,2) DEFAULT 100.00,
    telegram_enabled TINYINT(1) DEFAULT 0,
    telegram_bot_token VARCHAR(50) NULL,
    telegram_chat_id VARCHAR(20) NULL,
    telegram_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_settings (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 2. telegram_log Tabelle (für Verifizierungscodes)
CREATE TABLE IF NOT EXISTS telegram_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chat_id VARCHAR(20) NOT NULL,
    message_type VARCHAR(20) DEFAULT 'notification',
    message_text TEXT NULL,
    status VARCHAR(10) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Standard-Einstellungen für alle Benutzer erstellen
INSERT IGNORE INTO notification_settings (
    user_id, email_notifications, reading_reminder_enabled, 
    telegram_enabled, telegram_verified
)
SELECT 
    id, 1, 1, 0, 0
FROM users;

-- 4. Prüfung der Ergebnisse
SELECT 'Tabellen erfolgreich erstellt!' as status;

SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN telegram_enabled = 1 THEN 1 END) as telegram_users
FROM notification_settings;
