<?php
// dashboard.php
// Hauptdashboard nach Login

require_once 'config/database.php';
require_once 'config/session.php';

$pageConfig = [
    'enableGlassmorphism' => true,
    'enableAnimations' => true,
    'showThemeToggle' => true
];

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Dashboard - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// Statistiken abrufen (sicher mit Fallbacks)
$stats = [
    'total_devices' => Database::fetchSingle(
        "SELECT COUNT(*) as count FROM devices WHERE user_id = ? AND is_active = 1", 
        [$userId], 'count', 0
    ),
    
    'total_readings' => Database::fetchSingle(
        "SELECT COUNT(*) as count FROM meter_readings WHERE user_id = ?", 
        [$userId], 'count', 0
    ),
    
    'current_month_consumption' => Database::fetchSingle(
        "SELECT COALESCE(consumption, 0) as total FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE()) AND MONTH(reading_date) = MONTH(CURDATE())", 
        [$userId], 'total', 0
    ),
    
    'current_month_cost' => Database::fetchSingle(
        "SELECT COALESCE(cost, 0) as total FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE()) AND MONTH(reading_date) = MONTH(CURDATE())", 
        [$userId], 'total', 0
    ),
    
    'year_consumption' => Database::fetchSingle(
        "SELECT COALESCE(SUM(consumption), 0) as total FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE())", 
        [$userId], 'total', 0
    ),
    
    'year_cost' => Database::fetchSingle(
        "SELECT COALESCE(SUM(cost), 0) as total FROM meter_readings 
         WHERE user_id = ? AND YEAR(reading_date) = YEAR(CURDATE())", 
        [$userId], 'total', 0
    )
];

// Letzte Zählerstände (sicher)
$recentReadings = Database::fetchAll(
    "SELECT * FROM meter_readings 
     WHERE user_id = ? 
     ORDER BY reading_date DESC 
     LIMIT 5", 
    [$userId]
) ?: []; // Fallback zu leerem Array

# include 'includes/header.php';
include 'includes/header-modern.php';
include 'includes/navbar.php';
?>

<div class="container-fluid py-4">
    
    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="card-title mb-2">
                                <i class="bi bi-house-heart"></i>
                                Willkommen, <?= escape($user['name'] ?? $user['email']) ?>!
                            </h1>
                            <p class="card-text">
                                Verwalten Sie Ihren Stromverbrauch effizient und behalten Sie Ihre Energiekosten im Blick.
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end align-items-center">
                                <span class="energy-indicator me-2"></span>
                                <div>
                                    <div class="h5 mb-0"><?= date('H:i') ?></div>
                                    <small><?= date('d.m.Y') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistik Cards -->
    <div class="row mb-4">
        
        <!-- Aktueller Monat - Verbrauch -->
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= formatKwh($stats['current_month_consumption']) ?></h4>
                            <p class="card-text">Aktueller Monat</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Aktueller Monat - Kosten -->
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= formatCurrency($stats['current_month_cost']) ?></h4>
                            <p class="card-text">Kosten Monat</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Jahr - Verbrauch -->
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= formatKwh($stats['year_consumption']) ?></h4>
                            <p class="card-text">Jahr <?= date('Y') ?></p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Zählerstände -->
        <div class="col-md-3 mb-3">
            <div class="card stats-card text-white" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?= $stats['total_readings'] ?></h4>
                            <p class="card-text">Zählerstände</p>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hauptinhalt -->
    <div class="row">
        
        <!-- Linke Spalte -->
        <div class="col-md-8">
            
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-fill text-warning"></i>
                        Schnellaktionen
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="zaehlerstand.php" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle"></i><br>
                                <small>Zählerstand erfassen</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="geraete.php" class="btn btn-primary w-100">
                                <i class="bi bi-cpu"></i><br>
                                <small>Geräte verwalten</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="auswertung.php" class="btn btn-energy w-100">
                                <i class="bi bi-bar-chart"></i><br>
                                <small>Auswertung</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button class="btn btn-secondary w-100" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="bi bi-download"></i><br>
                                <small>Daten exportieren</small>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Letzte Zählerstände -->
            <div class="card glass-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-speedometer2"></i>
                        Letzte Zählerstände
                    </h5>
                    <a href="zaehlerstand.php" class="btn btn-sm btn-outline-primary">
                        Alle anzeigen
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentReadings)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-speedometer2 display-4 text-muted"></i>
                            <p class="text-muted mt-2">Noch keine Zählerstände erfasst.</p>
                            <a href="zaehlerstand.php" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Ersten Zählerstand erfassen
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Datum</th>
                                        <th>Zählerstand</th>
                                        <th>Verbrauch</th>
                                        <th>Kosten</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReadings as $reading): ?>
                                        <tr>
                                            <td>
                                                <?= formatDateShort($reading['reading_date']) ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?= date('F Y', strtotime($reading['reading_date'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= number_format($reading['meter_value'], 1, ',', '.') ?> kWh
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
                                                    <strong><?= formatCurrency($reading['cost']) ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
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
        
        <!-- Rechte Spalte -->
        <div class="col-md-4">
            
            <!-- Monatliche Entwicklung -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up text-success"></i>
                        Verbrauchstrend
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $monthlyData = Database::fetchAll(
                        "SELECT 
                            DATE_FORMAT(reading_date, '%Y-%m') as month,
                            consumption,
                            cost
                         FROM meter_readings 
                         WHERE user_id = ? AND consumption IS NOT NULL
                         ORDER BY reading_date DESC 
                         LIMIT 6", 
                        [$userId]
                    ) ?: []; // Fallback zu leerem Array
                    ?>
                    
                    <?php if (empty($monthlyData)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-graph-up display-6 text-muted"></i>
                            <p class="text-muted mt-2">Erfassen Sie mehrere Zählerstände, um Trends zu sehen.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach (array_reverse($monthlyData) as $data): ?>
                                <div class="col-md-2 text-center mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body p-2">
                                            <small class="text-muted"><?= date('M Y', strtotime($data['month'] . '-01')) ?></small>
                                            <div class="h6 mb-1"><?= formatKwh($data['consumption']) ?></div>
                                            <small class="text-success"><?= formatCurrency($data['cost']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (count($monthlyData) >= 2): ?>
                            <?php 
                            $current = $monthlyData[0];
                            $previous = $monthlyData[1];
                            $difference = $current['consumption'] - $previous['consumption'];
                            $percentage = $previous['consumption'] > 0 ? ($difference / $previous['consumption']) * 100 : 0;
                            ?>
                            <div class="alert <?= $difference > 0 ? 'alert-warning' : 'alert-success' ?> mt-3">
                                <i class="bi bi-<?= $difference > 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <strong>Trend:</strong> 
                                <?= $difference > 0 ? '+' : '' ?><?= formatKwh($difference) ?> 
                                (<?= $difference > 0 ? '+' : '' ?><?= number_format($percentage, 1) ?>%) 
                                gegenüber dem Vormonat
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="card glass-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-shield-check text-success"></i>
                        System Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Datenbankverbindung</span>
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle"></i> Aktiv
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Session</span>
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle"></i> Gültig
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>PHP Version</span>
                        <span class="badge bg-info"><?= PHP_VERSION ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Letzter Login</span>
                        <span class="badge bg-secondary"><?= date('H:i') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer-modern.php'; ?>