<?php
// scripts/setup-telegram-webhook.php
// TELEGRAM BOT WEBHOOK SETUP - Automatische Webhook-Registrierung

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
    error_log("[$timestamp] [TELEGRAM-SETUP] [$type] $message");
}

echo "<!DOCTYPE html>\n<html><head><title>Telegram Webhook Setup</title>";
echo "<style>body{font-family:monospace;background:#1a1a1a;color:#00ff00;padding:20px;}</style>";
echo "</head><body>\n<h2>🤖 TELEGRAM BOT WEBHOOK SETUP</h2>\n<pre>\n";

try {
    // Auth prüfen (nur für Admins - User-ID 1)
    Auth::requireLogin();
    $adminUserId = Auth::getUserId();
    
    if ($adminUserId !== 1) {
        throw new Exception('Admin-Rechte erforderlich - nur Benutzer mit ID 1');
    }
    
    setupLog("🚀 TELEGRAM WEBHOOK SETUP GESTARTET");
    setupLog("📝 Admin: " . Auth::getUser()['email']);
    
    // Schritt 1: System-Konfiguration prüfen
    setupLog("🔍 Prüfe System-Konfiguration...");
    
    $systemConfig = Database::fetchOne(
        "SELECT * FROM telegram_config WHERE is_active = 1 LIMIT 1"
    );
    
    if (!$systemConfig) {
        throw new Exception('Keine aktive Telegram-Konfiguration gefunden. Bitte führen Sie zuerst scripts/setup-telegram.php aus.');
    }
    
    $botToken = $systemConfig['bot_token'];
    $botUsername = $systemConfig['bot_username'];
    
    setupLog("✅ Bot gefunden: @$botUsername", 'SUCCESS');
    setupLog("🔑 Token: " . substr($botToken, 0, 10) . "...", 'INFO');
    
    // Schritt 2: Webhook-URL generieren
    setupLog("🌐 Generiere Webhook-URL...");
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = "{$protocol}://{$host}";
    
    // Webhook-Token für zusätzliche Sicherheit
    $webhookToken = hash('sha256', $botToken . 'webhook_secret');
    $webhookUrl = "{$baseUrl}" . dirname($_SERVER['PHP_SELF']) . "/../api/telegram-webhook.php?token={$webhookToken}";
    
    setupLog("🎯 Webhook-URL: $webhookUrl", 'INFO');
    
    // Schritt 3: Bot-Token validieren
    if ($botToken !== 'demo') {
        setupLog("🔍 Validiere Bot-Token...");
        
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
        
        setupLog("✅ Bot-Token gültig", 'SUCCESS');
        setupLog("🤖 Bot-Name: " . $botInfo['result']['first_name'], 'INFO');
        
    } else {
        setupLog("🧪 Demo-Modus - Webhook wird simuliert", 'WARNING');
    }
    
    // Schritt 4: Alten Webhook entfernen
    setupLog("🧹 Entferne alten Webhook...");
    
    if ($botToken !== 'demo') {
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
        
        $deleteResult = json_decode($deleteResponse, true);
        if ($deleteResult && $deleteResult['ok']) {
            setupLog("✅ Alter Webhook entfernt", 'SUCCESS');
        } else {
            setupLog("⚠️ Fehler beim Entfernen: " . ($deleteResult['description'] ?? 'Unbekannt'), 'WARNING');
        }
    }
    
    // Schritt 5: Neuen Webhook registrieren
    setupLog("🔗 Registriere neuen Webhook...");
    
    if ($botToken !== 'demo') {
        $postData = http_build_query([
            'url' => $webhookUrl,
            'max_connections' => 40,
            'allowed_updates' => json_encode(['message', 'callback_query']),
            // Telegram sendet diesen Wert bei jedem Webhook im Header
            // "X-Telegram-Bot-Api-Secret-Token" zurück -> serverseitig geprüft.
            'secret_token' => $webhookToken
        ]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postData,
                'timeout' => 15
            ]
        ]);
        
        $response = @file_get_contents(
            "https://api.telegram.org/bot{$botToken}/setWebhook",
            false,
            $context
        );
        
        if ($response === false) {
            throw new Exception('Webhook-Registrierung fehlgeschlagen - API nicht erreichbar');
        }
        
        $result = json_decode($response, true);
        if (!$result['ok']) {
            throw new Exception('Webhook-Registrierung fehlgeschlagen: ' . ($result['description'] ?? 'Unbekannt'));
        }
        
        setupLog("✅ Webhook erfolgreich registriert!", 'SUCCESS');
        
    } else {
        setupLog("🧪 Demo-Modus - Webhook-Registrierung übersprungen", 'INFO');
    }
    
    // Schritt 6: Webhook-Status prüfen
    setupLog("📊 Prüfe Webhook-Status...");
    
    if ($botToken !== 'demo') {
        $statusResponse = @file_get_contents(
            "https://api.telegram.org/bot{$botToken}/getWebhookInfo",
            false,
            stream_context_create(['http' => ['timeout' => 10]])
        );
        
        $status = json_decode($statusResponse, true);
        if ($status && $status['ok']) {
            $info = $status['result'];
            setupLog("🎯 Webhook-URL: " . ($info['url'] ?? 'Keine'), 'INFO');
            setupLog("✅ SSL-Zertifikat: " . ($info['has_custom_certificate'] ? 'Custom' : 'Standard'), 'INFO');
            setupLog("🔢 Pending Updates: " . ($info['pending_update_count'] ?? 0), 'INFO');
            
            if (!empty($info['last_error_message'])) {
                setupLog("⚠️ Letzter Fehler: " . $info['last_error_message'], 'WARNING');
            }
        }
    }
    
    // Schritt 7: Test-Webhook senden (optional)
    if (isset($_GET['test']) && $_GET['test'] === 'true') {
        setupLog("🧪 Führe Webhook-Test durch...");
        
        $testData = [
            'update_id' => 999999999,
            'message' => [
                'message_id' => 999999999,
                'from' => [
                    'id' => 12345,
                    'first_name' => 'Test',
                    'username' => 'testuser'
                ],
                'chat' => [
                    'id' => 12345,
                    'type' => 'private'
                ],
                'date' => time(),
                'text' => '/start'
            ]
        ];
        
        $testContext = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($testData),
                'timeout' => 10
            ]
        ]);
        
        $testResponse = @file_get_contents($webhookUrl, false, $testContext);
        
        if ($testResponse !== false) {
            $testResult = json_decode($testResponse, true);
            if ($testResult && $testResult['ok']) {
                setupLog("✅ Webhook-Test erfolgreich", 'SUCCESS');
            } else {
                setupLog("⚠️ Webhook-Test mit Warnung: " . json_encode($testResult), 'WARNING');
            }
        } else {
            setupLog("❌ Webhook-Test fehlgeschlagen", 'ERROR');
        }
    }
    
    // Schritt 8: Konfiguration in Datenbank aktualisieren
    setupLog("💾 Aktualisiere Konfiguration...");
    
    $updateResult = Database::update(
        'telegram_config',
        [
            'webhook_url' => $webhookUrl,
            'webhook_token' => $webhookToken,
            'webhook_set_at' => date('Y-m-d H:i:s'),
            'is_active' => 1
        ],
        'id = ?',
        [$systemConfig['id']]
    );
    
    if ($updateResult) {
        setupLog("✅ Konfiguration aktualisiert", 'SUCCESS');
    } else {
        setupLog("⚠️ Konfiguration konnte nicht aktualisiert werden", 'WARNING');
    }
    
    // Abschlussmeldung
    echo "\n";
    setupLog("🎉 WEBHOOK-SETUP ERFOLGREICH ABGESCHLOSSEN!", 'SUCCESS');
    echo "\n";
    setupLog("📋 Zusammenfassung:");
    setupLog("   🤖 Bot: @$botUsername");
    setupLog("   🌐 Webhook: " . substr($webhookUrl, 0, 50) . "...");
    setupLog("   🔐 Sicher: " . ($webhookToken ? 'Ja' : 'Nein'));
    echo "\n";
    setupLog("💡 Nächste Schritte:");
    setupLog("   1️⃣ Testen Sie den Bot durch direkte Nachricht");
    setupLog("   2️⃣ Konfigurieren Sie Chat-IDs in den Profilen");
    setupLog("   3️⃣ Senden Sie Zählerstände zum Testen");
    echo "\n";
    setupLog("🔍 Debugging: Prüfen Sie /logs für Webhook-Aktivität");
    
} catch (Exception $e) {
    echo "\n";
    setupLog("❌ SETUP FEHLGESCHLAGEN: " . $e->getMessage(), 'ERROR');
    echo "\n";
    setupLog("🛠️ Lösungsansätze:");
    setupLog("   - Prüfen Sie die Bot-Token Konfiguration");
    setupLog("   - Stellen Sie sicher, dass HTTPS verfügbar ist");
    setupLog("   - Kontaktieren Sie @BotFather für Bot-Status");
    setupLog("   - Prüfen Sie die Firewall-Einstellungen");
}

echo "\n</pre>\n";
echo '<p><a href="../admin-telegram.php" class="btn btn-primary">← Zurück zur Telegram-Verwaltung</a></p>';
echo '<p><a href="?test=true" class="btn btn-warning">🧪 Webhook testen</a></p>';
echo "</body></html>\n";
?>
