// js/modern-features.js
// Moderne Features für Stromtracker - FIXED VERSION (keine Auto-Reloads)

class ModernFeatures {
    
    constructor() {
        this.errorCount = 0;
        this.maxErrors = 3;
        this.init();
    }
    
    // ====================================
    // Initialisierung
    // ====================================
    init() {
        try {
            this.setupThemeToggle();
            this.setupAnimations();
            this.setupMobileFeatures();
            this.startEnergyIndicators();
            
            document.addEventListener('DOMContentLoaded', () => {
                this.onPageLoad();
            });
        } catch (error) {
            console.error('[ModernFeatures] Initialization error:', error);
        }
    }
    
    // ====================================
    // Theme Management
    // ====================================
    setupThemeToggle() {
        try {
            // Gespeichertes Theme laden
            const savedTheme = localStorage.getItem('stromtracker-theme') || 'light';
            this.setTheme(savedTheme);
            
            // Event Listener für Theme Toggle
            document.addEventListener('click', (e) => {
                if (e.target.closest('.theme-toggle')) {
                    this.toggleTheme();
                }
            });
        } catch (error) {
            console.error('[ModernFeatures] Theme setup error:', error);
        }
    }
    
    toggleTheme() {
        try {
            const currentTheme = document.body.dataset.theme || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            this.setTheme(newTheme);
        } catch (error) {
            console.error('[ModernFeatures] Theme toggle error:', error);
        }
    }
    
    setTheme(theme) {
        try {
            document.body.dataset.theme = theme;
            localStorage.setItem('stromtracker-theme', theme);
            
            // Theme Toggle Button aktualisieren
            this.updateThemeToggleButton(theme);
            
            // Charts aktualisieren falls vorhanden
            this.updateChartTheme(theme);
        } catch (error) {
            console.error('[ModernFeatures] Set theme error:', error);
        }
    }
    
    updateThemeToggleButton(theme) {
        const icon = document.getElementById('theme-icon');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
        
        // Alle Theme Toggle Buttons aktualisieren
        document.querySelectorAll('.theme-toggle i').forEach(icon => {
            icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        });
    }
    
    updateChartTheme(theme) {
        try {
            if (typeof Chart !== 'undefined') {
                Chart.defaults.color = theme === 'dark' ? '#ffffff' : '#6c757d';
                Chart.defaults.borderColor = theme === 'dark' ? '#404040' : '#dee2e6';
            }
        } catch (error) {
            console.error('[ModernFeatures] Chart theme update error:', error);
        }
    }
    
    // ====================================
    // Animationen
    // ====================================
    setupAnimations() {
        try {
            this.setupScrollAnimations();
            this.setupCardAnimations();
            this.setupButtonAnimations();
        } catch (error) {
            console.error('[ModernFeatures] Animation setup error:', error);
        }
    }
    
