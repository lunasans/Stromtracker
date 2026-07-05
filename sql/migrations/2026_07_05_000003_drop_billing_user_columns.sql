-- 2026_07_05_000003_drop_billing_user_columns.sql
-- Abrechnungszeitraum wird jetzt aus dem Beginn des aktiven Tarifs
-- (tariff_periods.valid_from) abgeleitet — die separaten User-Spalten
-- aus Migration 000002 entfallen.

ALTER TABLE users DROP COLUMN billing_start_day;

ALTER TABLE users DROP COLUMN billing_start_month;
