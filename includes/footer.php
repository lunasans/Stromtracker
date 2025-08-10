<?php
// includes/footer-enhanced.php
// Enhanced Footer mit verbessertem Design System und Performance
?>

<!-- Enhanced Footer -->
<footer class="enhanced-footer" role="contentinfo">
    <div class="container-fluid">
        
        <!-- Main Footer Content -->
        <div class="footer-main">
            <div class="row">
                
                <!-- Brand Section -->
                <div class="col-md-4 mb-4" data-animate="slide-in-left">
                    <div class="footer-brand">
                        <h5 class="brand-title">
                            <span class="energy-indicator"></span>
                            <i class="bi bi-lightning-charge text-gradient"></i> 
                            Stromtracker
                        </h5>
                        <p class="brand-description">
                            Moderne Stromverbrauchsverwaltung mit Enhanced Design System. 
                            Effizient, übersichtlich und nachhaltig.
                        </p>
                        
                        <!-- Enhanced Version Info -->
                        <div class="version-info">
                            <div class="version-badge">
                                <span class="version-label">Version</span>
                                <span class="version-number">2.0</span>
                                <span class="version-tag">Enhanced</span>
                            </div>
                            <div class="build-info">
                                Build <?= date('Ymd') ?> • PHP <?= PHP_VERSION ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation Links -->
                <div class="col-md-2 mb-4" data-animate="slide-in-up">
                    <h6 class="footer-heading">Navigation</h6>
                    <ul class="footer-nav">
                        <li><a href="dashboard.php" class="footer-link">
                            <i class="bi bi-house-door"></i>Dashboard
                        </a></li>
                        <li><a href="zaehlerstand.php" class="footer-link">
                            <i class="bi bi-speedometer2"></i>Zählerstand
                        </a></li>
                        <li><a href="geraete.php" class="footer-link">
                            <i class="bi bi-cpu"></i>Geräte
                        </a></li>
                        <li><a href="auswertung.php" class="footer-link">
                            <i class="bi bi-bar-chart"></i>Auswertung
                        </a></li>
                        <li><a href="tarife.php" class="footer-link">
                            <i class="bi bi-receipt"></i>Tarife
                        </a></li>
                    </ul>
                </div>
                
                <!-- System Status -->
                <div class="col-md-3 mb-4" data-animate="slide-in-up">
                    <h6 class="footer-heading">System Status</h6>
                    <ul class="status-list">
                        <li class="status-item">
                            <div class="status-indicator success"></div>
                            <span class="status-label">Database</span>
                            <span class="status-value">Online</span>
                        </li>
                        <li class="status-item">
                            <div class="status-indicator success"></div>
                            <span class="status-label">Session</span>
                            <span class="status-value">Active</span>
                        </li>
                        <li class="status-item">
                            <div class="status-indicator energy"></div>
                            <span class="status-label">Design System</span>
                            <span class="status-value">Enhanced</span>
                        </li>
                        <li class="status-item">
                            <div class="status-indicator info"></div>
                            <span class="status-label">Performance</span>
                            <span class="status-value" id="footerPerformance">Measuring...</span>
                        </li>
                    </ul>
                </div>
                               
                    
                    <!-- Quick Tools -->
                    <div class="quick-tools">
                        <button class="tool-btn" onclick="scrollToTop()" title="Nach oben">
                            <i class="bi bi-arrow-up"></i>
                        </button>
                        <button class="tool-btn" onclick="toggleTheme()" title="Theme wechseln">
                            <i class="bi bi-palette"></i>
                        </button>
                        <button class="tool-btn" onclick="showKeyboardShortcuts()" title="Tastenkürzel">
                            <i class="bi bi-keyboard"></i>
                        </button>
                        <button class="tool-btn" onclick="printPage()" title="Seite drucken">
                            <i class="bi bi-printer"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="copyright">
                        <span class="copyright-text">
                            © <?= date('Y') ?> Stromtracker - Enhanced Design System
                        </span>
                        <div class="tech-stack">
                            <span class="tech-item">
                                <i class="bi bi-bootstrap"></i>Bootstrap 5.3
                            </span>
                            <span class="tech-item">
                                <i class="bi bi-filetype-php"></i>PHP <?= PHP_VERSION ?>
                            </span>
                            <span class="tech-item">
                                <i class="bi bi-database"></i>MySQL
                            </span>
                            <span class="tech-item">
                                <i class="bi bi-lightning"></i>Enhanced
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 text-end">
                    <div class="footer-actions">
                        <!-- Performance Badge -->
                        <div class="performance-badge" id="performanceBadge">
                            <i class="bi bi-speedometer2"></i>
                            <span id="performanceValue">Loading...</span>
                        </div>
                        
                        <!-- Back to Top Enhanced -->
                        <button class="back-to-top-enhanced" id="backToTop" onclick="scrollToTop()">
                            <i class="bi bi-arrow-up"></i>
                            <span class="back-to-top-text">Top</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer Background Pattern -->
    <div class="footer-pattern"></div>
