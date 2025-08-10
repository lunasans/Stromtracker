<?php
// dashboard.php
// ECHTES Dashboard mit Datenbankdaten für Stromtracker

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Dashboard - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// =============================================================================
// ECHTE STATISTIKEN AUS DER DATENBANK
// =============================================================================

// Aktueller Monat
$currentMonth = date('Y-m');
$currentYear = date('Y');

// Aktueller Monat Verbrauch
$currentMonthData = Database::fetchOne(
    "SELECT consumption, cost FROM meter_readings 
     WHERE user_id = ? AND DATE_FORMAT(reading_date, '%Y-%m') = ?",
    [$userId, $currentMonth]
) ?: ['consumption' => 0, 'cost' => 0];

// Jahresverbrauch
$yearData = Database::fetchOne(
    "SELECT SUM(consumption) as consumption, SUM(cost) as cost 
     FROM meter_readings 
     WHERE user_id = ? AND YEAR(reading_date) = ?",
    [$userId, $currentYear]
) ?: ['consumption' => 0, 'cost' => 0];

// Geräte-Anzahl
$devicesData = Database::fetchOne(
    "SELECT COUNT(*) as active_count FROM devices 
     WHERE user_id = ? AND is_active = 1",
    [$userId]
) ?: ['active_count' => 0];

// Anzahl Zählerstände
$readingsData = Database::fetchOne(
    "SELECT COUNT(*) as total_readings FROM meter_readings 
     WHERE user_id = ?",
    [$userId]
) ?: ['total_readings' => 0];

// Trend-Berechnung (Vergleich mit Vormonat)
$lastMonth = date('Y-m', strtotime('-1 month'));
$lastMonthData = Database::fetchOne(
    "SELECT consumption, cost FROM meter_readings 
     WHERE user_id = ? AND DATE_FORMAT(reading_date, '%Y-%m') = ?",
    [$userId, $lastMonth]
) ?: ['consumption' => 0, 'cost' => 0];

// Trend berechnen
function calculateTrend($current, $previous) {
    if ($previous == 0) return 0;
    return round((($current - $previous) / $previous) * 100);
}

$consumptionTrend = calculateTrend($currentMonthData['consumption'], $lastMonthData['consumption']);
$costTrend = calculateTrend($currentMonthData['cost'], $lastMonthData['cost']);

// Letzten 7 Tage für Sparkline (falls Daten vorhanden)
$weeklyData = Database::fetchAll(
    "SELECT DATE(reading_date) as date, consumption, cost 
     FROM meter_readings 
     WHERE user_id = ? AND reading_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     ORDER BY reading_date ASC 
     LIMIT 7",
    [$userId]
) ?: [];

// Chart-Daten für die letzten 6 Monate
$chartData = Database::fetchAll(
    "SELECT 
        DATE_FORMAT(reading_date, '%Y-%m') as month,
        DATE_FORMAT(reading_date, '%m/%Y') as month_label,
        consumption,
        cost
     FROM meter_readings 
     WHERE user_id = ? AND reading_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     ORDER BY reading_date ASC",
    [$userId]
) ?: [];

// Top Geräte (geschätzt basierend auf Leistung)
$topDevices = Database::fetchAll(
    "SELECT name, category, wattage,
            (wattage * 24 * 30 / 1000) as estimated_monthly_kwh,
            (wattage * 24 * 30 / 1000 * 0.32) as estimated_monthly_cost
     FROM devices 
     WHERE user_id = ? AND is_active = 1 
     ORDER BY wattage DESC 
     LIMIT 5",
    [$userId]
) ?: [];

// Aktueller Tarif
$currentTariff = Database::fetchOne(
    "SELECT * FROM tariff_periods 
     WHERE user_id = ? AND is_active = 1 
     ORDER BY valid_from DESC LIMIT 1",
    [$userId]
) ?: null;

// Durchschnittsverbrauch berechnen
$avgConsumption = 0;
if (!empty($chartData)) {
    $totalConsumption = array_sum(array_column($chartData, 'consumption'));
    $avgConsumption = $totalConsumption / count($chartData);
}

