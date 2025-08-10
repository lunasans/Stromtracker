<?php
// einstellungen.php
// EINFACHE & SCHÖNE Einstellungen-Verwaltung

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Einstellungen - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// Einstellungen-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF-Token prüfen
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Flash::error('Sicherheitsfehler. Bitte versuchen Sie es erneut.');
    } else {
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'reset_data':
                // Alle Benutzerdaten löschen (außer Account)
                $confirmText = $_POST['confirm_text'] ?? '';
                
                if ($confirmText !== 'ALLE DATEN LÖSCHEN') {
                    Flash::error('Bestätigungstext ist falsch. Bitte geben Sie "ALLE DATEN LÖSCHEN" ein.');
                } else {
                    // Daten in der richtigen Reihenfolge löschen
                    Database::delete('meter_readings', 'user_id = ?', [$userId]);
                    Database::delete('tariff_periods', 'user_id = ?', [$userId]);
                    Database::delete('devices', 'user_id = ?', [$userId]);
                    
                    Flash::success('Alle Ihre Daten wurden erfolgreich gelöscht. Ihr Account bleibt bestehen.');
                }
                break;
                
            case 'export_data':
                // Einfacher CSV-Export aller Daten
                $timestamp = date('Y-m-d_H-i-s');
                $filename = "stromtracker_export_{$timestamp}.csv";
                
                // CSV-Header
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                $output = fopen('php://output', 'w');
                
                // UTF-8 BOM für Excel
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Benutzerinformationen
                fputcsv($output, ['=== BENUTZERINFORMATIONEN ==='], ';');
                fputcsv($output, ['Name', $user['name']], ';');
                fputcsv($output, ['E-Mail', $user['email']], ';');
                fputcsv($output, ['Export-Datum', date('d.m.Y H:i')], ';');
                fputcsv($output, [''], ';');
                
                // Zählerstände
                fputcsv($output, ['=== ZÄHLERSTÄNDE ==='], ';');
                fputcsv($output, ['Datum', 'Zählerstand', 'Verbrauch', 'Kosten', 'Notizen'], ';');
                
                $readings = Database::fetchAll(
                    "SELECT * FROM meter_readings WHERE user_id = ? ORDER BY reading_date",
                    [$userId]
                );
                
                foreach ($readings as $reading) {
                    fputcsv($output, [
                        $reading['reading_date'],
                        $reading['meter_value'],
                        $reading['consumption'],
                        $reading['cost'],
                        $reading['notes']
                    ], ';');
                }
                
                fputcsv($output, [''], ';');
                
                // Geräte
                fputcsv($output, ['=== GERÄTE ==='], ';');
                fputcsv($output, ['Name', 'Kategorie', 'Leistung (W)', 'Aktiv'], ';');
                
                $devices = Database::fetchAll(
                    "SELECT * FROM devices WHERE user_id = ? ORDER BY name",
                    [$userId]
                );
                
                foreach ($devices as $device) {
                    fputcsv($output, [
                        $device['name'],
                        $device['category'],
                        $device['wattage'],
                        $device['is_active'] ? 'Ja' : 'Nein'
                    ], ';');
                }
                
                fputcsv($output, [''], ';');
                
                // Tarife
                fputcsv($output, ['=== TARIFE ==='], ';');
                fputcsv($output, ['Gültig von', 'Gültig bis', 'Preis/kWh', 'Abschlag', 'Grundgebühr', 'Anbieter'], ';');
                
                $tariffs = Database::fetchAll(
                    "SELECT * FROM tariff_periods WHERE user_id = ? ORDER BY valid_from",
                    [$userId]
                );
                
                foreach ($tariffs as $tariff) {
                    fputcsv($output, [
                        $tariff['valid_from'],
                        $tariff['valid_to'],
                        $tariff['rate_per_kwh'],
                        $tariff['monthly_payment'],
                        $tariff['basic_fee'],
                        $tariff['provider_name']
                    ], ';');
                }
                
                fclose($output);
                exit;
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: einstellungen.php');
    exit;
}

// Statistiken für Übersicht
$readingsCount = Database::fetchOne("SELECT COUNT(*) as count FROM meter_readings WHERE user_id = ?", [$userId]);
$devicesCount = Database::fetchOne("SELECT COUNT(*) as count FROM devices WHERE user_id = ?", [$userId]);
$tariffsCount = Database::fetchOne("SELECT COUNT(*) as count FROM tariff_periods WHERE user_id = ?", [$userId]);

