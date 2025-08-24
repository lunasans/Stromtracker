<?php
// profil.php
// EINFACHE & SCHÖNE Profil-Verwaltung mit Profilbild-Upload

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
        
        return true;
    }
    
    /**
     * Bild verarbeiten und optimieren
     */
    private static function processImage($filepath) {
        
        $imageInfo = getimagesize($filepath);
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];
        
        // Zielgröße
        $targetSize = 200;
        
        // Bild nur verkleinern wenn größer als Ziel
        if ($width <= $targetSize && $height <= $targetSize) {
            return true;
        }
        
        // Seitenverhältnis berechnen
        $ratio = min($targetSize / $width, $targetSize / $height);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        
        // Quell-Bild laden
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filepath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($filepath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Neues Bild erstellen
        $target = imagecreatetruecolor($newWidth, $newHeight);
        
        // Transparenz für PNG/GIF erhalten
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefill($target, 0, 0, $transparent);
        }
        
        // Bild skalieren
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Speichern
        $result = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($target, $filepath, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($target, $filepath, 6);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($target, $filepath);
                break;
        }
        
        // Speicher freigeben
        imagedestroy($source);
        imagedestroy($target);
        
        return $result;
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

// Profil-Verarbeitung
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
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: profil.php');
    exit;
}

// User-Daten laden
$userData = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$userId]) ?: [];

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
                                    <?= strtoupper(substr($userData['name'] ?? 'U', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Account-Statistiken -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card success">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-speedometer2"></i>
                    <div class="small">Erfasst</div>
                </div>
                <h3><?= $accountStats['total_readings'] ?></h3>
                <p>Zählerstände</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card primary">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-cpu"></i>
                    <div class="small">Verwaltet</div>
                </div>
                <h3><?= $accountStats['total_devices'] ?></h3>
                <p>Geräte registriert</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card warning">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-tags"></i>
                    <div class="small">Konfiguriert</div>
                </div>
                <h3><?= $accountStats['total_tariffs'] ?></h3>
                <p>Tarife erstellt</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card energy">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-calendar-check"></i>
                    <div class="small">Dabei seit</div>
                </div>
                <h3><?= $daysSinceRegistration ?></h3>
                <p>Tagen aktiv</p>
            </div>
        </div>
    </div>

    <!-- Profil-Tabs -->
    <div class="row">
        <div class="col-12">
            
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" 
                            data-bs-target="#personal" type="button" role="tab">
                        <i class="bi bi-person me-2"></i>Persönliche Daten
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="image-tab" data-bs-toggle="tab" 
                            data-bs-target="#profile-image" type="button" role="tab">
                        <i class="bi bi-camera me-2"></i>Profilbild
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" 
                            data-bs-target="#security" type="button" role="tab">
                        <i class="bi bi-shield-lock me-2"></i>Sicherheit
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="account-tab" data-bs-toggle="tab" 
                            data-bs-target="#account" type="button" role="tab">
                        <i class="bi bi-info-circle me-2"></i>Account-Info
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="profileTabsContent">
                
                <!-- Persönliche Daten -->
                <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-person text-energy"></i>
                                Persönliche Informationen
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Name *</label>
                                        <input type="text" class="form-control" name="name" 
                                               value="<?= htmlspecialchars($userData['name'] ?? '') ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">E-Mail-Adresse *</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?= htmlspecialchars($userData['email'] ?? '') ?>" required>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Hinweis:</strong> Derzeit können nur Name und E-Mail-Adresse bearbeitet werden. 
                                    Weitere Profilfelder werden in einer zukünftigen Version hinzugefügt.
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-energy">
                                        <i class="bi bi-check-circle me-2"></i>Profil speichern
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Profilbild Tab - KORRIGIERT -->
                <div class="tab-pane fade" id="profile-image" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-camera text-energy"></i>
                                Profilbild verwalten
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <div class="row">
                                <!-- Aktuelles Profilbild -->
                                <div class="col-md-4 text-center mb-4">
                                    <h6>Aktuelles Profilbild</h6>
                                    
                                    <div class="profile-image-container mb-3">
                                        <?php 
                                        $currentImage = ProfileImageHandler::getImageUrl($userData['profile_image']); 
                                        if ($currentImage): 
                                        ?>
                                            <img src="<?= htmlspecialchars($currentImage) ?>" 
                                                 alt="Profilbild" 
                                                 class="profile-image-large">
                                        <?php else: ?>
                                            <div class="profile-image-placeholder">
                                                <i class="bi bi-person-circle"></i>
                                                <span class="placeholder-text">Kein Bild</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($currentImage): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="delete_image">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                    onclick="return confirm('Profilbild wirklich löschen?')">
                                                <i class="bi bi-trash me-1"></i>Löschen
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Upload-Formular -->
                                <div class="col-md-8">
                                    <h6>Neues Profilbild hochladen</h6>
                                    
                                    <form method="POST" enctype="multipart/form-data" class="upload-form">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="upload_image">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Bilddatei auswählen</label>
                                            <input type="file" class="form-control" name="profile_image" 
                                                   accept="image/jpeg,image/png,image/gif" 
                                                   required
                                                   onchange="previewImage(this)">
                                            <div class="form-text">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Erlaubt: JPG, PNG, GIF • Max. 2MB • Wird automatisch auf 200x200px verkleinert
                                            </div>
                                        </div>
                                        
                                        <!-- Vorschau -->
                                        <div class="mb-3">
                                            <div id="image-preview" class="image-preview" style="display: none;">
                                                <h6>Vorschau:</h6>
                                                <img id="preview-img" src="" alt="Vorschau" class="preview-image">
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-energy">
                                            <i class="bi bi-upload me-2"></i>Profilbild hochladen
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Hinweise -->
                            <hr class="my-4">
                            
                            <div class="alert alert-info">
                                <h6><i class="bi bi-lightbulb me-2"></i>Tipps für das perfekte Profilbild:</h6>
                                <ul class="mb-0">
                                    <li>Verwenden Sie ein aktuelles, gut erkennbares Foto</li>
                                    <li>Quadratische Bilder funktionieren am besten</li>
                                    <li>Achten Sie auf ausreichend Licht und gute Qualität</li>
                                    <li>Das Bild wird automatisch auf 200x200 Pixel verkleinert</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sicherheit -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-shield-lock text-energy"></i>
                                Passwort & Sicherheit
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Aktuelles Passwort *</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Neues Passwort *</label>
                                        <input type="password" class="form-control" name="new_password" 
                                               minlength="6" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Neues Passwort bestätigen *</label>
                                        <input type="password" class="form-control" name="confirm_password" 
                                               minlength="6" required>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-shield-check me-2"></i>Passwort ändern
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Sicherheits-Info -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle text-info"></i>
                                Sicherheits-Tipps
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-success">✅ Sicherheit</h6>
                                    <ul class="small">
                                        <li>Mindestens 8 Zeichen</li>
                                        <li>Groß- und Kleinbuchstaben</li>
                                        <li>Zahlen verwenden</li>
                                        <li>Sonderzeichen nutzen</li>
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
                
                <!-- Account-Info -->
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
                            
                            <div class="alert alert-info">
                                <h6>
                                    <i class="bi bi-shield-check me-2"></i>
                                    Datenschutz & Sicherheit
                                </h6>
                                <p class="mb-2">Ihre Daten werden sicher und verschlüsselt gespeichert.</p>
                                <ul class="mb-0">
                                    <li>Passwörter werden mit bcrypt gehashed</li>
                                    <li>CSRF-Schutz bei allen Formularen</li>
                                    <li>SSL-Verschlüsselung für alle Übertragungen</li>
                                    <li>Keine Weitergabe an Dritte</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- KORRIGIERTE CSS Styles für Profilbild -->
<style>
/* Header Avatar */
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
    box-shadow: var(--shadow-lg);
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
    font-weight: bold;
    font-size: 2rem;
    box-shadow: var(--shadow-lg);
}

/* Profile Image Management */
.profile-image-container {
    position: relative;
    display: inline-block;
}

.profile-image-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--energy);
    box-shadow: var(--shadow-lg);
    transition: all 0.3s ease;
}

