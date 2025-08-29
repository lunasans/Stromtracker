<?php
// scripts/aggregate-tasmota-data.php
// Automatische Daten-Aggregation für Tasmota-Readings
// Läuft stündlich via Cron-Job

require_once '../config/database.php';

class TasmotaDataAggregator {
    
    /**
     * Vollständige Aggregation durchführen
     */
    public static function runAggregation() {
        $startTime = microtime(true);
        $results = [];
        
        echo "=== Tasmota Daten-Aggregation gestartet ===\n";
        echo "Zeit: " . date('Y-m-d H:i:s') . "\n\n";
        
        // 1. Stündliche Aggregate erstellen
        $results['hourly'] = self::createHourlyAggregates();
        
        // 2. Tägliche Aggregate erstellen  
        $results['daily'] = self::createDailyAggregates();
        
        // 3. Alte Rohdaten bereinigen (älter als 7 Tage)
        $results['cleanup_raw'] = self::cleanupRawData();
        
        // 4. Alte stündliche Daten bereinigen (älter als 3 Monate)
        $results['cleanup_hourly'] = self::cleanupHourlyData();
        
        // 5. Tabellen optimieren
        $results['optimize'] = self::optimizeTables();
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        echo "\n=== Aggregation abgeschlossen ===\n";
        echo "Dauer: {$duration}ms\n";
        echo "Ergebnisse:\n";
        foreach ($results as $step => $result) {
            echo "- {$step}: {$result}\n";
        }
        
        // Log-Eintrag erstellen
        self::logAggregation($results, $duration);
        
        return $results;
    }
    
    /**
     * Stündliche Aggregate erstellen
     */
    private static function createHourlyAggregates() {
        // Tabelle erstellen falls nicht vorhanden
        self::createAggregateTable('tasmota_hourly');
        
        // Letzte aggregierte Stunde finden
        $lastHour = Database::fetchOne(
            "SELECT MAX(time_bucket) as last_bucket FROM tasmota_hourly"
        )['last_bucket'] ?? '2024-01-01 00:00:00';
        
        // Stündliche Aggregate seit letzter Aggregation erstellen
        $sql = "
            INSERT INTO tasmota_hourly 
            (device_id, user_id, time_bucket, 
             avg_voltage, avg_current, avg_power, avg_power_factor,
             min_voltage, max_voltage, min_power, max_power,
             energy_start, energy_end, energy_consumed,
             data_points, first_reading, last_reading)
            SELECT 
                device_id,
                user_id,
                DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00') as time_bucket,
                
                ROUND(AVG(voltage), 2) as avg_voltage,
                ROUND(AVG(current), 3) as avg_current, 
                ROUND(AVG(power), 2) as avg_power,
                ROUND(AVG(power_factor), 3) as avg_power_factor,
                
                ROUND(MIN(voltage), 2) as min_voltage,
                ROUND(MAX(voltage), 2) as max_voltage,
                ROUND(MIN(power), 2) as min_power,
                ROUND(MAX(power), 2) as max_power,
                
                ROUND(MIN(energy_total), 3) as energy_start,
                ROUND(MAX(energy_total), 3) as energy_end,
                ROUND(MAX(energy_total) - MIN(energy_total), 3) as energy_consumed,
                
                COUNT(*) as data_points,
                MIN(timestamp) as first_reading,
                MAX(timestamp) as last_reading
                
            FROM tasmota_readings 
            WHERE timestamp > ? 
            AND timestamp < DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY device_id, user_id, DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00')
            
            ON DUPLICATE KEY UPDATE
                avg_voltage = VALUES(avg_voltage),
                avg_current = VALUES(avg_current),
                avg_power = VALUES(avg_power),
                energy_consumed = VALUES(energy_consumed),
                data_points = VALUES(data_points),
                last_reading = VALUES(last_reading)
        ";
        
        Database::execute($sql, [$lastHour]);
        
        $count = Database::fetchOne("SELECT ROW_COUNT() as count")['count'] ?? 0;
        return "Stündliche Aggregate: {$count} Datensätze erstellt";
    }
    