$dataStats = [
    'readings' => $readingsCount['count'] ?? 0,
    'devices' => $devicesCount['count'] ?? 0,
    'tariffs' => $tariffsCount['count'] ?? 0
];

include 'includes/header.php';
include 'includes/navbar.php';
?>

<!-- Einstellungen Content -->
<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-energy mb-2">
                            <span class="energy-indicator"></span>
                            <i class="bi bi-gear"></i>
                            Einstellungen
                        </h1>
                        <p class="text-muted mb-0">Konfigurieren Sie Ihr Stromtracker-Erlebnis nach Ihren Wünschen.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex align-items-center justify-content-end gap-3">
                            <div class="text-center">
                                <div class="h4 text-energy mb-1" id="settings-status">✓</div>
                                <small class="text-muted">System OK</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Einstellungen-Tabs -->
    <div class="row">
        <div class="col-12">
            
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="appearance-tab" data-bs-toggle="tab" 
                            data-bs-target="#appearance" type="button" role="tab">
                        <i class="bi bi-palette me-2"></i>Erscheinungsbild
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="data-tab" data-bs-toggle="tab" 
                            data-bs-target="#data" type="button" role="tab">
                        <i class="bi bi-database me-2"></i>Daten
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" 
                            data-bs-target="#privacy" type="button" role="tab">
                        <i class="bi bi-shield-check me-2"></i>Datenschutz
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" 
                            data-bs-target="#system" type="button" role="tab">
                        <i class="bi bi-cpu me-2"></i>System
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="settingsTabsContent">
                
                <!-- Erscheinungsbild -->
                <div class="tab-pane fade show active" id="appearance" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-palette text-energy"></i>
                                Design & Darstellung
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <!-- Theme-Auswahl -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="mb-3">
                                        <i class="bi bi-moon-stars me-2"></i>
                                        Farbschema
                                    </h6>
                                    
                                    <div class="d-flex gap-3">
                                        <div class="theme-option" data-theme="light">
                                            <div class="theme-preview light-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-body">
                                                    <div class="theme-card"></div>
                                                    <div class="theme-card"></div>
                                                </div>
                                            </div>
                                            <small class="d-block text-center mt-2">Hell</small>
                                        </div>
                                        
                                        <div class="theme-option" data-theme="dark">
                                            <div class="theme-preview dark-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-body">
                                                    <div class="theme-card"></div>
                                                    <div class="theme-card"></div>
                                                </div>
                                            </div>
                                            <small class="d-block text-center mt-2">Dunkel</small>
                                        </div>
                                        
                                        <div class="theme-option" data-theme="auto">
                                            <div class="theme-preview auto-theme">
                                                <div class="theme-header"></div>
                                                <div class="theme-body">
                                                    <div class="theme-card"></div>
                                                    <div class="theme-card"></div>
                                                </div>
                                            </div>
                                            <small class="d-block text-center mt-2">Auto</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="mb-3">
                                        <i class="bi bi-speedometer me-2"></i>
                                        Performance
                                    </h6>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="animationsEnabled" checked>
                                        <label class="form-check-label" for="animationsEnabled">
                                            Animationen aktivieren
                                        </label>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                                        <label class="form-check-label" for="autoRefresh">
                                            Automatische Aktualisierung
                                        </label>
                                    </div>
                                    
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="compactMode">
                                        <label class="form-check-label" for="compactMode">
                                            Kompakte Ansicht
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Aktueller Status -->
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle me-2"></i>Aktuelle Einstellungen</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong>Theme:</strong> <span id="current-theme">Hell</span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Browser:</strong> <span id="browser-info">-</span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Auflösung:</strong> <span id="screen-info">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daten -->
                <div class="tab-pane fade" id="data" role="tabpanel">
                    <div class="row">
                        
                        <!-- Datenübersicht -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-pie-chart text-primary"></i>
                                        Ihre Daten
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="h3 text-primary"><?= $dataStats['readings'] ?></div>
                                            <small class="text-muted">Zählerstände</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h3 text-success"><?= $dataStats['devices'] ?></div>
                                            <small class="text-muted">Geräte</small>
                                        </div>
                                        <div class="col-4">
                                            <div class="h3 text-warning"><?= $dataStats['tariffs'] ?></div>
                                            <small class="text-muted">Tarife</small>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-grid gap-2">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="export_data">
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="bi bi-download me-2"></i>
                                                Alle Daten exportieren (CSV)
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Daten löschen -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Gefahrenbereich
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="text-danger">Alle Daten löschen</h6>
                                    <p class="text-muted">
                                        Löscht alle Ihre Zählerstände, Geräte und Tarife. 
                                        Ihr Account bleibt bestehen.
                                    </p>
                                    
                                    <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteDataModal">
                                        <i class="bi bi-trash me-2"></i>
                                        Alle Daten löschen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Datenschutz -->
                <div class="tab-pane fade" id="privacy" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-shield-check text-energy"></i>
                                Datenschutz & Sicherheit
                            </h5>
                        </div>
                        <div class="card-body">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>🔒 Ihre Daten sind sicher</h6>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle text-success me-2"></i>
                                            Alle Passwörter werden verschlüsselt gespeichert
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle text-success me-2"></i>
                                            CSRF-Schutz bei allen Formularen
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle text-success me-2"></i>
                                            Sichere Datenbankverbindung
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle text-success me-2"></i>
                                            Keine Weitergabe an Dritte
                                        </li>
                                    </ul>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6>📊 Lokale Einstellungen</h6>
                                    <p class="text-muted">
                                        Diese Einstellungen werden nur in Ihrem Browser gespeichert:
                                    </p>
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="bi bi-gear text-primary me-2"></i>
                                            Theme-Präferenz
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-gear text-primary me-2"></i>
                                            Performance-Einstellungen
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-gear text-primary me-2"></i>
                                            Tab-Auswahl
                                        </li>
                                    </ul>
                                    
                                    <button class="btn btn-outline-warning btn-sm" onclick="clearLocalSettings()">
                                        <i class="bi bi-trash me-1"></i>
                                        Lokale Einstellungen löschen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System -->
                <div class="tab-pane fade" id="system" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-cpu text-energy"></i>
                                System-Informationen
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Server</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td>PHP Version:</td>
                                            <td><code><?= PHP_VERSION ?></code></td>
                                        </tr>
                                        <tr>
                                            <td>Stromtracker Version:</td>
                                            <td><code>2.0.0</code></td>
                                        </tr>
                                        <tr>
                                            <td>Build:</td>
                                            <td><code><?= date('Ymd') ?></code></td>
                                        </tr>
                                        <tr>
                                            <td>Zeitzone:</td>
                                            <td><code><?= date_default_timezone_get() ?></code></td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6>Browser</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td>User Agent:</td>
                                            <td><small class="text-muted" id="user-agent">Wird geladen...</small></td>
                                        </tr>
                                        <tr>
                                            <td>Sprache:</td>
                                            <td><code id="browser-language">-</code></td>
                                        </tr>
                                        <tr>
                                            <td>Viewport:</td>
                                            <td><code id="viewport-size">-</code></td>
                                        </tr>
                                        <tr>
                                            <td>Online:</td>
                                            <td><span class="badge bg-success" id="online-status">Online</span></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <h6>Performance</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="text-center p-3" style="background: var(--gray-50); border-radius: var(--radius-lg);">
                                                <div class="h5 text-primary" id="page-load">-</div>
                                                <small class="text-muted">Ladezeit (ms)</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center p-3" style="background: var(--gray-50); border-radius: var(--radius-lg);">
                                                <div class="h5 text-success" id="memory-info">-</div>
                                                <small class="text-muted">Speicher (MB)</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center p-3" style="background: var(--gray-50); border-radius: var(--radius-lg);">
                                                <div class="h5 text-warning" id="connection-info">-</div>
                                                <small class="text-muted">Verbindung</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center p-3" style="background: var(--gray-50); border-radius: var(--radius-lg);">
                                                <div class="h5 text-energy">⚡</div>
                                                <small class="text-muted">Status</small>
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
    </div>
