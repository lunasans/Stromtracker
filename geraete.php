<?php
// geraete.php
// MODERNE Geräte-Verwaltung mit Tasmota Smart-Home-Integration

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Geräte-Verwaltung - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// =============================================================================
// TASMOTA DATA HELPER CLASS (NUR MONITORING - KEINE FERNSTEUERUNG)
// =============================================================================

class TasmotaDataHelper {
    
    /**
     * Neueste Tasmota-Daten aus der Datenbank abrufen
     * ✅ VERBESSERT: Längerer Zeitraum und besseres Debugging
     */
    public static function getLatestReadings($deviceIds) {
        if (empty($deviceIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($deviceIds) - 1) . '?';
        
        // ✅ FIX: UTC_TIMESTAMP() statt NOW() für korrekte UTC-Zeitvergleiche
        $readings = Database::fetchAll(
            "SELECT device_id, voltage, current, power, energy_today, energy_yesterday, 
                    energy_total, timestamp,
                    TIMESTAMPDIFF(MINUTE, timestamp, UTC_TIMESTAMP()) as minutes_ago
             FROM tasmota_readings 
             WHERE device_id IN ({$placeholders})
             AND timestamp >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
             ORDER BY device_id, timestamp DESC",
            $deviceIds
        );
        
        // Neueste Messung pro Gerät gruppieren
        $latestByDevice = [];
        foreach ($readings as $reading) {
            $deviceId = $reading['device_id'];
            if (!isset($latestByDevice[$deviceId])) {
                $latestByDevice[$deviceId] = $reading;
                
                // 🐛 DEBUG: Log für Entwicklung
                if ($_GET['debug'] ?? false) {
                    error_log("Latest reading for device {$deviceId}: " . json_encode($reading));
                }
            }
        }
        
        // 🐛 DEBUG: Anzahl gefundener Geräte
        if ($_GET['debug'] ?? false) {
            error_log("Found latest readings for " . count($latestByDevice) . " of " . count($deviceIds) . " devices");
        }
        
        return $latestByDevice;
    }
    
    /**
     * Online-Status basierend auf letzter Datenübertragung ermitteln
     * ✅ VERBESSERT: Längere Online-Zeit für robustere Erkennung
     */
    public static function getDeviceStatus($lastReading, $maxMinutesOffline = 10) {
        if (!$lastReading) {
            return 'unknown';
        }
        
        $minutesAgo = (int)$lastReading['minutes_ago'];
        
        // ✅ FIX: Tolerantere Zeiten (10min statt 30min)
        if ($minutesAgo <= $maxMinutesOffline) {
            return 'online';
        } elseif ($minutesAgo <= 60) { // 1 Stunde
            return 'warning';
        } else {
            return 'offline';
        }
    }
    
    /**
     * Formatierte Energiedaten für Anzeige
     */
    public static function formatEnergyData($reading) {
        if (!$reading) {
            return null;
        }
        
        return [
            'power' => (float)$reading['power'],
            'energy_today' => (float)$reading['energy_today'],
            'energy_yesterday' => (float)$reading['energy_yesterday'],
            'energy_total' => (float)$reading['energy_total'],
            'voltage' => (float)$reading['voltage'],
            'current' => (float)$reading['current'],
            'timestamp' => $reading['timestamp'],
            'minutes_ago' => (int)$reading['minutes_ago']
        ];
    }
    
    /**
     * Tagesstatistiken für Tasmota-Geräte
     */
    public static function getDailyStats($userId) {
        return Database::fetchAll(
            "SELECT d.id, d.name, d.category,
                    tr.energy_today, tr.power,
                    TIMESTAMPDIFF(MINUTE, tr.timestamp, UTC_TIMESTAMP()) as minutes_ago
             FROM devices d
             LEFT JOIN (
                 SELECT device_id, energy_today, power, timestamp,
                        ROW_NUMBER() OVER (PARTITION BY device_id ORDER BY timestamp DESC) as rn
                 FROM tasmota_readings
                 WHERE timestamp >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
             ) tr ON d.id = tr.device_id AND tr.rn = 1
             WHERE d.user_id = ? AND d.tasmota_enabled = 1 AND d.is_active = 1
             ORDER BY tr.energy_today DESC",
            [$userId]
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
            case 'add_device':
                // Normales Gerät hinzufügen
                $name = trim($_POST['name'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $wattage = (int)($_POST['wattage'] ?? 0);
                
                if (empty($name) || empty($category) || $wattage <= 0) {
                    Flash::error('Bitte füllen Sie alle Felder aus.');
                } else {
                    $success = Database::insert('devices', [
                        'user_id' => $userId,
                        'name' => $name,
                        'category' => $category,
                        'wattage' => $wattage,
                        'is_active' => true
                    ]);
                    
                    if ($success) {
                        Flash::success("Gerät '{$name}' erfolgreich hinzugefügt.");
                    } else {
                        Flash::error('Fehler beim Hinzufügen des Geräts.');
                    }
                }
                break;
                
            case 'add_tasmota_device':
                // Tasmota-Gerät hinzufügen
                $name = trim($_POST['name'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $wattage = (int)($_POST['wattage'] ?? 100);
                $tasmotaIp = trim($_POST['tasmota_ip'] ?? '');
                $tasmotaName = trim($_POST['tasmota_name'] ?? '');
                $tasmotaInterval = (int)($_POST['tasmota_interval'] ?? 300);
                
                if (empty($name)) {
                    Flash::error('Gerätename ist ein Pflichtfeld.');
                } elseif (!empty($tasmotaIp) && !filter_var($tasmotaIp, FILTER_VALIDATE_IP)) {
                    Flash::error('Ungültige IP-Adresse.');
                } else {
                    $success = Database::insert('devices', [
                        'user_id' => $userId,
                        'name' => $name,
                        'category' => $category,
                        'wattage' => $wattage,
                        'tasmota_ip' => $tasmotaIp,
                        'tasmota_name' => $tasmotaName,
                        'tasmota_enabled' => true,
                        'tasmota_interval' => $tasmotaInterval,
                        'is_active' => true
                    ]);
                    
                    if ($success) {
                        Flash::success("Tasmota-Gerät '{$name}' erfolgreich hinzugefügt! Daten werden über den lokalen Collector übertragen.");
                    } else {
                        Flash::error('Fehler beim Hinzufügen des Tasmota-Geräts.');
                    }
                }
                break;
                
            case 'edit_device':
                // Gerät bearbeiten
                $deviceId = (int)($_POST['device_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $wattage = (int)($_POST['wattage'] ?? 0);
                $tasmotaIp = trim($_POST['tasmota_ip'] ?? '');
                $tasmotaName = trim($_POST['tasmota_name'] ?? '');
                $tasmotaEnabled = isset($_POST['tasmota_enabled']) ? 1 : 0;
                $tasmotaInterval = (int)($_POST['tasmota_interval'] ?? 300);
                
                if ($deviceId <= 0 || empty($name) || empty($category)) {
                    Flash::error('Ungültige Eingaben.');
                } else {
                    $updateData = [
                        'name' => $name,
                        'category' => $category,
                        'wattage' => $wattage
                    ];
                    
                    // Tasmota-Felder nur aktualisieren wenn IP angegeben
                    if (!empty($tasmotaIp) && filter_var($tasmotaIp, FILTER_VALIDATE_IP)) {
                        $updateData['tasmota_ip'] = $tasmotaIp;
                        $updateData['tasmota_name'] = $tasmotaName;
                        $updateData['tasmota_enabled'] = $tasmotaEnabled;
                        $updateData['tasmota_interval'] = $tasmotaInterval;
                    } else {
                        $updateData['tasmota_enabled'] = 0;
                    }
                    
                    $success = Database::update('devices', $updateData, 'id = ? AND user_id = ?', [$deviceId, $userId]);
                    
                    if ($success) {
                        Flash::success("Gerät '{$name}' wurde erfolgreich aktualisiert.");
                    } else {
                        Flash::error('Fehler beim Bearbeiten des Geräts.');
                    }
                }
                break;
                
            case 'toggle_device':
                // Gerät aktivieren/deaktivieren
                $deviceId = (int)($_POST['device_id'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if ($deviceId <= 0) {
                    Flash::error('Ungültige Gerät-ID.');
                } else {
                    $success = Database::update('devices', [
                        'is_active' => $isActive
                    ], 'id = ? AND user_id = ?', [$deviceId, $userId]);
                    
                    if ($success) {
                        $status = $isActive ? 'aktiviert' : 'deaktiviert';
                        Flash::success("Gerät wurde erfolgreich {$status}.");
                    } else {
                        Flash::error('Fehler beim Ändern des Gerätestatus.');
                    }
                }
                break;
                
            case 'delete_device':
                // Gerät löschen
                $deviceId = (int)($_POST['device_id'] ?? 0);
                
                if ($deviceId <= 0) {
                    Flash::error('Ungültige Gerät-ID.');
                } else {
                    // Soft-Delete (deaktivieren statt löschen)
                    $success = Database::update('devices', [
                        'is_active' => 0
                    ], 'id = ? AND user_id = ?', [$deviceId, $userId]);
                    
                    if ($success) {
                        Flash::success('Gerät wurde erfolgreich gelöscht.');
                    } else {
                        Flash::error('Fehler beim Löschen des Geräts.');
                    }
                }
                break;
        }
    }
    
    // Redirect nach POST
    header('Location: geraete.php');
    exit;
}

// =============================================================================
// DATA LOADING
// =============================================================================

// Alle Geräte laden
$allDevices = Database::fetchAll(
    "SELECT * FROM devices WHERE user_id = ? ORDER BY is_active DESC, name ASC",
    [$userId]
) ?: [];

// Geräte nach Status trennen
$activeDevices = array_filter($allDevices, fn($d) => $d['is_active']);
$inactiveDevices = array_filter($allDevices, fn($d) => !$d['is_active']);

// Tasmota-Geräte identifizieren
$tasmotaDevices = array_filter($activeDevices, fn($d) => $d['tasmota_enabled']);

// Kategorien sammeln
$categories = array_unique(array_column($activeDevices, 'category'));

// Neueste Tasmota-Daten aus Datenbank laden
$tasmotaData = [];
if (!empty($tasmotaDevices)) {
    $tasmotaDeviceIds = array_column($tasmotaDevices, 'id');
    $latestReadings = TasmotaDataHelper::getLatestReadings($tasmotaDeviceIds);
    
    foreach ($tasmotaDevices as $device) {
        $reading = $latestReadings[$device['id']] ?? null;
        $tasmotaData[$device['id']] = [
            'reading' => $reading,
            'energy_data' => TasmotaDataHelper::formatEnergyData($reading),
            'status' => TasmotaDataHelper::getDeviceStatus($reading),
            'last_update' => $device['last_tasmota_reading']
        ];
    }
}

// Statistiken berechnen
$stats = [
    'total_devices' => count($activeDevices),
    'tasmota_devices' => count($tasmotaDevices),
    'inactive_devices' => count($inactiveDevices),
    'categories' => count($categories),
    'max_power' => array_sum(array_column($activeDevices, 'wattage')),
    'tasmota_online' => count(array_filter($tasmotaData, fn($data) => $data['status'] === 'online')),
    'tasmota_with_data' => count(array_filter($tasmotaData, fn($data) => $data['energy_data'] !== null))
];

include 'includes/header.php';
include 'includes/navbar.php';
?>

<!-- Custom CSS -->
<style>
.device-card {
    transition: all 0.3s ease;
    border: 1px solid var(--gray-300);
}

.device-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.device-card.inactive {
    opacity: 0.6;
    background: var(--gray-100);
}

.tasmota-device {
    border-left: 4px solid #28a745 !important;
    position: relative;
}

.tasmota-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #28a745;
    color: white;
    font-size: 0.7em;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: bold;
}

.energy-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.energy-item {
    text-align: center;
    padding: 8px;
    background: rgba(40, 167, 69, 0.1);
    border-radius: 6px;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.energy-value {
    font-size: 1.1em;
    font-weight: bold;
    color: #28a745;
    display: block;
}

.energy-label {
    font-size: 0.75em;
    color: #6c757d;
    margin-top: 3px;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

.status-online { background-color: #28a745; }
.status-offline { background-color: #dc3545; }
.status-unknown { background-color: #ffc107; }

/* Chart-Container */
.tasmota-charts {
    background: rgba(248, 249, 250, 0.5);
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
    min-height: 200px;
}

.chart-container {
    position: relative;
    height: 180px;
    margin-bottom: 15px;
}

.chart-loading {
    color: var(--gray-500);
    font-size: 0.9em;
}

.chart-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 10px;
}

.chart-tab {
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.3);
    color: #28a745;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 0.8em;
    cursor: pointer;
    transition: all 0.2s ease;
}

.chart-tab.active {
    background: #28a745;
    color: white;
}

.chart-tab:hover {
    background: rgba(40, 167, 69, 0.2);
}

/* Zeitraum-Buttons */
.btn-timerange.active {
    background-color: var(--energy);
    border-color: var(--energy);
    color: white;
}

/* Aktuelle Werte kompakt */
.current-values {
    background: rgba(255, 255, 255, 0.8);
    border-radius: 6px;
    padding: 8px;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.current-values strong {
    font-size: 1.1em;
}

.current-values small {
    font-size: 0.75em;
}

/* Auto-refresh Indicator */
.refresh-indicator {
    position: absolute;
    top: 5px;
    right: 5px;
    width: 8px;
    height: 8px;
    background: #28a745;
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.refresh-indicator.active {
    opacity: 1;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.3; }
    100% { opacity: 1; }
}

.control-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.control-buttons .btn {
    font-size: 0.8em;
    padding: 3px 8px;
}

/* Dark Theme Support */
[data-theme="dark"] .device-card {
    border-color: var(--gray-600);
    background: var(--gray-800);
}

[data-theme="dark"] .device-card.inactive {
    background: var(--gray-700);
}

[data-theme="dark"] .energy-item {
    background: rgba(40, 167, 69, 0.2);
    border-color: rgba(40, 167, 69, 0.3);
}
</style>

<!-- Geräte-Verwaltung Content -->
<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-energy mb-2">
                            <span class="energy-indicator"></span>
                            <i class="bi bi-cpu"></i>
                            Geräte-Verwaltung
                        </h1>
                        <p class="text-muted mb-0">
                            Verwalten Sie Ihre Haushaltsgeräte und Tasmota Smart-Home-Geräte.
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <!-- Gerät hinzufügen Buttons -->
                        <div class="btn-group">
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                                <i class="bi bi-plus-circle"></i> Normales Gerät
                            </button>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTasmotaModal">
                                <i class="bi bi-wifi"></i> Tasmota-Gerät
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiken -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h3 text-energy mb-1"><?= $stats['total_devices'] ?></div>
                    <small class="text-muted">Aktive Geräte</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h3 text-success mb-1"><?= $stats['tasmota_devices'] ?></div>
                    <small class="text-muted">Smart-Geräte</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h3 text-info mb-1"><?= $stats['tasmota_online'] ?>/<?= $stats['tasmota_devices'] ?></div>
                    <small class="text-muted">Online</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h3 text-warning mb-1"><?= $stats['categories'] ?></div>
                    <small class="text-muted">Kategorien</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h3 text-danger mb-1"><?= number_format($stats['max_power'] / 1000, 1) ?>kW</div>
                    <small class="text-muted">Max. Leistung</small>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <div class="h3 text-secondary mb-1"><?= $stats['inactive_devices'] ?></div>
                    <small class="text-muted">Inaktiv</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter/Suche -->
    <div class="row mb-3">
        <div class="col-md-6">
            <input type="text" class="form-control" id="deviceSearch" placeholder="🔍 Geräte durchsuchen...">
        </div>
        <div class="col-md-3">
            <select class="form-select" id="categoryFilter">
                <option value="">Alle Kategorien</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" id="typeFilter">
                <option value="">Alle Gerätetypen</option>
                <option value="normal">Normale Geräte</option>
                <option value="tasmota">Tasmota-Geräte</option>
                <option value="online">Online (Tasmota)</option>
                <option value="offline">Offline (Tasmota)</option>
            </select>
        </div>
    </div>
    
    <!-- Geräteliste -->
    <div class="row" id="deviceGrid">
        <?php foreach ($activeDevices as $device): ?>
            <?php
            $isTasmota = $device['tasmota_enabled'];
            $tasmotaInfo = $tasmotaData[$device['id']] ?? null;
            $energyData = $tasmotaInfo ? $tasmotaInfo['energy_data'] : null;
            $status = $tasmotaInfo ? $tasmotaInfo['status'] : 'unknown';
            ?>
            
            <div class="col-xl-4 col-lg-6 mb-4 device-item" 
                 data-name="<?= htmlspecialchars(strtolower($device['name'])) ?>"
                 data-category="<?= htmlspecialchars($device['category']) ?>"
                 data-type="<?= $isTasmota ? 'tasmota' : 'normal' ?>"
                 data-status="<?= $status ?>"
                 data-device-id="<?= $device['id'] ?>">
                
                <div class="card device-card <?= $isTasmota ? 'tasmota-device' : '' ?>">
                    
                    <?php if ($isTasmota): ?>
                        <div class="tasmota-badge">SMART</div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <!-- Geräte-Header -->
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0">
                                <?php if ($isTasmota): ?>
                                    <span class="status-indicator status-<?= $status ?>"></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($device['name']) ?>
                            </h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item edit-device" href="#" data-device='<?= json_encode($device) ?>'>
                                        <i class="bi bi-pencil"></i> Bearbeiten
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger delete-device" href="#" data-device-id="<?= $device['id'] ?>" data-device-name="<?= htmlspecialchars($device['name']) ?>">
                                        <i class="bi bi-trash"></i> Löschen
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Geräte-Info -->
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Kategorie:</small><br>
                                <span class="badge bg-secondary"><?= htmlspecialchars($device['category']) ?></span>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted">Leistung:</small><br>
                                <?php if ($isTasmota && $energyData && $energyData['power'] > 0): ?>
                                    <!-- ✅ LIVE-WATTAGE: Aus aktuellen Messdaten -->
                                    <strong class="text-warning"><?= number_format($energyData['power'], 0) ?>W</strong>
                                    <small class="text-muted d-block">Max: <?= $device['wattage'] ?>W</small>
                                <?php else: ?>
                                    <!-- 📋 STATISCHE LEISTUNG: Aus Geräte-Konfiguration -->
                                    <strong><?= $device['wattage'] ?>W</strong>
                                    <?php if ($isTasmota): ?>
                                        <small class="text-muted d-block">Konfiguriert</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Tasmota Energiedaten mit Verlaufsdiagrammen -->
                        <?php if ($isTasmota && $energyData): ?>
                            <!-- Zeitraum-Auswahl -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Live-Verlauf:</small>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-timerange active" 
                                            data-device-id="<?= $device['id'] ?>" data-range="5">5m</button>
                                    <button type="button" class="btn btn-outline-primary btn-timerange" 
                                            data-device-id="<?= $device['id'] ?>" data-range="60">1h</button>
                                    <button type="button" class="btn btn-outline-primary btn-timerange" 
                                            data-device-id="<?= $device['id'] ?>" data-range="720">12h</button>
                                </div>
                            </div>
                            
                            <!-- Chart-Container -->
                            <div class="tasmota-charts" id="charts-<?= $device['id'] ?>">
                                <div class="chart-loading text-center py-3">
                                    <i class="bi bi-hourglass-split"></i> Lade Daten...
                                </div>
                            </div>
                            
                            <!-- Aktuelle Werte (kompakt) -->
                            <div class="current-values mt-2" id="current-values-<?= $device['id'] ?>">
                                <div class="row text-center">
                                    <div class="col-3">
                                        <strong class="text-warning"><?= number_format($energyData['power'], 0) ?></strong><br>
                                        <small class="text-muted">Watt</small>
                                    </div>
                                    <div class="col-3">
                                        <strong class="text-info"><?= number_format($energyData['voltage'], 0) ?></strong><br>
                                        <small class="text-muted">Volt</small>
                                    </div>
                                    <div class="col-3">
                                        <strong class="text-danger"><?= number_format($energyData['current'], 2) ?></strong><br>
                                        <small class="text-muted">A</small>
                                    </div>
                                    <div class="col-3">
                                        <strong class="text-success"><?= number_format($energyData['energy_today'], 2) ?></strong><br>
                                        <small class="text-muted">kWh</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Zeitstempel -->
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> 
                                    <?php if ($energyData['minutes_ago'] < 5): ?>
                                        Vor <?= $energyData['minutes_ago'] ?> Min. • <span class="text-success">Live</span>
                                    <?php elseif ($energyData['minutes_ago'] < 60): ?>
                                        Vor <?= $energyData['minutes_ago'] ?> Min.
                                    <?php else: ?>
                                        Vor <?= number_format($energyData['minutes_ago'] / 60, 1) ?> Std.
                                    <?php endif; ?>
                                </small>
                            </div>
                            
                        <?php elseif ($isTasmota): ?>
                            <div class="alert alert-warning py-2 mt-2 mb-0">
                                <small>
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    <?php if ($status === 'offline'): ?>
                                        Offline - Keine aktuellen Daten
                                    <?php else: ?>
                                        Warte auf Datenübertragung...
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Tasmota-Konfiguration-Info -->
                        <?php if ($isTasmota): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-router"></i> 
                                    <?= htmlspecialchars($device['tasmota_ip']) ?>
                                    <?php if ($device['last_tasmota_reading']): ?>
                                        • Zuletzt: <?= date('d.m. H:i', strtotime($device['last_tasmota_reading'])) ?>
                                    <?php endif; ?>
                                    <br>
                                    <i class="bi bi-info-circle"></i> 
                                    Daten über lokalen Collector übertragen
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Keine Geräte gefunden -->
    <div class="row" id="noDevicesFound" style="display: none;">
        <div class="col-12">
            <div class="alert alert-info text-center">
                <i class="bi bi-search"></i> Keine Geräte gefunden. Passen Sie Ihre Suche an.
            </div>
        </div>
    </div>
    
    <!-- Inaktive Geräte (falls vorhanden) -->
    <?php if (!empty($inactiveDevices)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h4 class="text-muted mb-3">
                    <i class="bi bi-archive"></i> Inaktive Geräte (<?= count($inactiveDevices) ?>)
                </h4>
                <div class="row">
                    <?php foreach ($inactiveDevices as $device): ?>
                        <div class="col-xl-4 col-lg-6 mb-3">
                            <div class="card device-card inactive">
                                <div class="card-body py-3">
                                    <h6 class="card-title"><?= htmlspecialchars($device['name']) ?></h6>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-secondary"><?= htmlspecialchars($device['category']) ?></span>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="toggle_device">
                                            <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                            <input type="hidden" name="is_active" value="1">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="bi bi-arrow-clockwise"></i> Aktivieren
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Normales Gerät hinzufügen -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle text-energy"></i> Normales Gerät hinzufügen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add_device">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Gerätename *</label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="z.B. Kühlschrank">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategorie *</label>
                            <select class="form-select" name="category" required>
                                <option value="">Wählen...</option>
                                <option value="Haushaltsgeräte">Haushaltsgeräte</option>
                                <option value="Unterhaltung">Unterhaltung</option>
                                <option value="Beleuchtung">Beleuchtung</option>
                                <option value="Klimatechnik">Klimatechnik</option>
                                <option value="IT/Büro">IT/Büro</option>
                                <option value="Sonstiges">Sonstiges</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Leistung (Watt) *</label>
                            <input type="number" class="form-control" name="wattage" required 
                                   min="1" max="10000" placeholder="150">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check"></i> Gerät hinzufügen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Tasmota-Gerät hinzufügen -->
<div class="modal fade" id="addTasmotaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-wifi text-primary"></i> Tasmota Smart-Gerät hinzufügen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addTasmotaForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add_tasmota_device">
                
                <div class="modal-body">
                    <!-- Grundinformationen -->
                    <h6><i class="bi bi-info-circle"></i> Grundinformationen</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gerätename *</label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="z.B. Sonoff Steckdose">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategorie *</label>
                            <select class="form-select" name="category" required>
                                <option value="Smart Home">Smart Home</option>
                                <option value="Haushaltsgeräte">Haushaltsgeräte</option>
                                <option value="Unterhaltung">Unterhaltung</option>
                                <option value="Beleuchtung">Beleuchtung</option>
                                <option value="Klimatechnik">Klimatechnik</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maximale Leistung (Watt)</label>
                            <input type="number" class="form-control" name="wattage" 
                                   min="1" max="5000" value="100" placeholder="100">
                        </div>
                    </div>
                    
                    <!-- Tasmota-Konfiguration -->
                    <h6><i class="bi bi-router"></i> Tasmota-Konfiguration</h6>
                    <div class="alert alert-info py-2 mb-3">
                        <small>
                            <i class="bi bi-info-circle"></i> 
                            <strong>Hinweis:</strong> Die IP-Adresse wird für die Geräteerkennung gespeichert. 
                            Daten werden über Ihren lokalen Collector übertragen.
                        </small>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">IP-Adresse (optional)</label>
                            <input type="text" class="form-control" name="tasmota_ip" 
                                   placeholder="192.168.1.100" 
                                   pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                            <div class="form-text">
                                Lokale IP-Adresse des Tasmota-Geräts (zur Identifikation)
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tasmota-Name</label>
                            <input type="text" class="form-control" name="tasmota_name" 
                                   placeholder="sonoff-01">
                            <div class="form-text">
                                Gerätename in Tasmota (optional)
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Erwartetes Übertragungsintervall</label>
                            <select class="form-select" name="tasmota_interval">
                                <option value="60">1 Minute</option>
                                <option value="300" selected>5 Minuten</option>
                                <option value="600">10 Minuten</option>
                                <option value="1800">30 Minuten</option>
                                <option value="3600">1 Stunde</option>
                            </select>
                            <div class="form-text">
                                Wie oft der lokale Collector Daten senden soll
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-wifi"></i> Smart-Gerät hinzufügen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Gerät bearbeiten -->
<div class="modal fade" id="editDeviceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil text-warning"></i> Gerät bearbeiten
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editDeviceForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="edit_device">
                <input type="hidden" name="device_id" id="edit_device_id">
                
                <div class="modal-body">
                    <!-- Grundinformationen -->
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gerätename *</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategorie *</label>
                            <select class="form-select" name="category" id="edit_category" required>
                                <option value="Haushaltsgeräte">Haushaltsgeräte</option>
                                <option value="Smart Home">Smart Home</option>
                                <option value="Unterhaltung">Unterhaltung</option>
                                <option value="Beleuchtung">Beleuchtung</option>
                                <option value="Klimatechnik">Klimatechnik</option>
                                <option value="IT/Büro">IT/Büro</option>
                                <option value="Sonstiges">Sonstiges</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Leistung (Watt) *</label>
                            <input type="number" class="form-control" name="wattage" id="edit_wattage" required 
                                   min="1" max="10000">
                        </div>
                    </div>
                    
                    <!-- Tasmota-Konfiguration (falls Smart-Gerät) -->
                    <div id="editTasmotaSection" style="display: none;">
                        <hr>
                        <h6><i class="bi bi-router"></i> Tasmota-Konfiguration</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">IP-Adresse</label>
                                <input type="text" class="form-control" name="tasmota_ip" id="edit_tasmota_ip" 
                                       pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tasmota-Name</label>
                                <input type="text" class="form-control" name="tasmota_name" id="edit_tasmota_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Abfrage-Intervall</label>
                                <select class="form-select" name="tasmota_interval" id="edit_tasmota_interval">
                                    <option value="60">1 Minute</option>
                                    <option value="300">5 Minuten</option>
                                    <option value="600">10 Minuten</option>
                                    <option value="1800">30 Minuten</option>
                                    <option value="3600">1 Stunde</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="tasmota_enabled" 
                                           id="edit_tasmota_enabled">
                                    <label class="form-check-label" for="edit_tasmota_enabled">
                                        Tasmota-Integration aktiviert
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check"></i> Änderungen speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- JavaScript -->
<script>
// Filter und Suche
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('deviceSearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const typeFilter = document.getElementById('typeFilter');
    const deviceItems = document.querySelectorAll('.device-item');
    const noDevicesDiv = document.getElementById('noDevicesFound');
    
    function filterDevices() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value;
        const selectedType = typeFilter.value;
        let visibleCount = 0;
        
        deviceItems.forEach(item => {
            const name = item.dataset.name;
            const category = item.dataset.category;
            const type = item.dataset.type;
            const status = item.dataset.status;
            
            let show = true;
            
            // Suchbegriff
            if (searchTerm && !name.includes(searchTerm)) {
                show = false;
            }
            
            // Kategorie
            if (selectedCategory && category !== selectedCategory) {
                show = false;
            }
            
            // Typ/Status
            if (selectedType) {
                if (selectedType === 'normal' && type !== 'normal') show = false;
                if (selectedType === 'tasmota' && type !== 'tasmota') show = false;
                if (selectedType === 'online' && (type !== 'tasmota' || status !== 'online')) show = false;
                if (selectedType === 'offline' && (type !== 'tasmota' || status !== 'offline')) show = false;
            }
            
            item.style.display = show ? 'block' : 'none';
            if (show) visibleCount++;
        });
        
        noDevicesDiv.style.display = visibleCount === 0 ? 'block' : 'none';
    }
    
    searchInput.addEventListener('input', filterDevices);
    categoryFilter.addEventListener('change', filterDevices);
    typeFilter.addEventListener('change', filterDevices);
});

// Gerät bearbeiten
document.addEventListener('click', function(e) {
    if (e.target.closest('.edit-device')) {
        e.preventDefault();
        const deviceData = JSON.parse(e.target.closest('.edit-device').dataset.device);
        
        // Modal füllen
        document.getElementById('edit_device_id').value = deviceData.id;
        document.getElementById('edit_name').value = deviceData.name;
        document.getElementById('edit_category').value = deviceData.category;
        document.getElementById('edit_wattage').value = deviceData.wattage;
        
        // Tasmota-Sektion zeigen wenn Smart-Gerät
        const tasmotaSection = document.getElementById('editTasmotaSection');
        if (deviceData.tasmota_enabled == 1 || deviceData.tasmota_ip) {
            tasmotaSection.style.display = 'block';
            document.getElementById('edit_tasmota_ip').value = deviceData.tasmota_ip || '';
            document.getElementById('edit_tasmota_name').value = deviceData.tasmota_name || '';
            document.getElementById('edit_tasmota_interval').value = deviceData.tasmota_interval || 300;
            document.getElementById('edit_tasmota_enabled').checked = deviceData.tasmota_enabled == 1;
        } else {
            tasmotaSection.style.display = 'none';
        }
        
        new bootstrap.Modal(document.getElementById('editDeviceModal')).show();
    }
});

// Gerät löschen
document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-device')) {
        e.preventDefault();
        const deviceId = e.target.closest('.delete-device').dataset.deviceId;
        const deviceName = e.target.closest('.delete-device').dataset.deviceName;
        
        if (confirm(`Gerät "${deviceName}" wirklich löschen?\n\nDas Gerät wird deaktiviert und kann später wieder aktiviert werden.`)) {
            // Form erstellen und absenden
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="delete_device">
                <input type="hidden" name="device_id" value="${deviceId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
});

// ✅ VERBESSERTES Auto-Refresh: Schneller und mit Live-Werten
setInterval(function() {
    if (document.visibilityState === 'visible') {
        const tasmotaDevices = document.querySelectorAll('.tasmota-device');
        if (tasmotaDevices.length > 0) {
            // Live-Werte aktualisieren (alle 30 Sekunden)
            refreshLiveValues();
            
            // Charts refreshen (alle 60 Sekunden)
            refreshTasmotaCharts();
        }
    }
}, 30000); // ✅ Schnellerer Refresh: 30s statt 60s

// Neue Funktion: Live-Werte ohne kompletten Seitenreload aktualisieren
function refreshLiveValues() {
    fetch('api/live-device-data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Wattage-Anzeigen aktualisieren
                Object.entries(data.devices).forEach(([deviceId, deviceData]) => {
                    updateDeviceLiveData(deviceId, deviceData);
                });
            }
        })
        .catch(error => {
            console.debug('Live data refresh failed:', error);
        });
}

// Live-Daten für einzelnes Gerät aktualisieren
function updateDeviceLiveData(deviceId, data) {
    // Leistungsanzeige aktualisieren
    const powerElement = document.querySelector(`[data-device-id="${deviceId}"] .live-power`);
    if (powerElement && data.power) {
        powerElement.textContent = Math.round(data.power) + 'W';
    }
    
    // Status-Indicator aktualisieren
    const statusElement = document.querySelector(`[data-device-id="${deviceId}"] .status-indicator`);
    if (statusElement) {
        statusElement.className = `status-indicator status-${data.status || 'unknown'}`;
    }
    
    // Aktuelle Werte aktualisieren falls vorhanden
    updateCurrentValues(deviceId, data.current);
}

// =============================================================================
// TASMOTA CHART FUNKTIONALITÄT
// =============================================================================

// Chart-Instanzen speichern
const chartInstances = new Map();

// Charts nach Seitenladung initialisieren
document.addEventListener('DOMContentLoaded', function() {
    // Alle Tasmota-Geräte mit Daten finden
    document.querySelectorAll('.tasmota-charts').forEach(function(chartContainer) {
        const deviceId = chartContainer.id.replace('charts-', '');
        if (deviceId) {
            initTasmotaChart(deviceId, '5'); // Standard: 5 Minuten
        }
    });
    
    // Zeitraum-Button Event Listeners
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-timerange')) {
            const deviceId = e.target.dataset.deviceId;
            const timeRange = e.target.dataset.range;
            
            // Button-Status ändern
            const buttonGroup = e.target.parentNode;
            buttonGroup.querySelectorAll('.btn-timerange').forEach(btn => {
                btn.classList.remove('active');
            });
            e.target.classList.add('active');
            
            // Chart neu laden
            initTasmotaChart(deviceId, timeRange);
        }
    });
});

// Chart für Gerät initialisieren
function initTasmotaChart(deviceId, timeRange = '60') {
    const container = document.getElementById(`charts-${deviceId}`);
    if (!container) return;
    
    // Loading anzeigen
    container.innerHTML = `
        <div class="chart-loading text-center py-3">
            <i class="bi bi-hourglass-split"></i> Lade Verlaufsdaten...
        </div>
    `;
    
    // Chart-Typen definieren
    const chartTypes = [
        { key: 'power', label: 'Leistung', color: '#f59e0b', unit: 'W' },
        { key: 'voltage', label: 'Spannung', color: '#3b82f6', unit: 'V' },
        { key: 'current', label: 'Stromstärke', color: '#ef4444', unit: 'A' },
        { key: 'energy', label: 'Energie', color: '#10b981', unit: 'kWh' }
    ];
    
    // Container für Charts erstellen
    container.innerHTML = `
        <div class="refresh-indicator" id="refresh-${deviceId}"></div>
        <div class="chart-tabs" id="chart-tabs-${deviceId}">
            ${chartTypes.map((type, index) => 
                `<button class="chart-tab ${index === 0 ? 'active' : ''}" 
                         data-type="${type.key}" data-device="${deviceId}">
                    ${type.label}
                </button>`
            ).join('')}
        </div>
        <div class="chart-container">
            <canvas id="chart-canvas-${deviceId}"></canvas>
        </div>
    `;
    
    // Tab-Events
    container.querySelectorAll('.chart-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const chartType = this.dataset.type;
            const devId = this.dataset.device;
            
            // Tab-Status ändern
            container.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Chart-Daten laden
            loadChartData(devId, timeRange, chartType);
        });
    });
    
    // Ersten Chart laden (Leistung)
    loadChartData(deviceId, timeRange, 'power');
}

// Chart-Daten von API laden
function loadChartData(deviceId, timeRange, chartType) {
    const refreshIndicator = document.getElementById(`refresh-${deviceId}`);
    if (refreshIndicator) {
        refreshIndicator.classList.add('active');
    }
    
    fetch(`api/tasmota-chart-data.php?device_id=${deviceId}&timerange=${timeRange}&type=${chartType}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createChart(deviceId, data);
                updateCurrentValues(deviceId, data.current);
            } else {
                showChartError(deviceId, data.error || 'Fehler beim Laden der Daten');
            }
        })
        .catch(error => {
            console.error('Chart data error:', error);
            showChartError(deviceId, 'Verbindungsfehler');
        })
        .finally(() => {
            if (refreshIndicator) {
                refreshIndicator.classList.remove('active');
            }
        });
}

// Chart erstellen/aktualisieren
function createChart(deviceId, data) {
    const canvas = document.getElementById(`chart-canvas-${deviceId}`);
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Alten Chart zerstören falls vorhanden
    const chartKey = `chart-${deviceId}`;
    if (chartInstances.has(chartKey)) {
        chartInstances.get(chartKey).destroy();
    }
    
    // Neuen Chart erstellen
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.chart.labels,
            datasets: [{
                label: `${data.chart.type} (${data.chart.unit})`,
                data: data.chart.data,
                borderColor: data.chart.color,
                backgroundColor: data.chart.color + '20',
                borderWidth: 2,
                fill: true,
                tension: 0.3,
                pointRadius: 2,
                pointHoverRadius: 4,
                pointBackgroundColor: data.chart.color,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: data.chart.color,
                    borderWidth: 1,
                    cornerRadius: 6,
                    displayColors: false,
                    callbacks: {
                        title: function(tooltipItems) {
                            return tooltipItems[0].label;
                        },
                        label: function(context) {
                            return `${context.parsed.y} ${data.chart.unit}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 8,
                        color: '#6b7280'
                    }
                },
                y: {
                    display: true,
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        color: '#6b7280',
                        callback: function(value) {
                            return value + ' ' + data.chart.unit;
                        }
                    }
                }
            },
            animation: {
                duration: 750,
                easing: 'easeInOutQuart'
            }
        }
    });
    
    // Chart-Instanz speichern
    chartInstances.set(chartKey, chart);
}

