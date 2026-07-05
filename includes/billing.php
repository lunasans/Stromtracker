<?php
// includes/billing.php
// Abrechnungszeitraum-Logik: pro User konfigurierbarer Jahresbeginn
// (users.billing_start_day / billing_start_month, Default 1.1. = Kalenderjahr).

class BillingPeriod {

    /**
     * Abrechnungs-Stichtag eines Users ermitteln.
     *
     * Wird direkt aus dem Beginn (valid_from) des aktiven Tarifs abgeleitet —
     * der Abrechnungszeitraum startet mit dem Vertragsbeginn und wiederholt
     * sich jährlich. Ohne aktiven Tarif gilt das Kalenderjahr (1.1.).
     */
    public static function getSettings(int $userId): array {
        $row = Database::fetchOne(
            "SELECT valid_from FROM tariff_periods
             WHERE user_id = ? AND is_active = 1
             ORDER BY valid_from DESC LIMIT 1",
            [$userId]
        );

        if (!is_array($row) || empty($row['valid_from'])) {
            return ['day' => 1, 'month' => 1]; // Kalenderjahr
        }

        $ts = strtotime($row['valid_from']);
        $day   = (int) date('j', $ts);
        $month = (int) date('n', $ts);

        // Tag 29-31 auf 28 begrenzen (vermeidet Schaltjahr-/Monatsend-Probleme
        // bei der jährlichen Fortschreibung des Stichtags)
        $day = min(28, $day);

        return ['day' => $day, 'month' => $month];
    }

    /**
     * true, wenn der Abrechnungszeitraum dem Kalenderjahr entspricht.
     */
    public static function isCalendarYear(array $settings): bool {
        return $settings['day'] === 1 && $settings['month'] === 1;
    }

    /**
     * Zeitraum, der im Jahr $startYear beginnt.
     * Liefert ['start' => 'Y-m-d', 'end' => 'Y-m-d', 'label' => '2025/26'].
     */
    public static function periodForStartYear(array $settings, int $startYear): array {
        $start = sprintf('%04d-%02d-%02d', $startYear, $settings['month'], $settings['day']);
        $end = date('Y-m-d', strtotime($start . ' +1 year -1 day'));

        return [
            'start' => $start,
            'end'   => $end,
            'label' => self::label($settings, $startYear),
        ];
    }

    /**
     * Zeitraum, in den ein Datum fällt.
     */
    public static function periodForDate(array $settings, string $date): array {
        $year = (int) date('Y', strtotime($date));
        $anniversary = sprintf('%04d-%02d-%02d', $year, $settings['month'], $settings['day']);

        $startYear = ($date < $anniversary) ? $year - 1 : $year;
        return self::periodForStartYear($settings, $startYear);
    }

    /**
     * Aktueller Abrechnungszeitraum.
     */
    public static function currentPeriod(array $settings): array {
        return self::periodForDate($settings, date('Y-m-d'));
    }

    /**
     * Anzeige-Label: "2025" (Kalenderjahr) bzw. "2025/26".
     */
    public static function label(array $settings, int $startYear): string {
        if (self::isCalendarYear($settings)) {
            return (string) $startYear;
        }
        return $startYear . '/' . str_pad((string) (($startYear + 1) % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * SQL-Ausdruck, der für eine Datumsspalte das Startjahr des
     * Abrechnungszeitraums liefert (für GROUP BY / DISTINCT).
     * Werte stammen aus validierten Integern -> injektionssicher.
     */
    public static function sqlStartYearExpr(array $settings, string $column): string {
        if (self::isCalendarYear($settings)) {
            return "YEAR({$column})";
        }
        $mmdd = sprintf("'%02d%02d'", $settings['month'], $settings['day']);
        return "(YEAR({$column}) - (DATE_FORMAT({$column}, '%m%d') < {$mmdd}))";
    }
}
