<?php
// includes/footer.php
// EINFACHER & SCHÖNER Footer für Stromtracker - STICKY am Bildschirmrand
?>

    </main>
    <!-- Ende Main Content Wrapper -->

<!-- Footer - Sticky am Bildschirmrand -->
<footer style="background: var(--gray-800); color: var(--gray-300); margin-top: auto; padding: var(--space-8) 0;">
    <div class="container-fluid">
        
        <!-- Main Footer Content -->
        <div class="row">
            
            <!-- Brand Section -->
            <div class="col-md-4 mb-4">
                <h5 class="text-white mb-3">
                    <span class="energy-indicator"></span>
                    <i class="bi bi-lightning-charge text-energy"></i> 
                    Stromtracker
                </h5>
                <p class="mb-3" style="color: var(--gray-400);">
                    Verwalten Sie Ihren Stromverbrauch effizient und nachhaltig.
                </p>
                
                <!-- Version Info -->
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge" style="background: var(--energy); color: white; font-weight: 500;">
                        Version 2.0
                    </span>
                    <small style="color: var(--gray-500);">
                        Build <?= date('Ymd') ?>
                    </small>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="col-md-3 mb-4">
                <h6 class="text-white mb-3">Navigation</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="dashboard.php" class="text-decoration-none" style="color: var(--gray-400); transition: color 0.2s ease;">
                            <i class="bi bi-house-door me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="zaehlerstand.php" class="text-decoration-none" style="color: var(--gray-400); transition: color 0.2s ease;">
                            <i class="bi bi-speedometer2 me-2"></i>Zählerstand
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="geraete.php" class="text-decoration-none" style="color: var(--gray-400); transition: color 0.2s ease;">
                            <i class="bi bi-cpu me-2"></i>Geräte
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="auswertung.php" class="text-decoration-none" style="color: var(--gray-400); transition: color 0.2s ease;">
                            <i class="bi bi-bar-chart me-2"></i>Auswertung
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="tarife.php" class="text-decoration-none" style="color: var(--gray-400); transition: color 0.2s ease;">
                            <i class="bi bi-receipt me-2"></i>Tarife
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- System Info -->
            <div class="col-md-3 mb-4">
                <h6 class="text-white mb-3">System Status</h6>
                <ul class="list-unstyled">
                    <li class="mb-2 d-flex align-items-center">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <span style="color: var(--gray-400);">PHP <?= PHP_VERSION ?></span>
                    </li>
                    <li class="mb-2 d-flex align-items-center">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <span style="color: var(--gray-400);">MySQL aktiv</span>
                    </li>
                    <li class="mb-2 d-flex align-items-center">
                        <i class="bi bi-check-circle text-success me-2"></i>
                        <span style="color: var(--gray-400);">Bootstrap 5.3</span>
                    </li>
                    <li class="mb-2 d-flex align-items-center">
                        <i class="bi bi-clock text-energy me-2"></i>
                        <span style="color: var(--gray-400);" id="live-clock"><?= date('d.m.Y H:i') ?></span>
                    </li>
                </ul>
            </div>
            
            <!-- Quick Tools -->
            <div class="col-md-2 mb-4">
                <h6 class="text-white mb-3">Tools</h6>
                <div class="d-flex flex-column gap-2">
                    <button onclick="scrollToTop()" 
                            class="btn btn-outline-secondary btn-sm text-start" 
                            title="Nach oben">
                        <i class="bi bi-arrow-up me-2"></i>Nach oben
                    </button>
                    
                    <button onclick="toggleTheme()" 
                            class="btn btn-outline-secondary btn-sm text-start" 
                            title="Theme wechseln">
                        <i class="bi bi-palette me-2"></i>Theme
                    </button>
                    
                    <button onclick="window.print()" 
                            class="btn btn-outline-secondary btn-sm text-start" 
                            title="Seite drucken">
                        <i class="bi bi-printer me-2"></i>Drucken
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Footer Bottom -->
        <hr style="border-color: var(--gray-600); margin: var(--space-6) 0 var(--space-4) 0;">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <small style="color: var(--gray-500);">
                    © <?= date('Y') ?> Stromtracker - Entwickelt mit ❤️ und PHP
                </small>
            </div>
            <div class="col-md-6 text-end">
                <div class="d-flex justify-content-end align-items-center gap-3">
                    <small style="color: var(--gray-500);">
                        <i class="bi bi-code-slash me-1"></i>
                        LAMP Stack
                    </small>
                    
                    <!-- Theme Status -->
                    <small style="color: var(--gray-500);">
                        <i class="bi bi-palette me-1"></i>
                        <span id="theme-status">Light Mode</span>
                    </small>
                    
                    <!-- Performance Info -->
                    <small style="color: var(--gray-500);">
                        <i class="bi bi-speedometer me-1"></i>
                        <span id="page-load-time">Laden...</span>
                    </small>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Scroll to Top Button (Floating) -->
