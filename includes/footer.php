<?php
// includes/footer.php
// Footer für Stromtracker - ohne Uhrzeit
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
                        Version 3.0
                    </span>
                    <small style="color: var(--gray-500);">
                        Build 2025-09
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
                            <i class="bi bi-tags me-2"></i>Tarife
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Info & Hilfe -->
            <div class="col-md-3 mb-4">
                <h6 class="text-white mb-3">Hilfe & Support</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="einstellungen.php" class="text-decoration-none" style="color: var(--gray-400);">
                            <i class="bi bi-gear me-2"></i>Einstellungen
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="profil.php" class="text-decoration-none" style="color: var(--gray-400);">
                            <i class="bi bi-person me-2"></i>Profil
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-decoration-none" style="color: var(--gray-400);">
                            <i class="bi bi-question-circle me-2"></i>FAQ
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-decoration-none" style="color: var(--gray-400);">
                            <i class="bi bi-shield-check me-2"></i>Datenschutz
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Actions -->
            <div class="col-md-2 mb-4">
                <h6 class="text-white mb-3">Aktionen</h6>
                <div class="d-grid gap-2">
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
    
    // Theme Status Update
    function updateThemeStatus() {
        const theme = document.documentElement.getAttribute('data-theme') || 'light';
        const statusElement = document.getElementById('theme-status');
        if (statusElement) {
            statusElement.textContent = theme === 'dark' ? 'Dark Mode' : 'Light Mode';
        }
    }
    
    // Initialize theme status
    updateThemeStatus();
    
    // Listen for theme changes
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
    
    // Page Load Performance
    function updatePerformanceInfo() {
        const loadTime = performance.timing ? 
            ((performance.timing.loadEventEnd - performance.timing.navigationStart) / 1000).toFixed(2) : 
            'N/A';
        
        const perfElement = document.getElementById('page-load-time');
        if (perfElement) {
            perfElement.innerHTML = `${loadTime}s geladen`;
        }
    }
    
    // Update performance info when page is fully loaded
    if (document.readyState === 'complete') {
        updatePerformanceInfo();
    } else {
        window.addEventListener('load', updatePerformanceInfo);
    }
    
    // Scroll to Top Functionality
    const scrollBtn = document.getElementById('scrollTopBtn');
    
    // Show/hide button based on scroll position
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            scrollBtn.style.opacity = '1';
            scrollBtn.style.visibility = 'visible';
        } else {
            scrollBtn.style.opacity = '0';
            scrollBtn.style.visibility = 'hidden';
        }
    });
});

// Global Functions
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Hover effects for footer links
document.querySelectorAll('footer a[style*="color: var(--gray-400)"]').forEach(link => {
    link.addEventListener('mouseenter', function() {
        this.style.color = 'var(--energy)';
    });
    
    link.addEventListener('mouseleave', function() {
        this.style.color = 'var(--gray-400)';
    });
});
</script>

</body>
</html>