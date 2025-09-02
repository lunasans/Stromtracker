<?php
// fix_telegram_columns.php
// Korrigiert die Telegram-Spalten auf die richtige Größe

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

echo "<h2>🔧 Telegram Spalten-Fix</h2>\n";
echo "<pre>\n";

try {
    echo "1. 📋 Aktuelle Spalten-Definitionen...\n";
    
    $columns = Database::fetchAll("
        SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'notification_settings' 
        AND COLUMN_NAME LIKE 'telegram_%'
        ORDER BY COLUMN_NAME
    ");
    
    foreach ($columns as $col) {
        echo "   - {$col['COLUMN_NAME']}: {$col['COLUMN_TYPE']}\n";
    }
    
    echo "\n2. 🔧 Spalten-Größen korrigieren...\n";
    
    // telegram_bot_token vergrößern
    $currentTokenType = null;
    foreach ($columns as $col) {
        if ($col['COLUMN_NAME'] === 'telegram_bot_token') {
            $currentTokenType = $col['COLUMN_TYPE'];
            break;
        }
    }
    
    if ($currentTokenType === 'varchar(100)') {
        echo "   → telegram_bot_token von varchar(100) auf varchar(255) erweitern...\n";
        Database::execute("ALTER TABLE notification_settings MODIFY COLUMN telegram_bot_token VARCHAR(255) DEFAULT NULL");
        echo "   ✅ telegram_bot_token erweitert\n";
    } else {
        echo "   ✓ telegram_bot_token hat bereits die richtige Größe ($currentTokenType)\n";
    }
    
    // telegram_bot_username vergrößern
    $currentUsernameType = null;
    foreach ($columns as $col) {
        if ($col['COLUMN_NAME'] === 'telegram_bot_username') {
            $currentUsernameType = $col['COLUMN_TYPE'];
            break;
        }
    }
    
    if ($currentUsernameType === 'varchar(50)') {
        echo "   → telegram_bot_username von varchar(50) auf varchar(100) erweitern...\n";
        Database::execute("ALTER TABLE notification_settings MODIFY COLUMN telegram_bot_username VARCHAR(100) DEFAULT NULL");
        echo "   ✅ telegram_bot_username erweitert\n";
    } else {
        echo "   ✓ telegram_bot_username hat bereits die richtige Größe ($currentUsernameType)\n";
    }
    
    echo "\n3. 🧪 Erneuter Test mit korrigierten Spalten...\n";
    
    $userId = Auth::getUserId();
    $testToken = 'demo';
    
    echo "   User ID: $userId\n";
    echo "   Test Token: $testToken\n";
    
    // Direkter SQL-Test
    echo "\n   → Direkter UPDATE Test...\n";
    
    $updateResult = Database::update(
        'notification_settings',
        ['telegram_bot_token' => $testToken],
        'user_id = ?',
        [$userId]
    );
    
    echo "   UPDATE Ergebnis: " . ($updateResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";
    
    if ($updateResult) {
        // Verifikation
        $saved = Database::fetchOne(
            "SELECT telegram_bot_token FROM notification_settings WHERE user_id = ?",
            [$userId]
        );
        
        echo "   Gespeicherter Wert: " . ($saved['telegram_bot_token'] ?? 'NULL') . "\n";
        
        if ($saved && $saved['telegram_bot_token'] === $testToken) {
            echo "\n🎉 PROBLEM BEHOBEN!\n";
            echo "Die Spalten-Größe war das Problem.\n";
            echo "Sie können jetzt normal Bot-Token speichern.\n";
        } else {
            echo "\n❌ Noch immer ein Problem...\n";
        }
    } else {
        echo "\n🚨 UPDATE schlägt immer noch fehl!\n";
        echo "Das Problem liegt nicht an den Spalten-Größen.\n";
        
        // Weitere Diagnose
        echo "\n4. 🔍 Weitere Diagnose...\n";
        
        // Prüfen ob der Datensatz existiert
        $exists = Database::fetchOne(
            "SELECT id FROM notification_settings WHERE user_id = ?",
            [$userId]
        );
        
        if ($exists) {
            echo "   ✅ Datensatz existiert (ID: {$exists['id']})\n";
            
            // Versuch mit INSERT eines neuen Test-Datensatzes
            echo "   → Teste INSERT mit neuer Test-User-ID...\n";
            
            $testUserId = 999999; // Hoffentlich nicht verwendet
            
            try {
                $insertResult = Database::insert('notification_settings', [
                    'user_id' => $testUserId,
                    'email_notifications' => true,
                    'reading_reminder_enabled' => true,
                    'reading_reminder_days' => 5,
                    'high_usage_alert' => false,
                    'high_usage_threshold' => 200.00,
                    'cost_alert_enabled' => false,
                    'cost_alert_threshold' => 100.00,
                    'telegram_enabled' => false,
                    'telegram_bot_token' => $testToken
                ]);
                
                echo "   INSERT Ergebnis: " . ($insertResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";
                
                if ($insertResult) {
                    echo "   → INSERT funktioniert, UPDATE ist das Problem\n";
                    
                    // Test-Eintrag wieder löschen
                    Database::execute("DELETE FROM notification_settings WHERE user_id = ?", [$testUserId]);
                    echo "   → Test-Eintrag gelöscht\n";
                }
                
            } catch (Exception $e) {
                echo "   INSERT Fehler: " . $e->getMessage() . "\n";
            }
            
        } else {
            echo "   ❌ Datensatz existiert nicht!\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n❌ FEHLER: " . $e->getMessage() . "\n";
}

echo "\n</pre>\n";
echo '<p><a href="debug_telegram_save.php" class="btn btn-primary">🔍 Debug-Script erneut ausführen</a></p>';
echo '<p><a href="profil.php" class="btn btn-success">← Zurück zum Profil</a></p>';
?>
