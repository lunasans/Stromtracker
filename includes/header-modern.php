<?php
// includes/header-enhanced.php
// Enhanced Header mit verbessertem Design System
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f59e0b">
    <meta name="description" content="Moderne Stromverbrauchsverwaltung - Effizient, übersichtlich, nachhaltig">
    
    <title><?= $pageTitle ?? 'Stromtracker - Moderne Energieverwaltung' ?></title>
    
    <!-- Preload kritische Ressourcen -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&family=Inter+Display:wght@400;500;600;700&display=swap" as="style">
    <link rel="preload" href="css/enhanced-design-system.css" as="style">
    <link rel="preload" href="js/enhanced-animations.js" as="script">
    
    <!-- Google Fonts (Inter Family) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&family=Inter+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Design System -->
    <!--  <link href="css/style.css" rel="stylesheet"> -->
    <link href="css/modern-styles.css" rel="stylesheet">
    <link href="css/enhanced-design-system.css" rel="stylesheet">
    
    <!-- Chart.js für Diagramme -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js" defer></script>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- iOS Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Stromtracker">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= $pageTitle ?? 'Stromtracker' ?>">
    <meta property="og:description" content="Moderne Stromverbrauchsverwaltung">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    
    <!-- Critical CSS Inlining für Performance -->
    <style>
        /* Critical CSS für Above-the-fold Content */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #111827;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Loading Screen */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        
        .loading-screen.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .loading-content {
            text-align: center;
            color: white;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #fbbf24;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .loading-subtitle {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        /* Prevent FOUC */
        .enhanced-content {
            opacity: 0;
            transition: opacity 0.6s ease;
        }
        
        .enhanced-content.loaded {
            opacity: 1;
        }
        
        /* Energy Indicator Animation */
        .energy-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #d97706 100%);
            animation: energyPulse 2s ease-in-out infinite;
            display: inline-block;
            margin-right: 8px;
        }
        
        @keyframes energyPulse {
            0%, 100% { 
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7);
            }
            50% { 
                transform: scale(1.1);
                box-shadow: 0 0 0 10px rgba(251, 191, 36, 0);
            }
        }
    </style>
    
    <!-- Theme Detection Script (ASAP) -->
    <script>
        // Theme Detection vor dem Laden der Seite
        (function() {
            const savedTheme = localStorage.getItem('stromtracker-theme') || 'auto';
            
            if (savedTheme === 'auto') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
            } else {
                document.documentElement.setAttribute('data-theme', savedTheme);
            }
            
            // Reduced Motion Detection
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.documentElement.setAttribute('data-reduced-motion', 'true');
            }
        })();
    </script>