</footer>

<!-- Enhanced Floating Action Button (Mobile) -->
<div class="fab-container d-block d-lg-none">
    <button class="fab-main" onclick="showQuickActions()" title="Schnellaktionen">
        <i class="bi bi-plus"></i>
    </button>
    
    <!-- Quick Action Menu -->
    <div class="fab-menu" id="fabMenu">
        <button class="fab-item" onclick="window.location.href='zaehlerstand.php'" title="Zählerstand">
            <i class="bi bi-speedometer2"></i>
        </button>
        <button class="fab-item" onclick="window.location.href='geraete.php'" title="Geräte">
            <i class="bi bi-cpu"></i>
        </button>
        <button class="fab-item" onclick="window.location.href='auswertung.php'" title="Charts">
            <i class="bi bi-bar-chart"></i>
        </button>
        <button class="fab-item" onclick="toggleTheme()" title="Theme">
            <i class="bi bi-palette"></i>
        </button>
    </div>
</div>

<!-- Keyboard Shortcuts Modal -->
<div class="modal fade" id="keyboardShortcutsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-light">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-keyboard text-primary"></i>
                    Tastenkürzel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Navigation</h6>
                        <div class="shortcut-list">
                            <div class="shortcut-item">
                                <kbd>Alt</kbd> + <kbd>D</kbd>
                                <span>Dashboard</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>Alt</kbd> + <kbd>Z</kbd>
                                <span>Zählerstand</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>Alt</kbd> + <kbd>G</kbd>
                                <span>Geräte</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>Alt</kbd> + <kbd>A</kbd>
                                <span>Auswertung</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Aktionen</h6>
                        <div class="shortcut-list">
                            <div class="shortcut-item">
                                <kbd>Ctrl</kbd> + <kbd>N</kbd>
                                <span>Neuer Eintrag</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>Ctrl</kbd> + <kbd>T</kbd>
                                <span>Theme wechseln</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>Esc</kbd>
                                <span>Modal schließen</span>
                            </div>
                            <div class="shortcut-item">
                                <kbd>Pos1</kbd>
                                <span>Nach oben</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Footer Styles -->
<style>
/* Enhanced Footer Styles */
.enhanced-footer {
    background: linear-gradient(135deg, var(--neutral-900) 0%, var(--neutral-800) 100%);
    color: var(--neutral-200);
    margin-top: var(--space-20);
    position: relative;
    overflow: hidden;
}

[data-theme="dark"] .enhanced-footer {
    background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
    border-top: 1px solid var(--neutral-700);
}

.footer-pattern {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(251, 191, 36, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(59, 130, 246, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, rgba(16, 185, 129, 0.05) 0%, transparent 50%);
    pointer-events: none;
}

.footer-main {
    padding: var(--space-16) 0 var(--space-8);
    position: relative;
    z-index: 1;
}

.footer-bottom {
    padding: var(--space-6) 0;
    border-top: 1px solid var(--neutral-700);
    position: relative;
    z-index: 1;
}

/* Brand Section */
.footer-brand .brand-title {
    font-family: var(--font-display);
    font-size: var(--text-xl);
    font-weight: var(--font-bold);
    color: var(--neutral-100);
    margin-bottom: var(--space-3);
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.footer-brand .brand-description {
    font-size: var(--text-sm);
    color: var(--neutral-400);
    line-height: var(--leading-relaxed);
    margin-bottom: var(--space-4);
}

.version-info {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.version-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-3);
    background: var(--neutral-800);
    border-radius: var(--radius-lg);
    font-size: var(--text-xs);
    border: 1px solid var(--neutral-700);
}

.version-label {
    color: var(--neutral-400);
}

.version-number {
    color: var(--neutral-100);
    font-weight: var(--font-semibold);
}

.version-tag {
    background: var(--gradient-energy);
    color: white;
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-sm);
    font-weight: var(--font-semibold);
}

