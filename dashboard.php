<?php
// dashboard-enhanced.php
// Enhanced Dashboard mit verbessertem Design System (Beispiel)

require_once 'config/database.php';
require_once 'config/session.php';

// Login erforderlich
Auth::requireLogin();

$pageTitle = 'Enhanced Dashboard - Stromtracker';
$user = Auth::getUser();
$userId = Auth::getUserId();

// Page Configuration für Enhanced Features
$pageConfig = [
    'enableGlassmorphism' => true,
    'enableAnimations' => true,
    'showThemeToggle' => true,
    'animationLevel' => 'enhanced'
];

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

include 'includes/header-modern.php';
include 'includes/navbar.php';
?>

<!-- Main Content mit Enhanced Design -->
<div class="container-fluid py-4" id="main-content">
    
    <!-- Enhanced Welcome Header -->
    <div class="row mb-4" data-animate="slide-in-up">
        <div class="col-12">
            <div class="glass-light" style="border-radius: var(--radius-3xl); padding: var(--space-8); position: relative; overflow: hidden;">
                <!-- Background Pattern -->
                <div style="position: absolute; top: 0; right: 0; width: 200px; height: 200px; opacity: 0.1; background: radial-gradient(circle, var(--energy-400) 0%, transparent 50%); border-radius: 50%;"></div>
                
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="text-gradient mb-3">
                            <span class="energy-indicator active"></span>
                            Willkommen zurück, <?= escape($user['name'] ?? explode('@', $user['email'])[0]) ?>!
                        </h1>
                        <p class="lead" style="color: var(--neutral-600); margin-bottom: var(--space-4);">
                            Hier ist Ihre aktuelle Stromverbrauch-Übersicht mit Enhanced Design System.
                        </p>
                        
                        <!-- Quick Stats Inline -->
                        <div class="d-flex gap-4 flex-wrap">
                            <div class="d-flex align-items-center gap-2">
                                <div class="energy-indicator success"></div>
                                <span class="text-sm">System aktiv</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-clock text-success"></i>
                                <span class="text-sm">Letztes Update: <?= date('H:i') ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-shield-check text-info"></i>
                                <span class="text-sm">Sicher verbunden</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 text-end">
                        <div class="d-flex justify-content-end align-items-center gap-3">
                            <!-- Theme Info -->
                            <div class="text-center">
                                <div class="h4 text-gradient mb-1"><?= date('H:i') ?></div>
                                <div class="text-sm" style="color: var(--neutral-500);">
                                    <?= strftime('%A, %d. %B %Y') ?>
                                </div>
                            </div>
                            
                            <!-- Enhanced Energy Indicator -->
                            <div class="position-relative">
                                <div class="energy-indicator" style="width: 40px; height: 40px; margin: 0;"></div>
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: var(--text-xs); font-weight: var(--font-bold); color: white;">
                                    ⚡
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Stats Cards Grid -->
    <div class="row mb-4 animate-stagger" data-stagger-delay="150">
        
        <!-- Aktueller Monat - Verbrauch -->
        <div class="col-md-3 mb-3">
            <div class="stats-card-enhanced success" data-click-feedback>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stats-icon" style="color: var(--success-600); opacity: 0.8;">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <div class="stats-trend positive">
                        <i class="bi bi-arrow-up-short"></i>
                        <span>+12%</span>
                    </div>
                </div>
                
                <div class="stats-value" data-countup="<?= $stats['current_month_consumption'] ?>" data-decimals="1">
                    0
                </div>
                <div class="stats-label">kWh aktueller Monat</div>
                
                <!-- Mini Sparkline -->
                <div style="margin-top: var(--space-3); height: 20px;">
                    <canvas id="sparklineConsumption" width="150" height="20"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Aktueller Monat - Kosten -->
        <div class="col-md-3 mb-3">
            <div class="stats-card-enhanced warning" data-click-feedback>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stats-icon" style="color: var(--warning-600); opacity: 0.8;">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div class="stats-trend positive">
                        <i class="bi bi-arrow-up-short"></i>
                        <span>+8%</span>
                    </div>
                </div>
                
                <div class="stats-value" data-countup="<?= $stats['current_month_cost'] ?>" data-decimals="2">
                    0
                </div>
                <div class="stats-label">€ Kosten Monat</div>
                
                <!-- Mini Sparkline -->
                <div style="margin-top: var(--space-3); height: 20px;">
                    <canvas id="sparklineCost" width="150" height="20"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Jahr - Verbrauch -->
        <div class="col-md-3 mb-3">
            <div class="stats-card-enhanced info" data-click-feedback>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stats-icon" style="color: var(--info-600); opacity: 0.8;">
                        <i class="bi bi-calendar-year"></i>
                    </div>
                    <div class="stats-trend neutral">
                        <i class="bi bi-dash"></i>
                        <span>±0%</span>
                    </div>
                </div>
                
                <div class="stats-value" data-countup="<?= $stats['year_consumption'] ?>" data-decimals="1">
                    0
                </div>
                <div class="stats-label">kWh Jahr <?= date('Y') ?></div>
                
                <!-- Progress Ring -->
                <div style="margin-top: var(--space-3);">
                    <div style="width: 40px; height: 40px; position: relative;">
                        <svg width="40" height="40">
                            <circle cx="20" cy="20" r="16" stroke="var(--info-200)" stroke-width="3" fill="none"></circle>
                            <circle cx="20" cy="20" r="16" stroke="var(--info-500)" stroke-width="3" fill="none" 
                                    stroke-dasharray="100" stroke-dashoffset="25" 
                                    style="transform: rotate(-90deg); transform-origin: 20px 20px;"></circle>
                        </svg>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 10px; font-weight: bold; color: var(--info-600);">
                            75%
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Geräte -->
        <div class="col-md-3 mb-3">
            <div class="stats-card-enhanced" data-click-feedback>
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stats-icon" style="color: var(--energy-600); opacity: 0.8;">
                        <i class="bi bi-cpu"></i>
                    </div>
                    <div class="stats-trend positive">
                        <i class="bi bi-arrow-up-short"></i>
                        <span>+2</span>
                    </div>
                </div>
                
                <div class="stats-value" data-countup="<?= $stats['total_devices'] ?>">
                    0
                </div>
                <div class="stats-label">Registrierte Geräte</div>
                
                <!-- Device Icons -->
                <div style="margin-top: var(--space-3); display: flex; gap: var(--space-1);">
                    <i class="bi bi-tv" style="color: var(--neutral-400);"></i>
                    <i class="bi bi-laptop" style="color: var(--neutral-400);"></i>
                    <i class="bi bi-phone" style="color: var(--neutral-400);"></i>
                    <i class="bi bi-lightbulb" style="color: var(--energy-500);"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Quick Actions -->
    <div class="row mb-4" data-animate="fade-in">
        <div class="col-12">
            <div class="glass-light" style="border-radius: var(--radius-2xl); padding: var(--space-6);">
                <h5 class="mb-4 d-flex align-items-center gap-2">
                    <div class="energy-indicator"></div>
                    Schnellaktionen
                </h5>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="zaehlerstand.php" class="btn-enhanced success w-100" style="text-decoration: none; padding: var(--space-4);">
                            <i class="bi bi-plus-circle"></i>
                            <div>
                                <div style="font-weight: var(--font-semibold);">Zählerstand</div>
                                <small style="opacity: 0.8;">Neue Ablesung</small>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <a href="geraete.php" class="btn-enhanced info w-100" style="text-decoration: none; padding: var(--space-4);">
                            <i class="bi bi-cpu"></i>
                            <div>
                                <div style="font-weight: var(--font-semibold);">Geräte</div>
                                <small style="opacity: 0.8;">Verwalten</small>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <a href="auswertung.php" class="btn-enhanced primary w-100" style="text-decoration: none; padding: var(--space-4);">
                            <i class="bi bi-bar-chart"></i>
                            <div>
                                <div style="font-weight: var(--font-semibold);">Auswertung</div>
                                <small style="opacity: 0.8;">Charts & Trends</small>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <button class="btn-enhanced w-100" style="padding: var(--space-4);" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="bi bi-download"></i>
                            <div>
                                <div style="font-weight: var(--font-semibold);">Export</div>
                                <small style="opacity: 0.8;">Daten sichern</small>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Content Grid -->
    <div class="row">
        
        <!-- Hauptchart -->
        <div class="col-md-8 mb-4" data-animate="slide-in-left">
            <div class="glass-light" style="border-radius: var(--radius-2xl); padding: var(--space-6); height: 400px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0 d-flex align-items-center gap-2">
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
                
                <div style="position: relative; height: 300px;">
                    <canvas id="mainChart" style="max-height: 300px;"></canvas>
                    
                    <!-- Loading State -->
                    <div class="loading-skeleton" id="chartLoading" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; border-radius: var(--radius-lg);">
                        <div style="height: 100%; display: flex; align-items: center; justify-content: center; color: var(--neutral-500);">
                            <div class="text-center">
                                <div class="loading-skeleton" style="width: 200px; height: 20px; margin: 0 auto 10px;"></div>
                                <div class="loading-skeleton" style="width: 150px; height: 15px; margin: 0 auto;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Content -->
        <div class="col-md-4">
            
            <!-- System Status -->
            <div class="glass-light mb-4" style="border-radius: var(--radius-2xl); padding: var(--space-6);" data-animate="slide-in-right">
                <h6 class="mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-success"></i>
                    System Status
                </h6>
                
                <div class="space-y-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size: var(--text-sm);">Datenbankverbindung</span>
                        <div class="d-flex align-items-center gap-1">
                            <div class="energy-indicator success" style="width: 8px; height: 8px; margin: 0;"></div>
                            <span style="font-size: var(--text-xs); color: var(--success-600); font-weight: var(--font-semibold);">Aktiv</span>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size: var(--text-sm);">Session</span>
                        <div class="d-flex align-items-center gap-1">
                            <div class="energy-indicator success" style="width: 8px; height: 8px; margin: 0;"></div>
                            <span style="font-size: var(--text-xs); color: var(--success-600); font-weight: var(--font-semibold);">Gültig</span>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size: var(--text-sm);">Design System</span>
                        <div class="d-flex align-items-center gap-1">
                            <div class="energy-indicator" style="width: 8px; height: 8px; margin: 0;"></div>
                            <span style="font-size: var(--text-xs); color: var(--energy-600); font-weight: var(--font-semibold);">Enhanced</span>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size: var(--text-sm);">Performance</span>
                        <span style="font-size: var(--text-xs); color: var(--info-600); font-weight: var(--font-semibold);" id="performanceMetric">
                            Wird gemessen...
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Enhanced Tips -->
            <div class="glass-light" style="border-radius: var(--radius-2xl); padding: var(--space-6);" data-animate="slide-in-right">
                <h6 class="mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-lightbulb text-warning"></i>
                    Energiespar-Tipps
                </h6>
                
                <div class="space-y-3">
                    <div class="d-flex gap-3" style="padding: var(--space-3); background: var(--success-50); border-radius: var(--radius-lg); border-left: 4px solid var(--success-500);">
                        <div style="flex-shrink: 0;">💡</div>
                        <div>
                            <div style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--success-700);">
                                LED-Lampen verwenden
                            </div>
                            <div style="font-size: var(--text-xs); color: var(--success-600);">
                                Bis zu 80% weniger Stromverbrauch
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3" style="padding: var(--space-3); background: var(--info-50); border-radius: var(--radius-lg); border-left: 4px solid var(--info-500);">
                        <div style="flex-shrink: 0;">🔌</div>
                        <div>
                            <div style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--info-700);">
                                Standby-Verbrauch reduzieren
                            </div>
                            <div style="font-size: var(--text-xs); color: var(--info-600);">
                                Geräte komplett ausschalten
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3" style="padding: var(--space-3); background: var(--warning-50); border-radius: var(--radius-lg); border-left: 4px solid var(--warning-500);">
                        <div style="flex-shrink: 0;">🌡️</div>
                        <div>
                            <div style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--warning-700);">
                                Heizung optimieren
                            </div>
                            <div style="font-size: var(--text-xs); color: var(--warning-600);">
                                1°C weniger = 6% Ersparnis
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Scripts -->
<script>
// Enhanced Dashboard Scripts
document.addEventListener('DOMContentLoaded', function() {
    
    // Performance Metric Display
    setTimeout(() => {
        const totalTime = performance.now() - window.performanceMetrics.startTime;
        const performanceEl = document.getElementById('performanceMetric');
        if (performanceEl) {
            performanceEl.textContent = Math.round(totalTime) + 'ms';
            
            // Performance Färbung
            if (totalTime < 1000) {
                performanceEl.style.color = 'var(--success-600)';
            } else if (totalTime < 2000) {
                performanceEl.style.color = 'var(--warning-600)';
            } else {
                performanceEl.style.color = 'var(--danger-600)';
            }
        }
    }, 1000);
    
    // Enhanced Chart Setup
    setupEnhancedCharts();
    
    // Enhanced Interactions
    setupEnhancedInteractions();
});

