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
            return 'Datei ist zu groß. Maximum: ' . (self::$maxFileSize / 1024 / 1024) . 'MB';
        }
        
        // Dateityp prüfen
        if (!in_array($file['type'], self::$allowedTypes)) {
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

/**
 * User-Avatar rendern
 */
function renderUserAvatar($user, $size = 'medium') {
    $sizes = [
        'small' => '24px',
        'medium' => '40px', 
        'large' => '60px',
        'xlarge' => '150px'
    ];
    
    $avatarSize = $sizes[$size] ?? $sizes['medium'];
    $imageUrl = ProfileImageHandler::getImageUrl($user['profile_image'] ?? '');
    
    if ($imageUrl) {
        return "<img src='" . htmlspecialchars($imageUrl) . "' 
                     alt='Profilbild' 
                     style='width: {$avatarSize}; height: {$avatarSize}; 
                            border-radius: 50%; object-fit: cover; 
                            border: 4px solid var(--energy); 
                            box-shadow: var(--shadow-lg);'>";
    } else {
        $initial = strtoupper(substr($user['name'] ?? 'U', 0, 1));
        return "<div style='width: {$avatarSize}; height: {$avatarSize}; 
                            background: linear-gradient(135deg, var(--energy), #d97706); 
                            border-radius: 50%; display: flex; 
                            align-items: center; justify-content: center; 
                            color: white; font-weight: bold; 
                            font-size: calc({$avatarSize} * 0.4);'>{$initial}</div>";
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
                // Profil-Daten aktualisieren (nur name und email - existierende Felder)
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
                            // Session-Daten aktualisieren
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_name'] = $name;
                            
                            Flash::success('Profil wurde erfolgreich aktualisiert.');
                        } else {
                            Flash::error('Fehler beim Aktualisieren des Profils.');
                        }
                    }
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
                    $userRow = Database::fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);
                    
                    if (!$userRow || !password_verify($currentPassword, $userRow['password'])) {
                        Flash::error('Das aktuelle Passwort ist falsch.');
                    } else {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $success = Database::update('users', [
                            'password' => $hashedPassword
                        ], 'id = ?', [$userId]);
                        
                        if ($success) {
                            Flash::success('Passwort wurde erfolgreich geändert.');
                        } else {
                            Flash::error('Fehler beim Ändern des Passworts.');
                        }
                    }
                }
                break;
                
            case 'upload_image':
                // Profilbild hochladen
                if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
                    Flash::error('Bitte wählen Sie eine Bilddatei aus.');
                } else {
                    $result = ProfileImageHandler::handleUpload($_FILES['profile_image'], $userId);
                    
                    if ($result['success']) {
                        Flash::success('Profilbild wurde erfolgreich hochgeladen.');
                    } else {
                        Flash::error($result['error']);
                    }
                }
                break;
                
            case 'delete_image':
                // Profilbild löschen
                if (ProfileImageHandler::deleteImage($userId)) {
                    Flash::success('Profilbild wurde erfolgreich gelöscht.');
                } else {
                    Flash::error('Fehler beim Löschen des Profilbilds.');
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: profil.php');
    exit;
}

// Benutzer-Daten laden (nur existierende Felder)
$userData = Database::fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$userId]
);

// Account-Statistiken
$readingsResult = Database::fetchOne("SELECT COUNT(*) as count FROM meter_readings WHERE user_id = ?", [$userId]);
$devicesResult = Database::fetchOne("SELECT COUNT(*) as count FROM devices WHERE user_id = ? AND is_active = 1", [$userId]);
$tariffsResult = Database::fetchOne("SELECT COUNT(*) as count FROM tariff_periods WHERE user_id = ?", [$userId]);

$accountStats = [
    'created_at' => $userData['created_at'] ?? date('Y-m-d H:i:s'),
    'total_readings' => $readingsResult['count'] ?? 0,
    'total_devices' => $devicesResult['count'] ?? 0,
    'total_tariffs' => $tariffsResult['count'] ?? 0
];

// Berechne Tage seit Registrierung
$daysSinceRegistration = max(1, floor((time() - strtotime($accountStats['created_at'])) / 86400));

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
                        <p class="text-muted mb-0">Verwalten Sie Ihre persönlichen Daten und Einstellungen.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex align-items-center justify-content-end gap-3">
                            <div class="text-center">
                                <div class="h4 text-energy mb-1"><?= $daysSinceRegistration ?></div>
                                <small class="text-muted">Tage dabei</small>
                            </div>
                            
                            <!-- Avatar -->
                            <div class="position-relative">
                                <?= renderUserAvatar($userData, 'large') ?>
                                <div style="position: absolute; bottom: -2px; right: -2px; 
                                           width: 20px; height: 20px; background: var(--success); 
                                           border-radius: 50%; border: 2px solid white;
                                           display: flex; align-items: center; justify-content: center;">
                                    <i class="bi bi-check" style="font-size: 10px; color: white;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Account-Statistiken -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card primary">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-speedometer2"></i>
                    <div class="small">
                        Total
                    </div>
                </div>
                <h3><?= $accountStats['total_readings'] ?></h3>
                <p>Zählerstände erfasst</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card success">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-cpu"></i>
                    <div class="small">
                        Aktiv
                    </div>
                </div>
                <h3><?= $accountStats['total_devices'] ?></h3>
                <p>Geräte verwaltet</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card warning">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-receipt"></i>
                    <div class="small">
                        Tarife
                    </div>
                </div>
                <h3><?= $accountStats['total_tariffs'] ?></h3>
                <p>Tarife erstellt</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card energy">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-calendar-check"></i>
                    <div class="small">
                        Dabei seit
                    </div>
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
                
                <!-- Profilbild Tab -->
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
                                                <div class="mt-2">Kein Profilbild</div>
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
                                Passwort ändern
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label class="form-label">Aktuelles Passwort *</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Neues Passwort *</label>
                                        <input type="password" class="form-control" name="new_password" 
                                               minlength="6" required>
                                        <div class="form-text">Mindestens 6 Zeichen</div>
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
                                            <td><?= date('d.m.Y H:i', strtotime($accountStats['created_at'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Tage dabei:</strong></td>
                                            <td><?= $daysSinceRegistration ?> Tage</td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <h6>Nutzungsstatistiken</h6>
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
                                            <td><strong>Ablesungen/Monat:</strong></td>
                                            <td>⌀ <?= round($accountStats['total_readings'] / max(1, ceil($daysSinceRegistration / 30)), 1) ?></td>
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

<!-- Custom Styles für Profilbild -->
<style>
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
}

.profile-image-placeholder {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: var(--gray-100);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px dashed var(--gray-300);
    color: var(--gray-500);
    font-size: 3rem;
}

.image-preview {
    padding: 1rem;
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    border: 1px solid var(--gray-200);
}

.preview-image {
    max-width: 200px;
    max-height: 200px;
    border-radius: var(--radius-lg);
    object-fit: cover;
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
}

.form-control[type="file"]:hover {
    border-color: var(--energy);
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
}

/* Dark Theme Support */
[data-theme="dark"] .profile-image-placeholder {
    background: var(--gray-700);
    border-color: var(--gray-600);
    color: var(--gray-400);
}

[data-theme="dark"] .image-preview {
    background: var(--gray-700);
    border-color: var(--gray-600);
}

[data-theme="dark"] .upload-form {
    background: var(--gray-800);
    border-color: var(--gray-600);
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