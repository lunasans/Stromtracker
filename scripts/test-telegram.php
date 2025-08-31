#!/usr/bin/env php
<?php
/**
 * scripts/test-telegram.php
 * Test-Script für Telegram Bot Integration
 * 
 * Verwendung:
 * php scripts/test-telegram.php [action] [parameter]
 * 
 * Aktionen:
 * - install: Installation prüfen
 * - info: Bot-Informationen anzeigen
 * - send: Test-Nachricht senden
 * - verify: Chat-ID verifizieren
 * - stats: Telegram-Statistiken
 */

// Pfad zur Stromtracker-Installation
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';
require_once $basePath . '/includes/TelegramManager.php';
require_once $basePath . '/includes/NotificationManager.php';

// CLI-Script Kennzeichnung
define('CLI_MODE', true);

// Farben für CLI-Output
class CLIColors {
    public static $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m", 
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'reset' => "\033[0m"
    ];
    
    public static function color($text, $color) {
        return self::$colors[$color] . $text . self::$colors['reset'];
    }
}

// Logging-Funktion
function telegramLog($message, $level = 'INFO') {
    $colors = [
        'INFO' => 'cyan',
        'SUCCESS' => 'green', 
        'WARNING' => 'yellow',
        'ERROR' => 'red'
    ];
    
    $timestamp = date('H:i:s');
    $coloredLevel = CLIColors::color(sprintf("[%s]", $level), $colors[$level] ?? 'white');
    echo "[$timestamp] $coloredLevel $message\n";
}

// Hilfe anzeigen
function showHelp() {
    echo CLIColors::color("Stromtracker Telegram Bot - Test-Tool\n", 'cyan');
    echo "============================================\n\n";
    
    echo "Verwendung: php scripts/test-telegram.php [action] [parameter]\n\n";
    
    echo "Verfügbare Aktionen:\n";
    echo "  " . CLIColors::color("install", 'green') . "     - Telegram-System Installation prüfen\n";
    echo "  " . CLIColors::color("info", 'blue') . "        - Bot-Informationen anzeigen\n";
    echo "  " . CLIColors::color("send", 'yellow') . "        - Test-Nachricht senden (Chat-ID als Parameter)\n";
    echo "  " . CLIColors::color("verify", 'magenta') . "      - Chat-ID validieren (Chat-ID als Parameter)\n";
    echo "  " . CLIColors::color("stats", 'white') . "       - Telegram-Nutzungsstatistiken\n";
    echo "  " . CLIColors::color("config", 'white') . "      - Bot-Konfiguration anzeigen\n";
    echo "  " . CLIColors::color("help", 'white') . "        - Diese Hilfe anzeigen\n\n";
    
    echo "Beispiele:\n";
    echo "  php scripts/test-telegram.php install\n";
    echo "  php scripts/test-telegram.php info\n";
    echo "  php scripts/test-telegram.php send 123456789\n";
    echo "  php scripts/test-telegram.php verify 123456789\n";
    echo "  php scripts/test-telegram.php stats\n\n";
}

