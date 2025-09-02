<?php
// zaehlerstand.php
// EINFACHE & SCHÖNE Zählerstand-Verwaltung (KORRIGIERT)

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Zählerstand - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// CSRF-Token generieren
$csrfToken = Auth::generateCSRFToken();

// Aktueller Tarif für Berechnungen (mit robuster Fehlerbehandlung)
$currentTariff = null;
$currentRate = 0.32; // Fallback
$monthlyPayment = 0;
$basicFee = 0;

try {
    // Prüfen ob tariff_periods Tabelle existiert
    $tableExists = Database::fetchOne("SHOW TABLES LIKE 'tariff_periods'");
    
    if ($tableExists) {
        $currentTariff = Database::fetchOne(
            "SELECT * FROM tariff_periods 
             WHERE user_id = ? AND is_active = 1 
             ORDER BY valid_from DESC LIMIT 1",
            [$userId]
        );
        
        if ($currentTariff) {
            $currentRate = (float)($currentTariff['rate_per_kwh'] ?? 0.32);
            $monthlyPayment = (float)($currentTariff['monthly_payment'] ?? 0);
            $basicFee = (float)($currentTariff['basic_fee'] ?? 0);
        }
    }
} catch (Exception $e) {
    // Fallback bei Datenbankfehlern
    error_log("Tariff query error: " . $e->getMessage());
}