    /**
     * Tägliche Aggregate erstellen
     */
    private static function createDailyAggregates() {
        // Tabelle erstellen falls nicht vorhanden
        self::createAggregateTable('tasmota_daily');
        
        // Letzte aggregierte Tag finden
        $lastDay = Database::fetchOne(
            "SELECT MAX(time_bucket) as last_bucket FROM tasmota_daily"
        )['last_bucket'] ?? '2024-01-01';
        
        // Tägliche Aggregate aus stündlichen Daten erstellen
        $sql = "
            INSERT INTO tasmota_daily 
            (device_id, user_id, time_bucket,
             avg_voltage, avg_current, avg_power, avg_power_factor,
             min_voltage, max_voltage, min_power, max_power,
             energy_start, energy_end, energy_consumed,
             data_points, first_reading, last_reading, hours_active)
            SELECT 
                device_id,
                user_id,
                DATE(time_bucket) as time_bucket,
                
                ROUND(AVG(avg_voltage), 2) as avg_voltage,
                ROUND(AVG(avg_current), 3) as avg_current,
                ROUND(AVG(avg_power), 2) as avg_power,
                ROUND(AVG(avg_power_factor), 3) as avg_power_factor,
                
                ROUND(MIN(min_voltage), 2) as min_voltage,
                ROUND(MAX(max_voltage), 2) as max_voltage,
                ROUND(MIN(min_power), 2) as min_power,
                ROUND(MAX(max_power), 2) as max_power,
                
                ROUND(MIN(energy_start), 3) as energy_start,
                ROUND(MAX(energy_end), 3) as energy_end,
                ROUND(MAX(energy_end) - MIN(energy_start), 3) as energy_consumed,
                
                SUM(data_points) as data_points,
                MIN(first_reading) as first_reading,
                MAX(last_reading) as last_reading,
                COUNT(*) as hours_active
                
            FROM tasmota_hourly 
            WHERE DATE(time_bucket) > ?
            AND DATE(time_bucket) < CURDATE()
            GROUP BY device_id, user_id, DATE(time_bucket)
            
            ON DUPLICATE KEY UPDATE
                avg_voltage = VALUES(avg_voltage),
                avg_power = VALUES(avg_power),
                energy_consumed = VALUES(energy_consumed),
                data_points = VALUES(data_points),
                hours_active = VALUES(hours_active)
        ";
        
        Database::execute($sql, [$lastDay]);
        
        $count = Database::fetchOne("SELECT ROW_COUNT() as count")['count'] ?? 0;
        return "Tägliche Aggregate: {$count} Datensätze erstellt";
    }
    
    /**
     * Alte Rohdaten bereinigen (älter als 7 Tage)
     */
    private static function cleanupRawData() {
        $sql = "DELETE FROM tasmota_readings WHERE timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        Database::execute($sql);
        
        $count = Database::fetchOne("SELECT ROW_COUNT() as count")['count'] ?? 0;
        return "Rohdaten bereinigt: {$count} alte Datensätze gelöscht";
    }
    
    /**
     * Alte stündliche Daten bereinigen (älter als 3 Monate)
     */
    private static function cleanupHourlyData() {
        $sql = "DELETE FROM tasmota_hourly WHERE time_bucket < DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        Database::execute($sql);
        
        $count = Database::fetchOne("SELECT ROW_COUNT() as count")['count'] ?? 0;
        return "Stündliche Daten bereinigt: {$count} alte Datensätze gelöscht";
    }
    
