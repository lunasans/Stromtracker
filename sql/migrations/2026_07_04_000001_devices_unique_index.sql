-- 2026_07_04_000001_devices_unique_index.sql
-- Entfernt doppelte Geräte (behält jeweils die kleinste id) und erzwingt
-- danach Eindeutigkeit pro Benutzer. Wird vom Update-Runner einmalig angewendet.

DELETE d1 FROM devices d1
JOIN devices d2
  ON d1.user_id = d2.user_id
 AND d1.name = d2.name
 AND d1.id > d2.id;

ALTER TABLE devices
  ADD UNIQUE KEY uq_devices_user_name (user_id, name);