// Zählerstand-Verarbeitung mit robuster Fehlerbehandlung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF-Token prüfen
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Flash::error('Sicherheitsfehler. Bitte versuchen Sie es erneut.');
    } else {
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                try {
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
                            $energyCost = null;
                            $totalBill = null;
                            $paymentDifference = null;
                            
                            if ($lastReading && isset($lastReading['meter_value'])) {
                                $consumption = $meterValue - (float)$lastReading['meter_value'];
                                
                                if ($consumption < 0) {
                                    Flash::error('Der neue Zählerstand ist kleiner als der vorherige. Bitte prüfen Sie die Eingabe.');
                                    break;
                                }
                                
                                if ($currentRate > 0) {
                                    $energyCost = $consumption * $currentRate;
                                    $totalBill = $energyCost + $basicFee;
                                    $paymentDifference = $totalBill - $monthlyPayment;
                                }
                            }
                            
                            // Daten für Insert vorbereiten (nur definierte Spalten)
                            $insertData = [
                                'user_id' => $userId,
                                'reading_date' => $readingDate,
                                'meter_value' => $meterValue,
                                'notes' => $notes
                            ];
                            
                            // Erweiterte Felder nur hinzufügen wenn sie berechnet wurden
                            if ($consumption !== null) {
                                $insertData['consumption'] = $consumption;
                            }
                            if ($energyCost !== null) {
                                $insertData['cost'] = $energyCost;
                                $insertData['rate_per_kwh'] = $currentRate;
                            }
                            if ($monthlyPayment > 0) {
                                $insertData['monthly_payment'] = $monthlyPayment;
                            }
                            if ($basicFee > 0) {
                                $insertData['basic_fee'] = $basicFee;
                            }
                            if ($totalBill !== null) {
                                $insertData['total_bill'] = $totalBill;
                            }
                            if ($paymentDifference !== null) {
                                $insertData['payment_difference'] = $paymentDifference;
                            }
                            
                            $readingId = Database::insert('meter_readings', $insertData);
                            
                            if ($readingId) {
                                $message = "Zählerstand erfolgreich gespeichert.";
                                if ($consumption !== null) {
                                    $message .= " Verbrauch: " . number_format($consumption, 1) . " kWh";
                                    if ($energyCost !== null) {
                                        $message .= ", Stromkosten: " . number_format($energyCost, 2) . " €";
                                    }
                                }
                                Flash::success($message);
                            } else {
                                Flash::error('Fehler beim Speichern des Zählerstands.');
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Reading insert error: " . $e->getMessage());
                    Flash::error('Systemfehler beim Speichern. Bitte versuchen Sie es erneut.');
                }
                break;
                
            case 'edit':
                try {
                    // Zählerstand bearbeiten (vereinfacht)
                    $readingId = (int)($_POST['reading_id'] ?? 0);
                    $meterValue = (float)($_POST['meter_value'] ?? 0);
                    $notes = trim($_POST['notes'] ?? '');
                    
                    if ($readingId <= 0 || $meterValue <= 0) {
                        Flash::error('Bitte geben Sie einen gültigen Zählerstand ein.');
                    } else {
                        $success = Database::update('meter_readings', [
                            'meter_value' => $meterValue,
                            'notes' => $notes
                        ], 'id = ? AND user_id = ?', [$readingId, $userId]);
                        
                        if ($success) {
                            Flash::success('Zählerstand wurde erfolgreich aktualisiert.');
                        } else {
                            Flash::error('Fehler beim Bearbeiten des Zählerstands.');
                        }
                    }
                } catch (Exception $e) {
                    error_log("Reading edit error: " . $e->getMessage());
                    Flash::error('Systemfehler beim Bearbeiten.');
                }
                break;
                
            case 'delete':
                try {
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
                } catch (Exception $e) {
                    error_log("Reading delete error: " . $e->getMessage());
                    Flash::error('Systemfehler beim Löschen.');
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: zaehlerstand.php');
    exit;
}

// Zählerstände laden (mit robuster Fehlerbehandlung)
$readings = [];
try {
    $readings = Database::fetchAll(
        "SELECT * FROM meter_readings 
         WHERE user_id = ? 
         ORDER BY reading_date DESC",
        [$userId]
    ) ?: [];
} catch (Exception $e) {
    error_log("Readings fetch error: " . $e->getMessage());
    Flash::error('Fehler beim Laden der Zählerstände.');
}

// Statistiken berechnen (robust mit Fallbacks)
$stats = [
    'total_readings' => count($readings),
    'current_month_consumption' => 0,
    'current_month_cost' => 0,
    'year_consumption' => 0,
    'year_cost' => 0
];

$currentYear = date('Y');
$currentMonth = date('Y-m');

try {
    foreach ($readings as $reading) {
        if (isset($reading['consumption']) && $reading['consumption'] > 0) {
            // Aktueller Monat
            if (date('Y-m', strtotime($reading['reading_date'])) === $currentMonth) {
                $stats['current_month_consumption'] = (float)$reading['consumption'];
                $stats['current_month_cost'] = (float)($reading['cost'] ?? 0);
            }
            
            // Aktuelles Jahr
            if (date('Y', strtotime($reading['reading_date'])) === $currentYear) {
                $stats['year_consumption'] += (float)$reading['consumption'];
                $stats['year_cost'] += (float)($reading['cost'] ?? 0);
            }
        }
    }
} catch (Exception $e) {
    error_log("Stats calculation error: " . $e->getMessage());
}

// Nächste Ablesung vorschlagen
$suggestedDate = date('Y-m-01'); // Erster des aktuellen Monats
if (!empty($readings)) {
    $lastDate = $readings[0]['reading_date'] ?? '';
    if ($lastDate) {
        $suggestedDate = date('Y-m-01', strtotime($lastDate . '+1 month'));
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

    <!-- Tarif Info (falls vorhanden) -->
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
                                <?php if ($currentTariff): ?>
                                    <th>Gesamtrechnung</th>
                                    <th>Differenz</th>
                                <?php endif; ?>
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
                                        <?php if (isset($reading['consumption']) && $reading['consumption']): ?>
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
                                        <?php if (isset($reading['cost']) && $reading['cost']): ?>
                                            <div class="fw-bold text-warning">
                                                <?= number_format($reading['cost'], 2) ?> €
                                            </div>
                                            <?php if (isset($reading['rate_per_kwh'])): ?>
                                                <small class="text-muted">
                                                    <?= number_format($reading['rate_per_kwh'], 4) ?> €/kWh
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <?php if ($currentTariff): ?>
                                        <td>
                                            <?php if (isset($reading['total_bill']) && $reading['total_bill']): ?>
                                                <div class="fw-bold text-primary">
                                                    <?= number_format($reading['total_bill'], 2) ?> €
                                                </div>
                                                <small class="text-muted">
                                                    inkl. <?= number_format($reading['basic_fee'] ?? 0, 2) ?> € Grundgeb.
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <?php if (isset($reading['payment_difference']) && $reading['payment_difference'] !== null): ?>
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
                                    <?php endif; ?>
                                    
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
                        <input type="date" class="form-control" name="reading_date" 
                               value="<?= $suggestedDate ?>" required>
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i>
                            Vorgeschlagen: <?= date('F Y', strtotime($suggestedDate)) ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Zählerstand (kWh)</label>
                        <input type="number" class="form-control" name="meter_value" id="meter_value_input"
                               step="0.01" min="0" required placeholder="z.B. 12345.67">
                        <div class="form-text">Aktueller Wert vom Stromzähler</div>
                    </div>
                    
                    <!-- ========== OCR-ERWEITERUNG START ========== -->
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="bi bi-camera"></i> Oder Bild vom Zähler verwenden
                        </label>
                        <div class="d-grid gap-2">
                            <input type="file" id="meterImageInput" accept="image/*" class="form-control" style="display: none;">
                            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('meterImageInput').click()">
                                <i class="bi bi-camera"></i> Zählerbild auswählen
                            </button>
                        </div>
                    </div>

                    <!-- Bild-Vorschau und OCR-Status -->
                    <div id="imagePreviewSection" class="mb-3" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Bild-Vorschau</label>
                                <div class="border rounded p-2">
                                    <img id="imagePreview" src="" alt="Zählerstand-Bild" class="img-fluid" style="max-height: 200px;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">OCR-Status</label>
                                <div id="ocrStatus" class="alert alert-info">
                                    <i class="bi bi-hourglass-split"></i> Bereit für Bildanalyse...
                                </div>
                                <div id="ocrResult" class="mt-2" style="display: none;">
                                    <small class="text-muted">Erkannter Text:</small>
                                    <div id="ocrText" class="border rounded p-2 bg-light small"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ========== OCR-ERWEITERUNG ENDE ========== -->
                    
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

<!-- OCR Library -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>

<!-- JavaScript -->
<script>
// ========== OCR-FUNKTIONALITÄT START ==========
// OCR-Funktionalität für Zählerstand-Erkennung
document.getElementById('meterImageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    // Bild-Vorschau anzeigen
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('imagePreview').src = e.target.result;
        document.getElementById('imagePreviewSection').style.display = 'block';
        
        // OCR starten
        processImageWithOCR(file);
    };
    reader.readAsDataURL(file);
});

function processImageWithOCR(file) {
    const statusDiv = document.getElementById('ocrStatus');
    const resultDiv = document.getElementById('ocrResult');
    const textDiv = document.getElementById('ocrText');
    
    statusDiv.innerHTML = '<i class="bi bi-hourglass-split"></i> Zählerstand wird erkannt...';
    statusDiv.className = 'alert alert-info';
    resultDiv.style.display = 'none';
    
    // Tesseract OCR mit optimierten Einstellungen für Zahlen
    Tesseract.recognize(
        file,
        'deu+eng', // Deutsch und Englisch
        {
            logger: m => {
                if (m.status === 'recognizing text') {
                    const progress = Math.round(m.progress * 100);
                    statusDiv.innerHTML = `<i class="bi bi-gear-fill"></i> Analysiere Bild... ${progress}%`;
                }
            },
            tessedit_char_whitelist: '0123456789.,: kWhstandWert', // Nur relevante Zeichen
            tessedit_pageseg_mode: Tesseract.PSM.SINGLE_BLOCK
        }
    ).then(({ data: { text, confidence } }) => {
        textDiv.textContent = text;
        resultDiv.style.display = 'block';
        
        // Zählerstand aus Text extrahieren
        const meterReading = extractMeterReading(text);
        
        if (meterReading) {
            // Erkannten Wert ins Eingabefeld eintragen
            const meterValueInput = document.getElementById('meter_value_input');
            if (meterValueInput) {
                meterValueInput.value = meterReading;
                meterValueInput.focus();
            }
            
            statusDiv.innerHTML = `<i class="bi bi-check-circle-fill text-success"></i> Zählerstand erkannt: <strong>${meterReading} kWh</strong>`;
            statusDiv.className = 'alert alert-success';
        } else {
            // Zeige Hilfe-Text mit Tipps
            statusDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Kein Zählerstand erkannt. <br><small>Tipp: Foto frontal aufnehmen, gute Beleuchtung, nur die Zählerziffern im Bild.</small>';
            statusDiv.className = 'alert alert-warning';
        }
        
        console.log('OCR Confidence:', Math.round(confidence) + '%');
        
    }).catch(err => {
        console.error('OCR Error:', err);
        statusDiv.innerHTML = '<i class="bi bi-x-circle-fill"></i> Fehler bei der Bilderkennung. Bitte manuell eingeben.';
        statusDiv.className = 'alert alert-danger';
    });
}

function extractMeterReading(text) {
    console.log('OCR Text:', text);
    
    // Text vorbereinigen - Rauschen entfernen aber Zahlen und wichtige Wörter behalten
    let cleanText = text
        .replace(/[^\d\s.,kWhKWHSTANDstandwertzählerZÄHLER]/gi, ' ') // Nur relevante Zeichen
        .replace(/\s+/g, ' ') // Mehrfache Leerzeichen entfernen
        .toLowerCase();
    
    console.log('Bereinigter Text:', cleanText);
    
    // Erweiterte Muster für deutsche Zählerstände
    const patterns = [
        // Präzise Formate mit Kontext
        /kwh[\s:]*([\d\s]{1,3}[.,][\d\s]{3}[.,][\d\s]{1,3})/gi, // kWh: 1 2.345,67
        /stand[\s:]*([\d\s]{1,3}[.,][\d\s]{3}[.,][\d\s]{1,3})/gi, // Stand: 1 2.345,67
        
        // Standard-Formate mit Tausender-Trennung
        /([\d\s]{1,3}[.,][\d\s]{3}[.,][\d\s]{1,3})/g, // 12.345,67 oder 12 345 67
        /([\d\s]{4,6}[.,][\d\s]{1,3})/g, // 12345,67
        
        // Kompakte Formate
        /(\d{5,6})/g, // 123456 (ohne Kommastellen)
        
        // Nach kWh-Markierung suchen
        /kwh[\s:]*([\d\s.,]{4,10})/gi,
        /stand[\s:]*([\d\s.,]{4,10})/gi
    ];
    
    const candidates = [];
    
    // Alle möglichen Treffer sammeln
    for (let pattern of patterns) {
        const matches = [...cleanText.matchAll(pattern)];
        for (let match of matches) {
            let rawValue = match[1] || match[0];
            
            // Zahl extrahieren und normalisieren
            let reading = parseNumberFromOCR(rawValue);
            
            // Plausibilitätsprüfung für Stromzähler
            if (reading >= 1000 && reading <= 999999) {
                candidates.push({
                    value: reading,
                    confidence: getReadingConfidence(match[0], text, pattern.source),
                    raw: rawValue
                });
            }
        }
    }
    
    // Zusätzliche Suche nach isolierten 5-6 stelligen Zahlen
    const numberMatches = cleanText.match(/\b\d{5,6}\b/g);
    if (numberMatches) {
        numberMatches.forEach(numStr => {
            let reading = parseInt(numStr);
            if (reading >= 10000 && reading <= 999999) { // Höhere Mindestgrenze für isolierte Zahlen
                candidates.push({
                    value: reading,
                    confidence: 40, // Niedrigere Confidence für isolierte Zahlen
                    raw: numStr
                });
            }
        });
    }
    
    if (candidates.length === 0) {
        console.log('Keine Kandidaten gefunden');
        return null;
    }
    
    // Duplikate entfernen (gleiche Werte)
    const uniqueCandidates = candidates.filter((candidate, index, self) => 
        index === self.findIndex(c => c.value === candidate.value)
    );
    
    // Nach Confidence sortieren
    uniqueCandidates.sort((a, b) => b.confidence - a.confidence);
    
    console.log('Zählerstand-Kandidaten:', uniqueCandidates);
    
    return uniqueCandidates[0].value;
}

// Hilfsfunktion zum Parsen von OCR-Zahlen
function parseNumberFromOCR(rawText) {
    // Leerzeichen entfernen
    let cleaned = rawText.replace(/\s/g, '');
    
    // Deutsche Zahlenformate handhaben
    // 12.345,67 -> 12345.67
    if (cleaned.includes('.') && cleaned.includes(',')) {
        // Punkt als Tausendertrennzeichen, Komma als Dezimaltrennzeichen
        cleaned = cleaned.replace(/\./g, '').replace(',', '.');
    }
    // 12345,67 -> 12345.67  
    else if (cleaned.includes(',') && !cleaned.includes('.')) {
        cleaned = cleaned.replace(',', '.');
    }
    
    return parseFloat(cleaned) || 0;
}

function getReadingConfidence(matchText, fullText, patternType) {
    let confidence = 30; // Basis-Confidence
    
    // Bonus für Kontext-Wörter
    if (/kwh|stand|wert|zähler/i.test(fullText)) {
        confidence += 25;
    }
    
    // Bonus für typische Zählerstand-Formate
    if (/\d{4,5}[.,]\d{1,2}/.test(matchText)) {
        confidence += 20;
    }
    
    // Bonus für 5-6 stellige Zahlen (typisch für Stromzähler)
    if (/\d{5,6}/.test(matchText)) {
        confidence += 15;
    }
    
    // Pattern-spezifische Bonusse
    if (patternType && patternType.includes('kwh')) {
        confidence += 20; // Starker Bonus für kWh-Kontext
    }
    if (patternType && patternType.includes('stand')) {
        confidence += 20; // Starker Bonus für Stand-Kontext
    }
    
    // Malus für sehr kurze oder sehr lange Zahlen
    const numericPart = matchText.replace(/[^\d]/g, '');
    if (numericPart.length < 4 || numericPart.length > 8) {
        confidence -= 15;
    }
    
    return Math.max(confidence, 10); // Mindest-Confidence
}

// Bild zurücksetzen wenn Modal geschlossen wird
document.getElementById('addReadingModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('meterImageInput').value = '';
    document.getElementById('imagePreviewSection').style.display = 'none';
});
// ========== OCR-FUNKTIONALITÄT ENDE ==========

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
        document.getElementById('meter_value_input').focus();
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