<?php
// fix_telegram_update.php
// Analysiert und behebt das TelegramManager UPDATE-Problem

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();
$userId = Auth::getUserId();

echo "<h2>🔧 TelegramManager UPDATE-Fix</h2>\n";
echo "<pre>\n";

try {
    echo "1. 🧪 Einzelne Feld-Tests...\n";
    
    // Test jedes Feld einzeln
    $testFields = [
        'telegram_bot_token' => 'demo',
        'telegram_verified' => false,
        'telegram_chat_id' => null
    ];
    
    foreach ($testFields as $field => $value) {
        echo "\n   → Teste Feld: $field = " . var_export($value, true) . "\n";
        
        try {
            $result = Database::update(
                'notification_settings',
                [$field => $value],
                'user_id = ?',
                [$userId]
            );
            
            echo "   Ergebnis: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n";
            
            if (!$result) {
                echo "   🚨 PROBLEM-FELD GEFUNDEN: $field\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Exception: " . $e->getMessage() . "\n";
            echo "   🚨 PROBLEM-FELD GEFUNDEN: $field\n";
        }
    }
    
    echo "\n2. 🔍 Boolean-Wert Analyse...\n";
    
    // Das Problem könnte bei boolean false vs. 0 vs. '0' liegen
    $booleanTests = [
        'false (bool)' => false,
        '0 (int)' => 0,
        "'0' (string)" => '0',
        'null' => null
    ];
    
    echo "   Teste telegram_verified mit verschiedenen Werten:\n";
    
    foreach ($booleanTests as $label => $value) {
        echo "\n   → $label: ";
        
        try {
            $result = Database::update(
                'notification_settings',
                ['telegram_verified' => $value],
                'user_id = ?',
                [$userId]
            );
            
            echo ($result ? "✅ SUCCESS" : "❌ FAILED");
            
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage();
        }
    }
    
    echo "\n\n3. 🔍 NULL-Wert Analyse...\n";
    
    // Das Problem könnte bei NULL-Werten liegen
    echo "   Teste telegram_chat_id mit verschiedenen NULL-Werten:\n";
    
    $nullTests = [
        'null' => null,
        "'' (empty string)" => '',
        'NULL (string)' => 'NULL'
    ];
    
    foreach ($nullTests as $label => $value) {
        echo "\n   → $label: ";
        
        try {
            $result = Database::update(
                'notification_settings',
                ['telegram_chat_id' => $value],
                'user_id = ?',
                [$userId]
            );
            
            echo ($result ? "✅ SUCCESS" : "❌ FAILED");
            
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage();
        }
    }
    
    echo "\n\n4. 🛠️ Korrigierter TelegramManager-UPDATE...\n";
    
    // Teste mit korrigierten Werten
    echo "   → Versuch 1: Explizite Integer-Konvertierung...\n";
    
    $fixedData1 = [
        'telegram_bot_token' => 'demo',
        'telegram_verified' => 0,  // Explizit als 0 statt false
        'telegram_chat_id' => null
    ];
    
    try {
        $result1 = Database::update(
            'notification_settings',
            $fixedData1,
            'user_id = ?',
            [$userId]
        );
        
        echo "   Ergebnis: " . ($result1 ? "✅ SUCCESS" : "❌ FAILED") . "\n";
        
    } catch (Exception $e) {
        echo "   ❌ Exception: " . $e->getMessage() . "\n";
    }
    
    if (!$result1) {
        echo "\n   → Versuch 2: Ohne NULL-Werte...\n";
        
        $fixedData2 = [
            'telegram_bot_token' => 'demo',
            'telegram_verified' => 0
            // telegram_chat_id weglassen
        ];
        
        try {
            $result2 = Database::update(
                'notification_settings',
                $fixedData2,
                'user_id = ?',
                [$userId]
            );
            
            echo "   Ergebnis: " . ($result2 ? "✅ SUCCESS" : "❌ FAILED") . "\n";
            
        } catch (Exception $e) {
            echo "   ❌ Exception: " . $e->getMessage() . "\n";
        }
        
        if ($result2) {
            echo "\n🎉 PROBLEM GEFUNDEN: NULL-Werte verursachen das Problem!\n";
            echo "Lösung: NULL-Werte im TelegramManager vermeiden.\n";
        }
    } else {
        echo "\n🎉 PROBLEM GEFUNDEN: Boolean false vs. Integer 0!\n";
        echo "Lösung: false zu 0 konvertieren im TelegramManager.\n";
    }
    
    echo "\n5. 📋 Empfohlene Fix-Strategie...\n";
    
    if ($result1) {
        echo "   ✅ Verwende explizite Integer-Konvertierung\n";
        echo "   ✅ false → 0, true → 1\n";
        echo "   ✅ NULL-Werte sind OK\n";
    } else {
        echo "   ✅ Vermeiden von NULL-Werten in UPDATEs\n";
        echo "   ✅ Nur Felder mit nicht-NULL Werten updaten\n";
        echo "   ✅ Separate UPDATEs für NULL-Werte\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ANALYSE-FEHLER: " . $e->getMessage() . "\n";
}

echo "\n</pre>\n";
echo '<p><a href="profil.php" class="btn btn-primary">← Zurück zum Profil</a></p>';
?>