// Installation prüfen
function checkTelegramInstallation() {
    telegramLog("Prüfe Telegram-System Installation...");
    
    $issues = [];
    $success = 0;
    
    // 1. Datenbank-Tabellen prüfen
    telegramLog("Prüfe Datenbank-Tabellen...", 'INFO');
    
    $requiredTables = ['telegram_config', 'telegram_log', 'notification_settings'];
    
    foreach ($requiredTables as $table) {
        try {
            $exists = Database::fetchOne("SHOW TABLES LIKE '$table'");
            if ($exists) {
                telegramLog("  ✓ Tabelle '$table' gefunden", 'SUCCESS');
                $success++;
            } else {
                telegramLog("  ✗ Tabelle '$table' fehlt", 'ERROR');
                $issues[] = "Tabelle '$table' nicht gefunden - sql/telegram.sql ausführen";
            }
        } catch (Exception $e) {
            telegramLog("  ✗ Fehler beim Prüfen von '$table': " . $e->getMessage(), 'ERROR');
            $issues[] = "Datenbankfehler bei Tabelle '$table'";
        }
    }
    
    // 2. TelegramManager-Klasse prüfen
    telegramLog("Prüfe TelegramManager-Klasse...", 'INFO');
    if (class_exists('TelegramManager')) {
        telegramLog("  ✓ TelegramManager-Klasse geladen", 'SUCCESS');
        $success++;
    } else {
        telegramLog("  ✗ TelegramManager-Klasse nicht gefunden", 'ERROR');
        $issues[] = "includes/TelegramManager.php fehlt oder fehlerhaft";
    }
    
    // 3. Bot-Konfiguration prüfen
    telegramLog("Prüfe Bot-Konfiguration...", 'INFO');
    if (TelegramManager::isEnabled()) {
        telegramLog("  ✓ Telegram Bot aktiviert", 'SUCCESS');
        $success++;
    } else {
        telegramLog("  ✗ Telegram Bot nicht aktiviert", 'WARNING');
        $issues[] = "Bot-Token in telegram_config Tabelle konfigurieren";
    }
    
    // 4. Telegram API Erreichbarkeit
    if (TelegramManager::isEnabled()) {
        telegramLog("Prüfe Telegram API Erreichbarkeit...", 'INFO');
        try {
            $botInfo = TelegramManager::getBotInfo();
            if ($botInfo) {
                telegramLog("  ✓ Telegram API erreichbar", 'SUCCESS');
                telegramLog("  ✓ Bot: " . ($botInfo['first_name'] ?? 'Unbekannt'), 'SUCCESS');
                $success++;
            } else {
                telegramLog("  ✗ Telegram API nicht erreichbar", 'ERROR');
                $issues[] = "Bot-Token ungültig oder API nicht erreichbar";
            }
        } catch (Exception $e) {
            telegramLog("  ✗ API-Test fehlgeschlagen: " . $e->getMessage(), 'ERROR');
            $issues[] = "Telegram API Fehler: " . $e->getMessage();
        }
    }
    
    // Ergebnis
    echo "\n";
    if (count($issues) === 0) {
        telegramLog("🎉 Telegram-System vollständig installiert und funktionsfähig!", 'SUCCESS');
    } else {
        telegramLog("⚠️  Telegram-System unvollständig. Probleme:", 'WARNING');
        foreach ($issues as $issue) {
            telegramLog("  - $issue", 'ERROR');
        }
    }
    
    return count($issues) === 0;
}

// Bot-Informationen anzeigen
function showBotInfo() {
    telegramLog("Lade Bot-Informationen...");
    
    if (!TelegramManager::isEnabled()) {
        telegramLog("Telegram ist nicht aktiviert. Bot-Token konfigurieren!", 'ERROR');
        return;
    }
    
    try {
        $botInfo = TelegramManager::getBotInfo();
        
        if ($botInfo) {
            echo "\n";
            telegramLog("🤖 Bot-Informationen:", 'SUCCESS');
            telegramLog("  ID: " . $botInfo['id'], 'INFO');
            telegramLog("  Username: @" . ($botInfo['username'] ?? 'unbekannt'), 'INFO');
            telegramLog("  Name: " . ($botInfo['first_name'] ?? 'Unbekannt'), 'INFO');
            telegramLog("  Kann Gruppen beitreten: " . ($botInfo['can_join_groups'] ? 'Ja' : 'Nein'), 'INFO');
            telegramLog("  Kann Gruppennachrichten lesen: " . ($botInfo['can_read_all_group_messages'] ? 'Ja' : 'Nein'), 'INFO');
            
            echo "\n";
            telegramLog("📱 Bot-Link:", 'INFO');
            telegramLog("  https://t.me/" . ($botInfo['username'] ?? 'BOT_USERNAME'), 'INFO');
        } else {
            telegramLog("Bot-Informationen konnten nicht geladen werden", 'ERROR');
        }
        
    } catch (Exception $e) {
        telegramLog("Fehler beim Laden der Bot-Informationen: " . $e->getMessage(), 'ERROR');
    }
}

