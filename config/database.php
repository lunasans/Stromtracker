<?php
// config/database.php
// Datenbankverbindung und erweiterte Database-Klasse für Stromtracker

// =============================================================================
// SYSTEM-KONFIGURATION
// =============================================================================
// Zeitzone für Deutschland setzen (MEZ/MESZ)
// date_default_timezone_set('Europe/Berlin');

// =============================================================================
// DATENBANK-KONFIGURATION
// =============================================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '123');  // Bei XAMPP standardmäßig leer
define('DB_NAME', 'stromtracker');
define('DB_CHARSET', 'utf8mb4');

// =============================================================================
// SICHERHEITS-KONFIGURATION
// =============================================================================
// CSRF-Token Geheimschlüssel (falls noch nicht vorhanden)
define('CSRF_SECRET', 'stromtracker_csrf_secret_2024');

// Session-Konfiguration
define('SESSION_LIFETIME', 3600 * 24); // 24 Stunden

// =============================================================================
// DATENBANKVERBINDUNG
// =============================================================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
}

// =============================================================================
// ERWEITERTE DATABASE HELPER CLASS
// =============================================================================
class Database {
    private static $pdo;
    
    public static function init() {
        global $pdo;
        self::$pdo = $pdo;
    }
    
    /**
     * ✅ NEUE METHODE: Direkte SQL-Ausführung
     */
    public static function execute($sql, $params = []) {
        try {
            $stmt = self::$pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database execute error: " . $e->getMessage() . " SQL: " . $sql);
            return false;
        }
    }
    
