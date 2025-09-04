<?php
// api/telegram-webhook.php  
// TELEGRAM WEBHOOK ENDPOINT - Robuste Version mit Pfad-Debugging

// Error Reporting für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keine Ausgabe an Telegram
ini_set('log_errors', 1);

// Debug: Pfade loggen
error_log("[WEBHOOK] __DIR__: " . __DIR__);
error_log("[WEBHOOK] Realpath: " . realpath(__DIR__));

// Headers setzen
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Pfade definieren und prüfen
    $basePath = dirname(__DIR__);
    $configPath = $basePath . '/config/database.php';
    $handlerPath = $basePath . '/includes/TelegramBotHandler.php';
    
    error_log("[WEBHOOK] Base path: $basePath");
    error_log("[WEBHOOK] Config path: $configPath (exists: " . (file_exists($configPath) ? 'YES' : 'NO') . ")");
    error_log("[WEBHOOK] Handler path: $handlerPath (exists: " . (file_exists($handlerPath) ? 'YES' : 'NO') . ")");
    
    // Database-Konfiguration laden
    if (!file_exists($configPath)) {
        throw new Exception("Database config not found: $configPath");
    }
    require_once $configPath;
    
    // TelegramBotHandler laden (mit Fallback)
    if (file_exists($handlerPath)) {
        require_once $handlerPath;
    } else {
        // Inline Bot Handler als Fallback
        error_log("[WEBHOOK] Using inline bot handler");
        
        class TelegramBotHandler {
            public static function handleWebhook($webhookData) {
                error_log("[BOT] Fallback handler called with: " . json_encode($webhookData));
                
                if (!isset($webhookData['message'])) {
                    return false;
                }
                
                $message = $webhookData['message'];
                $chatId = $message['chat']['id'] ?? null;
                $text = trim($message['text'] ?? '');
                
                // Einfache Antwort für Test
                if ($text === '/start') {
                    self::sendSimpleMessage($chatId, "🤖 Bot ist aktiv! Senden Sie einen Zählerstand wie '12450'");
                    return true;
                } elseif (preg_match('/^(\d{4,6})$/', $text)) {
                    self::sendSimpleMessage($chatId, "📊 Zählerstand $text erfasst! (Test-Modus)");
                    return true;
                }
                
                return false;
            }
            
            private static function sendSimpleMessage($chatId, $text) {
                // Vereinfachtes Senden (ohne Benutzer-Token-Lookup)
                error_log("[BOT] Would send to $chatId: $text");
                return true;
            }
        }
    }
    
    // Nur POST-Requests erlauben
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    // Webhook-Daten lesen
    $input = file_get_contents('php://input');
    if (empty($input)) {
        error_log("[WEBHOOK] Empty input received");
        http_response_code(400);
        echo json_encode(['error' => 'No data received']);
        exit;
    }
    
    // JSON dekodieren
    $webhookData = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[WEBHOOK] Invalid JSON: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    // Debug-Logging
    error_log("[WEBHOOK] Received webhook: " . json_encode($webhookData));
    
    // Basis-Validierung
    if (!isset($webhookData['message']) && !isset($webhookData['callback_query'])) {
        error_log("[WEBHOOK] No message or callback_query in webhook");
        http_response_code(200);
        echo json_encode(['ok' => true, 'message' => 'No message to process']);
        exit;
    }
    
    // Prüfen ob Database-Klasse existiert
    if (!class_exists('Database')) {
        error_log("[WEBHOOK] Database class not found - using fallback");
        // Fallback ohne Datenbank-Check
        $success = TelegramBotHandler::handleWebhook($webhookData);
    } else {
        // Normale Verarbeitung mit Datenbank
        $activeUsers = Database::fetchOne(
            "SELECT COUNT(*) as count FROM notification_settings 
             WHERE telegram_enabled = 1 AND telegram_verified = 1 
             AND telegram_bot_token IS NOT NULL AND telegram_chat_id IS NOT NULL"
        );
        
        if (!$activeUsers || $activeUsers['count'] == 0) {
            error_log("[WEBHOOK] No active Telegram users found");
            http_response_code(200);
            echo json_encode(['ok' => true, 'message' => 'No active users']);
            exit;
        }
        
        error_log("[WEBHOOK] Active Telegram users: " . $activeUsers['count']);
        
        // Bot Handler aufrufen
        $success = TelegramBotHandler::handleWebhook($webhookData);
    }
    
    // Response für Telegram
    $response = [
        'ok' => true,
        'processed' => $success,
        'timestamp' => date('c'),
        'debug' => [
            'handler_exists' => class_exists('TelegramBotHandler'),
            'database_exists' => class_exists('Database'),
            'base_path' => $basePath
        ]
    ];
    
    error_log("[WEBHOOK] Processing result: " . ($success ? 'SUCCESS' : 'FAILED'));
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    // Fehler loggen aber 200 an Telegram senden
    error_log("[WEBHOOK] Exception: " . $e->getMessage());
    error_log("[WEBHOOK] Stack trace: " . $e->getTraceAsString());
    
    http_response_code(200); // Wichtig: 200 für Telegram!
    echo json_encode([
        'ok' => false,
        'error' => 'Internal error: ' . $e->getMessage(),
        'timestamp' => date('c')
    ]);
    
} catch (Error $e) {
    // PHP Fatal Errors abfangen
    error_log("[WEBHOOK] Fatal Error: " . $e->getMessage());
    
    http_response_code(200);
    echo json_encode([
        'ok' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>
