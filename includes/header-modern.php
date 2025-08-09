<?php
// includes/header-modern.php
// Erweiterte HTML Header für moderne Features
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#eab308">
    <meta name="description" content="Moderne Stromverbrauchsverwaltung - Effizient, übersichtlich, nachhaltig">
    
    <title><?= $pageTitle ?? 'Stromtracker - Moderne Energieverwaltung' ?></title>
    
    <!-- Preload kritische Ressourcen -->
    <link rel="preload" href="css/style.css" as="style"> 
    <link rel="preload" href="css/modern-styles.css" as="style">
    <link rel="preload" href="js/modern-features.js" as="script">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link href="css/style.css" rel="stylesheet">
    <link href="css/modern-styles.css" rel="stylesheet">
    
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
    
    <!-- Inline Critical CSS für Performance -->
    <style>
        /* Critical CSS für Above-the-fold Content */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0, #e0e0e0, #f0f0f0);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
            height: 20px;
            margin: 5px 0;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Prevent FOUC (Flash of Unstyled Content) */
        .modern-loading {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modern-loading.loaded {
            opacity: 1;
        }
    </style>
</head>
<body class="modern-loading" data-theme="light">

    <!-- Loading Screen -->
    <div id="loadingScreen" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" 
         style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); z-index: 9999;">
        <div class="text-center text-white">
            <div class="energy-indicator mb-3 mx-auto" style="width: 40px; height: 40px;"></div>
            <h4>Stromtracker</h4>
            <p class="opacity-75">Wird geladen...</p>
        </div>
    </div>

    <!-- Skip to main content (Accessibility) -->
    <a href="#main-content" class="visually-hidden-focusable">Zum Hauptinhalt springen</a>

    <?php
    // Flash Messages anzeigen (falls vorhanden)
    if (class_exists('Flash') && Flash::has()) {
        echo '<div class="container-fluid mt-2" role="alert">' . Flash::display() . '</div>';
    }
    
    // Page-spezifische Meta-Informationen
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
    $pageConfig = [
        'dashboard' => [
            'description' => 'Übersicht über Ihren Stromverbrauch und Energiekosten',
            'keywords' => 'stromverbrauch, dashboard, energiekosten, übersicht'
        ],
        'geraete' => [
            'description' => 'Verwalten Sie Ihre elektrischen Geräte und deren Stromverbrauch',
            'keywords' => 'geräte, elektrogeräte, stromverbrauch, verwaltung'
        ],
        'zaehlerstand' => [
            'description' => 'Erfassen Sie monatlich Ihren Stromzählerstand',
            'keywords' => 'zählerstand, stromzähler, ablesung, verbrauch'
        ],
        'auswertung' => [
            'description' => 'Detaillierte Analysen und Charts Ihres Stromverbrauchs',
            'keywords' => 'auswertung, charts, analyse, statistik, stromverbrauch'
        ],
        'tarife' => [
            'description' => 'Verwalten Sie Ihren Stromtarif und Abschlag',
            'keywords' => 'stromtarif, abschlag, grundgebühr, strompreis'
        ]
    ];
    ?>

    <!-- Structured Data für SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Stromtracker",
        "description": "<?= $pageConfig[$currentPage]['description'] ?? 'Moderne Stromverbrauchsverwaltung' ?>",
        "url": "<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>",
        "applicationCategory": "UtilityApplication",
        "operatingSystem": "Any",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "EUR"
        }
    }
    </script>

    <!-- Modern Features laden -->
    <script>
        // Frühe Theme-Erkennung (verhindert Flash)
        (function() {
            const savedTheme = localStorage.getItem('stromtracker-theme') || 'light';
            document.body.dataset.theme = savedTheme;
        })();
        
        // Page Load Performance Tracking
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Page loaded in ${Math.round(loadTime)}ms`);
            
            // Loading Screen ausblenden
            const loadingScreen = document.getElementById('loadingScreen');
            if (loadingScreen) {
                loadingScreen.style.opacity = '0';
                setTimeout(() => {
                    loadingScreen.remove();
                    document.body.classList.add('loaded');
                }, 300);
            }
        });
        
        // Service Worker registrieren (PWA)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('SW registered: ', registration);
                    })
                    .catch(function(registrationError) {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
    </script>