<?php
// admin-telegram.php
// Admin-Interface für die einmalige Telegram-System Konfiguration

require_once 'config/database.php';
require_once 'config/session.php';

// Nur für Admins (User-ID 1 oder erweitern Sie die Berechtigung nach Bedarf)
Auth::requireLogin();
$userId = Auth::getUserId();

if ($userId !== 1) { // Ändern Sie dies auf Ihre Admin-Benutzer-ID
    Flash::error('Zugriff verweigert. Nur Administratoren können das Telegram-System konfigurieren.');
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Telegram-System Admin - Stromtracker';

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// =============================================================================
// SYSTEM STATUS PRÜFEN
// =============================================================================

function getTelegramSystemStatus() {
    $status = [
        'tables_exist' => false,
        'bot_configured' => false,
        'bot_active' => false,
        'bot_info' => null,
        'user_count' => 0
    ];
    
    try {
        // Tabellen prüfen
        $tables = ['telegram_config', 'telegram_log'];
        $tablesExist = true;
        
        foreach ($tables as $table) {
            $exists = Database::fetchOne("SHOW TABLES LIKE '{$table}'");
            if (!$exists) {
                $tablesExist = false;
                break;
            }
        }
        
        $status['tables_exist'] = $tablesExist;
        
        if ($tablesExist) {
            // Bot-Konfiguration prüfen
            $config = Database::fetchOne("SELECT * FROM telegram_config WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
            
            if ($config && !empty($config['bot_token']) && $config['bot_token'] !== 'YOUR_BOT_TOKEN_HERE') {
                $status['bot_configured'] = true;
                $status['bot_active'] = (bool)$config['is_active'];
                
                // Bot-Info laden (falls möglich)
                if ($config['bot_token'] !== 'demo') {
                    try {
                        $response = @file_get_contents("https://api.telegram.org/bot{$config['bot_token']}/getMe", false, stream_context_create([
                            'http' => ['timeout' => 5]
                        ]));
                        
                        if ($response) {
                            $data = json_decode($response, true);
                            if (isset($data['ok']) && $data['ok']) {
                                $status['bot_info'] = $data['result'];
                            }
                        }
                    } catch (Exception $e) {
                        // API nicht erreichbar
                    }
                }
            }
            
            // Benutzer-Statistiken
            $userStats = Database::fetchOne("SELECT COUNT(*) as total FROM notification_settings WHERE telegram_enabled = 1") ?: ['total' => 0];
            $status['user_count'] = $userStats['total'];
        }
        
    } catch (Exception $e) {
        error_log("Telegram status check error: " . $e->getMessage());
    }
    
    return $status;
}

// =============================================================================
// FORM PROCESSING
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Flash::error('Sicherheitsfehler. Bitte versuchen Sie es erneut.');
    } else {
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_tables':
                try {
                    // notification_settings erweitern
                    $columns = Database::fetchAll("SHOW COLUMNS FROM notification_settings");
                    $columnNames = array_column($columns, 'Field');
                    
                    if (!in_array('telegram_enabled', $columnNames)) {
                        Database::execute("ALTER TABLE notification_settings 
                                         ADD COLUMN telegram_enabled BOOLEAN DEFAULT FALSE,
                                         ADD COLUMN telegram_chat_id VARCHAR(50) NULL,
                                         ADD COLUMN telegram_verified BOOLEAN DEFAULT FALSE");
                        Flash::success('notification_settings Tabelle erweitert');
                    }
                    
                    // telegram_config Tabelle
                    Database::execute("CREATE TABLE IF NOT EXISTS telegram_config (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        bot_token VARCHAR(100) NOT NULL,
                        bot_username VARCHAR(50) NULL,
                        is_active BOOLEAN DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    
                    // telegram_log Tabelle
                    Database::execute("CREATE TABLE IF NOT EXISTS telegram_log (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NULL,
                        chat_id VARCHAR(50) NOT NULL,
                        message_type ENUM('notification', 'verification', 'command') DEFAULT 'notification',
                        message_text TEXT NOT NULL,
                        status ENUM('sent', 'failed') DEFAULT 'sent',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                    )");
                    
                    Flash::success('Telegram-Tabellen erfolgreich erstellt!');
                    
                } catch (Exception $e) {
                    Flash::error('Fehler beim Erstellen der Tabellen: ' . $e->getMessage());
                }
                break;
                
            case 'configure_bot':
                $botToken = trim($_POST['bot_token'] ?? '');
                $botUsername = trim($_POST['bot_username'] ?? '');
                
                if (empty($botToken)) {
                    Flash::error('Bot-Token ist erforderlich.');
                } else {
                    try {
                        // Bot-Token validieren
                        $botInfo = null;
                        if ($botToken !== 'demo') {
                            $response = @file_get_contents("https://api.telegram.org/bot{$botToken}/getMe", false, stream_context_create([
                                'http' => ['timeout' => 10]
                            ]));
                            
                            if ($response) {
                                $data = json_decode($response, true);
                                if (isset($data['ok']) && $data['ok']) {
                                    $botInfo = $data['result'];
                                    $botUsername = $botInfo['username'] ?? $botUsername;
                                }
                            }
                        }
                        
                        // Konfiguration speichern
                        Database::execute("DELETE FROM telegram_config");
                        
                        $insertId = Database::insert('telegram_config', [
                            'bot_token' => $botToken,
                            'bot_username' => $botUsername,
                            'is_active' => true
                        ]);
                        
                        if ($insertId) {
                            Flash::success('Bot erfolgreich konfiguriert! Das Telegram-System ist jetzt für alle Benutzer verfügbar.');
                        } else {
                            Flash::error('Fehler beim Speichern der Bot-Konfiguration.');
                        }
                        
                    } catch (Exception $e) {
                        Flash::error('Fehler bei der Bot-Konfiguration: ' . $e->getMessage());
                    }
                }
                break;
                
            case 'toggle_system':
                $active = isset($_POST['active']) ? 1 : 0;
                $success = Database::execute("UPDATE telegram_config SET is_active = ?", [$active]);
                
                if ($success) {
                    Flash::success($active ? 'Telegram-System aktiviert' : 'Telegram-System deaktiviert');
                } else {
                    Flash::error('Fehler beim Ändern des System-Status');
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: admin-telegram.php');
    exit;
}

// System-Status laden
$systemStatus = getTelegramSystemStatus();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <h1 class="text-energy mb-2">
                    <i class="bi bi-gear"></i>
                    Telegram-System Administration
                </h1>
                <p class="text-muted mb-0">
                    Einmalige Konfiguration des System-weiten Telegram-Bots für alle Benutzer.
                </p>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            
            <!-- System-Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle text-info"></i>
                        System-Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>📋 Datenbank</h6>
                            <ul class="list-unstyled">
                                <li><?= $systemStatus['tables_exist'] ? '✅' : '❌' ?> Telegram-Tabellen</li>
                            </ul>
                            
                            <h6>🤖 Bot-Konfiguration</h6>
                            <ul class="list-unstyled">
                                <li><?= $systemStatus['bot_configured'] ? '✅' : '❌' ?> Bot konfiguriert</li>
                                <li><?= $systemStatus['bot_active'] ? '✅' : '❌' ?> System aktiv</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <?php if ($systemStatus['bot_info']): ?>
                            <h6>📱 Bot-Informationen</h6>
                            <ul class="list-unstyled">
                                <li><strong>Username:</strong> @<?= htmlspecialchars($systemStatus['bot_info']['username']) ?></li>
                                <li><strong>Name:</strong> <?= htmlspecialchars($systemStatus['bot_info']['first_name']) ?></li>
                                <li><strong>ID:</strong> <?= $systemStatus['bot_info']['id'] ?></li>
                            </ul>
                            <?php endif; ?>
                            
                            <h6>👥 Benutzer</h6>
                            <ul class="list-unstyled">
                                <li><strong><?= $systemStatus['user_count'] ?></strong> Benutzer haben Telegram aktiviert</li>
                            </ul>
                        </div>
                    </div>
                    
                    <?php if ($systemStatus['tables_exist'] && $systemStatus['bot_configured'] && $systemStatus['bot_active']): ?>
                    <div class="alert alert-success mt-3">
                        <h6><i class="bi bi-check-circle"></i> System bereit!</h6>
                        <p class="mb-0">Das Telegram-System ist vollständig konfiguriert. Benutzer können jetzt in ihren Profilen Telegram-Benachrichtigungen aktivieren.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!$systemStatus['tables_exist']): ?>
            <!-- Schritt 1: Tabellen erstellen -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-database text-primary"></i>
                        Schritt 1: Datenbank vorbereiten
                    </h5>
                </div>
                <div class="card-body">
                    <p>Erstellt die erforderlichen Datenbank-Tabellen für das Telegram-System.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="create_tables">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-database-add"></i> Tabellen erstellen
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($systemStatus['tables_exist'] && !$systemStatus['bot_configured']): ?>
            <!-- Schritt 2: Bot konfigurieren -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-robot text-success"></i>
                        Schritt 2: System-Bot konfigurieren
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Anleitung -->
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Bot erstellen:</h6>
                        <ol class="mb-0">
                            <li>Öffnen Sie Telegram</li>
                            <li>Suchen Sie <strong>@BotFather</strong></li>
                            <li>Senden Sie: <code>/newbot</code></li>
                            <li>Bot-Namen: <code>Stromtracker Benachrichtigungen</code></li>
                            <li>Username: <code>ihr_domain_stromtracker_bot</code></li>
                            <li>Kopieren Sie den Bot-Token</li>
                        </ol>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="configure_bot">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="bot_token" class="form-label">Bot-Token</label>
                                <input type="text" class="form-control font-monospace" 
                                       id="bot_token" name="bot_token" 
                                       placeholder="123456789:ABCdefGHijKLmnopQRstuvwxyz"
                                       pattern="[0-9]{8,10}:[A-Za-z0-9_-]{35}"
                                       required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="bot_username" class="form-label">Username (optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="text" class="form-control" 
                                           id="bot_username" name="bot_username" 
                                           placeholder="stromtracker_bot">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Bot konfigurieren & System aktivieren
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($systemStatus['bot_configured']): ?>
            <!-- System-Verwaltung -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-toggles text-warning"></i>
                        System-Verwaltung
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="toggle_system">
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="system_active" 
                                   name="active" <?= $systemStatus['bot_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="system_active">
                                <strong>Telegram-System aktiviert</strong>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-arrow-clockwise"></i> Status ändern
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h6>📊 Quick-Links</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <a href="profil.php#notifications-tab" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-person-gear"></i> Eigenes Profil testen
                            </a>
                        </div>
                        <div class="col-md-6">
                            <?php if ($systemStatus['bot_info']): ?>
                            <a href="https://t.me/<?= $systemStatus['bot_info']['username'] ?>" 
                               target="_blank" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-telegram"></i> Bot in Telegram öffnen
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Demo-Option -->
            <?php if (!$systemStatus['bot_configured']): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-flask text-secondary"></i>
                        Demo-Modus (Testen)
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Aktiviert das System im Demo-Modus ohne echten Bot für UI-Tests.
                    </p>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="configure_bot">
                        <input type="hidden" name="bot_token" value="demo">
                        <input type="hidden" name="bot_username" value="demo_bot">
                        <button type="submit" class="btn btn-secondary">
                            <i class="bi bi-play-circle"></i> Demo-Modus aktivieren
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<style>
.card.glass {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

[data-theme="dark"] .card.glass {
    background: rgba(33, 37, 41, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.form-check-input:focus {
    border-color: var(--energy);
    box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.25);
}

.form-control:focus {
    border-color: var(--energy);
    box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.25);
}
</style>

<script>
// Bot-Token Validierung
document.getElementById('bot_token')?.addEventListener('input', function(e) {
    const token = e.target.value;
    const pattern = /^[0-9]{8,10}:[A-Za-z0-9_-]{35}$/;
    
    if (token && !pattern.test(token)) {
        e.target.classList.add('is-invalid');
    } else {
        e.target.classList.remove('is-invalid');
    }
});

// Form-Validierung
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Wird verarbeitet...';
            
            // Nach 5 Sekunden wieder aktivieren (Fallback)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 5000);
        }
    });
});
</script>