<button id="scrollTopBtn" 
        onclick="scrollToTop()" 
        style="position: fixed; bottom: 30px; right: 30px; z-index: 1000; 
               width: 50px; height: 50px; border-radius: 50%; 
               background: var(--energy); border: none; color: white; 
               box-shadow: var(--shadow-lg); opacity: 0; visibility: hidden;
               transition: all 0.3s ease; cursor: pointer;"
        title="Nach oben">
    <i class="bi bi-arrow-up" style="font-size: 1.2rem;"></i>
</button>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Footer Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Live Clock Update
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleString('de-DE', {
            day: '2-digit',
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const clockElement = document.getElementById('live-clock');
        if (clockElement) {
            clockElement.textContent = timeString;
        }
    }
    
    // Update clock every minute
    setInterval(updateClock, 60000);
    updateClock();
    
    // Theme Status Update
    function updateThemeStatus() {
        const theme = document.documentElement.getAttribute('data-theme') || 'light';
        const statusElement = document.getElementById('theme-status');
        if (statusElement) {
            statusElement.textContent = theme === 'dark' ? 'Dark Mode' : 'Light Mode';
        }
    }
    
    // Update theme status initially and on changes
    updateThemeStatus();
    
    // Watch for theme changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                updateThemeStatus();
            }
        });
    });
    
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });
    
    // Page Load Time
    function showPageLoadTime() {
        const loadTime = Math.round(performance.now());
        const loadTimeElement = document.getElementById('page-load-time');
        if (loadTimeElement) {
            loadTimeElement.textContent = `${loadTime}ms`;
        }
    }
    
    // Show load time after everything is loaded
    if (document.readyState === 'complete') {
        showPageLoadTime();
    } else {
        window.addEventListener('load', showPageLoadTime);
    }
    
    // Scroll to Top Button
    const scrollTopBtn = document.getElementById('scrollTopBtn');
    
    function toggleScrollButton() {
        if (window.pageYOffset > 300) {
            scrollTopBtn.style.opacity = '1';
            scrollTopBtn.style.visibility = 'visible';
        } else {
            scrollTopBtn.style.opacity = '0';
            scrollTopBtn.style.visibility = 'hidden';
        }
    }
    
    // Show/hide scroll button on scroll
    window.addEventListener('scroll', toggleScrollButton);
    toggleScrollButton(); // Initial check
    
    // Footer Link Hover Effects
    const footerLinks = document.querySelectorAll('footer a');
    footerLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.color = 'var(--energy)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.color = 'var(--gray-400)';
        });
    });
    
    // Energy Indicator Animation (Random delays)
    const indicators = document.querySelectorAll('.energy-indicator');
    indicators.forEach(indicator => {
        const delay = Math.random() * 2000;
        indicator.style.animationDelay = delay + 'ms';
    });
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            try {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            } catch (e) {
                // Alert already closed or not found
            }
        });
    }, 5000);
    
    // Initialize tooltips if any exist
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Global Functions (referenced by buttons)
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Make sure theme toggle is available globally if not already defined
if (typeof window.toggleTheme !== 'function') {
    window.toggleTheme = function() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const newTheme = current === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        // Update theme icon if it exists
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = newTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
        
        console.log('Theme switched to:', newTheme);
    };
}

// Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K for quick actions
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        // Focus search if available, or show quick actions
        const searchInput = document.querySelector('input[type="search"]');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    // Ctrl/Cmd + T for theme toggle
    if ((e.ctrlKey || e.metaKey) && e.key === 't') {
        e.preventDefault();
        toggleTheme();
    }
    
    // Home key to scroll to top
    if (e.key === 'Home' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        scrollToTop();
    }
});

// Development Info (nur im Debug-Modus)
<?php if (defined('DEBUG') && DEBUG): ?>
console.group('🔧 Stromtracker Debug Info');
console.log('📄 Page:', window.location.pathname);
console.log('🎨 Theme:', document.documentElement.getAttribute('data-theme') || 'light');
console.log('⚡ PHP Version:', '<?= PHP_VERSION ?>');
console.log('⏱️ Load Time:', Math.round(performance.now()) + 'ms');
console.groupEnd();
<?php endif; ?>
</script>

</body>
</html>