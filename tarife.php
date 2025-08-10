<?php
// tarife.php
// EINFACHE & SCHÖNE Tarif-Verwaltung (Abschlag, Grundgebühr, Strompreise)

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Tarif-Verwaltung - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// Tarif-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF-Token prüfen
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Flash::error('Sicherheitsfehler. Bitte versuchen Sie es erneut.');
    } else {
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                // Neuen Tarif hinzufügen
                $validFrom = $_POST['valid_from'] ?? '';
                $ratePerKwh = (float)($_POST['rate_per_kwh'] ?? 0);
                $monthlyPayment = (float)($_POST['monthly_payment'] ?? 0);
                $basicFee = (float)($_POST['basic_fee'] ?? 0);
                $providerName = trim($_POST['provider_name'] ?? '');
                $tariffName = trim($_POST['tariff_name'] ?? '');
                $customerNumber = trim($_POST['customer_number'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                
                if (empty($validFrom) || $ratePerKwh <= 0 || $monthlyPayment < 0 || $basicFee < 0) {
                    Flash::error('Bitte füllen Sie alle Pflichtfelder korrekt aus.');
                } else {
                    // Vorherigen Tarif beenden
                    Database::update('tariff_periods', [
                        'valid_to' => date('Y-m-d', strtotime($validFrom . ' -1 day')),
                        'is_active' => 0
                    ], 'user_id = ? AND is_active = 1', [$userId]);
                    
                    $tariffId = Database::insert('tariff_periods', [
                        'user_id' => $userId,
                        'valid_from' => $validFrom,
                        'rate_per_kwh' => $ratePerKwh,
                        'monthly_payment' => $monthlyPayment,
                        'basic_fee' => $basicFee,
                        'provider_name' => $providerName,
                        'tariff_name' => $tariffName,
                        'customer_number' => $customerNumber,
                        'notes' => $notes,
                        'is_active' => 1
                    ]);
                    
                    if ($tariffId) {
                        Flash::success("Neuer Tarif '$tariffName' wurde erfolgreich erstellt.");
                    } else {
                        Flash::error('Fehler beim Erstellen des Tarifs.');
                    }
                }
                break;
                
            case 'edit':
                // Tarif bearbeiten
                $tariffId = (int)($_POST['tariff_id'] ?? 0);
                $ratePerKwh = (float)($_POST['rate_per_kwh'] ?? 0);
                $monthlyPayment = (float)($_POST['monthly_payment'] ?? 0);
                $basicFee = (float)($_POST['basic_fee'] ?? 0);
                $providerName = trim($_POST['provider_name'] ?? '');
                $tariffName = trim($_POST['tariff_name'] ?? '');
                $customerNumber = trim($_POST['customer_number'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                
                if ($tariffId <= 0 || $ratePerKwh <= 0 || $monthlyPayment < 0 || $basicFee < 0) {
                    Flash::error('Bitte füllen Sie alle Felder korrekt aus.');
                } else {
                    $success = Database::update('tariff_periods', [
                        'rate_per_kwh' => $ratePerKwh,
                        'monthly_payment' => $monthlyPayment,
                        'basic_fee' => $basicFee,
                        'provider_name' => $providerName,
                        'tariff_name' => $tariffName,
                        'customer_number' => $customerNumber,
                        'notes' => $notes
                    ], 'id = ? AND user_id = ?', [$tariffId, $userId]);
                    
                    if ($success) {
                        Flash::success("Tarif '$tariffName' wurde erfolgreich aktualisiert.");
                    } else {
                        Flash::error('Fehler beim Bearbeiten des Tarifs.');
                    }
                }
                break;
                
            case 'deactivate':
                // Tarif beenden
                $tariffId = (int)($_POST['tariff_id'] ?? 0);
                $validTo = $_POST['valid_to'] ?? date('Y-m-d');
                
                if ($tariffId <= 0) {
                    Flash::error('Ungültige Tarif-ID.');
                } else {
                    $success = Database::update('tariff_periods', [
                        'valid_to' => $validTo,
                        'is_active' => 0
                    ], 'id = ? AND user_id = ?', [$tariffId, $userId]);
                    
                    if ($success) {
                        Flash::success('Tarif wurde erfolgreich beendet.');
                    } else {
                        Flash::error('Fehler beim Beenden des Tarifs.');
                    }
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: tarife.php');
    exit;
}

// Tarife laden
$tariffs = Database::fetchAll(
    "SELECT * FROM tariff_periods 
     WHERE user_id = ? 
     ORDER BY valid_from DESC",
    [$userId]
) ?: [];

// Aktueller Tarif
$currentTariff = Database::fetchOne(
    "SELECT * FROM tariff_periods 
     WHERE user_id = ? AND is_active = 1 
     ORDER BY valid_from DESC LIMIT 1",
    [$userId]
);

// Statistiken berechnen
$stats = [
    'total_tariffs' => count($tariffs),
    'active_tariff' => $currentTariff ? 1 : 0,
    'current_rate' => $currentTariff['rate_per_kwh'] ?? 0,
    'current_payment' => $currentTariff['monthly_payment'] ?? 0
];

// Abschlag-Analyse der letzten 12 Monate (falls vorhanden)
$paymentAnalysis = Database::fetchAll(
    "SELECT mr.*, tp.monthly_payment, 
            (mr.total_bill - tp.monthly_payment) as payment_difference
     FROM meter_readings mr 
     JOIN tariff_periods tp ON DATE(mr.reading_date) BETWEEN tp.valid_from AND COALESCE(tp.valid_to, CURDATE())
     WHERE mr.user_id = ? AND mr.reading_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     ORDER BY mr.reading_date DESC 
     LIMIT 12",
    [$userId]
) ?: [];

include 'includes/header.php';
include 'includes/navbar.php';
?>

<!-- Tarif-Verwaltung Content -->
<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-energy mb-2">
                            <span class="energy-indicator"></span>
                            <i class="bi bi-receipt"></i>
                            Tarif-Verwaltung
                        </h1>
                        <p class="text-muted mb-0">Verwalten Sie Ihren Stromtarif, Abschlag und Grundgebühr.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-energy" data-bs-toggle="modal" data-bs-target="#addTariffModal">
                            <i class="bi bi-plus-circle me-2"></i>
                            Neuer Tarif
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
                    <i class="stats-icon bi bi-receipt"></i>
                    <div class="small">
                        Gesamt
                    </div>
                </div>
                <h3><?= $stats['total_tariffs'] ?></h3>
                <p>Tarife erfasst</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card <?= $stats['active_tariff'] > 0 ? 'success' : 'danger' ?>">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-<?= $stats['active_tariff'] > 0 ? 'check-circle' : 'x-circle' ?>"></i>
                    <div class="small">
                        Status
                    </div>
                </div>
                <h3><?= $stats['active_tariff'] > 0 ? 'Aktiv' : 'Fehlt' ?></h3>
                <p>Aktueller Tarif</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card energy">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-lightning-charge"></i>
                    <div class="small">
                        €/kWh
                    </div>
                </div>
                <h3><?= number_format($stats['current_rate'], 4) ?></h3>
                <p>Arbeitspreis</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card warning">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-calendar-check"></i>
                    <div class="small">
                        Monatlich
                    </div>
                </div>
                <h3><?= number_format($stats['current_payment'], 0) ?> €</h3>
                <p>Abschlag</p>
            </div>
        </div>
    </div>

    <!-- Aktueller Tarif -->
    <?php if ($currentTariff): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card" style="border: 2px solid var(--success); border-radius: var(--radius-xl);">
                    <div class="card-header" style="background: var(--success); color: white;">
                        <div class="flex-between">
                            <h5 class="mb-0">
                                <i class="bi bi-check-circle me-2"></i>
                                Aktueller Tarif
                            </h5>
                            <span class="badge bg-light text-dark">
                                Seit <?= date('d.m.Y', strtotime($currentTariff['valid_from'])) ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-building text-primary me-3" style="font-size: 1.5rem;"></i>
                                    <div>
                                        <small class="text-muted">Anbieter & Tarif</small>
                                        <div class="fw-bold"><?= htmlspecialchars($currentTariff['provider_name'] ?: 'Nicht angegeben') ?></div>
                                        <small class="text-energy"><?= htmlspecialchars($currentTariff['tariff_name'] ?: 'Standard') ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-lightning-charge text-energy me-3" style="font-size: 1.5rem;"></i>
                                    <div>
                                        <small class="text-muted">Arbeitspreis</small>
                                        <div class="fw-bold"><?= number_format($currentTariff['rate_per_kwh'], 4) ?> €/kWh</div>
                                        <small class="text-muted">pro Kilowattstunde</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar-check text-warning me-3" style="font-size: 1.5rem;"></i>
                                    <div>
                                        <small class="text-muted">Monatlicher Abschlag</small>
                                        <div class="fw-bold"><?= number_format($currentTariff['monthly_payment'], 2) ?> €</div>
                                        <small class="text-muted">Vorauszahlung</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-receipt text-info me-3" style="font-size: 1.5rem;"></i>
                                    <div>
                                        <small class="text-muted">Grundgebühr</small>
                                        <div class="fw-bold"><?= number_format($currentTariff['basic_fee'], 2) ?> €/Monat</div>
                                        <small class="text-muted">Fixkosten</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($currentTariff['customer_number']) || !empty($currentTariff['notes'])): ?>
                        <hr style="border-color: var(--gray-200);">
                        <div class="row">
                            <?php if (!empty($currentTariff['customer_number'])): ?>
                            <div class="col-md-6">
                                <small class="text-muted">Kundennummer:</small>
                                <div class="fw-bold"><?= htmlspecialchars($currentTariff['customer_number']) ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($currentTariff['notes'])): ?>
                            <div class="col-md-6">
                                <small class="text-muted">Notizen:</small>
                                <div><?= htmlspecialchars($currentTariff['notes']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-end mt-3">
                            <button class="btn btn-outline-primary btn-sm me-2" 
                                    onclick="editTariff(<?= htmlspecialchars(json_encode($currentTariff)) ?>)">
                                <i class="bi bi-pencil"></i> Bearbeiten
                            </button>
                            <button class="btn btn-outline-warning btn-sm" 
                                    onclick="endTariff(<?= $currentTariff['id'] ?>, '<?= htmlspecialchars($currentTariff['tariff_name'] ?: 'Aktueller Tarif') ?>')">
                                <i class="bi bi-stop-circle"></i> Tarif beenden
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Abschlag-Analyse (falls vorhanden) -->
    <?php if (!empty($paymentAnalysis)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up text-energy"></i>
                        Abschlag-Analyse (Letzte 12 Monate)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        $totalDifference = 0;
                        $months = array_slice($paymentAnalysis, 0, 6); // Nur 6 für bessere Darstellung
                        ?>
                        <?php foreach ($months as $month): ?>
                            <?php $totalDifference += $month['payment_difference'] ?? 0; ?>
                            <div class="col-md-2 mb-3">
                                <div class="text-center p-3" style="background: var(--gray-50); border-radius: var(--radius-lg);">
                                    <div class="fw-bold"><?= date('M Y', strtotime($month['reading_date'])) ?></div>
                                    <div class="mt-2 <?= ($month['payment_difference'] ?? 0) >= 0 ? 'text-danger' : 'text-success' ?>">
                                        <div class="fw-bold">
                                            <?= ($month['payment_difference'] ?? 0) >= 0 ? '+' : '' ?><?= number_format($month['payment_difference'] ?? 0, 2) ?> €
                                        </div>
                                        <small class="text-muted"><?= number_format($month['total_bill'] ?? 0, 2) ?> €</small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($paymentAnalysis) > 0): ?>
                    <div class="alert alert-info mt-3">
                        <strong>Trend:</strong> 
                        <?php if ($totalDifference > 10): ?>
                            <span class="text-danger">⬆️ Durchschnittlich <?= number_format($totalDifference / count($months), 2) ?> € Nachzahlung pro Monat. Abschlag sollte erhöht werden.</span>
                        <?php elseif ($totalDifference < -10): ?>
                            <span class="text-success">⬇️ Durchschnittlich <?= number_format(abs($totalDifference) / count($months), 2) ?> € Guthaben pro Monat. Abschlag könnte reduziert werden.</span>
                        <?php else: ?>
                            <span class="text-success">✅ Abschlag ist gut kalkuliert (Abweichung: <?= number_format($totalDifference / count($months), 2) ?> € pro Monat).</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tarif-Historie -->
    <div class="card">
        <div class="card-header">
            <div class="flex-between">
                <h5 class="mb-0">
                    <i class="bi bi-clock-history text-energy"></i>
                    Tarif-Historie
                </h5>
                <span class="badge bg-primary"><?= count($tariffs) ?> Tarife</span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($tariffs)): ?>
                <div class="text-center py-5">
                    <div class="mb-4">
                        <i class="bi bi-receipt display-2 text-muted"></i>
                    </div>
                    <h4 class="text-muted">Keine Tarife erfasst</h4>
                    <p class="text-muted mb-4">Erstellen Sie Ihren ersten Stromtarif.</p>
                    <button class="btn btn-energy" data-bs-toggle="modal" data-bs-target="#addTariffModal">
                        <i class="bi bi-plus-circle me-2"></i>
                        Ersten Tarif erstellen
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: var(--gray-50);">
                            <tr>
                                <th>Status</th>
                                <th>Zeitraum</th>
                                <th>Anbieter</th>
                                <th>Arbeitspreis</th>
                                <th>Abschlag</th>
                                <th>Grundgebühr</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tariffs as $tariff): ?>
                                <tr class="<?= !$tariff['is_active'] && $tariff['valid_to'] ? 'table-secondary' : '' ?>">
                                    <td>
                                        <?php if ($tariff['is_active']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Aktiv
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-clock"></i> Beendet
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= date('d.m.Y', strtotime($tariff['valid_from'])) ?></div>
                                        <?php if ($tariff['valid_to']): ?>
                                            <small class="text-muted">bis <?= date('d.m.Y', strtotime($tariff['valid_to'])) ?></small>
                                        <?php else: ?>
                                            <small class="text-success">unbegrenzt</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-building text-primary me-2"></i>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($tariff['provider_name'] ?: 'Nicht angegeben') ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($tariff['tariff_name'] ?: 'Standard') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-energy"><?= number_format($tariff['rate_per_kwh'], 4) ?> €</span>
                                        <br><small class="text-muted">pro kWh</small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-warning"><?= number_format($tariff['monthly_payment'], 2) ?> €</span>
                                        <br><small class="text-muted">pro Monat</small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-info"><?= number_format($tariff['basic_fee'], 2) ?> €</span>
                                        <br><small class="text-muted">pro Monat</small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editTariff(<?= htmlspecialchars(json_encode($tariff)) ?>)" 
                                                    title="Bearbeiten">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <?php if ($tariff['is_active']): ?>
                                                <button class="btn btn-outline-warning" 
                                                        onclick="endTariff(<?= $tariff['id'] ?>, '<?= htmlspecialchars($tariff['tariff_name'] ?: 'Tarif') ?>')" 
                                                        title="Beenden">
                                                    <i class="bi bi-stop-circle"></i>
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

<!-- Add Tariff Modal -->
<div class="modal fade" id="addTariffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle text-energy"></i>
                        Neuen Tarif hinzufügen
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gültig ab *</label>
                            <input type="date" class="form-control" name="valid_from" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Arbeitspreis (€/kWh) *</label>
                            <input type="number" class="form-control" name="rate_per_kwh" 
                                   step="0.0001" min="0" required placeholder="z.B. 0.3200">
                            <div class="form-text">Preis pro Kilowattstunde</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Monatlicher Abschlag (€) *</label>
                            <input type="number" class="form-control" name="monthly_payment" 
                                   step="0.01" min="0" required placeholder="z.B. 85.00">
                            <div class="form-text">Monatliche Vorauszahlung</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Grundgebühr/Monat (€) *</label>
                            <input type="number" class="form-control" name="basic_fee" 
                                   step="0.01" min="0" required placeholder="z.B. 12.50">
                            <div class="form-text">Monatliche Fixkosten</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Anbieter</label>
                            <input type="text" class="form-control" name="provider_name" 
                                   placeholder="z.B. Stadtwerke München">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tarifname</label>
                            <input type="text" class="form-control" name="tariff_name" 
                                   placeholder="z.B. M-Ökostrom Regional">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kundennummer</label>
                            <input type="text" class="form-control" name="customer_number" 
                                   placeholder="Ihre Kundennummer">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Notizen</label>
                            <textarea class="form-control" name="notes" rows="2" 
                                      placeholder="Zusätzliche Informationen..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-energy">
                        <i class="bi bi-check-circle me-1"></i>Tarif hinzufügen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Tariff Modal -->
<div class="modal fade" id="editTariffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil text-energy"></i>
                        Tarif bearbeiten
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tariff_id" id="edit_tariff_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Arbeitspreis (€/kWh) *</label>
                            <input type="number" class="form-control" name="rate_per_kwh" 
                                   id="edit_rate_per_kwh" step="0.0001" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Monatlicher Abschlag (€) *</label>
                            <input type="number" class="form-control" name="monthly_payment" 
                                   id="edit_monthly_payment" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Grundgebühr/Monat (€) *</label>
                            <input type="number" class="form-control" name="basic_fee" 
                                   id="edit_basic_fee" step="0.01" min="0" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Anbieter</label>
                            <input type="text" class="form-control" name="provider_name" 
                                   id="edit_provider_name">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tarifname</label>
                            <input type="text" class="form-control" name="tariff_name" 
                                   id="edit_tariff_name">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kundennummer</label>
                            <input type="text" class="form-control" name="customer_number" 
                                   id="edit_customer_number">
                        </div>
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

<!-- End Tariff Modal -->
<div class="modal fade" id="endTariffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="endForm">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-stop-circle text-warning"></i>
                        Tarif beenden
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="tariff_id" id="end_tariff_id">
                    
                    <p>Möchten Sie den Tarif "<strong id="end_tariff_name"></strong>" wirklich beenden?</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Gültig bis</label>
                        <input type="date" class="form-control" name="valid_to" 
                               value="<?= date('Y-m-d') ?>" required>
                        <div class="form-text">Der Tarif wird ab diesem Datum deaktiviert.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-stop-circle me-1"></i>Tarif beenden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- JavaScript -->
<script>
// Tariff bearbeiten
function editTariff(tariff) {
    document.getElementById('edit_tariff_id').value = tariff.id;
    document.getElementById('edit_rate_per_kwh').value = tariff.rate_per_kwh;
    document.getElementById('edit_monthly_payment').value = tariff.monthly_payment;
    document.getElementById('edit_basic_fee').value = tariff.basic_fee;
    document.getElementById('edit_provider_name').value = tariff.provider_name || '';
    document.getElementById('edit_tariff_name').value = tariff.tariff_name || '';
    document.getElementById('edit_customer_number').value = tariff.customer_number || '';
    document.getElementById('edit_notes').value = tariff.notes || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editTariffModal'));
    modal.show();
}

// Tariff beenden
function endTariff(tariffId, tariffName) {
    document.getElementById('end_tariff_id').value = tariffId;
    document.getElementById('end_tariff_name').textContent = tariffName;
    
    const modal = new bootstrap.Modal(document.getElementById('endTariffModal'));
    modal.show();
}

// Auto-focus und Validierung
document.addEventListener('DOMContentLoaded', function() {
    // Add Modal
    const addModal = document.getElementById('addTariffModal');
    addModal.addEventListener('shown.bs.modal', function() {
        document.querySelector('#addTariffModal input[name="rate_per_kwh"]').focus();
    });
    
    // Edit Modal
    const editModal = document.getElementById('editTariffModal');
    editModal.addEventListener('shown.bs.modal', function() {
        document.getElementById('edit_rate_per_kwh').focus();
    });
    
    // Form Validierung
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const rateField = this.querySelector('input[name="rate_per_kwh"]');
            if (rateField && (rateField.value <= 0 || isNaN(rateField.value))) {
                e.preventDefault();
                alert('Bitte geben Sie einen gültigen Arbeitspreis ein.');
                rateField.focus();
                return false;
            }
        });
    });
});
</script>