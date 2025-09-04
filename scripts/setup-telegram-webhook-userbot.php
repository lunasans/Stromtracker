<?php
// scripts/setup-telegram-webhook-userbot.php
// TELEGRAM USER-BOT WEBHOOK SETUP - Für benutzerspezifische Bots

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// Setup-Logging Funktion
function setupLog($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $colors = [
        'INFO' => '💙',
        'SUCCESS' => '✅',
        'WARNING' => '⚠️',
        'ERROR' => '❌',
    ];
    
    $icon = $colors[$type] ?? '📝';
    echo "[$timestamp] $icon $message\n";
    
    // Auch in Error-Log schreiben
    error_log("[$timestamp] [TELEGRAM-USERBOT-SETUP] [$type] $message");
}

echo "<!DOCTYPE html>\n<html><head><title>Telegram User-Bot Webhook Setup</title>";
echo "<style>body{font-family:monospace;background:#1a1a1a;color:#00ff00;padding:20px;}</style>";
echo "</head><body>\n<h2>🤖 TELEGRAM USER-BOT WEBHOOK SETUP</h2>\n<pre>\n";

try {
    // Auth prüfen (nur für Admins - User-ID 1)
    Auth::requireLogin();
    $adminUserId = Auth::getUserId();
    
    if ($adminUserId !== 1) {
        throw new Exception('Admin-Rechte erforderlich - nur Benutzer mit ID 1');
    }
    
    setupLog("🚀 TELEGRAM USER-BOT WEBHOOK SETUP GESTARTET");
    setupLog("📝 Admin: " . Auth::getUser()['email']);
    
    // Schritt 1: Aktive Telegram-Benutzer laden
    setupLog("🔍 Suche aktive Telegram-Benutzer...");
    
    $activeUsers = Database::fetchAll(
        "SELECT u.id, u.name, u.email, 
                ns.telegram_bot_token, ns.telegram_bot_username, 
                ns.telegram_chat_id, ns.telegram_enabled, ns.telegram_verified
         FROM users u 
         JOIN notification_settings ns ON u.id = ns.user_id 
         WHERE ns.telegram_enabled = 1 
         AND ns.telegram_bot_token IS NOT NULL 
         AND ns.telegram_bot_token != ''
         ORDER BY u.name"
    );
    
    if (empty($activeUsers)) {
        throw new Exception('Keine Telegram-aktiven Benutzer gefunden. Benutzer müssen zuerst ihre Bot-Tokens konfigurieren.');
    }
    
    setupLog("✅ " . count($activeUsers) . " aktive Telegram-Benutzer gefunden:", 'SUCCESS');
    
    foreach ($activeUsers as $user) {
        $verified = $user['telegram_verified'] ? '✅' : '⚠️';
        $chatId = $user['telegram_chat_id'] ?: 'nicht gesetzt';
        setupLog("  👤 {$user['name']} - @{$user['telegram_bot_username']} $verified (Chat: $chatId)");
    }
    
    // Schritt 2: Webhook-URL generieren
    setupLog("🌐 Generiere Webhook-URL...");
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = "{$protocol}://{$host}";
    
    $webhookUrl = "{$baseUrl}" . dirname($_SERVER['PHP_SELF']) . "/../api/telegram-webhook.php";
    
    setupLog("🎯 Webhook-URL: $webhookUrl", 'INFO');
    
    // Schritt 3: Webhook für jeden Bot registrieren
    setupLog("🔗 Registriere Webhooks für alle Benutzer-Bots...");
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($activeUsers as $user) {
        $botToken = $user['telegram_bot_token'];
        $userName = $user['name'];
        $botUsername = $user['telegram_bot_username'] ?? 'unbekannt';
        
        setupLog("🤖 Konfiguriere Bot für {$userName} (@{$botUsername})...");
        
        if ($botToken === 'demo') {
            setupLog("  🧪 Demo-Bot - Webhook-Registrierung übersprungen", 'WARNING');
            continue;
        }
        
        try {
            // Bot-Token validieren
            setupLog("  🔍 Validiere Bot-Token...");
            
            $response = @file_get_contents(
                "https://api.telegram.org/bot{$botToken}/getMe",
                false,
                stream_context_create(['http' => ['timeout' => 10]])
            );
            
            if ($response === false) {
                throw new Exception('Telegram API nicht erreichbar');
            }
            
            $botInfo = json_decode($response, true);
            if (!$botInfo['ok']) {
                throw new Exception('Ungültiger Bot-Token: ' . ($botInfo['description'] ?? 'Unbekannt'));
            }
            
            setupLog("  ✅ Bot-Token gültig: " . $botInfo['result']['first_name'], 'SUCCESS');
            
            // Alten Webhook entfernen
            setupLog("  🧹 Entferne alten Webhook...");
            
            $deleteResponse = @file_get_contents(
                "https://api.telegram.org/bot{$botToken}/deleteWebhook",
                false,
                stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'timeout' => 10
                    ]
                ])
            );
            
            // Neuen Webhook registrieren
            setupLog("  🔗 Registriere neuen Webhook...");
            
            $postData = http_build_query([
                'url' => $webhookUrl,
                'max_connections' => 40,
                'allowed_updates' => json_encode(['message', 'callback_query'])
            ]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => $postData,
                    'timeout' => 15
                ]
            ]);
            
            $webhookResponse = @file_get_contents(
                "https://api.telegram.org/bot{$botToken}/setWebhook",
                false,
                $context
            );
            
            if ($webhookResponse === false) {
                throw new Exception('Webhook-Registrierung fehlgeschlagen - API nicht erreichbar');
            }
            
            $webhookResult = json_decode($webhookResponse, true);
            if (!$webhookResult['ok']) {
                throw new Exception('Webhook-Registrierung fehlgeschlagen: ' . ($webhookResult['description'] ?? 'Unbekannt'));
            }
            
            setupLog("  ✅ Webhook erfolgreich registriert!", 'SUCCESS');
            
            // Webhook-Status prüfen
            $statusResponse = @file_get_contents(
                "https://api.telegram.org/bot{$botToken}/getWebhookInfo",
                false,
                stream_context_create(['http' => ['timeout' => 10]])
            );
            
            if ($statusResponse) {
                $status = json_decode($statusResponse, true);
                if ($status && $status['ok']) {
                    $info = $status['result'];
                    setupLog("  📊 Webhook aktiv: " . ($info['url'] ? 'JA' : 'NEIN'));
                    setupLog("  📥 Pending Updates: " . ($info['pending_update_count'] ?? 0));
                }
            }
            
            $successCount++;
            
        } catch (Exception $e) {
            setupLog("  ❌ Fehler für {$userName}: " . $e->getMessage(), 'ERROR');
            $errorCount++;
        }
        
        // Kurze Pause zwischen Bots
        usleep(500000); // 0.5 Sekunden
    }
    
    // Schritt 4: Zusammenfassung
    echo "\n";
    setupLog("📊 SETUP-ERGEBNISSE:");
    setupLog("  ✅ Erfolgreich: $successCount Bots");
    setupLog("  ❌ Fehlerhaft: $errorCount Bots");
    setupLog("  📊 Gesamt: " . count($activeUsers) . " Benutzer");
    
    $successRate = count($activeUsers) > 0 ? ($successCount / count($activeUsers)) * 100 : 0;
    setupLog("  📈 Erfolgsrate: " . number_format($successRate, 1) . "%");
    
    echo "\n";
    
    if ($successRate >= 80) {
        setupLog("🎉 WEBHOOK-SETUP ERFOLGREICH!", 'SUCCESS');
    } elseif ($successRate >= 50) {
        setupLog("⚠️ WEBHOOK-SETUP TEILWEISE ERFOLGREICH", 'WARNING');
    } else {
        setupLog("❌ WEBHOOK-SETUP PROBLEMATISCH", 'ERROR');
    }
    
    // Schritt 5: Test-Empfehlungen
    echo "\n";
    setupLog("💡 Nächste Schritte:");
    setupLog("  1️⃣ Testen Sie den Bot durch direkte Nachrichten");
    setupLog("  2️⃣ Verifizieren Sie Chat-IDs in den Profilen");
    setupLog("  3️⃣ Führen Sie den Funktionstest aus");
    setupLog("  4️⃣ Überwachen Sie die Logs auf Aktivität");
    echo "\n";
    setupLog("🔍 Webhook-Endpoint: $webhookUrl");
    setupLog("📝 Log-Datei: /logs für Debugging-Informationen");
    
    // Schritt 6: Benutzer ohne Chat-ID auflisten
    $unverifiedUsers = array_filter($activeUsers, function($user) {
        return !$user['telegram_verified'] || empty($user['telegram_chat_id']);
    });
    
    if (!empty($unverifiedUsers)) {
        echo "\n";
        setupLog("⚠️ Benutzer ohne verifizierte Chat-ID:", 'WARNING');
        foreach ($unverifiedUsers as $user) {
            setupLog("  👤 {$user['name']} ({$user['email']}) - Bot konfiguriert aber Chat-ID fehlt");
        }
        setupLog("💡 Diese Benutzer müssen ihre Chat-ID in ihrem Profil verifizieren!");
    }
    
} catch (Exception $e) {
    echo "\n";
    setupLog("❌ SETUP FEHLGESCHLAGEN: " . $e->getMessage(), 'ERROR');
    echo "\n";
    setupLog("🛠️ Lösungsansätze:");
    setupLog("   - Prüfen Sie ob Benutzer Bot-Tokens konfiguriert haben");
    setupLog("   - Stellen Sie sicher, dass HTTPS verfügbar ist");
    setupLog("   - Kontaktieren Sie @BotFather für Bot-Status");
    setupLog("   - Prüfen Sie die Netzwerk-Verbindung");
}

echo "\n</pre>\n";
echo '<p><a href="../admin-telegram.php" class="btn btn-primary">← Zurück zur Telegram-Verwaltung</a></p>';
echo '<p><a href="test-telegram-bot.php" class="btn btn-success">🧪 Bot-Funktionen testen</a></p>';
echo '<p><a href="../profil.php" class="btn btn-info">👤 Profil konfigurieren</a></p>';
echo "</body></html>\n";
?>