.build-info {
    font-size: var(--text-xs);
    color: var(--neutral-500);
}

/* Footer Navigation */
.footer-heading {
    font-size: var(--text-sm);
    font-weight: var(--font-semibold);
    color: var(--neutral-100);
    margin-bottom: var(--space-4);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.footer-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-nav li {
    margin-bottom: var(--space-2);
}

.footer-link {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    color: var(--neutral-400);
    text-decoration: none;
    font-size: var(--text-sm);
    padding: var(--space-1) 0;
    transition: all var(--transition-fast) var(--ease-out);
}

.footer-link:hover {
    color: var(--energy-400);
    transform: translateX(4px);
}

.footer-link i {
    font-size: var(--text-xs);
    opacity: 0.7;
}

/* Status List */
.status-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.status-item {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    margin-bottom: var(--space-2);
    font-size: var(--text-xs);
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: var(--radius-full);
    flex-shrink: 0;
}

.status-indicator.success {
    background: var(--success-500);
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.4);
}

.status-indicator.energy {
    background: var(--energy-500);
    box-shadow: 0 0 8px rgba(251, 191, 36, 0.4);
}

.status-indicator.info {
    background: var(--info-500);
    box-shadow: 0 0 8px rgba(59, 130, 246, 0.4);
}

.status-label {
    color: var(--neutral-400);
    min-width: 80px;
}

.status-value {
    color: var(--neutral-200);
    font-weight: var(--font-medium);
}

/* Live Widget */
.live-widget {
    padding: var(--space-3);
    background: var(--neutral-800);
    border-radius: var(--radius-xl);
    border: 1px solid var(--neutral-700);
    text-align: center;
}

.live-time {
    font-family: var(--font-mono);
    font-size: var(--text-lg);
    font-weight: var(--font-bold);
    color: var(--energy-400);
    margin-bottom: var(--space-1);
}

.live-date {
    font-size: var(--text-xs);
    color: var(--neutral-400);
}

/* Quick Tools */
.quick-tools {
    display: flex;
    gap: var(--space-2);
    flex-wrap: wrap;
}

.tool-btn {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: var(--radius-lg);
    background: var(--neutral-800);
    color: var(--neutral-400);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast) var(--ease-out);
    border: 1px solid var(--neutral-700);
}

.tool-btn:hover {
    background: var(--energy-500);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Footer Bottom */
.copyright {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.copyright-text {
    font-size: var(--text-sm);
    color: var(--neutral-300);
}

.tech-stack {
    display: flex;
    gap: var(--space-4);
    flex-wrap: wrap;
}

.tech-item {
    display: flex;
    align-items: center;
    gap: var(--space-1);
    font-size: var(--text-xs);
    color: var(--neutral-500);
}

.tech-item i {
    font-size: var(--text-sm);
}

/* Footer Actions */
.footer-actions {
    display: flex;
    align-items: center;
    gap: var(--space-4);
}

.performance-badge {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-3);
    background: var(--neutral-800);
    border-radius: var(--radius-lg);
    font-size: var(--text-xs);
    color: var(--neutral-300);
    border: 1px solid var(--neutral-700);
}

.back-to-top-enhanced {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-4);
    background: var(--gradient-energy);
    color: white;
    border: none;
    border-radius: var(--radius-xl);
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
    transition: all var(--transition-fast) var(--ease-out);
    opacity: 0;
    transform: translateY(10px);
}

.back-to-top-enhanced.visible {
    opacity: 1;
    transform: translateY(0);
}

