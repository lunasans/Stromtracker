<?php
// zaehlerstand.php
// EINFACHE & SCHÖNE Zählerstand-Verwaltung

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Zählerstand - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// Aktueller Tarif für Berechnungen
$currentTariff = Database::fetchOne(
    "SELECT * FROM tariff_periods 
     WHERE user_id = ? AND is_active = 1 
     ORDER BY valid_from DESC LIMIT 1",
    [$userId]
);

$currentRate = $currentTariff['rate_per_kwh'] ?? 0.32;
$monthlyPayment = $currentTariff['monthly_payment'] ?? 0;
$basicFee = $currentTariff['basic_fee'] ?? 0;

// Zählerstand-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF-Token prüfen
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Flash::error('Sicherheitsfehler. Bitte versuchen Sie es erneut.');
    } else {
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                // Neuen Zählerstand hinzufügen
                $readingDate = $_POST['reading_date'] ?? '';
                $meterValue = (float)($_POST['meter_value'] ?? 0);
                $notes = trim($_POST['notes'] ?? '');
                
                if (empty($readingDate) || $meterValue <= 0) {
                    Flash::error('Bitte geben Sie ein gültiges Datum und einen Zählerstand ein.');
                } else {
                    // Prüfen ob bereits Eintrag für diesen Monat existiert
                    $existingReading = Database::fetchOne(
                        "SELECT id FROM meter_readings WHERE user_id = ? AND reading_date = ?",
                        [$userId, $readingDate]
                    );
                    
                    if ($existingReading) {
                        Flash::error('Für diesen Monat existiert bereits ein Zählerstand.');
                    } else {
                        // Letzten Zählerstand holen für Verbrauchsberechnung
                        $lastReading = Database::fetchOne(
                            "SELECT meter_value FROM meter_readings 
                             WHERE user_id = ? AND reading_date < ? 
                             ORDER BY reading_date DESC LIMIT 1",
                            [$userId, $readingDate]
                        );
                        
                        $consumption = null;
                        $cost = null;
                        
                        if ($lastReading) {
                            $consumption = $meterValue - $lastReading['meter_value'];
                            $energyCost = $consumption * $currentRate;
                            $totalBill = $energyCost + $basicFee;
                            $paymentDifference = $totalBill - $monthlyPayment;
                            
                            // Negative Werte abfangen
                            if ($consumption < 0) {
                                Flash::error('Der neue Zählerstand ist kleiner als der vorherige. Bitte prüfen Sie die Eingabe.');
                                break;
                            }
                        } else {
                            $consumption = null;
                            $energyCost = null;
                            $totalBill = null;
                            $paymentDifference = null;
                        }
                        
                        $readingId = Database::insert('meter_readings', [
                            'user_id' => $userId,
                            'reading_date' => $readingDate,
                            'meter_value' => $meterValue,
                            'consumption' => $consumption,
                            'cost' => $energyCost,
                            'rate_per_kwh' => $currentRate,
                            'monthly_payment' => $monthlyPayment,
                            'basic_fee' => $basicFee,
                            'total_bill' => $totalBill,
                            'payment_difference' => $paymentDifference,
                            'notes' => $notes
                        ]);
                        
                        if ($readingId) {
                            $message = "Zählerstand erfolgreich gespeichert.";
                            if ($consumption !== null) {
                                $message .= " Verbrauch: " . number_format($consumption, 1) . " kWh, Stromkosten: " . number_format($energyCost, 2) . " €";
                                if ($totalBill !== null) {
                                    $message .= ", Gesamtrechnung: " . number_format($totalBill, 2) . " €";
                                    if ($paymentDifference !== null) {
                                        $diff = $paymentDifference >= 0 ? "Nachzahlung" : "Guthaben";
                                        $message .= " (" . $diff . ": " . number_format(abs($paymentDifference), 2) . " €)";
                                    }
                                }
                            }
                            Flash::success($message);
                        } else {
                            Flash::error('Fehler beim Speichern des Zählerstands.');
                        }
                    }
                }
                break;
                
            case 'edit':
                // Zählerstand bearbeiten
                $readingId = (int)($_POST['reading_id'] ?? 0);
                $meterValue = (float)($_POST['meter_value'] ?? 0);
                $notes = trim($_POST['notes'] ?? '');
                
                if ($readingId <= 0 || $meterValue <= 0) {
                    Flash::error('Bitte geben Sie einen gültigen Zählerstand ein.');
                } else {
                    // Aktueller Eintrag holen
                    $currentReading = Database::fetchOne(
                        "SELECT * FROM meter_readings WHERE id = ? AND user_id = ?",
                        [$readingId, $userId]
                    );
                    
                    if (!$currentReading) {
                        Flash::error('Zählerstand nicht gefunden.');
                    } else {
                        // Verbrauch neu berechnen
                        $lastReading = Database::fetchOne(
                            "SELECT meter_value FROM meter_readings 
                             WHERE user_id = ? AND reading_date < ? 
                             ORDER BY reading_date DESC LIMIT 1",
                            [$userId, $currentReading['reading_date']]
                        );
                        
                        $consumption = null;
                        $cost = null;
                        
                        if ($lastReading) {
                            $consumption = $meterValue - $lastReading['meter_value'];
                            $energyCost = $consumption * $currentRate;
                            $totalBill = $energyCost + $basicFee;
                            $paymentDifference = $totalBill - $monthlyPayment;
                            
                            if ($consumption < 0) {
                                Flash::error('Der Zählerstand ist kleiner als der vorherige. Bitte prüfen Sie die Eingabe.');
                                break;
                            }
                        } else {
                            $consumption = null;
                            $energyCost = null;
                            $totalBill = null;
                            $paymentDifference = null;
                        }
                        
                        $success = Database::update('meter_readings', [
                            'meter_value' => $meterValue,
                            'consumption' => $consumption,
                            'cost' => $energyCost,
                            'total_bill' => $totalBill,
                            'payment_difference' => $paymentDifference,
                            'notes' => $notes
                        ], 'id = ? AND user_id = ?', [$readingId, $userId]);
                        
                        if ($success) {
                            Flash::success('Zählerstand wurde erfolgreich aktualisiert.');
                        } else {
                            Flash::error('Fehler beim Bearbeiten des Zählerstands.');
                        }
                    }
                }
                break;
                
            case 'delete':
                // Zählerstand löschen
                $readingId = (int)($_POST['reading_id'] ?? 0);
                
                if ($readingId <= 0) {
                    Flash::error('Ungültige ID.');
                } else {
                    $success = Database::delete('meter_readings', 'id = ? AND user_id = ?', [$readingId, $userId]);
                    
                    if ($success) {
                        Flash::success('Zählerstand wurde erfolgreich gelöscht.');
                    } else {
                        Flash::error('Fehler beim Löschen des Zählerstands.');
                    }
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: zaehlerstand.php');
    exit;
}

// Zählerstände laden
$readings = Database::fetchAll(
    "SELECT * FROM meter_readings 
     WHERE user_id = ? 
     ORDER BY reading_date DESC",
    [$userId]
) ?: [];

// Statistiken berechnen
$stats = [
    'total_readings' => count($readings),
    'current_month_consumption' => 0,
    'current_month_cost' => 0,
    'year_consumption' => 0,
    'year_cost' => 0
];

$currentYear = date('Y');
$currentMonth = date('Y-m');

foreach ($readings as $reading) {
    if ($reading['consumption']) {
        // Aktueller Monat
        if (date('Y-m', strtotime($reading['reading_date'])) === $currentMonth) {
            $stats['current_month_consumption'] = $reading['consumption'];
            $stats['current_month_cost'] = $reading['cost'];
        }
        
        // Aktuelles Jahr
        if (date('Y', strtotime($reading['reading_date'])) === $currentYear) {
            $stats['year_consumption'] += $reading['consumption'];
            $stats['year_cost'] += $reading['cost'];
        }
    }
}

// Nächste Ablesung vorschlagen
$suggestedDate = date('Y-m-01'); // Erster des aktuellen Monats
if (!empty($readings)) {
    $lastDate = $readings[0]['reading_date'];
    $nextDate = date('Y-m-01', strtotime($lastDate . '+1 month'));
    if (strtotime($nextDate) <= time()) {
        $suggestedDate = $nextDate;
    }
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<!-- Zählerstand Content -->
<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-energy mb-2">
                            <span class="energy-indicator"></span>
                            <i class="bi bi-speedometer2"></i>
                            Zählerstand erfassen
                        </h1>
                        <p class="text-muted mb-0">Erfassen Sie monatlich Ihren Stromzählerstand für eine genaue Verbrauchsübersicht.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-energy" data-bs-toggle="modal" data-bs-target="#addReadingModal">
                            <i class="bi bi-plus-circle me-2"></i>
                            Neuer Zählerstand
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
                    <i class="stats-icon bi bi-list-ol"></i>
                    <div class="small">
                        Total
                    </div>
                </div>
                <h3><?= $stats['total_readings'] ?></h3>
                <p>Ablesungen</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card success">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-lightning-charge"></i>
                    <div class="small">
                        Aktuell
                    </div>
                </div>
                <h3><?= number_format($stats['current_month_consumption'], 1) ?></h3>
                <p>kWh aktueller Monat</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card warning">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-currency-euro"></i>
                    <div class="small">
                        Monat
                    </div>
                </div>
                <h3><?= number_format($stats['current_month_cost'], 2) ?> €</h3>
                <p>Kosten aktueller Monat</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card energy">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-graph-up"></i>
                    <div class="small">
                        <?= date('Y') ?>
                    </div>
                </div>
                <h3><?= number_format($stats['year_consumption'], 0) ?></h3>
                <p>kWh dieses Jahr</p>
            </div>
        </div>
    </div>

    <!-- Tarif Info -->
    <?php if ($currentTariff): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle text-primary me-2"></i>
                                <div>
                                    <small class="text-muted">Aktueller Tarif</small>
                                    <div class="fw-bold"><?= number_format($currentRate, 4) ?> €/kWh</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-check text-success me-2"></i>
                                <div>
                                    <small class="text-muted">Monatlicher Abschlag</small>
                                    <div class="fw-bold"><?= number_format($monthlyPayment, 2) ?> €</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-receipt text-warning me-2"></i>
                                <div>
                                    <small class="text-muted">Grundgebühr</small>
                                    <div class="fw-bold"><?= number_format($basicFee, 2) ?> €</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calculator text-energy me-2"></i>
                                <div>
                                    <small class="text-muted">Nächste Ablesung</small>
                                    <div class="fw-bold"><?= date('m/Y', strtotime($suggestedDate)) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Zählerstände Liste -->
    <div class="card">
        <div class="card-header">
            <div class="flex-between">
                <h5 class="mb-0">
                    <i class="bi bi-table text-energy"></i>
                    Meine Zählerstände
                </h5>
                <span class="badge bg-primary"><?= count($readings) ?> Einträge</span>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($readings)): ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-speedometer2 display-2 text-muted"></i>
                    </div>
                    <h4 class="text-muted">Noch keine Zählerstände erfasst</h4>
                    <p class="text-muted mb-4">Erfassen Sie Ihren ersten Zählerstand, um zu beginnen.</p>
                    <button class="btn btn-energy" data-bs-toggle="modal" data-bs-target="#addReadingModal">
                        <i class="bi bi-plus-circle me-2"></i>
                        Ersten Zählerstand erfassen
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: var(--gray-50);">
                            <tr>
                                <th>Datum</th>
                                <th>Zählerstand</th>
                                <th>Verbrauch</th>
                                <th>Stromkosten</th>
                                <th>Gesamtrechnung</th>
                                <th>Abschlag</th>
                                <th>Differenz</th>
                                <th>Notizen</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($readings as $reading): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= date('d.m.Y', strtotime($reading['reading_date'])) ?></div>
                                        <small class="text-muted">
                                            <?= date('F Y', strtotime($reading['reading_date'])) ?>
                                        </small>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-primary" style="font-size: 0.9rem;">
                                            <?= number_format($reading['meter_value'], 2, ',', '.') ?> kWh
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['consumption']): ?>
                                            <div class="fw-bold text-success">
                                                <?= number_format($reading['consumption'], 2) ?> kWh
                                            </div>
                                            <small class="text-muted">
                                                Ø <?= number_format($reading['consumption'] / 30, 2) ?> kWh/Tag
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['cost']): ?>
                                            <div class="fw-bold text-warning">
                                                <?= number_format($reading['cost'], 2) ?> €
                                            </div>
                                            <small class="text-muted">
                                                <?= number_format($reading['rate_per_kwh'], 4) ?> €/kWh
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['total_bill']): ?>
                                            <div class="fw-bold text-primary">
                                                <?= number_format($reading['total_bill'], 2) ?> €
                                            </div>
                                            <small class="text-muted">
                                                inkl. <?= number_format($reading['basic_fee'], 2) ?> € Grundgeb.
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['monthly_payment']): ?>
                                            <span class="badge bg-info">
                                                <?= number_format($reading['monthly_payment'], 2) ?> €
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['payment_difference'] !== null): ?>
                                            <?php if ($reading['payment_difference'] > 0): ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-arrow-up"></i>
                                                    <?= number_format($reading['payment_difference'], 2) ?> €
                                                </span>
                                                <br><small class="text-danger">Nachzahlung</small>
                                            <?php elseif ($reading['payment_difference'] < 0): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-arrow-down"></i>
                                                    <?= number_format(abs($reading['payment_difference']), 2) ?> €
                                                </span>
                                                <br><small class="text-success">Guthaben</small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-check"></i> Exakt
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if (!empty($reading['notes'])): ?>
                                            <button class="btn btn-outline-secondary btn-sm" 
                                                    data-bs-toggle="tooltip" 
                                                    title="<?= htmlspecialchars($reading['notes']) ?>">
                                                <i class="bi bi-note-text"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editReading(<?= htmlspecialchars(json_encode($reading)) ?>)" 
                                                    title="Bearbeiten">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="deleteReading(<?= $reading['id'] ?>, '<?= date('m/Y', strtotime($reading['reading_date'])) ?>')" 
                                                    title="Löschen">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