</head>
<body class="enhanced-content" data-theme="light">

    <!-- Enhanced Loading Screen -->
    <div id="loadingScreen" class="loading-screen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">
                <span class="energy-indicator"></span>
                Stromtracker
            </div>
            <div class="loading-subtitle">Design-System wird geladen...</div>
        </div>
    </div>

    <!-- Skip to main content (Accessibility) -->
    <a href="#main-content" class="visually-hidden-focusable">Zum Hauptinhalt springen</a>

    <?php
    // Flash Messages anzeigen (falls vorhanden)
    if (class_exists('Flash') && Flash::has()) {
        echo '<div class="container-fluid mt-2" role="alert" data-animate="slide-in-up">' . Flash::display() . '</div>';
    }
    
    // Page-spezifische Meta-Informationen
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
    $pageConfig = $pageConfig ?? [
        'enableGlassmorphism' => true,
        'enableAnimations' => true,
        'showThemeToggle' => true,
        'animationLevel' => 'enhanced' // basic, enhanced, full
    ];
    ?>

    <!-- Enhanced Theme Toggle -->
    <div class="theme-toggle-container" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <div class="theme-toggle glass-light" id="themeToggle">
            <i class="bi bi-moon-stars" id="themeIcon"></i>
        </div>
    </div>

    <!-- Structured Data für SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Stromtracker",
        "description": "Moderne Stromverbrauchsverwaltung mit Enhanced Design System",
        "url": "<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>",
        "applicationCategory": "UtilityApplication",
        "operatingSystem": "Any",
        "browserRequirements": "Requires JavaScript, Modern Browser",
        "softwareVersion": "2.0 Enhanced",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "EUR"
        },
        "featureList": [
            "Stromverbrauch-Tracking",
            "Responsive Design",
            "Dark/Light Theme",
            "Progressive Web App",
            "Enhanced Animations"
        ]
    }
    </script>

    <!-- Early Enhancement Script -->
    <script>
        // Performance Tracking
        window.performanceMetrics = {
            startTime: performance.now(),
            loadEvents: []
        };
        
        // Critical Enhancement Functions
        function addLoadEvent(name) {
            window.performanceMetrics.loadEvents.push({
                name: name,
                time: performance.now()
            });
        }
        
        // Early DOM Enhancement
        document.addEventListener('DOMContentLoaded', function() {
            addLoadEvent('DOM Ready');
            
            // Enhanced Loading vervollständigen
            setTimeout(() => {
                const loadingScreen = document.getElementById('loadingScreen');
                const content = document.body;
                
                if (loadingScreen) {
                    loadingScreen.classList.add('hidden');
                    setTimeout(() => {
                        loadingScreen.remove();
                    }, 500);
                }
                
                content.classList.add('loaded');
                addLoadEvent('Enhanced Loading Complete');
            }, 300);
        });
        
        // Window Load Performance
        window.addEventListener('load', function() {
            addLoadEvent('Window Load');
            
            const totalTime = performance.now() - window.performanceMetrics.startTime;
            console.log(`[Enhanced Design] Page loaded in ${Math.round(totalTime)}ms`);
            
            // Performance Metrics ausgeben (Development)
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.table(window.performanceMetrics.loadEvents);
            }
        });
        
        // Service Worker registrieren (PWA Enhancement)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('[Enhanced PWA] SW registered: ', registration);
                        addLoadEvent('Service Worker Registered');
                    })
                    .catch(function(registrationError) {
                        console.log('[Enhanced PWA] SW registration failed: ', registrationError);
                    });
            });
        }
        
        // Theme Toggle Functionality
        function initThemeToggle() {
            const toggle = document.getElementById('themeToggle');
            const icon = document.getElementById('themeIcon');
            
            if (toggle && icon) {
                // Initial icon state
                const currentTheme = document.documentElement.getAttribute('data-theme');
                updateThemeIcon(icon, currentTheme);
                
                toggle.addEventListener('click', function() {
                    const current = document.documentElement.getAttribute('data-theme');
                    const newTheme = current === 'dark' ? 'light' : 'dark';
                    
                    document.documentElement.setAttribute('data-theme', newTheme);
                    localStorage.setItem('stromtracker-theme', newTheme);
                    updateThemeIcon(icon, newTheme);
                    
                    // Smooth transition
                    document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
                    setTimeout(() => {
                        document.body.style.transition = '';
                    }, 300);
                });
            }
        }
        
        function updateThemeIcon(icon, theme) {
            if (theme === 'dark') {
                icon.className = 'bi bi-sun';
            } else {
                icon.className = 'bi bi-moon-stars';
            }
        }
        
        // Theme Toggle nach DOM Ready
        document.addEventListener('DOMContentLoaded', initThemeToggle);
        
        // Enhanced Error Handling
        window.addEventListener('error', function(e) {
            console.error('[Enhanced Design] JavaScript Error:', e.error);
            // Graceful degradation - continue ohne Animations
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            console.error('[Enhanced Design] Promise Rejection:', e.reason);
        });
    </script>

    <!-- Enhanced Design System wird nach DOM Ready geladen -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced Animations laden
            const script = document.createElement('script');
            script.src = 'js/enhanced-animations.js';
            script.defer = true;
            document.head.appendChild(script);
            
            addLoadEvent('Enhanced Animations Script Loaded');
        });
    </script>