</div>

<!-- Delete Data Modal -->
<div class="modal fade" id="deleteDataModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Alle Daten löschen
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="reset_data">
                    
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle me-2"></i>Achtung!</h6>
                        <p class="mb-0">
                            Diese Aktion löscht <strong>alle</strong> Ihre Daten unwiderruflich:
                        </p>
                        <ul class="mt-2 mb-0">
                            <li><?= $dataStats['readings'] ?> Zählerstände</li>
                            <li><?= $dataStats['devices'] ?> Geräte</li>
                            <li><?= $dataStats['tariffs'] ?> Tarife</li>
                        </ul>
                    </div>
                    
                    <p><strong>Ihr Account und Login bleiben erhalten.</strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            Geben Sie zur Bestätigung ein: <code>ALLE DATEN LÖSCHEN</code>
                        </label>
                        <input type="text" class="form-control" name="confirm_text" 
                               placeholder="ALLE DATEN LÖSCHEN" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Unwiderruflich löschen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Custom Styles für Theme-Previews -->
<style>
.theme-option {
    cursor: pointer;
    padding: 10px;
    border-radius: 8px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
}

.theme-option:hover {
    transform: scale(1.05);
    border-color: var(--energy);
}

.theme-option.active {
    border-color: var(--energy);
    background: rgba(245, 158, 11, 0.1);
}

