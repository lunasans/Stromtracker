<?php
// dashboard.php
// EINFACHES & SCHÖNES Dashboard für Stromtracker

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Dashboard - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// Statistiken (vereinfacht für Demo)
$stats = [
    'current_month_consumption' => 450.5,
    'current_month_cost' => 144.16,
    'year_consumption' => 4205.3,
    'year_cost' => 1345.70,
    'total_devices' => 12,
    'total_readings' => 8
];

// Trend-Daten für Sparklines
$trendData = [420, 435, 445, 450, 465, 450, 445];
$costTrend = [134.40, 139.20, 142.40, 144.16, 148.80, 144.16, 142.40];

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
                        <p class="lead text-muted mb-0">
                            Hier ist Ihre aktuelle Stromverbrauch-Übersicht.
                        </p>
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
                        <i class="bi bi-arrow-up-short"></i>
                        <span>+12%</span>
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
                        <i class="bi bi-arrow-up-short"></i>
                        <span>+8%</span>
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
                        <i class="bi bi-arrow-down-short"></i>
                        <span>-3%</span>
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
                        <i class="bi bi-arrow-up-short"></i>
                        <span>+2</span>
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
                                <small>Verwalten</small>
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
                        <button class="btn btn-outline-primary w-100 p-4" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="bi bi-download mb-2 d-block" style="font-size: 1.5rem;"></i>
                            <div>
                                <div class="fw-bold">Export</div>
                                <small>Daten sichern</small>
                            </div>
                        </button>
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
                        Verbrauchstrend
                    </h5>

                    <!-- Chart Controls -->
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary active" data-period="7d">7T</button>
                        <button class="btn btn-outline-primary" data-period="30d">30T</button>
                        <button class="btn btn-outline-primary" data-period="12m">12M</button>
                    </div>
                </div>

                <!-- Chart Canvas -->
                <div style="position: relative; height: 300px;">
                    <canvas id="consumptionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            
            <!-- Aktuelle Werte -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="card-title flex-center gap-2">
                        <i class="bi bi-speedometer2 text-energy"></i>
                        Live-Daten
                    </h6>
                    
                    <div class="mb-3">
                        <div class="flex-between">
                            <span class="text-muted">Aktueller Verbrauch</span>
                            <strong class="text-energy">2.4 kW</strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="flex-between">
                            <span class="text-muted">Heute bisher</span>
                            <strong>18.7 kWh</strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="flex-between">
                            <span class="text-muted">Kosten heute</span>
                            <strong class="text-warning">5.99 €</strong>
                        </div>
                    </div>
                    
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-energy" role="progressbar" style="width: 65%"></div>
                    </div>
                    <small class="text-muted">65% des Tagesziels erreicht</small>
                </div>
            </div>

            <!-- Top Geräte -->
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title flex-center gap-2">
                        <i class="bi bi-star text-warning"></i>
                        Top Stromverbraucher
                    </h6>
                    
                    <div class="mb-3">
                        <div class="flex-between mb-1">
                            <span>Waschmaschine</span>
                            <span class="text-danger">3.2 kWh</span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-danger" style="width: 80%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="flex-between mb-1">
                            <span>Kühlschrank</span>
                            <span class="text-warning">2.1 kWh</span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-warning" style="width: 52%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="flex-between mb-1">
                            <span>Fernseher</span>
                            <span class="text-success">0.8 kWh</span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-success" style="width: 20%"></div>
                        </div>
                    </div>
                    
                    <a href="geraete.php" class="btn btn-outline-primary btn-sm w-100 mt-3">
                        Alle Geräte anzeigen
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-download text-energy"></i>
                    Daten exportieren
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Welche Daten möchten Sie exportieren?</p>
                <div class="list-group">
                    <a href="export.php?type=consumption" class="list-group-item list-group-item-action">
                        <i class="bi bi-lightning-charge"></i> Verbrauchsdaten (CSV)
                    </a>
                    <a href="export.php?type=costs" class="list-group-item list-group-item-action">
                        <i class="bi bi-currency-euro"></i> Kostendaten (CSV)
                    </a>
                    <a href="export.php?type=devices" class="list-group-item list-group-item-action">
                        <i class="bi bi-cpu"></i> Geräteliste (CSV)
                    </a>
                    <a href="export.php?type=all" class="list-group-item list-group-item-action">
                        <i class="bi bi-archive"></i> Vollständiger Export (ZIP)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Chart.js Setup -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verbrauchstrend Chart
    const ctx = document.getElementById('consumptionChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
            datasets: [{
                label: 'Verbrauch (kWh)',
                data: <?= json_encode($trendData) ?>,
                borderColor: 'rgb(245, 158, 11)',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                tension: 0.4,
                fill: true
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

    // Chart Period Buttons
    document.querySelectorAll('[data-period]').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            document.querySelectorAll('[data-period]').forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            // Here you would typically load new data
            console.log('Loading data for period:', this.dataset.period);
        });
    });

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
});
</script>