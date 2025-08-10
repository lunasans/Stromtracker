<?php
// geraete.php
// EINFACHE & SCHÖNE Geräte-Verwaltung (CRUD)

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
            COUNT(ec.id) as usage_count
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

$stats = [
    'total_devices' => count($activeDevices),
    'inactive_devices' => count($inactiveDevices),
    'total_wattage' => array_sum(array_map(fn($d) => $d['is_active'] ? ($d['wattage'] ?? 0) : 0, $devices)),
    'categories' => count($categories)
];

include 'includes/header.php';
include 'includes/navbar.php';
?>

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
            <div class="stats-card energy">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-lightning-charge"></i>
                    <div class="small">
                        Max. Verbrauch
                    </div>
                </div>
                <h3><?= number_format($stats['total_wattage']) ?> W</h3>
                <p>Gesamtleistung</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card success">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-check-circle"></i>
                    <div class="small">
                        Online
                    </div>
                </div>
                <h3><?= count($categories) ?></h3>
                <p>Kategorien</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card <?= $stats['inactive_devices'] > 0 ? 'warning' : 'success' ?>">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-<?= $stats['inactive_devices'] > 0 ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                    <div class="small">
                        <?= $showInactive ? 'Angezeigt' : 'Versteckt' ?>
                    </div>
                </div>
                <h3><?= $stats['inactive_devices'] ?></h3>
                <p>Inaktive Geräte</p>
            </div>
        </div>
    </div>
    
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
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editDevice(<?= htmlspecialchars(json_encode($device)) ?>)" 
                                                    title="Bearbeiten">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <?php if ($device['is_active']): ?>
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

// Auto-focus auf erstes Feld in Modals
document.addEventListener('DOMContentLoaded', function() {
    // Add Modal
    const addModal = document.getElementById('addDeviceModal');
    addModal.addEventListener('shown.bs.modal', function() {
        document.querySelector('#addDeviceModal input[name="name"]').focus();
    });
    
    // Edit Modal
    const editModal = document.getElementById('editDeviceModal');
    editModal.addEventListener('shown.bs.modal', function() {
        document.getElementById('edit_name').focus();
    });
    
    // Tooltips initialisieren
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>