.theme-preview {
    width: 80px;
    height: 60px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #ddd;
    position: relative;
}

.light-theme {
    background: #ffffff;
}

.light-theme .theme-header {
    height: 15px;
    background: #f3f4f6;
    border-bottom: 1px solid #e5e7eb;
}

.light-theme .theme-body {
    padding: 4px;
}

.light-theme .theme-card {
    height: 8px;
    background: #e5e7eb;
    margin-bottom: 2px;
    border-radius: 2px;
}

.dark-theme {
    background: #1f2937;
}

.dark-theme .theme-header {
    height: 15px;
    background: #374151;
    border-bottom: 1px solid #4b5563;
}

.dark-theme .theme-body {
    padding: 4px;
}

.dark-theme .theme-card {
    height: 8px;
    background: #4b5563;
    margin-bottom: 2px;
    border-radius: 2px;
}

.auto-theme {
    background: linear-gradient(90deg, #ffffff 50%, #1f2937 50%);
}

.auto-theme .theme-header {
    height: 15px;
    background: linear-gradient(90deg, #f3f4f6 50%, #374151 50%);
}

.auto-theme .theme-body {
    padding: 4px;
}

.auto-theme .theme-card {
    height: 8px;
    background: linear-gradient(90deg, #e5e7eb 50%, #4b5563 50%);
    margin-bottom: 2px;
    border-radius: 2px;
}
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Theme-Auswahl
    const themeOptions = document.querySelectorAll('.theme-option');
    let currentTheme = localStorage.getItem('theme') || 'light';
    
    // Aktuelles Theme markieren
    function updateThemeSelection() {
        themeOptions.forEach(option => {
            const theme = option.dataset.theme;
            option.classList.toggle('active', theme === currentTheme);
        });
        
        // Theme-Status aktualisieren (mit null-check)
        const currentThemeElement = document.getElementById('current-theme');
        if (currentThemeElement) {
            const themeNames = { light: 'Hell', dark: 'Dunkel', auto: 'Automatisch' };
            currentThemeElement.textContent = themeNames[currentTheme] || 'Hell';
        }
    }
    
    // Theme-Auswahl Handler
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const newTheme = this.dataset.theme;
            currentTheme = newTheme; // Update the variable
            localStorage.setItem('theme', newTheme);
            
            // Theme anwenden
            const effectiveTheme = newTheme === 'auto' ? 
                (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : 
                newTheme;
            
            document.documentElement.setAttribute('data-theme', effectiveTheme);
            
            // Selection aktualisieren
            updateThemeSelection();
            
            // Theme-Icon im Header aktualisieren
            const themeIcon = document.getElementById('themeIcon');
            if (themeIcon) {
                themeIcon.className = effectiveTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            }
        });
    });
    
    updateThemeSelection();
    
    // Performance-Einstellungen
    const animationsToggle = document.getElementById('animationsEnabled');
    const autoRefreshToggle = document.getElementById('autoRefresh');
    const compactModeToggle = document.getElementById('compactMode');
    
    // Einstellungen aus localStorage laden
    animationsToggle.checked = localStorage.getItem('animationsEnabled') !== 'false';
    autoRefreshToggle.checked = localStorage.getItem('autoRefresh') !== 'false';
    compactModeToggle.checked = localStorage.getItem('compactMode') === 'true';
    
    // Einstellungen speichern
    [animationsToggle, autoRefreshToggle, compactModeToggle].forEach(toggle => {
        toggle.addEventListener('change', function() {
            localStorage.setItem(this.id, this.checked);
            
            // Einstellungen anwenden
            if (this.id === 'animationsEnabled') {
                document.body.style.setProperty('--transition-duration', this.checked ? '0.3s' : '0s');
            }
            
            if (this.id === 'compactMode') {
                document.body.classList.toggle('compact-mode', this.checked);
            }
        });
    });
    
    // System-Informationen
    function updateSystemInfo() {
        // Browser-Info (mit null-checks)
        const userAgentEl = document.getElementById('user-agent');
        const browserLangEl = document.getElementById('browser-language');
        const browserInfoEl = document.getElementById('browser-info');
        const viewportSizeEl = document.getElementById('viewport-size');
        const screenInfoEl = document.getElementById('screen-info');
        const pageLoadEl = document.getElementById('page-load');
        const memoryInfoEl = document.getElementById('memory-info');
        const connectionInfoEl = document.getElementById('connection-info');
        const onlineStatusEl = document.getElementById('online-status');
        
        if (userAgentEl) userAgentEl.textContent = navigator.userAgent;
        if (browserLangEl) browserLangEl.textContent = navigator.language;
        if (browserInfoEl) browserInfoEl.textContent = getBrowserName();
        
        // Viewport
        if (viewportSizeEl) viewportSizeEl.textContent = window.innerWidth + ' × ' + window.innerHeight;
        if (screenInfoEl) screenInfoEl.textContent = screen.width + ' × ' + screen.height;
        
        // Performance
        if (pageLoadEl) pageLoadEl.textContent = Math.round(performance.now());
        
        // Memory (falls verfügbar)
        if (memoryInfoEl) {
            if (performance.memory) {
                const memoryMB = Math.round(performance.memory.usedJSHeapSize / 1024 / 1024);
                memoryInfoEl.textContent = memoryMB;
            } else {
                memoryInfoEl.textContent = 'N/A';
            }
        }
        
        // Connection (falls verfügbar)
        if (connectionInfoEl) {
            if (navigator.connection) {
                connectionInfoEl.textContent = navigator.connection.effectiveType || 'unknown';
            } else {
                connectionInfoEl.textContent = 'unknown';
            }
        }
        
        // Online-Status
        if (onlineStatusEl) {
            onlineStatusEl.textContent = navigator.onLine ? 'Online' : 'Offline';
            onlineStatusEl.className = navigator.onLine ? 'badge bg-success' : 'badge bg-danger';
        }
    }
    
    function getBrowserName() {
        const userAgent = navigator.userAgent;
        if (userAgent.includes('Chrome')) return 'Chrome';
        if (userAgent.includes('Firefox')) return 'Firefox';
        if (userAgent.includes('Safari')) return 'Safari';
        if (userAgent.includes('Edge')) return 'Edge';
        return 'Unknown';
    }
    
    updateSystemInfo();
    
    // Online/Offline Events
    window.addEventListener('online', updateSystemInfo);
    window.addEventListener('offline', updateSystemInfo);
    
    // Viewport-Änderungen
    window.addEventListener('resize', function() {
        const viewportSizeEl = document.getElementById('viewport-size');
        if (viewportSizeEl) {
            viewportSizeEl.textContent = window.innerWidth + ' × ' + window.innerHeight;
        }
    });
    
    // Tab-Persistenz
    const tabTriggerList = [].slice.call(document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]'));
    tabTriggerList.forEach(function (tabTriggerEl) {
        tabTriggerEl.addEventListener('shown.bs.tab', function (event) {
            localStorage.setItem('activeSettingsTab', event.target.getAttribute('data-bs-target'));
        });
    });
    
    // Aktiven Tab wiederherstellen
    const activeTab = localStorage.getItem('activeSettingsTab');
    if (activeTab) {
        const tabTrigger = document.querySelector('#settingsTabs button[data-bs-target="' + activeTab + '"]');
        if (tabTrigger) {
            const tab = new bootstrap.Tab(tabTrigger);
            tab.show();
        }
    }
});

// Globale Funktionen
function clearLocalSettings() {
    if (confirm('Möchten Sie wirklich alle lokalen Einstellungen löschen?\n\nDies umfasst Theme, Performance-Einstellungen und gespeicherte Tab-Auswahl.')) {
        localStorage.removeItem('theme');
        localStorage.removeItem('animationsEnabled');
        localStorage.removeItem('autoRefresh');
        localStorage.removeItem('compactMode');
        localStorage.removeItem('activeSettingsTab');
        localStorage.removeItem('activeProfileTab');
        
        alert('Lokale Einstellungen wurden gelöscht. Die Seite wird neu geladen.');
        window.location.reload();
    }
}
</script>