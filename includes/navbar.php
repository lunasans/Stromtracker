<?php
// includes/navbar.php - Erweiterte Navigation mit Profilbild-Support
// Navigation für eingeloggte User

require_once __DIR__ . '/../config/database.php';

$user = Auth::getUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// =============================================================================
// AVATAR-FUNKTIONEN
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
 * User-Avatar rendern
 */
if (!function_exists('renderUserAvatar')) {
    function renderUserAvatar($user, $size = 'medium') {
        $sizes = [
            'small' => '28px',
            'medium' => '36px', 
            'large' => '48px',
            'xlarge' => '60px'
        ];
        
        $avatarSize = $sizes[$size] ?? $sizes['medium'];
        $imageUrl = ProfileImageHandler::getImageUrl($user['profile_image'] ?? '');
        
        if ($imageUrl) {
            return "<img src='" . htmlspecialchars($imageUrl) . "' 
                         alt='Profilbild' 
                         class='user-avatar'
                         style='width: {$avatarSize}; height: {$avatarSize}; 
                                border-radius: 50%; object-fit: cover; 
                                border: 2px solid var(--energy); 
                                box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
                                transition: all 0.3s ease;'>";
        } else {
            $initial = strtoupper(substr($user['name'] ?? explode('@', $user['email'])[0], 0, 1));
            return "<div class='user-avatar user-avatar-placeholder'
                         style='width: {$avatarSize}; height: {$avatarSize}; 
                                background: linear-gradient(135deg, var(--energy), #d97706); 
                                border-radius: 50%; display: flex; 
                                align-items: center; justify-content: center; 
                                color: white; font-weight: bold; 
                                font-size: calc({$avatarSize} * 0.45);
                                box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
                                transition: all 0.3s ease;'>{$initial}</div>";
        }
    }
}

/**
 * Escape Funktion
 */
if (!function_exists('escape')) {
    function escape($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Breadcrumb-Definitionen
$breadcrumbs = [
    'dashboard' => 'Dashboard',
    'geraete' => 'Geräte-Verwaltung',
    'zaehlerstand' => 'Zählerstände',
    'tarife' => 'Tarif-Verwaltung',
    'auswertung' => 'Auswertungen & Charts',
    'profil' => 'Mein Profil',
    'einstellungen' => 'Einstellungen'
];
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <!-- Logo/Brand -->
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <span class="energy-indicator me-2"></span>
            <i class="bi bi-lightning-charge me-2"></i>
            <span class="fw-bold">Stromtracker</span>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navigation Items -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Main Navigation -->
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
                        <i class="bi bi-house-door me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'geraete' ? 'active' : '' ?>" href="geraete.php">
                        <i class="bi bi-cpu me-1"></i> Geräte
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'zaehlerstand' ? 'active' : '' ?>" href="zaehlerstand.php">
                        <i class="bi bi-speedometer2 me-1"></i> Zählerstand
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'tarife' ? 'active' : '' ?>" href="tarife.php">
                        <i class="bi bi-tags me-1"></i> Tarife
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'auswertung' ? 'active' : '' ?>" href="auswertung.php">
                        <i class="bi bi-bar-chart me-1"></i> Auswertung
                    </a>
                </li>
            </ul>
            
            <!-- User Menu -->
            <ul class="navbar-nav">
                <!-- System Status -->
                <li class="nav-item d-flex align-items-center me-3 d-none d-lg-flex">
                    <span class="energy-indicator me-2"></span>
                    <small class="system-status-text">System aktiv</small>
                </li>
                
                <!-- User Dropdown mit Profilbild -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle user-menu-trigger d-flex align-items-center p-2" 
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        
                        <!-- Profilbild/Avatar -->
                        <div class="me-2">
                            <?= renderUserAvatar($user, 'medium') ?>
                        </div>
                        
                        <!-- User-Info (nur auf Desktop) -->
                        <div class="d-none d-lg-block text-start user-info-header">
                            <div class="user-display-name">
                                <?= escape($user['name'] ?? explode('@', $user['email'])[0]) ?>
                            </div>
                            <div class="user-display-status">
                                <i class="bi bi-circle-fill me-1"></i>Online
                            </div>
                        </div>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end user-dropdown">
                        <!-- User-Header im Dropdown -->
                        <li class="dropdown-header user-dropdown-header">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?= renderUserAvatar($user, 'large') ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="dropdown-user-name">
                                        <?= escape($user['name'] ?? 'Benutzer') ?>
                                    </div>
                                    <small class="dropdown-user-email">
                                        <?= escape($user['email']) ?>
                                    </small>
                                    <div class="d-flex align-items-center mt-1">
                                        <span class="energy-indicator me-1" style="width: 6px; height: 6px;"></span>
                                        <small class="dropdown-user-status">Online</small>
                                    </div>
                                </div>
                            </div>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Profile & Settings -->
                        <li>
                            <a class="dropdown-item" href="profil.php">
                                <i class="bi bi-person me-2 text-primary"></i> 
                                Mein Profil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="einstellungen.php">
                                <i class="bi bi-gear me-2 text-secondary"></i> 
                                Einstellungen
                            </a>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Quick Actions -->
                        <li class="dropdown-header px-3 py-2">
                            <small class="text-muted fw-semibold">SCHNELLAKTIONEN</small>
                        </li>
                        <li>
                            <a class="dropdown-item" href="zaehlerstand.php">
                                <i class="bi bi-plus-circle me-2 text-success"></i> 
                                Zählerstand erfassen
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="auswertung.php">
                                <i class="bi bi-bar-chart me-2 text-warning"></i> 
                                Auswertung anzeigen
                            </a>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Theme Toggle -->
                        <li>
                            <a class="dropdown-item" href="#" onclick="toggleTheme(); return false;">
                                <i id="themeIcon" class="bi bi-moon-stars me-2 text-info"></i> 
                                Theme wechseln
                            </a>
                        </li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Logout -->
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php" 
                               onclick="return confirm('Wirklich abmelden?')">
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

<!-- Breadcrumb Navigation -->
<?php if (isset($breadcrumbs[$currentPage])): ?>
<nav class="breadcrumb-nav py-2 bg-light border-bottom">
    <div class="container-fluid">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="dashboard.php" class="breadcrumb-link text-decoration-none">
                    <i class="bi bi-house-door me-1"></i>Home
                </a>
            </li>
            <?php if ($currentPage !== 'dashboard'): ?>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= $breadcrumbs[$currentPage] ?>
                </li>
            <?php endif; ?>
        </ol>
    </div>
</nav>
<?php endif; ?>

<!-- KORRIGIERTE CSS für perfekte Sichtbarkeit -->
<style>
/* ======================================
   NAVBAR - Alle Texte sichtbar
   ====================================== */

/* Grundlegende Navbar-Farben */
.navbar-dark {
    --bs-navbar-color: rgba(255, 255, 255, 0.9) !important;
    --bs-navbar-hover-color: rgba(255, 255, 255, 1) !important;
    --bs-navbar-disabled-color: rgba(255, 255, 255, 0.5) !important;
    --bs-navbar-active-color: rgba(255, 255, 255, 1) !important;
    --bs-navbar-brand-color: rgba(255, 255, 255, 1) !important;
    --bs-navbar-brand-hover-color: rgba(255, 255, 255, 1) !important;
}

/* Navbar Brand - immer weiß */
.navbar-brand {
    font-weight: 700 !important;
    font-size: 1.25rem !important;
    color: white !important;
    transition: all 0.3s ease !important;
}

.navbar-brand:hover,
.navbar-brand:focus {
    color: white !important;
    transform: scale(1.05);
}

/* Navigation Links - alle weiß */
.navbar-nav .nav-link {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500 !important;
    transition: all 0.3s ease !important;
    padding: 0.5rem 1rem !important;
    border-radius: 8px !important;
}

.navbar-nav .nav-link:hover {
    color: white !important;
    background: rgba(255, 255, 255, 0.1) !important;
    transform: translateY(-1px);
}

.navbar-nav .nav-link.active {
    color: white !important;
    background: rgba(245, 158, 11, 0.3) !important;
    font-weight: 600 !important;
    position: relative;
}

.navbar-nav .nav-link.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 2px;
    background: var(--energy);
    border-radius: 1px;
}

/* System Status Text - explizit weiß */
.system-status-text {
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
}

/* User Display Info - explizit weiß */
.user-display-name {
    color: white !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    line-height: 1.2 !important;
}

.user-display-status {
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 0.75rem !important;
    line-height: 1 !important;
}

.user-display-status .bi-circle-fill {
    color: #10b981 !important;
    font-size: 0.5rem !important;
}

/* User Menu Trigger */
.user-menu-trigger {
    color: white !important;
    border-radius: 12px !important;
    transition: all 0.3s ease !important;
    border: 1px solid transparent !important;
}

.user-menu-trigger:hover,
.user-menu-trigger:focus {
    color: white !important;
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
    transform: translateY(-1px) !important;
}

/* User Avatar Hover */
.user-avatar:hover {
    transform: scale(1.1) !important;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4) !important;
}

/* Dropdown Styling */
.user-dropdown {
    min-width: 320px;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    border-radius: 16px;
    padding: 0;
    margin-top: 8px;
}

.user-dropdown-header {
    padding: 1rem;
    background: linear-gradient(135deg, var(--energy), #d97706);
    color: white;
    border-radius: 16px 16px 0 0;
    margin-bottom: 0;
}

.dropdown-user-name {
    color: white !important;
    font-weight: bold !important;
    font-size: 1rem !important;
}

.dropdown-user-email {
    color: rgba(255, 255, 255, 0.8) !important;
}

.dropdown-user-status {
    color: #10b981 !important;
    font-weight: 500 !important;
}

.dropdown-item {
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    color: var(--gray-700);
}

.dropdown-item:hover {
    background: var(--gray-50);
    color: var(--gray-700);
    transform: translateX(4px);
}

.dropdown-item.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444 !important;
}

/* Energy Indicator */
.energy-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: var(--energy);
    animation: pulse 2s infinite;
    display: inline-block;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

/* Breadcrumb Navigation */
.breadcrumb-nav {
    background: var(--gray-50);
    border-bottom: 1px solid var(--gray-200);
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

/* Dark Theme Anpassungen */
[data-theme="dark"] .navbar-dark {
    background: var(--gray-800) !important;
}

[data-theme="dark"] .system-status-text,
[data-theme="dark"] .user-display-name,
[data-theme="dark"] .user-display-status,
[data-theme="dark"] .user-menu-trigger {
    color: white !important;
}

[data-theme="dark"] .breadcrumb-nav {
    background: var(--gray-800);
    border-color: var(--gray-700);
}

[data-theme="dark"] .breadcrumb-link {
    color: var(--gray-400);
}

[data-theme="dark"] .breadcrumb-item.active {
    color: var(--gray-200);
}

[data-theme="dark"] .user-dropdown {
    background: var(--gray-100);
    border-color: var(--gray-300);
}

[data-theme="dark"] .dropdown-item {
    color: var(--gray-700);
}

[data-theme="dark"] .dropdown-item:hover {
    background: var(--gray-200);
    color: var(--gray-700);
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .navbar-brand {
        font-size: 1.1rem;
    }
    
    .user-dropdown {
        min-width: 280px;
        right: 1rem !important;
        left: auto !important;
    }
    
    .nav-link {
        color: rgba(255, 255, 255, 0.9) !important;
        margin: 2px 0;
        border-radius: 6px;
    }
    
    .nav-link.active {
        background: rgba(245, 158, 11, 0.3) !important;
        color: white !important;
    }
}

@media (max-width: 576px) {
    .user-dropdown {
        min-width: 260px;
        right: 0.5rem !important;
    }
}
</style>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Theme Icon beim Laden aktualisieren
    const savedTheme = localStorage.getItem('theme') || 'light';
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.className = savedTheme === 'dark' ? 'bi bi-sun me-2 text-info' : 'bi bi-moon-stars me-2 text-info';
    }
    
    // Mobile Navigation Auto-Close
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (navbarToggler && navbarCollapse) {
        const navLinks = navbarCollapse.querySelectorAll('.nav-link:not(.dropdown-toggle)');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 768 && navbarCollapse.classList.contains('show')) {
                    navbarToggler.click();
                }
            });
        });
    }
});

// Theme Toggle Funktion
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = current === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.className = newTheme === 'dark' ? 'bi bi-sun me-2 text-info' : 'bi bi-moon-stars me-2 text-info';
    }
}
</script>