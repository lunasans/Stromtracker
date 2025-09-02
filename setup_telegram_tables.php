<?php
// setup_telegram_tables.php
// PHP-basierte Erstellung der Telegram-Tabellen

require_once 'config/database.php';

echo "<h2>🤖 Telegram-Tabellen Setup</h2>\n";
echo "<pre>\n";

try {
    // 1. notification_settings Tabelle erweitern
    echo "1. Prüfe notification_settings Tabelle...\n";
    
    // Prüfen ob Tabelle existiert
    $tableExists = Database::fetchOne("SHOW TABLES LIKE 'notification_settings'");
    
    if (!$tableExists) {
        echo "   → Tabelle existiert nicht, erstelle neue Tabelle...\n";
        
        Database::execute("
            CREATE TABLE `notification_settings` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `email_notifications` tinyint(1) DEFAULT 1,
              `reading_reminder_enabled` tinyint(1) DEFAULT 1,
              `reading_reminder_days` int(11) DEFAULT 5,
              `reading_reminder_sent` tinyint(1) DEFAULT 0,
              `last_reminder_date` date DEFAULT NULL,
              `high_usage_alert` tinyint(1) DEFAULT 0,
              `high_usage_threshold` decimal(8,2) DEFAULT 200.00,
              `cost_alert_enabled` tinyint(1) DEFAULT 0,
              `cost_alert_threshold` decimal(8,2) DEFAULT 100.00,
              `telegram_enabled` tinyint(1) DEFAULT 0,
              `telegram_bot_token` varchar(255) DEFAULT NULL,
              `telegram_bot_username` varchar(100) DEFAULT NULL,
              `telegram_chat_id` varchar(50) DEFAULT NULL,
              `telegram_verified` tinyint(1) DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        echo "   ✅ Tabelle erstellt!\n";
    } else {
        echo "   → Tabelle existiert, prüfe Spalten...\n";
        
        // Telegram-Spalten hinzufügen
        $telegramColumns = [
            'telegram_enabled' => 'tinyint(1) DEFAULT 0',
            'telegram_bot_token' => 'varchar(255) DEFAULT NULL',
            'telegram_bot_username' => 'varchar(100) DEFAULT NULL', 
            'telegram_chat_id' => 'varchar(50) DEFAULT NULL',
            'telegram_verified' => 'tinyint(1) DEFAULT 0'
        ];
        
        foreach ($telegramColumns as $column => $definition) {
            $columnExists = Database::fetchOne("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'notification_settings' 
                AND COLUMN_NAME = ?
            ", [$column]);
            
            if (!$columnExists) {
                try {
                    Database::execute("ALTER TABLE notification_settings ADD COLUMN `$column` $definition");
                    echo "   ✅ Spalte '$column' hinzugefügt\n";
                } catch (Exception $e) {
                    echo "   ⚠️ Spalte '$column' konnte nicht hinzugefügt werden: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Spalte '$column' existiert bereits\n";
            }
        }
    }
    
    // 2. telegram_log Tabelle
    echo "\n2. Erstelle telegram_log Tabelle...\n";
    
    Database::execute("
        CREATE TABLE IF NOT EXISTS `telegram_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `chat_id` varchar(50) DEFAULT NULL,
          `bot_token_used` varchar(50) DEFAULT NULL,
          `message_type` enum('notification','verification','test','reminder') DEFAULT 'notification',
          `message_text` text,
          `telegram_message_id` varchar(50) DEFAULT NULL,
          `status` enum('pending','sent','failed','used') DEFAULT 'pending',
          `error_message` text,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    echo "   ✅ telegram_log Tabelle bereit\n";
    
    // 3. notification_log Tabelle  
    echo "\n3. Erstelle notification_log Tabelle...\n";
    
    Database::execute("
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
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    echo "   ✅ notification_log Tabelle bereit\n";
    
    // 4. Status anzeigen
    echo "\n4. 📊 Tabellen-Status:\n";
    
    $tables = ['notification_settings', 'telegram_log', 'notification_log'];
    foreach ($tables as $table) {
        $exists = Database::fetchOne("SHOW TABLES LIKE '$table'");
        echo "   " . ($exists ? "✅" : "❌") . " $table\n";
    }
    
    echo "\n🎉 Setup abgeschlossen!\n";
    echo "\nSie können jetzt Bot-Token im Profil eintragen.\n";
    echo "Zum Testen verwenden Sie 'demo' als Token.\n";
    
} catch (Exception $e) {
    echo "\n❌ FEHLER: " . $e->getMessage() . "\n";
    echo "\nDebug-Info:\n";
    echo "- Datenbankverbindung: " . (Database::getConnection() ? "OK" : "FEHLER") . "\n";
    echo "- User ID: " . (Auth::isLoggedIn() ? Auth::getUserId() : "Nicht eingeloggt") . "\n";
}

echo "</pre>\n";

// Zurück zum Profil
echo '<p><a href="profil.php" class="btn btn-primary">← Zurück zum Profil</a></p>';
?>
