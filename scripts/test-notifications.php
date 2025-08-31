#!/usr/bin/env php
<?php
/**
 * scripts/test-notifications.php
 * Test-Script für das Benachrichtigungssystem
 * 
 * Verwendung:
 * php scripts/test-notifications.php [action]
 * 
 * Aktionen:
 * - check: Prüft welche Benutzer Erinnerungen benötigen
 * - send: Sendet Test-Erinnerung an aktuellen Benutzer
 * - install: Prüft Installation des Systems
 * - stats: Zeigt Benachrichtigungsstatistiken
 */

// Pfad zur Stromtracker-Installation
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';
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
function testLog($message, $level = 'INFO') {
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

// Hilfe-Text anzeigen
function showHelp() {
    echo CLIColors::color("Stromtracker Benachrichtigungen - Test-Tool\n", 'cyan');
    echo "==============================================\n\n";
    
    echo "Verwendung: php scripts/test-notifications.php [action]\n\n";
    
    echo "Verfügbare Aktionen:\n";
    echo "  " . CLIColors::color("install", 'green') . "   - Prüft Installation des Benachrichtigungssystems\n";
    echo "  " . CLIColors::color("check", 'blue') . "     - Zeigt Benutzer die Erinnerungen benötigen\n";
    echo "  " . CLIColors::color("send", 'yellow') . "      - Sendet Test-Erinnerung (User-ID als Parameter)\n";
    echo "  " . CLIColors::color("stats", 'magenta') . "     - Zeigt Benachrichtigungsstatistiken\n";
    echo "  " . CLIColors::color("help", 'white') . "      - Zeigt diese Hilfe\n\n";
    
    echo "Beispiele:\n";
    echo "  php scripts/test-notifications.php install\n";
    echo "  php scripts/test-notifications.php check\n";
    echo "  php scripts/test-notifications.php send 1\n";
    echo "  php scripts/test-notifications.php stats\n\n";
}

// Installation prüfen
function checkInstallation() {
    testLog("Prüfe Benachrichtigungssystem-Installation...");
    
    $issues = [];
    $success = 0;
    
    // 1. Tabellen prüfen
    testLog("Prüfe Datenbank-Tabellen...", 'INFO');
    
    $requiredTables = ['notification_settings', 'notification_log', 'reminder_queue'];
    
    foreach ($requiredTables as $table) {
        try {
            $exists = Database::fetchOne("SHOW TABLES LIKE '$table'");
            if ($exists) {
                testLog("  ✓ Tabelle '$table' gefunden", 'SUCCESS');
                $success++;
            } else {
                testLog("  ✗ Tabelle '$table' fehlt", 'ERROR');
                $issues[] = "Tabelle '$table' nicht gefunden - sql/notifications.sql ausführen";
            }
        } catch (Exception $e) {
            testLog("  ✗ Fehler beim Prüfen von '$table': " . $e->getMessage(), 'ERROR');
            $issues[] = "Datenbankfehler bei Tabelle '$table'";
        }
    }
    
    // 2. NotificationManager-Klasse prüfen
    testLog("Prüfe NotificationManager-Klasse...", 'INFO');
    if (class_exists('NotificationManager')) {
        testLog("  ✓ NotificationManager-Klasse geladen", 'SUCCESS');
        $success++;
    } else {
        testLog("  ✗ NotificationManager-Klasse nicht gefunden", 'ERROR');
        $issues[] = "includes/NotificationManager.php fehlt oder fehlerhaft";
    }
    
    // 3. Logs-Ordner prüfen
    testLog("Prüfe Logs-Ordner...", 'INFO');
    $logDir = dirname(__DIR__) . '/logs';
    if (is_dir($logDir) && is_writable($logDir)) {
        testLog("  ✓ Logs-Ordner beschreibbar", 'SUCCESS');
        $success++;
    } else {
        testLog("  ✗ Logs-Ordner nicht beschreibbar", 'WARNING');
        $issues[] = "Logs-Ordner erstellen: mkdir logs && chmod 755 logs";
    }
    
    // 4. Cron-Script prüfen
    testLog("Prüfe Cron-Script...", 'INFO');
    $cronScript = dirname(__DIR__) . '/scripts/send-reminders.php';
    if (file_exists($cronScript) && is_readable($cronScript)) {
        testLog("  ✓ Cron-Script gefunden", 'SUCCESS');
        $success++;
    } else {
        testLog("  ✗ Cron-Script fehlt", 'ERROR');
        $issues[] = "scripts/send-reminders.php fehlt";
    }
    
    // Ergebnis
    echo "\n";
    if (count($issues) === 0) {
        testLog("🎉 Installation vollständig! Alle Komponenten gefunden.", 'SUCCESS');
        testLog("Nächster Schritt: Cron-Job einrichten", 'INFO');
        testLog("Beispiel: 0 18 * * * /usr/bin/php " . dirname(__DIR__) . "/scripts/send-reminders.php", 'INFO');
    } else {
        testLog("⚠️  Installation unvollständig. Probleme gefunden:", 'WARNING');
        foreach ($issues as $issue) {
            testLog("  - $issue", 'ERROR');
        }
    }
    
    return count($issues) === 0;
}

// Benutzer prüfen die Erinnerungen benötigen
function checkReminders() {
    testLog("Prüfe Benutzer die Erinnerungen benötigen...");
    
    try {
        $reminders = NotificationManager::findUsersNeedingReminders();
        
        if (empty($reminders)) {
            testLog("✅ Keine Erinnerungen fällig", 'SUCCESS');
            return;
        }
        
        testLog("📧 " . count($reminders) . " Benutzer benötigen Erinnerungen:", 'WARNING');
        
        foreach ($reminders as $item) {
            $user = $item['user'];
            $reminder = $item['reminder'];
            
            echo "\n";
            testLog("Benutzer: " . $user['name'] . " (" . $user['email'] . ")", 'INFO');
            testLog("  Grund: " . $reminder['reason'], 'INFO');
            testLog("  Nachricht: " . $reminder['message'], 'INFO');
            
            if (isset($reminder['days_since'])) {
                testLog("  Tage seit letzter Ablesung: " . $reminder['days_since'], 'INFO');
            }
            
            if (isset($reminder['suggested_date'])) {
                testLog("  Vorgeschlagenes Datum: " . date('d.m.Y', strtotime($reminder['suggested_date'])), 'INFO');
            }
        }
        
    } catch (Exception $e) {
        testLog("Fehler beim Prüfen der Erinnerungen: " . $e->getMessage(), 'ERROR');
    }
}

// Test-Erinnerung senden
function sendTestReminder($userId = null) {
    if (!$userId) {
        testLog("Keine User-ID angegeben", 'ERROR');
        testLog("Verwendung: php scripts/test-notifications.php send [USER_ID]", 'INFO');
        return;
    }
    
    testLog("Sende Test-Erinnerung an User ID $userId...");
    
    try {
        // Benutzer laden
        $user = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            testLog("Benutzer mit ID $userId nicht gefunden", 'ERROR');
            return;
        }
        
        testLog("Benutzer gefunden: " . $user['name'] . " (" . $user['email'] . ")", 'SUCCESS');
        
        // Prüfen ob Erinnerung benötigt wird
        $reminderCheck = NotificationManager::needsReadingReminder($userId);
        
        if (!$reminderCheck['needed']) {
            testLog("Benutzer benötigt momentan keine Erinnerung", 'INFO');
            testLog("Sende trotzdem Test-Erinnerung...", 'WARNING');
            
            // Test-Daten erstellen
            $reminderData = [
                'needed' => true,
                'reason' => 'test_reminder',
                'message' => 'Dies ist eine Test-Erinnerung für das Benachrichtigungssystem.',
                'suggested_date' => date('Y-m-01')
            ];
        } else {
            testLog("Erinnerung ist fällig: " . $reminderCheck['message'], 'INFO');
            $reminderData = $reminderCheck;
        }
        
        // Test-E-Mail senden
        testLog("Sende E-Mail...", 'INFO');
        $success = NotificationManager::sendReminderEmail(
            $user['email'],
            $user['name'],
            $reminderData
        );
        
        if ($success) {
            testLog("✅ Test-E-Mail erfolgreich gesendet!", 'SUCCESS');
            NotificationManager::markReminderSent($userId);
        } else {
            testLog("❌ Fehler beim Senden der Test-E-Mail", 'ERROR');
            testLog("Prüfen Sie die PHP mail() Konfiguration", 'WARNING');
        }
        
    } catch (Exception $e) {
        testLog("Fehler beim Senden der Test-Erinnerung: " . $e->getMessage(), 'ERROR');
    }
}

