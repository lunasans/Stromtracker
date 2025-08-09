<?php
// index.php
// Login-Seite für Stromtracker

require_once 'config/database.php';
require_once 'config/session.php';

// Bereits eingeloggt? Weiterleitung zum Dashboard
if (Auth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Login - Stromtracker';
$error = '';

// Login-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validierung
    if (empty($email) || empty($password)) {
        $error = 'Bitte geben Sie E-Mail und Passwort ein.';
    } else {
        // Login-Versuch
        if (Auth::login($email, $password)) {
            Flash::success('Login erfolgreich! Willkommen zurück.');
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Ungültige Anmeldedaten. Bitte versuchen Sie es erneut.';
        }
    }
}

// Error aus URL (z.B. von requireLogin)
if (isset($_GET['error']) && $_GET['error'] === 'login_required') {
    $error = 'Bitte melden Sie sich an, um fortzufahren.';
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .energy-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #eab308;
            animation: pulse 2s infinite;
            margin-right: 8px;
            display: inline-block;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(234, 179, 8, 0); }
            100% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
        }
        
        .btn-login {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            padding: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .form-control:focus {
            border-color: #eab308;
            box-shadow: 0 0 0 0.2rem rgba(234, 179, 8, 0.25);
        }
        
        .floating-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .floating-elements::before,
        .floating-elements::after {
            content: '⚡';
            position: absolute;
            font-size: 2rem;
            color: rgba(255,255,255,0.1);
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-elements::before {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-elements::after {
            top: 60%;
            right: 10%;
            animation-delay: 3s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body>
    <div class="floating-elements"></div>
    
    <div class="container-fluid d-flex align-items-center justify-content-center min-vh-100">
        <div class="row w-100 justify-content-center">
            <div class="col-md-6 col-lg-4">
                
                <!-- Login Card -->
                <div class="card login-card">
                    
                    <!-- Header -->
                    <div class="card-header bg-white text-center py-4">
                        <h2 class="mb-0">
                            <span class="energy-indicator"></span>
                            <span class="text-warning fw-bold">⚡ Stromtracker</span>
                        </h2>
                        <p class="text-muted mb-0">Melden Sie sich an, um fortzufahren</p>
                    </div>
                    
                    <!-- Body -->
                    <div class="card-body p-4">
                        
                        <!-- Error Message -->
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?= escape($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Login Form -->
                        <form method="POST" novalidate>
                            
                            <!-- E-Mail -->
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope"></i> E-Mail-Adresse
                                </label>
                                <input 
                                    type="email" 
                                    class="form-control form-control-lg" 
                                    id="email" 
                                    name="email" 
                                    value="<?= escape($_POST['email'] ?? 'admin@test.com') ?>"
                                    placeholder="ihre@email.com"
                                    required
                                    autofocus
                                >
                            </div>
                            
                            <!-- Passwort -->
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock"></i> Passwort
                                </label>
                                <input 
                                    type="password" 
                                    class="form-control form-control-lg" 
                                    id="password" 
                                    name="password" 
                                    placeholder="Ihr Passwort"
                                    required
                                >
                            </div>
                            
                            <!-- Login Button -->
                            <button type="submit" class="btn btn-login btn-lg w-100 text-white">
                                <i class="bi bi-box-arrow-in-right"></i>
                                Anmelden
                            </button>
                        </form>
                    </div>
                    
                    <!-- Footer -->
                    <div class="card-footer bg-light text-center">
                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Test-Anmeldedaten:</strong><br>
                                    E-Mail: <code>admin@test.com</code><br>
                                    Passwort: <code>password123</code>
                                </small>
                            </div>
                        </div>
                        
                        <hr class="my-3">
                        
                        <!-- System Status -->
                        <div class="row">
                            <div class="col-4">
                                <small class="text-success">
                                    <i class="bi bi-check-circle"></i><br>
                                    PHP
                                </small>
                            </div>
                            <div class="col-4">
                                <small class="text-success">
                                    <i class="bi bi-check-circle"></i><br>
                                    MySQL
                                </small>
                            </div>
                            <div class="col-4">
                                <small class="text-success">
                                    <i class="bi bi-check-circle"></i><br>
                                    Bootstrap
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Footer Info -->
                <div class="text-center mt-4">
                    <small class="text-white">
                        <i class="bi bi-code-slash"></i>
                        Entwickelt mit PHP & Bootstrap | © <?= date('Y') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-focus on email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if (emailField && emailField.value === '') {
                emailField.focus();
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Bitte füllen Sie alle Felder aus.');
                return false;
            }
            
            // Simple email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
                return false;
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>