<?php
// includes/navbar.php - Navigation mit Profilbild-Support
// Styles liegen zentral in css/style.css (Abschnitt 6)

require_once __DIR__ . '/../config/database.php';

$user = Auth::getUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Profilbild aus DB laden — die Session enthält es nicht
// (fällt bei Fehler/fehlender Spalte still auf den Initial-Avatar zurück)
if (is_array($user) && !empty($user['id'])) {
    $profileRow = Database::fetchOne(
        "SELECT profile_image FROM users WHERE id = ?",
        [$user['id']]
    );
    if (is_array($profileRow) && !empty($profileRow['profile_image'])) {
        $user['profile_image'] = $profileRow['profile_image'];
    }
}

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

            // Nur Dateiname zulassen (kein Pfad-Traversal über DB-Wert)
            $filename = basename($filename);

            $filepath = self::$uploadDir . $filename;
            if (file_exists($filepath)) {
                return $filepath . '?v=' . filemtime($filepath); // Cache-busting
            }

            return null;
        }
    }
}

if (!function_exists('escape')) {
    function escape($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * User-Avatar rendern (Bild oder Initial-Platzhalter)
 */
if (!function_exists('renderUserAvatar')) {
    function renderUserAvatar($user, $size = 'medium') {
        $sizes = [
            'small'  => '28px',
            'medium' => '36px',
            'large'  => '48px',
            'xlarge' => '60px',
        ];

        $avatarSize = $sizes[$size] ?? $sizes['medium'];
        $imageUrl = ProfileImageHandler::getImageUrl($user['profile_image'] ?? '');

        if ($imageUrl) {
            return "<img src='" . escape($imageUrl) . "'
                         alt='Profilbild'
                         class='user-avatar'
                         style='width: {$avatarSize}; height: {$avatarSize};'>";
        }

        // Initial UTF-8-sicher ermitteln (Umlaute!) und escapen
        $source = trim($user['name'] ?? '') !== ''
            ? trim($user['name'])
            : explode('@', $user['email'] ?? 'U')[0];
        $initial = escape(mb_strtoupper(mb_substr($source, 0, 1, 'UTF-8'), 'UTF-8'));

        return "<div class='user-avatar user-avatar-placeholder'
                     style='width: {$avatarSize}; height: {$avatarSize};
                            font-size: calc({$avatarSize} * 0.45);'>{$initial}</div>";
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
    'einstellungen' => 'Einstellungen',
];

// Hauptnavigation
$navItems = [
    'dashboard'    => ['bi-house-door',   'Dashboard'],
    'geraete'      => ['bi-cpu',          'Geräte'],
    'zaehlerstand' => ['bi-speedometer2', 'Zählerstand'],
    'tarife'       => ['bi-tags',         'Tarife'],
    'auswertung'   => ['bi-bar-chart',    'Auswertung'],
];
?>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container-fluid">
        <!-- Logo/Brand -->
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <span class="energy-indicator me-2"></span>
            <i class="bi bi-lightning-charge me-2"></i>
            <span class="fw-bold">Stromtracker</span>
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Navigation umschalten">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Items -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Main Navigation -->
            <ul class="navbar-nav me-auto">
                <?php foreach ($navItems as $page => [$icon, $label]): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === $page ? 'active' : '' ?>"
                           <?= $currentPage === $page ? 'aria-current="page"' : '' ?>
                           href="<?= $page ?>.php">
                            <i class="bi <?= $icon ?> me-1"></i> <?= $label ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- User Menu -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle user-menu-trigger d-flex align-items-center p-2"
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">

                        <div class="me-2">
                            <?= renderUserAvatar($user, 'medium') ?>
                        </div>

                        <div class="d-none d-lg-block text-start user-info-header">
                            <div class="user-display-name">
                                <?= escape($user['name'] ?? explode('@', $user['email'] ?? '')[0]) ?>
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
                                        <?= escape($user['email'] ?? '') ?>
                                    </small>
                                </div>
                            </div>
                        </li>

                        <li><hr class="dropdown-divider"></li>

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

                        <li>
                            <a class="dropdown-item" href="#" onclick="toggleTheme(); return false;">
                                <i id="themeIcon" class="bi bi-moon-stars me-2 text-info"></i>
                                Theme wechseln
                            </a>
                        </li>

                        <li><hr class="dropdown-divider"></li>

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
<nav class="breadcrumb-nav py-2">
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

<script>
// Mobile Navigation: nach Klick auf einen Link automatisch einklappen
document.addEventListener('DOMContentLoaded', function() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');

    if (navbarToggler && navbarCollapse) {
        navbarCollapse.querySelectorAll('.nav-link:not(.dropdown-toggle)').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 768 && navbarCollapse.classList.contains('show')) {
                    navbarToggler.click();
                }
            });
        });
    }
});
</script>
