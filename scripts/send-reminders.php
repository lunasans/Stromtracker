#!/usr/bin/env php
<?php
/**
 * scripts/send-reminders.php
 * Cron-Job für automatische Zählerstand-Erinnerungen (mit Telegram-Support)
 * 
 * Verwendung:
 * php scripts/send-reminders.php
 * 
 * Cron-Job Beispiel (täglich 18:00):
 * 0 18 * * * /usr/bin/php /path/to/stromtracker/scripts/send-reminders.php
 */

// Pfad zur Stromtracker-Installation
$basePath = dirname(__DIR__);
require_once $basePath . '/config/database.php';
require_once $basePath . '/includes/NotificationManager.php';
require_once $basePath . '/includes/TelegramManager.php';

// CLI-Script Kennzeichnung
define('CLI_MODE', true);

// Logging-Funktion für Cron
function cronLog($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    
    // In Datei schreiben
    $logFile = dirname(__DIR__) . '/logs/reminders.log';
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Auch auf Konsole ausgeben
    echo $logMessage;
}

// System-Status prüfen
function checkSystemStatus() {
    $status = [
        'notifications_ready' => false,
        'telegram_ready' => false,
        'email_ready' => true // Annahme: E-Mail immer verfügbar
    ];
    
    // Notification-System prüfen
    try {
        $tableExists = Database::fetchOne("SHOW TABLES LIKE 'notification_settings'");
        $status['notifications_ready'] = (bool)$tableExists;
    } catch (Exception $e) {
        cronLog("Notification-System Check Fehler: " . $e->getMessage(), 'ERROR');
    }
    
    // Telegram-System prüfen
    try {
        $status['telegram_ready'] = TelegramManager::isEnabled();
        if ($status['telegram_ready']) {
            cronLog("Telegram-Bot aktiviert", 'INFO');
        } else {
            cronLog("Telegram-Bot deaktiviert oder nicht konfiguriert", 'INFO');
        }
    } catch (Exception $e) {
        cronLog("Telegram-System Check Fehler: " . $e->getMessage(), 'WARNING');
        $status['telegram_ready'] = false;
    }
    
    return $status;
}

// Haupt-Ausführung
try {
    cronLog("=== Reminder-Job gestartet ===");
    
    // System-Status prüfen
    $systemStatus = checkSystemStatus();
    
    if (!$systemStatus['notifications_ready']) {
        cronLog("Notification-System nicht installiert. Führen Sie sql/notifications.sql aus.", 'ERROR');
        exit(1);
    }
    
    cronLog("System-Status:", 'INFO');
    cronLog("  E-Mail: " . ($systemStatus['email_ready'] ? 'Verfügbar' : 'Nicht verfügbar'), 'INFO');
    cronLog("  Telegram: " . ($systemStatus['telegram_ready'] ? 'Verfügbar' : 'Nicht verfügbar'), 'INFO');
    
    // Erinnerungen verarbeiten
    cronLog("Verarbeite ausstehende Erinnerungen...", 'INFO');
    $result = NotificationManager::processPendingReminders();
    
    // Detaillierte Ausgabe
    cronLog("=== ERGEBNIS ===", 'INFO');
    cronLog("Total Benutzer geprüft: {$result['total']}", 'INFO');
    cronLog("Total Nachrichten gesendet: {$result['sent']}", 'INFO');
    
    // Kanal-spezifische Statistiken (falls verfügbar)
    if (isset($result['email_sent'])) {
        cronLog("  📧 E-Mail gesendet: {$result['email_sent']}", 'INFO');
    }
    if (isset($result['telegram_sent'])) {
        cronLog("  📱 Telegram gesendet: {$result['telegram_sent']}", 'INFO');
    }
    
    if ($result['failed'] > 0) {
        cronLog("Fehlgeschlagen: {$result['failed']}", 'WARNING');
    }
    
    // Erfolgs-/Fehler-Meldungen
    if ($result['sent'] > 0) {
        cronLog("✅ {$result['sent']} Erinnerungen erfolgreich versendet", 'SUCCESS');
        
        // Telegram-spezifische Statistiken loggen
        if ($systemStatus['telegram_ready'] && isset($result['telegram_sent']) && $result['telegram_sent'] > 0) {
            cronLog("📱 Telegram-Nachrichten: {$result['telegram_sent']}", 'SUCCESS');
        }
    }
    
    if ($result['failed'] > 0) {
        cronLog("⚠️  {$result['failed']} Erinnerungen fehlgeschlagen", 'WARNING');
    }
    
    if ($result['total'] === 0) {
        cronLog("ℹ️  Keine Erinnerungen fällig", 'INFO');
    }
    
    // Performance-Statistiken
    $endTime = microtime(true);
    $executionTime = isset($startTime) ? round($endTime - $startTime, 2) : 0;
    cronLog("Ausführungszeit: {$executionTime}s", 'INFO');
    
    cronLog("=== Reminder-Job beendet ===\n");
    exit(0);
    
} catch (Exception $e) {
    cronLog("FATAL ERROR: " . $e->getMessage(), 'ERROR');
    cronLog("Stacktrace: " . $e->getTraceAsString(), 'ERROR');
    
    // Optional: Admin-Benachrichtigung bei kritischen Fehlern
    try {
        $adminEmail = 'admin@domain.de'; // Anpassen!
        $subject = 'Stromtracker Reminder-Job Fehler';
        $message = "Der automatische Reminder-Job ist fehlgeschlagen:\n\n" . $e->getMessage();
        @mail($adminEmail, $subject, $message);
    } catch (Exception $mailError) {
        cronLog("Admin-E-Mail konnte nicht gesendet werden: " . $mailError->getMessage(), 'ERROR');
    }
    
    exit(1);
}
