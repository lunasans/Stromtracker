<?php
// dashboard.php - Dashboard ohne Uhrzeit-Anzeige
require_once 'config/database.php';
require_once 'config/session.php';

Auth::requireLogin();
$pageTitle = 'Dashboard - Stromtracker';

$userId = Auth::getUserId();

// User-Daten abrufen
$user = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

// Aktuelles Jahr für Statistiken
$currentYear = date('Y');
$currentMonth = date('Y-m');

// Dashboard-Statistiken abrufen
$stats = [
    'total_readings' => 0,
    'current_month_consumption' => 0,
    'current_month_cost' => 0,
    'year_consumption' => 0,
    'total_devices' => 0
];

try {
    // Anzahl Zählerstände
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM meter_readings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats['total_readings'] = $stmt->fetchColumn();

    // Monatsverbrauch und -kosten
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(consumption), 0) as consumption,
            COALESCE(SUM(cost), 0) as cost
        FROM meter_readings 
        WHERE user_id = ? AND DATE_FORMAT(reading_date, '%Y-%m') = ?
    ");
    $stmt->execute([$userId, $currentMonth]);
    $monthData = $stmt->fetch();
    $stats['current_month_consumption'] = $monthData['consumption'];
    $stats['current_month_cost'] = $monthData['cost'];

    // Jahresverbrauch
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(consumption), 0) as consumption
        FROM meter_readings 
        WHERE user_id = ? AND YEAR(reading_date) = ?
    ");
    $stmt->execute([$userId, $currentYear]);
    $stats['year_consumption'] = $stmt->fetchColumn();

    // Anzahl aktive Geräte
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$userId]);
    $stats['total_devices'] = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Dashboard Fehler: " . $e->getMessage());
}

// Trend-Berechnung (vereinfacht)
$consumptionTrend = 0;
$costTrend = 0;

include 'includes/header.php';
include 'includes/navbar.php';
?>

