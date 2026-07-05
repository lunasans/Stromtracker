-- 2026_07_05_000002_billing_period_settings.sql
-- Konfigurierbarer Abrechnungszeitraum pro Benutzer.
-- Default 1.1. = Kalenderjahr -> bestehendes Verhalten bleibt unverändert.

ALTER TABLE users
  ADD COLUMN billing_start_day TINYINT NOT NULL DEFAULT 1 COMMENT 'Tag des Abrechnungsbeginns (1-28)';

ALTER TABLE users
  ADD COLUMN billing_start_month TINYINT NOT NULL DEFAULT 1 COMMENT 'Monat des Abrechnungsbeginns (1-12)';
