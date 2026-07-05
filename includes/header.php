<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Stromtracker', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="theme-color" content="#1e293b">

    <!-- CRITICAL: Theme Loading BEFORE any CSS to prevent FOUC -->
    <script>
        // Theme sofort aus localStorage laden und anwenden (blocking)
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);

            if (savedTheme === 'dark') {
                document.documentElement.style.setProperty('--initial-bg', '#0f172a');
                document.documentElement.style.setProperty('--initial-color', '#e2e8f0');
            } else {
                document.documentElement.style.setProperty('--initial-bg', '#f8fafc');
                document.documentElement.style.setProperty('--initial-color', '#334155');
            }
        })();
    </script>

    <!-- Inline Critical CSS for immediate theme application -->
    <style>
        /* Verhindert FOUC durch sofortige Theme-Anwendung */
        html {
            background-color: var(--initial-bg, #f8fafc);
            color: var(--initial-color, #334155);
        }

        body {
            background-color: var(--initial-bg, #f8fafc);
            color: var(--initial-color, #334155);
        }
    </style>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Main CSS -->
    <link href="css/style.css" rel="stylesheet">

    <!-- Chart.js für Diagramme -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="js/tasmota-integration.js" defer></script>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">

    <!-- Zentrale Theme-Toggle-Funktion (einzige Definition der App) -->
    <script>
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = current === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);

            // Critical-CSS-Variablen mitziehen (html/body-Hintergrund)
            if (newTheme === 'dark') {
                document.documentElement.style.setProperty('--initial-bg', '#0f172a');
                document.documentElement.style.setProperty('--initial-color', '#e2e8f0');
            } else {
                document.documentElement.style.setProperty('--initial-bg', '#f8fafc');
                document.documentElement.style.setProperty('--initial-color', '#334155');
            }

            updateThemeIcon(newTheme);
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-sun me-2 text-info' : 'bi bi-moon-stars me-2 text-info';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateThemeIcon(localStorage.getItem('theme') || 'light');

            // Flash Messages nach 5 Sekunden ausblenden
            const flash = document.getElementById('flash-messages');
            if (flash && flash.children.length > 0) {
                setTimeout(() => {
                    flash.style.opacity = '0';
                    setTimeout(() => flash.remove(), 500);
                }, 5000);
            }
        });
    </script>
</head>

<body>
    <?php // Flash-Messages im Body (nicht im <head> – valides HTML) ?>
    <?php if (class_exists('Flash') && Flash::has()): ?>
        <div id="flash-messages" class="flash-container"><?= Flash::display() ?></div>
    <?php endif; ?>

    <!-- Main Content Wrapper für Sticky Footer -->
    <main class="main-content">
