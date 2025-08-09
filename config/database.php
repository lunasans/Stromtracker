<?php
// config/database.php
// Datenbankverbindung für Stromtracker

// Datenbank-Konfiguration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Bei XAMPP standardmäßig leer
define('DB_NAME', 'stromtracker');
define('DB_CHARSET', 'utf8mb4');

// Globale Datenbankverbindung
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

// Hilfsfunktionen für Datenbankoperationen
class Database {
    private static $pdo;
    
    public static function init() {
        global $pdo;
        self::$pdo = $pdo;
    }
    
    // Einzelnen Datensatz abrufen
    public static function fetchOne($sql, $params = []) {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    // Mehrere Datensätze abrufen
    public static function fetchAll($sql, $params = []) {
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Daten einfügen
    public static function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($data);
        
        return self::$pdo->lastInsertId();
    }
    
    // Daten aktualisieren (einfache Positional-Parameter-Version)
    public static function update($table, $data, $where, $whereParams = []) {
        $columns = array_keys($data);
        $values = array_values($data);
        
        // SET-Klausel mit ? erstellen
        $setClause = implode(' = ?, ', $columns) . ' = ?';
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $stmt = self::$pdo->prepare($sql);
        
        // Parameter zusammenführen: SET-Werte + WHERE-Parameter
        $allParams = array_merge($values, $whereParams);
        
        return $stmt->execute($allParams);
    }
    
    // Daten löschen
    public static function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = self::$pdo->prepare($sql);
        return $stmt->execute($params);
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
}

// Database-Klasse initialisieren
Database::init();

// Utility-Funktionen (PHP 8+ kompatibel)
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
?>