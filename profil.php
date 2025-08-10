<?php
// profil.php
// EINFACHE & SCHÖNE Profil-Verwaltung

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Profil - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

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
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $postal_code = trim($_POST['postal_code'] ?? '');
                
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
                            'email' => $email,
                            'phone' => $phone,
                            'address' => $address,
                            'city' => $city,
                            'postal_code' => $postal_code,
                            'updated_at' => date('Y-m-d H:i:s')
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
                            'password' => $hashedPassword,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], 'id = ?', [$userId]);
                        
                        if ($success) {
                            Flash::success('Passwort wurde erfolgreich geändert.');
                        } else {
                            Flash::error('Fehler beim Ändern des Passworts.');
                        }
                    }
                }
                break;
                
            case 'update_preferences':
                // Benutzer-Einstellungen
                $theme = $_POST['theme'] ?? 'light';
                $language = $_POST['language'] ?? 'de';
                $notifications = isset($_POST['notifications']) ? 1 : 0;
                $newsletter = isset($_POST['newsletter']) ? 1 : 0;
                
                $success = Database::update('users', [
                    'theme_preference' => $theme,
                    'language' => $language,
                    'notifications_enabled' => $notifications,
                    'newsletter_enabled' => $newsletter,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$userId]);
                
                if ($success) {
                    Flash::success('Einstellungen wurden gespeichert.');
                } else {
                    Flash::error('Fehler beim Speichern der Einstellungen.');
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: profil.php');
    exit;
}

// Benutzer-Daten laden
$userData = Database::fetchOne(
    "SELECT * FROM users WHERE id = ?",
    [$userId]
);

// Account-Statistiken
$accountStats = [
    'created_at' => $userData['created_at'] ?? date('Y-m-d H:i:s'),
    'last_login' => $userData['last_login'] ?? date('Y-m-d H:i:s'),
    'total_readings' => Database::fetchSingle(
        "SELECT COUNT(*) FROM meter_readings WHERE user_id = ?",
        [$userId]
    ) ?: 0,
    'total_devices' => Database::fetchSingle(
        "SELECT COUNT(*) FROM devices WHERE user_id = ? AND is_active = 1",
        [$userId]
    ) ?: 0,
    'total_tariffs' => Database::fetchSingle(
        "SELECT COUNT(*) FROM tariff_periods WHERE user_id = ?",
        [$userId]
    ) ?: 0
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
                                <div style="width: 60px; height: 60px; background: var(--gradient-energy); 
                                           border-radius: 50%; display: flex; align-items: center; 
                                           justify-content: center; color: white; font-size: 1.5rem; font-weight: bold;">
                                    <?= strtoupper(substr($userData['name'] ?? 'U', 0, 1)) ?>
                                </div>
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
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" 
                            data-bs-target="#security" type="button" role="tab">
                        <i class="bi bi-shield-lock me-2"></i>Sicherheit
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" 
                            data-bs-target="#preferences" type="button" role="tab">
                        <i class="bi bi-gear me-2"></i>Einstellungen
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
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Telefon</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" 
                                               placeholder="z.B. +49 123 456789">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Postleitzahl</label>
                                        <input type="text" class="form-control" name="postal_code" 
                                               value="<?= htmlspecialchars($userData['postal_code'] ?? '') ?>" 
                                               placeholder="z.B. 12345">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Adresse</label>
                                        <input type="text" class="form-control" name="address" 
                                               value="<?= htmlspecialchars($userData['address'] ?? '') ?>" 
                                               placeholder="Straße und Hausnummer">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Stadt</label>
                                        <input type="text" class="form-control" name="city" 
                                               value="<?= htmlspecialchars($userData['city'] ?? '') ?>" 
                                               placeholder="z.B. München">
                                    </div>
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
                
                <!-- Einstellungen -->
                <div class="tab-pane fade" id="preferences" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-gear text-energy"></i>
                                Persönliche Einstellungen
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="action" value="update_preferences">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-palette me-2"></i>Theme
                                        </label>
                                        <select class="form-select" name="theme">
                                            <option value="light" <?= ($userData['theme_preference'] ?? 'light') === 'light' ? 'selected' : '' ?>>Hell (Standard)</option>
                                            <option value="dark" <?= ($userData['theme_preference'] ?? 'light') === 'dark' ? 'selected' : '' ?>>Dunkel</option>
                                            <option value="auto" <?= ($userData['theme_preference'] ?? 'light') === 'auto' ? 'selected' : '' ?>>Automatisch</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-translate me-2"></i>Sprache
                                        </label>
                                        <select class="form-select" name="language">
                                            <option value="de" <?= ($userData['language'] ?? 'de') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                                            <option value="en" <?= ($userData['language'] ?? 'de') === 'en' ? 'selected' : '' ?>>English</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <h6>Benachrichtigungen</h6>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="notifications" 
                                                   id="notifications" <?= ($userData['notifications_enabled'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="notifications">
                                                <i class="bi bi-bell me-2"></i>
                                                E-Mail-Benachrichtigungen erhalten
                                            </label>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="newsletter" 
                                                   id="newsletter" <?= ($userData['newsletter_enabled'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="newsletter">
                                                <i class="bi bi-envelope me-2"></i>
                                                Newsletter und Updates erhalten
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-energy">
                                        <i class="bi bi-check-circle me-2"></i>Einstellungen speichern
                                    </button>
                                </div>
                            </form>
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
                                            <td><strong>Registriert:</strong></td>
                                            <td><?= date('d.m.Y H:i', strtotime($accountStats['created_at'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Letzter Login:</strong></td>
                                            <td><?= date('d.m.Y H:i', strtotime($accountStats['last_login'])) ?></td>
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
    
    // Theme preview
    const themeSelect = document.querySelector('select[name="theme"]');
    if (themeSelect) {
        themeSelect.addEventListener('change', function() {
            const theme = this.value === 'auto' ? 
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : 
                this.value;
            
            document.documentElement.setAttribute('data-theme', theme);
        });
    }
});
</script>