function setupEnhancedCharts() {
    // Sparklines
    const sparklineData = <?= json_encode($trendData) ?>;
    const costSparklineData = <?= json_encode($costTrend) ?>;
    
    // Consumption Sparkline
    const consumptionCtx = document.getElementById('sparklineConsumption');
    if (consumptionCtx) {
        new Chart(consumptionCtx, {
            type: 'line',
            data: {
                labels: ['', '', '', '', '', '', ''],
                datasets: [{
                    data: sparklineData,
                    borderColor: 'var(--success-500)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: { point: { hoverRadius: 0 } }
            }
        });
    }
    
    // Cost Sparkline
    const costCtx = document.getElementById('sparklineCost');
    if (costCtx) {
        new Chart(costCtx, {
            type: 'line',
            data: {
                labels: ['', '', '', '', '', '', ''],
                datasets: [{
                    data: costSparklineData,
                    borderColor: 'var(--warning-500)',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: { point: { hoverRadius: 0 } }
            }
        });
    }
    
    // Main Chart (Enhanced)
    setupMainChart();
}

function setupMainChart() {
    const ctx = document.getElementById('mainChart');
    const loading = document.getElementById('chartLoading');
    
    if (ctx) {
        // Loading State simulieren
        setTimeout(() => {
            if (loading) loading.style.display = 'none';
            
            const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.05)');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul'],
                    datasets: [{
                        label: 'Verbrauch (kWh)',
                        data: [380, 420, 445, 410, 465, 450, 445],
                        borderColor: 'var(--info-500)',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: 'var(--info-500)',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 3,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: { color: 'var(--neutral-600)' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: 'var(--neutral-600)' }
                        }
                    }
                }
            });
        }, 800);
    }
}

function setupEnhancedInteractions() {
    // Chart Period Buttons
    document.querySelectorAll('[data-period]').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active from all
            document.querySelectorAll('[data-period]').forEach(b => b.classList.remove('active'));
            // Add active to clicked
            this.classList.add('active');
            
            // Enhanced Feedback
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });
}
</script>

<?php include 'includes/footer-modern.php'; ?>