.back-to-top-enhanced:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* FAB Container */
.fab-container {
    position: fixed;
    bottom: var(--space-6);
    right: var(--space-6);
    z-index: var(--z-fixed);
}

.fab-main {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-full);
    background: var(--gradient-energy);
    color: white;
    border: none;
    font-size: var(--text-xl);
    box-shadow: var(--shadow-xl);
    transition: all var(--transition-normal) var(--ease-bounce);
}

.fab-main:hover {
    transform: scale(1.1);
    box-shadow: var(--shadow-2xl);
}

.fab-main.active {
    transform: rotate(45deg);
}

.fab-menu {
    position: absolute;
    bottom: 70px;
    right: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    opacity: 0;
    transform: scale(0.8);
    transition: all var(--transition-normal) var(--ease-bounce);
    pointer-events: none;
}

.fab-menu.open {
    opacity: 1;
    transform: scale(1);
    pointer-events: all;
}

.fab-item {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-full);
    background: var(--neutral-800);
    color: var(--neutral-200);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-lg);
    transition: all var(--transition-fast) var(--ease-out);
}

.fab-item:hover {
    background: var(--gradient-info);
    color: white;
    transform: scale(1.1);
}

/* Keyboard Shortcuts */
.shortcut-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.shortcut-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-2);
    background: var(--neutral-50);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
}

[data-theme="dark"] .shortcut-item {
    background: var(--neutral-800);
}

kbd {
    background: var(--neutral-200);
    color: var(--neutral-800);
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-sm);
    font-size: var(--text-xs);
    font-family: var(--font-mono);
    border: 1px solid var(--neutral-300);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] kbd {
    background: var(--neutral-700);
    color: var(--neutral-200);
    border-color: var(--neutral-600);
}

/* Responsive */
@media (max-width: 768px) {
    .footer-main {
        padding: var(--space-12) 0 var(--space-6);
    }
    
    .tech-stack {
        flex-direction: column;
        gap: var(--space-2);
    }
    
    .footer-actions {
        flex-direction: column;
        align-items: flex-end;
        gap: var(--space-2);
    }
    
    .quick-tools {
        justify-content: center;
    }
    
    .fab-container {
        bottom: var(--space-20); /* Space for mobile navigation */
    }
}
</style>

<!-- Enhanced Footer JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script src="js/enhanced-animations.js"></script>

