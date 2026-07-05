<?php
// includes/footer.php
// Footer für Stromtracker — Styles zentral in css/style.css (Abschnitt 14)
?>

    </main>
    <!-- Ende Main Content Wrapper -->

<footer class="app-footer">
    <div class="container-fluid">

        <div class="row">

            <!-- Brand Section -->
            <div class="col-md-4 mb-4">
                <h5 class="mb-3">
                    <span class="energy-indicator"></span>
                    <i class="bi bi-lightning-charge text-energy"></i>
                    Stromtracker
                </h5>
                <p class="mb-3">
                    Verwalten Sie Ihren Stromverbrauch effizient und nachhaltig.
                </p>

                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge badge-energy">Version 3.1</span>
                    <small>Build 2026-07</small>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-md-3 mb-4">
                <h6 class="mb-3">Navigation</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="dashboard.php" class="app-footer-link">
                            <i class="bi bi-house-door me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="zaehlerstand.php" class="app-footer-link">
                            <i class="bi bi-speedometer2 me-2"></i>Zählerstand
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="geraete.php" class="app-footer-link">
                            <i class="bi bi-cpu me-2"></i>Geräte
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="auswertung.php" class="app-footer-link">
                            <i class="bi bi-bar-chart me-2"></i>Auswertung
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="tarife.php" class="app-footer-link">
                            <i class="bi bi-tags me-2"></i>Tarife
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Konto -->
            <div class="col-md-3 mb-4">
                <h6 class="mb-3">Konto</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="einstellungen.php" class="app-footer-link">
                            <i class="bi bi-gear me-2"></i>Einstellungen
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="profil.php" class="app-footer-link">
                            <i class="bi bi-person me-2"></i>Profil
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="logout.php" class="app-footer-link">
                            <i class="bi bi-box-arrow-right me-2"></i>Abmelden
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="col-md-2 mb-4">
                <h6 class="mb-3">Aktionen</h6>
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
        <hr>

        <div class="row align-items-center">
            <div class="col-md-6">
                <small>© <?= date('Y') ?> Stromtracker – Entwickelt mit ❤️ und PHP</small>
            </div>
            <div class="col-md-6 text-md-end">
                <small>
                    <i class="bi bi-palette me-1"></i>
                    <span id="theme-status">Light Mode</span>
                </small>
            </div>
        </div>
    </div>
</footer>

<!-- Scroll to Top Button (Floating) -->
<button id="scrollTopBtn" class="scroll-top-btn" onclick="scrollToTop()" title="Nach oben">
    <i class="bi bi-arrow-up" style="font-size: 1.2rem;"></i>
</button>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Footer Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // Theme-Status-Anzeige aktuell halten
    function updateThemeStatus() {
        const theme = document.documentElement.getAttribute('data-theme') || 'light';
        const statusElement = document.getElementById('theme-status');
        if (statusElement) {
            statusElement.textContent = theme === 'dark' ? 'Dark Mode' : 'Light Mode';
        }
    }

    updateThemeStatus();

    new MutationObserver(updateThemeStatus).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    // Scroll-to-Top-Button ein-/ausblenden
    const scrollBtn = document.getElementById('scrollTopBtn');
    window.addEventListener('scroll', function() {
        scrollBtn.classList.toggle('visible', window.pageYOffset > 300);
    }, { passive: true });
});

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

</body>
</html>
