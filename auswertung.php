<?php
// auswertung.php
// EINFACHE & SCHÖNE Auswertungen und Charts für Stromverbrauch

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

// Tasmota Smart-Geräte monatlicher Verbrauch
$tasmotaDevices = Database::fetchAll(
    "SELECT id, name, category FROM devices 
     WHERE user_id = ? AND tasmota_enabled = 1 AND is_active = 1
     ORDER BY name ASC",
    [$userId]
) ?: [];

// Monatliche Verbrauchsdaten für jedes Smart-Gerät
$tasmotaMonthlyData = [];
foreach ($tasmotaDevices as $device) {
    // Einfache Abfrage: Letzter energy_today Wert pro Tag
    $monthlyData = Database::fetchAll(
        "SELECT 
            DATE_FORMAT(day_date, '%Y-%m') as month,
            DATE_FORMAT(day_date, '%m/%Y') as month_label,
            YEAR(day_date) as year,
            MONTH(day_date) as month_num,
            SUM(daily_kwh) as total_kwh
         FROM (
             SELECT 
                 DATE(timestamp) as day_date,
                 MAX(energy_today) as daily_kwh
             FROM tasmota_readings
             WHERE device_id = ? 
             AND timestamp >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             AND energy_today IS NOT NULL AND energy_today > 0 AND energy_today <= 50
             GROUP BY DATE(timestamp)
         ) daily_summary
         GROUP BY DATE_FORMAT(day_date, '%Y-%m')
         HAVING SUM(daily_kwh) > 0
         ORDER BY year ASC, month_num ASC
         LIMIT 6",
        [$device['id']]
    ) ?: [];
    
    if (!empty($monthlyData)) {
        $tasmotaMonthlyData[$device['id']] = [
            'device' => $device,
            'data' => array_reverse($monthlyData) // Chronologische Reihenfolge
        ];
    }
}

// Aktuelle Statistiken
$currentYear = date('Y');
$currentStats = [
    'current_month' => Database::fetchOne(
        "SELECT consumption, cost FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE()) AND MONTH(reading_date) = MONTH(CURDATE())",
        [$userId]
    ) ?: ['consumption' => 0, 'cost' => 0],
    
    'year_total' => Database::fetchOne(
        "SELECT SUM(consumption) as consumption, SUM(cost) as cost 
         FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE())",
        [$userId]
    ) ?: ['consumption' => 0, 'cost' => 0],
    
    'avg_monthly' => Database::fetchOne(
        "SELECT AVG(consumption) as consumption, AVG(cost) as cost 
         FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE()) AND consumption IS NOT NULL",
        [$userId]
    ) ?: ['consumption' => 0, 'cost' => 0],
    
    'highest_month' => Database::fetchOne(
        "SELECT consumption, cost, DATE_FORMAT(reading_date, '%m/%Y') as month 
         FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE()) AND consumption IS NOT NULL
         ORDER BY consumption DESC LIMIT 1",
        [$userId]
    ) ?: ['consumption' => 0, 'cost' => 0, 'month' => '-'],
    
    'lowest_month' => Database::fetchOne(
        "SELECT consumption, cost, DATE_FORMAT(reading_date, '%m/%Y') as month 
         FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE()) AND consumption IS NOT NULL
         ORDER BY consumption ASC LIMIT 1",
        [$userId]
    ) ?: ['consumption' => 0, 'cost' => 0, 'month' => '-']
];

include 'includes/header.php';
include 'includes/navbar.php';
?>

