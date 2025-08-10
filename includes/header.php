<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Stromtracker' ?></title>
    
    <!-- CRITICAL: Theme Loading BEFORE any CSS to prevent FOUC -->
    <script>
        // Theme sofort aus localStorage laden und anwenden (blocking)
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            
            // Zusätzlich CSS Custom Property setzen für sofortige Wirkung
            if (savedTheme === 'dark') {
                document.documentElement.style.setProperty('--initial-bg', '#111827');
                document.documentElement.style.setProperty('--initial-color', '#f9fafb');
            } else {
                document.documentElement.style.setProperty('--initial-bg', '#f9fafb');
                document.documentElement.style.setProperty('--initial-color', '#374151');
            }
        })();
    </script>
    
    <!-- Inline Critical CSS for immediate theme application -->
    <style>
        /* Verhindert FOUC durch sofortige Theme-Anwendung */
        html {
            background-color: var(--initial-bg, #f9fafb);
            color: var(--initial-color, #374151);
        }
        
        body {
            background-color: var(--initial-bg, #f9fafb);
            color: var(--initial-color, #374151);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* Loading-Screen während CSS lädt */
        .theme-loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--initial-bg, #f9fafb);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
            transition: opacity 0.5s ease;
        }
        
        .theme-loading.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .theme-loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid transparent;
            border-top: 3px solid #f59e0b;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    
    <!-- Google Fonts - Nur Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Main CSS -->
    <link href="css/style.css" rel="stylesheet">
    
    <!-- Chart.js für Diagramme -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <?php
    // Flash Messages anzeigen
    if (class_exists('Flash')) {
        echo '<div id="flash-messages" style="position:fixed;top:20px;right:20px;z-index:1060;">' . Flash::display() . '</div>';
    }
    ?>
</head>

<body>
    <!-- Loading Screen -->
    <div class="theme-loading" id="themeLoading">
        <div class="theme-loading-spinner"></div>
    </div>
    
    <!-- Theme Toggle Update Script -->
    <script>
        // Globale Theme-Toggle Funktion (Updated)
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = current === 'dark' ? 'light' : 'dark';
            
            // Theme anwenden
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // CSS Custom Properties für sofortige Wirkung
            if (newTheme === 'dark') {
                document.documentElement.style.setProperty('--initial-bg', '#111827');
                document.documentElement.style.setProperty('--initial-color', '#f9fafb');
            } else {
                document.documentElement.style.setProperty('--initial-bg', '#f9fafb');
                document.documentElement.style.setProperty('--initial-color', '#374151');
            }
            
            // Icon Update
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = newTheme === 'dark' ? 'bi bi-sun me-2' : 'bi bi-moon-stars me-2';
            }
            
            console.log('Theme switched to:', newTheme);
        }
        
        // Loading Screen ausblenden nach DOM Ready
        document.addEventListener('DOMContentLoaded', function() {
            const loadingScreen = document.getElementById('themeLoading');
            if (loadingScreen) {
                setTimeout(() => {
                    loadingScreen.classList.add('hidden');
                    setTimeout(() => {
                        loadingScreen.remove();
                    }, 500);
                }, 100); // Kurze Verzögerung damit CSS geladen ist
            }
            
            // Theme Icon setzen
            const savedTheme = localStorage.getItem('theme') || 'light';
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = savedTheme === 'dark' ? 'bi bi-sun me-2' : 'bi bi-moon-stars me-2';
            }
            
            // Flash Messages nach 5 Sekunden ausblenden
            const flash = document.getElementById('flash-messages');
            if (flash && flash.children.length > 0) {
                setTimeout(() => {
                    flash.style.opacity = '0';
                    setTimeout(() => flash.remove(), 500);
                }, 5000);
            }
        });
        
        // Fallback für sehr langsame Verbindungen
        window.addEventListener('load', function() {
            const loadingScreen = document.getElementById('themeLoading');
            if (loadingScreen) {
                loadingScreen.classList.add('hidden');
                setTimeout(() => {
                    if (loadingScreen.parentNode) {
                        loadingScreen.remove();
                    }
                }, 500);
            }
        });
    </script>

    <!-- Main Content Wrapper für Sticky Footer -->
    <main class="main-content">