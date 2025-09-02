<?php
// deep_debug_telegram.php
// Tieferes Debugging für den Datenbankfehler

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/TelegramManager.php';

// Login erforderlich
Auth::requireLogin();
$userId = Auth::getUserId();

echo "<h2>🔬 Deep Debug: Datenbankfehler</h2>\n";
echo "<pre>\n";

try {
    echo "1. 📋 Bestehender Datensatz laden...\n";
    
    $existing = Database::fetchOne(
        "SELECT * FROM notification_settings WHERE user_id = ?",
        [$userId]
    );
    
    if ($existing) {
        echo "   ✅ Datensatz gefunden (ID: {$existing['id']})\n";
        echo "   - User ID: {$existing['user_id']}\n";
        echo "   - Current Token: " . ($existing['telegram_bot_token'] ?: 'NULL') . "\n";
    } else {
        echo "   ❌ Kein Datensatz gefunden!\n";
        exit;
    }
    
    echo "\n2. 🧪 Manueller UPDATE-Test...\n";
    
    $testToken = 'demo_test_' . time(); // Eindeutiger Test-Token
    echo "   Test Token: $testToken\n";
    
    // Schritt 1: Direkte SQL-Ausführung
    echo "\n   → Schritt 1: Direkte SQL-Ausführung...\n";
    
    $sql = "UPDATE notification_settings SET telegram_bot_token = ? WHERE user_id = ?";
    echo "   SQL: $sql\n";
    echo "   Params: ['$testToken', $userId]\n";
    
    try {
        $directResult = Database::execute($sql, [$testToken, $userId]);
        echo "   Direktes execute() Ergebnis: " . ($directResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";
        
        // Verifikation
        $check1 = Database::fetchOne("SELECT telegram_bot_token FROM notification_settings WHERE user_id = ?", [$userId]);
        echo "   Gespeicherter Wert: " . ($check1['telegram_bot_token'] ?? 'NULL') . "\n";
        
    } catch (Exception $e) {
        echo "   ❌ SQL-Exception: " . $e->getMessage() . "\n";
    }
    
    // Schritt 2: Database::update() verwenden
    echo "\n   → Schritt 2: Database::update() Methode...\n";
    
    $testToken2 = 'demo_update_' . time();
    echo "   Test Token 2: $testToken2\n";
    
    try {
        $updateResult = Database::update(
            'notification_settings',
            ['telegram_bot_token' => $testToken2],
            'user_id = ?',
            [$userId]
        );
        
        echo "   Database::update() Ergebnis: " . ($updateResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";
        
        if (!$updateResult) {
            echo "   → Database::update() gab FALSE zurück\n";
        }
        
        // Verifikation
        $check2 = Database::fetchOne("SELECT telegram_bot_token FROM notification_settings WHERE user_id = ?", [$userId]);
        echo "   Gespeicherter Wert: " . ($check2['telegram_bot_token'] ?? 'NULL') . "\n";
        
    } catch (Exception $e) {
        echo "   ❌ Database::update() Exception: " . $e->getMessage() . "\n";
    }
    
    // Schritt 3: Prüfen ob WHERE-Klausel das Problem ist
    echo "\n   → Schritt 3: WHERE-Klausel testen...\n";
    
    $countBefore = Database::fetchOne("SELECT COUNT(*) as count FROM notification_settings WHERE user_id = ?", [$userId]);
    echo "   Datensätze mit user_id = $userId: " . $countBefore['count'] . "\n";
    
    if ($countBefore['count'] == 0) {
        echo "   🚨 PROBLEM GEFUNDEN: WHERE-Klausel findet keinen Datensatz!\n";
        
        // Alle user_ids auflisten
        $allUsers = Database::fetchAll("SELECT user_id FROM notification_settings");
        echo "   Vorhandene user_ids: ";
        foreach ($allUsers as $u) {
            echo $u['user_id'] . " ";
        }
        echo "\n";
    }
    
    // Schritt 4: TelegramManager debuggen
    echo "\n3. 🔍 TelegramManager::saveUserBot() debuggen...\n";
    
    // Nur die relevanten Teile der saveUserBot Methode nachstellen
    echo "   → Bestehende Settings prüfen (wie TelegramManager)...\n";
    
    $existing = Database::fetchOne(
        "SELECT id FROM notification_settings WHERE user_id = ?",
        [$userId]
    );
    
    if ($existing) {
        echo "   ✅ TelegramManager würde UPDATE verwenden\n";
        echo "   → Simuliere TelegramManager UPDATE...\n";
        
        $data = [
            'telegram_bot_token' => 'demo',
            'telegram_verified' => false,
            'telegram_chat_id' => null
        ];
        
        echo "   Data Array: " . json_encode($data) . "\n";
        
        try {
            $tmResult = Database::update(
                'notification_settings',
                $data,
                'user_id = ?',
                [$userId]
            );
            
            echo "   TelegramManager-Style UPDATE: " . ($tmResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";
            
            if ($tmResult) {
                echo "\n🎉 PROBLEM BEHOBEN!\n";
                echo "Der TelegramManager-Style UPDATE funktioniert.\n";
                echo "Das Problem lag wahrscheinlich an einem temporären Zustand.\n";
            } else {
                echo "\n🚨 TelegramManager-UPDATE schlägt auch fehl!\n";
                
                // Letzte Diagnose: Permissions, Locks, etc.
                echo "\n4. 🔧 Letzte Diagnose...\n";
                echo "   → Prüfe Tabellen-Status...\n";
                
                $tableStatus = Database::fetchOne("SHOW TABLE STATUS LIKE 'notification_settings'");
                if ($tableStatus) {
                    echo "   Engine: " . $tableStatus['Engine'] . "\n";
                    echo "   Rows: " . $tableStatus['Rows'] . "\n";
                }
                
                // Versuch mit sehr einfachem UPDATE
                echo "   → Einfachster möglicher UPDATE...\n";
                $simpleResult = Database::execute(
                    "UPDATE notification_settings SET telegram_enabled = telegram_enabled WHERE user_id = ?", 
                    [$userId]
                );
                echo "   Einfacher UPDATE (keine Änderung): " . ($simpleResult ? "✅ SUCCESS" : "❌ FAILED") . "\n";
                
                if (!$simpleResult) {
                    echo "\n💥 KRITISCH: Selbst einfache UPDATEs schlagen fehl!\n";
                    echo "Das deutet auf ein grundlegendes Problem hin:\n";
                    echo "- Tabelle ist gesperrt\n";
                    echo "- Benutzer hat keine UPDATE-Berechtigung\n"; 
                    echo "- Datenbank-Constraint-Problem\n";
                    echo "- InnoDB-Speicher-Problem\n";
                }
            }
            
        } catch (Exception $e) {
            echo "   ❌ TelegramManager-UPDATE Exception: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "   ⚠️ TelegramManager würde INSERT verwenden\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ DEEP DEBUG FEHLER: " . $e->getMessage() . "\n";
}

echo "\n</pre>\n";
echo '<p><a href="profil.php" class="btn btn-primary">← Zurück zum Profil</a></p>';
?>
