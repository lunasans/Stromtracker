-- EINFACHE VERSION: SQL für Telegram-Integration (ohne Stored Procedures)
-- Führt die ALTER TABLE Statements einzeln aus - Fehler können ignoriert werden

-- ==============================================================================
-- 1. NOTIFICATION_SETTINGS Tabelle erweitern (einfache Methode)  
-- ==============================================================================

-- Diese Statements einzeln ausführen - Fehler bei bereits vorhandenen Spalten ignorieren

ALTER TABLE `notification_settings` ADD COLUMN `telegram_enabled` tinyint(1) DEFAULT 0;
ALTER TABLE `notification_settings` ADD COLUMN `telegram_bot_token` varchar(255) DEFAULT NULL;
ALTER TABLE `notification_settings` ADD COLUMN `telegram_bot_username` varchar(100) DEFAULT NULL; 
ALTER TABLE `notification_settings` ADD COLUMN `telegram_chat_id` varchar(50) DEFAULT NULL;
ALTER TABLE `notification_settings` ADD COLUMN `telegram_verified` tinyint(1) DEFAULT 0;

-- Weitere Spalten
ALTER TABLE `notification_settings` ADD COLUMN `reading_reminder_sent` tinyint(1) DEFAULT 0;
ALTER TABLE `notification_settings` ADD COLUMN `last_reminder_date` date DEFAULT NULL;
ALTER TABLE `notification_settings` ADD COLUMN `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `notification_settings` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ==============================================================================
-- 2. TELEGRAM_LOG Tabelle erstellen
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `telegram_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `chat_id` varchar(50) DEFAULT NULL,
  `bot_token_used` varchar(50) DEFAULT NULL COMMENT 'Erste 20 Zeichen des Bot-Tokens',
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
-- 3. NOTIFICATION_LOG Tabelle erstellen  
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
-- ANLEITUNG:
-- ==============================================================================
-- 1. Führen Sie die ALTER TABLE Statements einzeln aus
-- 2. Ignorieren Sie Fehlermeldungen wie "Duplicate column name"
-- 3. Die CREATE TABLE Statements sind sicher (IF NOT EXISTS)
-- 4. Testen Sie dann das Bot-Token-System
