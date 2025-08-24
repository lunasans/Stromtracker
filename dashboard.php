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
    'total_devices' => 0,
    'consumption_trend' => 0,
    'cost_trend' => 0,
    'avg_consumption' => 0
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

    // Trend-Berechnung (Vergleich mit Vormonat)
    $lastMonth = date('Y-m', strtotime('-1 month'));
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(consumption), 0) as consumption,
            COALESCE(SUM(cost), 0) as cost
        FROM meter_readings 
        WHERE user_id = ? AND DATE_FORMAT(reading_date, '%Y-%m') = ?
    ");
    $stmt->execute([$userId, $lastMonth]);
    $lastMonthData = $stmt->fetch();

    // Trend berechnen
    if ($lastMonthData['consumption'] > 0) {
        $stats['consumption_trend'] = round((($stats['current_month_consumption'] - $lastMonthData['consumption']) / $lastMonthData['consumption']) * 100);
    }
    if ($lastMonthData['cost'] > 0) {
        $stats['cost_trend'] = round((($stats['current_month_cost'] - $lastMonthData['cost']) / $lastMonthData['cost']) * 100);
    }

} catch (PDOException $e) {
    error_log("Dashboard Fehler: " . $e->getMessage());
}

// Chart-Daten für die letzten 6 Monate
$chartData = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(reading_date, '%Y-%m') as month,
            DATE_FORMAT(reading_date, '%m/%Y') as month_label,
            consumption,
            cost
         FROM meter_readings 
         WHERE user_id = ? AND reading_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         ORDER BY reading_date ASC
    ");
    $stmt->execute([$userId]);
    $chartData = $stmt->fetchAll();

    // Durchschnittsverbrauch berechnen
    if (!empty($chartData)) {
        $totalConsumption = array_sum(array_column($chartData, 'consumption'));
        $stats['avg_consumption'] = $totalConsumption / count($chartData);
    }
} catch (PDOException $e) {
    error_log("Chart-Daten Fehler: " . $e->getMessage());
}

// Trend-Daten für Charts vorbereiten
$trendLabels = [];
$trendConsumption = [];
$trendCosts = [];

foreach ($chartData as $data) {
    $trendLabels[] = $data['month_label'];
    $trendConsumption[] = (float)($data['consumption'] ?? 0);
    $trendCosts[] = (float)($data['cost'] ?? 0);
}

// Falls keine Daten vorhanden, Demo-Daten für Chart
if (empty($chartData)) {
    $trendLabels = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun'];
    $trendConsumption = [0, 0, 0, 0, 0, 0];
    $trendCosts = [0, 0, 0, 0, 0, 0];
}

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
                        <?php if ($stats['consumption_trend'] > 0): ?>
                            <i class="bi bi-arrow-up-short text-danger"></i>
                            <span class="text-danger">+<?= $stats['consumption_trend'] ?>%</span>
                        <?php elseif ($stats['consumption_trend'] < 0): ?>
                            <i class="bi bi-arrow-down-short text-success"></i>
                            <span class="text-success"><?= $stats['consumption_trend'] ?>%</span>
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
                        <?php if ($stats['cost_trend'] > 0): ?>
                            <i class="bi bi-arrow-up-short text-danger"></i>
                            <span class="text-danger">+<?= $stats['cost_trend'] ?>%</span>
                        <?php elseif ($stats['cost_trend'] < 0): ?>
                            <i class="bi bi-arrow-down-short text-success"></i>
                            <span class="text-success"><?= $stats['cost_trend'] ?>%</span>
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

    <!-- Verbrauchstrend Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <div class="flex-between mb-4">
                    <h5 class="mb-0 flex-center gap-2">
                        <i class="bi bi-graph-up text-success"></i>
                        Verbrauchstrend (Letzte 6 Monate)
                    </h5>
                    
                    <!-- Chart Info -->
                    <div class="text-end">
                        <?php if (!empty($chartData)): ?>
                            <small class="text-muted">
                                Ø <?= number_format($stats['avg_consumption'], 1) ?> kWh/Monat
                            </small>
                        <?php else: ?>
                            <small class="text-muted">
                                <a href="zaehlerstand.php" class="text-energy">Zählerstände erfassen</a>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chart Container -->
                <div style="position: relative; height: 350px;">
                    <?php if (!empty($chartData)): ?>
                        <canvas id="consumptionChart"></canvas>
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center h-100">
                            <div class="text-center">
                                <i class="bi bi-graph-up display-4 text-muted mb-3"></i>
                                <h5 class="text-muted">Keine Verbrauchsdaten</h5>
                                <p class="text-muted">Erfassen Sie Zählerstände, um Charts zu sehen.</p>
                                <a href="zaehlerstand.php" class="btn btn-energy">
                                    <i class="bi bi-plus-circle me-2"></i>Zählerstand erfassen
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
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
                        SELECT meter_value, consumption, cost, reading_date, notes
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
                                        <td><?= number_format($reading['meter_value'], 0, ',', '.') ?> kWh</td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    <?php if (!empty($chartData)): ?>
    // Verbrauchstrend Chart mit echten Daten
    const ctx = document.getElementById('consumptionChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [
                {
                    label: 'Verbrauch (kWh)',
                    data: <?= json_encode($trendConsumption) ?>,
                    borderColor: 'rgb(245, 158, 11)',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgb(245, 158, 11)',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                },
                {
                    label: 'Kosten (€)',
                    data: <?= json_encode($trendCosts) ?>,
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: false,
                    pointBackgroundColor: 'rgb(16, 185, 129)',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: 'rgb(245, 158, 11)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return `Verbrauch: ${context.parsed.y.toFixed(1)} kWh`;
                            } else {
                                return `Kosten: ${context.parsed.y.toFixed(2)} €`;
                            }
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Monat'
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Verbrauch (kWh)'
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    },
                    beginAtZero: true
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Kosten (€)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    beginAtZero: true
                }
            }
        }
    });
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
    
    // Debug-Info
    console.log('Dashboard Stats:', {
        readings: <?= $stats['total_readings'] ?>,
        currentMonth: <?= $stats['current_month_consumption'] ?>,
        yearTotal: <?= $stats['year_consumption'] ?>,
        devices: <?= $stats['total_devices'] ?>,
        chartDataPoints: <?= count($chartData) ?>,
        avgConsumption: <?= $stats['avg_consumption'] ?>
    });
});
</script>