.profile-image-placeholder {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: var(--gray-100);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px dashed var(--gray-300);
    color: var(--gray-500);
    font-size: 2.2rem; /* REDUZIERT von 3rem auf 2.2rem */
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

/* Text unter dem Icon - OPTIMIERT */
.profile-image-placeholder .placeholder-text {
    position: absolute;
    bottom: 18px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.7rem; /* Sehr klein */
    font-weight: 500;
    white-space: nowrap;
    text-align: center;
    line-height: 1;
    opacity: 0.7;
    background: rgba(255, 255, 255, 0.9);
    padding: 2px 6px;
    border-radius: 8px;
}

.image-preview {
    padding: 1rem;
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
    margin-top: 1rem;
}

.preview-image {
    max-width: 200px;
    max-height: 200px;
    border-radius: var(--radius-lg);
    object-fit: cover;
    border: 2px solid var(--energy);
}

.upload-form {
    background: var(--white);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
}

/* Drag & Drop Styling */
.form-control[type="file"] {
    transition: all 0.3s ease;
    border: 2px dashed var(--gray-300);
    background: var(--gray-50);
    padding: 1rem;
    border-radius: var(--radius-lg);
}

.form-control[type="file"]:hover,
.form-control[type="file"]:focus {
    border-color: var(--energy);
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
    background: rgba(245, 158, 11, 0.02);
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
    background: rgba(245, 158, 11, 0.1);
}

[data-theme="dark"] .profile-image-placeholder .placeholder-text {
    background: rgba(0, 0, 0, 0.7);
    color: var(--gray-300);
}

[data-theme="dark"] .image-preview {
    background: var(--gray-700);
    border-color: var(--gray-600);
}

[data-theme="dark"] .upload-form {
    background: var(--gray-800);
    border-color: var(--gray-600);
}

[data-theme="dark"] .form-control[type="file"] {
    background: var(--gray-700);
    border-color: var(--gray-600);
    color: var(--gray-300);
}

[data-theme="dark"] .form-control[type="file"]:hover,
[data-theme="dark"] .form-control[type="file"]:focus {
    background: rgba(245, 158, 11, 0.05);
    border-color: var(--energy);
}

/* Responsive Anpassungen */
@media (max-width: 768px) {
    .profile-header-image,
    .profile-header-placeholder {
        width: 60px;
        height: 60px;
    }
    
    .profile-header-placeholder {
        font-size: 1.5rem;
    }
    
    .profile-image-large,
    .profile-image-placeholder {
        width: 120px;
        height: 120px;
    }
    
    .profile-image-placeholder {
        font-size: 1.8rem;
    }
    
    .profile-image-placeholder .placeholder-text {
        font-size: 0.65rem;
        bottom: 15px;
        padding: 1px 4px;
    }
}
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Password confirmation validation
    const newPasswordField = document.querySelector('input[name="new_password"]');
    const confirmPasswordField = document.querySelector('input[name="confirm_password"]');
    
    if (newPasswordField && confirmPasswordField) {
        function validatePasswords() {
            if (confirmPasswordField.value && newPasswordField.value !== confirmPasswordField.value) {
                confirmPasswordField.setCustomValidity('Passwörter stimmen nicht überein');
            } else {
                confirmPasswordField.setCustomValidity('');
            }
        }
        
        newPasswordField.addEventListener('input', validatePasswords);
        confirmPasswordField.addEventListener('input', validatePasswords);
    }
    
    // Tab persistence
    const tabTriggerList = [].slice.call(document.querySelectorAll('#profileTabs button[data-bs-toggle="tab"]'));
    tabTriggerList.forEach(function (tabTriggerEl) {
        tabTriggerEl.addEventListener('shown.bs.tab', function (event) {
            localStorage.setItem('activeProfileTab', event.target.getAttribute('data-bs-target'));
        });
    });
    
    // Restore active tab
    const activeTab = localStorage.getItem('activeProfileTab');
    if (activeTab) {
        const tabTrigger = document.querySelector('#profileTabs button[data-bs-target="' + activeTab + '"]');
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }
    }
    
    // Form validation feedback
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Add loading state to submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Speichern...';
                submitBtn.disabled = true;
                
                // Re-enable if form validation fails
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                }, 3000);
            }
        });
    });
});

// Image Preview Function
function previewImage(input) {
    const preview = document.getElementById('image-preview');
    const previewImg = document.getElementById('preview-img');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}
</script>