// Test-Nachricht senden
function sendTestMessage($chatId = null) {
    if (!$chatId) {
        telegramLog("Keine Chat-ID angegeben", 'ERROR');
        telegramLog("Verwendung: php scripts/test-telegram.php send [CHAT_ID]", 'INFO');
        return;
    }
    
    if (!TelegramManager::isEnabled()) {
        telegramLog("Telegram ist nicht aktiviert", 'ERROR');
        return;
    }
    
    telegramLog("Sende Test-Nachricht an Chat-ID: $chatId");
    
    try {
        $message = "🧪 <b>Test-Nachricht vom Stromtracker!</b>\n\n";
        $message .= "⚡ Wenn Sie diese Nachricht erhalten, funktioniert die Telegram-Integration korrekt.\n\n";
        $message .= "📊 <b>Test-Informationen:</b>\n";
        $message .= "🔸 Zeitstempel: " . date('d.m.Y H:i:s') . "\n";
        $message .= "🔸 Chat-ID: <code>$chatId</code>\n";
        $message .= "🔸 Server: " . gethostname() . "\n\n";
        $message .= "✅ Telegram-Benachrichtigungen sind bereit!";
        
        $success = TelegramManager::sendMessage($chatId, $message);
        
        if ($success) {
            telegramLog("✅ Test-Nachricht erfolgreich gesendet!", 'SUCCESS');
        } else {
            telegramLog("❌ Test-Nachricht konnte nicht gesendet werden", 'ERROR');
        }
        
    } catch (Exception $e) {
        telegramLog("Fehler beim Senden: " . $e->getMessage(), 'ERROR');
    }
}

// Chat-ID validieren
function verifyChatId($chatId = null) {
    if (!$chatId) {
        telegramLog("Keine Chat-ID angegeben", 'ERROR');
        telegramLog("Verwendung: php scripts/test-telegram.php verify [CHAT_ID]", 'INFO');
        return;
    }
    
    if (!TelegramManager::isEnabled()) {
        telegramLog("Telegram ist nicht aktiviert", 'ERROR');
        return;
    }
    
    telegramLog("Validiere Chat-ID: $chatId");
    
    try {
        $isValid = TelegramManager::validateChatId($chatId);
        
        if ($isValid) {
            telegramLog("✅ Chat-ID ist gültig", 'SUCCESS');
            
            // Zusätzliche Chat-Info über API
            $botToken = Database::fetchOne("SELECT bot_token FROM telegram_config WHERE is_active = 1")['bot_token'];
            $chatInfo = @file_get_contents("https://api.telegram.org/bot{$botToken}/getChat?chat_id={$chatId}");
            
            if ($chatInfo) {
                $chatData = json_decode($chatInfo, true);
                if ($chatData['ok']) {
                    $chat = $chatData['result'];
                    telegramLog("📱 Chat-Details:", 'INFO');
                    telegramLog("  Typ: " . ($chat['type'] ?? 'unbekannt'), 'INFO');
                    telegramLog("  Name: " . ($chat['first_name'] ?? $chat['title'] ?? 'unbekannt'), 'INFO');
                    if (isset($chat['username'])) {
                        telegramLog("  Username: @" . $chat['username'], 'INFO');
                    }
                }
            }
        } else {
            telegramLog("❌ Chat-ID ist ungültig oder Bot wurde nicht gestartet", 'ERROR');
            telegramLog("Lösung: Benutzer soll /start an den Bot senden", 'WARNING');
        }
        
    } catch (Exception $e) {
        telegramLog("Fehler bei der Validierung: " . $e->getMessage(), 'ERROR');
    }
}

