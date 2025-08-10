<?php
// includes/navbar.php - Erweitert mit Profilbild-Support & Dark Mode Fix
// Navigation für eingeloggte User

require_once __DIR__ . '/../config/database.php';

$user = Auth::getUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// =============================================================================
// AVATAR-FUNKTIONEN (nur wenn noch nicht definiert)
// =============================================================================

if (!class_exists('ProfileImageHandler')) {
    class ProfileImageHandler {
        private static $uploadDir = 'uploads/profile/';
        
        /**
         * Profilbild-URL abrufen
         */
        public static function getImageUrl($filename) {
            if (empty($filename)) {
                return null;
            }
            
            $filepath = self::$uploadDir . $filename;
            if (file_exists($filepath)) {
                return $filepath . '?v=' . filemtime($filepath); // Cache-busting
            }
            
            return null;
        }
    }
}

/**
 * User-Avatar rendern (nur wenn noch nicht definiert)
 */
if (!function_exists('renderUserAvatar')) {
    function renderUserAvatar($user, $size = 'medium') {
        $sizes = [
            'small' => '24px',
            'medium' => '40px', 
            'large' => '60px'
        ];
        
        $avatarSize = $sizes[$size] ?? $sizes['medium'];
        $imageUrl = ProfileImageHandler::getImageUrl($user['profile_image'] ?? '');
        
        if ($imageUrl) {
            return "<img src='" . htmlspecialchars($imageUrl) . "' 
                         alt='Profilbild' 
                         style='width: {$avatarSize}; height: {$avatarSize}; 
                                border-radius: 50%; object-fit: cover; 
                                border: 2px solid var(--energy); 
                                box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
        } else {
            $initial = strtoupper(substr($user['name'] ?? 'U', 0, 1));
            return "<div style='width: {$avatarSize}; height: {$avatarSize}; 
                                background: linear-gradient(135deg, var(--energy), #d97706); 
                                border-radius: 50%; display: flex; 
                                align-items: center; justify-content: center; 
                                color: white; font-weight: bold; 
                                font-size: calc({$avatarSize} * 0.4);
                                box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>{$initial}</div>";
        }
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <!-- Logo/Brand -->
        <a class="navbar-brand" href="dashboard.php">
            <span class="energy-indicator d-inline-block"></span>
            ⚡ Stromtracker
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navigation Items -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'geraete' ? 'active' : '' ?>" href="geraete.php">
                        <i class="bi bi-lightning"></i> Geräte
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'zaehlerstand' ? 'active' : '' ?>" href="zaehlerstand.php">
                        <i class="bi bi-speedometer2"></i> Zählerstand
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'tarife' ? 'active' : '' ?>" href="tarife.php">
                        <i class="bi bi-receipt"></i> Tarife
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'auswertung' ? 'active' : '' ?>" href="auswertung.php">
                        <i class="bi bi-bar-chart"></i> Auswertung
                    </a>
                </li>
            </ul>
            
            <!-- User Menu -->
            <ul class="navbar-nav">
                <!-- Live-Status -->
                <li class="nav-item d-flex align-items-center me-3">
                    <span class="energy-indicator"></span>
                    <small class="text-light">System aktiv</small>
                </li>
                
                <!-- User Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" 
                       href="#" role="button" data-bs-toggle="dropdown" 
                       style="gap: 0.5rem;">
                        
                        <!-- Profilbild/Avatar -->
                        <?= renderUserAvatar($user, 'medium') ?>
                        
                        <!-- User-Info -->
                        <div class="d-none d-md-block text-start">
                            <div style="font-size: 0.875rem; font-weight: 500;">
                                <?= escape($user['name'] ?? explode('@', $user['email'])[0]) ?>
                            </div>
                            <div style="font-size: 0.75rem; opacity: 0.7;">
                                Online
                            </div>
                        </div>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 280px;">
                        <!-- User-Header im Dropdown -->
                        <li class="dropdown-header">
                            <div class="d-flex align-items-center">
                                <?= renderUserAvatar($user, 'large') ?>
                                <div class="ms-3">
                                    <div class="fw-bold"><?= escape($user['name'] ?? 'Benutzer') ?></div>
                                    <small class="text-muted"><?= escape($user['email']) ?></small>
                                    <div class="d-flex align-items-center mt-1">
                                        <span class="energy-indicator" style="width: 8px; height: 8px; margin-right: 6px;"></span>
                                        <small class="text-success">Online</small>
                                    </div>
                                </div>
                            </div>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Navigation Links -->
                        <li>
                            <a class="dropdown-item" href="profil.php">
                                <i class="bi bi-person me-2"></i> 
                                Mein Profil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="einstellungen.php">
                                <i class="bi bi-gear me-2"></i> 
                                Einstellungen
                            </a>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Quick Actions -->
                        <li class="dropdown-header">Schnellaktionen</li>
                        <li>
                            <a class="dropdown-item" href="zaehlerstand.php">
                                <i class="bi bi-plus-circle me-2 text-success"></i> 
                                Zählerstand erfassen
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="auswertung.php">
                                <i class="bi bi-bar-chart me-2 text-primary"></i> 
                                Auswertung anzeigen
                            </a>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Theme Toggle -->
                        <li>
                            <a class="dropdown-item" href="#" onclick="toggleTheme(); return false;">
                                <i id="themeIcon" class="bi bi-moon-stars me-2"></i> 
                                Theme wechseln
                            </a>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Logout -->
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> 
                                Abmelden
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Breadcrumb (optional) -->
<nav aria-label="breadcrumb" class="breadcrumb-nav py-2">
    <div class="container-fluid">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="dashboard.php" class="text-decoration-none breadcrumb-link">
                    <i class="bi bi-house-door"></i> Home
                </a>
            </li>
            <?php
            $breadcrumbs = [
                'dashboard' => 'Dashboard',
                'geraete' => 'Geräte-Verwaltung', 
                'zaehlerstand' => 'Zählerstand erfassen',
                'tarife' => 'Tarif-Verwaltung',
                'auswertung' => 'Auswertung & Charts',
                'profil' => 'Mein Profil',
                'einstellungen' => 'Einstellungen'
            ];
            
            if (isset($breadcrumbs[$currentPage]) && $currentPage !== 'dashboard') {
                echo '<li class="breadcrumb-item active">' . $breadcrumbs[$currentPage] . '</li>';
            }
            ?>
        </ol>
    </div>
</nav>

<!-- Custom Navbar Styles -->
<style>
.navbar-nav .dropdown-menu {
    border: none;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    border-radius: 12px;
    padding: 0.5rem 0;
}

.dropdown-header {
    padding: 0.75rem 1rem;
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 0.5rem;
}

.dropdown-item {
    padding: 0.5rem 1rem;
    transition: all 0.2s ease;
    border-radius: 0;
}

.dropdown-item:hover {
    background: var(--gray-50);
    transform: translateX(4px);
}

.dropdown-item.text-danger:hover {
    background: rgba(var(--danger), 0.1);
    color: var(--danger) !important;
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.25rem;
    transition: all 0.2s ease;
}

.navbar-brand:hover {
    transform: scale(1.05);
}

.nav-link {
    font-weight: 500;
    transition: all 0.2s ease;
    border-radius: 6px;
    margin: 0 2px;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-1px);
}

.nav-link.active {
    background: rgba(var(--energy), 0.2);
    color: var(--energy) !important;
}

/* Breadcrumb Styling */
.breadcrumb-nav {
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
    transition: all 0.2s ease;
}

.breadcrumb {
    background: none;
    padding: 0;
    margin: 0;
}

.breadcrumb-link {
    color: var(--gray-600);
    transition: color 0.2s ease;
}

.breadcrumb-link:hover {
    color: var(--energy);
}

.breadcrumb-item.active {
    color: var(--gray-700);
    font-weight: 500;
}

.breadcrumb-item + .breadcrumb-item::before {
    color: var(--gray-400);
}

/* Dark Theme Breadcrumb */
[data-theme="dark"] .breadcrumb-nav {
    background: var(--gray-800);
    border-color: var(--gray-700);
}

[data-theme="dark"] .breadcrumb-link {
    color: var(--gray-400);
}

[data-theme="dark"] .breadcrumb-link:hover {
    color: var(--energy);
}

[data-theme="dark"] .breadcrumb-item.active {
    color: var(--gray-200);
}

[data-theme="dark"] .breadcrumb-item + .breadcrumb-item::before {
    color: var(--gray-500);
}

[data-theme="dark"] .dropdown-header {
    background: var(--gray-700);
    border-color: var(--gray-600);
    color: var(--gray-300);
}

[data-theme="dark"] .dropdown-item:hover {
    background: var(--gray-700);
    color: white;
}

/* Mobile Optimizations */
@media (max-width: 768px) {
    .dropdown-menu {
        position: static !important;
        transform: none !important;
        border: none;
        box-shadow: none;
        background: var(--gray-800);
        margin-top: 0.5rem;
    }
    
    .dropdown-item {
        color: var(--gray-300);
    }
    
    .dropdown-item:hover {
        background: var(--gray-700);
        color: white;
    }
    
    .dropdown-header {
        background: var(--gray-700);
        border-color: var(--gray-600);
        color: var(--gray-300);
    }
    
    .breadcrumb-nav {
        padding: 0.5rem 0;
    }
    
    .breadcrumb {
        font-size: 0.875rem;
    }
}

/* Avatar Hover-Effekt */
.nav-link img,
.nav-link > div {
    transition: all 0.2s ease;
}

.nav-link:hover img,
.nav-link:hover > div {
    transform: scale(1.1);
}

/* Energy Indicator Animation */
.energy-indicator {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { 
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); 
    }
    70% { 
        box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); 
    }
    100% { 
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); 
    }
}
</style>

<!-- JavaScript für erweiterte Funktionen -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Theme Icon aktualisieren
    const savedTheme = localStorage.getItem('theme') || 'light';
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.className = savedTheme === 'dark' ? 'bi bi-sun me-2' : 'bi bi-moon-stars me-2';
    }
    
    // Dropdown Auto-Close bei Klick außerhalb
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            const dropdowns = document.querySelectorAll('.dropdown-menu.show');
            dropdowns.forEach(dropdown => {
                const bsDropdown = bootstrap.Dropdown.getOrCreateInstance(dropdown.previousElementSibling);
                bsDropdown.hide();
            });
        }
    });
    
    // Mobile Navigation verbesserungen
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (navbarToggler && navbarCollapse) {
        // Auto-close bei Link-Klick auf Mobile
        const navLinks = navbarCollapse.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 768 && navbarCollapse.classList.contains('show')) {
                    navbarToggler.click();
                }
            });
        });
    }
});

// Globale Theme-Toggle Funktion
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = current === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Icon aktualisieren
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.className = newTheme === 'dark' ? 'bi bi-sun me-2' : 'bi bi-moon-stars me-2';
    }
    
    // Feedback
    console.log('Theme switched to:', newTheme);
}
</script>