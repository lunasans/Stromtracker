<?php
// tarife.php
// Tarif-Verwaltung (Abschlag, Grundgebühr, Strompreise)

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

// Abschlag-Analyse der letzten 12 Monate
$paymentAnalysis = Database::fetchAll(
    "SELECT * FROM payment_analysis 
     WHERE user_id = ? 
     ORDER BY reading_date DESC 
     LIMIT 12",
    [$userId]
) ?: [];

// Jahresabrechnung-Prognose
$yearlyForecast = Database::fetchOne(
    "SELECT * FROM yearly_billing_forecast 
     WHERE user_id = ? AND year = YEAR(CURDATE())
     LIMIT 1",
    [$userId]
);

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="bi bi-receipt text-warning"></i>
                Tarif-Verwaltung
            </h1>
            <p class="text-muted">Verwalten Sie Ihren Stromtarif, Abschlag und Grundgebühr.</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTariffModal">
                <i class="bi bi-plus-circle"></i>
                Neuer Tarif
            </button>
        </div>
    </div>
    
    <!-- Aktueller Tarif -->
    <?php if ($currentTariff): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-check-circle"></i>
                            Aktueller Tarif
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <h6>Anbieter & Tarif</h6>
                                <p class="mb-1"><strong><?= escape($currentTariff['provider_name']) ?></strong></p>
                                <p class="text-muted"><?= escape($currentTariff['tariff_name']) ?></p>
                                <?php if ($currentTariff['customer_number']): ?>
                                    <small class="text-muted">Kundennr: <?= escape($currentTariff['customer_number']) ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-2">
                                <h6>Arbeitspreis</h6>
                                <div class="h4 text-primary"><?= formatCurrency($currentTariff['rate_per_kwh']) ?></div>
                                <small class="text-muted">pro kWh</small>
                            </div>
                            
                            <div class="col-md-2">
                                <h6>Monatlicher Abschlag</h6>
                                <div class="h4 text-warning"><?= formatCurrency($currentTariff['monthly_payment']) ?></div>
                                <small class="text-muted">pro Monat</small>
                            </div>
                            
                            <div class="col-md-2">
                                <h6>Grundgebühr</h6>
                                <div class="h4 text-info"><?= formatCurrency($currentTariff['basic_fee']) ?></div>
                                <small class="text-muted">pro Monat</small>
                            </div>
                            
                            <div class="col-md-2">
                                <h6>Gültig seit</h6>
                                <div class="h6"><?= formatDateShort($currentTariff['valid_from']) ?></div>
                            </div>
                            
                            <div class="col-md-1 text-end">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="editTariff(<?= htmlspecialchars(json_encode($currentTariff)) ?>)"
                                        data-bs-toggle="modal" data-bs-target="#editTariffModal">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning mb-4">
            <h6 class="alert-heading">
                <i class="bi bi-exclamation-triangle"></i>
                Kein aktiver Tarif
            </h6>
            <p class="mb-0">
                Sie haben noch keinen Stromtarif erfasst. 
                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addTariffModal">
                    Jetzt erstellen
                </button>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Abschlag vs. Realität -->
    <?php if (!empty($paymentAnalysis) && $yearlyForecast): ?>
        <div class="row mb-4">
            
            <!-- Jahresübersicht -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up-arrow text-success"></i>
                            Abschlag vs. tatsächliche Kosten <?= date('Y') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-primary bg-opacity-10 rounded">
                                    <div class="h4 text-primary"><?= formatCurrency($yearlyForecast['total_payments']) ?></div>
                                    <small>Abschläge gezahlt</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
                                    <div class="h4 text-warning"><?= formatCurrency($yearlyForecast['actual_total_cost']) ?></div>
                                    <small>Tatsächliche Kosten</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 <?= $yearlyForecast['total_difference'] >= 0 ? 'bg-danger bg-opacity-10' : 'bg-success bg-opacity-10' ?> rounded">
                                    <div class="h4 <?= $yearlyForecast['total_difference'] >= 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= $yearlyForecast['total_difference'] >= 0 ? '+' : '' ?><?= formatCurrency($yearlyForecast['total_difference']) ?>
                                    </div>
                                    <small><?= $yearlyForecast['total_difference'] >= 0 ? 'Nachzahlung' : 'Guthaben' ?></small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-info bg-opacity-10 rounded">
                                    <div class="h4 text-info"><?= formatCurrency($yearlyForecast['projected_yearly_cost']) ?></div>
                                    <small>Jahresprognose</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Prognose-Info -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="bi bi-calculator"></i>
                                Jahresabrechnung-Prognose
                            </h6>
                            <p class="mb-2">
                                <strong>Erwartete Gesamt-Nachzahlung/-Guthaben:</strong> 
                                <span class="<?= ($yearlyForecast['projected_yearly_cost'] - ($yearlyForecast['avg_monthly_payment'] * 12)) >= 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= ($yearlyForecast['projected_yearly_cost'] - ($yearlyForecast['avg_monthly_payment'] * 12)) >= 0 ? '+' : '' ?>
                                    <?= formatCurrency($yearlyForecast['projected_yearly_cost'] - ($yearlyForecast['avg_monthly_payment'] * 12)) ?>
                                </span>
                            </p>
                            <small class="text-muted">
                                Basierend auf <?= $yearlyForecast['readings_count'] ?> Ablesungen. 
                                Durchschnittlicher Verbrauch: <?= formatKwh($yearlyForecast['avg_monthly_consumption']) ?>/Monat
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Monatlicher Trend -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-month text-info"></i>
                            Letzte Monate
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach (array_slice($paymentAnalysis, 0, 6) as $month): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded <?= $month['payment_difference'] >= 0 ? 'bg-danger bg-opacity-10' : 'bg-success bg-opacity-10' ?>">
                                <div>
                                    <strong><?= $month['month_name'] ?> <?= $month['year'] ?></strong><br>
                                    <small class="text-muted"><?= formatKwh($month['consumption']) ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="<?= $month['payment_difference'] >= 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= $month['payment_difference'] >= 0 ? '+' : '' ?><?= formatCurrency($month['payment_difference']) ?>
                                    </div>
                                    <small class="text-muted"><?= formatCurrency($month['total_bill']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Tarif-Historie -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-clock-history"></i>
                Tarif-Historie
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($tariffs)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-receipt display-4 text-muted"></i>
                    <h4 class="mt-3">Keine Tarife erfasst</h4>
                    <p class="text-muted">Erstellen Sie Ihren ersten Stromtarif.</p>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTariffModal">
                        <i class="bi bi-plus-circle"></i>
                        Ersten Tarif erstellen
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
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
                                <tr>
                                    <td>
                                        <?php if ($tariff['is_active']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Aktiv
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-archive"></i> Beendet
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <strong><?= formatDateShort($tariff['valid_from']) ?></strong>
                                        <?php if ($tariff['valid_to']): ?>
                                            <br>bis <?= formatDateShort($tariff['valid_to']) ?>
                                        <?php else: ?>
                                            <br><small class="text-muted">unbegrenzt</small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <div><strong><?= escape($tariff['provider_name']) ?></strong></div>
                                        <small class="text-muted"><?= escape($tariff['tariff_name']) ?></small>
                                    </td>
                                    
                                    <td>
                                        <span class="badge bg-primary">
                                            <?= formatCurrency($tariff['rate_per_kwh']) ?>/kWh
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <strong><?= formatCurrency($tariff['monthly_payment']) ?></strong>
                                        <small class="text-muted">/Monat</small>
                                    </td>
                                    
                                    <td>
                                        <?= formatCurrency($tariff['basic_fee']) ?>
                                        <small class="text-muted">/Monat</small>
                                    </td>
                                    
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editTariff(<?= htmlspecialchars(json_encode($tariff)) ?>)"
                                                    data-bs-toggle="modal" data-bs-target="#editTariffModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            
                                            <?php if ($tariff['is_active']): ?>
                                                <button class="btn btn-outline-warning" 
                                                        onclick="endTariff(<?= $tariff['id'] ?>, '<?= escape($tariff['tariff_name']) ?>')">
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

<!-- Modals -->

<!-- Tarif hinzufügen Modal -->
<div class="modal fade" id="addTariffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle text-success"></i>
                        Neuen Tarif erstellen
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gültig ab *</label>
                            <input type="date" class="form-control" name="valid_from" 
                                   value="<?= date('Y-m-01') ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Arbeitspreis (€/kWh) *</label>
                            <input type="number" class="form-control" name="rate_per_kwh" 
                                   step="0.0001" min="0" placeholder="0.32" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Monatlicher Abschlag (€) *</label>
                            <input type="number" class="form-control" name="monthly_payment" 
                                   step="0.01" min="0" placeholder="85.00" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Grundgebühr/Monat (€) *</label>
                            <input type="number" class="form-control" name="basic_fee" 
                                   step="0.01" min="0" placeholder="12.50" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Anbieter</label>
                            <input type="text" class="form-control" name="provider_name" 
                                   placeholder="z.B. Stadtwerke" value="Stadtwerke">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tarifname</label>
                            <input type="text" class="form-control" name="tariff_name" 
                                   placeholder="z.B. Haushaltsstrom Basic" value="Haushaltsstrom">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kundennummer (optional)</label>
                        <input type="text" class="form-control" name="customer_number" 
                               placeholder="Ihre Kundennummer beim Stromanbieter">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notizen (optional)</label>
                        <textarea class="form-control" name="notes" rows="2"
                                  placeholder="z.B. Sondertarif, Preisgarantie bis..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i>
                        Tarif erstellen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tarif bearbeiten Modal -->
<div class="modal fade" id="editTariffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="tariff_id" id="edit_tariff_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil text-primary"></i>
                        Tarif bearbeiten
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
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
                        <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
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

<!-- Tarif beenden Modal -->
<div class="modal fade" id="endTariffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="tariff_id" id="end_tariff_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Tarif beenden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <p>Möchten Sie den Tarif <strong id="end_tariff_name"></strong> wirklich beenden?</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Gültig bis</label>
                        <input type="date" class="form-control" name="valid_to" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-stop-circle"></i>
                        Tarif beenden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Tarif bearbeiten
function editTariff(tariff) {
    document.getElementById('edit_tariff_id').value = tariff.id;
    document.getElementById('edit_rate_per_kwh').value = tariff.rate_per_kwh;
    document.getElementById('edit_monthly_payment').value = tariff.monthly_payment;
    document.getElementById('edit_basic_fee').value = tariff.basic_fee;
    document.getElementById('edit_provider_name').value = tariff.provider_name || '';
    document.getElementById('edit_tariff_name').value = tariff.tariff_name || '';
    document.getElementById('edit_customer_number').value = tariff.customer_number || '';
    document.getElementById('edit_notes').value = tariff.notes || '';
}

// Tarif beenden
function endTariff(tariffId, tariffName) {
    document.getElementById('end_tariff_id').value = tariffId;
    document.getElementById('end_tariff_name').textContent = tariffName;
    
    const modal = new bootstrap.Modal(document.getElementById('endTariffModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>