<!-- CSS Fixes -->
<style>
.flex-between {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>

<!-- Auswertung Content -->
<div class="container-fluid py-4">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-energy mb-2">
                            <span class="energy-indicator"></span>
                            <i class="bi bi-bar-chart"></i>
                            Auswertung & Charts
                        </h1>
                        <p class="text-muted mb-0">Analysieren Sie Ihren Stromverbrauch mit interaktiven Diagrammen und Statistiken.</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <button class="btn btn-outline-primary btn-sm" onclick="exportCharts()">
                                <i class="bi bi-download me-1"></i>Export
                            </button>
                            <button class="btn btn-energy btn-sm" onclick="refreshCharts()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Aktualisieren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="bi bi-calendar me-1"></i>Zeitraum
                            </label>
                            <select class="form-select" name="period" onchange="this.form.submit()">
                                <option value="6months" <?= $selectedPeriod === '6months' ? 'selected' : '' ?>>Letzte 6 Monate</option>
                                <option value="12months" <?= $selectedPeriod === '12months' ? 'selected' : '' ?>>Letzte 12 Monate</option>
                                <option value="year" <?= $selectedPeriod === 'year' ? 'selected' : '' ?>>Aktuelles Jahr</option>
                            </select>
                        </div>
                        
                        <?php if (!empty($availableYears) && $selectedPeriod !== 'year'): ?>
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="bi bi-calendar3 me-1"></i>Jahr
                            </label>
                            <select class="form-select" name="year" onchange="this.form.submit()">
                                <?php foreach ($availableYears as $year): ?>
                                    <option value="<?= $year['year'] ?>" 
                                            <?= $selectedYear == $year['year'] ? 'selected' : '' ?>>
                                        <?= $year['year'] ?>
                                        <?= $year['year'] == date('Y') ? ' (Aktuell)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i> Filter anwenden
                                </button>
                                <a href="auswertung.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistik Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card success">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-lightning-charge"></i>
                    <div class="small">
                        Aktuell
                    </div>
                </div>
                <h3><?= number_format($currentStats['current_month']['consumption'] ?? 0, 1) ?></h3>
                <p>kWh diesen Monat</p>
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
                <h3><?= number_format($currentStats['current_month']['cost'] ?? 0, 2) ?> €</h3>
                <p>Kosten diesen Monat</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card primary">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-graph-up"></i>
                    <div class="small">
                        <?= date('Y') ?>
                    </div>
                </div>
                <h3><?= number_format($currentStats['year_total']['consumption'] ?? 0, 0) ?></h3>
                <p>kWh dieses Jahr</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stats-card energy">
                <div class="flex-between mb-3">
                    <i class="stats-icon bi bi-calculator"></i>
                    <div class="small">
                        Durchschnitt
                    </div>
                </div>
                <h3><?= number_format($currentStats['avg_monthly']['consumption'] ?? 0, 1) ?></h3>
                <p>kWh pro Monat</p>
            </div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="row">
        
        <!-- Hauptchart - Verbrauchsverlauf -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <div class="flex-between">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up text-energy"></i>
                            Verbrauchsverlauf
                        </h5>
                        <small class="text-muted">
                            (<?= $selectedPeriod === 'year' ? 'Aktuelles Jahr' : 
                                   ($selectedYear ? $selectedYear : 'Letzte ' . $monthsBack . ' Monate') ?>)
                        </small>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($chartData)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-graph-up display-4 text-muted"></i>
                            <h5 class="mt-3 text-muted">Keine Daten verfügbar</h5>
                            <p class="text-muted">Erfassen Sie zuerst einige Zählerstände.</p>
                            <a href="zaehlerstand.php" class="btn btn-energy">
                                <i class="bi bi-plus-circle me-2"></i>Zählerstand erfassen
                            </a>
                        </div>
                    <?php else: ?>
                        <canvas id="consumptionChart" style="height: 300px;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Jahresvergleich -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart text-primary"></i>
                        Jahresvergleich
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($yearlyComparison)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-pie-chart display-4 text-muted"></i>
                            <p class="text-muted mt-3">Keine Jahresdaten verfügbar</p>
                        </div>
                    <?php else: ?>
                        <canvas id="yearComparisonChart" style="height: 250px;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kostentrend und Analyse -->
    <div class="row">
        
        <!-- Kostenentwicklung -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-currency-euro text-success"></i>
                        Kostenentwicklung
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($chartData)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-currency-euro display-4 text-muted"></i>
                            <p class="text-muted mt-3">Keine Kostendaten verfügbar</p>
                        </div>
                    <?php else: ?>
                        <canvas id="costChart" style="height: 250px;"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Detailanalyse -->
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
                            <div class="text-center p-3 rounded" style="background: var(--gray-50);">
                                <div class="h4 text-success"><?= number_format($currentStats['avg_monthly']['consumption'] ?? 0, 1) ?> kWh</div>
                                <small class="text-muted">⌀ Monatlich</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 rounded" style="background: var(--gray-50);">
                                <div class="h4 text-primary"><?= number_format($currentStats['avg_monthly']['cost'] ?? 0, 2) ?> €</div>
                                <small class="text-muted">⌀ Kosten</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Extreme Werte -->
                    <div class="row">
                        <div class="col-12">
                            <h6 class="mb-3">Extreme Werte:</h6>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 p-3 rounded" 
                                 style="background: rgba(239, 68, 68, 0.1);">
                                <span>
                                    <i class="bi bi-arrow-up text-danger me-2"></i>
                                    <strong>Höchster Verbrauch:</strong>
                                </span>
                                <span>
                                    <span class="fw-bold"><?= number_format($currentStats['highest_month']['consumption'] ?? 0, 1) ?> kWh</span>
                                    <br><small class="text-muted"><?= $currentStats['highest_month']['month'] ?? '-' ?></small>
                                </span>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center p-3 rounded" 
                                 style="background: rgba(16, 185, 129, 0.1);">
                                <span>
                                    <i class="bi bi-arrow-down text-success me-2"></i>
                                    <strong>Niedrigster Verbrauch:</strong>
                                </span>
                                <span>
                                    <span class="fw-bold"><?= number_format($currentStats['lowest_month']['consumption'] ?? 0, 1) ?> kWh</span>
                                    <br><small class="text-muted"><?= $currentStats['lowest_month']['month'] ?? '-' ?></small>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Smart-Geräte monatlicher Verbrauch -->
    <?php if (!empty($tasmotaMonthlyData)): ?>
    <div class="row mt-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-wifi text-success"></i>
                        Smart-Geräte Verbrauch (Letzte 12 Monate)
                    </h5>
                    <small class="text-muted">Monatlicher kWh-Verbrauch für alle Tasmota-Geräte</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($tasmotaMonthlyData as $deviceId => $deviceData): ?>
                            <div class="col-xl-6 col-lg-12 mb-4">
                                <div class="card border-success">
                                    <div class="card-header bg-success bg-gradient text-white py-2">
                                        <h6 class="mb-0">
                                            <i class="bi bi-cpu me-2"></i>
                                            <?= htmlspecialchars($deviceData['device']['name']) ?>
                                        </h6>
                                        <small class="opacity-75">
                                            <?= htmlspecialchars($deviceData['device']['category']) ?>
                                        </small>
                                    </div>
                                    <div class="card-body">
                                        <!-- Statistiken -->
                                        <?php 
                                            $totalKwh = array_sum(array_column($deviceData['data'], 'total_kwh'));
                                            $avgKwh = count($deviceData['data']) > 0 ? $totalKwh / count($deviceData['data']) : 0;
                                            $maxMonth = !empty($deviceData['data']) ? max(array_column($deviceData['data'], 'total_kwh')) : 0;
                                        ?>
                                        <div class="row mb-3 text-center">
                                            <div class="col-4">
                                                <div class="p-2 rounded" style="background: rgba(16, 185, 129, 0.1);">
                                                    <div class="fw-bold text-success"><?= number_format($totalKwh, 1) ?></div>
                                                    <small class="text-muted">Total kWh</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="p-2 rounded" style="background: rgba(59, 130, 246, 0.1);">
                                                    <div class="fw-bold text-primary"><?= number_format($avgKwh, 1) ?></div>
                                                    <small class="text-muted">⌀ Monat</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="p-2 rounded" style="background: rgba(249, 115, 22, 0.1);">
                                                    <div class="fw-bold text-warning"><?= number_format($maxMonth, 1) ?></div>
                                                    <small class="text-muted">Max kWh</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Chart -->
                                        <div class="chart-container" style="position: relative; height: 200px; width: 100%; overflow: hidden;">
                                        <canvas id="tasmotaChart_<?= $deviceId ?>"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Jahresvergleich Tabelle -->
    <?php if (!empty($yearlyComparison)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-table text-energy"></i>
                        Jahresvergleich im Detail
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: var(--gray-50);">
                                <tr>
                                    <th>Jahr</th>
                                    <th>Ablesungen</th>
                                    <th>Gesamtverbrauch</th>
                                    <th>Gesamtkosten</th>
                                    <th>⌀ Monatlich</th>
                                    <th>Min - Max</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($yearlyComparison as $year): ?>
                                    <tr class="<?= $year['year'] == date('Y') ? 'table-success' : '' ?>">
                                        <td>
                                            <strong><?= $year['year'] ?></strong>
                                            <?php if ($year['year'] == date('Y')): ?>
                                                <span class="badge bg-primary ms-1">Aktuell</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $year['readings_count'] ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?= number_format($year['total_consumption'], 1) ?> kWh
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-warning"><?= number_format($year['total_cost'], 2) ?> €</strong>
                                        </td>
                                        <td><?= number_format($year['avg_consumption'], 1) ?> kWh</td>
                                        <td>
                                            <small class="text-muted">
                                                <?= number_format($year['min_consumption'], 1) ?> - 
                                                <?= number_format($year['max_consumption'], 1) ?> kWh
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<!-- JavaScript für Charts -->
<script>
// ✅ IMMER alle Variablen definieren - verhindert JavaScript-Fehler
const chartData = <?= json_encode($chartData) ?> || [];
const yearlyData = <?= json_encode($yearlyComparison) ?> || [];
const tasmotaData = <?= json_encode($tasmotaMonthlyData) ?> || {};

// Chart-Konfiguration mit Energy-Theme
if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = 'rgb(75, 85, 99)';
}

// Farben für konsistentes Design
const colors = {
    energy: 'rgb(245, 158, 11)',
    energyLight: 'rgba(245, 158, 11, 0.1)',
    success: 'rgb(16, 185, 129)',
    successLight: 'rgba(16, 185, 129, 0.1)',
    primary: 'rgb(59, 130, 246)',
    primaryLight: 'rgba(59, 130, 246, 0.1)',
    warning: 'rgb(249, 115, 22)',
    warningLight: 'rgba(249, 115, 22, 0.1)',
    gray: 'rgb(156, 163, 175)'
};

document.addEventListener('DOMContentLoaded', function() {
    // Prüfe Chart.js Verfügbarkeit
    if (typeof Chart === 'undefined') {
        console.error('Chart.js ist nicht verfügbar');
        return;
    }
        // 1. Verbrauchsverlauf Chart
        if (chartData && chartData.length > 0) {
            const consumptionCanvas = document.getElementById('consumptionChart');
            if (consumptionCanvas) {
                const consumptionCtx = consumptionCanvas.getContext('2d');
                new Chart(consumptionCtx, {
                    type: 'line',
                    data: {
                        labels: chartData.map(item => item.month_label || 'N/A'),
                        datasets: [{
                            label: 'Verbrauch (kWh)',
                            data: chartData.map(item => parseFloat(item.consumption) || 0),
                            borderColor: colors.energy,
                            backgroundColor: colors.energyLight,
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: colors.energy,
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        devicePixelRatio: 1,
                        layout: {
                            padding: {
                                top: 10,
                                right: 10,
                                bottom: 10,
                                left: 10
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: colors.energy,
                                borderWidth: 1,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        return `Verbrauch: ${context.parsed.y.toFixed(1)} kWh`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0, 0, 0, 0.1)' },
                                ticks: {
                                    callback: function(value) {
                                        return value + ' kWh';
                                    }
                                }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            }
        }

        // 2. Jahresvergleich Chart (Doughnut)
        if (yearlyData && yearlyData.length > 0) {
            const yearCanvas = document.getElementById('yearComparisonChart');
            if (yearCanvas) {
                const yearCtx = yearCanvas.getContext('2d');
                const chartColors = [colors.energy, colors.primary, colors.success, colors.warning, colors.gray];
                
                new Chart(yearCtx, {
                    type: 'doughnut',
                    data: {
                        labels: yearlyData.map(item => item.year.toString()),
                        datasets: [{
                            data: yearlyData.map(item => parseFloat(item.total_consumption) || 0),
                            backgroundColor: chartColors.slice(0, yearlyData.length),
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
                                labels: { padding: 20, usePointStyle: true }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return `${context.label}: ${value.toFixed(1)} kWh (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // 3. Kostenentwicklung Chart
        if (chartData && chartData.length > 0) {
            const costCanvas = document.getElementById('costChart');
            if (costCanvas) {
                const costCtx = costCanvas.getContext('2d');
                new Chart(costCtx, {
                    type: 'bar',
                    data: {
                        labels: chartData.map(item => item.month_label || 'N/A'),
                        datasets: [{
                            label: 'Kosten (€)',
                            data: chartData.map(item => parseFloat(item.cost) || 0),
                            backgroundColor: colors.successLight,
                            borderColor: colors.success,
                            borderWidth: 2,
                            borderRadius: 4,
                            borderSkipped: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: colors.success,
                                borderWidth: 1,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        return `Kosten: ${context.parsed.y.toFixed(2)} €`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0, 0, 0, 0.1)' },
                                ticks: {
                                    callback: function(value) {
                                        return value + ' €';
                                    }
                                }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            }
        }
        
        // 4. Smart-Geräte Balkendiagramme
        if (tasmotaData && Object.keys(tasmotaData).length > 0) {
            Object.entries(tasmotaData).forEach(([deviceId, deviceInfo]) => {
                const canvasId = `tasmotaChart_${deviceId}`;
                const canvas = document.getElementById(canvasId);
                
                if (canvas && deviceInfo.data && deviceInfo.data.length > 0) {
                    const ctx = canvas.getContext('2d');
                    
                    // Farben für bessere Unterscheidung zwischen Geräten
                    const deviceColors = [
                        { bg: 'rgba(16, 185, 129, 0.8)', border: 'rgb(16, 185, 129)' },   // Grün
                        { bg: 'rgba(59, 130, 246, 0.8)', border: 'rgb(59, 130, 246)' },   // Blau
                        { bg: 'rgba(245, 158, 11, 0.8)', border: 'rgb(245, 158, 11)' },   // Orange
                        { bg: 'rgba(239, 68, 68, 0.8)', border: 'rgb(239, 68, 68)' },     // Rot
                        { bg: 'rgba(139, 92, 246, 0.8)', border: 'rgb(139, 92, 246)' }    // Lila
                    ];
                    
                    const colorIndex = parseInt(deviceId) % deviceColors.length;
                    const color = deviceColors[colorIndex];
                    
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: deviceInfo.data.map(item => item.month_label || 'N/A'),
                            datasets: [{
                                label: `${deviceInfo.device.name} (kWh)`,
                                // Begrenze nur extrem unrealistische Werte
                                data: deviceInfo.data.map(item => {
                                    let value = parseFloat(item.total_kwh) || 0;
                                    // Nur bei wirklich extremen Werten eingreifen (>500 kWh/Monat)
                                    if (value > 500) {
                                        value = 50; // Fallback-Wert
                                    }
                                    return value;
                                }),
                                backgroundColor: color.bg,
                                borderColor: color.border,
                                borderWidth: 2,
                                borderRadius: 4,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: '#ffffff',
                                    bodyColor: '#ffffff',
                                    borderColor: color.border,
                                    borderWidth: 1,
                                    cornerRadius: 8,
                                    callbacks: {
                                        title: function(tooltipItems) {
                                            return tooltipItems[0].label;
                                        },
                                        label: function(context) {
                                            const value = context.parsed.y;
                                            return `Verbrauch: ${value.toFixed(2)} kWh`;
                                        },
                                        afterLabel: function(context) {
                                            const estimatedCost = context.parsed.y * 0.30;
                                            return `Geschätzte Kosten: ${estimatedCost.toFixed(2)} €`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: 'rgba(0, 0, 0, 0.1)' },
                                    ticks: {
                                        callback: function(value) {
                                            return value.toFixed(1) + ' kWh';
                                        },
                                        font: { size: 11 }
                                    }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 0,
                                        font: { size: 10 }
                                    }
                                }
                            },
                            animation: {
                                duration: 1000,
                                easing: 'easeInOutQuart'
                            }
                        }
                    });
                    }
                    });
                    }
});

// Utility Functions
function refreshCharts() {
    window.location.reload();
}

function exportCharts() {
    alert('Export-Funktion wird in einer zukünftigen Version implementiert.');
}

// Print-optimierte Styles
window.addEventListener('beforeprint', function() {
    if (typeof Chart !== 'undefined' && Chart.helpers) {
        Chart.helpers.each(Chart.instances, function(chart) {
            chart.resize();
        });
    }
});
</script>