// Statistiken anzeigen
function showTelegramStats() {
    telegramLog("Lade Telegram-Statistiken...");
    
    try {
        $stats = TelegramManager::getStatistics();
        
        if (empty($stats)) {
            telegramLog("Keine Statistik-Daten verfügbar", 'WARNING');
            return;
        }
        
        echo "\n";
        telegramLog("📊 Benutzer-Statistiken:", 'INFO');
        telegramLog("  Benutzer gesamt: " . ($stats['total_users'] ?? 0), 'INFO');
        telegramLog("  Telegram aktiviert: " . ($stats['enabled_users'] ?? 0), 'INFO');
        telegramLog("  Mit Chat-ID: " . ($stats['users_with_chat_id'] ?? 0), 'INFO');
        telegramLog("  Verifiziert: " . ($stats['verified_users'] ?? 0), 'INFO');
        
        echo "\n";
        telegramLog("📧 Nachrichten-Statistiken (30 Tage):", 'INFO');
        telegramLog("  Total gesendet: " . ($stats['total_messages'] ?? 0), 'INFO');
        telegramLog("  Erfolgreich: " . ($stats['sent_messages'] ?? 0), 'SUCCESS');
        telegramLog("  Fehlgeschlagen: " . ($stats['failed_messages'] ?? 0), 'ERROR');
        
        if ($stats['total_messages'] > 0) {
            $successRate = round(($stats['sent_messages'] / $stats['total_messages']) * 100, 1);
            telegramLog("  Erfolgsrate: $successRate%", $successRate >= 90 ? 'SUCCESS' : 'WARNING');
        }
        
        // Letzte Nachrichten
        $recentMessages = Database::fetchAll(
            "SELECT tl.*, u.name 
             FROM telegram_log tl
             LEFT JOIN users u ON tl.user_id = u.id
             ORDER BY tl.created_at DESC 
             LIMIT 5"
        ) ?: [];
        
        if (!empty($recentMessages)) {
            echo "\n";
            telegramLog("📋 Letzte Telegram-Nachrichten:", 'INFO');
            foreach ($recentMessages as $msg) {
                $status = [
                    'sent' => CLIColors::color('✓', 'green'),
                    'failed' => CLIColors::color('✗', 'red'),
                    'pending' => CLIColors::color('⏳', 'yellow')
                ][$msg['status']] ?? '?';
                
                $userName = $msg['name'] ?: 'User #' . $msg['user_id'];
                $messagePreview = substr($msg['message_text'], 0, 50) . (strlen($msg['message_text']) > 50 ? '...' : '');
                
                telegramLog(
                    "  $status " . $messagePreview . " → " . $userName . 
                    " (" . date('d.m H:i', strtotime($msg['created_at'])) . ")", 
                    'INFO'
                );
            }
        }
        
    } catch (Exception $e) {
        telegramLog("Fehler beim Laden der Statistiken: " . $e->getMessage(), 'ERROR');
    }
}

// Bot-Konfiguration anzeigen
function showBotConfig() {
    telegramLog("Lade Bot-Konfiguration...");
    
    try {
        $config = Database::fetchOne("SELECT * FROM telegram_config WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        
        if (!$config) {
            telegramLog("Keine Bot-Konfiguration gefunden", 'ERROR');
            telegramLog("Lösung: sql/telegram.sql ausführen und Bot-Token konfigurieren", 'WARNING');
            return;
        }
        
        echo "\n";
        telegramLog("⚙️  Bot-Konfiguration:", 'INFO');
        telegramLog("  Token: " . substr($config['bot_token'], 0, 20) . "...", 'INFO');
        telegramLog("  Username: @" . ($config['bot_username'] ?? 'nicht gesetzt'), 'INFO');
        telegramLog("  Status: " . ($config['is_active'] ? 'Aktiv' : 'Deaktiviert'), $config['is_active'] ? 'SUCCESS' : 'ERROR');
        telegramLog("  Erstellt: " . date('d.m.Y H:i', strtotime($config['created_at'])), 'INFO');
        
        if ($config['webhook_url']) {
            telegramLog("  Webhook: " . $config['webhook_url'], 'INFO');
        }
        
    } catch (Exception $e) {
        telegramLog("Fehler beim Laden der Konfiguration: " . $e->getMessage(), 'ERROR');
    }
}

// Haupt-Ausführung
$action = $argv[1] ?? 'help';
$param = $argv[2] ?? null;

echo CLIColors::color("🤖 Telegram Bot für Stromtracker - Test\n", 'cyan');
echo "=======================================\n\n";

switch ($action) {
    case 'install':
        checkTelegramInstallation();
        break;
        
    case 'info':
        showBotInfo();
        break;
        
    case 'send':
        sendTestMessage($param);
        break;
        
    case 'verify':
        verifyChatId($param);
        break;
        
    case 'stats':
        showTelegramStats();
        break;
        
    case 'config':
        showBotConfig();
        break;
        
    case 'help':
    default:
        showHelp();
        break;
}

echo "\n";
