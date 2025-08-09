<?php
// geraete.php
// Geräte-Verwaltung (CRUD)

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
$whereConditions = ['d.user_id = ?'];  // d. für devices-Tabelle hinzugefügt
$params = [$userId];

if (!$showInactive) {
    $whereConditions[] = 'd.is_active = 1';  // d. hinzugefügt
}

if (!empty($categoryFilter)) {
    $whereConditions[] = 'd.category = ?';  // d. hinzugefügt
    $params[] = $categoryFilter;
}

if (!empty($searchTerm)) {
    $whereConditions[] = 'd.name LIKE ?';  // d. hinzugefügt
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
) ?: []; // Fallback zu leerem Array

// Kategorien für Filter laden (sicher)
$categories = Database::fetchAll(
    "SELECT DISTINCT category FROM devices WHERE user_id = ? AND is_active = 1 ORDER BY category",
    [$userId]
) ?: []; // Fallback zu leerem Array

// Statistiken (sicher berechnen)
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

<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="bi bi-cpu text-primary"></i>
                Geräte-Verwaltung
            </h1>
            <p class="text-muted">Verwalten Sie Ihre elektrischen Geräte und deren Stromverbrauch.</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                <i class="bi bi-plus-circle"></i>
                Neues Gerät
            </button>
        </div>
    </div>
    
    <!-- Statistik Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['total_devices'] ?></h4>
                            <p class="mb-0">Aktive Geräte</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-cpu"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($stats['total_wattage']) ?> W</h4>
                            <p class="mb-0">Gesamtleistung</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['categories'] ?></h4>
                            <p class="mb-0">Kategorien</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-tags"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['inactive_devices'] ?></h4>
                            <p class="mb-0">Inaktive Geräte</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-archive"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter & Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Suche</label>
                    <input type="text" class="form-control" name="search" 
                           value="<?= escape($searchTerm) ?>" 
                           placeholder="Gerätename suchen...">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Kategorie</label>
                    <select class="form-select" name="category">
                        <option value="">Alle Kategorien</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= escape($cat['category']) ?>" 
                                    <?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>>
                                <?= escape($cat['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="inactive">
                        <option value="0" <?= !$showInactive ? 'selected' : '' ?>>Nur aktive</option>
                        <option value="1" <?= $showInactive ? 'selected' : '' ?>>Alle anzeigen</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filtern
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Geräte-Liste -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-list"></i>
                Meine Geräte (<?= count($devices) ?>)
            </h5>
            <div>
                <a href="?<?= http_build_query(array_merge($_GET, ['inactive' => $showInactive ? '0' : '1'])) ?>" 
                   class="btn btn-sm btn-outline-secondary">
                    <?= $showInactive ? 'Nur aktive anzeigen' : 'Alle anzeigen' ?>
                </a>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($devices)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-cpu display-4 text-muted"></i>
                    <h4 class="mt-3">Keine Geräte gefunden</h4>
                    <p class="text-muted">
                        <?php if (!empty($searchTerm) || !empty($categoryFilter)): ?>
                            Versuchen Sie andere Suchkriterien oder 
                            <a href="geraete.php">alle Geräte anzeigen</a>.
                        <?php else: ?>
                            Fügen Sie Ihr erstes Gerät hinzu, um zu beginnen.
                        <?php endif; ?>
                    </p>
                    <?php if (empty($searchTerm) && empty($categoryFilter)): ?>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                            <i class="bi bi-plus-circle"></i>
                            Erstes Gerät hinzufügen
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
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
                                                <i class="bi bi-archive"></i> Inaktiv
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="energy-indicator me-2"></span>
                                            <div>
                                                <div class="fw-bold"><?= escape($device['name']) ?></div>
                                                <small class="text-muted">
                                                    Erstellt: <?= formatDateShort($device['created_at']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-primary"><?= escape($device['category']) ?></span>
                                    </td>
                                    
                                    <td>
                                        <strong><?= number_format($device['wattage']) ?> W</strong>
                                    </td>
                                    
                                    <td>
                                        <?php if ($device['total_consumption'] > 0): ?>
                                            <span class="badge bg-warning text-dark">
                                                <?= formatKwh($device['total_consumption']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($device['total_cost'] > 0): ?>
                                            <strong class="text-success">
                                                <?= formatCurrency($device['total_cost']) ?>
                                            </strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-info"><?= $device['usage_count'] ?></span>
                                    </td>
                                    
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <!-- Bearbeiten -->
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editDevice(<?= htmlspecialchars(json_encode($device)) ?>)"
                                                    data-bs-toggle="modal" data-bs-target="#editDeviceModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <?php if ($device['is_active']): ?>
                                                <!-- Deaktivieren -->
                                                <button class="btn btn-outline-warning" 
                                                        onclick="confirmAction('delete', <?= $device['id'] ?>, '<?= escape($device['name']) ?>')">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                            <?php else: ?>
                                                <!-- Aktivieren -->
                                                <button class="btn btn-outline-success" 
                                                        onclick="confirmAction('activate', <?= $device['id'] ?>, '<?= escape($device['name']) ?>')">
                                                    <i class="bi bi-arrow-clockwise"></i>
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

<!-- Modals -->

<!-- Gerät hinzufügen Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle text-success"></i>
                        Neues Gerät hinzufügen
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Gerätename *</label>
                        <input type="text" class="form-control" name="name" required
                               placeholder="z.B. Kühlschrank Samsung XY">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kategorie *</label>
                        <select class="form-select" name="category" required>
                            <option value="">Kategorie wählen...</option>
                            <option value="Küchengerät">Küchengerät</option>
                            <option value="Haushaltsgerät">Haushaltsgerät</option>
                            <option value="Unterhaltung">Unterhaltung</option>
                            <option value="Beleuchtung">Beleuchtung</option>
                            <option value="Heizung/Klima">Heizung/Klima</option>
                            <option value="Büro/Computer">Büro/Computer</option>
                            <option value="Sonstiges">Sonstiges</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Leistung (Watt) *</label>
                        <input type="number" class="form-control" name="wattage" min="1" max="50000" required
                               placeholder="z.B. 150">
                        <div class="form-text">
                            Typische Werte: Kühlschrank 150W, Waschmaschine 2000W, LED-TV 120W
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i>
                        Gerät hinzufügen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Gerät bearbeiten Modal -->
<div class="modal fade" id="editDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="device_id" id="edit_device_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil text-primary"></i>
                        Gerät bearbeiten
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Gerätename *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kategorie *</label>
                        <select class="form-select" name="category" id="edit_category" required>
                            <option value="">Kategorie wählen...</option>
                            <option value="Küchengerät">Küchengerät</option>
                            <option value="Haushaltsgerät">Haushaltsgerät</option>
                            <option value="Unterhaltung">Unterhaltung</option>
                            <option value="Beleuchtung">Beleuchtung</option>
                            <option value="Heizung/Klima">Heizung/Klima</option>
                            <option value="Büro/Computer">Büro/Computer</option>
                            <option value="Sonstiges">Sonstiges</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Leistung (Watt) *</label>
                        <input type="number" class="form-control" name="wattage" id="edit_wattage" 
                               min="1" max="50000" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i>
                        Änderungen speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bestätigungs-Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmTitle">Bestätigung</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmBody">
                Sind Sie sicher?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Abbrechen
                </button>
                <button type="button" class="btn btn-danger" id="confirmButton">
                    Bestätigen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Form für Aktionen -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" id="actionType">
    <input type="hidden" name="device_id" id="actionDeviceId">
</form>

<script>
// Gerät bearbeiten
function editDevice(device) {
    document.getElementById('edit_device_id').value = device.id;
    document.getElementById('edit_name').value = device.name;
    document.getElementById('edit_category').value = device.category;
    document.getElementById('edit_wattage').value = device.wattage;
}

// Aktion bestätigen
function confirmAction(action, deviceId, deviceName) {
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const title = document.getElementById('confirmTitle');
    const body = document.getElementById('confirmBody');
    const button = document.getElementById('confirmButton');
    
    if (action === 'delete') {
        title.textContent = 'Gerät deaktivieren';
        body.innerHTML = `Möchten Sie das Gerät <strong>"${deviceName}"</strong> wirklich deaktivieren?<br><small class="text-muted">Das Gerät wird nicht gelöscht, sondern nur deaktiviert.</small>`;
        button.textContent = 'Deaktivieren';
        button.className = 'btn btn-warning';
    } else if (action === 'activate') {
        title.textContent = 'Gerät aktivieren';
        body.innerHTML = `Möchten Sie das Gerät <strong>"${deviceName}"</strong> wieder aktivieren?`;
        button.textContent = 'Aktivieren';
        button.className = 'btn btn-success';
    }
    
    button.onclick = function() {
        document.getElementById('actionType').value = action;
        document.getElementById('actionDeviceId').value = deviceId;
        document.getElementById('actionForm').submit();
    };
    
    modal.show();
}

// Form-Validierung
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus auf erstes Eingabefeld in Modals
    document.getElementById('addDeviceModal').addEventListener('shown.bs.modal', function() {
        this.querySelector('input[name="name"]').focus();
    });
    
    document.getElementById('editDeviceModal').addEventListener('shown.bs.modal', function() {
        this.querySelector('input[name="name"]').focus();
    });
});
</script>

<?php include 'includes/footer.php'; ?>