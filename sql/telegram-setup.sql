-- ============================================
-- TELEGRAM BENACHRICHTIGUNGEN - Database Setup
-- Fügt alle notwendigen Tabellen für Telegram-Funktionalität hinzu
-- ============================================

USE stromtracker;

-- =============================================================================
-- 1. NOTIFICATION SETTINGS TABELLE
-- =============================================================================

CREATE TABLE IF NOT EXISTS notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- E-Mail Einstellungen
    email_notifications BOOLEAN DEFAULT TRUE COMMENT 'E-Mail-Benachrichtigungen aktiviert',
    
    -- Erinnerungen
    reading_reminder_enabled BOOLEAN DEFAULT TRUE COMMENT 'Zählerstand-Erinnerungen aktiv',
    reading_reminder_days INT DEFAULT 5 COMMENT 'Erinnerung X Tage vor Monatsende',
    reading_reminder_sent BOOLEAN DEFAULT FALSE COMMENT 'Erinnerung für aktuellen Monat gesendet',
    last_reminder_date DATE NULL COMMENT 'Datum der letzten Erinnerung',
    
    -- Verbrauchs-Alarme
    high_usage_alert BOOLEAN DEFAULT FALSE COMMENT 'Warnung bei hohem Verbrauch',
    high_usage_threshold DECIMAL(10,2) DEFAULT 200.00 COMMENT 'Schwellwert in kWh/Monat',
    cost_alert_enabled BOOLEAN DEFAULT FALSE COMMENT 'Kostenalarm aktiv',
    cost_alert_threshold DECIMAL(10,2) DEFAULT 100.00 COMMENT 'Kostenschwelle in Euro/Monat',
    
    -- Telegram Einstellungen
    telegram_enabled BOOLEAN DEFAULT FALSE COMMENT 'Telegram-Benachrichtigungen aktiv',
    telegram_bot_token VARCHAR(50) NULL COMMENT 'Persönlicher Bot-Token',
    telegram_bot_username VARCHAR(50) NULL COMMENT 'Bot-Username (@xyz_bot)',
    telegram_chat_id VARCHAR(20) NULL COMMENT 'Chat-ID des Benutzers',
    telegram_verified BOOLEAN DEFAULT FALSE COMMENT 'Chat-ID verifiziert',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id),
    INDEX idx_reminder_users (reading_reminder_enabled, telegram_enabled),
    INDEX idx_telegram_users (telegram_enabled, telegram_verified)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. TELEGRAM LOG TABELLE
-- =============================================================================

CREATE TABLE IF NOT EXISTS telegram_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    chat_id VARCHAR(20) NOT NULL COMMENT 'Telegram Chat-ID',
    bot_token_used VARCHAR(20) NULL COMMENT 'Verwendeter Bot-Token (gekürzt)',
    
    message_type ENUM('notification', 'reminder', 'verification', 'test', 'alert') DEFAULT 'notification',
    message_text TEXT NULL COMMENT 'Nachrichtentext (gekürzt)',
    telegram_message_id INT NULL COMMENT 'Telegram Message-ID',
    
    status ENUM('pending', 'sent', 'failed', 'used') DEFAULT 'pending',
    error_message VARCHAR(500) NULL COMMENT 'Fehlermeldung bei failed',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_user_type (user_id, message_type),
    INDEX idx_verification_codes (user_id, message_type, status) -- Für Verifizierung
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. NOTIFICATION LOG TABELLE (E-Mail Benachrichtigungen)
-- =============================================================================

CREATE TABLE IF NOT EXISTS notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    notification_type ENUM('reading_reminder', 'high_usage_alert', 'cost_alert', 'system_notification') DEFAULT 'reading_reminder',
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message VARCHAR(500) NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_user_type (user_id, notification_type),
    INDEX idx_sent_date (sent_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. TELEGRAM SYSTEM TABELLE (Optional für Admin-Einstellungen)
-- =============================================================================

CREATE TABLE IF NOT EXISTS telegram_system (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NULL,
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_setting (setting_key)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. STANDARD-EINSTELLUNGEN FÜR BESTEHENDE BENUTZER
-- =============================================================================

-- Notification Settings für alle bestehenden Benutzer erstellen
INSERT IGNORE INTO notification_settings (
    user_id, 
    email_notifications, 
    reading_reminder_enabled, 
    reading_reminder_days,
    high_usage_alert,
    high_usage_threshold,
    cost_alert_enabled,
    cost_alert_threshold,
    telegram_enabled,
    telegram_verified
)
SELECT 
    id,
    TRUE,  -- E-Mail standardmäßig aktiv
    TRUE,  -- Erinnerungen aktiv
    5,     -- 5 Tage vor Monatsende
    FALSE, -- Verbrauchsalarm aus
    200.00,
    FALSE, -- Kostenalarm aus  
    100.00,
    FALSE, -- Telegram aus
    FALSE  -- Nicht verifiziert
FROM users
WHERE NOT EXISTS (
    SELECT 1 FROM notification_settings WHERE notification_settings.user_id = users.id
);

-- =============================================================================
-- 6. SYSTEM-EINSTELLUNGEN
-- =============================================================================

INSERT IGNORE INTO telegram_system (setting_key, setting_value, description) VALUES
('system_enabled', '1', 'Telegram-System global aktiviert'),
('rate_limit_per_minute', '20', 'Max. Nachrichten pro Benutzer/Minute'),
('default_reminder_days', '5', 'Standard-Erinnerungstage'),
('max_verification_attempts', '3', 'Max. Verifizierungsversuche pro Stunde');

-- =============================================================================
-- 7. ERGEBNIS & VALIDIERUNG
-- =============================================================================

-- Prüfen ob alle Tabellen erstellt wurden
SELECT 
    'notification_settings' as tabelle,
    COUNT(*) as datensaetze,
    'OK' as status
FROM notification_settings

UNION ALL

SELECT 
    'telegram_log' as tabelle,
    COUNT(*) as datensaetze,
    'OK' as status  
FROM telegram_log

UNION ALL

SELECT 
    'notification_log' as tabelle,
    COUNT(*) as datensaetze,
    'OK' as status
FROM notification_log

UNION ALL

SELECT 
    'telegram_system' as tabelle,
    COUNT(*) as datensaetze,
    'OK' as status
FROM telegram_system;

-- Benutzer-Statistik
SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN telegram_enabled = 1 THEN 1 END) as telegram_enabled_users,
    COUNT(CASE WHEN telegram_verified = 1 THEN 1 END) as telegram_verified_users,
    COUNT(CASE WHEN telegram_bot_token IS NOT NULL THEN 1 END) as users_with_bot_token
FROM notification_settings;

SELECT '🎉 TELEGRAM SETUP ERFOLGREICH ABGESCHLOSSEN!' as final_status;
SELECT 'Alle Benutzer können jetzt Telegram-Benachrichtigungen konfigurieren.' as info;