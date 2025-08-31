<?php
// includes/TelegramManager.php
// Telegram Bot Integration für Stromtracker Benachrichtigungen

class TelegramManager {
    
    private static $botConfig = null;
    
    /**
     * Bot-Konfiguration laden
     */
    private static function loadBotConfig() {
        if (self::$botConfig === null) {
            try {
                self::$botConfig = Database::fetchOne(
                    "SELECT * FROM telegram_config WHERE is_active = 1 ORDER BY id DESC LIMIT 1"
                );
            } catch (Exception $e) {
                error_log("Telegram config error: " . $e->getMessage());
                self::$botConfig = false;
            }
        }
        
        return self::$botConfig;
    }
    
    /**
     * Prüfen ob Telegram aktiviert und konfiguriert ist
     */
    public static function isEnabled() {
        $config = self::loadBotConfig();
        return $config && !empty($config['bot_token']) && $config['bot_token'] !== 'YOUR_BOT_TOKEN_HERE';
    }
    
    /**
     * Bot-Token abrufen
     */
    private static function getBotToken() {
        $config = self::loadBotConfig();
        return $config ? $config['bot_token'] : null;
    }
    
    /**
     * Telegram API Anfrage senden
     */
    private static function apiRequest($method, $parameters = []) {
        $botToken = self::getBotToken();
        if (!$botToken) {
            throw new Exception('Telegram Bot Token nicht konfiguriert');
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
     * Nachricht an Chat-ID senden
     */
    public static function sendMessage($chatId, $text, $parseMode = 'HTML') {
        if (!self::isEnabled()) {
            throw new Exception('Telegram ist nicht aktiviert');
        }
        
        if (empty($chatId) || empty($text)) {
            throw new Exception('Chat-ID und Text sind erforderlich');
        }
        
        try {
            $result = self::apiRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true
            ]);
            
            // Erfolg in Datenbank loggen
            self::logMessage($chatId, $text, 'notification', 'sent', $result['message_id'] ?? null);
            
            return true;
            
        } catch (Exception $e) {
            // Fehler in Datenbank loggen
            self::logMessage($chatId, $text, 'notification', 'failed', null, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Chat-ID validieren (prüfen ob Chat existiert)
     */
    public static function validateChatId($chatId) {
        if (!self::isEnabled()) {
            return false;
        }
        
        try {
            $result = self::apiRequest('getChat', [
                'chat_id' => $chatId
            ]);
            
            return isset($result['id']);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verifizierungscode an Chat-ID senden
     */
    public static function sendVerificationCode($chatId, $code) {
        $message = "🔐 <b>Stromtracker Verifizierung</b>\n\n";
        $message .= "Ihr Verifizierungscode: <code>{$code}</code>\n\n";
        $message .= "Geben Sie diesen Code in Ihrem Stromtracker-Profil ein, um Telegram-Benachrichtigungen zu aktivieren.\n\n";
        $message .= "⚡ Dann erhalten Sie automatisch Erinnerungen für Zählerstand-Ablesungen!";
        
        try {
            self::sendMessage($chatId, $message);
            self::logMessage($chatId, $message, 'verification');
            return true;
        } catch (Exception $e) {
            error_log("Telegram verification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Zählerstand-Erinnerung senden
     */
    public static function sendReadingReminder($chatId, $userName, $reminderData) {
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
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Hoher Verbrauch-Alarm senden
     */
    public static function sendHighUsageAlert($chatId, $userName, $consumption, $threshold) {
        $message = "⚠️ <b>Hoher Verbrauch-Alarm</b>\n\n";
        $message .= "Hallo " . htmlspecialchars($userName) . "!\n\n";
        $message .= "📈 Ihr Stromverbrauch ist überdurchschnittlich hoch:\n\n";
        $message .= "🔸 <b>Aktueller Verbrauch:</b> " . number_format($consumption, 1) . " kWh\n";
        $message .= "🔸 <b>Ihr Grenzwert:</b> " . number_format($threshold, 1) . " kWh\n";
        $message .= "🔸 <b>Überschreitung:</b> +" . number_format($consumption - $threshold, 1) . " kWh\n\n";
        $message .= "💡 Prüfen Sie Ihre Geräte auf ungewöhnliche Aktivität.\n\n";
        $message .= "📊 [Zur Auswertung](https://ihredomain.de/auswertung.php)";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Nachrichten-Log erstellen
     */
    private static function logMessage($chatId, $text, $type = 'notification', $status = 'sent', $telegramMessageId = null, $errorMessage = null) {
        try {
            // User-ID anhand Chat-ID ermitteln (falls möglich)
            $userId = null;
            $user = Database::fetchOne(
                "SELECT user_id FROM notification_settings WHERE telegram_chat_id = ?", 
                [$chatId]
            );
            if ($user) {
                $userId = $user['user_id'];
            }
            
            Database::insert('telegram_log', [
                'user_id' => $userId,
                'chat_id' => $chatId,
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
    public static function saveUserTelegramSettings($userId, $chatId = null, $enabled = false) {
        // Aktuelle Einstellungen laden
        $current = Database::fetchOne(
            "SELECT telegram_chat_id, telegram_enabled FROM notification_settings WHERE user_id = ?",
            [$userId]
        );
        
        $updates = [];
        
        // Chat-ID aktualisieren
        if ($chatId !== null) {
            $updates['telegram_chat_id'] = $chatId;
            $updates['telegram_verified'] = false; // Muss neu verifiziert werden
        }
        
        // Aktivierungsstatus
        $updates['telegram_enabled'] = $enabled ? 1 : 0;
        
        return Database::update(
            'notification_settings',
            $updates,
            'user_id = ?',
            [$userId]
        );
    }
    
    /**
     * Chat-ID als verifiziert markieren
     */
    public static function markChatIdVerified($userId) {
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
            "SELECT telegram_enabled, telegram_chat_id, telegram_verified 
             FROM notification_settings 
             WHERE user_id = ?",
            [$userId]
        ) ?: [
            'telegram_enabled' => false,
            'telegram_chat_id' => null,
            'telegram_verified' => false
        ];
    }
    
    /**
     * Bot-Informationen abrufen
     */
    public static function getBotInfo() {
        if (!self::isEnabled()) {
            return null;
        }
        
        try {
            $result = self::apiRequest('getMe');
            return [
                'id' => $result['id'],
                'username' => $result['username'] ?? null,
                'first_name' => $result['first_name'] ?? null,
                'can_join_groups' => $result['can_join_groups'] ?? false,
                'can_read_all_group_messages' => $result['can_read_all_group_messages'] ?? false
            ];
        } catch (Exception $e) {
            error_log("Telegram bot info error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Zufälligen Verifizierungscode generieren
     */
    public static function generateVerificationCode() {
        return sprintf('%06d', mt_rand(100000, 999999));
    }
    
    /**
     * Telegram-Statistiken für Admin
     */
    public static function getStatistics() {
        try {
            // Benutzer-Statistiken
            $userStats = Database::fetchOne(
                "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN telegram_enabled THEN 1 ELSE 0 END) as enabled_users,
                    SUM(CASE WHEN telegram_chat_id IS NOT NULL THEN 1 ELSE 0 END) as users_with_chat_id,
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
            error_log("Telegram stats error: " . $e->getMessage());
            return [];
        }
    }
}
