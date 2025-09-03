<?php
// profil.php
// EINFACHE & SCHÖNE Profil-Verwaltung mit Profilbild-Upload, API-Key-Management und Benachrichtigungen + TELEGRAM

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/NotificationManager.php';
require_once 'includes/TelegramManager.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Profil - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// =============================================================================
// PROFILBILD UPLOAD-HANDLER (unverändert)
// =============================================================================

class ProfileImageHandler {
    
    private static $uploadDir = 'uploads/profile/';
    private static $maxFileSize = 2 * 1024 * 1024; // 2MB
    private static $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private static $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    /**
     * Upload und verarbeite Profilbild
     */
    public static function handleUpload($file, $userId) {
        
        // Upload-Ordner erstellen falls nicht vorhanden
        if (!is_dir(self::$uploadDir)) {
            mkdir(self::$uploadDir, 0755, true);
        }
        
        // .htaccess für Sicherheit erstellen
        self::createHtaccess();
        
        // Validierung
        $validation = self::validateUpload($file);
        if ($validation !== true) {
            return ['success' => false, 'error' => $validation];
        }
        
        // Eindeutigen Dateinamen generieren
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
        $filepath = self::$uploadDir . $filename;
        
        // Altes Profilbild löschen
        self::deleteOldImage($userId);
        
        // Datei hochladen
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Fehler beim Speichern der Datei.'];
        }
        
        // Bild verkleinern und optimieren
        $processResult = self::processImage($filepath);
        if (!$processResult) {
            unlink($filepath);
            return ['success' => false, 'error' => 'Fehler beim Verarbeiten des Bildes.'];
        }
        
        // In Datenbank speichern
        $dbResult = Database::update('users', [
            'profile_image' => $filename
        ], 'id = ?', [$userId]);
        
        if ($dbResult) {
            return ['success' => true, 'filename' => $filename];
        } else {
            unlink($filepath);
            return ['success' => false, 'error' => 'Fehler beim Speichern in der Datenbank.'];
        }
    }
    
    /**
     * Validiere Upload
     */
    private static function validateUpload($file) {
        
        // Allgemeine Upload-Fehler
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Upload-Fehler: ' . self::getUploadErrorMessage($file['error']);
        }
        
        // Dateigröße prüfen
        if ($file['size'] > self::$maxFileSize) {
            return 'Datei zu groß. Maximum: 2MB.';
        }
        
        // MIME-Type prüfen
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, self::$allowedTypes)) {
            return 'Nicht erlaubter Dateityp. Erlaubt: JPG, PNG, GIF';
        }
        
        // Dateiendung prüfen
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::$allowedExtensions)) {
            return 'Nicht erlaubte Dateiendung.';
        }
        
        // Bild-Validierung (echtes Bild?)
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return 'Datei ist kein gültiges Bild.';
        }
        
        // Maximale Bildgröße
        if ($imageInfo[0] > 2000 || $imageInfo[1] > 2000) {
            return 'Bild zu groß. Maximum: 2000x2000 Pixel.';
        }
        
        return true;
    }
    
    /**
     * Bild verarbeiten (verkleinern, optimieren)
     */
    private static function processImage($filepath) {
        try {
            $imageInfo = getimagesize($filepath);
            if (!$imageInfo) return false;
            
            $maxSize = 400; // Maximale Kantenlänge
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            // Skalierung berechnen
            if ($width <= $maxSize && $height <= $maxSize) {
                return true; // Bereits klein genug
            }
            
            $scale = min($maxSize / $width, $maxSize / $height);
            $newWidth = round($width * $scale);
            $newHeight = round($height * $scale);
            
            // Ursprungsbild laden
            switch ($mimeType) {
                case 'image/jpeg':
                    $source = imagecreatefromjpeg($filepath);
                    break;
                case 'image/png':
                    $source = imagecreatefrompng($filepath);
                    break;
                case 'image/gif':
                    $source = imagecreatefromgif($filepath);
                    break;
                default:
                    return false;
            }
            
            if (!$source) return false;
            
            // Neues Bild erstellen
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            
            // Transparenz für PNG/GIF beibehalten
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefill($resized, 0, 0, $transparent);
            }
            
            // Skalieren
            imagecopyresampled($resized, $source, 0, 0, 0, 0, 
                              $newWidth, $newHeight, $width, $height);
            
            // Speichern
            $result = false;
            switch ($mimeType) {
                case 'image/jpeg':
                    $result = imagejpeg($resized, $filepath, 85);
                    break;
                case 'image/png':
                    $result = imagepng($resized, $filepath, 6);
                    break;
                case 'image/gif':
                    $result = imagegif($resized, $filepath);
                    break;
            }
            
            // Speicher freigeben
            imagedestroy($source);
            imagedestroy($resized);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Image processing error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Altes Profilbild löschen
     */
    private static function deleteOldImage($userId) {
        $user = Database::fetchOne("SELECT profile_image FROM users WHERE id = ?", [$userId]);
        
        if ($user && !empty($user['profile_image'])) {
            $oldFile = self::$uploadDir . $user['profile_image'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }
    }
    
    /**
     * Profilbild-URL abrufen
     */
    public static function getImageUrl($filename) {
        if (empty($filename)) {
            return null;
        }
        
        $filepath = self::$uploadDir . $filename;
        if (file_exists($filepath)) {
            return $filepath . '?v=' . filemtime($filepath); // Cache-busting
        }
        
        return null;
    }
    
    /**
     * Profilbild löschen
     */
    public static function deleteImage($userId) {
        self::deleteOldImage($userId);
        
        return Database::update('users', [
            'profile_image' => null
        ], 'id = ?', [$userId]);
    }
    
    /**
     * .htaccess für Upload-Ordner erstellen
     */
    private static function createHtaccess() {
        $htaccessPath = self::$uploadDir . '.htaccess';
        
        if (!file_exists($htaccessPath)) {
            $content = "# Sicherheit für Upload-Ordner\n";
            $content .= "Options -ExecCGI\n";
            $content .= "AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
            $content .= "\n";
            $content .= "# Nur Bilder erlauben\n";
            $content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif)$\">\n";
            $content .= "    Order Allow,Deny\n";
            $content .= "    Allow from all\n";
            $content .= "</FilesMatch>\n";
            $content .= "\n";
            $content .= "<FilesMatch \"\\.(php|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">\n";
            $content .= "    Order Deny,Allow\n";
            $content .= "    Deny from all\n";
            $content .= "</FilesMatch>\n";
            
            file_put_contents($htaccessPath, $content);
        }
    }
    
    /**
     * Upload-Fehlermeldungen
     */
    private static function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Datei ist zu groß.';
            case UPLOAD_ERR_PARTIAL:
                return 'Datei wurde nur teilweise hochgeladen.';
            case UPLOAD_ERR_NO_FILE:
                return 'Keine Datei ausgewählt.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Kein temporärer Upload-Ordner.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Fehler beim Schreiben der Datei.';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload durch PHP-Erweiterung gestoppt.';
            default:
                return 'Unbekannter Upload-Fehler.';
        }
    }
}

