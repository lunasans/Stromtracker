-- sql/add-smtp-settings.sql
-- SMTP-Konfiguration zu notification_settings hinzufügen

ALTER TABLE `notification_settings`
ADD COLUMN `smtp_enabled` TINYINT(1) DEFAULT 0 AFTER `email_notifications`,
ADD COLUMN `smtp_host` VARCHAR(255) DEFAULT NULL AFTER `smtp_enabled`,
ADD COLUMN `smtp_port` INT DEFAULT 587 AFTER `smtp_host`,
ADD COLUMN `smtp_encryption` VARCHAR(10) DEFAULT 'tls' AFTER `smtp_port`,
ADD COLUMN `smtp_username` VARCHAR(255) DEFAULT NULL AFTER `smtp_encryption`,
ADD COLUMN `smtp_password` VARCHAR(255) DEFAULT NULL AFTER `smtp_username`,
ADD COLUMN `smtp_from_email` VARCHAR(255) DEFAULT NULL AFTER `smtp_password`,
ADD COLUMN `smtp_from_name` VARCHAR(255) DEFAULT 'Stromtracker' AFTER `smtp_from_email`;

-- Index für Performance
CREATE INDEX idx_smtp_enabled ON notification_settings(smtp_enabled);

-- Kommentar hinzufügen
ALTER TABLE `notification_settings` 
COMMENT = 'Benachrichtigungseinstellungen inkl. SMTP-Konfiguration für E-Mail';