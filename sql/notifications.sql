-- ============================================
-- BENACHRICHTIGUNGEN & ERINNERUNGEN
-- Erweitert Stromtracker um Smart Notifications
-- ============================================

-- Benutzer-Benachrichtigungseinstellungen
CREATE TABLE IF NOT EXISTS notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_notifications BOOLEAN DEFAULT TRUE,
    reading_reminder_enabled BOOLEAN DEFAULT TRUE,
    reading_reminder_days TINYINT DEFAULT 5 COMMENT 'Tage vor Monatsende',
    reading_reminder_sent BOOLEAN DEFAULT FALSE,
    last_reminder_date DATE NULL,
    high_usage_alert BOOLEAN DEFAULT FALSE,
    high_usage_threshold DECIMAL(10,2) DEFAULT 200.00 COMMENT 'kWh Grenzwert',
    cost_alert_enabled BOOLEAN DEFAULT FALSE,
    cost_alert_threshold DECIMAL(10,2) DEFAULT 100.00 COMMENT 'Euro Grenzwert',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id)
);

-- Benachrichtigungs-Log (für Tracking und Debugging)
CREATE TABLE IF NOT EXISTS notification_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type ENUM(
        'reading_reminder',
        'high_usage_alert', 
        'cost_alert',
        'tariff_reminder',
        'system_notification'
    ) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type_status (user_id, notification_type, status),
    INDEX idx_created_at (created_at)
);

-- Standard-Einstellungen für bestehende Benutzer erstellen (KORRIGIERT)
INSERT IGNORE INTO notification_settings (user_id, email_notifications, reading_reminder_enabled)
SELECT id, TRUE, TRUE FROM users;

-- ============================================
-- ERINNERUNGS-QUEUE (statt komplexer Views)
-- ============================================

-- Einfache Tabelle für Erinnerungen (MySQL kompatibel)
CREATE TABLE IF NOT EXISTS reminder_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_name VARCHAR(255),
    user_email VARCHAR(255),
    last_reading_date DATE,
    days_since_last INT,
    suggested_date DATE,
    reminder_due BOOLEAN DEFAULT FALSE,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_queue (user_id),
    INDEX idx_reminder_due (reminder_due, processed)
);

-- ============================================
-- DEMO-DATEN & STANDARD-EINSTELLUNGEN  
-- ============================================

-- Admin-User Benachrichtigungen aktivieren (falls User ID 1 existiert)
INSERT IGNORE INTO notification_settings (user_id, email_notifications, reading_reminder_enabled, reading_reminder_days, high_usage_alert, high_usage_threshold)
SELECT 1, TRUE, TRUE, 3, TRUE, 180.00 FROM users WHERE id = 1 LIMIT 1;

-- Test-Benachrichtigung erstellen
INSERT IGNORE INTO notification_log (user_id, notification_type, subject, message, status) 
SELECT 1, 'system_notification', 
       'Benachrichtigungssystem aktiviert', 
       'Das Erinnerungssystem für Zählerstand-Ablesungen ist jetzt aktiv. Sie erhalten ab sofort Erinnerungen vor Monatsende.',
       'sent'
FROM users WHERE id = 1 LIMIT 1;

-- ============================================
-- ERFOLGS-INFO  
-- ============================================

SELECT 'Benachrichtigungssystem erfolgreich installiert!' as status;

SELECT 
    COUNT(*) as total_users_configured,
    SUM(CASE WHEN reading_reminder_enabled THEN 1 ELSE 0 END) as users_with_reminders,
    SUM(CASE WHEN high_usage_alert THEN 1 ELSE 0 END) as users_with_usage_alerts
FROM notification_settings;

SELECT 'Erinnerungssystem bereit - Konfiguration im Profil verfügbar!' as final_status;