    setupScrollAnimations() {
        try {
            if (!('IntersectionObserver' in window)) {
                console.warn('[ModernFeatures] IntersectionObserver not supported');
                return;
            }
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        entry.target.classList.add('animate-in');
                    }
                });
            }, { threshold: 0.1 });
            
            document.querySelectorAll('.card, .stats-card').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
        } catch (error) {
            console.error('[ModernFeatures] Scroll animations error:', error);
        }
    }
    
    setupCardAnimations() {
        try {
            document.addEventListener('click', (e) => {
                const card = e.target.closest('.stats-card, .stats-card-modern');
                if (card) {
                    this.animateCardClick(card);
                }
            });
        } catch (error) {
            console.error('[ModernFeatures] Card animations error:', error);
        }
    }
    
    animateCardClick(card) {
        try {
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
                card.style.transform = '';
            }, 150);
            
            const valueElement = card.querySelector('h3, h4');
            if (valueElement) {
                valueElement.classList.add('pulse-data');
                setTimeout(() => {
                    valueElement.classList.remove('pulse-data');
                }, 600);
            }
        } catch (error) {
            console.error('[ModernFeatures] Card click animation error:', error);
        }
    }
    
    setupButtonAnimations() {
        try {
            document.addEventListener('mouseenter', (e) => {
                if (e.target.matches('.btn, .quick-action')) {
                    e.target.style.transform = 'translateY(-2px)';
                }
            });
            
            document.addEventListener('mouseleave', (e) => {
                if (e.target.matches('.btn, .quick-action')) {
                    e.target.style.transform = '';
                }
            });
        } catch (error) {
            console.error('[ModernFeatures] Button animations error:', error);
        }
    }
    
    // ====================================
    // Mobile Features
    // ====================================
    setupMobileFeatures() {
        try {
            if (window.innerWidth <= 768) {
                this.createMobileBottomNav();
            }
            
            this.setupResponsiveNavbar();
        } catch (error) {
            console.error('[ModernFeatures] Mobile features error:', error);
        }
    }
    
    createMobileBottomNav() {
        try {
            if (document.querySelector('.mobile-bottom-nav')) return;
            
            const nav = document.createElement('div');
            nav.className = 'mobile-bottom-nav d-lg-none';
            nav.innerHTML = `
                <a href="dashboard.php" class="mobile-nav-item ${this.isCurrentPage('dashboard') ? 'active' : ''}">
                    <i class="bi bi-house-door mb-1"></i>
                    <span>Home</span>
                </a>
                <a href="zaehlerstand.php" class="mobile-nav-item ${this.isCurrentPage('zaehlerstand') ? 'active' : ''}">
                    <i class="bi bi-speedometer2 mb-1"></i>
                    <span>Ablesung</span>
                </a>
                <a href="auswertung.php" class="mobile-nav-item ${this.isCurrentPage('auswertung') ? 'active' : ''}">
                    <i class="bi bi-bar-chart mb-1"></i>
                    <span>Charts</span>
                </a>
                <a href="geraete.php" class="mobile-nav-item ${this.isCurrentPage('geraete') ? 'active' : ''}">
                    <i class="bi bi-cpu mb-1"></i>
                    <span>Geräte</span>
                </a>
            `;
            document.body.appendChild(nav);
            document.body.style.paddingBottom = '80px';
        } catch (error) {
            console.error('[ModernFeatures] Mobile nav creation error:', error);
        }
    }
    
    isCurrentPage(page) {
        try {
            return window.location.pathname.includes(page + '.php');
        } catch (error) {
            console.error('[ModernFeatures] Page check error:', error);
            return false;
        }
    }
    
    setupResponsiveNavbar() {
        try {
            const navbar = document.querySelector('.navbar');
            if (navbar && !navbar.classList.contains('glass-navbar')) {
                navbar.classList.add('glass-navbar');
            }
        } catch (error) {
            console.error('[ModernFeatures] Responsive navbar error:', error);
        }
    }
    
    // ====================================
    // Energy Indicators
    // ====================================
    startEnergyIndicators() {
        try {
            const indicators = document.querySelectorAll('.energy-indicator');
            indicators.forEach((indicator, index) => {
                indicator.classList.add('energy-indicator-pulse');
                indicator.style.animationDelay = (index * 0.5) + 's';
            });
            
            // Zufällige Updates alle 10 Sekunden (nicht 5)
            setInterval(() => {
                this.randomPulseUpdate();
            }, 10000);
        } catch (error) {
            console.error('[ModernFeatures] Energy indicators error:', error);
        }
    }
    
    randomPulseUpdate() {
        try {
            const cards = document.querySelectorAll('.stats-card, .stats-card-modern');
            if (cards.length > 0) {
                const randomCard = cards[Math.floor(Math.random() * cards.length)];
                randomCard.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    randomCard.style.transform = '';
                }, 300);
            }
        } catch (error) {
            console.error('[ModernFeatures] Random pulse update error:', error);
        }
    }
    
    // ====================================
    // Page Load Handler
    // ====================================
    onPageLoad() {
        try {
            this.enhanceExistingElements();
            this.initTooltips();
            this.setupFlashMessages();
            this.createThemeToggleIfNeeded();
        } catch (error) {
            console.error('[ModernFeatures] Page load error:', error);
        }
    }
    
    enhanceExistingElements() {
        try {
            // Bestehende Cards modernisieren
            document.querySelectorAll('.card:not(.glass-card)').forEach(card => {
                if (!card.classList.contains('stats-card')) {
                    card.classList.add('glass-card');
                }
            });
            
            // Quick Actions modernisieren
            document.querySelectorAll('a[href*=".php"]').forEach(link => {
                if (link.querySelector('i') && link.closest('.card-body')) {
                    link.classList.add('quick-action');
                }
            });
        } catch (error) {
            console.error('[ModernFeatures] Element enhancement error:', error);
        }
    }
    
    createThemeToggleIfNeeded() {
        try {
            if (!document.querySelector('.theme-toggle')) {
                const toggle = document.createElement('button');
                toggle.className = 'theme-toggle btn';
                toggle.innerHTML = '<i class="bi bi-moon-stars" id="theme-icon"></i>';
                toggle.setAttribute('title', 'Theme wechseln');
                document.body.appendChild(toggle);
                
                console.log('[ModernFeatures] Theme toggle button created');
            }
        } catch (error) {
            console.error('[ModernFeatures] Theme toggle creation error:', error);
        }
    }
    
    initTooltips() {
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        } catch (error) {
            console.error('[ModernFeatures] Tooltips initialization error:', error);
        }
    }
    
    setupFlashMessages() {
        try {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                });
            }, 5000);
        } catch (error) {
            console.error('[ModernFeatures] Flash messages error:', error);
        }
    }
    
    // ====================================
    // Utility Methods
    // ====================================
    showNotification(message, type = 'info') {
        try {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 4000);
        } catch (error) {
            console.error('[ModernFeatures] Show notification error:', error);
        }
    }
    
    updateLiveData(data) {
        try {
            Object.keys(data).forEach(key => {
                const element = document.querySelector(`[data-live="${key}"]`);
                if (element) {
                    element.textContent = data[key];
                    element.classList.add('pulse-data');
                    setTimeout(() => element.classList.remove('pulse-data'), 600);
                }
            });
        } catch (error) {
            console.error('[ModernFeatures] Live data update error:', error);
        }
    }
    
    // ====================================
    // Safe Error Handling
    // ====================================
    handleError(error, context = 'Unknown') {
        this.errorCount++;
        console.error(`[ModernFeatures] Error in ${context}:`, error);
        
        // Nur bei vielen Fehlern Warnung anzeigen (KEIN RELOAD!)
        if (this.errorCount > this.maxErrors) {
            this.showNotification(
                'Einige Features funktionieren möglicherweise nicht korrekt. Bitte laden Sie die Seite neu, wenn Probleme auftreten.',
                'warning'
            );
            
            // Reset error counter nach 30 Sekunden
            setTimeout(() => {
                this.errorCount = 0;
            }, 30000);
        }
    }
}