// =============================================================================
// API-KEY MANAGER CLASS (unverändert)
// =============================================================================

class ApiKeyManager {
    
    /**
     * Generiert einen sicheren API-Key
     */
    public static function generateKey($prefix = 'st_') {
        return $prefix . bin2hex(random_bytes(30)); // 64 Zeichen total
    }
    
    /**
     * Validiert API-Key Format
     */
    public static function isValidFormat($key) {
        return preg_match('/^st_[a-f0-9]{60}$/', $key);
    }
    
    /**
     * Validiert API-Key gegen Datenbank
     */
    public static function validateApiKey($providedKey) {
        if (empty($providedKey) || !self::isValidFormat($providedKey)) {
            return false;
        }
        
        $user = Database::fetchOne(
            "SELECT id FROM users WHERE api_key = ? AND api_key IS NOT NULL", 
            [$providedKey]
        );
        
        return $user !== false;
    }
    
    /**
     * API-Key-Inhaber ermitteln
     */
    public static function getUserByApiKey($apiKey) {
        return Database::fetchOne(
            "SELECT id, name, email FROM users WHERE api_key = ?", 
            [$apiKey]
        );
    }
}

// =============================================================================
// FORM PROCESSING (erweitert um Telegram)
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF-Token prüfen
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Flash::error('Sicherheitsfehler. Bitte versuchen Sie es erneut.');
    } else {
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_profile':
                // Profil-Daten aktualisieren
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                
                if (empty($name) || empty($email)) {
                    Flash::error('Name und E-Mail sind Pflichtfelder.');
                } else {
                    // E-Mail bereits vorhanden prüfen (außer eigene)
                    $existingUser = Database::fetchOne(
                        "SELECT id FROM users WHERE email = ? AND id != ?",
                        [$email, $userId]
                    );
                    
                    if ($existingUser) {
                        Flash::error('Diese E-Mail-Adresse wird bereits verwendet.');
                    } else {
                        $success = Database::update('users', [
                            'name' => $name,
                            'email' => $email
                        ], 'id = ?', [$userId]);
                        
                        if ($success) {
                            Flash::success('Profil erfolgreich aktualisiert.');
                        } else {
                            Flash::error('Fehler beim Speichern der Änderungen.');
                        }
                    }
                }
                break;
                
            case 'upload_image':
                // Profilbild hochladen
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $result = ProfileImageHandler::handleUpload($_FILES['profile_image'], $userId);
                    
                    if ($result['success']) {
                        Flash::success('Profilbild erfolgreich hochgeladen.');
                    } else {
                        Flash::error($result['error']);
                    }
                } else {
                    Flash::error('Bitte wählen Sie eine Datei aus.');
                }
                break;
                
            case 'delete_image':
                // Profilbild löschen
                $success = ProfileImageHandler::deleteImage($userId);
                
                if ($success) {
                    Flash::success('Profilbild erfolgreich gelöscht.');
                } else {
                    Flash::error('Fehler beim Löschen des Profilbildes.');
                }
                break;
                
            case 'change_password':
                // Passwort ändern
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    Flash::error('Alle Passwort-Felder sind erforderlich.');
                } elseif ($newPassword !== $confirmPassword) {
                    Flash::error('Die neuen Passwörter stimmen nicht überein.');
                } elseif (strlen($newPassword) < 6) {
                    Flash::error('Das neue Passwort muss mindestens 6 Zeichen lang sein.');
                } else {
                    // Aktuelles Passwort prüfen
                    $userData = Database::fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);
                    
                    if ($userData && password_verify($currentPassword, $userData['password'])) {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $success = Database::update('users', [
                            'password' => $hashedPassword
                        ], 'id = ?', [$userId]);
                        
                        if ($success) {
                            Flash::success('Passwort erfolgreich geändert.');
                        } else {
                            Flash::error('Fehler beim Ändern des Passworts.');
                        }
                    } else {
                        Flash::error('Das aktuelle Passwort ist falsch.');
                    }
                }
                break;
                
            case 'generate_api_key':
                // Neuen API-Key generieren
                $newApiKey = ApiKeyManager::generateKey();
                $success = Database::update('users', [
                    'api_key' => $newApiKey
                ], 'id = ?', [$userId]);
                
                if ($success) {
                    Flash::success('API-Key erfolgreich generiert!');
                } else {
                    Flash::error('Fehler beim Generieren des API-Keys.');
                }
                break;
                
            case 'delete_api_key':
                // API-Key löschen
                $success = Database::update('users', [
                    'api_key' => null
                ], 'id = ?', [$userId]);
                
                if ($success) {
                    Flash::success('API-Key erfolgreich gelöscht.');
                } else {
                    Flash::error('Fehler beim Löschen des API-Keys.');
                }
                break;
                
            case 'update_notifications':
                // Benachrichtigungseinstellungen speichern (inkl. Telegram)
                try {
                    $notificationSettings = [
                        'email_notifications' => isset($_POST['email_notifications']),
                        'reading_reminder_enabled' => isset($_POST['reading_reminder_enabled']),
                        'reading_reminder_days' => max(1, min(15, (int)($_POST['reading_reminder_days'] ?? 5))),
                        'high_usage_alert' => isset($_POST['high_usage_alert']),
                        'high_usage_threshold' => max(50, (float)($_POST['high_usage_threshold'] ?? 200)),
                        'cost_alert_enabled' => isset($_POST['cost_alert_enabled']),
                        'cost_alert_threshold' => max(10, (float)($_POST['cost_alert_threshold'] ?? 100)),
                        
                        // Telegram-Einstellungen (korrigiert)
                        'telegram_enabled' => isset($_POST['telegram_enabled']),
                        'telegram_chat_id' => trim($_POST['telegram_chat_id'] ?? '')
                    ];
                    
                    // Debug-Logging hinzufügen
                    error_log("[Profil] update_notifications - telegram_chat_id: '" . $notificationSettings['telegram_chat_id'] . "'");
                    
                    // TABELLEN-EXISTENZ PRÜFEN
                    $tableExists = Database::fetchOne("SHOW TABLES LIKE 'notification_settings'");
                    if (!$tableExists) {
                        Flash::error('❌ KRITISCH: Datenbanktabellen fehlen! Führen Sie sql/telegram-setup.sql aus.');
                        break;
                    }
                    
                    $success = NotificationManager::saveUserSettings($userId, $notificationSettings);
                    
                    if ($success) {
                        Flash::success('Benachrichtigungseinstellungen erfolgreich gespeichert.');
                    } else {
                        Flash::error('Fehler beim Speichern der Benachrichtigungseinstellungen.');
                    }
                    
                } catch (Exception $e) {
                    error_log("Notification settings error: " . $e->getMessage());
                    Flash::error('Systemfehler beim Speichern der Benachrichtigungen.');
                }
                break;
                
            case 'telegram_verify':
                // Telegram Verifizierung starten (REPARIERT + DEBUG)
                try {
                    error_log("=== TELEGRAM VERIFY START ===");
                    $chatId = trim($_POST['telegram_chat_id'] ?? '');
                    
                    if (empty($chatId)) {
                        Flash::error('Bitte geben Sie eine Chat-ID ein.');
                        break;
                    }
                    
                    error_log("[VERIFY] Chat ID: " . $chatId);
                    
                    // Chat-ID Format validieren
                    if (!preg_match('/^-?\d{5,15}$/', $chatId)) {
                        Flash::error('Ungültige Chat-ID. Nur Zahlen erlaubt (z.B. 123456789).');
                        break;
                    }
                    
                    if (!TelegramManager::isEnabled()) {
                        Flash::error('Telegram ist nicht aktiviert.');
                        break;
                    }
                    
                    // Bot-Token prüfen
                    $userSettings = Database::fetchOne(
                        "SELECT telegram_bot_token FROM notification_settings WHERE user_id = ?",
                        [$userId]
                    );
                    
                    if (!$userSettings || empty($userSettings['telegram_bot_token'])) {
                        Flash::error('Bitte konfigurieren Sie zuerst Ihren Bot-Token.');
                        break;
                    }
                    
                    // Alte pending Codes löschen
                    Database::execute(
                        "UPDATE telegram_log SET status = 'expired' WHERE user_id = ? AND message_type = 'verification' AND status = 'pending'",
                        [$userId]
                    );
                    error_log("[VERIFY] Old pending codes expired");
                    
                    // Verifizierungscode generieren
                    $verificationCode = sprintf('%06d', mt_rand(100000, 999999));
                    error_log("[VERIFY] Generated code: " . $verificationCode);
                    
                    // Code senden
                    $success = TelegramManager::sendUserVerificationCode($userId, $chatId, $verificationCode);
                    error_log("[VERIFY] Send result: " . ($success ? 'SUCCESS' : 'FAILED'));
                    
                    if ($success) {
                        // Code in telegram_log speichern
                        $insertResult = Database::insert('telegram_log', [
                            'user_id' => $userId,
                            'chat_id' => $chatId,
                            'message_type' => 'verification',
                            'message_text' => $verificationCode,
                            'status' => 'pending'
                        ]);
                        
                        error_log("[VERIFY] Code insert result: " . ($insertResult ? 'SUCCESS' : 'FAILED'));
                        
                        // Verifikation: Code wieder auslesen
                        $verification = Database::fetchOne(
                            "SELECT message_text FROM telegram_log WHERE id = ?",
                            [$insertResult]
                        );
                        error_log("[VERIFY] Code verification: '" . ($verification['message_text'] ?? 'NULL') . "'");
                        
                        // Chat-ID speichern (unverified)
                        Database::update(
                            'notification_settings',
                            [
                                'telegram_chat_id' => $chatId,
                                'telegram_verified' => 0
                            ],
                            'user_id = ?',
                            [$userId]
                        );
                        
                        Flash::success('Verifizierungscode wurde gesendet! Geben Sie den Code ein.');
                        
                    } else {
                        Flash::error('Code konnte nicht gesendet werden. Prüfen Sie Bot-Token und Chat-ID.');
                    }
                    
                } catch (Exception $e) {
                    Flash::error('Verifizierung fehlgeschlagen: ' . $e->getMessage());
                    error_log("[VERIFY] Error: " . $e->getMessage());
                }
                break;
                
            case 'telegram_confirm':
                // Telegram Verifizierungscode bestätigen (REPARIERT + DEBUG)
                try {
                    error_log("=== TELEGRAM CONFIRM START ===");
                    $verificationCode = trim($_POST['verification_code'] ?? '');
                    
                    if (empty($verificationCode)) {
                        Flash::error('Bitte geben Sie den Verifizierungscode ein.');
                        break;
                    }
                    
                    error_log("[CONFIRM] Code eingegeben: '" . $verificationCode . "' (Length: " . strlen($verificationCode) . ")");
                    
                    // ALLE pending codes für User anzeigen (Debug)
                    $allPending = Database::fetchAll(
                        "SELECT id, message_text, created_at FROM telegram_log 
                         WHERE user_id = ? AND message_type = 'verification' AND status = 'pending'
                         ORDER BY created_at DESC",
                        [$userId]
                    );
                    
                    error_log("[CONFIRM] Found " . count($allPending) . " pending codes:");
                    foreach ($allPending as $idx => $pending) {
                        error_log("[CONFIRM] Code #" . ($idx+1) . ": '" . $pending['message_text'] . "' (ID: " . $pending['id'] . ", Created: " . $pending['created_at'] . ")");
                    }
                    
                    // Code aus telegram_log abrufen
                    $storedCodeRecord = Database::fetchOne(
                        "SELECT id, message_text, created_at FROM telegram_log 
                         WHERE user_id = ? AND message_type = 'verification' AND status = 'pending'
                         ORDER BY created_at DESC LIMIT 1",
                        [$userId]
                    );
                    
                    $storedCode = $storedCodeRecord ? $storedCodeRecord['message_text'] : null;
                    error_log("[CONFIRM] Latest stored code: '" . $storedCode . "' (Length: " . strlen($storedCode ?? '') . ")");
                    
                    if (!$storedCode) {
                        Flash::error('Kein gültiger Verifizierungscode gefunden. Senden Sie einen neuen Code.');
                        break;
                    }
                    
                    // String-Vergleich mit Debug
                    $codesMatch = ($verificationCode === $storedCode);
                    error_log("[CONFIRM] String comparison: '" . $verificationCode . "' === '" . $storedCode . "' = " . ($codesMatch ? 'TRUE' : 'FALSE'));
                    
                    if (!$codesMatch) {
                        // Zusätzliche Debug-Info
                        error_log("[CONFIRM] Hex comparison:");
                        error_log("[CONFIRM] Input hex: " . bin2hex($verificationCode));
                        error_log("[CONFIRM] Stored hex: " . bin2hex($storedCode));
                        
                        Flash::error('Ungültiger Verifizierungscode. Prüfen Sie die Eingabe.');
                        break;
                    }
                    
                    // Code als verwendet markieren
                    $updateResult = Database::update(
                        'telegram_log',
                        ['status' => 'used'],
                        'user_id = ? AND message_type = ? AND status = ?',
                        [$userId, 'verification', 'pending']
                    );
                    
                    error_log("[CONFIRM] Code mark as used: " . ($updateResult ? 'SUCCESS' : 'FAILED'));
                    
                    // Chat-ID als verifiziert UND aktiviert markieren
                    $verifyResult = Database::update(
                        'notification_settings',
                        [
                            'telegram_verified' => 1,
                            'telegram_enabled' => 1  // WICHTIG: Auch aktivieren!
                        ],
                        'user_id = ?',
                        [$userId]
                    );
                    
                    error_log("[CONFIRM] Verification update result: " . ($verifyResult ? 'SUCCESS' : 'FAILED'));
                    
                    if ($verifyResult) {
                        Flash::success('🎉 Telegram erfolgreich verifiziert und aktiviert! Benachrichtigungen sind jetzt aktiv.');
                    } else {
                        Flash::error('Code korrekt, aber Aktivierung fehlgeschlagen.');
                    }
                    
                } catch (Exception $e) {
                    Flash::error('Bestätigung fehlgeschlagen: ' . $e->getMessage());
                    error_log("[CONFIRM] Error: " . $e->getMessage());
                }
                break;
                
            case 'telegram_save_bot':
                // Bot-Token speichern
                try {
                    $botToken = trim($_POST['telegram_bot_token'] ?? '');
                    
                    if (empty($botToken)) {
                        Flash::error('Bitte geben Sie ein Bot-Token ein.');
                        break;
                    }
                    
                    if ($botToken !== 'demo' && !TelegramManager::validateBotToken($botToken)) {
                        Flash::error('Ungültiges Bot-Token Format. Erwartetes Format: 123456789:ABCdefGHijKLmnopQRstuvwxyz');
                        break;
                    }
                    
                    // API-Validierung (außer Demo-Token)
                    if ($botToken !== 'demo' && !TelegramManager::validateBotTokenAPI($botToken)) {
                        Flash::error('Bot-Token ist ungültig oder Bot nicht erreichbar. Prüfen Sie das Token.');
                        break;
                    }
                    
                    $success = TelegramManager::saveUserBot($userId, $botToken);
                    
                    if ($success) {
                        Flash::success('Bot-Token erfolgreich gespeichert! Chat-ID muss neu verifiziert werden.');
                    } else {
                        Flash::error('Unbekannter Fehler beim Speichern des Bot-Tokens.');
                    }
                    
                } catch (Exception $e) {
                    error_log("Telegram bot save error for user {$userId}: " . $e->getMessage());
                    Flash::error('Bot-Token Fehler: ' . $e->getMessage());
                }
                break;
                
            case 'telegram_remove_bot':
                // Bot-Token entfernen
                try {
                    $success = TelegramManager::removeUserBot($userId);
                    if ($success) {
                        Flash::success('Bot-Token erfolgreich entfernt.');
                    } else {
                        Flash::error('Fehler beim Entfernen des Bot-Tokens.');
                    }
                    
                } catch (Exception $e) {
                    Flash::error('Fehler: ' . $e->getMessage());
                }
                break;
                
            case 'telegram_test':
                // Test-Telegram senden
                try {
                    $success = TelegramManager::sendUserTestMessage($userId);
                    
                    if ($success) {
                        Flash::success('Test-Nachricht erfolgreich an Telegram gesendet!');
                    } else {
                        Flash::error('Test-Nachricht konnte nicht gesendet werden.');
                    }
                    
                } catch (Exception $e) {
                    Flash::error('Test fehlgeschlagen: ' . $e->getMessage());
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: profil.php');
    exit;
}

