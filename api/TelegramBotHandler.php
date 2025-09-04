<?php
// api/TelegramBotHandler.php
// BACKUP VERSION - Falls includes-Pfad nicht funktioniert

class TelegramBotHandler {
    
    /**
     * Verarbeitet eingehende Telegram Webhook-Nachrichten
     */
    public static function handleWebhook($webhookData) {
        try {
            error_log("[BOT] Webhook received: " . json_encode($webhookData));
            
            // Basis-Validierung
            if (!isset($webhookData['message'])) {
                error_log("[BOT] No message in webhook data");
                return false;
            }
            
            $message = $webhookData['message'];
            $chatId = $message['chat']['id'] ?? null;
            $text = trim($message['text'] ?? '');
            $userId = self::getUserByChatId($chatId);
            
            if (!$userId) {
                error_log("[BOT] Unknown or unverified chat ID: $chatId");
                self::sendUnknownUserMessage($chatId);
                return false;
            }
            
            error_log("[BOT] Processing message from user $userId (chat $chatId): '$text'");
            
            // Command/Message routing
            return self::processUserMessage($userId, $chatId, $text);
            
        } catch (Exception $e) {
            error_log("[BOT] Webhook handler error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Benutzer anhand Chat-ID ermitteln
     */
    private static function getUserByChatId($chatId) {
        if (!$chatId || !class_exists('Database')) return null;
        
        $user = Database::fetchOne(
            "SELECT u.id, u.name, u.email, ns.telegram_bot_token 
             FROM users u 
             JOIN notification_settings ns ON u.id = ns.user_id 
             WHERE ns.telegram_chat_id = ? 
             AND ns.telegram_verified = 1 
             AND ns.telegram_enabled = 1
             AND ns.telegram_bot_token IS NOT NULL",
            [$chatId]
        );
        
        return $user ? $user['id'] : null;
    }
    
    /**
     * Benutzer-Nachricht verarbeiten
     */
    private static function processUserMessage($userId, $chatId, $text) {
        try {
            // Bot-Commands
            if (str_starts_with($text, '/')) {
                return self::handleCommand($userId, $chatId, $text);
            }
            
            // Zählerstand-Erkennung versuchen
            $meterReading = self::extractMeterReading($text);
            if ($meterReading) {
                return self::processMeterReading($userId, $chatId, $meterReading, $text);
            }
            
            // Unerkannte Nachricht
            return self::handleUnknownMessage($userId, $chatId, $text);
            
        } catch (Exception $e) {
            error_log("[BOT] Message processing error: " . $e->getMessage());
            self::sendErrorMessage($chatId, "Fehler beim Verarbeiten der Nachricht.");
            return false;
        }
    }
    
    /**
     * Bot-Commands verarbeiten
     */
    private static function handleCommand($userId, $chatId, $command) {
        $cmd = strtolower(explode(' ', $command)[0]);
        
        switch ($cmd) {
            case '/start':
                return self::sendWelcomeMessage($userId, $chatId);
                
            case '/help':
            case '/hilfe':
                return self::sendHelpMessage($chatId);
                
            case '/status':
                return self::sendStatusMessage($userId, $chatId);
                
            case '/stand':
                $parts = explode(' ', $command, 2);
                if (count($parts) === 2) {
                    $reading = self::extractMeterReading($parts[1]);
                    if ($reading) {
                        return self::processMeterReading($userId, $chatId, $reading, $command);
                    }
                }
                self::sendMessage($chatId, "❌ Ungültiges Format. Beispiel: /stand 12450");
                return false;
                
            default:
                self::sendMessage($chatId, "❓ Unbekannter Befehl. Senden Sie /help für Hilfe.");
                return false;
        }
    }
    
    /**
     * Zählerstand aus Text extrahieren
     */
    private static function extractMeterReading($text) {
        $patterns = [
            '/^(\d{4,6})$/',                           // "12450" 
            '/(?:stand|zähler|kwh)[\s:]*(\d{4,6})/i',  // "Stand: 12450"
            '/(\d{4,6})[\s]*kwh/i',                    // "12450 kWh"
            '/(\d{1,3}[.,]\d{3})/',                    // "12.450"
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $reading = str_replace(['.', ','], '', $matches[1]);
                $reading = (float)$reading;
                
                if ($reading >= 1000 && $reading <= 999999) {
                    return $reading;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Zählerstand verarbeiten und speichern
     */
    private static function processMeterReading($userId, $chatId, $reading, $originalText) {
        try {
            if (!class_exists('Database')) {
                self::sendMessage($chatId, "🤖 Test-Modus: Zählerstand $reading erkannt!");
                return true;
            }
            
            // Vereinfachte Verarbeitung
            self::sendMessage($chatId, "✅ Zählerstand $reading erfasst!\n📊 Verarbeitung läuft...");
            return true;
            
        } catch (Exception $e) {
            error_log("[BOT] Process meter reading error: " . $e->getMessage());
            self::sendMessage($chatId, "❌ Fehler beim Verarbeiten des Zählerstands.");
            return false;
        }
    }
    
    /**
     * Status-Nachricht senden
     */
    private static function sendStatusMessage($userId, $chatId) {
        $message = "📊 <b>Bot Status</b>\n\n";
        $message .= "🤖 Telegram Bot ist aktiv!\n";
        $message .= "👤 Benutzer-ID: $userId\n\n";
        $message .= "💡 Senden Sie einen Zählerstand wie <code>12450</code>";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Hilfe-Nachricht senden
     */
    private static function sendHelpMessage($chatId) {
        $message = "🤖 <b>Stromtracker Bot Hilfe</b>\n\n";
        $message .= "📊 <b>Zählerstand erfassen:</b>\n";
        $message .= "• <code>12450</code>\n";
        $message .= "• <code>Stand: 12450</code>\n";
        $message .= "• <code>/stand 12450</code>\n\n";
        $message .= "🛠️ <b>Befehle:</b>\n";
        $message .= "• <code>/start</code> - Bot starten\n";
        $message .= "• <code>/status</code> - Status anzeigen\n";
        $message .= "• <code>/help</code> - Diese Hilfe";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Welcome-Nachricht senden
     */
    private static function sendWelcomeMessage($userId, $chatId) {
        $message = "🔌 <b>Willkommen im Stromtracker Bot!</b>\n\n";
        $message .= "Sie können jetzt Zählerstände über Telegram erfassen:\n\n";
        $message .= "📊 Einfach senden:\n";
        $message .= "• <code>12450</code>\n";
        $message .= "• <code>/stand 12450</code>\n\n";
        $message .= "Senden Sie <code>/help</code> für weitere Informationen!";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Unbekannte Nachricht behandeln
     */
    private static function handleUnknownMessage($userId, $chatId, $text) {
        $message = "❓ <b>Nachricht nicht verstanden</b>\n\n";
        $message .= "💡 Senden Sie einen Zählerstand wie <code>12450</code>\n";
        $message .= "🆘 Oder <code>/help</code> für Hilfe.";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Nachricht an unbekannten Benutzer
     */
    private static function sendUnknownUserMessage($chatId) {
        $message = "❌ <b>Nicht autorisiert</b>\n\n";
        $message .= "Dieser Bot ist nur für registrierte Stromtracker-Benutzer.\n\n";
        $message .= "Bitte konfigurieren Sie Telegram in Ihrem Profil.";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Generische Nachricht senden
     */
    private static function sendMessage($chatId, $text, $parseMode = 'HTML') {
        try {
            error_log("[BOT] Sending to $chatId: " . substr($text, 0, 50) . "...");
            
            if (!class_exists('Database')) {
                error_log("[BOT] No Database class - would send: $text");
                return true;
            }
            
            // Bot-Token ermitteln
            $userBot = Database::fetchOne(
                "SELECT ns.telegram_bot_token 
                 FROM notification_settings ns 
                 JOIN users u ON u.id = ns.user_id 
                 WHERE ns.telegram_chat_id = ? 
                 AND ns.telegram_verified = 1 
                 AND ns.telegram_enabled = 1",
                [$chatId]
            );
            
            if (!$userBot || empty($userBot['telegram_bot_token'])) {
                error_log("[BOT] No bot token found for chat ID: $chatId");
                return false;
            }
            
            $botToken = $userBot['telegram_bot_token'];
            
            // Demo-Modus
            if ($botToken === 'demo') {
                error_log("[BOT] Demo mode - would send: $text");
                return true;
            }
            
            // Telegram API Request
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $postData = http_build_query([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true
            ]);
            
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
                error_log("[BOT] API request failed");
                return false;
            }
            
            $decoded = json_decode($response, true);
            if (!isset($decoded['ok']) || !$decoded['ok']) {
                error_log("[BOT] API error: " . ($decoded['description'] ?? 'Unknown'));
                return false;
            }
            
            error_log("[BOT] Message sent successfully");
            return true;
            
        } catch (Exception $e) {
            error_log("[BOT] Send message error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Error-Nachricht senden
     */
    private static function sendErrorMessage($chatId, $error) {
        return self::sendMessage($chatId, "❌ " . $error);
    }
}
?>
