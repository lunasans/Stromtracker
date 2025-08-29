<?php
// profil.php
// EINFACHE & SCHÖNE Profil-Verwaltung mit Profilbild-Upload und API-Key-Management

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Profil - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// =============================================================================
// PROFILBILD UPLOAD-HANDLER
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
// API-KEY MANAGER CLASS
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
// FORM PROCESSING
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
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: profil.php');
    exit;
}

// =============================================================================
// DATA LOADING
// =============================================================================

// User-Daten laden (mit API-Key)
$userData = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]) ?: [];
$userApiKey = $userData['api_key'] ?? null;

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
                
                <!-- Profil Tab -->
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
                
                <!-- Profilbild Tab -->
                <div class="tab-pane fade" id="image" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-image text-energy"></i>
                                Profilbild verwalten
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- Aktuelles Profilbild -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Aktuelles Profilbild</h6>
                                    <div class="profile-image-container">
                                        <?php if ($currentImage): ?>
                                            <img src="<?= htmlspecialchars($currentImage) ?>" 
                                                 alt="Aktuelles Profilbild" 
                                                 class="profile-image-current">
                                        <?php else: ?>
                                            <div class="profile-image-placeholder">
                                                <i class="bi bi-person"></i>
                                                <div class="placeholder-text">Kein Bild</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Lösch-Button falls Bild vorhanden -->
                                    <?php if ($currentImage): ?>
                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="delete_image">
                                            <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Profilbild wirklich löschen?')">
                                                <i class="bi bi-trash"></i> Bild Löschen
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <!-- Upload Form -->
                                    <h6>Neues Profilbild hochladen</h6>
                                    <form method="POST" enctype="multipart/form-data" class="upload-form">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="upload_image">
                                        
                                        <div class="mb-3">
                                            <label for="profile_image" class="form-label">Bild auswählen</label>
                                            <input type="file" class="form-control" id="profile_image" 
                                                   name="profile_image" accept="image/*" required>
                                            <div class="form-text">
                                                <i class="bi bi-info-circle"></i> 
                                                Max. 2MB, JPG/PNG/GIF, wird automatisch auf 400px skaliert
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-upload"></i> Hochladen
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Passwort Tab -->
                <div class="tab-pane fade" id="password" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-lock text-energy"></i>
                                Passwort ändern
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="current_password" class="form-label">Aktuelles Passwort</label>
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="new_password" class="form-label">Neues Passwort</label>
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password" minlength="6" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Passwort bestätigen</label>
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" minlength="6" required>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-shield-lock"></i> Passwort Ändern
                                </button>
                            </form>
                            
                            <!-- Passwort-Tipps -->
                            <div class="card bg-light mt-4">
                                <div class="card-body">
                                    <h6><i class="bi bi-lightbulb"></i> Sicheres Passwort</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-success">✅ Empfohlen</h6>
                                            <ul class="small">
                                                <li>Mindestens 8 Zeichen</li>
                                                <li>Groß- und Kleinbuchstaben</li>
                                                <li>Zahlen und Sonderzeichen</li>
                                                <li>Keine Wörterbuch-Wörter</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-danger">❌ Vermeiden</h6>
                                            <ul class="small">
                                                <li>Persönliche Daten</li>
                                                <li>Einfache Wörter</li>
                                                <li>Tastaturmuster</li>
                                                <li>Wiederverwendung</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- API-Keys Tab -->
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
                            
                            <!-- Dokumentation -->
                            <div class="card bg-light mt-4">
                                <div class="card-body">
                                    <h6><i class="bi bi-book"></i> Integration Guide</h6>
                                    
                                    <div class="accordion" id="apiAccordion">
                                        <!-- Tasmota Konfiguration -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed" type="button" 
                                                        data-bs-toggle="collapse" data-bs-target="#tasmota-config">
                                                    <i class="bi bi-router me-2"></i>Tasmota Konfiguration
                                                </button>
                                            </h2>
                                            <div id="tasmota-config" class="accordion-collapse collapse" 
                                                 data-bs-parent="#apiAccordion">
                                                <div class="accordion-body">
                                                    <p><strong>HTTP-Endpoint für Datenübertragung:</strong></p>
                                                    <code>POST <?= $_SERVER['HTTP_HOST'] ?>/api/receive-tasmota.php</code>
                                                    
                                                    <p class="mt-3"><strong>Beispiel JSON-Payload:</strong></p>
                                                    <pre class="bg-dark text-light p-2 rounded"><code>{
  "api_key": "<?= htmlspecialchars($userApiKey ?: 'IHR_API_KEY_HIER') ?>",
  "user_id": <?= $userId ?>,
  "device_name": "Tasmota-Steckdose",
  "timestamp": "2024-01-15 14:30:00",
  "energy_today": 2.5,
  "energy_total": 157.3,
  "power": 850
}</code></pre>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Sicherheitshinweise -->
                                        <div class="accordion-item">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed" type="button" 
                                                        data-bs-toggle="collapse" data-bs-target="#security-info">
                                                    <i class="bi bi-shield-check me-2"></i>Sicherheit
                                                </button>
                                            </h2>
                                            <div id="security-info" class="accordion-collapse collapse" 
                                                 data-bs-parent="#apiAccordion">
                                                <div class="accordion-body">
                                                    <div class="alert alert-warning">
                                                        <strong>⚠️ Wichtige Sicherheitshinweise:</strong>
                                                        <ul class="mb-0 mt-2">
                                                            <li>API-Key niemals öffentlich teilen</li>
                                                            <li>Key nur über HTTPS übertragen</li>
                                                            <li>Bei Verdacht auf Missbrauch sofort neu generieren</li>
                                                            <li>Key regelmäßig wechseln (alle 3-6 Monate)</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Account Tab -->
                <div class="tab-pane fade" id="account" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle text-energy"></i>
                                Account-Informationen
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <h6>Account-Details</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>User-ID:</strong></td>
                                            <td>#<?= $userId ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Name:</strong></td>
                                            <td><?= htmlspecialchars($userData['name'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>E-Mail:</strong></td>
                                            <td><?= htmlspecialchars($userData['email'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Registriert:</strong></td>
                                            <td><?= date('d.m.Y', strtotime($accountStats['registration_date'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Aktiv seit:</strong></td>
                                            <td><?= max(1, floor($daysSinceRegistration / 30)) ?> Monat(en)</td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <h6>Aktivitäts-Statistik</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Zählerstände:</strong></td>
                                            <td><?= $accountStats['total_readings'] ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Geräte:</strong></td>
                                            <td><?= $accountStats['total_devices'] ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Tarife:</strong></td>
                                            <td><?= $accountStats['total_tariffs'] ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ø Aktivität:</strong></td>
                                            <td><?= number_format(max(0.1, $accountStats['total_readings'] / max(1, $daysSinceRegistration / 30)), 1) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div> <!-- Tab Content Ende -->
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Custom CSS -->
<style>
/* Profil-Header */
.profile-header-avatar {
    display: flex;
    justify-content: center;
    align-items: center;
}

.profile-header-image {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--energy);
}

.profile-header-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--energy), #d97706);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
}

/* Profilbild-Container */
.profile-image-container {
    text-align: center;
    margin-bottom: 1rem;
}

.profile-image-current {
    max-width: 200px;
    max-height: 200px;
    border-radius: var(--radius-lg);
    object-fit: cover;
    border: 2px solid var(--energy);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.profile-image-placeholder {
    width: 200px;
    height: 200px;
    margin: 0 auto;
    border-radius: var(--radius-lg);
    background: var(--gray-100);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px dashed var(--gray-300);
    color: var(--gray-500);
    font-size: 2.2rem;
    transition: all 0.3s ease;
    position: relative;
    cursor: pointer;
}

.profile-image-placeholder:hover {
    border-color: var(--energy);
    color: var(--energy);
    background: rgba(245, 158, 11, 0.05);
    transform: scale(1.02);
}

.placeholder-text {
    position: absolute;
    bottom: 18px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.7rem;
    font-weight: 500;
    white-space: nowrap;
    text-align: center;
    line-height: 1;
    opacity: 0.7;
    background: rgba(255, 255, 255, 0.9);
    padding: 2px 6px;
    border-radius: 8px;
}

/* Upload-Form */
.upload-form {
    background: var(--gray-50);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
}

/* Dark Theme Support */
[data-theme="dark"] .profile-header-placeholder {
    background: linear-gradient(135deg, var(--energy), #d97706);
}

[data-theme="dark"] .profile-image-placeholder {
    background: var(--gray-700);
    border-color: var(--gray-600);
    color: var(--gray-400);
}

[data-theme="dark"] .profile-image-placeholder:hover {
    border-color: var(--energy);
    color: var(--energy);
}

[data-theme="dark"] .upload-form {
    background: var(--gray-800);
    border-color: var(--gray-600);
}
</style>

<!-- JavaScript -->
<script>
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

// Passwort-Bestätigung validieren
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (newPassword && confirmPassword) {
        function validatePasswordMatch() {
            if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwörter stimmen nicht überein');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        newPassword.addEventListener('input', validatePasswordMatch);
        confirmPassword.addEventListener('input', validatePasswordMatch);
    }
    
    // File-Upload Preview (optional)
    const fileInput = document.getElementById('profile_image');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                // Hier könnte eine Preview-Funktionalität implementiert werden
                console.log('Image selected:', file.name);
            }
        });
    }
});
</script>