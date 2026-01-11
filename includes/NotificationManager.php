<?php
// includes/NotificationManager.php
// Intelligente Erinnerungen mit E-Mail (SMTP) und Telegram

require_once __DIR__ . '/TelegramManager.php';

class NotificationManager {
    
    /**
     * Benutzer-Benachrichtigungseinstellungen laden (mit SMTP)
     */
    public static function getUserSettings($userId) {
        return Database::fetchOne(
            "SELECT * FROM notification_settings WHERE user_id = ?",
            [$userId]
        ) ?: [
            'user_id' => $userId,
            'email_notifications' => true,
            'smtp_enabled' => false,
            'smtp_host' => null,
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => null,
            'smtp_password' => null,
            'smtp_from_email' => null,
            'smtp_from_name' => 'Stromtracker',
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
     * Benachrichtigungseinstellungen speichern (mit SMTP)
     */
    public static function saveUserSettings($userId, $settings) {
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
        
        // SMTP-Einstellungen
        if (isset($settings['smtp_enabled'])) {
            $data['smtp_enabled'] = (bool)$settings['smtp_enabled'];
        }
        if (isset($settings['smtp_host'])) {
            $data['smtp_host'] = trim($settings['smtp_host']) ?: null;
        }
        if (isset($settings['smtp_port'])) {
            $data['smtp_port'] = max(1, min(65535, (int)$settings['smtp_port']));
        }
        if (isset($settings['smtp_encryption'])) {
            $encryption = strtolower(trim($settings['smtp_encryption']));
            $data['smtp_encryption'] = in_array($encryption, ['tls', 'ssl', 'none']) ? $encryption : 'tls';
        }
        if (isset($settings['smtp_username'])) {
            $data['smtp_username'] = trim($settings['smtp_username']) ?: null;
        }
        if (isset($settings['smtp_password'])) {
            // Passwort nur speichern wenn neu eingegeben
            $newPassword = trim($settings['smtp_password']);
            if (!empty($newPassword) && $newPassword !== '********') {
                $data['smtp_password'] = $newPassword;
            }
        }
        if (isset($settings['smtp_from_email'])) {
            $data['smtp_from_email'] = trim($settings['smtp_from_email']) ?: null;
        }
        if (isset($settings['smtp_from_name'])) {
            $data['smtp_from_name'] = trim($settings['smtp_from_name']) ?: 'Stromtracker';
        }
        
        // Telegram-Einstellungen
        if (isset($settings['telegram_enabled'])) {
            $data['telegram_enabled'] = (bool)$settings['telegram_enabled'];
        }
        if (isset($settings['telegram_chat_id'])) {
            $data['telegram_chat_id'] = $settings['telegram_chat_id'];
            $data['telegram_verified'] = false;
        }
        
        if ($existing) {
            return Database::update('notification_settings', $data, 'user_id = ?', [$userId]);
        } else {
            $data['user_id'] = $userId;
            return Database::insert('notification_settings', $data);
        }
    }
    
    /**
     * Prüfen ob PHPMailer verfügbar ist
     */
    public static function isPHPMailerAvailable() {
        return class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    
    /**
     * E-Mail mit SMTP senden (PHPMailer)
     */
    private static function sendViaSMTP($to, $subject, $body, $smtpConfig) {
        if (!self::isPHPMailerAvailable()) {
            error_log("PHPMailer not available. Install via: composer require phpmailer/phpmailer");
            return false;
        }
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // SMTP Konfiguration
            $mail->isSMTP();
            $mail->Host = $smtpConfig['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtpConfig['smtp_username'];
            $mail->Password = $smtpConfig['smtp_password'];
            $mail->Port = $smtpConfig['smtp_port'];
            
            // Verschlüsselung
            if ($smtpConfig['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtpConfig['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Absender
            $fromEmail = $smtpConfig['smtp_from_email'] ?: $smtpConfig['smtp_username'];
            $fromName = $smtpConfig['smtp_from_name'] ?: 'Stromtracker';
            $mail->setFrom($fromEmail, $fromName);
            
            // Empfänger
            $mail->addAddress($to);
            
            // Inhalt
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            // Debugging (optional)
            // $mail->SMTPDebug = 2;
            // $mail->Debugoutput = 'error_log';
            
            $mail->send();
            return true;
            
        } catch (\Exception $e) {
            error_log("SMTP send error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * E-Mail mit PHP mail() senden (Fallback)
     */
    private static function sendViaMail($to, $subject, $body, $fromEmail = null, $fromName = null) {
        if (!function_exists('mail')) {
            error_log("PHP mail() function not available");
            return false;
        }
        
        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $fromEmail = $fromEmail ?: "noreply@{$serverName}";
        $fromName = $fromName ?: 'Stromtracker';
        
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        $additionalParams = "-f{$fromEmail}";
        
        return @mail($to, $subject, $body, $headers, $additionalParams);
    }
    
    /**
     * SMTP-Konfiguration testen
     */
    public static function testSMTPConnection($userId) {
        $settings = self::getUserSettings($userId);
        
        if (!$settings['smtp_enabled']) {
            return [
                'success' => false,
                'error' => 'SMTP ist nicht aktiviert'
            ];
        }
        
        if (empty($settings['smtp_host']) || empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
            return [
                'success' => false,
                'error' => 'SMTP-Einstellungen unvollständig'
            ];
        }
        
        if (!self::isPHPMailerAvailable()) {
            return [
                'success' => false,
                'error' => 'PHPMailer nicht installiert. Führe aus: composer require phpmailer/phpmailer'
            ];
        }
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_username'];
            $mail->Password = $settings['smtp_password'];
            $mail->Port = $settings['smtp_port'];
            
            if ($settings['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($settings['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Verbindungstest ohne E-Mail zu senden
            $mail->Timeout = 10;
            $mail->SMTPDebug = 0;
            
            // Versuche zu verbinden
            if ($mail->smtpConnect()) {
                $mail->smtpClose();
                return [
                    'success' => true,
                    'message' => 'SMTP-Verbindung erfolgreich!'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'SMTP-Verbindung fehlgeschlagen'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Erinnerung per E-Mail senden (mit SMTP-Support)
     */
    public static function sendReminderEmail($userEmail, $userName, $reminderData, $userId = null) {
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
        
        $sent = false;
        $errorMessage = null;
        
        try {
            // SMTP-Einstellungen des Benutzers laden
            if ($userId) {
                $settings = self::getUserSettings($userId);
                
                if ($settings['smtp_enabled'] && !empty($settings['smtp_host']) && self::isPHPMailerAvailable()) {
                    // Via SMTP senden
                    $sent = self::sendViaSMTP($userEmail, $subject, $message, $settings);
                    $method = 'smtp';
                } else {
                    // Fallback auf mail()
                    $fromEmail = $settings['smtp_from_email'] ?? null;
                    $fromName = $settings['smtp_from_name'] ?? null;
                    $sent = self::sendViaMail($userEmail, $subject, $message, $fromEmail, $fromName);
                    $method = 'mail';
                }
            } else {
                // Kein User-ID, nutze mail()
                $sent = self::sendViaMail($userEmail, $subject, $message);
                $method = 'mail';
            }
            
            if (!$sent) {
                $errorMessage = "E-Mail-Versand via {$method} fehlgeschlagen";
            }
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
        
        // Logging
        self::logNotification(
            $userEmail,
            'reading_reminder',
            $subject,
            $message,
            $sent ? 'sent' : 'failed',
            $errorMessage
        );
        
        if (!$sent && $errorMessage) {
            error_log("E-Mail-Versand fehlgeschlagen an {$userEmail}: {$errorMessage}");
        }
        
        return $sent;
    }
    
    /**
     * Test-E-Mail senden
     */
    public static function sendTestEmail($userEmail, $userName, $userId) {
        $subject = "🔌 Stromtracker: Test-E-Mail";
        
        $message = "Hallo" . ($userName ? " {$userName}" : "") . ",\n\n";
        $message .= "Dies ist eine Test-E-Mail von Stromtracker.\n\n";
        
        $settings = self::getUserSettings($userId);
        $method = 'unknown';
        
        if ($settings['smtp_enabled'] && !empty($settings['smtp_host'])) {
            if (self::isPHPMailerAvailable()) {
                $message .= "Versandmethode: SMTP\n";
                $message .= "SMTP Server: " . $settings['smtp_host'] . ":" . $settings['smtp_port'] . "\n";
                $message .= "Verschlüsselung: " . strtoupper($settings['smtp_encryption']) . "\n";
                $method = 'smtp';
            } else {
                $message .= "⚠️ PHPMailer nicht installiert - Fallback auf mail()\n";
                $method = 'mail';
            }
        } else {
            $message .= "Versandmethode: PHP mail()\n";
            $method = 'mail';
        }
        
        $message .= "\nServer-Informationen:\n";
        $message .= "- PHP Version: " . phpversion() . "\n";
        $message .= "- Server: " . ($_SERVER['SERVER_NAME'] ?? 'unbekannt') . "\n";
        $message .= "- Zeit: " . date('d.m.Y H:i:s') . "\n\n";
        $message .= "Wenn Sie diese E-Mail erhalten, funktioniert der E-Mail-Versand!\n\n";
        $message .= "Mit freundlichen Grüßen\n";
        $message .= "Ihr Stromtracker";
        
        $sent = false;
        $errorMessage = null;
        
        try {
            if ($method === 'smtp') {
                $sent = self::sendViaSMTP($userEmail, $subject, $message, $settings);
            } else {
                $fromEmail = $settings['smtp_from_email'] ?? null;
                $fromName = $settings['smtp_from_name'] ?? null;
                $sent = self::sendViaMail($userEmail, $subject, $message, $fromEmail, $fromName);
            }
            
            if (!$sent) {
                $errorMessage = "E-Mail-Versand via {$method} fehlgeschlagen";
            }
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
        
        self::logNotification(
            $userEmail,
            'test_email',
            $subject,
            $message,
            $sent ? 'sent' : 'failed',
            $errorMessage
        );
        
        return [
            'success' => $sent,
            'method' => $method,
            'error' => $errorMessage,
            'info' => [
                'smtp_enabled' => $settings['smtp_enabled'],
                'smtp_configured' => !empty($settings['smtp_host']),
                'phpmailer_available' => self::isPHPMailerAvailable(),
                'mail_function_exists' => function_exists('mail'),
                'php_version' => phpversion()
            ]
        ];
    }
    
    // ... Rest der Klasse bleibt gleich ...
    
    /**
     * Prüfen ob Benutzer eine Zählerstand-Erinnerung benötigt
     */
    public static function needsReadingReminder($userId) {
        $settings = self::getUserSettings($userId);
        
        if (!$settings['reading_reminder_enabled']) {
            return ['needed' => false];
        }
        
        $lastReading = Database::fetchOne(
            "SELECT reading_date FROM meter_readings 
             WHERE user_id = ? 
             ORDER BY reading_date DESC LIMIT 1",
            [$userId]
        );
        
        if (!$lastReading) {
            return [
                'needed' => true,
                'reason' => 'first_reading',
                'message' => 'Sie haben noch keinen Zählerstand erfasst.'
            ];
        }
        
        $lastDate = $lastReading['reading_date'];
        $daysSince = (strtotime('today') - strtotime($lastDate)) / (24 * 60 * 60);
        
        $currentMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime($lastDate));
        
        if ($lastMonth < $currentMonth) {
            $reminderDays = (int)$settings['reading_reminder_days'];
            $daysInMonth = date('t');
            $reminderDate = $daysInMonth - $reminderDays + 1;
            
            if (date('j') >= $reminderDate) {
                return [
                    'needed' => true,
                    'reason' => 'monthly_reminder',
                    'message' => "Ihr letzter Zählerstand ist vom " . 
                                date('d.m.Y', strtotime($lastDate)) . 
                                " (vor " . round($daysSince) . " Tagen).",
                    'suggested_date' => date('Y-m-01'),
                    'days_since' => round($daysSince)
                ];
            }
        }
        
        return ['needed' => false];
    }
    
    /**
     * Alle Benutzer finden die eine Erinnerung benötigen
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
    public static function logNotification($userIdentifier, $type, $subject, $message, $status = 'pending', $errorMessage = null) {
        $userId = $userIdentifier;
        if (!is_numeric($userIdentifier)) {
            $user = Database::fetchOne("SELECT id FROM users WHERE email = ?", [$userIdentifier]);
            $userId = $user ? $user['id'] : null;
        }
        
        if (!$userId) {
            error_log("Cannot log notification: User not found for identifier: {$userIdentifier}");
            return false;
        }
        
        try {
            $logData = [
                'user_id' => $userId,
                'notification_type' => $type,
                'subject' => $subject,
                'message' => $message,
                'status' => $status
            ];
            
            if ($errorMessage && $status === 'failed') {
                $logData['message'] = $message . "\n\n[ERROR: " . $errorMessage . "]";
            }
            
            return Database::insert('notification_log', $logData);
        } catch (Exception $e) {
            error_log("Notification logging error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Letzte Erinnerung als gesendet markieren
     */
    public static function markReminderSent($userId) {
        try {
            return Database::update(
                'notification_settings',
                ['last_reminder_date' => date('Y-m-d H:i:s')],
                'user_id = ?',
                [$userId]
            );
        } catch (Exception $e) {
            error_log("Mark reminder sent error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Erinnerungen an alle berechtigten Benutzer senden
     */
    public static function sendAllReminders() {
        $reminders = self::findUsersNeedingReminders();
        $emailSent = 0;
        $telegramSent = 0;
        $failed = 0;
        
        foreach ($reminders as $item) {
            $user = $item['user'];
            $reminder = $item['reminder'];
            $userSuccess = false;
            
            try {
                if ($user['email_notifications']) {
                    $emailSuccess = self::sendReminderEmail(
                        $user['email'],
                        $user['name'],
                        $reminder,
                        $user['id'] // User-ID für SMTP-Settings
                    );
                    
                    if ($emailSuccess) {
                        $emailSent++;
                        $userSuccess = true;
                    }
                }
                
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
     * Benutzerstatistiken für Dashboard
     */
    public static function getNotificationStats($userId) {
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
        
        $allNotifications = array_merge($emailNotifications, $telegramNotifications);
        usort($allNotifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        $recentNotifications = array_slice($allNotifications, 0, 5);
        
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
            'pending' => 0,
            'email' => $emailCounts,
            'telegram' => $telegramCounts
        ];
        
        $lastNotification = null;
        if (!empty($allNotifications)) {
            $lastNotification = $allNotifications[0]['created_at'];
        }
        
        return [
            'recent_notifications' => $recentNotifications,
            'counts' => $totalCounts,
            'last_notification' => $lastNotification,
            'settings' => self::getUserSettings($userId),
            'telegram_enabled' => TelegramManager::isEnabled()
        ];
    }
    
    // Telegram-Methoden bleiben unverändert...
    public static function initiateTelegramVerification($userId, $chatId) { /* ... */ }
    public static function verifyTelegramCode($userId, $providedCode) { /* ... */ }
    public static function saveVerificationCode($userId, $code) { /* ... */ }
    public static function getVerificationCode($userId) { /* ... */ }
    public static function clearVerificationCode($userId) { /* ... */ }
}