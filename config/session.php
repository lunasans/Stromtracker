<?php
// config/session.php
// Session-Management für Stromtracker

// Session starten (falls noch nicht gestartet)
if (session_status() === PHP_SESSION_NONE) {
    // Sichere Cookie-Parameter setzen (vor session_start)
    session_set_cookie_params([
        'lifetime' => 0,               // Session-Cookie (bis Browser schließt)
        'path'     => '/',
        'httponly' => true,            // Kein JS-Zugriff auf das Cookie
        'samesite' => 'Lax',           // CSRF-Schutz für Cross-Site-Requests
        // Nur über HTTPS senden, sofern verfügbar
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
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
            // Session-Fixation verhindern: neue Session-ID nach erfolgreichem Login
            session_regenerate_id(true);

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
        // Session-Daten leeren
        $_SESSION = [];

        // Session-Cookie im Browser löschen
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

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

// =============================================================================
// LOGIN THROTTLE — dateibasierter Brute-Force-Schutz pro IP (ohne DB-Schema)
// =============================================================================
class LoginThrottle {

    private const MAX_ATTEMPTS = 5;        // Erlaubte Fehlversuche pro Fenster
    private const WINDOW       = 900;      // Zeitfenster in Sekunden (15 Min)
    private const LOCKOUT      = 900;      // Sperrdauer in Sekunden (15 Min)

    private static function storePath(): string {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/login-throttle.json';
    }

    private static function key(): string {
        // IP als Schlüssel; hinter Proxy ggf. anpassen
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private static function read(): array {
        $file = self::storePath();
        if (!is_readable($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private static function write(array $data): void {
        file_put_contents(self::storePath(), json_encode($data), LOCK_EX);
    }

    /**
     * Prüft, ob die aktuelle IP gerade gesperrt ist.
     * Gibt verbleibende Sperrsekunden zurück (0 = nicht gesperrt).
     */
    public static function secondsUntilUnlock(): int {
        $data = self::read();
        $entry = $data[self::key()] ?? null;
        if ($entry && isset($entry['locked_until']) && $entry['locked_until'] > time()) {
            return (int) ($entry['locked_until'] - time());
        }
        return 0;
    }

    public static function isLocked(): bool {
        return self::secondsUntilUnlock() > 0;
    }

    /**
     * Fehlversuch registrieren; sperrt bei Überschreitung des Limits.
     */
    public static function registerFailure(): void {
        $data = self::read();
        $key = self::key();
        $now = time();
        $entry = $data[$key] ?? ['count' => 0, 'first' => $now, 'locked_until' => 0];

        // Fenster abgelaufen -> Zähler zurücksetzen
        if ($now - ($entry['first'] ?? $now) > self::WINDOW) {
            $entry = ['count' => 0, 'first' => $now, 'locked_until' => 0];
        }

        $entry['count']++;
        if ($entry['count'] >= self::MAX_ATTEMPTS) {
            $entry['locked_until'] = $now + self::LOCKOUT;
            $entry['count'] = 0;
            $entry['first'] = $now;
        }

        $data[$key] = $entry;
        self::prune($data, $now);
        self::write($data);
    }

    /**
     * Zähler nach erfolgreichem Login zurücksetzen.
     */
    public static function clear(): void {
        $data = self::read();
        unset($data[self::key()]);
        self::write($data);
    }

    /**
     * Alte/abgelaufene Einträge entfernen, damit die Datei nicht wächst.
     */
    private static function prune(array &$data, int $now): void {
        foreach ($data as $k => $e) {
            $expiredLock = ($e['locked_until'] ?? 0) < $now;
            $expiredWindow = $now - ($e['first'] ?? 0) > self::WINDOW;
            if ($expiredLock && $expiredWindow) {
                unset($data[$k]);
            }
        }
    }
}
?>