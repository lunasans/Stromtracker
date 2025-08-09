<?php
// includes/footer.php
// HTML Footer für alle Seiten
?>

<!-- Footer -->
<footer class="bg-dark text-light py-4 mt-5">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6">
                <h6><i class="bi bi-lightning-charge text-warning"></i> Stromtracker</h6>
                <p class="small mb-0">
                    Verwalten Sie Ihren Stromverbrauch effizient und nachhaltig.
                </p>
            </div>
            <div class="col-md-3">
                <h6>Quick Links</h6>
                <ul class="list-unstyled small">
                    <li><a href="dashboard.php" class="text-light text-decoration-none">Dashboard</a></li>
                    <li><a href="geraete.php" class="text-light text-decoration-none">Geräte</a></li>
                    <li><a href="verbrauch.php" class="text-light text-decoration-none">Verbrauch</a></li>
                    <li><a href="auswertung.php" class="text-light text-decoration-none">Auswertung</a></li>
                </ul>
            </div>
            <div class="col-md-3">
                <h6>System Info</h6>
                <ul class="list-unstyled small">
                    <li><i class="bi bi-check-circle text-success"></i> PHP <?= PHP_VERSION ?></li>
                    <li><i class="bi bi-check-circle text-success"></i> MySQL aktiv</li>
                    <li><i class="bi bi-check-circle text-success"></i> Bootstrap 5.3</li>
                    <li><i class="bi bi-clock"></i> <?= date('d.m.Y H:i') ?></li>
                </ul>
            </div>
        </div>
        
        <hr class="my-3 border-secondary">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <small class="text-muted">
                    © <?= date('Y') ?> Stromtracker - Entwickelt mit ❤️ und PHP
                </small>
            </div>
            <div class="col-md-6 text-end">
                <small class="text-muted">
                    <i class="bi bi-code-slash"></i> 
                    LAMP Stack (Linux, Apache, MySQL, PHP)
                </small>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="js/main.js"></script>

<!-- Real-time Clock -->
<script>
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleString('de-DE');
    const clockElement = document.querySelector('.footer-clock');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}

// Update clock every second
setInterval(updateClock, 1000);
updateClock(); // Initial call

// Energy indicator animation
document.addEventListener('DOMContentLoaded', function() {
    const indicators = document.querySelectorAll('.energy-indicator');
    indicators.forEach(indicator => {
        // Random pulse delay for natural effect
        const delay = Math.random() * 2000;
        indicator.style.animationDelay = delay + 'ms';
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Tooltip initialization
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

</body>
</html>