<!-- Add Reading Modal -->
<div class="modal fade" id="addReadingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle text-energy"></i>
                        Neuen Zählerstand erfassen
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Ablesung für Monat</label>
                        <input type="month" class="form-control" name="reading_date" 
                               value="<?= $suggestedDate ?>" required>
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i>
                            Vorgeschlagen: <?= date('F Y', strtotime($suggestedDate)) ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Zählerstand (kWh)</label>
                        <input type="number" class="form-control" name="meter_value" 
                               step="0.01" min="0" required placeholder="z.B. 12345.67">
                        <div class="form-text">Aktueller Wert vom Stromzähler</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notizen <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" name="notes" rows="3" 
                                  placeholder="z.B. Urlaubszeit, Sonderverbrauch..."></textarea>
                    </div>
                    
                    <!-- Tarif Info -->
                    <?php if ($currentTariff): ?>
                    <div class="alert alert-info">
                        <div class="row">
                            <div class="col-6">
                                <strong>Tarif:</strong> <?= number_format($currentRate, 4) ?> €/kWh
                            </div>
                            <div class="col-6">
                                <strong>Abschlag:</strong> <?= number_format($monthlyPayment, 2) ?> €
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
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

<!-- Edit Reading Modal -->
<div class="modal fade" id="editReadingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil text-energy"></i>
                        Zählerstand bearbeiten
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="reading_id" id="edit_reading_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Monat</label>
                        <input type="text" class="form-control" id="edit_month" readonly 
                               style="background: var(--gray-100);">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Zählerstand (kWh)</label>
                        <input type="number" class="form-control" name="meter_value" 
                               id="edit_meter_value" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notizen</label>
                        <textarea class="form-control" name="notes" id="edit_notes" rows="3"></textarea>
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