// Aktuelle Werte aktualisieren
function updateCurrentValues(deviceId, currentData) {
    const container = document.getElementById(`current-values-${deviceId}`);
    if (!container || !currentData) return;
    
    container.innerHTML = `
        <div class="row text-center">
            <div class="col-3">
                <strong class="text-warning">${currentData.power}</strong><br>
                <small class="text-muted">Watt</small>
            </div>
            <div class="col-3">
                <strong class="text-info">${currentData.voltage}</strong><br>
                <small class="text-muted">Volt</small>
            </div>
            <div class="col-3">
                <strong class="text-danger">${currentData.current}</strong><br>
                <small class="text-muted">A</small>
            </div>
            <div class="col-3">
                <strong class="text-success">${currentData.energy_today}</strong><br>
                <small class="text-muted">kWh</small>
            </div>
        </div>
    `;
}

// Chart-Fehler anzeigen
function showChartError(deviceId, errorMessage) {
    const container = document.getElementById(`charts-${deviceId}`);
    if (container) {
        container.innerHTML = `
            <div class="alert alert-warning py-2 text-center">
                <small><i class="bi bi-exclamation-triangle"></i> ${errorMessage}</small>
            </div>
        `;
    }
}

// Alle Tasmota-Charts refreshen
function refreshTasmotaCharts() {
    document.querySelectorAll('.btn-timerange.active').forEach(button => {
        const deviceId = button.dataset.deviceId;
        const timeRange = button.dataset.range;
        const activeTab = document.querySelector(`#chart-tabs-${deviceId} .chart-tab.active`);
        const chartType = activeTab ? activeTab.dataset.type : 'power';
        
        loadChartData(deviceId, timeRange, chartType);
    });
}

// Chart-Größe bei Fenster-Resize anpassen
window.addEventListener('resize', function() {
    chartInstances.forEach(chart => {
        chart.resize();
    });
});
</script>