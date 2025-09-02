-- SQL Script für Telegram-Integration
-- Erstellt/aktualisiert die erforderlichen Tabellen für das Telegram-System

-- ==============================================================================
-- 1. NOTIFICATION_SETTINGS Tabelle erweitern/erstellen
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  
  -- E-Mail Benachrichtigungen
  `email_notifications` tinyint(1) DEFAULT 1,
  `reading_reminder_enabled` tinyint(1) DEFAULT 1,
  `reading_reminder_days` int(11) DEFAULT 5,
  `reading_reminder_sent` tinyint(1) DEFAULT 0,
  `last_reminder_date` date DEFAULT NULL,
  
  -- Verbrauchsalarme
  `high_usage_alert` tinyint(1) DEFAULT 0,
  `high_usage_threshold` decimal(8,2) DEFAULT 200.00,
  `cost_alert_enabled` tinyint(1) DEFAULT 0,
  `cost_alert_threshold` decimal(8,2) DEFAULT 100.00,
  
  -- Telegram-Einstellungen (benutzerdefinierter Bot)
  `telegram_enabled` tinyint(1) DEFAULT 0,
  `telegram_bot_token` varchar(255) DEFAULT NULL,
  `telegram_bot_username` varchar(100) DEFAULT NULL,
  `telegram_chat_id` varchar(50) DEFAULT NULL,
  `telegram_verified` tinyint(1) DEFAULT 0,
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 2. TELEGRAM_LOG Tabelle für Message-Logging
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `telegram_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `chat_id` varchar(50) DEFAULT NULL,
  `bot_token_used` varchar(50) DEFAULT NULL COMMENT 'Erste 20 Zeichen des verwendeten Bot-Tokens',
  `message_type` enum('notification','verification','test','reminder') DEFAULT 'notification',
  `message_text` text,
  `telegram_message_id` varchar(50) DEFAULT NULL,
  `status` enum('pending','sent','failed','used') DEFAULT 'pending',
  `error_message` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  KEY `status` (`status`),
  KEY `message_type` (`message_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 3. NOTIFICATION_LOG Tabelle für E-Mail-Logging  
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `notification_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 4. TELEGRAM_SYSTEM Tabelle für System-Einstellungen (optional)
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `telegram_system` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard-System-Einstellungen einfügen
INSERT INTO `telegram_system` (`setting_key`, `setting_value`, `description`) VALUES 
('system_enabled', '1', 'Telegram-System global aktiviert/deaktiviert'),
('rate_limit_per_minute', '20', 'Maximale Nachrichten pro Benutzer pro Minute')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ==============================================================================
-- 5. ALTER STATEMENTS für bestehende Tabellen (MySQL-kompatibel)
-- ==============================================================================

-- Procedure für sichere Spalten-Ergänzung
DELIMITER //

CREATE PROCEDURE AddColumnIfNotExists(
    IN table_name VARCHAR(128), 
    IN column_name VARCHAR(128), 
    IN column_definition TEXT
)
BEGIN
    DECLARE column_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO column_count
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = table_name
    AND COLUMN_NAME = column_name;
    
    IF column_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', table_name, '` ADD COLUMN `', column_name, '` ', column_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

DELIMITER ;

-- Telegram-Spalten zu notification_settings hinzufügen
CALL AddColumnIfNotExists('notification_settings', 'telegram_enabled', 'tinyint(1) DEFAULT 0');
CALL AddColumnIfNotExists('notification_settings', 'telegram_bot_token', 'varchar(255) DEFAULT NULL');
CALL AddColumnIfNotExists('notification_settings', 'telegram_bot_username', 'varchar(100) DEFAULT NULL');
CALL AddColumnIfNotExists('notification_settings', 'telegram_chat_id', 'varchar(50) DEFAULT NULL');
CALL AddColumnIfNotExists('notification_settings', 'telegram_verified', 'tinyint(1) DEFAULT 0');

-- Weitere Spalten hinzufügen
CALL AddColumnIfNotExists('notification_settings', 'reading_reminder_sent', 'tinyint(1) DEFAULT 0');
CALL AddColumnIfNotExists('notification_settings', 'last_reminder_date', 'date DEFAULT NULL');
CALL AddColumnIfNotExists('notification_settings', 'created_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL AddColumnIfNotExists('notification_settings', 'updated_at', 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Procedure wieder löschen
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;

-- ==============================================================================
-- 6. INDEX-OPTIMIERUNGEN
-- ==============================================================================

-- Weitere Indizes für bessere Performance
ALTER TABLE `telegram_log` ADD INDEX IF NOT EXISTS `user_created_idx` (`user_id`, `created_at`);
ALTER TABLE `notification_log` ADD INDEX IF NOT EXISTS `user_created_idx` (`user_id`, `created_at`);

-- ==============================================================================
-- FERTIG! 
-- ==============================================================================

-- Dieses Script kann sicher mehrfach ausgeführt werden.
-- Es erstellt nur fehlende Tabellen/Spalten und überschreibt keine bestehenden Daten.
