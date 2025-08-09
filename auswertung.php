<?php
// auswertung.php
// Auswertungen und Charts für Stromverbrauch

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Auswertung & Charts - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// Filter-Parameter
$selectedYear = $_GET['year'] ?? date('Y');
$selectedPeriod = $_GET['period'] ?? '12months'; // 12months, 6months, year

// Verfügbare Jahre für Filter
$availableYears = Database::fetchAll(
    "SELECT DISTINCT YEAR(reading_date) as year 
     FROM meter_readings 
     WHERE user_id = ? 
     ORDER BY year DESC",
    [$userId]
) ?: [];

// Monatliche Daten für Charts
switch ($selectedPeriod) {
    case '6months':
        $monthsBack = 6;
        break;
    case 'year':
        $monthsBack = 12;
        $selectedYear = null; // Aktuelles Jahr
        break;
    default:
        $monthsBack = 12;
}

if ($selectedYear && $selectedPeriod !== 'year') {
    // Spezifisches Jahr
    $chartData = Database::fetchAll(
        "SELECT 
            DATE_FORMAT(reading_date, '%Y-%m') as month,
            DATE_FORMAT(reading_date, '%m/%Y') as month_label,
            meter_value,
            consumption,
            cost,
            rate_per_kwh
         FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = ?
         ORDER BY reading_date ASC",
        [$userId, $selectedYear]
    ) ?: [];
} else {
    // Letzte X Monate
    $chartData = Database::fetchAll(
        "SELECT 
            DATE_FORMAT(reading_date, '%Y-%m') as month,
            DATE_FORMAT(reading_date, '%m/%Y') as month_label,
            meter_value,
            consumption,
            cost,
            rate_per_kwh
         FROM meter_readings 
         WHERE user_id = ? AND reading_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
         ORDER BY reading_date ASC",
        [$userId, $monthsBack]
    ) ?: [];
}

// Jahresvergleich-Daten
$yearlyComparison = Database::fetchAll(
    "SELECT 
        YEAR(reading_date) as year,
        COUNT(*) as readings_count,
        SUM(consumption) as total_consumption,
        SUM(cost) as total_cost,
        AVG(consumption) as avg_consumption,
        MIN(consumption) as min_consumption,
        MAX(consumption) as max_consumption
     FROM meter_readings 
     WHERE user_id = ? AND consumption IS NOT NULL
     GROUP BY YEAR(reading_date)
     ORDER BY year DESC",
    [$userId]
) ?: [];

// Aktuelle Statistiken
$currentYear = date('Y');
$currentStats = [
    'current_month' => Database::fetchSingle(
        "SELECT consumption, cost FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE()) AND MONTH(reading_date) = MONTH(CURDATE())",
        [$userId]
    ) ?: ['consumption' => 0, 'cost' => 0],
    
    'year_total' => Database::fetchSingle(
        "SELECT SUM(consumption) as consumption, SUM(cost) as cost 
         FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE())",
        [$userId]
    ) ?: ['consumption' => 0, 'cost' => 0],
    
    'avg_monthly' => Database::fetchSingle(
        "SELECT AVG(consumption) as consumption, AVG(cost) as cost 
         FROM meter_readings 
         WHERE user_id = ? AND consumption IS NOT NULL",
        [$userId]
    ) ?: ['consumption' => 0, 'cost' => 0],
    
    'highest_month' => Database::fetchSingle(
        "SELECT DATE_FORMAT(reading_date, '%m/%Y') as month, consumption, cost 
         FROM meter_readings 
         WHERE user_id = ? AND consumption IS NOT NULL
         ORDER BY consumption DESC LIMIT 1",
        [$userId]
    ) ?: ['month' => '-', 'consumption' => 0, 'cost' => 0],
    
    'lowest_month' => Database::fetchSingle(
        "SELECT DATE_FORMAT(reading_date, '%m/%Y') as month, consumption, cost 
         FROM meter_readings 
         WHERE user_id = ? AND consumption IS NOT NULL
         ORDER BY consumption ASC LIMIT 1",
        [$userId]
    ) ?: ['month' => '-', 'consumption' => 0, 'cost' => 0]
];