// Statistiken anzeigen
function showStats() {
    testLog("Lade Benachrichtigungsstatistiken...");
    
    try {
        // Gesamtstatistiken
        $totalUsers = Database::fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
        $configuredUsers = Database::fetchOne("SELECT COUNT(*) as count FROM notification_settings")['count'] ?? 0;
        $activeReminders = Database::fetchOne("SELECT COUNT(*) as count FROM notification_settings WHERE reading_reminder_enabled = 1")['count'] ?? 0;
        
        echo "\n";
        testLog("📊 System-Statistiken:", 'INFO');
        testLog("  Benutzer gesamt: $totalUsers", 'INFO');
        testLog("  Benachrichtigungen konfiguriert: $configuredUsers", 'INFO');
        testLog("  Aktive Erinnerungen: $activeReminders", 'INFO');
        
        // Benachrichtigungslog
        $logStats = Database::fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
             FROM notification_log"
        ) ?? ['total' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0];
        
        echo "\n";
        testLog("📧 Benachrichtigungslog:", 'INFO');
        testLog("  Total versendet: " . $logStats['total'], 'INFO');
        testLog("  Erfolgreich: " . $logStats['sent'], 'SUCCESS');
        testLog("  Fehlgeschlagen: " . $logStats['failed'], 'ERROR');
        testLog("  Ausstehend: " . $logStats['pending'], 'WARNING');
        
        if ($logStats['total'] > 0) {
            $successRate = round(($logStats['sent'] / $logStats['total']) * 100, 1);
            testLog("  Erfolgsrate: $successRate%", $successRate >= 80 ? 'SUCCESS' : 'WARNING');
        }
        
        // Letzte Benachrichtigungen
        $recentNotifications = Database::fetchAll(
            "SELECT n.*, u.name, u.email 
             FROM notification_log n
             JOIN users u ON n.user_id = u.id
             ORDER BY n.created_at DESC 
             LIMIT 5"
        ) ?? [];
        
        if (!empty($recentNotifications)) {
            echo "\n";
            testLog("📋 Letzte Benachrichtigungen:", 'INFO');
            foreach ($recentNotifications as $notif) {
                $status = [
                    'sent' => CLIColors::color('✓', 'green'),
                    'failed' => CLIColors::color('✗', 'red'),
                    'pending' => CLIColors::color('⏳', 'yellow')
                ][$notif['status']] ?? '?';
                
                testLog(
                    "  $status " . $notif['subject'] . " → " . $notif['name'] . 
                    " (" . date('d.m H:i', strtotime($notif['created_at'])) . ")", 
                    'INFO'
                );
            }
        }
        
    } catch (Exception $e) {
        testLog("Fehler beim Laden der Statistiken: " . $e->getMessage(), 'ERROR');
    }
}

// Haupt-Ausführung
$action = $argv[1] ?? 'help';
$param = $argv[2] ?? null;

echo CLIColors::color("🔔 Stromtracker Benachrichtigungen - Test\n", 'cyan');
echo "=========================================\n\n";

switch ($action) {
    case 'install':
        checkInstallation();
        break;
        
    case 'check':
        if (!checkInstallation()) {
            testLog("Installation unvollständig. Beheben Sie erst die Probleme.", 'ERROR');
            exit(1);
        }
        checkReminders();
        break;
        
    case 'send':
        if (!checkInstallation()) {
            testLog("Installation unvollständig. Beheben Sie erst die Probleme.", 'ERROR');
            exit(1);
        }
        sendTestReminder($param);
        break;
        
    case 'stats':
        if (!checkInstallation()) {
            testLog("Installation unvollständig. Beheben Sie erst die Probleme.", 'ERROR');
            exit(1);
        }
        showStats();
        break;
        
    case 'help':
    default:
        showHelp();
        break;
}

echo "\n";
