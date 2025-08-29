<?php
// geraete.php
// ERWEITERTE Geräte-Verwaltung mit Messfunktion

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Geräte-Verwaltung - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF-Token prüfen
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Flash::error('Sicherheitsfehler. Bitte versuchen Sie es erneut.');
    } else {
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                // Neues Gerät hinzufügen
                $name = trim($_POST['name'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $wattage = (int)($_POST['wattage'] ?? 0);
                
                if (empty($name) || empty($category) || $wattage <= 0) {
                    Flash::error('Bitte füllen Sie alle Felder korrekt aus.');
                } else {
                    $deviceId = Database::insert('devices', [
                        'user_id' => $userId,
                        'name' => $name,
                        'category' => $category,
                        'wattage' => $wattage,
                        'is_active' => 1
                    ]);
                    
                    if ($deviceId) {
                        Flash::success("Gerät '$name' wurde erfolgreich hinzugefügt.");
                    } else {
                        Flash::error('Fehler beim Hinzufügen des Geräts.');
                    }
                }
                break;
                
            case 'edit':
                // Gerät bearbeiten
                $deviceId = (int)($_POST['device_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $wattage = (int)($_POST['wattage'] ?? 0);
                
                if ($deviceId <= 0 || empty($name) || empty($category) || $wattage <= 0) {
                    Flash::error('Bitte füllen Sie alle Felder korrekt aus.');
                } else {
                    $success = Database::update('devices', [
                        'name' => $name,
                        'category' => $category,
                        'wattage' => $wattage
                    ], 'id = ? AND user_id = ?', [$deviceId, $userId]);
                    
                    if ($success) {
                        Flash::success("Gerät '$name' wurde erfolgreich aktualisiert.");
                    } else {
                        Flash::error('Fehler beim Bearbeiten des Geräts.');
                    }
                }
                break;
                
            case 'delete':
                // Gerät löschen (deaktivieren)
                $deviceId = (int)($_POST['device_id'] ?? 0);
                
                if ($deviceId <= 0) {
                    Flash::error('Ungültige Gerät-ID.');
                } else {
                    // Gerät deaktivieren statt löschen (für Datenintegrität)
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
                
            case 'activate':
                // Gerät reaktivieren
                $deviceId = (int)($_POST['device_id'] ?? 0);
                
                if ($deviceId <= 0) {
                    Flash::error('Ungültige Gerät-ID.');
                } else {
                    $success = Database::update('devices', [
                        'is_active' => 1
                    ], 'id = ? AND user_id = ?', [$deviceId, $userId]);
                    
                    if ($success) {
                        Flash::success('Gerät wurde erfolgreich aktiviert.');
                    } else {
                        Flash::error('Fehler beim Aktivieren des Geräts.');
                    }
                }
                break;
                
            case 'add_measurement':
                // Neue Messung hinzufügen
                $deviceId = (int)($_POST['device_id'] ?? 0);
                $consumptionType = $_POST['consumption_type'] ?? 'direct';
                $measurementDate = $_POST['measurement_date'] ?? date('Y-m-d H:i:s');
                $notes = trim($_POST['notes'] ?? '');
                
                // Gerät prüfen
                $device = Database::fetchOne(
                    "SELECT * FROM devices WHERE id = ? AND user_id = ? AND is_active = 1",
                    [$deviceId, $userId]
                );
                
                if (!$device) {
                    Flash::error('Gerät nicht gefunden oder nicht aktiv.');
                    break;
                }
                
                // Datum validieren
                if (empty($measurementDate)) {
                    Flash::error('Bitte geben Sie ein gültiges Datum ein.');
                    break;
                }
                
                // Datum nicht in der Zukunft
                if (strtotime($measurementDate) > time()) {
                    Flash::error('Das Datum darf nicht in der Zukunft liegen.');
                    break;
                }
                
                $consumption = 0;
                $cost = 0;
                
                if ($consumptionType === 'direct') {
                    // Direkter kWh-Wert
                    $consumption = (float)($_POST['consumption_kwh'] ?? 0);
                    
                    if ($consumption <= 0) {
                        Flash::error('Bitte geben Sie einen gültigen Verbrauch ein.');
                        break;
                    }
                } else {
                    // Berechnung aus Zeit und Leistung
                    $hours = (float)($_POST['usage_hours'] ?? 0);
                    $wattage = $device['wattage'];
                    
                    if ($hours <= 0) {
                        Flash::error('Bitte geben Sie eine gültige Nutzungsdauer ein.');
                        break;
                    }
                    
                    $consumption = ($wattage * $hours) / 1000; // Watt-Stunden zu kWh
                }
                
                // Aktueller Strompreis
                $currentRate = 0.32; // Fallback
                
                // Tarif zum Messzeitpunkt holen
                $tariff = Database::fetchOne(
                    "SELECT rate_per_kwh FROM tariff_periods 
                     WHERE user_id = ? AND ? BETWEEN valid_from AND COALESCE(valid_to, CURDATE())
                     ORDER BY valid_from DESC LIMIT 1",
                    [$userId, date('Y-m-d', strtotime($measurementDate))]
                );
                
                if (!$tariff) {
                    // Fallback: Aktuellen oder neuesten Tarif nehmen
                    $tariff = Database::fetchOne(
                        "SELECT rate_per_kwh FROM tariff_periods 
                         WHERE user_id = ? AND is_active = 1 
                         ORDER BY valid_from DESC LIMIT 1",
                        [$userId]
                    );
                }
                
                if ($tariff) {
                    $currentRate = (float)$tariff['rate_per_kwh'];
                }
                
                $cost = $consumption * $currentRate;
                
                // Messung speichern
                $measurementId = Database::insert('energy_consumption', [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                    'consumption' => $consumption,
                    'cost' => $cost,
                    'timestamp' => $measurementDate,
                    'notes' => $notes
                ]);
                
                if ($measurementId) {
                    $dateFormatted = date('d.m.Y H:i', strtotime($measurementDate));
                    Flash::success("Messung für '{$device['name']}' vom {$dateFormatted} erfolgreich hinzugefügt: " . number_format($consumption, 2) . " kWh (". number_format($cost, 2) . " €)");
                } else {
                    Flash::error('Fehler beim Speichern der Messung.');
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: geraete.php');
    exit;
}

// Filter-Parameter
$showInactive = isset($_GET['inactive']) && $_GET['inactive'] === '1';
$categoryFilter = $_GET['category'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Geräte laden
$whereConditions = ['d.user_id = ?'];
$params = [$userId];

if (!$showInactive) {
    $whereConditions[] = 'd.is_active = 1';
}

if (!empty($categoryFilter)) {
    $whereConditions[] = 'd.category = ?';
    $params[] = $categoryFilter;
}

if (!empty($searchTerm)) {
    $whereConditions[] = 'd.name LIKE ?';
    $params[] = '%' . $searchTerm . '%';
}

$whereClause = implode(' AND ', $whereConditions);

$devices = Database::fetchAll(
    "SELECT d.*, 
            COALESCE(SUM(ec.consumption), 0) as total_consumption,
            COALESCE(SUM(ec.cost), 0) as total_cost,
            COUNT(ec.id) as usage_count,
            MAX(ec.timestamp) as last_measurement
     FROM devices d 
     LEFT JOIN energy_consumption ec ON d.id = ec.device_id 
     WHERE $whereClause
     GROUP BY d.id 
     ORDER BY d.is_active DESC, d.name ASC",
    $params
) ?: [];

// Kategorien für Filter laden
$categories = Database::fetchAll(
    "SELECT DISTINCT category FROM devices WHERE user_id = ? AND is_active = 1 ORDER BY category",
    [$userId]
) ?: [];

// Statistiken berechnen
$activeDevices = array_filter($devices, fn($d) => $d['is_active']);
$inactiveDevices = array_filter($devices, fn($d) => !$d['is_active']);

// Realistischere Statistiken basierend auf tatsächlichen Messungen
$totalMeasurements = array_sum(array_map(fn($d) => $d['usage_count'], $devices));
$totalActualConsumption = array_sum(array_map(fn($d) => $d['total_consumption'], $devices));
$totalActualCost = array_sum(array_map(fn($d) => $d['total_cost'], $devices));

// Durchschnittlicher Tagesverbrauch (letzte 30 Tage)
$avgDailyConsumption = 0;
if ($totalMeasurements > 0) {
    $recentConsumption = Database::fetchOne(
        "SELECT COALESCE(SUM(consumption), 0) as total 
         FROM energy_consumption 
         WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        [$userId]
    );
    $avgDailyConsumption = ($recentConsumption['total'] ?? 0) / 30;
}

$stats = [
    'total_devices' => count($activeDevices),
    'inactive_devices' => count($inactiveDevices),
    'total_measurements' => $totalMeasurements,
    'total_actual_consumption' => $totalActualConsumption,
    'total_actual_cost' => $totalActualCost,
    'avg_daily_consumption' => $avgDailyConsumption,
    'categories' => count($categories),
    'max_theoretical_power' => array_sum(array_map(fn($d) => $d['is_active'] ? ($d['wattage'] ?? 0) : 0, $devices))
];

include 'includes/header.php';
include 'includes/navbar.php';
?>
<!-- Tasmota Geräte-Karten (Für Integration in bestehende Geräteliste) -->
<style>
.tasmota-device-card {
    border-left: 4px solid #28a745 !important;
    position: relative;
}

.tasmota-device-card::before {
    content: "SMART";
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

.tasmota-controls {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.energy-data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.energy-data-item {
    text-align: center;
    padding: 10px;
    background: rgba(40, 167, 69, 0.1);
    border-radius: 8px;
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.energy-value {
    font-size: 1.2em;
    font-weight: bold;
    color: #28a745;
    display: block;
}

.energy-label {
    font-size: 0.8em;
    color: #6c757d;
    margin-top: 5px;
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
                        <p class="text-muted mb-0">Verwalten Sie Ihre elektrischen Geräte und deren Stromverbrauch.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-energy" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                            <i class="bi bi-plus-circle me-2"></i>
                            Neues Gerät
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistik Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card primary">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-cpu"></i>
                    <div class="small">
                        <?= $stats['categories'] ?> Kategorien
                    </div>
                </div>
                <h3><?= $stats['total_devices'] ?></h3>
                <p>Aktive Geräte</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card success">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-lightning-charge"></i>
                    <div class="small">
                        Gemessen
                    </div>
                </div>
                <h3><?= number_format($stats['total_actual_consumption'], 1) ?></h3>
                <p>kWh Gesamtverbrauch</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card warning">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-currency-euro"></i>
                    <div class="small">
                        Gesamtkosten
                    </div>
                </div>
                <h3><?= number_format($stats['total_actual_cost'], 2) ?> €</h3>
                <p>Alle Geräte</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card energy">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-graph-up"></i>
                    <div class="small">
                        Ø täglich (30d)
                    </div>
                </div>
                <h3><?= number_format($stats['avg_daily_consumption'], 1) ?></h3>
                <p>kWh pro Tag</p>
            </div>
        </div>
    </div>
    
    <!-- Zusätzliche Info-Box -->
    <?php if ($stats['total_measurements'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info">
                <div class="row text-center">
                    <div class="col-md-3">
                        <strong><?= $stats['total_measurements'] ?></strong><br>
                        <small>Messungen erfasst</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= number_format($stats['max_theoretical_power']) ?> W</strong><br>
                        <small>Max. Anschlussleistung</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= $stats['total_actual_consumption'] > 0 ? number_format(($stats['total_actual_cost'] / $stats['total_actual_consumption']), 4) : '0.0000' ?> €</strong><br>
                        <small>Ø Strompreis/kWh</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= number_format($stats['avg_daily_consumption'] * 365, 0) ?> kWh</strong><br>
                        <small>Hochrechnung/Jahr</small>
                    </div>
                </div>
                <hr class="my-2">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Hinweis:</strong> Die Statistiken basieren auf Ihren tatsächlichen Messungen. 
                    Die Anschlussleistung zeigt die theoretische Maximalleistung wenn alle Geräte gleichzeitig mit voller Leistung liefen.
                </small>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filter & Suche -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="bi bi-search me-1"></i>Gerät suchen
                            </label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?= htmlspecialchars($searchTerm) ?>" 
                                   placeholder="Gerätename eingeben...">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="bi bi-tags me-1"></i>Kategorie
                            </label>
                            <select class="form-select" name="category">
                                <option value="">Alle Kategorien</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat['category']) ?>" 
                                            <?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['category']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="bi bi-toggles me-1"></i>Status
                            </label>
                            <select class="form-select" name="inactive">
                                <option value="0" <?= !$showInactive ? 'selected' : '' ?>>Nur aktive</option>
                                <option value="1" <?= $showInactive ? 'selected' : '' ?>>Alle anzeigen</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Filtern
                                </button>
                                <?php if (!empty($searchTerm) || !empty($categoryFilter) || $showInactive): ?>
                                    <a href="geraete.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x-circle"></i> Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Geräte-Liste -->
    <div class="card">
        <div class="card-header">
            <div class="flex-between">
                <h5 class="mb-0">
                    <i class="bi bi-list text-energy"></i>
                    Meine Geräte (<?= count($devices) ?>)
                </h5>
                <div class="btn-group btn-group-sm">
                    <a href="?<?= http_build_query(array_merge($_GET, ['inactive' => '0'])) ?>" 
                       class="btn btn-outline-success <?= !$showInactive ? 'active' : '' ?>">
                        <i class="bi bi-check-circle"></i> Aktive
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['inactive' => '1'])) ?>" 
                       class="btn btn-outline-warning <?= $showInactive ? 'active' : '' ?>">
                        <i class="bi bi-list"></i> Alle
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($devices)): ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-cpu display-2 text-muted"></i>
                    </div>
                    <h4 class="text-muted">Keine Geräte gefunden</h4>
                    <p class="text-muted mb-4">
                        <?php if (!empty($searchTerm) || !empty($categoryFilter)): ?>
                            Versuchen Sie andere Suchkriterien oder 
                            <a href="geraete.php" class="text-energy">alle Geräte anzeigen</a>.
                        <?php else: ?>
                            Fügen Sie Ihr erstes Gerät hinzu, um zu beginnen.
                        <?php endif; ?>
                    </p>
                    <?php if (empty($searchTerm) && empty($categoryFilter)): ?>
                        <button class="btn btn-energy" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                            <i class="bi bi-plus-circle me-2"></i>
                            Erstes Gerät hinzufügen
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: var(--gray-50);">
                            <tr>
                                <th>Status</th>
                                <th>Gerät</th>
                                <th>Kategorie</th>
                                <th>Leistung</th>
                                <th>Gesamtverbrauch</th>
                                <th>Gesamtkosten</th>
                                <th>Messungen</th>
                                <th>Letzte Messung</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $device): ?>
                                <tr class="<?= !$device['is_active'] ? 'table-secondary' : '' ?>">
                                    <td>
                                        <?php if ($device['is_active']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Aktiv
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-x-circle"></i> Inaktiv
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-cpu text-primary me-2"></i>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($device['name']) ?></div>
                                                <small class="text-muted">ID: <?= $device['id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--gray-200); color: var(--gray-700);">
                                            <?= htmlspecialchars($device['category']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-energy"><?= number_format($device['wattage']) ?> W</span>
                                    </td>
                                    <td>
                                        <?php if ($device['total_consumption'] > 0): ?>
                                            <span class="fw-bold"><?= number_format($device['total_consumption'], 2) ?> kWh</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($device['total_cost'] > 0): ?>
                                            <span class="fw-bold text-success"><?= number_format($device['total_cost'], 2) ?> €</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $device['usage_count'] ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($device['last_measurement'])): ?>
                                            <div class="small">
                                                <strong><?= date('d.m.Y', strtotime($device['last_measurement'])) ?></strong><br>
                                                <span class="text-muted"><?= date('H:i', strtotime($device['last_measurement'])) ?> Uhr</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Keine Messung</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editDevice(<?= htmlspecialchars(json_encode($device)) ?>)" 
                                                    title="Bearbeiten">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <?php if ($device['is_active']): ?>
                                                <button class="btn btn-outline-success" 
                                                        onclick="addMeasurement(<?= $device['id'] ?>, '<?= htmlspecialchars($device['name']) ?>', <?= $device['wattage'] ?>)" 
                                                        title="Messung hinzufügen">
                                                    <i class="bi bi-plus-circle"></i>
                                                </button>
                                                
                                                <button class="btn btn-outline-warning" 
                                                        onclick="deleteDevice(<?= $device['id'] ?>, '<?= htmlspecialchars($device['name']) ?>')" 
                                                        title="Deaktivieren">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-outline-success" 
                                                        onclick="activateDevice(<?= $device['id'] ?>, '<?= htmlspecialchars($device['name']) ?>')" 
                                                        title="Aktivieren">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
                                   
                    <!-- Verbindungstest-Ergebnis -->
                    <div id="tasmotaTestResult" class="alert alert-info d-none">
                        <div class="d-flex align-items-center">
                            <div class="spinner-border spinner-border-sm me-2" role="status">
                                <span class="visually-hidden">Teste...</span>
                            </div>
                            <span>Teste Verbindung zur Tasmota-Steckdose...</span>
                        </div>
                    </div>
                    
                    <!-- Live-Daten Vorschau -->
                    <div id="tasmotaLiveData" class="card border-success d-none">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-lightning-charge"></i> 
                                Live-Daten von der Steckdose
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="tasmotaEnergyDisplay">
                                <!-- Wird dynamisch befüllt -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Erweiterte Einstellungen -->
                    <div class="accordion mt-3" id="advancedSettings">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#advancedOptions">
                                    <i class="bi bi-gear me-2"></i>
                                    Erweiterte Einstellungen
                                </button>
                            </h2>
                            <div id="advancedOptions" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Maximale Leistung (Watt)</label>
                                            <input type="number" class="form-control" name="wattage" 
                                                   id="tasmota_wattage" placeholder="Auto-Erkennung">
                                            <div class="form-text">
                                                Wird automatisch aus Tasmota ausgelesen, falls leer
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Standby-Verbrauch (Watt)</label>
                                            <input type="number" class="form-control" name="standby_power" 
                                                   step="0.1" placeholder="z.B. 0.5">
                                            <div class="form-text">
                                                Verbrauch im Standby-Modus
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <label class="form-label">Notizen</label>
                                        <textarea class="form-control" name="notes" rows="2" 
                                                  placeholder="Zusätzliche Informationen zum Gerät..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-energy" id="saveTasmotaDevice">
                        <i class="bi bi-check-lg"></i>
                        Gerät speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Device Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle text-energy"></i>
                        Neues Gerät hinzufügen
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Gerätename</label>
                        <input type="text" class="form-control" name="name" required 
                               placeholder="z.B. Waschmaschine Siemens">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kategorie</label>
                        <select class="form-select" name="category" required>
                            <option value="">Kategorie wählen</option>
                            <option value="Haushaltsgeräte">Haushaltsgeräte</option>
                            <option value="Unterhaltung">Unterhaltung</option>
                            <option value="Büro">Büro</option>
                            <option value="Beleuchtung">Beleuchtung</option>
                            <option value="Heizung/Klima">Heizung/Klima</option>
                            <option value="Sonstiges">Sonstiges</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Leistung (Watt)</label>
                        <input type="number" class="form-control" name="wattage" required 
                               min="1" max="10000" placeholder="z.B. 2000">
                        <div class="form-text">Maximale Leistungsaufnahme in Watt</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-energy">
                        <i class="bi bi-check-circle me-1"></i>Hinzufügen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Device Modal -->
<div class="modal fade" id="editDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil text-energy"></i>
                        Gerät bearbeiten
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="device_id" id="edit_device_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Gerätename</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kategorie</label>
                        <select class="form-select" name="category" id="edit_category" required>
                            <option value="Haushaltsgeräte">Haushaltsgeräte</option>
                            <option value="Unterhaltung">Unterhaltung</option>
                            <option value="Büro">Büro</option>
                            <option value="Beleuchtung">Beleuchtung</option>
                            <option value="Heizung/Klima">Heizung/Klima</option>
                            <option value="Sonstiges">Sonstiges</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Leistung (Watt)</label>
                        <input type="number" class="form-control" name="wattage" id="edit_wattage" required min="1" max="10000">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-energy">
                        <i class="bi bi-check-circle me-1"></i>Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Measurement Modal -->
<div class="modal fade" id="addMeasurementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle text-success"></i>
                        Messung hinzufügen
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add_measurement">
                    <input type="hidden" name="device_id" id="measurement_device_id">
                    
                    <div class="alert alert-info">
                        <strong id="measurement_device_name">Gerät</strong><br>
                        <small>Leistung: <span id="measurement_device_wattage">0</span> Watt</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Datum und Zeit der Messung</label>
                        <div class="row">
                            <div class="col-md-8">
                                <input type="datetime-local" class="form-control" name="measurement_date" 
                                       id="measurement_date" value="<?= date('Y-m-d\TH:i') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" onchange="setQuickDate(this.value)">
                                    <option value="">Schnellauswahl</option>
                                    <option value="now">Jetzt</option>
                                    <option value="today_morning">Heute früh</option>
                                    <option value="yesterday">Gestern</option>
                                    <option value="last_week">Letzte Woche</option>
                                    <option value="last_month">Letzter Monat</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-text">
                            Wann wurde der Verbrauch gemessen/verursacht? 
                            <br><small class="text-muted">Beispiele: Waschgang gestern, Gaming letzte Woche, Verbrauch vom letzten Monat nachtragen</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Verbrauch erfassen</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="consumption_type" id="direct_kwh" value="direct" checked onchange="toggleMeasurementType()">
                            <label class="form-check-label" for="direct_kwh">
                                Direkter kWh-Wert
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="consumption_type" id="calculated" value="calculated" onchange="toggleMeasurementType()">
                            <label class="form-check-label" for="calculated">
                                Aus Nutzungsdauer berechnen
                            </label>
                        </div>
                    </div>
                    
                    <div id="direct_input" class="mb-3">
                        <label class="form-label">Verbrauch (kWh)</label>
                        <input type="number" class="form-control" name="consumption_kwh" 
                               step="0.001" min="0" placeholder="z.B. 2.5">
                        <div class="form-text">Gemessener oder abgelesener Verbrauch</div>
                    </div>
                    
                    <div id="calculated_input" class="mb-3" style="display: none;">
                        <label class="form-label">Nutzungsdauer (Stunden)</label>
                        <input type="number" class="form-control" name="usage_hours" 
                               step="0.1" min="0" placeholder="z.B. 3.5">
                        <div class="form-text">Verbrauch wird automatisch berechnet: <span id="calculated_result">-</span></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notizen <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" name="notes" rows="2" 
                                  placeholder="z.B. Waschgang, Gaming-Session..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Messung speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Forms für Actions -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="device_id" id="delete_device_id">
</form>

<form method="POST" id="activateForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="activate">
    <input type="hidden" name="device_id" id="activate_device_id">
</form>

<?php include 'includes/footer.php'; ?>

<!-- JavaScript -->
<script>
// Device bearbeiten
function editDevice(device) {
    document.getElementById('edit_device_id').value = device.id;
    document.getElementById('edit_name').value = device.name;
    document.getElementById('edit_category').value = device.category;
    document.getElementById('edit_wattage').value = device.wattage;
    
    const modal = new bootstrap.Modal(document.getElementById('editDeviceModal'));
    modal.show();
}

// Device löschen/deaktivieren
function deleteDevice(deviceId, deviceName) {
    if (confirm(`Möchten Sie das Gerät "${deviceName}" wirklich deaktivieren?\n\nDas Gerät wird nicht gelöscht, sondern nur deaktiviert und kann später wieder aktiviert werden.`)) {
        document.getElementById('delete_device_id').value = deviceId;
        document.getElementById('deleteForm').submit();
    }
}

// Device aktivieren
function activateDevice(deviceId, deviceName) {
    if (confirm(`Möchten Sie das Gerät "${deviceName}" wieder aktivieren?`)) {
        document.getElementById('activate_device_id').value = deviceId;
        document.getElementById('activateForm').submit();
    }
}

// Messung hinzufügen
function addMeasurement(deviceId, deviceName, wattage) {
    // Elemente prüfen bevor wir sie verwenden
    const deviceIdInput = document.getElementById('measurement_device_id');
    const deviceNameSpan = document.getElementById('measurement_device_name');
    const deviceWattageSpan = document.getElementById('measurement_device_wattage');
    const dateInput = document.getElementById('measurement_date');
    const directRadio = document.getElementById('direct_kwh');
    
    if (!deviceIdInput || !deviceNameSpan || !deviceWattageSpan || !dateInput || !directRadio) {
        console.error('Measurement modal elements not found');
        alert('Fehler beim Öffnen des Messung-Dialogs. Bitte laden Sie die Seite neu.');
        return;
    }
    
    deviceIdInput.value = deviceId;
    deviceNameSpan.textContent = deviceName;
    deviceWattageSpan.textContent = wattage;
    
    // Reset form
    const form = document.querySelector('#addMeasurementModal form');
    if (form) {
        form.reset();
        deviceIdInput.value = deviceId; // Nach reset wieder setzen
    }
    
    directRadio.checked = true;
    
    // Datum auf jetzt setzen
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    dateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
    
    toggleMeasurementType();
    
    const modal = new bootstrap.Modal(document.getElementById('addMeasurementModal'));
    modal.show();
}

// Messungs-Typ umschalten
function toggleMeasurementType() {
    const directRadio = document.getElementById('direct_kwh');
    const directInput = document.getElementById('direct_input');
    const calculatedInput = document.getElementById('calculated_input');
    const consumptionInput = document.querySelector('input[name="consumption_kwh"]');
    const hoursInput = document.querySelector('input[name="usage_hours"]');
    
    if (!directRadio || !directInput || !calculatedInput || !consumptionInput || !hoursInput) {
        console.error('Toggle elements not found');
        return;
    }
    
    const isDirect = directRadio.checked;
    
    if (isDirect) {
        directInput.style.display = 'block';
        calculatedInput.style.display = 'none';
        consumptionInput.required = true;
        hoursInput.required = false;
    } else {
        directInput.style.display = 'none';
        calculatedInput.style.display = 'block';
        consumptionInput.required = false;
        hoursInput.required = true;
    }
    
    calculateConsumption();
}

// Verbrauch berechnen
function calculateConsumption() {
    const hoursInput = document.querySelector('input[name="usage_hours"]');
    const resultSpan = document.getElementById('calculated_result');
    const wattageSpan = document.getElementById('measurement_device_wattage');
    
    if (!hoursInput || !resultSpan || !wattageSpan) {
        console.error('Calculation elements not found');
        return;
    }
    
    const wattage = parseInt(wattageSpan.textContent);
    
    if (hoursInput.value && wattage && !isNaN(wattage)) {
        const hours = parseFloat(hoursInput.value);
        const kwh = (wattage * hours) / 1000;
        resultSpan.textContent = kwh.toFixed(3) + ' kWh';
    } else {
        resultSpan.textContent = '-';
    }
}

// Schnell-Datum setzen
function setQuickDate(period) {
    const dateInput = document.getElementById('measurement_date');
    if (!dateInput || !period) return;
    
    const now = new Date();
    let targetDate = new Date();
    
    switch (period) {
        case 'now':
            targetDate = now;
            break;
        case 'today_morning':
            targetDate.setHours(8, 0, 0, 0);
            break;
        case 'yesterday':
            targetDate.setDate(now.getDate() - 1);
            targetDate.setHours(12, 0, 0, 0);
            break;
        case 'last_week':
            targetDate.setDate(now.getDate() - 7);
            targetDate.setHours(12, 0, 0, 0);
            break;
        case 'last_month':
            targetDate.setMonth(now.getMonth() - 1);
            targetDate.setDate(1);
            targetDate.setHours(12, 0, 0, 0);
            break;
        default:
            return;
    }
    
    // Format für datetime-local input
    const year = targetDate.getFullYear();
    const month = String(targetDate.getMonth() + 1).padStart(2, '0');
    const day = String(targetDate.getDate()).padStart(2, '0');
    const hours = String(targetDate.getHours()).padStart(2, '0');
    const minutes = String(targetDate.getMinutes()).padStart(2, '0');
    
    dateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
}

// Auto-focus auf erstes Feld in Modals
document.addEventListener('DOMContentLoaded', function() {
    // Add Modal
    const addModal = document.getElementById('addDeviceModal');
    if (addModal) {
        addModal.addEventListener('shown.bs.modal', function() {
            const nameInput = document.querySelector('#addDeviceModal input[name="name"]');
            if (nameInput) nameInput.focus();
        });
    }
    
    // Edit Modal
    const editModal = document.getElementById('editDeviceModal');
    if (editModal) {
        editModal.addEventListener('shown.bs.modal', function() {
            const editNameInput = document.getElementById('edit_name');
            if (editNameInput) editNameInput.focus();
        });
    }
    
    // Measurement Modal
    const measurementModal = document.getElementById('addMeasurementModal');
    if (measurementModal) {
        measurementModal.addEventListener('shown.bs.modal', function() {
            const consumptionInput = document.querySelector('#addMeasurementModal input[name="consumption_kwh"]');
            if (consumptionInput) consumptionInput.focus();
        });
    }
    
    // Live-Berechnung für Nutzungsdauer
    const hoursInput = document.querySelector('input[name="usage_hours"]');
    if (hoursInput) {
        hoursInput.addEventListener('input', calculateConsumption);
    }
    
    // Tooltips initialisieren
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    if (tooltipTriggerList.length > 0) {
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Debug: Prüfen ob alle Modal-Elemente existieren
    console.log('Modal elements check:', {
        addModal: !!document.getElementById('addDeviceModal'),
        editModal: !!document.getElementById('editDeviceModal'),
        measurementModal: !!document.getElementById('addMeasurementModal'),
        deviceIdInput: !!document.getElementById('measurement_device_id'),
        deviceNameSpan: !!document.getElementById('measurement_device_name'),
        deviceWattageSpan: !!document.getElementById('measurement_device_wattage'),
        dateInput: !!document.getElementById('measurement_date')
    });
});

document.addEventListener('DOMContentLoaded', function() {
    
    // Verbindungstest
    document.getElementById('testTasmotaConnection').addEventListener('click', async function() {
        const ipInput = document.getElementById('tasmota_ip');
        const ip = ipInput.value.trim();
        
        if (!ip) {
            alert('Bitte geben Sie eine IP-Adresse ein');
            return;
        }
        
        const resultDiv = document.getElementById('tasmotaTestResult');
        const liveDataDiv = document.getElementById('tasmotaLiveData');
        
        // Test-UI anzeigen
        resultDiv.classList.remove('d-none');
        resultDiv.className = 'alert alert-info';
        resultDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                <span>Teste Verbindung zu ${ip}...</span>
            </div>
        `;
        
        try {
            const response = await fetch(`/api/tasmota.php?action=test&ip=${ip}`);
            const data = await response.json();
            
            if (data.success && data.energy_data) {
                // Erfolg
                resultDiv.className = 'alert alert-success';
                resultDiv.innerHTML = `
                    <i class="bi bi-check-circle"></i>
                    <strong>Verbindung erfolgreich!</strong> Tasmota-Gerät gefunden.
                `;
                
                // Live-Daten anzeigen
                displayLiveData(data.energy_data);
                liveDataDiv.classList.remove('d-none');
                
                // Geräte-Name automatisch setzen falls vorhanden
                if (data.raw_data && data.raw_data.Status && data.raw_data.Status.FriendlyName) {
                    document.getElementById('tasmota_device_name').value = 
                        data.raw_data.Status.FriendlyName[0];
                }
                
            } else {
                // Fehler
                resultDiv.className = 'alert alert-danger';
                resultDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Verbindung fehlgeschlagen!</strong><br>
                    ${data.error || 'Unbekannter Fehler'}
                `;
                liveDataDiv.classList.add('d-none');
            }
            
        } catch (error) {
            resultDiv.className = 'alert alert-danger';
            resultDiv.innerHTML = `
                <i class="bi bi-wifi-off"></i>
                <strong>Netzwerk-Fehler!</strong><br>
                Gerät unter ${ip} nicht erreichbar.
            `;
            liveDataDiv.classList.add('d-none');
        }
    });
    
    // Live-Daten anzeigen
    function displayLiveData(data) {
        const display = document.getElementById('tasmotaEnergyDisplay');
        display.innerHTML = `
            <div class="energy-data-grid">
                <div class="energy-data-item">
                    <span class="energy-value">${data.power || 0}</span>
                    <div class="energy-label">Watt</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.voltage || 0}</span>
                    <div class="energy-label">Volt</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.current || 0}</span>
                    <div class="energy-label">Ampere</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.energy_today || 0}</span>
                    <div class="energy-label">kWh heute</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.energy_total || 0}</span>
                    <div class="energy-label">kWh gesamt</div>
                </div>
                <div class="energy-data-item">
                    <span class="energy-value">${data.power_factor || 0}</span>
                    <div class="energy-label">Leistungsfaktor</div>
                </div>
            </div>
        `;
        
        // Wattage automatisch setzen falls leer
        const wattageInput = document.getElementById('tasmota_wattage');
        if (!wattageInput.value && data.power > 0) {
            wattageInput.value = Math.ceil(data.power);
        }
    }
    
    // Modal reset beim Schließen
    document.getElementById('tasmotaDeviceModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('tasmotaTestResult').classList.add('d-none');
        document.getElementById('tasmotaLiveData').classList.add('d-none');
        document.getElementById('tasmotaDeviceForm').reset();
    });
});
</script>