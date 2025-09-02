#!/usr/bin/env php
<?php
/**
 * scripts/setup-telegram.php
 * Automatisches Setup für Telegram-Benachrichtigungen
 * 
 * Verwendung: php scripts/setup-telegram.php [BOT_TOKEN]
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

function setupLog($message, $level = 'INFO') {
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

// CLI-Script Kennzeichnung
define('CLI_MODE', true);

echo CLIColors::color("🤖 Telegram-Setup für Stromtracker\n", 'cyan');
echo "===================================\n\n";

// Bot-Token aus Parameter oder interaktiv
$botToken = $argv[1] ?? null;

if (!$botToken) {
    echo CLIColors::color("📱 Telegram Bot Setup-Assistent\n", 'blue');
    echo "================================\n\n";
    echo "Sie benötigen einen Telegram Bot-Token von @BotFather\n\n";
    echo "Schritte:\n";
    echo "1. Öffnen Sie Telegram\n";
    echo "2. Suchen Sie nach @BotFather\n";
    echo "3. Senden Sie: /newbot\n";
    echo "4. Folgen Sie den Anweisungen\n";
    echo "5. Kopieren Sie den Bot-Token\n\n";
    
    echo "Bot-Token eingeben (oder 'demo' für Demo-Modus): ";
    $botToken = trim(fgets(STDIN));
}

if (empty($botToken)) {
    setupLog("❌ Kein Bot-Token angegeben", 'ERROR');
    exit(1);
}

// Datenbank-Setup prüfen
try {
    // Pfad zur Stromtracker-Installation
    $basePath = dirname(__DIR__);
    
    if (!file_exists($basePath . '/config/database.php')) {
        setupLog("❌ config/database.php nicht gefunden", 'ERROR');
        exit(1);
    }
    
    // Datenbankverbindung
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');  
    define('DB_PASS', '');
    define('DB_NAME', 'stromtracker');
    define('DB_CHARSET', 'utf8mb4');
    
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    setupLog("✅ Datenbankverbindung hergestellt", 'SUCCESS');
    
} catch (PDOException $e) {
    setupLog("❌ Datenbankfehler: " . $e->getMessage(), 'ERROR');
    setupLog("💡 Lösungen:", 'INFO');
    setupLog("   - MySQL-Server starten", 'INFO');
    setupLog("   - Zugangsdaten in config/database.php prüfen", 'INFO');
    setupLog("   - Datenbank 'stromtracker' erstellen", 'INFO');
    exit(1);
}

// Schritt 1: Telegram-Tabellen erstellen
setupLog("📋 Erstelle Telegram-Tabellen...");

try {
    // notification_settings erweitern
    setupLog("  🔧 Erweitere notification_settings Tabelle...", 'INFO');
    
    // Prüfen ob Spalten bereits existieren
    $columns = $pdo->query("SHOW COLUMNS FROM notification_settings")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('telegram_enabled', $columns)) {
        $pdo->exec("ALTER TABLE notification_settings 
                    ADD COLUMN telegram_enabled BOOLEAN DEFAULT FALSE COMMENT 'Telegram Benachrichtigungen aktiviert',
                    ADD COLUMN telegram_chat_id VARCHAR(50) NULL COMMENT 'Telegram Chat-ID des Benutzers',
                    ADD COLUMN telegram_verified BOOLEAN DEFAULT FALSE COMMENT 'Telegram Chat-ID verifiziert'");
        setupLog("  ✅ notification_settings erweitert", 'SUCCESS');
    } else {
        setupLog("  ✅ notification_settings bereits aktuell", 'SUCCESS');
    }
    
    // telegram_config Tabelle
    setupLog("  🔧 Erstelle telegram_config Tabelle...", 'INFO');
    $pdo->exec("CREATE TABLE IF NOT EXISTS telegram_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bot_token VARCHAR(100) NOT NULL COMMENT 'Telegram Bot Token',
        bot_username VARCHAR(50) NULL COMMENT 'Bot Username für Anzeige',
        webhook_url VARCHAR(255) NULL COMMENT 'Webhook URL (optional)',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    setupLog("  ✅ telegram_config Tabelle erstellt", 'SUCCESS');
    
    // telegram_log Tabelle
    setupLog("  🔧 Erstelle telegram_log Tabelle...", 'INFO');
    $pdo->exec("CREATE TABLE IF NOT EXISTS telegram_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        chat_id VARCHAR(50) NOT NULL,
        message_type ENUM('notification', 'verification', 'command', 'error') DEFAULT 'notification',
        message_text TEXT NOT NULL,
        telegram_message_id INT NULL COMMENT 'Telegram Message ID',
        status ENUM('sent', 'failed', 'delivered') DEFAULT 'sent',
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_chat_id (chat_id),
        INDEX idx_created_at (created_at)
    )");
    setupLog("  ✅ telegram_log Tabelle erstellt", 'SUCCESS');
    
} catch (PDOException $e) {
    setupLog("❌ Fehler beim Erstellen der Tabellen: " . $e->getMessage(), 'ERROR');
    exit(1);
}

// Schritt 2: Bot-Konfiguration speichern
setupLog("🤖 Konfiguriere Telegram Bot...");

try {
    // Bot-Token validieren (falls nicht Demo)
    if ($botToken !== 'demo') {
        setupLog("  🔍 Validiere Bot-Token...", 'INFO');
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents("https://api.telegram.org/bot{$botToken}/getMe", false, $context);
        
        if ($response === false) {
            setupLog("  ❌ Telegram API nicht erreichbar", 'WARNING');
            setupLog("  💡 Bot wird trotzdem konfiguriert (offline)", 'INFO');
        } else {
            $data = json_decode($response, true);
            
            if (isset($data['ok']) && $data['ok']) {
                $botInfo = $data['result'];
                setupLog("  ✅ Bot-Token gültig: @" . ($botInfo['username'] ?? 'unbekannt'), 'SUCCESS');
                setupLog("  📝 Bot-Name: " . ($botInfo['first_name'] ?? 'Unbekannt'), 'INFO');
                $botUsername = $botInfo['username'] ?? null;
            } else {
                setupLog("  ❌ Ungültiger Bot-Token", 'ERROR');
                setupLog("  💡 Prüfen Sie den Token bei @BotFather", 'INFO');
                exit(1);
            }
        }
    } else {
        setupLog("  🧪 Demo-Modus aktiviert", 'INFO');
        $botUsername = 'demo_bot';
    }
    
    // Bot-Konfiguration speichern
    setupLog("  💾 Speichere Bot-Konfiguration...", 'INFO');
    
    // Bestehende Konfiguration löschen
    $pdo->exec("DELETE FROM telegram_config");
    
    // Neue Konfiguration einfügen
    $stmt = $pdo->prepare("INSERT INTO telegram_config (bot_token, bot_username, is_active) VALUES (?, ?, TRUE)");
    $stmt->execute([$botToken, $botUsername ?? null]);
    
    setupLog("  ✅ Bot-Konfiguration gespeichert", 'SUCCESS');
    
} catch (Exception $e) {
    setupLog("❌ Fehler bei Bot-Konfiguration: " . $e->getMessage(), 'ERROR');
    exit(1);
}

// Schritt 3: System testen
setupLog("🧪 Teste Telegram-System...");

try {
    // TelegramManager testen
    require_once $basePath . '/includes/TelegramManager.php';
    
    if (class_exists('TelegramManager')) {
        setupLog("  ✅ TelegramManager-Klasse verfügbar", 'SUCCESS');
        
        // isEnabled() testen
        if (TelegramManager::isEnabled()) {
            setupLog("  ✅ Telegram-System aktiviert", 'SUCCESS');
        } else {
            setupLog("  ❌ Telegram-System nicht aktiviert", 'ERROR');
        }
    } else {
        setupLog("  ❌ TelegramManager-Klasse nicht gefunden", 'ERROR');
    }
    
} catch (Exception $e) {
    setupLog("  ⚠️  System-Test fehlgeschlagen: " . $e->getMessage(), 'WARNING');
    setupLog("  💡 Prüfen Sie die includes/TelegramManager.php", 'INFO');
}

// Schritt 4: Erfolgsmeldung
echo "\n";
setupLog("🎉 TELEGRAM-SETUP ABGESCHLOSSEN!", 'SUCCESS');
setupLog("================================", 'SUCCESS');

echo "\n" . CLIColors::color("📱 NÄCHSTE SCHRITTE:\n", 'yellow');
echo "===================\n\n";

echo "1. " . CLIColors::color("Profil öffnen:", 'green') . "\n";
echo "   http://localhost/stromtracker/profil.php\n\n";

echo "2. " . CLIColors::color("Benachrichtigungen-Tab wählen", 'green') . "\n\n";

echo "3. " . CLIColors::color("Telegram aktivieren:", 'green') . "\n";
echo "   - Chat-ID eingeben (von @" . ($botUsername ?? 'ihrem_bot') . ")\n";
echo "   - Verifizierung durchführen\n";
echo "   - Test-Nachricht senden\n\n";

if ($botToken !== 'demo') {
    echo "4. " . CLIColors::color("Bot starten:", 'green') . "\n";
    echo "   https://t.me/" . ($botUsername ?? 'ihrem_bot') . "\n";
    echo "   Senden Sie: /start\n\n";
}

echo "5. " . CLIColors::color("System testen:", 'green') . "\n";
echo "   php scripts/test-telegram.php stats\n\n";

setupLog("✅ Telegram-Benachrichtigungen sind bereit! 🚀", 'SUCCESS');

echo "\n";
