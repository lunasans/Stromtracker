<?php
// config/env.php
// Minimaler .env-Loader ohne externe Abhängigkeiten (für die reine PHP-App).
// Parst eine .env-Datei im Projekt-Root und stellt Werte über env() bereit.

if (!function_exists('loadEnv')) {
    /**
     * Lädt die .env-Datei einmalig in $_ENV / getenv().
     *
     * Suchreihenfolge (erste lesbare Datei gewinnt):
     *   1. EINE Ebene ÜBER dem Projekt-Root (außerhalb des Web-Roots —
     *      empfohlen für Produktion, da so kein Webserver die Datei
     *      ausliefern kann, egal ob Apache oder nginx)
     *   2. Projekt-Root selbst (lokale Entwicklung)
     */
    function loadEnv(?string $path = null): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $projectRoot = dirname(__DIR__);
        $candidates = $path !== null
            ? [$path]
            : [dirname($projectRoot) . '/.env', $projectRoot . '/.env'];

        $path = null;
        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                $path = $candidate;
                break;
            }
        }
        if ($path === null) {
            return; // Keine .env vorhanden -> es greifen die Fallbacks in env()
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Kommentare und leere Zeilen überspringen
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Umschließende Anführungszeichen entfernen
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($name === '') {
                continue;
            }

            // Bereits gesetzte echte Umgebungsvariablen nicht überschreiben
            if (getenv($name) === false) {
                putenv("{$name}={$value}");
            }
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

if (!function_exists('env')) {
    /**
     * Liest eine Umgebungsvariable mit optionalem Fallback.
     * Wandelt gängige Literale (true/false/null) um.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

// .env direkt beim Einbinden laden
loadEnv();
