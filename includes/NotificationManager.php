<?php
// includes/NotificationManager.php
// Intelligente Erinnerungen für Zählerstand-Ablesungen mit Telegram-Unterstützung

require_once __DIR__ . '/TelegramManager.php';

class NotificationManager {
    
    /**
     * Benutzer-Benachrichtigungseinstellungen laden (mit Telegram)
     */
    public static function getUserSettings($userId) {
        return Database::fetchOne(
            "SELECT * FROM notification_settings WHERE user_id = ?",
            [$userId]
        ) ?: [
            'user_id' => $userId,
            'email_notifications' => true,
            'reading_reminder_enabled' => true,
            'reading_reminder_days' => 5,
            'high_usage_alert' => false,
            'high_usage_threshold' => 200.00,
            'cost_alert_enabled' => false,
            'cost_alert_threshold' => 100.00,
            'telegram_enabled' => false,
            'telegram_chat_id' => null,
            'telegram_verified' => false
        ];
    }
    
    /**
     * Benachrichtigungseinstellungen speichern/aktualisieren (mit Telegram)
     */
    public static function saveUserSettings($userId, $settings) {
        // Prüfen ob bereits Einstellungen existieren
        $existing = Database::fetchOne(
            "SELECT id FROM notification_settings WHERE user_id = ?",
            [$userId]
        );
        
        $data = [
            'email_notifications' => (bool)($settings['email_notifications'] ?? true),
            'reading_reminder_enabled' => (bool)($settings['reading_reminder_enabled'] ?? true),
            'reading_reminder_days' => (int)($settings['reading_reminder_days'] ?? 5),
            'high_usage_alert' => (bool)($settings['high_usage_alert'] ?? false),
            'high_usage_threshold' => (float)($settings['high_usage_threshold'] ?? 200.00),
            'cost_alert_enabled' => (bool)($settings['cost_alert_enabled'] ?? false),
            'cost_alert_threshold' => (float)($settings['cost_alert_threshold'] ?? 100.00)
        ];
        
        // Telegram-Einstellungen nur hinzufügen wenn übergeben
        if (isset($settings['telegram_enabled'])) {
            $data['telegram_enabled'] = (bool)$settings['telegram_enabled'];
        }
        if (isset($settings['telegram_chat_id'])) {
            $data['telegram_chat_id'] = $settings['telegram_chat_id'];
            $data['telegram_verified'] = false; // Muss neu verifiziert werden
        }
        
        if ($existing) {
            // Update
            return Database::update(
                'notification_settings', 
                $data,
                'user_id = ?',
                [$userId]
            );
        } else {
            // Insert
            $data['user_id'] = $userId;
            return Database::insert('notification_settings', $data);
        }
    }
    
    /**
     * Prüfen ob Benutzer eine Zählerstand-Erinnerung benötigt
     */
    public static function needsReadingReminder($userId) {
        $settings = self::getUserSettings($userId);
        
        if (!$settings['reading_reminder_enabled']) {
            return ['needed' => false];
        }
        
        // Letzten Zählerstand holen
        $lastReading = Database::fetchOne(
            "SELECT reading_date FROM meter_readings 
             WHERE user_id = ? 
             ORDER BY reading_date DESC LIMIT 1",
            [$userId]
        );
        
        if (!$lastReading) {
            // Noch nie abgelesen - Erinnerung senden
            return [
                'needed' => true,
                'reason' => 'first_reading',
                'message' => 'Sie haben noch keinen Zählerstand erfasst.'
            ];
        }
        
        $lastDate = $lastReading['reading_date'];
        $daysSince = (strtotime('today') - strtotime($lastDate)) / (24 * 60 * 60);
        
        // Aktueller Monat
        $currentMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime($lastDate));
        
        // Wenn letzter Eintrag nicht aus diesem Monat ist
        if ($lastMonth < $currentMonth) {
            $reminderDays = (int)$settings['reading_reminder_days'];
            $daysInMonth = date('t'); // Tage im aktuellen Monat
            $reminderDate = $daysInMonth - $reminderDays + 1;
            
            // Erinnerung ab dem X-letzten Tag des Monats
            if (date('j') >= $reminderDate) {
                return [
                    'needed' => true,
                    'reason' => 'monthly_reminder',
                    'message' => "Ihr letzter Zählerstand ist vom " . 
                                date('d.m.Y', strtotime($lastDate)) . 
                                " (vor " . round($daysSince) . " Tagen).",
                    'suggested_date' => date('Y-m-01'), // Erster des aktuellen Monats
                    'days_since' => round($daysSince)
                ];
            }
        }
        