// =============================================================================
// DATA LOADING (erweitert um Telegram)
// =============================================================================

// User-Daten laden (mit API-Key)
$userData = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]) ?: [];
$userApiKey = $userData['api_key'] ?? null;

// Benachrichtigungseinstellungen laden (inkl. Telegram)
$notificationSettings = NotificationManager::getUserSettings($userId);
$notificationStats = NotificationManager::getNotificationStats($userId);

// Telegram-System verfügbar?
$telegramEnabled = TelegramManager::isEnabled();
$telegramBotInfo = null;
$telegramUserSettings = [];
if ($telegramEnabled) {
    try {
        $telegramBotInfo = TelegramManager::getBotInfo();
        $telegramUserSettings = TelegramManager::getUserTelegramSettings($userId);
        
        // Bot-Info für Benutzer laden falls Bot-Token vorhanden
        if (!empty($telegramUserSettings['telegram_bot_token'])) {
            $userBotInfo = TelegramManager::getUserBotInfo($userId);
            if ($userBotInfo) {
                $telegramBotInfo = $userBotInfo; // User-Bot hat Priorität
            }
        }
    } catch (Exception $e) {
        error_log("Telegram bot info error: " . $e->getMessage());
    }
}

// Prüfen ob Erinnerung benötigt wird
$reminderCheck = NotificationManager::needsReadingReminder($userId);