    /**
     * ✅ NEUE METHODE: SQL ausführen und betroffene Zeilen zurückgeben
     */
    public static function executeAndCount($sql, $params = []) {
        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Database executeAndCount error: " . $e->getMessage());
            return 0;
        }
    }
    
    // Einzelnen Datensatz abrufen
    public static function fetchOne($sql, $params = []) {
        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Database fetchOne error: " . $e->getMessage());
            return false;
        }
    }
    
    // Mehrere Datensätze abrufen
    public static function fetchAll($sql, $params = []) {
        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database fetchAll error: " . $e->getMessage());
            return [];
        }
    }
    
    // Daten einfügen
    public static function insert($table, $data) {
        try {
            $columns = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($data);
            
            return self::$pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database insert error: " . $e->getMessage() . " Table: {$table}");
            return false;
        }
    }
    
    // Daten aktualisieren
    public static function update($table, $data, $where, $whereParams = []) {
        try {
            $columns = array_keys($data);
            $values = array_values($data);
            $setClause = implode(' = ?, ', $columns) . ' = ?';
            
            $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
            $stmt = self::$pdo->prepare($sql);
            $allParams = array_merge($values, $whereParams);
            
            return $stmt->execute($allParams);
        } catch (PDOException $e) {
            error_log("Database update error: " . $e->getMessage() . " Table: {$table}");
            return false;
        }
    }
    
    // Daten löschen
    public static function delete($table, $where, $params = []) {
        try {
            $sql = "DELETE FROM {$table} WHERE {$where}";
            $stmt = self::$pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Database delete error: " . $e->getMessage() . " Table: {$table}");
            return false;
        }
    }
    
    // Sichere Datenbankabfrage mit Fallback
    public static function fetchSingle($sql, $params = [], $column = null, $default = 0) {
        $result = self::fetchOne($sql, $params);
        
        if ($result === false) {
            return $default;
        }
        
        if ($column !== null) {
            return $result[$column] ?? $default;
        }
        
        return $result;
    }
    
    /**
     * ✅ NEUE METHODE: Transaktion starten
     */
    public static function beginTransaction() {
        return self::$pdo->beginTransaction();
    }
    
    /**
     * ✅ NEUE METHODE: Transaktion bestätigen
     */
    public static function commit() {
        return self::$pdo->commit();
    }
    
    /**
     * ✅ NEUE METHODE: Transaktion zurückrollen
     */
    public static function rollback() {
        return self::$pdo->rollBack();
    }
    
    /**
     * ✅ NEUE METHODE: Letzten eingefügten ID abrufen
     */
    public static function lastInsertId() {
        return self::$pdo->lastInsertId();
    }
    
    /**
     * ✅ NEUE METHODE: Tabelle existiert prüfen
     */
    public static function tableExists($tableName) {
        try {
            $result = self::fetchOne(
                "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()",
                [$tableName]
            );
            return ($result['count'] ?? 0) > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * ✅ NEUE METHODE: Sichere Datenbank-Operation mit Fehlerbehandlung
     */
    public static function safeExecute($sql, $params = [], $defaultReturn = false) {
        try {
            $stmt = self::$pdo->prepare($sql);
            $result = $stmt->execute($params);
            return $result;
        } catch (PDOException $e) {
            error_log("Database safeExecute error: " . $e->getMessage() . " SQL: " . substr($sql, 0, 100));
            return $defaultReturn;
        }
    }
}

// =============================================================================
// SECURITY HELPER CLASS
// =============================================================================
class SecurityConfig {
    
    /**
     * Validiert API-Key für Tasmota-Empfang
     */
    public static function isValidApiKey($providedKey) {
        if (empty($providedKey)) {
            return false;
        }
        
        // API-Key Format prüfen (st_xxxxxxxxx...)
        if (!preg_match('/^st_[a-f0-9]{60}$/', $providedKey)) {
            return false;
        }
        
        // In Datenbank suchen
        $user = Database::fetchOne(
            "SELECT id, name, email FROM users WHERE api_key = ? AND api_key IS NOT NULL", 
            [$providedKey]
        );
        
        return $user !== false ? $user : false;
    }
    
    /**
     * Gibt alle gültigen API-Keys zurück (für Admin-Interface)
     */
    public static function getAllApiKeys() {
        return Database::fetchAll("SELECT id, name, email, api_key FROM users WHERE api_key IS NOT NULL");
    }
    
    /**
     * Generiert neuen API-Key
     */
    public static function generateApiKey($prefix = 'st_') {
        return $prefix . bin2hex(random_bytes(30));
    }
    
    /**
     * API-Key-Inhaber ermitteln
     */
    public static function getUserByApiKey($apiKey) {
        return Database::fetchOne(
            "SELECT id, name, email FROM users WHERE api_key = ?", 
            [$apiKey]
        );
    }
}

// Database-Klasse initialisieren
Database::init();

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================
function escape($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatCurrency($amount) {
    if ($amount === null || $amount === '') {
        return '0,00 €';
    }
    return number_format((float)$amount, 2, ',', '.') . ' €';
}

function formatKwh($kwh) {
    if ($kwh === null || $kwh === '') {
        return '0,00 kWh';
    }
    return number_format((float)$kwh, 2, ',', '.') . ' kWh';
}

function formatDate($date) {
    if (empty($date)) {
        return '-';
    }
    return date('d.m.Y H:i', strtotime($date));
}

function formatDateShort($date) {
    if (empty($date)) {
        return '-';
    }
    return date('d.m.Y', strtotime($date));
}

/**
 * ✅ NEUE FUNKTION: Sichere JSON-Antwort für APIs
 */
function jsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ✅ NEUE FUNKTION: Log-Eintrag erstellen
 */
function logMessage($message, $type = 'info', $file = 'app.log') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;
    
    $logDir = 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logDir . '/' . $file, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * ✅ ZEITZONE: UTC zu MEZ/MESZ konvertieren
 */
function convertUtcToLocal($utcTimestamp) {
    if (empty($utcTimestamp)) {
        return date('Y-m-d H:i:s'); // Aktuelle lokale Zeit
    }
    
    try {
        // UTC-Zeitstempel parsen
        $utcDate = new DateTime($utcTimestamp, new DateTimeZone('UTC'));
        
        // Zu lokaler Zeitzone konvertieren
        $utcDate->setTimezone(new DateTimeZone('Europe/Berlin'));
        
        return $utcDate->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Timezone conversion error: " . $e->getMessage());
        return date('Y-m-d H:i:s'); // Fallback zu aktueller lokaler Zeit
    }
}

/**
 * ✅ ZEITZONE: MEZ/MESZ zu UTC konvertieren
 */
function convertLocalToUtc($localTimestamp) {
    if (empty($localTimestamp)) {
        return gmdate('Y-m-d H:i:s'); // Aktuelle UTC-Zeit
    }
    
    try {
        // Lokalen Zeitstempel parsen
        $localDate = new DateTime($localTimestamp, new DateTimeZone('Europe/Berlin'));
        
        // Zu UTC konvertieren
        $localDate->setTimezone(new DateTimeZone('UTC'));
        
        return $localDate->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("Timezone conversion error: " . $e->getMessage());
        return gmdate('Y-m-d H:i:s'); // Fallback zu aktueller UTC-Zeit
    }
}
?>