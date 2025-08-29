-- ============================================
-- API-Key Management - Datenbank Update
-- KOMPATIBEL mit allen MySQL-Versionen
-- ============================================

-- Prüfen ob api_key Spalte bereits existiert
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'api_key' 
    AND TABLE_SCHEMA = DATABASE()
);

-- API-Key Spalte nur hinzufügen wenn sie nicht existiert
SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE users ADD COLUMN api_key VARCHAR(64) NULL UNIQUE COMMENT "Persönlicher API-Key für Tasmota-Integration"',
    'SELECT "Spalte api_key bereits vorhanden" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index für schnelle API-Key Suche (nur falls nicht vorhanden)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_NAME = 'users' 
    AND INDEX_NAME = 'api_key' 
    AND TABLE_SCHEMA = DATABASE()
);

SET @sql_index = IF(@index_exists = 0, 
    'CREATE INDEX idx_users_api_key ON users(api_key)',
    'SELECT "Index für api_key bereits vorhanden" AS info'
);
PREPARE stmt_index FROM @sql_index;
EXECUTE stmt_index;
DEALLOCATE PREPARE stmt_index;

-- Bestehende NULL-Werte bereinigen (optional)
UPDATE users SET api_key = NULL WHERE api_key = '';

-- ============================================
-- ERGEBNIS ANZEIGEN
-- ============================================
SELECT 'API-Key Management erfolgreich installiert!' as status;

SELECT 
    COUNT(*) as total_users, 
    COUNT(api_key) as users_with_api_key,
    COUNT(*) - COUNT(api_key) as users_without_api_key
FROM users;

-- Spalten-Info anzeigen
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'users' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'api_key';

SELECT 'Update abgeschlossen - Alle Systeme bereit!' as final_status;