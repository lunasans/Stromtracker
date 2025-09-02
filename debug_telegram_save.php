<?php
// debug_telegram_save.php
// Debug-Script für Telegram Bot-Token Speicherung (ohne getConnection)

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/TelegramManager.php';

// Login erforderlich
Auth::requireLogin();
$userId = Auth::getUserId();

echo "<h2>🔍 Telegram Bot-Token Debug (Korrigiert)</h2>\n";
echo "<pre>\n";

try {
    // 1. Datenbankverbindung testen
    echo "1. 📋 Datenbankverbindung...\n";
    
    $testQuery = Database::fetchOne("SELECT 1 as test");
    if ($testQuery && $testQuery['test'] == 1) {
        echo "   ✅ Verbindung OK\n";
    } else {
        echo "   ❌ Datenbankverbindung fehlgeschlagen\n";
        exit;
    }
    
    // 2. User-Info
    echo "\n2. 👤 Benutzer-Info...\n";
    echo "   User ID: $userId\n";
    
    // 3. Tabellen-Struktur prüfen
    echo "\n3. 🗃️ notification_settings Tabelle...\n";
    
    $tableExists = Database::fetchOne("SHOW TABLES LIKE 'notification_settings'");
    if (!$tableExists) {
        echo "   ❌ Tabelle existiert nicht!\n";
        echo "   → Führen Sie setup_telegram_tables.php aus\n";
        exit;
    }
    
    echo "   ✅ Tabelle existiert\n";
    
    // Spalten auflisten
    echo "\n   📋 Vorhandene Spalten:\n";
    $columns = Database::fetchAll("DESCRIBE notification_settings");
    foreach ($columns as $col) {
        echo "   - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    // 4. Erforderliche Spalten prüfen
    echo "\n4. 🔍 Erforderliche Telegram-Spalten...\n";
    
    $requiredColumns = [
        'telegram_enabled' => 'tinyint(1)',
        'telegram_bot_token' => 'varchar(255)',
        'telegram_bot_username' => 'varchar(100)', 
        'telegram_chat_id' => 'varchar(50)',
        'telegram_verified' => 'tinyint(1)'
    ];
    
    $missingColumns = [];
    foreach ($requiredColumns as $column => $type) {
        $columnExists = Database::fetchOne("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'notification_settings' 
            AND COLUMN_NAME = ?
        ", [$column]);
        
        if ($columnExists) {
            echo "   ✅ $column\n";
        } else {
            echo "   ❌ $column FEHLT!\n";
            $missingColumns[] = $column;
        }
    }
    
    if (!empty($missingColumns)) {
        echo "\n   🚨 FEHLENDE SPALTEN GEFUNDEN!\n";
        echo "   → Führen Sie setup_telegram_tables.php aus\n";
        echo "   → Oder führen Sie diese SQL-Statements aus:\n\n";
        
        foreach ($missingColumns as $column) {
            $definition = $requiredColumns[$column];
            echo "   ALTER TABLE notification_settings ADD COLUMN `$column` $definition DEFAULT " . 
                 (strpos($definition, 'varchar') !== false ? "NULL" : "0") . ";\n";
        }
        echo "\n";
        exit;
    }
    
    // 5. Bestehende Settings laden
    echo "\n5. 📖 Bestehende notification_settings...\n";
    
    $existing = Database::fetchOne(
        "SELECT * FROM notification_settings WHERE user_id = ?",
        [$userId]
    );
    
    if ($existing) {
        echo "   ✅ Eintrag existiert (ID: " . $existing['id'] . ")\n";
        echo "   - Telegram aktiviert: " . ($existing['telegram_enabled'] ?? 'NULL') . "\n";
        echo "   - Bot-Token: " . (empty($existing['telegram_bot_token']) ? 'LEER' : 'VORHANDEN (' . substr($existing['telegram_bot_token'], 0, 10) . '...)') . "\n";
    } else {
        echo "   ⚠️ Noch kein Eintrag vorhanden\n";
    }
    
    // 6. Test-Speicherung durchführen
    echo "\n6. 🧪 Test-Speicherung...\n";
    
    $testToken = 'demo';
    echo "   Token: $testToken\n";
    
    // Validierung testen
    if (!TelegramManager::validateBotToken($testToken)) {
        echo "   ❌ Token-Validierung fehlgeschlagen\n";
        exit;
    }
    echo "   ✅ Token-Validierung OK\n";
    
    // TelegramManager verwenden
    echo "   → Verwende TelegramManager::saveUserBot()...\n";
    
    try {
        $result = TelegramManager::saveUserBot($userId, $testToken);
        echo "   Ergebnis: " . ($result ? "✅ ERFOLGREICH" : "❌ FEHLGESCHLAGEN") . "\n";
        
        if ($result) {
            // 7. Verifikation
            echo "\n7. ✅ Verifikation...\n";
            
            $saved = Database::fetchOne(
                "SELECT telegram_bot_token FROM notification_settings WHERE user_id = ?",
                [$userId]
            );
            
            if ($saved && $saved['telegram_bot_token'] === $testToken) {
                echo "   ✅ Token korrekt gespeichert!\n";
                echo "\n🎉 TEST ERFOLGREICH!\n";
                echo "\nDas System funktioniert korrekt.\n";
                echo "Sie können jetzt normale Bot-Token speichern.\n";
            } else {
                echo "   ❌ Token nicht korrekt gespeichert\n";
                echo "   Expected: $testToken\n";
                echo "   Found: " . ($saved['telegram_bot_token'] ?? 'NULL') . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "   ❌ Exception: " . $e->getMessage() . "\n";
        echo "\n🚨 PROBLEM GEFUNDEN!\n";
        echo "Fehlermeldung: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ KRITISCHER FEHLER: " . $e->getMessage() . "\n";
    echo "\nPrüfen Sie:\n";
    echo "- Ist die Datenbank erreichbar?\n";
    echo "- Existiert die notification_settings Tabelle?\n";
    echo "- Sind alle erforderlichen Spalten vorhanden?\n";
}

echo "\n</pre>\n";
echo '<p><a href="profil.php" class="btn btn-primary">← Zurück zum Profil</a></p>';
echo '<p><a href="setup_telegram_tables.php" class="btn btn-warning">🛠️ Tabellen-Setup ausführen</a></p>';
?>