    /**
     * Aggregate-Tabellen erstellen
     */
    private static function createAggregateTable($tableName) {
        if ($tableName === 'tasmota_hourly') {
            $sql = "
                CREATE TABLE IF NOT EXISTS tasmota_hourly (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    device_id INT NOT NULL,
                    user_id INT NOT NULL,
                    time_bucket DATETIME NOT NULL,
                    
                    avg_voltage DECIMAL(6,2),
                    avg_current DECIMAL(8,3),
                    avg_power DECIMAL(8,2),
                    avg_power_factor DECIMAL(4,3),
                    
                    min_voltage DECIMAL(6,2),
                    max_voltage DECIMAL(6,2),
                    min_power DECIMAL(8,2),
                    max_power DECIMAL(8,2),
                    
                    energy_start DECIMAL(12,3),
                    energy_end DECIMAL(12,3), 
                    energy_consumed DECIMAL(10,3),
                    
                    data_points INT DEFAULT 0,
                    first_reading TIMESTAMP NULL,
                    last_reading TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    
                    UNIQUE KEY unique_device_hour (device_id, time_bucket),
                    INDEX idx_time_bucket (time_bucket),
                    INDEX idx_user_time (user_id, time_bucket),
                    
                    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
        } else { // tasmota_daily
            $sql = "
                CREATE TABLE IF NOT EXISTS tasmota_daily (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    device_id INT NOT NULL,
                    user_id INT NOT NULL, 
                    time_bucket DATE NOT NULL,
                    
                    avg_voltage DECIMAL(6,2),
                    avg_current DECIMAL(8,3),
                    avg_power DECIMAL(8,2),
                    avg_power_factor DECIMAL(4,3),
                    
                    min_voltage DECIMAL(6,2),
                    max_voltage DECIMAL(6,2),
                    min_power DECIMAL(8,2),
                    max_power DECIMAL(8,2),
                    
                    energy_start DECIMAL(12,3),
                    energy_end DECIMAL(12,3),
                    energy_consumed DECIMAL(10,3),
                    
                    data_points INT DEFAULT 0,
                    hours_active INT DEFAULT 0,
                    first_reading TIMESTAMP NULL,
                    last_reading TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    
                    UNIQUE KEY unique_device_day (device_id, time_bucket),
                    INDEX idx_time_bucket (time_bucket),
                    INDEX idx_user_time (user_id, time_bucket),
                    
                    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
        }
        
        Database::execute($sql);
    }
    
    /**
     * Tabellen optimieren
     */
    private static function optimizeTables() {
        $tables = ['tasmota_readings', 'tasmota_hourly', 'tasmota_daily'];
        $optimized = 0;
        
        foreach ($tables as $table) {
            if (Database::tableExists($table)) {
                Database::execute("OPTIMIZE TABLE {$table}");
                $optimized++;
            }
        }
        
        return "Tabellen optimiert: {$optimized}";
    }
    
    /**
     * Aggregations-Log erstellen
     */
    private static function logAggregation($results, $duration) {
        $logEntry = sprintf(
            "[%s] Tasmota-Aggregation: %s (%.2fms)\n",
            date('Y-m-d H:i:s'),
            json_encode($results),
            $duration
        );
        
        $logDir = '../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logDir . '/tasmota-aggregation.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Datenbank-Statistiken anzeigen
     */
    public static function showStatistics() {
        echo "=== Tasmota Datenbank-Statistiken ===\n\n";
        
        // Rohdaten
        if (Database::tableExists('tasmota_readings')) {
            $rawStats = Database::fetchOne(
                "SELECT COUNT(*) as total, 
                        MIN(timestamp) as oldest,
                        MAX(timestamp) as newest,
                        COUNT(DISTINCT device_id) as devices
                 FROM tasmota_readings"
            );
            
            echo "📊 Rohdaten (tasmota_readings):\n";
            echo "- Datensätze: " . number_format($rawStats['total']) . "\n";
            echo "- Geräte: " . $rawStats['devices'] . "\n";
            echo "- Zeitraum: " . $rawStats['oldest'] . " bis " . $rawStats['newest'] . "\n\n";
        }
        
        // Stündliche Aggregate
        if (Database::tableExists('tasmota_hourly')) {
            $hourlyStats = Database::fetchOne(
                "SELECT COUNT(*) as total,
                        MIN(time_bucket) as oldest,
                        MAX(time_bucket) as newest
                 FROM tasmota_hourly"
            );
            
            echo "📊 Stündliche Aggregate (tasmota_hourly):\n";
            echo "- Datensätze: " . number_format($hourlyStats['total']) . "\n";
            echo "- Zeitraum: " . $hourlyStats['oldest'] . " bis " . $hourlyStats['newest'] . "\n\n";
        }
        
        // Tägliche Aggregate
        if (Database::tableExists('tasmota_daily')) {
            $dailyStats = Database::fetchOne(
                "SELECT COUNT(*) as total,
                        MIN(time_bucket) as oldest,
                        MAX(time_bucket) as newest
                 FROM tasmota_daily"
            );
            
            echo "📊 Tägliche Aggregate (tasmota_daily):\n";
            echo "- Datensätze: " . number_format($dailyStats['total']) . "\n";
            echo "- Zeitraum: " . $dailyStats['oldest'] . " bis " . $dailyStats['newest'] . "\n\n";
        }
        
        // Platzbedarf schätzen
        $totalRows = ($rawStats['total'] ?? 0) + ($hourlyStats['total'] ?? 0) + ($dailyStats['total'] ?? 0);
        $estimatedSizeMB = round($totalRows * 0.5 / 1024, 2); // ~0.5KB pro Datensatz
        
        echo "💾 Geschätzter Speicherbedarf: {$estimatedSizeMB} MB\n";
        echo "🔄 Ohne Aggregation wären es: " . round(($rawStats['total'] ?? 0) * 12 * 0.5 / 1024, 2) . " MB\n";
    }
}

// CLI-Aufruf
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'aggregate';
    
    switch ($action) {
        case 'stats':
            TasmotaDataAggregator::showStatistics();
            break;
        case 'aggregate':
        default:
            TasmotaDataAggregator::runAggregation();
            break;
    }
}
?>