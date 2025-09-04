<?php
// includes/TelegramBotHandler.php
// TELEGRAM BOT MESSAGE HANDLER für automatische Zählerstand-Erfassung

require_once __DIR__ . '/TelegramManager.php';
require_once __DIR__ . '/NotificationManager.php';

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
                // Freundliche Nachricht senden
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
        if (!$chatId) return null;
        
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
            
            // Spezielle Kommandos ohne /
            if (self::handleSpecialCommand($userId, $chatId, $text)) {
                return true;
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
                // "/stand 12450" Format
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
        // Verschiedene Patterns für Zählerstände
        $patterns = [
            '/^(\d{4,6})$/',                           // "12450" 
            '/(?:stand|zähler|kwh)[\s:]*(\d{4,6})/i',  // "Stand: 12450" oder "Zählerstand 12450"
            '/(\d{4,6})[\s]*kwh/i',                    // "12450 kWh"
            '/(\d{1,3}[.,]\d{3})/',                    // "12.450" oder "12,450"
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $reading = str_replace(['.', ','], '', $matches[1]);
                $reading = (float)$reading;
                
                // Sinnvoll-Check (zwischen 1000 und 999999 kWh)
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
            error_log("[BOT] Processing meter reading $reading for user $userId");
            
            // Letzten Zählerstand holen
            $lastReading = Database::fetchOne(
                "SELECT * FROM meter_readings 
                 WHERE user_id = ? 
                 ORDER BY reading_date DESC 
                 LIMIT 1",
                [$userId]
            );
            
            // Validierung
            $validation = self::validateMeterReading($userId, $reading, $lastReading);
            if (!$validation['valid']) {
                self::sendMessage($chatId, $validation['message']);
                return false;
            }
            
            // Aktuellen Tarif holen
            $currentTariff = Database::fetchOne(
                "SELECT * FROM tariff_periods 
                 WHERE user_id = ? 
                 AND valid_from <= CURDATE() 
                 AND (valid_to IS NULL OR valid_to >= CURDATE())
                 ORDER BY valid_from DESC 
                 LIMIT 1",
                [$userId]
            );
            
            if (!$currentTariff) {
                self::sendMessage($chatId, "❌ Kein aktiver Tarif gefunden. Bitte konfigurieren Sie zuerst einen Tarif in der Web-App.");
                return false;
            }
            
            // Berechnungen
            $consumption = $lastReading ? ($reading - $lastReading['meter_value']) : 0;
            $cost = $consumption * $currentTariff['rate_per_kwh'];
            $totalBill = $cost + ($currentTariff['basic_fee'] ?? 0);
            $paymentDifference = $totalBill - ($currentTariff['monthly_payment'] ?? 0);
            
            // In Datenbank speichern
            $insertId = Database::insert('meter_readings', [
                'user_id' => $userId,
                'reading_date' => date('Y-m-d'),
                'meter_value' => $reading,
                'consumption' => $consumption,
                'cost' => $cost,
                'rate_per_kwh' => $currentTariff['rate_per_kwh'],
                'monthly_payment' => $currentTariff['monthly_payment'],
                'basic_fee' => $currentTariff['basic_fee'],
                'total_bill' => $totalBill,
                'payment_difference' => $paymentDifference,
                'notes' => "Erfasst via Telegram Bot: " . $originalText
            ]);
            
            if ($insertId) {
                // Erfolgs-Nachricht senden
                self::sendSuccessMessage($userId, $chatId, [
                    'reading' => $reading,
                    'consumption' => $consumption,
                    'cost' => $cost,
                    'total_bill' => $totalBill,
                    'payment_difference' => $paymentDifference,
                    'last_reading' => $lastReading
                ]);
                
                error_log("[BOT] Meter reading saved successfully: ID $insertId");
                return true;
            } else {
                self::sendMessage($chatId, "❌ Fehler beim Speichern des Zählerstands. Bitte versuchen Sie es erneut.");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("[BOT] Process meter reading error: " . $e->getMessage());
            self::sendMessage($chatId, "❌ Systemfehler beim Verarbeiten des Zählerstands.");
            return false;
        }
    }
    
    /**
     * Zählerstand-Validierung
     */
    private static function validateMeterReading($userId, $reading, $lastReading) {
        // Basis-Checks
        if ($reading < 1000) {
            return ['valid' => false, 'message' => "❌ Zählerstand zu niedrig. Minimum: 1000 kWh"];
        }
        
        if ($reading > 999999) {
            return ['valid' => false, 'message' => "❌ Zählerstand zu hoch. Maximum: 999.999 kWh"];
        }
        
        // Vergleich mit letztem Wert
        if ($lastReading) {
            $lastValue = (float)$lastReading['meter_value'];
            
            if ($reading <= $lastValue) {
                return ['valid' => false, 'message' => 
                    "❌ Zählerstand muss höher als der letzte Wert sein.\n" .
                    "Letzter Stand: " . number_format($lastValue, 0, ',', '.') . " kWh\n" .
                    "Ihr Wert: " . number_format($reading, 0, ',', '.') . " kWh"
                ];
            }
            
            $consumption = $reading - $lastValue;
            if ($consumption > 2000) {
                return ['valid' => false, 'message' => 
                    "❌ Verbrauch sehr hoch: " . number_format($consumption, 0, ',', '.') . " kWh\n" .
                    "Bitte prüfen Sie den Zählerstand."
                ];
            }
        }
        
        // Duplikate prüfen (heute schon erfasst?)
        $today = Database::fetchOne(
            "SELECT id FROM meter_readings WHERE user_id = ? AND reading_date = CURDATE()",
            [$userId]
        );
        
        if ($today) {
            return ['valid' => false, 'message' => 
                "❌ Heute wurde bereits ein Zählerstand erfasst.\n" .
                "Senden Sie 'Korrektur: $reading' um zu überschreiben."
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Spezielle Kommandos ohne / behandeln
     */
    private static function handleSpecialCommand($userId, $chatId, $text) {
        $text = trim(strtolower($text));
        
        // "Status" oder "status"
        if (in_array($text, ['status', 'stand', 'info', 'übersicht'])) {
            return self::sendStatusMessage($userId, $chatId);
        }
        
        // "Hilfe" oder "help"
        if (in_array($text, ['hilfe', 'help', 'h', '?'])) {
            return self::sendHelpMessage($chatId);
        }
        
        // Korrekturen: "korrektur: 12450" oder "korrigiere 12450"
        if (preg_match('/^(korrektur|korrigiere|fix)[:\s]*(\d{4,6})$/i', $text, $matches)) {
            $reading = (float)$matches[2];
            return self::handleCorrection($userId, $chatId, $reading, $text);
        }
        
        // Löschungen: "lösche heute" oder "delete last"
        if (preg_match('/^(lösche?|delete|entferne?)\s+(heute|today|last|letzten?)$/i', $text)) {
            return self::handleDeletion($userId, $chatId, $text);
        }
        
        // Verbrauch-Abfrage: "verbrauch" oder "consumption"
        if (in_array($text, ['verbrauch', 'consumption', 'usage', 'statistik'])) {
            return self::sendConsumptionStats($userId, $chatId);
        }
        
        // Tarif-Info: "tarif" oder "rate"
        if (in_array($text, ['tarif', 'rate', 'kosten', 'preis'])) {
            return self::sendTariffInfo($userId, $chatId);
        }
        
        return false; // Kein spezielles Kommando erkannt
    }
    
    /**
     * Korrektur des heutigen Zählerstands
     */
    private static function handleCorrection($userId, $chatId, $reading, $originalText) {
        try {
            // Heutigen Eintrag suchen
            $todayEntry = Database::fetchOne(
                "SELECT * FROM meter_readings WHERE user_id = ? AND reading_date = CURDATE()",
                [$userId]
            );
            
            if (!$todayEntry) {
                self::sendMessage($chatId, "❌ Heute wurde noch kein Zählerstand erfasst. Senden Sie einfach den aktuellen Wert.");
                return false;
            }
            
            // Basis-Validierung
            if ($reading < 1000 || $reading > 999999) {
                self::sendMessage($chatId, "❌ Ungültiger Zählerstand. Muss zwischen 1000 und 999999 liegen.");
                return false;
            }
            
            $oldValue = $todayEntry['meter_value'];
            
            // Neu berechnen
            $lastReading = Database::fetchOne(
                "SELECT * FROM meter_readings 
                 WHERE user_id = ? AND reading_date < CURDATE() 
                 ORDER BY reading_date DESC LIMIT 1",
                [$userId]
            );
            
            $consumption = $lastReading ? ($reading - $lastReading['meter_value']) : 0;
            
            // Tarif holen
            $currentTariff = Database::fetchOne(
                "SELECT * FROM tariff_periods 
                 WHERE user_id = ? AND valid_from <= CURDATE() 
                 AND (valid_to IS NULL OR valid_to >= CURDATE())
                 ORDER BY valid_from DESC LIMIT 1",
                [$userId]
            );
            
            if (!$currentTariff) {
                self::sendMessage($chatId, "❌ Kein aktiver Tarif gefunden.");
                return false;
            }
            
            $cost = $consumption * $currentTariff['rate_per_kwh'];
            $totalBill = $cost + ($currentTariff['basic_fee'] ?? 0);
            $paymentDifference = $totalBill - ($currentTariff['monthly_payment'] ?? 0);
            
            // Update durchführen
            $updateResult = Database::update(
                'meter_readings',
                [
                    'meter_value' => $reading,
                    'consumption' => $consumption,
                    'cost' => $cost,
                    'total_bill' => $totalBill,
                    'payment_difference' => $paymentDifference,
                    'notes' => ($todayEntry['notes'] ?? '') . " | Korrigiert via Bot: $originalText"
                ],
                'id = ?',
                [$todayEntry['id']]
            );
            
            if ($updateResult) {
                $message = "✅ <b>Zählerstand korrigiert!</b>\n\n";
                $message .= "🔄 <b>Alt:</b> " . number_format($oldValue, 0, ',', '.') . " kWh\n";
                $message .= "🆕 <b>Neu:</b> " . number_format($reading, 0, ',', '.') . " kWh\n\n";
                $message .= "⚡ <b>Verbrauch:</b> " . number_format($consumption, 0, ',', '.') . " kWh\n";
                $message .= "💰 <b>Kosten:</b> " . number_format($cost, 2, ',', '.') . " €\n";
                
                self::sendMessage($chatId, $message, 'HTML');
                return true;
            } else {
                self::sendMessage($chatId, "❌ Fehler beim Speichern der Korrektur.");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("[BOT] Correction error: " . $e->getMessage());
            self::sendMessage($chatId, "❌ Fehler bei der Korrektur.");
            return false;
        }
    }
    
    /**
     * Letzten/heutigen Eintrag löschen
     */
    private static function handleDeletion($userId, $chatId, $originalText) {
        try {
            // Heutigen oder letzten Eintrag suchen
            $entry = Database::fetchOne(
                "SELECT * FROM meter_readings WHERE user_id = ? ORDER BY reading_date DESC LIMIT 1",
                [$userId]
            );
            
            if (!$entry) {
                self::sendMessage($chatId, "❌ Kein Zählerstand zum Löschen gefunden.");
                return false;
            }
            
            $isToday = (date('Y-m-d') === $entry['reading_date']);
            $dateText = $isToday ? 'heute' : date('d.m.Y', strtotime($entry['reading_date']));
            
            // Löschen
            $deleteResult = Database::execute(
                "DELETE FROM meter_readings WHERE id = ?",
                [$entry['id']]
            );
            
            if ($deleteResult) {
                $message = "✅ <b>Zählerstand gelöscht!</b>\n\n";
                $message .= "📅 <b>Datum:</b> $dateText\n";
                $message .= "🔢 <b>Wert:</b> " . number_format($entry['meter_value'], 0, ',', '.') . " kWh\n\n";
                $message .= "💡 <i>Sie können jetzt einen neuen Wert erfassen.</i>";
                
                self::sendMessage($chatId, $message, 'HTML');
                return true;
            } else {
                self::sendMessage($chatId, "❌ Fehler beim Löschen des Zählerstands.");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("[BOT] Deletion error: " . $e->getMessage());
            self::sendMessage($chatId, "❌ Fehler beim Löschen.");
            return false;
        }
    }
    
    /**
     * Verbrauchsstatistiken senden
     */
    private static function sendConsumptionStats($userId, $chatId) {
        try {
            $user = Database::fetchOne("SELECT name FROM users WHERE id = ?", [$userId]);
            $userName = $user['name'] ?? 'Stromtracker-Nutzer';
            
            // Aktueller Monat
            $monthConsumption = Database::fetchOne(
                "SELECT SUM(consumption) as total, COUNT(*) as readings 
                 FROM meter_readings 
                 WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE()) 
                 AND MONTH(reading_date) = MONTH(CURDATE())",
                [$userId]
            );
            
            // Letzter Monat  
            $lastMonthConsumption = Database::fetchOne(
                "SELECT SUM(consumption) as total 
                 FROM meter_readings 
                 WHERE user_id = ? AND reading_date >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY), INTERVAL 1 MONTH)
                 AND reading_date < DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE())-1 DAY)",
                [$userId]
            );
            
            // Jahr
            $yearConsumption = Database::fetchOne(
                "SELECT SUM(consumption) as total, AVG(consumption) as avg_daily 
                 FROM meter_readings 
                 WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE())",
                [$userId]
            );
            
            // Letzter Stand
            $lastReading = Database::fetchOne(
                "SELECT * FROM meter_readings WHERE user_id = ? ORDER BY reading_date DESC LIMIT 1",
                [$userId]
            );
            
            $message = "📊 <b>Verbrauchsstatistik - " . htmlspecialchars($userName) . "</b>\n\n";
            
            if ($lastReading) {
                $message .= "🔢 <b>Aktueller Stand:</b> " . number_format($lastReading['meter_value'], 0, ',', '.') . " kWh\n";
                $message .= "📅 <b>Letzter Eintrag:</b> " . date('d.m.Y', strtotime($lastReading['reading_date'])) . "\n\n";
            }
            
            if ($monthConsumption && $monthConsumption['total'] > 0) {
                $message .= "📈 <b>Dieser Monat:</b> " . number_format($monthConsumption['total'], 0, ',', '.') . " kWh";
                $message .= " (" . $monthConsumption['readings'] . " Ablesungen)\n";
                
                $daysInMonth = date('t');
                $currentDay = date('j');
                $avgDaily = $monthConsumption['total'] / $currentDay;
                $projected = $avgDaily * $daysInMonth;
                
                $message .= "📊 <b>Hochrechnung:</b> " . number_format($projected, 0, ',', '.') . " kWh\n";
            }
            
            if ($lastMonthConsumption && $lastMonthConsumption['total'] > 0) {
                $message .= "📉 <b>Letzter Monat:</b> " . number_format($lastMonthConsumption['total'], 0, ',', '.') . " kWh\n";
            }
            
            if ($yearConsumption && $yearConsumption['total'] > 0) {
                $message .= "\n🗓️ <b>Jahresverbrauch:</b> " . number_format($yearConsumption['total'], 0, ',', '.') . " kWh\n";
                if ($yearConsumption['avg_daily']) {
                    $message .= "📋 <b>Ø täglich:</b> " . number_format($yearConsumption['avg_daily'], 1, ',', '.') . " kWh\n";
                }
            }
            
            $message .= "\n💡 <i>Tipp: 'Tarif' für Kosteninformationen</i>";
            
            return self::sendMessage($chatId, $message, 'HTML');
            
        } catch (Exception $e) {
            error_log("[BOT] Stats error: " . $e->getMessage());
            self::sendMessage($chatId, "❌ Fehler beim Laden der Statistiken.");
            return false;
        }
    }
    
    /**
     * Tarif-Informationen senden
     */
    private static function sendTariffInfo($userId, $chatId) {
        try {
            $currentTariff = Database::fetchOne(
                "SELECT * FROM tariff_periods 
                 WHERE user_id = ? AND valid_from <= CURDATE() 
                 AND (valid_to IS NULL OR valid_to >= CURDATE())
                 ORDER BY valid_from DESC LIMIT 1",
                [$userId]
            );
            
            if (!$currentTariff) {
                self::sendMessage($chatId, "❌ Kein aktiver Tarif konfiguriert. Bitte richten Sie einen Tarif in der Web-App ein.");
                return false;
            }
            
            $message = "💰 <b>Aktuelle Tarif-Informationen</b>\n\n";
            $message .= "⚡ <b>Preis pro kWh:</b> " . number_format($currentTariff['rate_per_kwh'], 4, ',', '.') . " €\n";
            
            if ($currentTariff['basic_fee']) {
                $message .= "🏠 <b>Grundgebühr:</b> " . number_format($currentTariff['basic_fee'], 2, ',', '.') . " €/Monat\n";
            }
            
            if ($currentTariff['monthly_payment']) {
                $message .= "💸 <b>Abschlag:</b> " . number_format($currentTariff['monthly_payment'], 2, ',', '.') . " €/Monat\n";
            }
            
            $validFrom = date('d.m.Y', strtotime($currentTariff['valid_from']));
            $validTo = $currentTariff['valid_to'] ? date('d.m.Y', strtotime($currentTariff['valid_to'])) : 'unbegrenzt';
            
            $message .= "\n📅 <b>Gültig:</b> $validFrom - $validTo\n";
            
            if ($currentTariff['notes']) {
                $message .= "\n📝 <b>Hinweise:</b> " . htmlspecialchars($currentTariff['notes']);
            }
            
            $message .= "\n\n💡 <i>Beispielrechnung für 100 kWh:</i>\n";
            $exampleCost = 100 * $currentTariff['rate_per_kwh'] + ($currentTariff['basic_fee'] ?? 0);
            $message .= "💸 <b>Kosten:</b> " . number_format($exampleCost, 2, ',', '.') . " €";
            
            return self::sendMessage($chatId, $message, 'HTML');
            
        } catch (Exception $e) {
            error_log("[BOT] Tariff info error: " . $e->getMessage());
            self::sendMessage($chatId, "❌ Fehler beim Laden der Tarif-Informationen.");
            return false;
        }
    }
    
    /**
     * Erfolgs-Nachricht senden
     */
    private static function sendSuccessMessage($userId, $chatId, $data) {
        $user = Database::fetchOne("SELECT name FROM users WHERE id = ?", [$userId]);
        $userName = $user['name'] ?? 'Stromtracker-Nutzer';
        
        $message = "✅ <b>Zählerstand erfasst!</b>\n\n";
        $message .= "👤 <b>" . htmlspecialchars($userName) . "</b>\n";
        $message .= "📊 <b>Neuer Stand:</b> " . number_format($data['reading'], 0, ',', '.') . " kWh\n";
        
        if ($data['consumption'] > 0) {
            $message .= "⚡ <b>Verbrauch:</b> " . number_format($data['consumption'], 0, ',', '.') . " kWh\n";
            $message .= "💰 <b>Stromkosten:</b> " . number_format($data['cost'], 2, ',', '.') . " €\n";
            $message .= "📄 <b>Gesamtrechnung:</b> " . number_format($data['total_bill'], 2, ',', '.') . " €\n";
            
            if ($data['payment_difference'] > 0) {
                $message .= "📈 <b>Nachzahlung:</b> +" . number_format($data['payment_difference'], 2, ',', '.') . " €\n";
            } elseif ($data['payment_difference'] < 0) {
                $message .= "📉 <b>Guthaben:</b> " . number_format(abs($data['payment_difference']), 2, ',', '.') . " €\n";
            }
            
            // Vergleich zum Vormonat
            if ($data['last_reading']) {
                $daysDiff = (strtotime('today') - strtotime($data['last_reading']['reading_date'])) / 86400;
                if ($daysDiff > 0) {
                    $dailyConsumption = $data['consumption'] / $daysDiff;
                    $message .= "\n📈 <b>Tagesverbrauch:</b> " . number_format($dailyConsumption, 1, ',', '.') . " kWh/Tag\n";
                }
            }
        }
        
        $message .= "\n📅 <b>Erfasst am:</b> " . date('d.m.Y H:i') . "\n";
        $message .= "\n💡 <i>Tipp: Senden Sie 'Status' für eine Übersicht</i>";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Status-Nachricht senden
     */
    private static function sendStatusMessage($userId, $chatId) {
        $user = Database::fetchOne("SELECT name FROM users WHERE id = ?", [$userId]);
        $userName = $user['name'] ?? 'Stromtracker-Nutzer';
        
        // Letzten Zählerstand holen
        $lastReading = Database::fetchOne(
            "SELECT * FROM meter_readings WHERE user_id = ? ORDER BY reading_date DESC LIMIT 1",
            [$userId]
        );
        
        // Jahresverbrauch
        $yearConsumption = Database::fetchOne(
            "SELECT SUM(consumption) as total FROM meter_readings 
             WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE())",
            [$userId]
        );
        
        $message = "📊 <b>Status für " . htmlspecialchars($userName) . "</b>\n\n";
        
        if ($lastReading) {
            $message .= "🔢 <b>Letzter Stand:</b> " . number_format($lastReading['meter_value'], 0, ',', '.') . " kWh\n";
            $message .= "📅 <b>Erfasst am:</b> " . date('d.m.Y', strtotime($lastReading['reading_date'])) . "\n";
            
            $daysSince = floor((time() - strtotime($lastReading['reading_date'])) / 86400);
            if ($daysSince > 0) {
                $message .= "⏱️ <b>Vor:</b> $daysSince Tag" . ($daysSince > 1 ? 'en' : '') . "\n";
            }
            
            if ($yearConsumption && $yearConsumption['total'] > 0) {
                $message .= "\n📈 <b>Jahresverbrauch:</b> " . number_format($yearConsumption['total'], 0, ',', '.') . " kWh\n";
            }
        } else {
            $message .= "❓ <b>Noch kein Zählerstand erfasst</b>\n\n";
            $message .= "💡 <i>Senden Sie einfach Ihren aktuellen Zählerstand!</i>";
        }
        
        $message .= "\n\n🤖 <b>Bot-Befehle:</b>\n";
        $message .= "• Zählerstand: <code>12450</code>\n";
        $message .= "• Mit Befehl: <code>/stand 12450</code>\n";
        $message .= "• Status: <code>/status</code>\n";
        $message .= "• Hilfe: <code>/help</code>";
        
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
        $message .= "• <code>Zählerstand 12450 kWh</code>\n";
        $message .= "• <code>/stand 12450</code>\n\n";
        
        $message .= "🛠️ <b>Bot-Befehle:</b>\n";
        $message .= "• <code>/status</code> oder <code>Status</code> - Aktueller Status\n";
        $message .= "• <code>/help</code> oder <code>Hilfe</code> - Diese Hilfe\n";
        $message .= "• <code>Verbrauch</code> - Statistiken anzeigen\n";
        $message .= "• <code>Tarif</code> - Tarif-Informationen\n\n";
        
        $message .= "🔧 <b>Erweiterte Funktionen:</b>\n";
        $message .= "• <code>Korrektur: 12450</code> - Heutigen Stand korrigieren\n";
        $message .= "• <code>Lösche heute</code> - Letzten Stand löschen\n\n";
        
        $message .= "✨ <b>Features:</b>\n";
        $message .= "• ✅ Automatische Berechnung von Verbrauch & Kosten\n";
        $message .= "• ✅ Validierung der Eingaben\n";
        $message .= "• ✅ Sofortige Bestätigung\n";
        $message .= "• ✅ Korrekturen & Löschungen\n";
        $message .= "• ✅ Verbrauchsstatistiken\n";
        $message .= "• ✅ Tarif-Informationen\n\n";
        
        $message .= "💡 <b>Beispiele:</b>\n";
        $message .= "• <code>12450</code> → Zählerstand erfassen\n";
        $message .= "• <code>Status</code> → Aktueller Stand\n";
        $message .= "• <code>Verbrauch</code> → Monatsstatistik\n";
        $message .= "• <code>Korrektur: 12500</code> → Heute korrigieren\n\n";
        
        $message .= "🚀 <i>Der Bot erkennt Zählerstände automatisch!</i>";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Welcome-Nachricht senden
     */
    private static function sendWelcomeMessage($userId, $chatId) {
        $user = Database::fetchOne("SELECT name FROM users WHERE id = ?", [$userId]);
        $userName = $user['name'] ?? 'Stromtracker-Nutzer';
        
        $message = "🔌 <b>Willkommen im Stromtracker Bot!</b>\n\n";
        $message .= "Hallo " . htmlspecialchars($userName) . "! 👋\n\n";
        $message .= "Sie können jetzt Zählerstände direkt über Telegram erfassen:\n\n";
        $message .= "📊 Einfach den aktuellen Zählerstand senden:\n";
        $message .= "• <code>12450</code>\n";
        $message .= "• <code>Stand: 12450</code>\n";
        $message .= "• <code>/stand 12450</code>\n\n";
        $message .= "Der Bot berechnet automatisch:\n";
        $message .= "• ⚡ Verbrauch seit letzter Ablesung\n";
        $message .= "• 💰 Stromkosten\n";
        $message .= "• 📊 Verbrauchstrends\n\n";
        $message .= "Senden Sie <code>/help</code> für weitere Informationen!";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Unbekannte Nachricht behandeln
     */
    private static function handleUnknownMessage($userId, $chatId, $text) {
        $message = "❓ <b>Nachricht nicht verstanden</b>\n\n";
        $message .= "💡 <b>Zählerstand erfassen:</b>\n";
        $message .= "Senden Sie einfach die Zahl, z.B. <code>12450</code>\n\n";
        $message .= "🆘 Senden Sie <code>/help</code> für weitere Hilfe.";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Nachricht an unbekannten Benutzer
     */
    private static function sendUnknownUserMessage($chatId) {
        $message = "❌ <b>Nicht autorisiert</b>\n\n";
        $message .= "Dieser Bot ist nur für verifizierte Stromtracker-Benutzer verfügbar.\n\n";
        $message .= "Bitte:\n";
        $message .= "1️⃣ Registrieren Sie sich auf der Website\n";
        $message .= "2️⃣ Konfigurieren Sie Telegram in Ihrem Profil\n";
        $message .= "3️⃣ Verifizieren Sie Ihre Chat-ID";
        
        return self::sendMessage($chatId, $message, 'HTML');
    }
    
    /**
     * Generische Nachricht senden (mit Benutzer-Bot-Token)
     */
    private static function sendMessage($chatId, $text, $parseMode = 'HTML') {
        try {
            error_log("[BOT] Sending message to $chatId: " . substr($text, 0, 100) . "...");
            
            // Bot-Token des Benutzers aus Chat-ID ermitteln
            $userBot = Database::fetchOne(
                "SELECT u.id as user_id, u.name, ns.telegram_bot_token 
                 FROM users u 
                 JOIN notification_settings ns ON u.id = ns.user_id 
                 WHERE ns.telegram_chat_id = ? 
                 AND ns.telegram_verified = 1 
                 AND ns.telegram_enabled = 1
                 AND ns.telegram_bot_token IS NOT NULL",
                [$chatId]
            );
            
            if (!$userBot || empty($userBot['telegram_bot_token'])) {
                error_log("[BOT] No bot token found for chat ID: $chatId");
                return false;
            }
            
            $botToken = $userBot['telegram_bot_token'];
            $userId = $userBot['user_id'];
            
            // Demo-Modus
            if ($botToken === 'demo') {
                error_log("[BOT] Demo mode - message logged but not sent for user $userId");
                // Nachricht trotzdem loggen
                self::logMessage($userId, $chatId, $text, 'notification', 'sent', null, 'demo');
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
                error_log("[BOT] Telegram API request failed for user $userId");
                self::logMessage($userId, $chatId, $text, 'notification', 'failed', null, 'API request failed');
                return false;
            }
            
            $decoded = json_decode($response, true);
            
            if (!isset($decoded['ok']) || !$decoded['ok']) {
                $error = $decoded['description'] ?? 'Unknown error';
                error_log("[BOT] Telegram API error for user $userId: $error");
                self::logMessage($userId, $chatId, $text, 'notification', 'failed', null, $error);
                return false;
            }
            
            $messageId = $decoded['result']['message_id'] ?? null;
            error_log("[BOT] Message sent successfully to user $userId: Message ID $messageId");
            
            // Erfolgreiches Senden loggen
            self::logMessage($userId, $chatId, $text, 'notification', 'sent', $messageId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("[BOT] Send message error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Telegram-Nachricht in Log-Tabelle speichern
     */
    private static function logMessage($userId, $chatId, $text, $type = 'notification', $status = 'sent', $messageId = null, $error = null) {
        try {
            // Prüfen ob telegram_log Tabelle existiert
            $tableExists = Database::fetchOne("SHOW TABLES LIKE 'telegram_log'");
            if (!$tableExists) {
                error_log("[BOT] telegram_log table does not exist - skipping log entry");
                return;
            }
            
            Database::insert('telegram_log', [
                'user_id' => $userId,
                'chat_id' => $chatId,
                'message_type' => $type,
                'message_text' => substr($text, 0, 1000), // Text kürzen
                'telegram_message_id' => $messageId,
                'status' => $status,
                'error_message' => $error,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            // Logging-Fehler nicht weiterwerfen
            error_log("[BOT] Failed to log message: " . $e->getMessage());
        }
    }
    
    /**
     * Error-Nachricht senden
     */
    private static function sendErrorMessage($chatId, $error) {
        return self::sendMessage($chatId, "❌ " . $error);
    }
}