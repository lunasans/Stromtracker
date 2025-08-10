-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 10. Aug 2025 um 11:08
-- Server-Version: 5.7.17
-- PHP-Version: 8.3.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `stromtracker`
--

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `daily_consumption`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `daily_consumption` (
`user_name` varchar(255)
,`date` date
,`total_kwh` decimal(32,2)
,`total_cost` decimal(32,2)
,`readings` bigint(21)
);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `energy_consumption`
--

CREATE TABLE `energy_consumption` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `consumption` decimal(10,2) NOT NULL COMMENT 'Verbrauch in kWh',
  `cost` decimal(10,2) NOT NULL COMMENT 'Kosten in Euro',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `energy_rates`
--

CREATE TABLE `energy_rates` (
  `id` int(11) NOT NULL,
  `rate` decimal(10,4) NOT NULL COMMENT 'Preis pro kWh in Euro',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `provider` varchar(255) DEFAULT 'Standard',
  `tariff_name` varchar(255) DEFAULT 'Grundtarif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `meter_readings`
--

CREATE TABLE `meter_readings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reading_date` date NOT NULL,
  `meter_value` decimal(10,2) NOT NULL COMMENT 'Zählerstand in kWh',
  `consumption` decimal(10,2) DEFAULT NULL COMMENT 'Berechneter Verbrauch seit letzter Ablesung',
  `cost` decimal(10,2) DEFAULT NULL COMMENT 'Berechnete Kosten',
  `rate_per_kwh` decimal(10,4) DEFAULT NULL COMMENT 'Strompreis zum Zeitpunkt der Ablesung',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `monthly_payment` decimal(10,2) DEFAULT NULL COMMENT 'Monatlicher Abschlag zum Zeitpunkt der Ablesung',
  `basic_fee` decimal(10,2) DEFAULT NULL COMMENT 'Grundgebühr zum Zeitpunkt der Ablesung',
  `total_bill` decimal(10,2) DEFAULT NULL COMMENT 'Gesamtrechnung (Verbrauch + Grundgebühr)',
  `payment_difference` decimal(10,2) DEFAULT NULL COMMENT 'Differenz zwischen Abschlag und tatsächlichen Kosten'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `monthly_consumption`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `monthly_consumption` (
);

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `payment_analysis`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `payment_analysis` (
`user_id` int(11)
,`reading_date` date
,`year` int(4)
,`month` int(2)
,`month_name` varchar(9)
,`consumption` decimal(10,2)
,`energy_cost` decimal(10,2)
,`basic_fee` decimal(10,2)
,`total_bill` decimal(10,2)
,`planned_payment` decimal(10,2)
,`payment_difference` decimal(10,2)
,`provider_name` varchar(255)
,`tariff_name` varchar(255)
,`rate_per_kwh` decimal(10,4)
);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tariff_periods`
--

CREATE TABLE `tariff_periods` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `rate_per_kwh` decimal(10,4) NOT NULL COMMENT 'Arbeitspreis pro kWh in Euro',
  `monthly_payment` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Monatlicher Abschlag in Euro',
  `basic_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Monatliche Grundgebühr in Euro',
  `provider_name` varchar(255) DEFAULT 'Stromversorger',
  `tariff_name` varchar(255) DEFAULT 'Haushaltsstrom',
  `customer_number` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `yearly_billing_forecast`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `yearly_billing_forecast` (
`user_id` int(11)
,`year` int(4)
,`readings_count` bigint(21)
,`actual_total_cost` decimal(32,2)
,`actual_energy_cost` decimal(32,2)
,`actual_basic_fees` decimal(32,2)
,`total_payments` decimal(32,2)
,`total_difference` decimal(32,2)
,`avg_monthly_consumption` decimal(14,6)
,`avg_monthly_cost` decimal(14,6)
,`avg_monthly_payment` decimal(14,6)
,`projected_yearly_cost` decimal(38,6)
,`projected_yearly_payments` decimal(38,6)
);

-- --------------------------------------------------------

--
-- Struktur des Views `daily_consumption`
--
DROP TABLE IF EXISTS `daily_consumption`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_consumption`  AS SELECT `u`.`name` AS `user_name`, cast(`ec`.`timestamp` as date) AS `date`, sum(`ec`.`consumption`) AS `total_kwh`, sum(`ec`.`cost`) AS `total_cost`, count(0) AS `readings` FROM (`energy_consumption` `ec` join `users` `u` on((`ec`.`user_id` = `u`.`id`))) GROUP BY `u`.`id`, cast(`ec`.`timestamp` as date) ORDER BY `date` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `monthly_consumption`
--
DROP TABLE IF EXISTS `monthly_consumption`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `monthly_consumption`  AS SELECT `u`.`name` AS `user_name`, `d`.`name` AS `device_name`, `d`.`category` AS `category`, year(`ec`.`timestamp`) AS `year`, month(`ec`.`timestamp`) AS `month`, sum(`ec`.`consumption`) AS `total_kwh`, sum(`ec`.`cost`) AS `total_cost`, count(0) AS `readings` FROM ((`energy_consumption` `ec` join `users` `u` on((`ec`.`user_id` = `u`.`id`))) left join `devices` `d` on((`ec`.`device_id` = `d`.`id`))) GROUP BY `u`.`id`, `d`.`id`, year(`ec`.`timestamp`), month(`ec`.`timestamp`) ORDER BY `year` DESC, `month` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `payment_analysis`
--
DROP TABLE IF EXISTS `payment_analysis`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `payment_analysis`  AS SELECT `mr`.`user_id` AS `user_id`, `mr`.`reading_date` AS `reading_date`, year(`mr`.`reading_date`) AS `year`, month(`mr`.`reading_date`) AS `month`, monthname(`mr`.`reading_date`) AS `month_name`, `mr`.`consumption` AS `consumption`, `mr`.`cost` AS `energy_cost`, `mr`.`basic_fee` AS `basic_fee`, `mr`.`total_bill` AS `total_bill`, `mr`.`monthly_payment` AS `planned_payment`, `mr`.`payment_difference` AS `payment_difference`, `tp`.`provider_name` AS `provider_name`, `tp`.`tariff_name` AS `tariff_name`, `tp`.`rate_per_kwh` AS `rate_per_kwh` FROM (`meter_readings` `mr` left join `tariff_periods` `tp` on(((`mr`.`user_id` = `tp`.`user_id`) and (`mr`.`reading_date` >= `tp`.`valid_from`) and (isnull(`tp`.`valid_to`) or (`mr`.`reading_date` <= `tp`.`valid_to`))))) WHERE (`mr`.`consumption` is not null) ORDER BY `mr`.`user_id` ASC, `mr`.`reading_date` DESC ;

-- --------------------------------------------------------

--
-- Struktur des Views `yearly_billing_forecast`
--
DROP TABLE IF EXISTS `yearly_billing_forecast`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `yearly_billing_forecast`  AS SELECT `meter_readings`.`user_id` AS `user_id`, year(`meter_readings`.`reading_date`) AS `year`, count(0) AS `readings_count`, sum(coalesce(`meter_readings`.`total_bill`,0)) AS `actual_total_cost`, sum(coalesce(`meter_readings`.`cost`,0)) AS `actual_energy_cost`, sum(coalesce(`meter_readings`.`basic_fee`,0)) AS `actual_basic_fees`, sum(coalesce(`meter_readings`.`monthly_payment`,0)) AS `total_payments`, sum(coalesce(`meter_readings`.`payment_difference`,0)) AS `total_difference`, avg(coalesce(`meter_readings`.`consumption`,0)) AS `avg_monthly_consumption`, avg(coalesce(`meter_readings`.`total_bill`,0)) AS `avg_monthly_cost`, avg(coalesce(`meter_readings`.`monthly_payment`,0)) AS `avg_monthly_payment`, (case when (count(0) < 12) then ((sum(coalesce(`meter_readings`.`total_bill`,0)) / count(0)) * 12) else sum(coalesce(`meter_readings`.`total_bill`,0)) end) AS `projected_yearly_cost`, (case when (count(0) < 12) then ((sum(coalesce(`meter_readings`.`monthly_payment`,0)) / count(0)) * 12) else sum(coalesce(`meter_readings`.`monthly_payment`,0)) end) AS `projected_yearly_payments` FROM `meter_readings` WHERE (`meter_readings`.`consumption` is not null) GROUP BY `meter_readings`.`user_id`, year(`meter_readings`.`reading_date`) ORDER BY `meter_readings`.`user_id` ASC, `year` DESC ;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `energy_consumption`
--
ALTER TABLE `energy_consumption`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `idx_user_timestamp` (`user_id`,`timestamp`);

--
-- Indizes für die Tabelle `energy_rates`
--
ALTER TABLE `energy_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `meter_readings`
--
ALTER TABLE `meter_readings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_month` (`user_id`,`reading_date`),
  ADD KEY `idx_user_date` (`user_id`,`reading_date`);

--
-- Indizes für die Tabelle `tariff_periods`
--
ALTER TABLE `tariff_periods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_valid` (`user_id`,`valid_from`,`valid_to`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `energy_consumption`
--
ALTER TABLE `energy_consumption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `energy_rates`
--
ALTER TABLE `energy_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `meter_readings`
--
ALTER TABLE `meter_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tariff_periods`
--
ALTER TABLE `tariff_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
