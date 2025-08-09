<?php
// config/session.php
// Session-Management für Stromtracker

// Session starten (falls noch nicht gestartet)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    
    // User einloggen
    public static function login($email, $password) {
        $user = Database::fetchOne(
            "SELECT * FROM users WHERE email = ?", 
            [$email]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['logged_in'] = true;
            
            return true;
        }
        
        return false;
    }
    
    // User ausloggen
    public static function logout() {
        session_destroy();
        header('Location: index.php');
        exit;
    }
    
    // Prüfen ob User eingeloggt ist
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    // Eingeloggten User abrufen
    public static function getUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name' => $_SESSION['user_name']
        ];
    }
    
    // User-ID abrufen
    public static function getUserId() {
        return self::isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    // Login erforderlich (Redirect zu Login-Seite)
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: index.php?error=login_required');
            exit;
        }
    }
    
    // Passwort hashen
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    // CSRF-Token generieren
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // CSRF-Token validieren
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Flash-Messages (für Erfolgs-/Fehlermeldungen)
class Flash {
    
    // Message setzen
    public static function set($type, $message) {
        $_SESSION['flash'][$type] = $message;
    }
    
    // Success-Message
    public static function success($message) {
        self::set('success', $message);
    }
    
    // Error-Message
    public static function error($message) {
        self::set('error', $message);
    }
    
    // Info-Message
    public static function info($message) {
        self::set('info', $message);
    }
    
    // Messages abrufen und löschen
    public static function get($type = null) {
        if ($type) {
            $message = $_SESSION['flash'][$type] ?? null;
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }
    
    // Prüfen ob Messages vorhanden
    public static function has($type = null) {
        if ($type) {
            return isset($_SESSION['flash'][$type]);
        }
        return !empty($_SESSION['flash']);
    }
    
    // Flash-Messages als HTML ausgeben
    public static function display() {
        $messages = self::get();
        $html = '';
        
        foreach ($messages as $type => $message) {
            $alertClass = match($type) {
                'success' => 'alert-success',
                'error' => 'alert-danger',
                'info' => 'alert-info',
                default => 'alert-secondary'
            };
            
            $icon = match($type) {
                'success' => '✅',
                'error' => '❌',
                'info' => 'ℹ️',
                default => '📢'
            };
            
            $html .= "<div class='alert {$alertClass} alert-dismissible fade show' role='alert'>";
            $html .= "{$icon} " . escape($message);
            $html .= "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
            $html .= "</div>";
        }
        
        return $html;
    }
}
?>