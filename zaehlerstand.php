<?php
// zaehlerstand.php
// Monatliche Zählerstände verwalten

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
                                $message .= " Verbrauch: " . formatKwh($consumption) . ", Stromkosten: " . formatCurrency($energyCost);
                                if ($totalBill !== null) {
                                    $message .= ", Gesamtrechnung: " . formatCurrency($totalBill);
                                    if ($paymentDifference !== null) {
                                        $diff = $paymentDifference >= 0 ? "Nachzahlung" : "Guthaben";
                                        $message .= " (" . $diff . ": " . formatCurrency(abs($paymentDifference)) . ")";
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
                            'rate_per_kwh' => $currentRate,
                            'monthly_payment' => $monthlyPayment,
                            'basic_fee' => $basicFee,
                            'total_bill' => $totalBill,
                            'payment_difference' => $paymentDifference,
                            'notes' => $notes
                        ], 'id = ? AND user_id = ?', [$readingId, $userId]);
                        
                        if ($success) {
                            Flash::success('Zählerstand wurde erfolgreich aktualisiert.');
                            
                            // Nachfolgende Einträge neu berechnen
                            $futureReadings = Database::fetchAll(
                                "SELECT * FROM meter_readings 
                                 WHERE user_id = ? AND reading_date > ? 
                                 ORDER BY reading_date ASC",
                                [$userId, $currentReading['reading_date']]
                            );
                            
                            $prevValue = $meterValue;
                            foreach ($futureReadings as $future) {
                                $futureConsumption = $future['meter_value'] - $prevValue;
                                $futureEnergyCost = $futureConsumption * ($future['rate_per_kwh'] ?? $currentRate);
                                $futureTotalBill = $futureEnergyCost + ($future['basic_fee'] ?? $basicFee);
                                $futurePaymentDiff = $futureTotalBill - ($future['monthly_payment'] ?? $monthlyPayment);
                                
                                Database::update('meter_readings', [
                                    'consumption' => $futureConsumption,
                                    'cost' => $futureEnergyCost,
                                    'total_bill' => $futureTotalBill,
                                    'payment_difference' => $futurePaymentDiff
                                ], 'id = ?', [$future['id']]);
                                
                                $prevValue = $future['meter_value'];
                            }
                            
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

// Zählerstände laden (sicher)
$readings = Database::fetchAll(
    "SELECT * FROM meter_readings 
     WHERE user_id = ? 
     ORDER BY reading_date DESC",
    [$userId]
) ?: []; // Fallback zu leerem Array

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
    $nextDate = date('Y-m-01', strtotime($lastDate . ' +1 month'));
    if (strtotime($nextDate) <= time()) {
        $suggestedDate = $nextDate;
    }
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="bi bi-speedometer2 text-warning"></i>
                Zählerstand erfassen
            </h1>
            <p class="text-muted">Erfassen Sie monatlich Ihren Stromzählerstand für eine genaue Verbrauchsübersicht.</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addReadingModal">
                <i class="bi bi-plus-circle"></i>
                Neuer Zählerstand
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
                            <h4><?= $stats['total_readings'] ?></h4>
                            <p class="mb-0">Ablesungen</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-list-ol"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= formatKwh($stats['current_month_consumption']) ?></h4>
                            <p class="mb-0">Aktueller Monat</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-lightning-charge"></i>
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
                            <h4><?= formatCurrency($stats['current_month_cost']) ?></h4>
                            <p class="mb-0">Kosten Monat</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-cash-coin"></i>
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
                            <h4><?= formatCurrency($stats['year_cost']) ?></h4>
                            <p class="mb-0">Kosten <?= $currentYear ?></p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-calendar-year"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
                            <div class="alert alert-info mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="alert-heading mb-2">
                    <i class="bi bi-info-circle"></i>
                    Wie funktioniert's?
                </h6>
                <p class="mb-0">
                    Lesen Sie einmal monatlich Ihren Stromzähler ab und tragen Sie den Wert hier ein. 
                    Das System berechnet automatisch Ihren Verbrauch, die Stromkosten, Grundgebühr und vergleicht mit Ihrem Abschlag.
                </p>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($currentTariff): ?>
                    <div class="badge bg-primary text-dark fs-6 mb-1">
                        Arbeitspreis: <?= formatCurrency($currentRate) ?>/kWh
                    </div><br>
                    <div class="badge bg-warning text-dark fs-6 mb-1">
                        Abschlag: <?= formatCurrency($monthlyPayment) ?>/Monat
                    </div><br>
                    <div class="badge bg-info text-dark fs-6">
                        Grundgebühr: <?= formatCurrency($basicFee) ?>/Monat
                    </div>
                <?php else: ?>
                    <div class="badge bg-danger text-white fs-6">
                        <a href="tarife.php" class="text-white text-decoration-none">
                            Tarif konfigurieren
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Zählerstände -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-table"></i>
                Meine Zählerstände
            </h5>
            <span class="badge bg-primary"><?= count($readings) ?> Einträge</span>
        </div>
        
        <div class="card-body p-0">
            <?php if (empty($readings)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-speedometer2 display-4 text-muted"></i>
                    <h4 class="mt-3">Noch keine Zählerstände erfasst</h4>
                    <p class="text-muted">Erfassen Sie Ihren ersten Zählerstand, um zu beginnen.</p>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addReadingModal">
                        <i class="bi bi-plus-circle"></i>
                        Ersten Zählerstand erfassen
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
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
                                        <strong><?= formatDateShort($reading['reading_date']) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?= date('F Y', strtotime($reading['reading_date'])) ?>
                                        </small>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-primary fs-6">
                                            <?= number_format($reading['meter_value'], 2, ',', '.') ?> kWh
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['consumption']): ?>
                                            <span class="badge bg-success">
                                                <?= formatKwh($reading['consumption']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['cost']): ?>
                                            <?= formatCurrency($reading['cost']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['total_bill']): ?>
                                            <strong class="text-primary">
                                                <?= formatCurrency($reading['total_bill']) ?>
                                            </strong>
                                            <?php if ($reading['basic_fee']): ?>
                                                <br><small class="text-muted">
                                                    (inkl. <?= formatCurrency($reading['basic_fee']) ?> Grundgebühr)
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['monthly_payment']): ?>
                                            <?= formatCurrency($reading['monthly_payment']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['payment_difference'] !== null): ?>
                                            <span class="badge <?= $reading['payment_difference'] >= 0 ? 'bg-danger' : 'bg-success' ?>">
                                                <?= $reading['payment_difference'] >= 0 ? '+' : '' ?><?= formatCurrency($reading['payment_difference']) ?>
                                            </span>
                                            <br><small class="text-muted">
                                                <?= $reading['payment_difference'] >= 0 ? 'Nachzahlung' : 'Guthaben' ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($reading['notes']): ?>
                                            <small><?= escape($reading['notes']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editReading(<?= htmlspecialchars(json_encode($reading)) ?>)"
                                                    data-bs-toggle="modal" data-bs-target="#editReadingModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="confirmDelete(<?= $reading['id'] ?>, '<?= formatDateShort($reading['reading_date']) ?>')">
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

<!-- Modals -->

<!-- Zählerstand hinzufügen Modal -->
<div class="modal fade" id="addReadingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle text-success"></i>
                        Neuen Zählerstand erfassen
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ablesedatum *</label>
                        <input type="date" class="form-control" name="reading_date" 
                               value="<?= $suggestedDate ?>" required>
                        <div class="form-text">
                            Empfohlen: Immer am ersten des Monats ablesen
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Zählerstand (kWh) *</label>
                        <input type="number" class="form-control" name="meter_value" 
                               step="0.1" min="0" required
                               placeholder="z.B. 1234.5">
                        <div class="form-text">
                            Aktueller Wert vom Stromzähler ablesen
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notizen (optional)</label>
                        <textarea class="form-control" name="notes" rows="2"
                                  placeholder="z.B. Urlaubsmonat, neuer Tarif, etc."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-calculator"></i>
                        <strong>Automatische Berechnung:</strong><br>
                        Das System berechnet automatisch:
                        <ul class="mb-0 mt-2">
                            <li>Verbrauch seit letzter Ablesung</li>
                            <li>Stromkosten (<?= formatCurrency($currentRate) ?>/kWh)</li>
                            <li>Grundgebühr (<?= formatCurrency($basicFee) ?>/Monat)</li>
                            <li>Differenz zu Ihrem Abschlag (<?= formatCurrency($monthlyPayment) ?>/Monat)</li>
                        </ul>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i>
                        Zählerstand speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Zählerstand bearbeiten Modal -->
<div class="modal fade" id="editReadingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="reading_id" id="edit_reading_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil text-primary"></i>
                        Zählerstand bearbeiten
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Datum</label>
                        <input type="text" class="form-control" id="edit_date_display" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Zählerstand (kWh) *</label>
                        <input type="number" class="form-control" name="meter_value" 
                               id="edit_meter_value" step="0.1" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notizen</label>
                        <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Hinweis:</strong> Bei Änderungen werden auch nachfolgende Verbrauchsberechnungen automatisch angepasst.
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

<!-- Löschen bestätigen Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Löschen bestätigen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Möchten Sie den Zählerstand vom <strong id="delete_date"></strong> wirklich löschen?</p>
                <p class="text-muted small">Diese Aktion kann nicht rückgängig gemacht werden.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Abbrechen
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="bi bi-trash"></i>
                    Löschen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden Form für Löschung -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="reading_id" id="delete_reading_id">
</form>

<script>
// Zählerstand bearbeiten
function editReading(reading) {
    document.getElementById('edit_reading_id').value = reading.id;
    document.getElementById('edit_date_display').value = new Date(reading.reading_date).toLocaleDateString('de-DE');
    document.getElementById('edit_meter_value').value = reading.meter_value;
    document.getElementById('edit_notes').value = reading.notes || '';
}

// Löschen bestätigen
function confirmDelete(readingId, date) {
    document.getElementById('delete_reading_id').value = readingId;
    document.getElementById('delete_date').textContent = date;
    
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
    
    document.getElementById('confirmDeleteBtn').onclick = function() {
        document.getElementById('deleteForm').submit();
    };
}

// Auto-focus und Validierung
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus bei Modal-Öffnung
    document.getElementById('addReadingModal').addEventListener('shown.bs.modal', function() {
        this.querySelector('input[name="meter_value"]').focus();
    });
    
    // Datum-Validierung
    const dateInput = document.querySelector('input[name="reading_date"]');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            
            if (selectedDate > today) {
                alert('Das Datum darf nicht in der Zukunft liegen.');
                this.value = '';
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>