<script>
// Enhanced Footer Functionality
class EnhancedFooter {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.startLiveClock();
        this.updatePerformanceMetrics();
    }
    
    init() {
        // Back to top visibility
        this.updateBackToTopVisibility();
        
        // Initialize keyboard shortcuts
        this.setupKeyboardShortcuts();
        
        // Setup FAB menu
        this.setupFABMenu();
    }
    
    setupEventListeners() {
        // Scroll events
        window.addEventListener('scroll', this.debounce(() => {
            this.updateBackToTopVisibility();
        }, 100));
        
        // Keyboard events
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
        
        // Performance tracking
        window.addEventListener('load', () => {
            this.updatePerformanceMetrics();
        });
    }
    
    updateBackToTopVisibility() {
        const backToTop = document.getElementById('backToTop');
        const scrolled = window.pageYOffset > 300;
        
        if (backToTop) {
            backToTop.classList.toggle('visible', scrolled);
        }
    }
    
    startLiveClock() {
        const clockElement = document.getElementById('liveClock');
        
        const updateClock = () => {
            if (clockElement) {
                const now = new Date();
                clockElement.textContent = now.toLocaleTimeString('de-DE');
            }
        };
        
        updateClock();
        setInterval(updateClock, 1000);
    }
    
    updatePerformanceMetrics() {
        const performanceElements = [
            document.getElementById('footerPerformance'),
            document.getElementById('performanceValue')
        ];
        
        const totalTime = performance.now() - (window.performanceMetrics?.startTime || 0);
        const formattedTime = Math.round(totalTime) + 'ms';
        
        performanceElements.forEach(el => {
            if (el) {
                el.textContent = formattedTime;
                
                // Color coding based on performance
                if (totalTime < 1000) {
                    el.style.color = 'var(--success-500)';
                } else if (totalTime < 2000) {
                    el.style.color = 'var(--warning-500)';
                } else {
                    el.style.color = 'var(--danger-500)';
                }
            }
        });
    }
    
    setupKeyboardShortcuts() {
        // Register shortcuts
        this.shortcuts = {
            'Alt+KeyD': () => window.location.href = 'dashboard.php',
            'Alt+KeyZ': () => window.location.href = 'zaehlerstand.php',
            'Alt+KeyG': () => window.location.href = 'geraete.php',
            'Alt+KeyA': () => window.location.href = 'auswertung.php',
            'KeyF1': () => this.showKeyboardShortcuts(),
            'Home': () => this.scrollToTop(),
            'ControlLeft+KeyT': () => this.toggleTheme(),
            'ControlLeft+KeyN': () => this.openNewEntryDialog()
        };
    }
    
    handleKeyboardShortcuts(e) {
        const key = (e.altKey ? 'Alt+' : '') + 
                   (e.ctrlKey ? 'ControlLeft+' : '') + 
                   (e.shiftKey ? 'Shift+' : '') + 
                   e.code;
        
        if (this.shortcuts[key]) {
            e.preventDefault();
            this.shortcuts[key]();
        }
        
        // ESC to close modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            });
        }
    }
    
    setupFABMenu() {
        const fabMain = document.querySelector('.fab-main');
        const fabMenu = document.querySelector('.fab-menu');
        
        if (fabMain && fabMenu) {
            fabMain.addEventListener('click', () => {
                fabMain.classList.toggle('active');
                fabMenu.classList.toggle('open');
            });
            
            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.fab-container')) {
                    fabMain.classList.remove('active');
                    fabMenu.classList.remove('open');
                }
            });
        }
    }
    
    // Public Methods
    scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
    
    toggleTheme() {
        if (window.modernFeatures) {
            window.modernFeatures.toggleTheme();
        } else {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('stromtracker-theme', newTheme);
        }
    }
    
    showKeyboardShortcuts() {
        const modal = new bootstrap.Modal(document.getElementById('keyboardShortcutsModal'));
        modal.show();
    }
    
    printPage() {
        window.print();
    }
    
    openNewEntryDialog() {
        // Context-aware new entry
        const currentPage = window.location.pathname.split('/').pop();
        
        switch (currentPage) {
            case 'zaehlerstand.php':
                document.querySelector('[data-bs-target="#addReadingModal"]')?.click();
                break;
            case 'geraete.php':
                document.querySelector('[data-bs-target="#addDeviceModal"]')?.click();
                break;
            case 'tarife.php':
                document.querySelector('[data-bs-target="#addTariffModal"]')?.click();
                break;
            default:
                window.location.href = 'zaehlerstand.php';
        }
    }
    
    // Utility
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Global functions for compatibility
window.scrollToTop = function() {
    window.enhancedFooter?.scrollToTop() || window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.toggleTheme = function() {
    window.enhancedFooter?.toggleTheme();
};

window.showKeyboardShortcuts = function() {
    window.enhancedFooter?.showKeyboardShortcuts();
};

window.printPage = function() {
    window.enhancedFooter?.printPage() || window.print();
};

window.showQuickActions = function() {
    const fabMain = document.querySelector('.fab-main');
    const fabMenu = document.querySelector('.fab-menu');
    
    if (fabMain && fabMenu) {
        fabMain.classList.toggle('active');
        fabMenu.classList.toggle('open');
    }
};

// Initialize Enhanced Footer
document.addEventListener('DOMContentLoaded', function() {
    try {
        window.enhancedFooter = new EnhancedFooter();
        console.log('[Enhanced Footer] Initialized successfully');
    } catch (error) {
        console.error('[Enhanced Footer] Initialization failed:', error);
    }
});

// Development helpers
<?php if (defined('DEBUG') && DEBUG): ?>
console.group('Enhanced Footer Debug Info');
console.log('Theme:', document.documentElement.getAttribute('data-theme'));
console.log('Performance Start:', window.performanceMetrics?.startTime);
console.log('PHP Version:', '<?= PHP_VERSION ?>');
console.log('Current Page:', window.location.pathname);
console.groupEnd();
<?php endif; ?>
</script>

</body>
</html>