<!-- Dashboard Content -->
<div class="container-fluid py-4">

    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-energy mb-3">
                            <span class="energy-indicator"></span>
                            Willkommen<?= $user['name'] ? ', ' . htmlspecialchars($user['name']) : (', ' . explode('@', $user['email'])[0]) ?>!
                        </h1>
                        <?php if ($stats['total_readings'] > 0): ?>
                            <p class="lead text-muted mb-0">
                                Hier ist Ihre aktuelle Stromverbrauch-Übersicht basierend auf <?= $stats['total_readings'] ?> Zählerständen.
                            </p>
                        <?php else: ?>
                            <p class="lead text-muted mb-0">
                                Erfassen Sie Ihren ersten Zählerstand, um mit der Verbrauchsanalyse zu beginnen.
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex justify-content-end align-items-center">
                            <div class="energy-indicator" style="width: 40px; height: 40px; position: relative;">
                                <span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 20px;">⚡</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards Grid -->
    <div class="row mb-4">
        
        <!-- Aktueller Monat - Verbrauch -->
        <div class="col-md-3 mb-3">
            <div class="stats-card success">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-lightning-charge"></i>
                    <div class="small">
                        <?php if ($consumptionTrend > 0): ?>
                            <i class="bi bi-arrow-up-short text-danger"></i>
                            <span class="text-danger">+<?= $consumptionTrend ?>%</span>
                        <?php elseif ($consumptionTrend < 0): ?>
                            <i class="bi bi-arrow-down-short text-success"></i>
                            <span class="text-success"><?= $consumptionTrend ?>%</span>
                        <?php else: ?>
                            <span class="text-muted">Dieser Monat</span>
                        <?php endif; ?>
                    </div>
                </div>
                <h3><?= number_format($stats['current_month_consumption'], 0) ?></h3>
                <p>kWh <?= date('M Y') ?></p>
            </div>
        </div>

        <!-- Aktueller Monat - Kosten -->
        <div class="col-md-3 mb-3">
            <div class="stats-card warning">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-currency-euro"></i>
                    <div class="small">
                        <?php if ($costTrend > 0): ?>
                            <i class="bi bi-arrow-up-short text-danger"></i>
                            <span class="text-danger">+<?= $costTrend ?>%</span>
                        <?php elseif ($costTrend < 0): ?>
                            <i class="bi bi-arrow-down-short text-success"></i>
                            <span class="text-success"><?= $costTrend ?>%</span>
                        <?php else: ?>
                            <span class="text-muted">Dieser Monat</span>
                        <?php endif; ?>
                    </div>
                </div>
                <h3><?= number_format($stats['current_month_cost'], 2) ?> €</h3>
                <p>Kosten <?= date('M Y') ?></p>
            </div>
        </div>

        <!-- Jahr -->
        <div class="col-md-3 mb-3">
            <div class="stats-card primary">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-graph-up"></i>
                    <div class="small">
                        <?= $currentYear ?>
                    </div>
                </div>
                <h3><?= number_format($stats['year_consumption'], 0) ?></h3>
                <p>kWh dieses Jahr</p>
            </div>
        </div>

        <!-- Geräte -->
        <div class="col-md-3 mb-3">
            <div class="stats-card energy">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-cpu"></i>
                    <div class="small">
                        Aktiv
                    </div>
                </div>
                <h3><?= $stats['total_devices'] ?></h3>
                <p>Registrierte Geräte</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <h5 class="mb-4 flex-center gap-2">
                    <div class="energy-indicator"></div>
                    Schnellaktionen
                </h5>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="zaehlerstand.php" class="btn btn-success w-100 p-4 text-decoration-none">
                            <i class="bi bi-plus-circle mb-2 d-block" style="font-size: 1.5rem;"></i>
                            <div>
                                <div class="fw-bold">Zählerstand</div>
                                <small>Neuen Wert erfassen</small>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3 mb-3">
                        <a href="geraete.php" class="btn btn-primary w-100 p-4 text-decoration-none">
                            <i class="bi bi-cpu mb-2 d-block" style="font-size: 1.5rem;"></i>
                            <div>
                                <div class="fw-bold">Geräte</div>
                                <small>Verwalten & hinzufügen</small>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3 mb-3">
                        <a href="auswertung.php" class="btn btn-warning w-100 p-4 text-decoration-none">
                            <i class="bi bi-bar-chart mb-2 d-block" style="font-size: 1.5rem;"></i>
                            <div>
                                <div class="fw-bold">Auswertung</div>
                                <small>Statistiken anzeigen</small>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3 mb-3">
                        <a href="tarife.php" class="btn btn-energy w-100 p-4 text-decoration-none">
                            <i class="bi bi-tags mb-2 d-block" style="font-size: 1.5rem;"></i>
                            <div>
                                <div class="fw-bold">Tarife</div>
                                <small>Preise anpassen</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Letzte Aktivitäten -->
    <?php if ($stats['total_readings'] > 0): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history text-energy"></i>
                        Letzte Zählerstände
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Letzte 5 Zählerstände abrufen
                    $recentReadings = $pdo->prepare("
                        SELECT reading_value, consumption, cost, reading_date, notes
                        FROM meter_readings 
                        WHERE user_id = ? 
                        ORDER BY reading_date DESC 
                        LIMIT 5
                    ");
                    $recentReadings->execute([$userId]);
                    $recentReadings = $recentReadings->fetchAll();

                    if ($recentReadings): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Datum</th>
                                        <th>Zählerstand</th>
                                        <th>Verbrauch</th>
                                        <th>Kosten</th>
                                        <th>Notizen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReadings as $reading): ?>
                                    <tr>
                                        <td><?= date('d.m.Y', strtotime($reading['reading_date'])) ?></td>
                                        <td><?= number_format($reading['reading_value'], 0, ',', '.') ?> kWh</td>
                                        <td>
                                            <?php if ($reading['consumption'] > 0): ?>
                                                <span class="text-success">
                                                    <?= number_format($reading['consumption'], 0, ',', '.') ?> kWh
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($reading['cost'] > 0): ?>
                                                <?= number_format($reading['cost'], 2, ',', '.') ?> €
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($reading['notes']): ?>
                                                <span class="text-muted" title="<?= htmlspecialchars($reading['notes']) ?>">
                                                    <?= mb_strlen($reading['notes']) > 20 ? mb_substr($reading['notes'], 0, 20) . '...' : $reading['notes'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-center mt-3">
                            <a href="zaehlerstand.php" class="btn btn-outline-primary">
                                <i class="bi bi-list me-2"></i>Alle Zählerstände anzeigen
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-info-circle fs-1 mb-3 d-block"></i>
                            <p>Noch keine Zählerstände erfasst</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    <?php if ($stats['total_readings'] > 0): ?>
    // Chart-Daten für Verlaufsanzeige (falls später hinzugefügt)
    <?php endif; ?>

    // Animate stats cards on load
    const statsCards = document.querySelectorAll('.stats-card');
    statsCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            
            requestAnimationFrame(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            });
        }, index * 100);
    });
    
    // Echte Trend-Anzeige basierend auf Daten
    console.log('Dashboard Stats:', {
        readings: <?= $stats['total_readings'] ?>,
        currentMonth: <?= $stats['current_month_consumption'] ?>,
        yearTotal: <?= $stats['year_consumption'] ?>,
        devices: <?= $stats['total_devices'] ?>
    });
});
</script>