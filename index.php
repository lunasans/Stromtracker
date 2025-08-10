<?php
// index.php
// EINFACHE & SCHÖNE Login-Seite für Stromtracker

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

    <!-- Google Fonts - Inter für Konsistenz -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Simple Design System -->
    <link href="css/style.css" rel="stylesheet">

    <!-- Login-spezifische Styles -->
    <style>
        body {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background Elements */
        .bg-elements {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .bg-elements::before,
        .bg-elements::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: var(--energy);
            opacity: 0.1;
            animation: float 8s ease-in-out infinite;
        }

        .bg-elements::before {
            width: 300px;
            height: 300px;
            top: -150px;
            right: -150px;
            animation-delay: 0s;
        }

        .bg-elements::after {
            width: 200px;
            height: 200px;
            bottom: -100px;
            left: -100px;
            animation-delay: 4s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-30px) rotate(180deg);
            }
        }

        /* Login Container */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
            padding: var(--space-4);
        }

        .login-card {
            background: var(--white);
            border: none;
            border-radius: var(--radius-3xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            backdrop-filter: blur(10px);
            transition: var(--transition-smooth);
        }

        .login-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .login-header {
            background: var(--gradient-energy);
            color: white;
            padding: var(--space-8) var(--space-6);
            text-align: center;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M30 30l15-15v30l-15-15zm0 0l-15-15v30l15-15z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.1;
        }

        .brand-title {
            font-size: var(--text-2xl);
            font-weight: var(--font-bold);
            margin-bottom: var(--space-2);
            position: relative;
            z-index: 1;
        }

        .brand-subtitle {
            opacity: 0.9;
            font-size: var(--text-base);
            position: relative;
            z-index: 1;
        }

        .login-body {
            padding: var(--space-8) var(--space-6);
        }

        .form-floating {
            margin-bottom: var(--space-4);
        }

        .form-floating>.form-control {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-4) var(--space-3);
            font-size: var(--text-base);
            line-height: 1.25;
            height: auto;
            background: var(--gray-50);
            transition: all var(--transition-normal) ease;
        }

        .form-floating>.form-control:focus {
            border-color: var(--energy);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .form-floating>label {
            color: var(--gray-500);
            font-weight: var(--font-medium);
            padding: var(--space-4) var(--space-4) 0;
        }

        .btn-login {
            background: var(--gradient-energy);
            border: none;
            color: white;
            font-weight: var(--font-semibold);
            padding: var(--space-4) var(--space-6);
            border-radius: var(--radius-lg);
            font-size: var(--text-base);
            transition: var(--transition-smooth);
            width: 100%;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, var(--energy-500) 0%, var(--energy-600) 50%, var(--energy-700) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            background: var(--gray-50);
            padding: var(--space-6);
            text-align: center;
            border-top: 1px solid var(--gray-200);
        }

        .demo-credentials {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
        }

        .demo-credentials h6 {
            color: var(--energy);
            margin-bottom: var(--space-2);
        }

        .demo-credentials code {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .system-status {
            display: flex;
            justify-content: center;
            gap: var(--space-6);
            margin-top: var(--space-4);
        }

        .status-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .status-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .login-container {
                padding: var(--space-3);
            }

            .login-header {
                padding: var(--space-6) var(--space-4);
            }

            .login-body {
                padding: var(--space-6) var(--space-4);
            }

            .brand-title {
                font-size: var(--text-xl);
            }

            .system-status {
                flex-direction: column;
                gap: var(--space-3);
            }
        }

        /* Dark Theme Support */
        [data-theme="dark"] .login-card {
            background: var(--gray-800);
            color: var(--gray-100);
        }

        [data-theme="dark"] .form-floating>.form-control {
            background: var(--gray-700);
            border-color: var(--gray-600);
            color: var(--gray-100);
        }

        [data-theme="dark"] .form-floating>.form-control:focus {
            background: var(--gray-600);
        }

        [data-theme="dark"] .login-footer {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }

        [data-theme="dark"] .demo-credentials {
            background: var(--gray-700);
            border-color: var(--gray-600);
        }
    </style>
</head>

<body>
    <!-- Animated Background Elements -->
    <div class="bg-elements"></div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-card">

            <!-- Header -->
            <div class="login-header">
                <div class="brand-title">
                    <span class="energy-indicator"></span>
                    <i class="bi bi-lightning-charge me-2"></i>
                    Stromtracker
                </div>
                <div class="brand-subtitle">
                    Stromtracker
                </div>
            </div>

            <!-- Body -->
            <div class="login-body">

                <!-- Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" novalidate>

                    <!-- E-Mail -->
                    <div class="form-floating">
                        <input type="email" class="form-control" id="email" name="email" placeholder="ihre@email.com"
                            required autofocus>
                        <label for="email">
                            <i class="bi bi-envelope me-2"></i>E-Mail-Adresse
                        </label>
                    </div>

                    <!-- Passwort -->
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Ihr Passwort" required>
                        <label for="password">
                            <i class="bi bi-lock me-2"></i>Passwort
                        </label>
                    </div>

                    <!-- Login Button -->
                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Anmelden
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <div class="system-status">
                    <div class="status-item">
                        <div class="status-icon">
                            <i class="bi bi-check"></i>
                        </div>
                        <small class="text-muted">PHP <?= PHP_VERSION ?></small>
                    </div>

                    <div class="status-item">
                        <div class="status-icon">
                            <i class="bi bi-check"></i>
                        </div>
                        <small class="text-muted">MySQL</small>
                    </div>

                    <div class="status-item">
                        <div class="status-icon">
                            <i class="bi bi-check"></i>
                        </div>
                        <small class="text-muted">Bootstrap</small>
                    </div>
                </div>

                <div class="mt-4">
                    <small class="text-muted">
                        © <?= date('Y') ?> Stromtracker - Entwickelt mit ❤️ und PHP
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Login Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // Auto-focus auf erstes leeres Feld
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');

            if (emailField.value === '') {
                emailField.focus();
            } else {
                passwordField.focus();
            }

            // Form Validierung
            const form = document.querySelector('form');
            form.addEventListener('submit', function (e) {
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;

                if (!email || !password) {
                    e.preventDefault();

                    // Highlight empty fields
                    if (!email) {
                        emailField.classList.add('is-invalid');
                        emailField.focus();
                    }
                    if (!password) {
                        passwordField.classList.add('is-invalid');
                    }

                    return false;
                }

                // Simple email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    emailField.classList.add('is-invalid');
                    emailField.focus();
                    return false;
                }
            });

            // Remove invalid class on input
            [emailField, passwordField].forEach(field => {
                field.addEventListener('input', function () {
                    this.classList.remove('is-invalid');
                });
            });

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Theme detection and application
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);

        // Loading animation for submit button
        document.querySelector('form').addEventListener('submit', function () {
            const btn = document.querySelector('.btn-login');
            const originalText = btn.innerHTML;

            btn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Anmelden...';
            btn.disabled = true;

            // Re-enable if form validation fails
            setTimeout(() => {
                if (btn.disabled) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            }, 3000);
        });
    </script>
</body>

</html>