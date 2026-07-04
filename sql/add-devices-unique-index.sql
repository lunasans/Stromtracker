-- sql/add-devices-unique-index.sql
-- Verhindert doppelte Geräte pro Benutzer (Race Condition im Tasmota-Empfang).
--
-- WICHTIG: Vor dem Anlegen des Unique-Index müssen evtl. vorhandene Duplikate
-- entfernt werden, sonst schlägt ALTER TABLE fehl.

-- 1) Duplikate prüfen (nur anzeigen):
--    SELECT user_id, name, COUNT(*) c
--    FROM devices GROUP BY user_id, name HAVING c > 1;

-- 2) Duplikate bereinigen (behält jeweils die kleinste id):
--    DELETE d1 FROM devices d1
--    JOIN devices d2
--      ON d1.user_id = d2.user_id
--     AND d1.name = d2.name
--     AND d1.id > d2.id;

-- 3) Unique-Index anlegen:
ALTER TABLE devices
    ADD UNIQUE KEY uq_devices_user_name (user_id, name);
