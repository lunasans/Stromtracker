<?php
// includes/TelegramManager.php
// Benutzerbezogene Telegram Bot Integration - Jeder User kann seinen eigenen Bot verwenden

class TelegramManager {
    
    /**
     * Konvertiert boolean-Werte zu Integern für MySQL-Kompatibilität
     */
    private static function prepareDatabaseData($data) {
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $data[$key] = $value ? 1 : 0;
            }
        }
        return $data;
    }
    
    /**
     * Prüfen ob Telegram allgemein verfügbar ist (Alias für isSystemEnabled)
     */
    public static function isEnabled() {
        return self::isSystemEnabled();
    }
    
    /**
     * Prüfen ob Telegram-System global aktiviert ist
     */
    public static function isSystemEnabled() {
        try {
            // Prüfen ob telegram_system Tabelle existiert
            $systemTable = Database::fetchOne("SHOW TABLES LIKE 'telegram_system'");
            if (!$systemTable) {
                return true; // Standard: aktiviert, wenn Tabelle nicht existiert
            }
            
            $setting = Database::fetchOne(
                "SELECT setting_value FROM telegram_system WHERE setting_key = 'system_enabled'"
            );
            
            return ($setting['setting_value'] ?? '1') === '1';
            
        } catch (Exception $e) {
            error_log("Telegram system check error: " . $e->getMessage());
            return true; // Default: aktiviert
        }
    }
    
    /**
     * Prüfen ob Benutzer einen konfigurierten Bot hat
     */
    public static function isUserBotReady($userId) {
        if (!self::isSystemEnabled()) {
            return false;
        }
        
        try {
            $settings = Database::fetchOne(
                "SELECT telegram_bot_token, telegram_chat_id, telegram_verified 
                 FROM notification_settings 
                 WHERE user_id = ?",
                [$userId]
            );
            
            return $settings && 
                   !empty($settings['telegram_bot_token']) && 
                   $settings['telegram_bot_token'] !== 'demo' &&
                   !empty($settings['telegram_chat_id']) &&
                   $settings['telegram_verified'];
                   
        } catch (Exception $e) {
            error_log("User bot check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Bot-Token für Benutzer speichern (mit Debug-Logging)
     */
    public static function saveUserBot($userId, $botToken, $botUsername = null) {
        try {
            error_log("[TelegramManager] saveUserBot started - User: $userId, Token: " . substr($botToken, 0, 10) . "...");
            
            // Bot-Token validieren
            if (!self::validateBotToken($botToken)) {
                error_log("[TelegramManager] Token validation failed");
                throw new Exception('Ungültiger Bot-Token');
            }
            error_log("[TelegramManager] Token validation passed");
            
            // Bot-Username ermitteln falls nicht übergeben
            if (!$botUsername && $botToken !== 'demo') {
                $botInfo = self::getBotInfoFromToken($botToken);
                if ($botInfo) {
                    $botUsername = $botInfo['username'] ?? null;
                    error_log("[TelegramManager] Bot username retrieved: $botUsername");
                }
            }
            
            // Bestehende Einstellungen prüfen
            $existing = Database::fetchOne(
                "SELECT id FROM notification_settings WHERE user_id = ?",
                [$userId]
            );
            
            error_log("[TelegramManager] Existing record: " . ($existing ? "ID " . $existing['id'] : "none"));
            
            $data = [
                'telegram_bot_token' => $botToken,
                'telegram_verified' => 0,    // Explizit als Integer statt false
                'telegram_chat_id' => ''     // Leerer String statt null
            ];
            
            // Bot-Username nur setzen wenn vorhanden
            if ($botUsername) {
                $data['telegram_bot_username'] = $botUsername;
            }
            
            if ($existing) {
                // Update bestehender Eintrag
                error_log("[TelegramManager] Performing UPDATE...");
                $result = Database::update(
                    'notification_settings',
                    self::prepareDatabaseData($data),  // Boolean-Konvertierung
                    'user_id = ?',
                    [$userId]
                );
                error_log("[TelegramManager] UPDATE result: " . ($result ? 'true' : 'false'));
            } else {
                // Neuen Eintrag erstellen
                error_log("[TelegramManager] Performing INSERT...");
                $data['user_id'] = $userId;
                
                // Standardwerte für alle Felder setzen (mit Integer-Konvertierung)
                $data['email_notifications'] = 1;          // true → 1
                $data['reading_reminder_enabled'] = 1;     // true → 1
                $data['reading_reminder_days'] = 5;
                $data['high_usage_alert'] = 0;             // false → 0
                $data['high_usage_threshold'] = 200.00;
                $data['cost_alert_enabled'] = 0;          // false → 0
                $data['cost_alert_threshold'] = 100.00;
                $data['telegram_enabled'] = 0;            // false → 0
                
                $result = Database::insert('notification_settings', self::prepareDatabaseData($data));
                error_log("[TelegramManager] INSERT result: " . ($result ? 'true' : 'false'));
            }
            
            // Zusätzliche Verifikation
            if ($result) {
                $verification = Database::fetchOne(
                    "SELECT telegram_bot_token FROM notification_settings WHERE user_id = ?",
                    [$userId]
                );
                
                if ($verification && $verification['telegram_bot_token'] === $botToken) {
                    error_log("[TelegramManager] Verification passed - token correctly saved");
                } else {
                    error_log("[TelegramManager] Verification FAILED - token not found or incorrect");
                    throw new Exception('Token wurde nicht korrekt gespeichert');
                }
            } else {
                error_log("[TelegramManager] Database operation returned false");
                throw new Exception('Datenbankfehler beim Speichern');
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[TelegramManager] SaveUserBot error for user {$userId}: " . $e->getMessage());
            throw new Exception('Bot-Token konnte nicht gespeichert werden: ' . $e->getMessage());
        }
    }
    
    /**
     * Bot-Informationen direkt vom Token abrufen
     */
    private static function getBotInfoFromToken($botToken) {
        if ($botToken === 'demo') {
            return [
                'id' => 'demo',
                'username' => 'demo_bot',
                'first_name' => 'Demo Bot',
                'is_demo' => true
            ];
        }
        
        try {
            $response = @file_get_contents(
                "https://api.telegram.org/bot{$botToken}/getMe",
                false,
                stream_context_create(['http' => ['timeout' => 5]])
            );
            
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            if (isset($data['ok']) && $data['ok']) {
                return $data['result'];
            }
            
        } catch (Exception $e) {
            error_log("Bot info from token error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Bot-Token Format validieren
     */
    public static function validateBotToken($token) {
        if (empty($token)) {
            return false;
        }
        
        // Demo-Token
        if ($token === 'demo') {
            return true;
        }
        
        // Echtes Token-Format: 123456789:ABCdefGHijKLmnopQRstuvwxyz
        return preg_match('/^[0-9]{8,10}:[A-Za-z0-9_-]{35}$/', $token);
    }
    
    /**
     * Bot-Token gegen Telegram API validieren
     */
    public static function validateBotTokenAPI($botToken) {
        if (!self::validateBotToken($botToken) || $botToken === 'demo') {
            return false;
        }
        
        try {
            $response = @file_get_contents(
                "https://api.telegram.org/bot{$botToken}/getMe",
                false,
                stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 10
                    ]
                ])
            );
            
            if ($response === false) {
                return false;
            }
            
            $data = json_decode($response, true);
            return isset($data['ok']) && $data['ok'];
            
        } catch (Exception $e) {
            error_log("Bot token validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Bot-Informationen abrufen (allgemein/system)
     */
    public static function getBotInfo() {
        // Für System-weite Bot-Informationen - kann später erweitert werden
        // Momentan einfache Fallback-Informationen
        return [
            'id' => 'system',
            'username' => 'stromtracker_bot',
            'first_name' => 'Stromtracker Bot',
            'is_system' => true
        ];
    }
    
    /**
     * Bot-Informationen für spezifischen Benutzer abrufen
     */
    public static function getUserBotInfo($userId) {
        $settings = Database::fetchOne(
            "SELECT telegram_bot_token, telegram_bot_username 
             FROM notification_settings 
             WHERE user_id = ?",
            [$userId]
        );
        
        if (!$settings || empty($settings['telegram_bot_token'])) {
            return null;
        }
        
        $botToken = $settings['telegram_bot_token'];
        
        if ($botToken === 'demo') {
            return [
                'id' => 'demo',
                'username' => 'demo_bot',
                'first_name' => 'Demo Bot',
                'is_demo' => true
            ];
        }
        
        try {
            $response = @file_get_contents(
                "https://api.telegram.org/bot{$botToken}/getMe",
                false,
                stream_context_create(['http' => ['timeout' => 5]])
            );
            
            if ($response === false) {
                return null;
            }
            
            $data = json_decode($response, true);
            if (isset($data['ok']) && $data['ok']) {
                return $data['result'];
            }
            
        } catch (Exception $e) {
            error_log("Bot info error for user {$userId}: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Telegram API Anfrage für spezifischen Benutzer
     */
    private static function userApiRequest($userId, $method, $parameters = []) {
        $settings = Database::fetchOne(
            "SELECT telegram_bot_token FROM notification_settings WHERE user_id = ?",
            [$userId]
        );
        
        if (!$settings || empty($settings['telegram_bot_token'])) {
            throw new Exception('Kein Bot-Token für Benutzer konfiguriert');
        }
        
        $botToken = $settings['telegram_bot_token'];
        
        if ($botToken === 'demo') {
            // Demo-Modus: Nachricht loggen aber nicht senden
            self::logMessage($userId, $parameters['chat_id'] ?? 'demo', $parameters['text'] ?? 'Demo-Nachricht', 'notification', 'sent', null, 'demo');
            return ['message_id' => rand(1000, 9999)]; // Fake Message-ID
        }
        
        $url = "https://api.telegram.org/bot{$botToken}/{$method}";
        
        $postData = http_build_query($parameters);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postData,
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Telegram API nicht erreichbar');
        }
        
        $decoded = json_decode($response, true);
        
        if (!$decoded['ok']) {
            throw new Exception('Telegram API Fehler: ' . ($decoded['description'] ?? 'Unbekannter Fehler'));
        }
        
        return $decoded['result'];
    }
    
    /**
     * Nachricht an Benutzer senden (mit seinem Bot)
     */
    public static function sendUserMessage($userId, $text, $parseMode = 'HTML') {
        $settings = Database::fetchOne(
            "SELECT telegram_chat_id, telegram_verified FROM notification_settings WHERE user_id = ?",
            [$userId]
        );
        
        if (!$settings || !$settings['telegram_verified'] || empty($settings['telegram_chat_id'])) {
            throw new Exception('Telegram nicht konfiguriert oder verifiziert');
        }
        
        $chatId = $settings['telegram_chat_id'];
        
        try {
            $result = self::userApiRequest($userId, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true
            ]);
            
            // Erfolg loggen
            self::logMessage($userId, $chatId, $text, 'notification', 'sent', $result['message_id'] ?? null);
            
            return true;
            
        } catch (Exception $e) {
            // Fehler loggen
            self::logMessage($userId, $chatId, $text, 'notification', 'failed', null, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Chat-ID mit Benutzer-Bot validieren
     */
    public static function validateUserChatId($userId, $chatId) {
        try {
            $result = self::userApiRequest($userId, 'getChat', [
                'chat_id' => $chatId
            ]);
            
            return isset($result['id']);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifizierungscode an Benutzer senden
     */
    public static function sendUserVerificationCode($userId, $chatId, $code) {
        $userSettings = Database::fetchOne("SELECT u.name FROM users u WHERE u.id = ?", [$userId]);
        $userName = $userSettings['name'] ?? 'Stromtracker-Nutzer';
        
        $message = "🔐 <b>Stromtracker Verifizierung</b>\n\n";
        $message .= "Hallo " . htmlspecialchars($userName) . "!\n\n";
        $message .= "Ihr Verifizierungscode: <code>{$code}</code>\n\n";
        $message .= "Geben Sie diesen Code in Ihrem Stromtracker-Profil ein, um Telegram-Benachrichtigungen zu aktivieren.\n\n";
        $message .= "⚡ Dann erhalten Sie automatisch Erinnerungen für Zählerstand-Ablesungen!";
        
        try {
            $result = self::userApiRequest($userId, 'sendMessage', [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);
            
            self::logMessage($userId, $chatId, $message, 'verification', 'sent', $result['message_id'] ?? null);
            return true;
            
        } catch (Exception $e) {
            self::logMessage($userId, $chatId, $message, 'verification', 'failed', null, $e->getMessage());
            error_log("Telegram verification send error for user {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Zählerstand-Erinnerung an Benutzer senden
     */
    public static function sendUserReadingReminder($userId, $reminderData) {
        $userSettings = Database::fetchOne("SELECT u.name FROM users u WHERE u.id = ?", [$userId]);
        $userName = $userSettings['name'] ?? 'Stromtracker-Nutzer';
        
        $message = "⚡ <b>Stromtracker Erinnerung</b>\n\n";
        $message .= "Hallo " . htmlspecialchars($userName) . "!\n\n";
        $message .= "📊 <b>Zählerstand erfassen</b>\n";
        $message .= htmlspecialchars($reminderData['message']) . "\n\n";
        
        if (isset($reminderData['suggested_date'])) {
            $message .= "💡 <b>Vorgeschlagenes Datum:</b> " . 
                       date('d.m.Y', strtotime($reminderData['suggested_date'])) . "\n\n";
        }
        
        $message .= "🔗 Jetzt erfassen: [Zum Stromtracker](https://ihredomain.de/zaehlerstand.php)\n\n";
        $message .= "⚙️ Einstellungen ändern: [Profil](https://ihredomain.de/profil.php)";
        
        return self::sendUserMessage($userId, $message, 'HTML');
    }
    
    /**
     * Test-Nachricht an Benutzer senden
     */
    public static function sendUserTestMessage($userId) {
        $userSettings = Database::fetchOne("SELECT u.name FROM users u WHERE u.id = ?", [$userId]);
        $userName = $userSettings['name'] ?? 'Stromtracker-Nutzer';
        
        $message = "🧪 <b>Test-Nachricht</b>\n\n";
        $message .= "Hallo " . htmlspecialchars($userName) . "!\n\n";
        $message .= "✅ Ihr persönlicher Telegram-Bot funktioniert korrekt!\n\n";
        $message .= "📱 <b>Bot-Informationen:</b>\n";
        $message .= "🔸 Zeitstempel: " . date('d.m.Y H:i:s') . "\n";
        $message .= "🔸 Benutzer-ID: #$userId\n\n";
        $message .= "🔔 Sie erhalten jetzt automatische Erinnerungen über diesen Bot.";
        
        return self::sendUserMessage($userId, $message, 'HTML');
    }
    
    /**
     * Nachrichten-Log erstellen
     */
    private static function logMessage($userId, $chatId, $text, $type = 'notification', $status = 'sent', $telegramMessageId = null, $errorMessage = null) {
        try {
            // Bot-Token-Prefix für Logs (Sicherheit)
            $botTokenPrefix = null;
            $settings = Database::fetchOne(
                "SELECT telegram_bot_token FROM notification_settings WHERE user_id = ?",
                [$userId]
            );
            
            if ($settings && !empty($settings['telegram_bot_token'])) {
                $botTokenPrefix = substr($settings['telegram_bot_token'], 0, 20);
            }
            
            Database::insert('telegram_log', [
                'user_id' => $userId,
                'chat_id' => $chatId,
                'bot_token_used' => $botTokenPrefix,
                'message_type' => $type,
                'message_text' => substr($text, 0, 1000), // Text kürzen für DB
                'telegram_message_id' => $telegramMessageId,
                'status' => $status,
                'error_message' => $errorMessage
            ]);
            
        } catch (Exception $e) {
            error_log("Telegram log error: " . $e->getMessage());
        }
    }
    
    /**
     * Benutzer-Telegram-Einstellungen speichern
     */
    public static function saveUserTelegramSettings($userId, $settings) {
        $existing = Database::fetchOne(
            "SELECT id FROM notification_settings WHERE user_id = ?",
            [$userId]
        );
        
        $data = [
            'telegram_enabled' => (bool)($settings['telegram_enabled'] ?? false),
            'telegram_bot_token' => $settings['telegram_bot_token'] ?? null,
            'telegram_bot_username' => $settings['telegram_bot_username'] ?? null,
            'telegram_chat_id' => $settings['telegram_chat_id'] ?? null,
            'telegram_verified' => (bool)($settings['telegram_verified'] ?? false)
        ];
        
        if ($existing) {
            return Database::update(
                'notification_settings',
                $data,
                'user_id = ?',
                [$userId]
            );
        } else {
            $data['user_id'] = $userId;
            return Database::insert('notification_settings', $data);
        }
    }
    
    /**
     * Chat-ID als verifiziert markieren
     */
    public static function markUserChatIdVerified($userId) {
        return Database::update(
            'notification_settings',
            ['telegram_verified' => true],
            'user_id = ?',
            [$userId]
        );
    }
    
    /**
     * Benutzer-Telegram-Einstellungen laden
     */
    public static function getUserTelegramSettings($userId) {
        return Database::fetchOne(
            "SELECT telegram_enabled, telegram_bot_token, telegram_bot_username, 
                    telegram_chat_id, telegram_verified 
             FROM notification_settings 
             WHERE user_id = ?",
            [$userId]
        ) ?: [
            'telegram_enabled' => false,
            'telegram_bot_token' => null,
            'telegram_bot_username' => null,
            'telegram_chat_id' => null,
            'telegram_verified' => false
        ];
    }
    
    /**
     * Zufälligen Verifizierungscode generieren
     */
    public static function generateVerificationCode() {
        return sprintf('%06d', mt_rand(100000, 999999));
    }
    
    /**
     * Telegram-Statistiken für Benutzer
     */
    public static function getUserStatistics($userId) {
        try {
            $stats = Database::fetchOne(
                "SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_messages,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_messages
                 FROM telegram_log 
                 WHERE user_id = ? 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$userId]
            ) ?: ['total_messages' => 0, 'sent_messages' => 0, 'failed_messages' => 0];
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("User telegram stats error: " . $e->getMessage());
            return ['total_messages' => 0, 'sent_messages' => 0, 'failed_messages' => 0];
        }
    }
    
    /**
     * Rate-Limiting prüfen (optional)
     */
    public static function checkRateLimit($userId) {
        try {
            $rateLimitSetting = Database::fetchOne(
                "SELECT setting_value FROM telegram_system WHERE setting_key = 'rate_limit_per_minute'"
            );
            
            $limit = (int)($rateLimitSetting['setting_value'] ?? 20);
            
            $recentCount = Database::fetchOne(
                "SELECT COUNT(*) as count FROM telegram_log 
                 WHERE user_id = ? 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
                [$userId]
            )['count'] ?? 0;
            
            return $recentCount < $limit;
            
        } catch (Exception $e) {
            return true; // Bei Fehler: Rate-Limit nicht anwenden
        }
    }
    
    /**
     * System-weite Telegram-Statistiken (für Admin)
     */
    public static function getSystemStatistics() {
        try {
            // Benutzer-Statistiken
            $userStats = Database::fetchOne(
                "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN telegram_enabled THEN 1 ELSE 0 END) as enabled_users,
                    SUM(CASE WHEN telegram_bot_token IS NOT NULL THEN 1 ELSE 0 END) as users_with_bot,
                    SUM(CASE WHEN telegram_verified THEN 1 ELSE 0 END) as verified_users
                 FROM notification_settings"
            ) ?: [];
            
            // Nachrichten-Statistiken
            $messageStats = Database::fetchOne(
                "SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_messages,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_messages
                 FROM telegram_log 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ) ?: [];
            
            return array_merge($userStats, $messageStats);
            
        } catch (Exception $e) {
            error_log("System telegram stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Bot-Token aus Einstellungen entfernen
     */
    public static function removeUserBot($userId) {
        return Database::update(
            'notification_settings',
            [
                'telegram_enabled' => false,
                'telegram_bot_token' => null,
                'telegram_bot_username' => null,
                'telegram_chat_id' => null,
                'telegram_verified' => false
            ],
            'user_id = ?',
            [$userId]
        );
    }
}
