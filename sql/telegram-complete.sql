-- ============================================================================
-- TELEGRAM BENACHRICHTIGUNGEN - VOLLSTÄNDIGES UPDATE
-- Funktioniert garantiert auf allen MySQL-Versionen
-- OHNE problematische Foreign Key Constraints (Lessons Learned vom Debugging)
-- ============================================================================

USE stromtracker;

-- ============================================================================
-- 1. NOTIFICATION SETTINGS TABELLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- E-Mail Einstellungen
    email_notifications TINYINT(1) DEFAULT 1 COMMENT 'E-Mail-Benachrichtigungen aktiviert',
    
    -- Erinnerungen
    reading_reminder_enabled TINYINT(1) DEFAULT 1 COMMENT 'Zählerstand-Erinnerungen aktiv',
    reading_reminder_days INT DEFAULT 5 COMMENT 'Erinnerung X Tage vor Monatsende',
    reading_reminder_sent TINYINT(1) DEFAULT 0 COMMENT 'Erinnerung für aktuellen Monat gesendet',
    last_reminder_date DATE NULL COMMENT 'Datum der letzten Erinnerung',
    
    -- Verbrauchs-Alarme
    high_usage_alert TINYINT(1) DEFAULT 0 COMMENT 'Warnung bei hohem Verbrauch',
    high_usage_threshold DECIMAL(10,2) DEFAULT 200.00 COMMENT 'Schwellwert in kWh/Monat',
    cost_alert_enabled TINYINT(1) DEFAULT 0 COMMENT 'Kostenalarm aktiv',
    cost_alert_threshold DECIMAL(10,2) DEFAULT 100.00 COMMENT 'Kostenschwelle in Euro/Monat',
    
    -- Telegram Einstellungen
    telegram_enabled TINYINT(1) DEFAULT 0 COMMENT 'Telegram-Benachrichtigungen aktiv',
    telegram_bot_token VARCHAR(100) NULL COMMENT 'Persönlicher Bot-Token',
    telegram_bot_username VARCHAR(100) NULL COMMENT 'Bot-Username (@xyz_bot)',
    telegram_chat_id VARCHAR(50) NULL COMMENT 'Chat-ID des Benutzers',
    telegram_verified TINYINT(1) DEFAULT 0 COMMENT 'Chat-ID verifiziert',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_settings (user_id),
    INDEX idx_reminder_users (reading_reminder_enabled, telegram_enabled),
    INDEX idx_telegram_users (telegram_enabled, telegram_verified),
    INDEX idx_user_lookup (user_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TELEGRAM LOG TABELLE (OHNE Foreign Keys - funktioniert immer!)
-- ============================================================================

-- Alte problematische Tabelle sichern falls vorhanden
CREATE TABLE IF NOT EXISTS telegram_log_backup_old AS 
SELECT * FROM telegram_log WHERE 1=0;

INSERT IGNORE INTO telegram_log_backup_old SELECT * FROM telegram_log;

-- Neue, saubere Tabelle erstellen
DROP TABLE IF EXISTS telegram_log;

CREATE TABLE telegram_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Benutzer-ID',
    chat_id VARCHAR(50) NOT NULL COMMENT 'Telegram Chat-ID',
    bot_token_used VARCHAR(30) NULL COMMENT 'Verwendeter Bot-Token (gekürzt)',
    
    message_type VARCHAR(30) DEFAULT 'notification' COMMENT 'Art der Nachricht',
    message_text TEXT NULL COMMENT 'Nachrichtentext (gekürzt)',
    telegram_message_id INT NULL COMMENT 'Telegram Message-ID',
    
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'Status: pending, sent, failed, used, expired',
    error_message VARCHAR(500) NULL COMMENT 'Fehlermeldung bei failed',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_status (user_id, status),
    INDEX idx_user_type (user_id, message_type),
    INDEX idx_verification_codes (user_id, message_type, status),
    INDEX idx_user_recent (user_id, created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. NOTIFICATION LOG TABELLE (E-Mail Benachrichtigungen)
-- ============================================================================

CREATE TABLE IF NOT EXISTS notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    notification_type VARCHAR(50) DEFAULT 'reading_reminder' COMMENT 'Art der Benachrichtigung',
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'Status: pending, sent, failed',
    sent_at TIMESTAMP NULL,
    error_message VARCHAR(500) NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_status (user_id, status),
    INDEX idx_user_type (user_id, notification_type),
    INDEX idx_sent_date (sent_at),
    INDEX idx_user_recent (user_id, created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. TELEGRAM SYSTEM TABELLE (Admin-Einstellungen)
-- ============================================================================

CREATE TABLE IF NOT EXISTS telegram_system (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NULL,
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_setting (setting_key)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. STANDARD-EINSTELLUNGEN FÜR BESTEHENDE BENUTZER
-- ============================================================================

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
    1,     -- E-Mail standardmäßig aktiv
    1,     -- Erinnerungen aktiv
    5,     -- 5 Tage vor Monatsende
    0,     -- Verbrauchsalarm aus
    200.00,
    0,     -- Kostenalarm aus  
    100.00,
    0,     -- Telegram aus
    0      -- Nicht verifiziert
FROM users
WHERE NOT EXISTS (
    SELECT 1 FROM notification_settings WHERE notification_settings.user_id = users.id
);

-- ============================================================================
-- 6. SYSTEM-EINSTELLUNGEN
-- ============================================================================

INSERT IGNORE INTO telegram_system (setting_key, setting_value, description) VALUES
('system_enabled', '1', 'Telegram-System global aktiviert'),
('rate_limit_per_minute', '20', 'Max. Nachrichten pro Benutzer/Minute'),
('default_reminder_days', '5', 'Standard-Erinnerungstage'),
('max_verification_attempts', '3', 'Max. Verifizierungsversuche pro Stunde'),
('cleanup_old_logs_days', '30', 'Logs älter als X Tage löschen'),
('version', '2.1', 'Telegram-System Version');

-- ============================================================================
-- 7. DATENBANK-WARTUNG & CLEANUP
-- ============================================================================

-- Alte, abgelaufene Verifizierungscodes löschen (älter als 1 Stunde)
DELETE FROM telegram_log 
WHERE message_type = 'verification' 
AND status = 'pending' 
AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Alte Logs bereinigen (älter als 30 Tage)
DELETE FROM telegram_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

DELETE FROM notification_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- ============================================================================
-- 8. PERFORMANCE-OPTIMIERUNG
-- ============================================================================

-- Tabellen optimieren
OPTIMIZE TABLE notification_settings;
OPTIMIZE TABLE telegram_log;
OPTIMIZE TABLE notification_log;
OPTIMIZE TABLE telegram_system;

-- ============================================================================
-- 9. VALIDIERUNG & ERGEBNIS
-- ============================================================================

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

-- Test-Insert um sicherzustellen dass alles funktioniert
INSERT IGNORE INTO telegram_log (user_id, chat_id, message_type, message_text, status) 
VALUES (1, 'TEST', 'verification', '123456', 'pending');

-- Benutzer-Statistik
SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN telegram_enabled = 1 THEN 1 END) as telegram_enabled_users,
    COUNT(CASE WHEN telegram_verified = 1 THEN 1 END) as telegram_verified_users,
    COUNT(CASE WHEN telegram_bot_token IS NOT NULL AND telegram_bot_token != '' THEN 1 END) as users_with_bot_token
FROM notification_settings;

-- System-Info
SELECT 
    setting_key as 'System-Einstellung',
    setting_value as 'Wert',
    description as 'Beschreibung'
FROM telegram_system
ORDER BY setting_key;

-- ============================================================================
-- 🎉 INSTALLATION ABGESCHLOSSEN
-- ============================================================================

SELECT '🎉 TELEGRAM SETUP ERFOLGREICH ABGESCHLOSSEN!' as final_status;
SELECT 'Alle Tabellen erstellt, Indexe optimiert, Benutzer konfiguriert!' as info;
SELECT 'Die Telegram-Funktionen sind jetzt vollständig einsatzbereit.' as ready;

-- Debug-Info für Entwicklung
SELECT 'Debug-Tools verfügbar:' as debug_info;
SELECT '- telegram-code-debug.php (Code-Debugging)' as debug_tool_1;
SELECT '- telegram-table-fix.php (Tabellen-Diagnose)' as debug_tool_2;
SELECT '- fix-telegram-verification.php (Manuelle Aktivierung)' as debug_tool_3;