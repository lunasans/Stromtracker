<?php
// includes/footer-modern.php
// Sichere Footer-Version ohne Auto-Reloads
?>

<!-- Footer -->
<footer class="bg-dark text-light py-4 mt-5" role="contentinfo">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <h6>
                    <span class="energy-indicator me-2"></span>
                    <i class="bi bi-lightning-charge text-warning"></i> 
                    Stromtracker
                </h6>
                <p class="small mb-0">
                    Verwalten Sie Ihren Stromverbrauch effizient und nachhaltig.
                </p>
                <div class="mt-2">
                    <small class="text-muted">
                        Version 2.0 | Build <?= date('Ymd') ?>
                    </small>
                </div>
            </div>
            
            <div class="col-md-3">
                <h6>Navigation</h6>
                <ul class="list-unstyled small">
                    <li><a href="dashboard.php" class="text-light text-decoration-none">Dashboard</a></li>
                    <li><a href="zaehlerstand.php" class="text-light text-decoration-none">Zählerstand</a></li>
                    <li><a href="geraete.php" class="text-light text-decoration-none">Geräte</a></li>
                    <li><a href="auswertung.php" class="text-light text-decoration-none">Auswertung</a></li>
                </ul>
            </div>
            
            <div class="col-md-3">
                <h6>System Status</h6>
                <ul class="list-unstyled small">
                    <li>
                        <span class="energy-indicator me-1"></span>
                        <span class="text-success">Database: Online</span>
                    </li>
                    <li>
                        <i class="bi bi-cpu text-info"></i>
                        PHP <?= PHP_VERSION ?>
                    </li>
                    <li>
                        <i class="bi bi-bootstrap text-primary"></i>
                        Bootstrap 5.3
                    </li>
                    <li>
                        <i class="bi bi-clock text-muted"></i>
                        <span id="live-time"><?= date('H:i:s') ?></span>
                    </li>
                </ul>
            </div>
        </div>
        
        <hr class="my-3 border-secondary">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <small class="text-muted">
                    © <?= date('Y') ?> Stromtracker - Entwickelt mit ❤️ für nachhaltige Energie
                </small>
            </div>
            <div class="col-md-6 text-end">
                <div class="d-flex align-items-center justify-content-end gap-3">
                    <!-- Back to Top -->
                    <button class="btn btn-sm btn-outline-light" 
                            onclick="scrollToTop()" 
                            title="Nach oben">
                        <i class="bi bi-arrow-up"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Floating Action Button (nur Mobile) -->
<div class="fab-container d-block d-lg-none">
    <button class="fab-main" onclick="showQuickAdd()" title="Schnellaktionen">
        <i class="bi bi-plus"></i>
    </button>
</div>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript (bestehend) -->
<script src="js/main.js"></script>

<!-- Modern Features (nur wenn verfügbar) -->
<script src="js/modern-features.js"></script>

<!-- Minimale, sichere Scripts -->
<script>
// =====================================
// Nur kritische, sichere Funktionen
// =====================================

// Back to Top
function scrollToTop() {
    try {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e) {
        window.scrollTo(0, 0); // Fallback
    }
}

// Back to Top Button Sichtbarkeit
window.addEventListener('scroll', function() {
    try {
        const backToTop = document.querySelector('button[onclick="scrollToTop()"]');
        if (backToTop) {
            backToTop.style.opacity = window.pageYOffset > 300 ? '1' : '0.5';
        }
    } catch (e) {
        // Ignorieren
    }
});

// Quick Add Funktion
function showQuickAdd() {
    try {
        if (typeof bootstrap !== 'undefined') {
            // Einfaches Alert statt komplexes Modal
            alert('Schnellaktionen:\n• Zählerstand: zaehlerstand.php\n• Geräte: geraete.php\n• Charts: auswertung.php');
        }
    } catch (e) {
        console.log('Quick add error:', e);
    }
}

// Live-Zeit (optional)
function updateTime() {
    try {
        const timeEl = document.getElementById('live-time');
        if (timeEl) {
            timeEl.textContent = new Date().toLocaleTimeString('de-DE');
        }
    } catch (e) {
        // Ignorieren
    }
}

// Sichere Initialisierung
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Tooltips initialisieren
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(el => new bootstrap.Tooltip(el));
        }
        
        // Live-Zeit starten
        updateTime();
        setInterval(updateTime, 1000);
        
        // Energy Indicators starten
        document.querySelectorAll('.energy-indicator').forEach((el, i) => {
            el.style.animationDelay = (i * 0.5) + 's';
        });
        
        // Modern Features initialisieren (falls verfügbar)
        if (typeof window.modernFeatures !== 'undefined') {
            console.log('Modern features loaded successfully');
        }
        
    } catch (error) {
        console.log('Footer initialization error:', error);
        // KEIN RELOAD! Nur loggen.
    }
});

// Keyboard Shortcuts (optional)
document.addEventListener('keydown', function(e) {
    try {
        // Escape für Modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            });
        }
    } catch (e) {
        // Ignorieren
    }
});

// Auto-hide Flash Messages (sicher)
setTimeout(function() {
    try {
        document.querySelectorAll('.alert').forEach(alert => {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        });
    } catch (e) {
        // Ignorieren
    }
}, 5000);

// =====================================
// KEINE Error Handler mit Auto-Reload!
// =====================================

// Debug Info (nur Development)
<?php if (defined('DEBUG') && DEBUG): ?>
console.group('Stromtracker Debug');
console.log('PHP:', '<?= PHP_VERSION ?>');
console.log('Page:', '<?= basename($_SERVER['PHP_SELF']) ?>');
console.log('Memory:', '<?= round(memory_get_usage() / 1024 / 1024, 2) ?> MB');
console.groupEnd();
<?php endif; ?>

</script>

</body>
</html>