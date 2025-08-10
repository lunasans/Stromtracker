<?php
// includes/header-simple.php
// EINFACHER Header - Nur das Nötige, schön und sauber
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Stromtracker' ?></title>
    
    <!-- Google Fonts - Nur Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- EINE einfache CSS-Datei -->
    <link href="css/simple-design.css" rel="stylesheet">
    
    <!-- Chart.js für Diagramme -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js" defer></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    
    <?php
    // Flash Messages anzeigen
    if (class_exists('Flash')) {
        echo '<div id="flash-messages" style="position:fixed;top:20px;right:20px;z-index:1060;">' . Flash::display() . '</div>';
    }
    ?>
    
    <!-- Einfaches Theme Toggle -->
    <script>
        // Theme aus localStorage laden
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Theme Toggle Funktion
        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const newTheme = current === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Icon Update
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = newTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            }
        }
        
        // Icon nach dem Laden setzen
        document.addEventListener('DOMContentLoaded', function() {
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = savedTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
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
    </script>
</head>

<body>
    <!-- Einfacher Theme Toggle Button -->
    <button onclick="toggleTheme()" 
            style="position: fixed; top: 20px; right: 20px; z-index: 1060; 
                   width: 44px; height: 44px; border-radius: 50%; 
                   background: var(--white); border: 2px solid var(--gray-200); 
                   color: var(--energy); cursor: pointer; 
                   display: flex; align-items: center; justify-content: center;
                   box-shadow: var(--shadow);"
            title="Theme wechseln">
        <i id="themeIcon" class="bi bi-moon-stars"></i>
    </button>