<!-- Hidden Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="reading_id" id="delete_reading_id">
</form>

<?php include 'includes/footer.php'; ?>

<!-- JavaScript -->
<script>
// Reading bearbeiten
function editReading(reading) {
    document.getElementById('edit_reading_id').value = reading.id;
    document.getElementById('edit_month').value = new Date(reading.reading_date).toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
    document.getElementById('edit_meter_value').value = reading.meter_value;
    document.getElementById('edit_notes').value = reading.notes || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editReadingModal'));
    modal.show();
}

// Reading löschen
function deleteReading(readingId, monthYear) {
    if (confirm(`Möchten Sie den Zählerstand für ${monthYear} wirklich löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden.`)) {
        document.getElementById('delete_reading_id').value = readingId;
        document.getElementById('deleteForm').submit();
    }
}

// Auto-focus und Validierung
document.addEventListener('DOMContentLoaded', function() {
    // Add Modal
    const addModal = document.getElementById('addReadingModal');
    addModal.addEventListener('shown.bs.modal', function() {
        document.querySelector('#addReadingModal input[name="meter_value"]').focus();
    });
    
    // Edit Modal
    const editModal = document.getElementById('editReadingModal');
    editModal.addEventListener('shown.bs.modal', function() {
        document.getElementById('edit_meter_value').focus();
    });
    
    // Tooltips initialisieren
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Form Validierung
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const meterValue = this.querySelector('input[name="meter_value"]');
            if (meterValue && (meterValue.value <= 0 || isNaN(meterValue.value))) {
                e.preventDefault();
                alert('Bitte geben Sie einen gültigen Zählerstand ein.');
                meterValue.focus();
                return false;
            }
        });
    });
});
</script>