// Prognose für nächsten Monat (basierend auf Durchschnitt der letzten 3 Monate)
$forecast = Database::fetchSingle(
    "SELECT AVG(consumption) as avg_consumption, AVG(cost) as avg_cost 
     FROM meter_readings 
     WHERE user_id = ? AND consumption IS NOT NULL
     ORDER BY reading_date DESC LIMIT 3",
    [$userId]
) ?: ['avg_consumption' => 0, 'avg_cost' => 0];

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="bi bi-bar-chart text-primary"></i>
                Auswertung & Charts
            </h1>
            <p class="text-muted">Visualisierung Ihres Stromverbrauchs und detaillierte Analysen.</p>
        </div>
        <div class="col-md-4">
            <!-- Filter -->
            <form method="GET" class="d-flex gap-2">
                <select name="period" class="form-select form-select-sm">
                    <option value="12months" <?= $selectedPeriod === '12months' ? 'selected' : '' ?>>
                        Letzte 12 Monate
                    </option>
                    <option value="6months" <?= $selectedPeriod === '6months' ? 'selected' : '' ?>>
                        Letzte 6 Monate
                    </option>
                    <option value="year" <?= $selectedPeriod === 'year' ? 'selected' : '' ?>>
                        Aktuelles Jahr
                    </option>
                </select>
                
                <?php if (!empty($availableYears) && $selectedPeriod !== 'year'): ?>
                    <select name="year" class="form-select form-select-sm">
                        <?php foreach ($availableYears as $yearData): ?>
                            <option value="<?= $yearData['year'] ?>" 
                                    <?= $selectedYear == $yearData['year'] ? 'selected' : '' ?>>
                                <?= $yearData['year'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel"></i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Schnell-Statistiken -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= formatKwh($currentStats['current_month']['consumption']) ?></h4>
                            <p class="mb-0">Aktueller Monat</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-calendar-month"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= formatKwh($currentStats['year_total']['consumption']) ?></h4>
                            <p class="mb-0">Jahr <?= $currentYear ?></p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-calendar-year"></i>
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
                            <h4><?= formatKwh($currentStats['avg_monthly']['consumption']) ?></h4>
                            <p class="mb-0">⌀ Monatlich</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-graph-up"></i>
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
                            <h4><?= formatCurrency($currentStats['year_total']['cost']) ?></h4>
                            <p class="mb-0">Kosten Jahr</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="row mb-4">
        
        <!-- Verbrauchstrend -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up text-success"></i>
                        Verbrauchstrend
                        <small class="text-muted">
                            (<?= $selectedPeriod === 'year' ? 'Aktuelles Jahr' : 
                                   ($selectedYear ? $selectedYear : 'Letzte ' . $monthsBack . ' Monate') ?>)
                        </small>
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="consumptionChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Kostenverteilung -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart text-warning"></i>
                        Jahresvergleich
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="yearComparisonChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kostentrend und Detailanalyse -->
    <div class="row mb-4">
        
        <!-- Kostentrend -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-currency-euro text-success"></i>
                        Kostenentwicklung
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="costChart" height="120"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Verbrauchsanalyse -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-speedometer2 text-info"></i>
                        Verbrauchsanalyse
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Durchschnittswerte -->
                    <div class="row mb-4">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-success"><?= formatKwh($currentStats['avg_monthly']['consumption']) ?></div>
                                <small class="text-muted">⌀ Monatlich</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="h4 text-primary"><?= formatCurrency($currentStats['avg_monthly']['cost']) ?></div>
                                <small class="text-muted">⌀ Kosten</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Extreme Werte -->
                    <div class="row">
                        <div class="col-12">
                            <h6 class="mb-3">Extreme Werte:</h6>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-danger bg-opacity-10 rounded">
                                <span>
                                    <i class="bi bi-arrow-up text-danger"></i>
                                    <strong>Höchster Verbrauch:</strong>
                                </span>
                                <span>
                                    <?= formatKwh($currentStats['highest_month']['consumption']) ?>
                                    <small class="text-muted">(<?= $currentStats['highest_month']['month'] ?>)</small>
                                </span>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center p-2 bg-success bg-opacity-10 rounded">
                                <span>
                                    <i class="bi bi-arrow-down text-success"></i>
                                    <strong>Niedrigster Verbrauch:</strong>
                                </span>
                                <span>
                                    <?= formatKwh($currentStats['lowest_month']['consumption']) ?>
                                    <small class="text-muted">(<?= $currentStats['lowest_month']['month'] ?>)</small>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Prognose und Jahresübersicht -->
    <div class="row">
        
        <!-- Prognose -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-crystal-ball text-purple"></i>
                        Prognose
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Nächster Monat:</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h5"><?= formatKwh($forecast['avg_consumption']) ?></div>
                                    <small>Erwarteter Verbrauch</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h5"><?= formatCurrency($forecast['avg_cost']) ?></div>
                                    <small>Erwartete Kosten</small>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Basierend auf dem Durchschnitt der letzten 3 Monate
                        </small>
                    </div>
                    
                    <!-- Energiespar-Tipps -->
                    <div class="mt-3">
                        <h6>💡 Energiespar-Tipps:</h6>
                        <ul class="small text-muted">
                            <li>LED-Lampen verwenden</li>
                            <li>Geräte komplett ausschalten</li>
                            <li>Kühlschrank richtig einstellen</li>
                            <li>Stoßlüften statt Dauerlüften</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Jahresübersicht -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-table text-primary"></i>
                        Jahresübersicht
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($yearlyComparison)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x display-4 text-muted"></i>
                            <p class="text-muted mt-2">Keine Jahresvergleichsdaten verfügbar.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Jahr</th>
                                        <th>Ablesungen</th>
                                        <th>Gesamt-Verbrauch</th>
                                        <th>Gesamt-Kosten</th>
                                        <th>⌀ Monatlich</th>
                                        <th>Min/Max</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($yearlyComparison as $year): ?>
                                        <tr>
                                            <td>
                                                <strong><?= $year['year'] ?></strong>
                                                <?php if ($year['year'] == date('Y')): ?>
                                                    <span class="badge bg-primary ms-1">Aktuell</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $year['readings_count'] ?></td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?= formatKwh($year['total_consumption']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= formatCurrency($year['total_cost']) ?></strong>
                                            </td>
                                            <td><?= formatKwh($year['avg_consumption']) ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= formatKwh($year['min_consumption']) ?> - 
                                                    <?= formatKwh($year['max_consumption']) ?>
                                                </small>
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
    </div>
</div>

<!-- JavaScript für Charts -->
<script>
// Chart-Daten von PHP vorbereiten
const chartData = <?= json_encode($chartData) ?>;
const yearlyData = <?= json_encode($yearlyComparison) ?>;

// Chart-Konfiguration
Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
Chart.defaults.color = '#6c757d';

// 1. Verbrauchstrend-Chart
if (chartData.length > 0) {
    const consumptionCtx = document.getElementById('consumptionChart').getContext('2d');
    new Chart(consumptionCtx, {
        type: 'line',
        data: {
            labels: chartData.map(item => item.month_label),
            datasets: [{
                label: 'Verbrauch (kWh)',
                data: chartData.map(item => parseFloat(item.consumption) || 0),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Verbrauch (kWh)'
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            elements: {
                point: {
                    hoverRadius: 8
                }
            }
        }
    });
}

// 2. Jahresvergleich-Donut-Chart
if (yearlyData.length > 0) {
    const yearCtx = document.getElementById('yearComparisonChart').getContext('2d');
    new Chart(yearCtx, {
        type: 'doughnut',
        data: {
            labels: yearlyData.map(item => item.year.toString()),
            datasets: [{
                data: yearlyData.map(item => parseFloat(item.total_consumption)),
                backgroundColor: [
                    '#3b82f6',
                    '#10b981', 
                    '#f59e0b',
                    '#ef4444',
                    '#8b5cf6',
                    '#06b6d4'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
}

// 3. Kosten-Chart
if (chartData.length > 0) {
    const costCtx = document.getElementById('costChart').getContext('2d');
    new Chart(costCtx, {
        type: 'bar',
        data: {
            labels: chartData.map(item => item.month_label),
            datasets: [{
                label: 'Kosten (€)',
                data: chartData.map(item => parseFloat(item.cost) || 0),
                backgroundColor: 'rgba(245, 158, 11, 0.8)',
                borderColor: '#f59e0b',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Kosten (€)'
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Chart-Updates bei Resize
window.addEventListener('resize', function() {
    Chart.helpers.each(Chart.instances, function(instance) {
        instance.resize();
    });
});

// Export-Funktionen
function exportChart(chartId, filename) {
    const canvas = document.getElementById(chartId);
    const url = canvas.toDataURL('image/png');
    const link = document.createElement('a');
    link.download = filename + '.png';
    link.href = url;
    link.click();
}

// Daten-Export (CSV)
function exportData() {
    let csv = 'Monat,Zählerstand,Verbrauch,Kosten,Preis pro kWh\n';
    
    chartData.forEach(function(item) {
        csv += `${item.month_label},${item.meter_value},${item.consumption || 0},${item.cost || 0},${item.rate_per_kwh || 0}\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.download = 'stromverbrauch_export.csv';
    link.href = url;
    link.click();
    window.URL.revokeObjectURL(url);
}
</script>

<?php include 'includes/footer.php'; ?>