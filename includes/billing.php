<?php
// includes/billing.php
// Abrechnungszeitraum-Logik: pro User konfigurierbarer Jahresbeginn
// (users.billing_start_day / billing_start_month, Default 1.1. = Kalenderjahr).

class BillingPeriod {

    /**
     * Abrechnungs-Einstellungen eines Users laden.
     * Fällt bei fehlenden Spalten (Migration noch nicht gelaufen) auf 1.1. zurück.
     */
    public static function getSettings(int $userId): array {
        $row = Database::fetchOne(
            "SELECT billing_start_day, billing_start_month FROM users WHERE id = ?",
            [$userId]
        );

        $day   = is_array($row) ? (int) ($row['billing_start_day'] ?? 1) : 1;
        $month = is_array($row) ? (int) ($row['billing_start_month'] ?? 1) : 1;

        // Grenzen absichern (Tag 1-28 vermeidet Schaltjahr-/Monatsend-Probleme)
        $day   = max(1, min(28, $day));
        $month = max(1, min(12, $month));

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