// ====================================
// CSS für Animationen hinzufügen
// ====================================
const style = document.createElement('style');
style.textContent = `
    .pulse-data {
        animation: dataUpdate 0.6s ease-in-out;
    }
    
    @keyframes dataUpdate {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); color: var(--energy-color, #eab308); }
        100% { transform: scale(1); }
    }
    
    .animate-in {
        opacity: 1 !important;
        transform: translateY(0) !important;
    }
`;
document.head.appendChild(style);

// ====================================
// Sichere Instanz-Erstellung
// ====================================
let modernFeaturesInstance = null;

function initModernFeatures() {
    try {
        if (!modernFeaturesInstance) {
            modernFeaturesInstance = new ModernFeatures();
            window.modernFeatures = modernFeaturesInstance;
            console.log('[ModernFeatures] Successfully initialized');
        }
    } catch (error) {
        console.error('[ModernFeatures] Failed to initialize:', error);
    }
}

// Sichere Initialisierung
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModernFeatures);
} else {
    initModernFeatures();
}

// Globale Funktionen für Kompatibilität
window.toggleTheme = function() {
    if (window.modernFeatures) {
        window.modernFeatures.toggleTheme();
    }
};

window.showQuickActionMenu = function() {
    try {
        const modal = document.getElementById('quickActionModal');
        if (modal && typeof bootstrap !== 'undefined') {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    } catch (error) {
        console.error('Quick action menu error:', error);
    }
};