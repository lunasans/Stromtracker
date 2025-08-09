<?php
// includes/navbar.php
// Navigation für eingeloggte User
$user = Auth::getUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
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
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?= escape($user['name'] ?? $user['email']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text">
                                <small class="text-muted">Angemeldet als</small><br>
                                <strong><?= escape($user['email']) ?></strong>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="profil.php">
                                <i class="bi bi-person"></i> Profil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="einstellungen.php">
                                <i class="bi bi-gear"></i> Einstellungen
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Abmelden
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Breadcrumb (optional) -->
<nav aria-label="breadcrumb" class="bg-light py-2">
    <div class="container-fluid">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
                <a href="dashboard.php" class="text-decoration-none">
                    <i class="bi bi-house-door"></i> Home
                </a>
            </li>
            <?php
            $breadcrumbs = [
                'dashboard' => 'Dashboard',
                'geraete' => 'Geräte-Verwaltung', 
                'zaehlerstand' => 'Zählerstand erfassen',
                'tarife' => 'Tarif-Verwaltung',
                'auswertung' => 'Auswertung & Charts'
            ];
            
            if (isset($breadcrumbs[$currentPage]) && $currentPage !== 'dashboard') {
                echo '<li class="breadcrumb-item active">' . $breadcrumbs[$currentPage] . '</li>';
            }
            ?>
        </ol>
    </div>
</nav>