// Statistiken zusammenfassen
$stats = [
    'current_month_consumption' => (float)($currentMonthData['consumption'] ?? 0),
    'current_month_cost' => (float)($currentMonthData['cost'] ?? 0),
    'year_consumption' => (float)($yearData['consumption'] ?? 0),
    'year_cost' => (float)($yearData['cost'] ?? 0),
    'total_devices' => (int)($devicesData['active_count'] ?? 0),
    'total_readings' => (int)($readingsData['total_readings'] ?? 0),
    'consumption_trend' => $consumptionTrend,
    'cost_trend' => $costTrend,
    'avg_consumption' => $avgConsumption
];

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
            <div class="card glass p-5">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-energy mb-3">
                            <span class="energy-indicator"></span>
                            Willkommen zurück, <?= htmlspecialchars($user['name'] ?? explode('@', $user['email'])[0]) ?>!
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
                        <div class="d-flex justify-content-end align-items-center gap-3">
                            <div class="text-center">
                                <div class="h4 text-energy mb-1"><?= date('H:i') ?></div>
                                <small class="text-muted">Aktuelle Zeit</small>
                            </div>
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
                            <i class="bi bi-dash"></i>
                            <span>0%</span>
                        <?php endif; ?>
                    </div>
                </div>
                <h3><?= number_format($stats['current_month_consumption'], 1) ?></h3>
                <p>kWh aktueller Monat</p>
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
                            <i class="bi bi-dash"></i>
                            <span>0%</span>
                        <?php endif; ?>
                    </div>
                </div>
                <h3><?= number_format($stats['current_month_cost'], 2) ?> €</h3>
                <p>Kosten aktueller Monat</p>
            </div>
        </div>

        <!-- Jahresverbrauch -->
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
                                <small>Neue Ablesung</small>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3 mb-3">
                        <a href="geraete.php" class="btn btn-primary w-100 p-4 text-decoration-none">
                            <i class="bi bi-cpu mb-2 d-block" style="font-size: 1.5rem;"></i>
                            <div>
                                <div class="fw-bold">Geräte</div>
                                <small>Verwalten (<?= $stats['total_devices'] ?>)</small>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3 mb-3">
                        <a href="auswertung.php" class="btn btn-energy w-100 p-4 text-decoration-none">
                            <i class="bi bi-bar-chart mb-2 d-block" style="font-size: 1.5rem;"></i>
                            <div>
                                <div class="fw-bold text-white">Auswertung</div>
                                <small class="text-white">Charts & Trends</small>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-3 mb-3">
                        <a href="tarife.php" class="btn btn-outline-primary w-100 p-4 text-decoration-none">
                            <i class="bi bi-receipt mb-2 d-block" style="font-size: 1.5rem;"></i>
                            <div>
                                <div class="fw-bold">Tarife</div>
                                <small><?= $currentTariff ? 'Verwalten' : 'Erstellen' ?></small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="row">
        
        <!-- Hauptchart -->
        <div class="col-md-8 mb-4">
            <div class="card glass p-4" style="height: 400px;">
                <div class="flex-between mb-4">
                    <h5 class="mb-0 flex-center gap-2">
                        <i class="bi bi-graph-up text-success"></i>
                        Verbrauchstrend (<?= count($chartData) ?> Monate)
                    </h5>

                    <!-- Chart Info -->
                    <div class="text-end">
                        <?php if (!empty($chartData)): ?>
                            <small class="text-muted">
                                Ø <?= number_format($avgConsumption, 1) ?> kWh/Monat
                            </small>
                        <?php else: ?>
                            <small class="text-muted">
                                Keine Daten verfügbar
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chart Canvas -->
                <div style="position: relative; height: 300px;">
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

        <!-- Sidebar -->
        <div class="col-md-4">
            
            <!-- Aktueller Tarif -->
            <?php if ($currentTariff): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title flex-center gap-2">
                        <i class="bi bi-receipt text-energy"></i>
                        Aktueller Tarif
                    </h6>
                    
                    <div class="mb-3">
                        <div class="flex-between">
                            <span class="text-muted">Anbieter</span>
                            <strong><?= htmlspecialchars($currentTariff['provider_name'] ?: 'Standard') ?></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="flex-between">
                            <span class="text-muted">Arbeitspreis</span>
                            <strong class="text-energy"><?= number_format($currentTariff['rate_per_kwh'], 4) ?> €/kWh</strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="flex-between">
                            <span class="text-muted">Monatlicher Abschlag</span>
                            <strong class="text-warning"><?= number_format($currentTariff['monthly_payment'], 2) ?> €</strong>
                        </div>
                    </div>
                    
                    <a href="tarife.php" class="btn btn-outline-primary btn-sm w-100">
                        Tarife verwalten
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Geräte -->
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title flex-center gap-2">
                        <i class="bi bi-star text-warning"></i>
                        Top Stromverbraucher
                    </h6>
                    
                    <?php if (!empty($topDevices)): ?>
                        <?php foreach (array_slice($topDevices, 0, 3) as $index => $device): ?>
                            <div class="mb-3">
                                <div class="flex-between mb-1">
                                    <span>
                                        <i class="bi bi-cpu me-1"></i>
                                        <?= htmlspecialchars($device['name']) ?>
                                    </span>
                                    <span class="<?= $index === 0 ? 'text-danger' : ($index === 1 ? 'text-warning' : 'text-success') ?>">
                                        <?= number_format($device['estimated_monthly_kwh'], 1) ?> kWh
                                    </span>
                                </div>
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar <?= $index === 0 ? 'bg-danger' : ($index === 1 ? 'bg-warning' : 'bg-success') ?>" 
                                         style="width: <?= min(100, ($device['wattage'] / 2000) * 100) ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?= number_format($device['wattage']) ?>W • 
                                    ~<?= number_format($device['estimated_monthly_cost'], 2) ?> €/Monat
                                </small>
                            </div>
                        <?php endforeach; ?>
                        
                        <a href="geraete.php" class="btn btn-outline-primary btn-sm w-100 mt-3">
                            Alle <?= count($topDevices) ?> Geräte anzeigen
                        </a>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-cpu display-4 text-muted"></i>
                            <p class="text-muted mt-2">Keine Geräte registriert</p>
                            <a href="geraete.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle me-1"></i>Geräte hinzufügen
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Chart.js Setup -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Nur Chart erstellen wenn Daten vorhanden sind
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
                    pointRadius: 4
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
                    pointRadius: 4,
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
                        padding: 20
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
                    }
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
    
    // Echte Trend-Anzeige basierend auf Daten
    console.log('Dashboard Stats:', {
        readings: <?= $stats['total_readings'] ?>,
        currentMonth: <?= $stats['current_month_consumption'] ?>,
        yearTotal: <?= $stats['year_consumption'] ?>,
        devices: <?= $stats['total_devices'] ?>
    });
});
</script>