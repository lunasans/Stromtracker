#!/usr/bin/env php
<?php
/**
 * scripts/test-telegram.php
 * Test-Script für Telegram Bot Integration (Verbesserte Version mit Fehlerbehandlung)
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

// Verbesserte Datenbank-Prüfung
function checkDatabaseConnection() {
    // PHP-Erweiterungen prüfen
    if (!extension_loaded('pdo')) {
        telegramLog("❌ PDO-Erweiterung nicht verfügbar", 'ERROR');
        telegramLog("Lösung: php -m | grep pdo", 'INFO');
        return false;
    }
    
    $drivers = PDO::getAvailableDrivers();
    if (!in_array('mysql', $drivers)) {
        telegramLog("❌ PDO MySQL-Treiber nicht verfügbar", 'ERROR');
        telegramLog("Verfügbare Treiber: " . implode(', ', $drivers), 'INFO');
        telegramLog("Lösung: PDO MySQL installieren", 'INFO');
        return false;
    }
    
    // Pfad zur Stromtracker-Installation
    $basePath = dirname(__DIR__);
    
    // Datenbankverbindung testen
    try {
        // Konfiguration laden
        if (!file_exists($basePath . '/config/database.php')) {
            telegramLog("❌ config/database.php nicht gefunden", 'ERROR');
            return false;
        }
        
        // Nur die Konstanten definieren, nicht die PDO-Verbindung erstellen
        define('DB_HOST', 'localhost');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_NAME', 'stromtracker');
        define('DB_CHARSET', 'utf8mb4');
        
        // Testverbindung
        $testPdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        telegramLog("✅ Datenbankverbindung erfolgreich", 'SUCCESS');
        return $testPdo;
        
    } catch (PDOException $e) {
        telegramLog("❌ Datenbankverbindung fehlgeschlagen: " . $e->getMessage(), 'ERROR');
        
        // Spezifische Lösungsvorschläge
        if (strpos($e->getMessage(), 'could not find driver') !== false) {
            telegramLog("💡 Lösung: PDO MySQL-Treiber installieren", 'WARNING');
            telegramLog("   Windows: extension=pdo_mysql in php.ini aktivieren", 'INFO');
            telegramLog("   Linux: sudo apt-get install php-mysql", 'INFO');
        } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
            telegramLog("💡 Lösung: MySQL-Zugangsdaten prüfen", 'WARNING');
            telegramLog("   Datei: config/database.php", 'INFO');
        } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
            telegramLog("💡 Lösung: Datenbank 'stromtracker' erstellen", 'WARNING');
            telegramLog("   mysql -u root -p -e \"CREATE DATABASE stromtracker\"", 'INFO');
        }
        
        return false;
    }
}

// Vereinfachte Database-Klasse für CLI
class SimpleDatabase {
    private static $pdo;
    
    public static function init($pdo) {
        self::$pdo = $pdo;
    }
    
    public static function fetchOne($sql, $params = []) {
        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            telegramLog("SQL Error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    public static function fetchAll($sql, $params = []) {
        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            telegramLog("SQL Error: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
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
    echo "  " . CLIColors::color("checkdb", 'white') . "     - Datenbankverbindung prüfen\n";
    echo "  " . CLIColors::color("help", 'white') . "        - Diese Hilfe anzeigen\n\n";
    
    echo "Beispiele:\n";
    echo "  php scripts/test-telegram.php checkdb\n";
    echo "  php scripts/test-telegram.php install\n";
    echo "  php scripts/test-telegram.php info\n";
    echo "  php scripts/test-telegram.php send 123456789\n\n";
}

// Installation prüfen
function checkTelegramInstallation($pdo) {
    telegramLog("Prüfe Telegram-System Installation...");
    
    $issues = [];
    $success = 0;
    
    // 1. Datenbank-Tabellen prüfen
    telegramLog("Prüfe Datenbank-Tabellen...", 'INFO');
    
    $requiredTables = ['telegram_config', 'telegram_log', 'notification_settings'];
    
    foreach ($requiredTables as $table) {
        try {
            $exists = SimpleDatabase::fetchOne("SHOW TABLES LIKE '$table'");
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
    
    // 2. Bot-Konfiguration prüfen
    telegramLog("Prüfe Bot-Konfiguration...", 'INFO');
    try {
        $config = SimpleDatabase::fetchOne("SELECT * FROM telegram_config WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        
        if ($config && !empty($config['bot_token']) && $config['bot_token'] !== 'YOUR_BOT_TOKEN_HERE') {
            telegramLog("  ✓ Bot-Token konfiguriert", 'SUCCESS');
            $success++;
        } else {
            telegramLog("  ✗ Bot-Token nicht konfiguriert", 'WARNING');
            $issues[] = "Bot-Token in telegram_config Tabelle konfigurieren";
        }
    } catch (Exception $e) {
        telegramLog("  ✗ Fehler beim Laden der Bot-Konfiguration: " . $e->getMessage(), 'ERROR');
        $issues[] = "Kann Bot-Konfiguration nicht laden";
    }
    
    // Ergebnis
    echo "\n";
    if (count($issues) === 0) {
        telegramLog("🎉 Telegram-System vollständig installiert!", 'SUCCESS');
    } else {
        telegramLog("⚠️  Telegram-System unvollständig. Probleme:", 'WARNING');
        foreach ($issues as $issue) {
            telegramLog("  - $issue", 'ERROR');
        }
    }
    
    return count($issues) === 0;
}

// Bot-Informationen anzeigen
function showBotInfo($pdo) {
    telegramLog("Lade Bot-Informationen...");
    
    try {
        $config = SimpleDatabase::fetchOne("SELECT * FROM telegram_config WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        
        if (!$config || empty($config['bot_token']) || $config['bot_token'] === 'YOUR_BOT_TOKEN_HERE') {
            telegramLog("Bot-Token nicht konfiguriert", 'ERROR');
            return;
        }
        
        $botToken = $config['bot_token'];
        $url = "https://api.telegram.org/bot{$botToken}/getMe";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            telegramLog("Telegram API nicht erreichbar", 'ERROR');
            return;
        }
        
        $data = json_decode($response, true);
        
        if (!$data['ok']) {
            telegramLog("API-Fehler: " . ($data['description'] ?? 'Unbekannt'), 'ERROR');
            return;
        }
        
        $botInfo = $data['result'];
        
        echo "\n";
        telegramLog("🤖 Bot-Informationen:", 'SUCCESS');
        telegramLog("  ID: " . $botInfo['id'], 'INFO');
        telegramLog("  Username: @" . ($botInfo['username'] ?? 'unbekannt'), 'INFO');
        telegramLog("  Name: " . ($botInfo['first_name'] ?? 'Unbekannt'), 'INFO');
        
        echo "\n";
        telegramLog("📱 Bot-Link:", 'INFO');
        telegramLog("  https://t.me/" . ($botInfo['username'] ?? 'BOT_USERNAME'), 'INFO');
        
    } catch (Exception $e) {
        telegramLog("Fehler beim Laden der Bot-Informationen: " . $e->getMessage(), 'ERROR');
    }
}

// Statistiken anzeigen
function showTelegramStats($pdo) {
    telegramLog("Lade Telegram-Statistiken...");
    
    try {
        // Benutzer-Statistiken
        $userStats = SimpleDatabase::fetchOne(
            "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN telegram_enabled THEN 1 ELSE 0 END) as enabled_users,
                SUM(CASE WHEN telegram_chat_id IS NOT NULL THEN 1 ELSE 0 END) as users_with_chat_id,
                SUM(CASE WHEN telegram_verified THEN 1 ELSE 0 END) as verified_users
             FROM notification_settings"
        ) ?: ['total_users' => 0, 'enabled_users' => 0, 'users_with_chat_id' => 0, 'verified_users' => 0];
        
        // Nachrichten-Statistiken
        $messageStats = SimpleDatabase::fetchOne(
            "SELECT 
                COUNT(*) as total_messages,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_messages,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_messages
             FROM telegram_log 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ) ?: ['total_messages' => 0, 'sent_messages' => 0, 'failed_messages' => 0];
        
        echo "\n";
        telegramLog("📊 Benutzer-Statistiken:", 'INFO');
        telegramLog("  Benutzer gesamt: " . $userStats['total_users'], 'INFO');
        telegramLog("  Telegram aktiviert: " . $userStats['enabled_users'], 'INFO');
        telegramLog("  Mit Chat-ID: " . $userStats['users_with_chat_id'], 'INFO');
        telegramLog("  Verifiziert: " . $userStats['verified_users'], 'INFO');
        
        echo "\n";
        telegramLog("📧 Nachrichten-Statistiken (30 Tage):", 'INFO');
        telegramLog("  Total gesendet: " . $messageStats['total_messages'], 'INFO');
        telegramLog("  Erfolgreich: " . $messageStats['sent_messages'], 'SUCCESS');
        telegramLog("  Fehlgeschlagen: " . $messageStats['failed_messages'], 'ERROR');
        
        if ($messageStats['total_messages'] > 0) {
            $successRate = round(($messageStats['sent_messages'] / $messageStats['total_messages']) * 100, 1);
            telegramLog("  Erfolgsrate: $successRate%", $successRate >= 90 ? 'SUCCESS' : 'WARNING');
        }
        
    } catch (Exception $e) {
        telegramLog("Fehler beim Laden der Statistiken: " . $e->getMessage(), 'ERROR');
    }
}

// Hauptausführung
$action = $argv[1] ?? 'help';
$param = $argv[2] ?? null;

echo CLIColors::color("🤖 Telegram Bot für Stromtracker - Test\n", 'cyan');
echo "=======================================\n\n";

// CLI-Script Kennzeichnung
define('CLI_MODE', true);

switch ($action) {
    case 'checkdb':
        $pdo = checkDatabaseConnection();
        if ($pdo) {
            telegramLog("✅ Datenbankverbindung funktioniert", 'SUCCESS');
        } else {
            telegramLog("❌ Datenbankverbindung fehlgeschlagen", 'ERROR');
            telegramLog("💡 Führen Sie aus: php scripts/check-php.php", 'INFO');
        }
        break;
        
    case 'install':
        $pdo = checkDatabaseConnection();
        if ($pdo) {
            SimpleDatabase::init($pdo);
            checkTelegramInstallation($pdo);
        } else {
            telegramLog("Datenbankverbindung erforderlich für Installation-Check", 'ERROR');
        }
        break;
        
    case 'info':
        $pdo = checkDatabaseConnection();
        if ($pdo) {
            SimpleDatabase::init($pdo);
            showBotInfo($pdo);
        } else {
            telegramLog("Datenbankverbindung erforderlich für Bot-Info", 'ERROR');
        }
        break;
        
    case 'stats':
        $pdo = checkDatabaseConnection();
        if ($pdo) {
            SimpleDatabase::init($pdo);
            showTelegramStats($pdo);
        } else {
            telegramLog("Datenbankverbindung erforderlich für Statistiken", 'ERROR');
        }
        break;
        
    case 'send':
        telegramLog("Direct send feature requires web interface", 'INFO');
        telegramLog("Use: http://localhost/stromtracker/profil.php", 'INFO');
        break;
        
    case 'verify':
        telegramLog("Direct verify feature requires web interface", 'INFO');
        telegramLog("Use: http://localhost/stromtracker/profil.php", 'INFO');
        break;
        
    case 'config':
        $pdo = checkDatabaseConnection();
        if ($pdo) {
            SimpleDatabase::init($pdo);
            $config = SimpleDatabase::fetchOne("SELECT * FROM telegram_config WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
            
            if (!$config) {
                telegramLog("Keine Bot-Konfiguration gefunden", 'ERROR');
            } else {
                echo "\n";
                telegramLog("⚙️  Bot-Konfiguration:", 'INFO');
                telegramLog("  Token: " . substr($config['bot_token'], 0, 20) . "...", 'INFO');
                telegramLog("  Username: @" . ($config['bot_username'] ?? 'nicht gesetzt'), 'INFO');
                telegramLog("  Status: " . ($config['is_active'] ? 'Aktiv' : 'Deaktiviert'), $config['is_active'] ? 'SUCCESS' : 'ERROR');
            }
        }
        break;
        
    case 'help':
    default:
        showHelp();
        break;
}

echo "\n";
