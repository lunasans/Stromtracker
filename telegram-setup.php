<?php
// telegram-setup.php
// Web-Interface für Telegram-System Aktivierung

require_once 'config/database.php';
require_once 'config/session.php';

// Nur für eingeloggte Benutzer
Auth::requireLogin();

$pageTitle = 'Telegram-Setup - Stromtracker';
$user = Auth::getUser();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// =============================================================================
// SETUP LOGIC
// =============================================================================

$setupComplete = false;
$errors = [];
$success = [];

// Prüfen ob Telegram-System bereits aktiviert ist
function isTelegramSystemReady() {
    try {
        // Prüfen ob Tabellen existieren
        $tables = ['telegram_config', 'telegram_log'];
        foreach ($tables as $table) {
            $exists = Database::fetchOne("SHOW TABLES LIKE '{$table}'");
            if (!$exists) {
                return false;
            }
        }
        
        // Prüfen ob notification_settings erweitert wurde
        $columns = Database::fetchAll("SHOW COLUMNS FROM notification_settings");
        $columnNames = array_column($columns, 'Field');
        
        if (!in_array('telegram_enabled', $columnNames)) {
            return false;
        }
        
        // Prüfen ob Bot-Token konfiguriert ist
        $config = Database::fetchOne("SELECT * FROM telegram_config WHERE is_active = 1");
        if (!$config || empty($config['bot_token']) || $config['bot_token'] === 'YOUR_BOT_TOKEN_HERE') {
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        return false;
    }
}

// Form Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Flash::error('Sicherheitsfehler. Bitte versuchen Sie es erneut.');
    } else {
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_tables':
                // Tabellen erstellen
                try {
                    // notification_settings erweitern
                    $columns = Database::fetchAll("SHOW COLUMNS FROM notification_settings");
                    $columnNames = array_column($columns, 'Field');
                    
                    if (!in_array('telegram_enabled', $columnNames)) {
                        Database::execute("ALTER TABLE notification_settings 
                                         ADD COLUMN telegram_enabled BOOLEAN DEFAULT FALSE COMMENT 'Telegram Benachrichtigungen aktiviert',
                                         ADD COLUMN telegram_chat_id VARCHAR(50) NULL COMMENT 'Telegram Chat-ID des Benutzers',
                                         ADD COLUMN telegram_verified BOOLEAN DEFAULT FALSE COMMENT 'Telegram Chat-ID verifiziert'");
                        $success[] = 'notification_settings Tabelle erweitert';
                    } else {
                        $success[] = 'notification_settings bereits aktuell';
                    }
                    
                    // telegram_config Tabelle
                    Database::execute("CREATE TABLE IF NOT EXISTS telegram_config (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        bot_token VARCHAR(100) NOT NULL COMMENT 'Telegram Bot Token',
                        bot_username VARCHAR(50) NULL COMMENT 'Bot Username für Anzeige',
                        webhook_url VARCHAR(255) NULL COMMENT 'Webhook URL (optional)',
                        is_active BOOLEAN DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )");
                    $success[] = 'telegram_config Tabelle erstellt';
                    
                    // telegram_log Tabelle
                    Database::execute("CREATE TABLE IF NOT EXISTS telegram_log (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NULL,
                        chat_id VARCHAR(50) NOT NULL,
                        message_type ENUM('notification', 'verification', 'command', 'error') DEFAULT 'notification',
                        message_text TEXT NOT NULL,
                        telegram_message_id INT NULL COMMENT 'Telegram Message ID',
                        status ENUM('sent', 'failed', 'delivered') DEFAULT 'sent',
                        error_message TEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                        INDEX idx_chat_id (chat_id),
                        INDEX idx_created_at (created_at)
                    )");
                    $success[] = 'telegram_log Tabelle erstellt';
                    
                    Flash::success('Telegram-Tabellen erfolgreich erstellt!');
                    
                } catch (Exception $e) {
                    $errors[] = 'Datenbankfehler: ' . $e->getMessage();
                    Flash::error('Fehler beim Erstellen der Tabellen: ' . $e->getMessage());
                }
                break;
                
            case 'configure_bot':
                // Bot konfigurieren
                $botToken = trim($_POST['bot_token'] ?? '');
                $botUsername = trim($_POST['bot_username'] ?? '');
                
                if (empty($botToken)) {
                    Flash::error('Bot-Token ist erforderlich.');
                } else {
                    try {
                        // Bot-Token validieren (optional)
                        $botInfo = null;
                        if ($botToken !== 'demo') {
                            $context = stream_context_create([
                                'http' => [
                                    'method' => 'GET',
                                    'timeout' => 10
                                ]
                            ]);
                            
                            $response = @file_get_contents("https://api.telegram.org/bot{$botToken}/getMe", false, $context);
                            
                            if ($response !== false) {
                                $data = json_decode($response, true);
                                if (isset($data['ok']) && $data['ok']) {
                                    $botInfo = $data['result'];
                                    $botUsername = $botInfo['username'] ?? $botUsername;
                                    $success[] = 'Bot-Token validiert: @' . $botUsername;
                                }
                            }
                        }
                        
                        // Bestehende Konfiguration löschen
                        Database::execute("DELETE FROM telegram_config");
                        
                        // Neue Konfiguration speichern
                        $insertId = Database::insert('telegram_config', [
                            'bot_token' => $botToken,
                            'bot_username' => $botUsername,
                            'is_active' => true
                        ]);
                        
                        if ($insertId) {
                            Flash::success('Bot erfolgreich konfiguriert! Das System ist jetzt aktiviert.');
                            $setupComplete = true;
                        } else {
                            Flash::error('Fehler beim Speichern der Bot-Konfiguration.');
                        }
                        
                    } catch (Exception $e) {
                        $errors[] = 'Bot-Konfigurationsfehler: ' . $e->getMessage();
                        Flash::error('Fehler bei der Bot-Konfiguration: ' . $e->getMessage());
                    }
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    if ($setupComplete) {
        header('Location: profil.php#notifications-tab');
        exit;
    } else {
        header('Location: telegram-setup.php');
        exit;
    }
}

// Setup-Status prüfen
$systemReady = isTelegramSystemReady();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <h1 class="text-energy mb-2">
                    <span class="energy-indicator"></span>
                    <i class="bi bi-telegram"></i>
                    Telegram-Setup
                </h1>
                <p class="text-muted mb-0">
                    Aktivieren Sie das Telegram-Benachrichtigungssystem für Ihren Stromtracker.
                </p>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            
            <?php if ($systemReady): ?>
            
            <!-- System bereits aktiviert -->
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="text-success mb-4">
                        <i class="bi bi-check-circle" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="text-success">🎉 Telegram-System ist aktiviert!</h3>
                    <p class="text-muted">Das Telegram-Benachrichtigungssystem ist erfolgreich eingerichtet und bereit zur Nutzung.</p>
                    
                    <div class="mt-4">
                        <a href="profil.php#notifications-tab" class="btn btn-success btn-lg">
                            <i class="bi bi-person-gear"></i> Zu den Profil-Einstellungen
                        </a>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            
            <!-- Setup-Assistent -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear text-energy"></i>
                        Telegram-System Aktivierung
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Schritt 1: Tabellen erstellen -->
                    <div class="setup-step">
                        <h6><i class="bi bi-database"></i> Schritt 1: Datenbank vorbereiten</h6>
                        <p class="text-muted">Erstellt die erforderlichen Datenbank-Tabellen für das Telegram-System.</p>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="create_tables">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-database-add"></i> Tabellen erstellen
                            </button>
                        </form>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Schritt 2: Bot konfigurieren -->
                    <div class="setup-step">
                        <h6><i class="bi bi-robot"></i> Schritt 2: Telegram Bot konfigurieren</h6>
                        <p class="text-muted">Tragen Sie die Daten Ihres Telegram Bots ein.</p>
                        
                        <!-- Bot erstellen Anleitung -->
                        <div class="alert alert-info">
                            <h6><i class="bi bi-info-circle"></i> Bot erstellen (falls noch nicht geschehen):</h6>
                            <ol class="mb-0">
                                <li>Öffnen Sie Telegram</li>
                                <li>Suchen Sie nach <strong>@BotFather</strong></li>
                                <li>Senden Sie: <code>/newbot</code></li>
                                <li>Folgen Sie den Anweisungen</li>
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
                                    <div class="form-text">
                                        Der Token sieht aus wie: 123456789:ABCdefGHijKLmnop...
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="bot_username" class="form-label">Bot-Username (optional)</label>
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
            </div>
            
            <?php endif; ?>
            
            <!-- Demo/Test Option -->
            <?php if (!$systemReady): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-flask text-warning"></i>
                        Demo-Modus
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Aktivieren Sie das System im Demo-Modus zum Testen der Benutzeroberfläche.
                        <small>(Nachrichten werden nicht wirklich gesendet)</small>
                    </p>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="configure_bot">
                        <input type="hidden" name="bot_token" value="demo">
                        <input type="hidden" name="bot_username" value="demo_bot">
                        <button type="submit" class="btn btn-warning">
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
.setup-step {
    padding: 1.5rem;
    border-left: 4px solid var(--energy);
    background: rgba(245, 158, 11, 0.05);
    border-radius: 0 8px 8px 0;
    margin-bottom: 1rem;
}

.setup-step h6 {
    color: var(--energy);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.energy-indicator {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: var(--energy);
    border-radius: 50%;
    margin-right: 0.5rem;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.card.glass {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

[data-theme="dark"] .card.glass {
    background: rgba(33, 37, 41, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
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
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Wird verarbeitet...';
        }
    });
});
</script>