        return ['needed' => false];
    }
    
    /**
     * Alle Benutzer finden die eine Erinnerung benötigen (mit Telegram)
     */
    public static function findUsersNeedingReminders() {
        $users = Database::fetchAll(
            "SELECT u.id, u.name, u.email, ns.* 
             FROM users u
             JOIN notification_settings ns ON u.id = ns.user_id
             WHERE ns.reading_reminder_enabled = 1 
             AND (ns.email_notifications = 1 OR (ns.telegram_enabled = 1 AND ns.telegram_verified = 1))"
        ) ?: [];
        
        $reminders = [];
        
        foreach ($users as $user) {
            $reminder = self::needsReadingReminder($user['id']);
            if ($reminder['needed']) {
                $reminders[] = [
                    'user' => $user,
                    'reminder' => $reminder
                ];
            }
        }
        
        return $reminders;
    }
    
    /**
     * Erinnerung per E-Mail senden
     */
    public static function sendReminderEmail($userEmail, $userName, $reminderData) {
        $subject = "🔌 Stromtracker: Zählerstand-Erinnerung";
        
        $message = "Hallo " . ($userName ?: "Stromtracker-Nutzer") . ",\n\n";
        $message .= $reminderData['message'] . "\n\n";
        $message .= "📊 Bitte erfassen Sie Ihren aktuellen Zählerstand:\n";
        $message .= "- Öffnen Sie Stromtracker\n";
        $message .= "- Gehen Sie auf 'Zählerstand erfassen'\n";
        $message .= "- Tragen Sie den aktuellen Wert ein\n\n";
        
        if (isset($reminderData['suggested_date'])) {
            $message .= "💡 Vorgeschlagenes Datum: " . 
                       date('d.m.Y', strtotime($reminderData['suggested_date'])) . "\n\n";
        }
        
        $message .= "Mit freundlichen Grüßen\n";
        $message .= "Ihr Stromtracker";
        
        // Einfacher E-Mail-Versand (kann später erweitert werden)
        $headers = "From: noreply@stromtracker.local\r\n";
        $headers .= "Reply-To: noreply@stromtracker.local\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        $sent = mail($userEmail, $subject, $message, $headers);
        
        // Log-Eintrag erstellen
        self::logNotification(
            $userEmail, 
            'reading_reminder',
            $subject,
            $message,
            $sent ? 'sent' : 'failed'
        );
        
        return $sent;
    }
    
    /**
     * Erinnerung per Telegram senden
     */
    public static function sendReminderTelegram($chatId, $userName, $reminderData) {
        if (!TelegramManager::isEnabled()) {
            return false;
        }
        
        try {
            return TelegramManager::sendReadingReminder($chatId, $userName, $reminderData);
        } catch (Exception $e) {
            error_log("Telegram reminder send error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Benachrichtigung in Datenbank loggen
     */
    public static function logNotification($userIdentifier, $type, $subject, $message, $status = 'pending') {
        // User-ID ermitteln (falls E-Mail übergeben wurde)
        $userId = $userIdentifier;
        if (!is_numeric($userIdentifier)) {
            $user = Database::fetchOne("SELECT id FROM users WHERE email = ?", [$userIdentifier]);
            $userId = $user['id'] ?? null;
        }
        
        if (!$userId) return false;
        
        return Database::insert('notification_log', [
            'user_id' => $userId,
            'notification_type' => $type,
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null
        ]);
    }
    
    /**
     * Erinnerungs-Status für Benutzer setzen
     */
    public static function markReminderSent($userId) {
        return Database::update(
            'notification_settings',
            [
                'reading_reminder_sent' => true,
                'last_reminder_date' => date('Y-m-d')
            ],
            'user_id = ?',
            [$userId]
        );
    }
    
    /**
     * Alle fälligen Erinnerungen verarbeiten (für Cron-Job) - mit Telegram-Support
     */
    public static function processPendingReminders() {
        $reminders = self::findUsersNeedingReminders();
        $emailSent = 0;
        $telegramSent = 0;
        $failed = 0;
        
        foreach ($reminders as $item) {
            $user = $item['user'];
            $reminder = $item['reminder'];
            $userSuccess = false;
            
            try {
                // E-Mail senden falls aktiviert
                if ($user['email_notifications']) {
                    $emailSuccess = self::sendReminderEmail(
                        $user['email'],
                        $user['name'],
                        $reminder
                    );
                    
                    if ($emailSuccess) {
                        $emailSent++;
                        $userSuccess = true;
                    }
                }
                
                // Telegram senden falls aktiviert und verifiziert
                if ($user['telegram_enabled'] && $user['telegram_verified'] && $user['telegram_chat_id']) {
                    $telegramSuccess = self::sendReminderTelegram(
                        $user['telegram_chat_id'],
                        $user['name'],
                        $reminder
                    );
                    
                    if ($telegramSuccess) {
                        $telegramSent++;
                        $userSuccess = true;
                    }
                }
                
                // Als gesendet markieren wenn mindestens ein Kanal erfolgreich war
                if ($userSuccess) {
                    self::markReminderSent($user['id']);
                } else {
                    $failed++;
                }
                
            } catch (Exception $e) {
                error_log("Reminder send error for user {$user['id']}: " . $e->getMessage());
                $failed++;
            }
        }
        
        return [
            'sent' => $emailSent + $telegramSent,
            'email_sent' => $emailSent,
            'telegram_sent' => $telegramSent,
            'failed' => $failed,
            'total' => count($reminders)
        ];
    }
    
    /**
     * Telegram Chat-ID Verifizierung
     */
    public static function initiateTelegramVerification($userId, $chatId) {
        if (!TelegramManager::isEnabled()) {
            throw new Exception('Telegram ist nicht aktiviert');
        }
        
        // Chat-ID validieren
        if (!TelegramManager::validateChatId($chatId)) {
            throw new Exception('Ungültige Chat-ID oder Bot wurde nicht gestartet');
        }
        
        // Verifizierungscode generieren
        $verificationCode = TelegramManager::generateVerificationCode();
        
        // Code in Session/Cache speichern (hier vereinfacht in Datenbank)
        self::saveVerificationCode($userId, $verificationCode);
        
        // Chat-ID speichern (noch nicht verifiziert)
        TelegramManager::saveUserTelegramSettings($userId, $chatId, false);
        
        // Verifizierungsnachricht senden
        $sent = TelegramManager::sendVerificationCode($chatId, $verificationCode);
        
        if (!$sent) {
            throw new Exception('Verifizierungsnachricht konnte nicht gesendet werden');
        }
        
        return true;
    }
    
    /**
     * Telegram Verifizierungscode prüfen
     */
    public static function verifyTelegramCode($userId, $providedCode) {
        $storedCode = self::getVerificationCode($userId);
        
        if (!$storedCode || $providedCode !== $storedCode) {
            return false;
        }
        
        // Code als verwendet markieren
        self::clearVerificationCode($userId);
        
        // Chat-ID als verifiziert markieren
        TelegramManager::markChatIdVerified($userId);
        
        return true;
    }
    
    /**
     * Verifizierungscode speichern (vereinfacht)
     */
    private static function saveVerificationCode($userId, $code) {
        // In echtem System: Redis/Memcached mit Ablaufzeit
        // Hier: Einfache Datenbankspeisung mit execute
        Database::execute(
            "INSERT INTO telegram_log (user_id, chat_id, message_type, message_text, status) 
             VALUES (?, 'VERIFICATION', 'verification', ?, 'pending')
             ON DUPLICATE KEY UPDATE message_text = VALUES(message_text)",
            [$userId, $code]
        );
    }
    
    /**
     * Verifizierungscode abrufen
     */
    private static function getVerificationCode($userId) {
        $result = Database::fetchOne(
            "SELECT message_text FROM telegram_log 
             WHERE user_id = ? AND message_type = 'verification' AND status = 'pending' 
             ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );
        
        return $result ? $result['message_text'] : null;
    }
    
    /**
     * Verifizierungscode löschen
     */
    private static function clearVerificationCode($userId) {
        Database::update(
            'telegram_log',
            ['status' => 'used'],
            'user_id = ? AND message_type = ? AND status = ?',
            [$userId, 'verification', 'pending']
        );
    }
    
    /**
     * Benutzerstatistiken für Dashboard (mit Telegram)
     */
    public static function getNotificationStats($userId) {
        // Letzte Benachrichtigungen (E-Mail + Telegram)
        $emailNotifications = Database::fetchAll(
            "SELECT *, 'email' as channel FROM notification_log 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT 5",
            [$userId]
        ) ?: [];
        
        $telegramNotifications = Database::fetchAll(
            "SELECT 
                id, user_id, message_type as notification_type, 
                message_text as subject, message_text as message, 
                status, created_at, 'telegram' as channel
             FROM telegram_log 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT 5",
            [$userId]
        ) ?: [];
        
        // Alle Benachrichtigungen zusammenfassen und sortieren
        $allNotifications = array_merge($emailNotifications, $telegramNotifications);
        usort($allNotifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        $recentNotifications = array_slice($allNotifications, 0, 5);
        
        // Zähler
        $emailCounts = Database::fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM notification_log 
             WHERE user_id = ?",
            [$userId]
        ) ?: ['total' => 0, 'sent' => 0, 'failed' => 0];
        
        $telegramCounts = Database::fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM telegram_log 
             WHERE user_id = ?",
            [$userId]
        ) ?: ['total' => 0, 'sent' => 0, 'failed' => 0];
        
        $totalCounts = [
            'total' => $emailCounts['total'] + $telegramCounts['total'],
            'sent' => $emailCounts['sent'] + $telegramCounts['sent'],
            'failed' => $emailCounts['failed'] + $telegramCounts['failed'],
            'email' => $emailCounts,
            'telegram' => $telegramCounts
        ];
        
        return [
            'recent_notifications' => $recentNotifications,
            'counts' => $totalCounts,
            'settings' => self::getUserSettings($userId),
            'telegram_enabled' => TelegramManager::isEnabled()
        ];
    }
}