// Account-Statistiken für Info-Tab
$accountStats = [
    'total_readings' => 0,
    'total_devices' => 0,
    'total_tariffs' => 0,
    'registration_date' => $userData['created_at'] ?? date('Y-m-d')
];

try {
    $accountStats['total_readings'] = Database::fetchOne(
        "SELECT COUNT(*) as count FROM meter_readings WHERE user_id = ?", 
        [$userId]
    )['count'] ?? 0;
    
    $accountStats['total_devices'] = Database::fetchOne(
        "SELECT COUNT(*) as count FROM devices WHERE user_id = ?", 
        [$userId]
    )['count'] ?? 0;
    
    $accountStats['total_tariffs'] = Database::fetchOne(
        "SELECT COUNT(*) as count FROM tariff_periods WHERE user_id = ?", 
        [$userId]
    )['count'] ?? 0;
} catch (Exception $e) {
    error_log("Account stats error: " . $e->getMessage());
}

// Tage seit Registrierung
$daysSinceRegistration = floor((time() - strtotime($accountStats['registration_date'])) / 86400);

include 'includes/header.php';
include 'includes/navbar.php';
?>

<!-- Profil Content -->
<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-energy mb-2">
                            <span class="energy-indicator"></span>
                            <i class="bi bi-person-circle"></i>
                            Mein Profil
                        </h1>
                        <p class="text-muted mb-0">
                            Verwalten Sie Ihre persönlichen Daten und Account-Einstellungen.
                        </p>
                        
                        <!-- Erinnerungs-Status -->
                        <?php if ($reminderCheck['needed']): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Erinnerung:</strong> <?= htmlspecialchars($reminderCheck['message']) ?>
                            <a href="zaehlerstand.php" class="btn btn-sm btn-warning ms-2">
                                <i class="bi bi-speedometer2"></i> Jetzt erfassen
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="profile-header-avatar">
                            <?php
                            $currentImage = ProfileImageHandler::getImageUrl($userData['profile_image']);
                            if ($currentImage):
                            ?>
                                <img src="<?= htmlspecialchars($currentImage) ?>" 
                                     alt="Profilbild" 
                                     class="profile-header-image">
                            <?php else: ?>
                                <div class="profile-header-placeholder">
                                    <?= strtoupper(substr($userData['name'] ?? 'User', 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profil Tabs -->
    <div class="row">
        <div class="col-12">
            
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" 
                            data-bs-target="#profile" type="button" role="tab">
                        <i class="bi bi-person me-2"></i>Profil
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" 
                            data-bs-target="#notifications" type="button" role="tab">
                        <i class="bi bi-bell me-2"></i>Benachrichtigungen
                        <?php if ($notificationSettings['reading_reminder_enabled']): ?>
                        <span class="badge bg-success ms-1">●</span>
                        <?php endif; ?>
                        <?php if ($telegramEnabled && $notificationSettings['telegram_enabled'] && $notificationSettings['telegram_verified']): ?>
                        <span class="badge bg-info ms-1">📱</span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="image-tab" data-bs-toggle="tab" 
                            data-bs-target="#image" type="button" role="tab">
                        <i class="bi bi-image me-2"></i>Profilbild
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="password-tab" data-bs-toggle="tab" 
                            data-bs-target="#password" type="button" role="tab">
                        <i class="bi bi-lock me-2"></i>Passwort
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="api-tab" data-bs-toggle="tab" 
                            data-bs-target="#api" type="button" role="tab">
                        <i class="bi bi-key me-2"></i>API-Keys
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="account-tab" data-bs-toggle="tab" 
                            data-bs-target="#account" type="button" role="tab">
                        <i class="bi bi-info-circle me-2"></i>Account
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="profileTabContent">
                
                <!-- Profil Tab (unverändert) -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-person text-energy"></i>
                                Profil-Informationen
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($userData['name'] ?? '') ?>" 
                                               required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">E-Mail-Adresse</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($userData['email'] ?? '') ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Profil Aktualisieren
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Benachrichtigungen Tab (erweitert um Telegram) -->
                <div class="tab-pane fade" id="notifications" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-bell text-energy"></i>
                                Benachrichtigungen & Erinnerungen
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- Aktueller Status (erweitert) -->
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <div class="alert alert-info">
                                        <h6><i class="bi bi-info-circle"></i> Status</h6>
                                        <p class="mb-1">
                                            <strong>📧 E-Mail:</strong> 
                                            <?= $notificationSettings['email_notifications'] ? '✅ Aktiv' : '❌ Deaktiviert' ?>
                                        </p>
                                        <?php if ($telegramEnabled): ?>
                                        <p class="mb-1">
                                            <strong>📱 Telegram:</strong> 
                                            <?php if ($notificationSettings['telegram_enabled'] && $notificationSettings['telegram_verified']): ?>
                                                ✅ Aktiv & Verifiziert
                                            <?php elseif ($notificationSettings['telegram_enabled']): ?>
                                                ⚠️ Aktiv, nicht verifiziert
                                            <?php else: ?>
                                                ❌ Deaktiviert
                                            <?php endif; ?>
                                        </p>
                                        <?php endif; ?>
                                        <p class="mb-1">
                                            <strong>🔔 Erinnerungen:</strong> 
                                            <?= $notificationSettings['reading_reminder_enabled'] ? '✅ Aktiv' : '❌ Deaktiviert' ?>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Letzte Benachrichtigung:</strong><br>
                                            <small><?= $notificationSettings['last_reminder_date'] ? 
                                                date('d.m.Y', strtotime($notificationSettings['last_reminder_date'])) : 
                                                'Noch keine' ?></small>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <!-- Erinnerungs-Check -->
                                    <?php if ($reminderCheck['needed']): ?>
                                    <div class="alert alert-warning">
                                        <h6><i class="bi bi-exclamation-triangle"></i> Erinnerung fällig</h6>
                                        <p><?= htmlspecialchars($reminderCheck['message']) ?></p>
                                        <?php if (isset($reminderCheck['suggested_date'])): ?>
                                        <p class="mb-2">
                                            <strong>Vorgeschlagenes Datum:</strong> 
                                            <?= date('d.m.Y', strtotime($reminderCheck['suggested_date'])) ?>
                                        </p>
                                        <?php endif; ?>
                                        <a href="zaehlerstand.php" class="btn btn-warning">
                                            <i class="bi bi-speedometer2"></i> Zählerstand erfassen
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-success">
                                        <h6><i class="bi bi-check-circle"></i> Alles aktuell</h6>
                                        <p class="mb-0">Momentan sind keine Erinnerungen fällig.</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Einstellungs-Form (erweitert um Telegram) -->
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update_notifications">
                                
                                <h6>📧 E-Mail Benachrichtigungen</h6>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   id="email_notifications" name="email_notifications"
                                                   <?= $notificationSettings['email_notifications'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="email_notifications">
                                                <i class="bi bi-envelope-at"></i> E-Mail-Benachrichtigungen aktivieren
                                            </label>
                                        </div>
                                        <small class="text-muted">Klassische E-Mail-Benachrichtigungen</small>
                                    </div>
                                </div>
                                
                                <?php if ($telegramEnabled): ?>
                                <hr>
                                
                                <h6>📱 Telegram Benachrichtigungen</h6>
                                
                                <!-- Bot-Token Konfiguration -->
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6><i class="bi bi-robot"></i> Ihr persönlicher Bot</h6>
                                        
                                        <?php if (!empty($telegramUserSettings['telegram_bot_token'])): ?>
                                        <!-- Bot bereits konfiguriert -->
                                        <div class="alert alert-success mb-2">
                                            <div class="row align-items-center">
                                                <div class="col-md-8">
                                                    <strong>✅ Bot konfiguriert:</strong> @<?= htmlspecialchars($telegramBotInfo['username'] ?? 'unbekannt') ?><br>
                                                    <small class="text-muted">
                                                        Token: <?= substr($telegramUserSettings['telegram_bot_token'], 0, 15) ?>...
                                                        <?php if ($telegramUserSettings['telegram_bot_token'] === 'demo'): ?>
                                                        <span class="badge bg-warning">DEMO</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="collapse" data-bs-target="#changeBotForm">
                                                        <i class="bi bi-pencil"></i> Ändern
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="action" value="telegram_remove_bot">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Bot-Token wirklich entfernen?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="collapse" id="changeBotForm">
                                        <?php else: ?>
                                        <!-- Kein Bot konfiguriert -->
                                        <div class="alert alert-info mb-2">
                                            <strong><i class="bi bi-info-circle"></i> Kein Bot konfiguriert</strong><br>
                                            Erstellen Sie einen eigenen Telegram-Bot für persönliche Benachrichtigungen.
                                        </div>
                                        <?php endif; ?>
                                        
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="action" value="telegram_save_bot">
                                                
                                                <div class="row">
                                                    <div class="col-md-8 mb-3">
                                                        <label for="telegram_bot_token" class="form-label">
                                                            <i class="bi bi-key"></i> Bot-Token
                                                        </label>
                                                        <input type="text" class="form-control font-monospace" 
                                                               id="telegram_bot_token" name="telegram_bot_token"
                                                               placeholder="123456789:ABCdefGHijKLmnopQRstuvwxyz oder 'demo'"
                                                               value="<?= htmlspecialchars($telegramUserSettings['telegram_bot_token'] ?? '') ?>">
                                                        <small class="text-muted">
                                                            🤖 Erstellen Sie einen Bot bei @BotFather oder verwenden Sie 'demo' zum Testen
                                                        </small>
                                                    </div>
                                                    <div class="col-md-4 mb-3 d-flex align-items-end">
                                                        <button type="submit" class="btn btn-primary w-100">
                                                            <i class="bi bi-check-circle"></i> Bot Speichern
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                            
                                        <?php if (!empty($telegramUserSettings['telegram_bot_token'])): ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($telegramUserSettings['telegram_bot_token'])): ?>
                                <!-- Chat-ID und Aktivierung nur wenn Bot konfiguriert -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   id="telegram_enabled" name="telegram_enabled"
                                                   <?= $notificationSettings['telegram_enabled'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="telegram_enabled">
                                                <i class="bi bi-telegram"></i> Telegram-Benachrichtigungen aktivieren
                                            </label>
                                        </div>
                                        <small class="text-muted">Sofortige Push-Benachrichtigungen über Telegram</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="telegram_chat_id" class="form-label">Chat-ID</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control font-monospace" 
                                                   id="telegram_chat_id" name="telegram_chat_id"
                                                   value="<?= htmlspecialchars($notificationSettings['telegram_chat_id'] ?? '') ?>"
                                                   placeholder="z.B. 123456789">
                                            <button class="btn btn-outline-info" type="button" 
                                                    data-bs-toggle="modal" data-bs-target="#telegramHelpModal">
                                                <i class="bi bi-question-circle"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">
                                            Starten Sie @<?= $telegramBotInfo['username'] ?? 'ihren_bot' ?> in Telegram
                                        </small>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Telegram Verifizierung (nur wenn Bot konfiguriert) -->
                                <?php if (!empty($telegramUserSettings['telegram_bot_token']) && $notificationSettings['telegram_chat_id'] && !$notificationSettings['telegram_verified']): ?>
                                <div class="alert alert-warning">
                                    <h6><i class="bi bi-shield-exclamation"></i> Verifizierung erforderlich</h6>
                                    <p>Ihre Chat-ID muss verifiziert werden, bevor Telegram-Benachrichtigungen aktiviert werden können.</p>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="action" value="telegram_verify">
                                                <input type="hidden" name="telegram_chat_id" value="<?= htmlspecialchars($notificationSettings['telegram_chat_id']) ?>">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-send"></i> Verifizierungscode senden
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-6">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="action" value="telegram_confirm">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="verification_code"
                                                           placeholder="6-stelliger Code aus Telegram" required>
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="bi bi-check-circle"></i> Bestätigen
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Telegram Test (nur wenn Bot konfiguriert und verifiziert) -->
                                <?php if (!empty($telegramUserSettings['telegram_bot_token']) && $notificationSettings['telegram_enabled'] && $notificationSettings['telegram_verified']): ?>
                                <div class="alert alert-success">
                                    <h6><i class="bi bi-check-circle"></i> Telegram ist bereit!</h6>
                                    <p class="mb-2">Ihre Telegram-Benachrichtigungen sind aktiv und verifiziert.</p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="telegram_test">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-chat-dots"></i> Test-Nachricht senden
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                                
                                <?php else: ?>
                                
                                <hr>
                                
                                <div class="alert alert-secondary">
                                    <h6><i class="bi bi-info-circle"></i> Telegram nicht verfügbar</h6>
                                    <p class="mb-0">Das Telegram-System ist nicht aktiviert. Bitte kontaktieren Sie den Administrator.</p>
                                </div>
                                
                                <?php endif; ?>
                                
                                <hr>
                                
                                <h6>🔔 Zählerstand-Erinnerungen</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   id="reading_reminder_enabled" name="reading_reminder_enabled"
                                                   <?= $notificationSettings['reading_reminder_enabled'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="reading_reminder_enabled">
                                                <i class="bi bi-calendar-check"></i> Monatliche Zählerstand-Erinnerung
                                            </label>
                                        </div>
                                        <small class="text-muted">Erinnert Sie daran, den Zählerstand zu erfassen</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="reading_reminder_days" class="form-label">Erinnerung (Tage vor Monatsende)</label>
                                        <select class="form-select" id="reading_reminder_days" name="reading_reminder_days">
                                            <?php for($i = 1; $i <= 10; $i++): ?>
                                            <option value="<?= $i ?>" <?= $notificationSettings['reading_reminder_days'] == $i ? 'selected' : '' ?>>
                                                <?= $i ?> Tag<?= $i > 1 ? 'e' : '' ?>
                                            </option>
                                            <?php endfor; ?>
                                        </select>
                                        <small class="text-muted">Wann soll die Erinnerung gesendet werden?</small>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <h6>⚠️ Verbrauchsalarme (Optional)</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   id="high_usage_alert" name="high_usage_alert"
                                                   <?= $notificationSettings['high_usage_alert'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="high_usage_alert">
                                                <i class="bi bi-lightning-charge-fill"></i> Hoher Verbrauch-Alarm
                                            </label>
                                        </div>
                                        <small class="text-muted">Warnung bei überdurchschnittlichem Verbrauch</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="high_usage_threshold" class="form-label">Grenzwert (kWh/Monat)</label>
                                        <input type="number" class="form-control" id="high_usage_threshold" 
                                               name="high_usage_threshold" min="50" max="1000" step="10"
                                               value="<?= $notificationSettings['high_usage_threshold'] ?>">
                                        <small class="text-muted">Ab welchem monatlichen Verbrauch alarmieren?</small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" 
                                                   id="cost_alert_enabled" name="cost_alert_enabled"
                                                   <?= $notificationSettings['cost_alert_enabled'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="cost_alert_enabled">
                                                <i class="bi bi-currency-euro"></i> Kostenalarm
                                            </label>
                                        </div>
                                        <small class="text-muted">Warnung bei hohen monatlichen Kosten</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="cost_alert_threshold" class="form-label">Grenzwert (Euro/Monat)</label>
                                        <input type="number" class="form-control" id="cost_alert_threshold" 
                                               name="cost_alert_threshold" min="10" max="500" step="5"
                                               value="<?= $notificationSettings['cost_alert_threshold'] ?>">
                                        <small class="text-muted">Ab welchen monatlichen Kosten alarmieren?</small>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-check-circle"></i> Alle Einstellungen Speichern
                                </button>
                            </form>
                            
                            <!-- Statistiken (erweitert um Telegram) -->
                            <div class="card bg-light mt-4">
                                <div class="card-body">
                                    <h6><i class="bi bi-bar-chart"></i> Benachrichtigungsstatistik</h6>
                                    <div class="row">
                                        <?php if (isset($notificationStats['counts']['email'])): ?>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <div class="fs-5 text-primary"><?= $notificationStats['counts']['email']['sent'] ?></div>
                                                <small>📧 E-Mail</small>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (isset($notificationStats['counts']['telegram'])): ?>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <div class="fs-5 text-info"><?= $notificationStats['counts']['telegram']['sent'] ?></div>
                                                <small>📱 Telegram</small>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <div class="fs-5 text-success"><?= $notificationStats['counts']['sent'] ?></div>
                                                <small>Gesendet</small>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <div class="fs-5 text-danger"><?= $notificationStats['counts']['failed'] ?></div>
                                                <small>Fehlgeschlagen</small>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <div class="fs-5 text-muted"><?= $notificationStats['counts']['total'] ?></div>
                                                <small>Gesamt</small>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="text-center">
                                                <div class="fs-5 text-success">
                                                    <?= $notificationStats['counts']['total'] > 0 ? 
                                                        round(($notificationStats['counts']['sent'] / $notificationStats['counts']['total']) * 100) : 100 ?>%
                                                </div>
                                                <small>Erfolgsrate</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Weitere Tabs unverändert... (Profilbild, Passwort, API-Keys, Account) -->
                <!-- [Hier würden die anderen Tabs folgen - gekürzt für Übersichtlichkeit] -->
                
                <!-- API-Keys Tab (beispielhaft eingefügt) -->
                <div class="tab-pane fade" id="api" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-key text-energy"></i>
                                API-Key Verwaltung
                                <span class="badge bg-info ms-2">Tasmota Integration</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <?php if (!empty($userApiKey)): ?>
                            <!-- Bestehender API-Key -->
                            <div class="alert alert-success">
                                <h6><i class="bi bi-check-circle"></i> API-Key aktiv</h6>
                                <p class="mb-2">Ihr persönlicher API-Key für die Tasmota-Integration:</p>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control font-monospace" 
                                           id="current-api-key" value="<?= htmlspecialchars($userApiKey) ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" 
                                            onclick="copyApiKey()" title="In Zwischenablage kopieren">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                
                                <!-- API-Key Aktionen -->
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="generate_api_key">
                                            <button type="submit" class="btn btn-warning w-100"
                                                    onclick="return confirm('Neuen API-Key generieren?\n\nDer alte Key wird ungültig!')">
                                                <i class="bi bi-arrow-clockwise"></i> Neu Generieren
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="delete_api_key">
                                            <button type="submit" class="btn btn-danger w-100"
                                                    onclick="return confirm('API-Key wirklich löschen?\n\nDie Tasmota-Integration wird deaktiviert!')">
                                                <i class="bi bi-trash"></i> Löschen
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <?php else: ?>
                            <!-- Kein API-Key vorhanden -->
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle"></i> Kein API-Key vorhanden</h6>
                                <p>Generieren Sie einen API-Key für die automatische Datenübertragung von Tasmota-Geräten.</p>
                                
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="generate_api_key">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-plus-circle"></i> API-Key Generieren
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
                
            </div> <!-- Tab Content Ende -->
        </div>
    </div>
</div>

<!-- Telegram Hilfe Modal -->
<div class="modal fade" id="telegramHelpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-telegram text-info"></i> 
                    Telegram-Benachrichtigungen einrichten
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>📱 Schritt 1: Bot starten</h6>
                        <ol>
                            <li>Öffnen Sie Telegram</li>
                            <li>Suchen Sie nach: <code>@<?= $telegramBotInfo['username'] ?? 'stromtracker_bot' ?></code></li>
                            <li>Starten Sie den Chat mit <code>/start</code></li>
                            <li>Der Bot zeigt Ihre Chat-ID an</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>🔢 Schritt 2: Chat-ID kopieren</h6>
                        <p>Der Bot antwortet mit einer Nachricht wie:</p>
                        <div class="bg-light p-2 rounded font-monospace">
                            🔌 Hallo!<br>
                            Ihre Chat-ID: <strong>123456789</strong><br>
                            Geben Sie diese ID in Ihren...
                        </div>
                        <p><small>Kopieren Sie nur die Zahlen!</small></p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>✅ Schritt 3: Verifizieren</h6>
                        <ol>
                            <li>Chat-ID hier eingeben</li>
                            <li>"Verifizierungscode senden" klicken</li>
                            <li>6-stelligen Code aus Telegram eingeben</li>
                            <li>"Bestätigen" klicken</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>🎉 Fertig!</h6>
                        <p>Nach der Verifizierung erhalten Sie:</p>
                        <ul>
                            <li>📊 Zählerstand-Erinnerungen</li>
                            <li>⚠️ Verbrauchsalarme</li>
                            <li>💰 Kostenwarnungen</li>
                            <li>🔧 System-Benachrichtigungen</li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($telegramBotInfo): ?>
                <div class="alert alert-info">
                    <strong>📱 Bot-Informationen:</strong><br>
                    <strong>Username:</strong> @<?= $telegramBotInfo['username'] ?><br>
                    <strong>Name:</strong> <?= $telegramBotInfo['first_name'] ?><br>
                    <a href="https://t.me/<?= $telegramBotInfo['username'] ?>" target="_blank" class="btn btn-info btn-sm">
                        <i class="bi bi-telegram"></i> Bot in Telegram öffnen
                    </a>
                </div>
                <?php endif; ?>
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Custom CSS (erweitert) -->
<style>
/* Bestehende Styles bleiben unverändert... */

/* Telegram-spezifische Styles */
.telegram-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius-md);
    font-size: 0.875rem;
}

.telegram-status.verified {
    background-color: rgba(13, 202, 240, 0.1);
    color: #0dca70;
}

.telegram-status.pending {
    background-color: rgba(255, 193, 7, 0.1);
    color: #ffab00;
}

.telegram-status.disabled {
    background-color: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}

/* Modal Styling */
.modal-body code {
    background-color: var(--bs-gray-100);
    padding: 0.2rem 0.4rem;
    border-radius: 0.25rem;
    font-family: 'Courier New', monospace;
}

[data-theme="dark"] .modal-body code {
    background-color: var(--bs-gray-800);
    color: var(--bs-gray-100);
}
</style>

<!-- JavaScript (erweitert um Telegram) -->
<script>
// Bestehende JavaScript-Funktionen bleiben...

// API-Key in Zwischenablage kopieren
function copyApiKey() {
    const apiKeyField = document.getElementById('current-api-key');
    if (!apiKeyField) return;
    
    apiKeyField.select();
    apiKeyField.setSelectionRange(0, 99999); // Mobile
    
    navigator.clipboard.writeText(apiKeyField.value).then(function() {
        showToast('API-Key in Zwischenablage kopiert!', 'success');
    }).catch(function() {
        // Fallback für ältere Browser
        document.execCommand('copy');
        showToast('API-Key kopiert!', 'success');
    });
}

// Toast-Benachrichtigung anzeigen
function showToast(message, type = 'info') {
    const toastHTML = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'primary'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // Toast-Container erstellen falls nicht vorhanden
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    container.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = container.lastElementChild;
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    // Toast nach Anzeige entfernen
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Telegram Chat-ID Validierung
function validateTelegramChatId(input) {
    const chatId = input.value.trim();
    const isValid = /^-?\d+$/.test(chatId) && chatId.length >= 5;
    
    if (chatId && !isValid) {
        input.classList.add('is-invalid');
        
        let feedback = input.nextElementSibling;
        if (!feedback || !feedback.classList.contains('invalid-feedback')) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentNode.appendChild(feedback);
        }
        feedback.textContent = 'Chat-ID muss eine Nummer mit mindestens 5 Stellen sein (z.B. 123456789)';
    } else {
        input.classList.remove('is-invalid');
        const feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.remove();
        }
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Telegram Chat-ID Validierung
    const chatIdInput = document.getElementById('telegram_chat_id');
    if (chatIdInput) {
        chatIdInput.addEventListener('input', function() {
            validateTelegramChatId(this);
        });
        
        // Beim Laden validieren falls bereits Wert vorhanden
        if (chatIdInput.value) {
            validateTelegramChatId(chatIdInput);
        }
    }
    
    // Telegram aktivieren/deaktivieren abhängig von Chat-ID
    const telegramEnabledCheckbox = document.getElementById('telegram_enabled');
    const telegramChatIdInput = document.getElementById('telegram_chat_id');
    
    if (telegramEnabledCheckbox && telegramChatIdInput) {
        function updateTelegramState() {
            if (telegramEnabledCheckbox.checked && !telegramChatIdInput.value.trim()) {
                showToast('Bitte geben Sie eine Chat-ID ein, um Telegram zu aktivieren', 'warning');
                telegramEnabledCheckbox.checked = false;
            }
        }
        
        telegramEnabledCheckbox.addEventListener('change', updateTelegramState);
    }
    
    // Automatisches Öffnen des Benachrichtigungen-Tabs falls Hash vorhanden
    if (window.location.hash === '#notifications-tab') {
        const notificationsTab = document.getElementById('notifications-tab');
        if (notificationsTab) {
            notificationsTab.click();
        }
    }
});
</script>
