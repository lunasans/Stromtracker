<?php
// admin-update.php
// Admin-Update-Konsole (nur User-ID 1):
//  - Setup-Checks
//  - Code-Update (git pull)
//  - DB-Migrationen (nachvollziehbar via schema_migrations)
//  - Deploy-Housekeeping

require_once 'config/database.php';
require_once 'config/session.php';

// -----------------------------------------------------------------------------
// Zugriffsschutz: nur eingeloggter Admin (User-ID 1)
// -----------------------------------------------------------------------------
Auth::requireLogin();
if ((int) Auth::getUserId() !== 1) {
    http_response_code(403);
    die('Admin-Rechte erforderlich (nur Benutzer mit ID 1).');
}

$projectRoot = __DIR__;

// =============================================================================
// AKTIONEN
// =============================================================================

/**
 * Code-Update per git pull (portabel über git -C, ohne cd).
 */
function actionGitPull(string $root): array {
    if (!function_exists('exec')) {
        return ['ok' => false, 'lines' => ['exec() ist auf diesem Server deaktiviert – git pull nicht möglich.']];
    }
    if (!is_dir($root . '/.git')) {
        return ['ok' => false, 'lines' => ['Kein Git-Repository gefunden (.git fehlt).']];
    }
    $out = [];
    $code = 0;
    exec('git -C ' . escapeshellarg($root) . ' pull 2>&1', $out, $code);
    if (empty($out)) {
        $out[] = '(keine Ausgabe)';
    }
    return ['ok' => $code === 0, 'lines' => $out];
}

/**
 * Ausstehende DB-Migrationen aus sql/migrations/ anwenden.
 */
function actionMigrate(string $root): array {
    $lines = [];
    $dir = $root . '/sql/migrations';

    // Tracking-Tabelle sicherstellen
    Database::execute(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            filename VARCHAR(255) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL
        )"
    );

    $applied = array_column(
        Database::fetchAll("SELECT filename FROM schema_migrations"),
        'filename'
    );

    $files = glob($dir . '/*.sql') ?: [];
    sort($files);

    if (empty($files)) {
        return ['ok' => true, 'lines' => ['Keine Migrationsdateien in sql/migrations/.']];
    }

    $ok = true;
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) {
            $lines[] = "⏭️  {$name} – bereits angewendet";
            continue;
        }

        $statements = parseSqlStatements((string) file_get_contents($file));
        $migrationOk = true;

        foreach ($statements as $stmt) {
            try {
                Database::rawExec($stmt);
            } catch (PDOException $e) {
                $driverCode = (int) ($e->errorInfo[1] ?? 0);
                // Tolerierbar: Objekt existiert bereits (idempotentes Re-Run)
                //   1050 = Tabelle existiert, 1060 = Spalte existiert, 1061 = Index existiert
                if (in_array($driverCode, [1050, 1060, 1061], true)) {
                    $lines[] = "   ↷ übersprungen (existiert bereits): " . shortStmt($stmt);
                    continue;
                }
                $migrationOk = false;
                $ok = false;
                $lines[] = "❌ {$name} – Fehler [{$driverCode}]: " . $e->getMessage();
                break;
            }
        }

        if ($migrationOk) {
            Database::insert('schema_migrations', [
                'filename'   => $name,
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
            $lines[] = "✅ {$name} – angewendet";
        } else {
            $lines[] = "⛔ Abbruch – behebe den Fehler und starte erneut.";
            break;
        }
    }

    return ['ok' => $ok, 'lines' => $lines];
}

/**
 * Deploy-Housekeeping: große Debug-Logs kürzen, OPcache leeren.
 */
function actionHousekeeping(string $root): array {
    $lines = [];

    $debug = $root . '/logs/tasmota-debug.log';
    if (is_file($debug)) {
        $size = (int) filesize($debug);
        if ($size > 2 * 1024 * 1024) { // > 2 MB
            file_put_contents($debug, '');
            $lines[] = "🧹 tasmota-debug.log geleert (war " . round($size / 1048576, 1) . " MB)";
        } else {
            $lines[] = "ℹ️ tasmota-debug.log: " . round($size / 1024) . " KB (ok)";
        }
    } else {
        $lines[] = "ℹ️ Keine tasmota-debug.log vorhanden";
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
        $lines[] = "🔄 OPcache zurückgesetzt";
    } else {
        $lines[] = "ℹ️ OPcache nicht aktiv";
    }

    return ['ok' => true, 'lines' => $lines];
}

// -----------------------------------------------------------------------------
// Hilfsfunktionen
// -----------------------------------------------------------------------------

/**
 * Zerlegt eine .sql-Datei in einzelne Statements (Kommentarzeilen entfernt).
 * Bewusst simpel gehalten – Migrationen sollten keine ';' in Strings enthalten.
 */
function parseSqlStatements(string $sql): array {
    $noComments = [];
    foreach (preg_split('/\r?\n/', $sql) as $line) {
        $trim = ltrim($line);
        if ($trim === '' || str_starts_with($trim, '--')) {
            continue;
        }
        $noComments[] = $line;
    }
    $joined = implode("\n", $noComments);

    $statements = [];
    foreach (explode(';', $joined) as $part) {
        $part = trim($part);
        if ($part !== '') {
            $statements[] = $part;
        }
    }
    return $statements;
}

function shortStmt(string $stmt): string {
    $stmt = preg_replace('/\s+/', ' ', $stmt);
    return mb_strlen($stmt) > 60 ? mb_substr($stmt, 0, 60) . '…' : $stmt;
}

/**
 * Anzahl noch nicht angewendeter Migrationen (für die Übersicht).
 */
function pendingMigrationCount(string $root): int {
    $dir = $root . '/sql/migrations';
    $files = glob($dir . '/*.sql') ?: [];
    if (empty($files)) {
        return 0;
    }
    if (!Database::tableExists('schema_migrations')) {
        return count($files);
    }
    $applied = array_column(
        Database::fetchAll("SELECT filename FROM schema_migrations"),
        'filename'
    );
    $pending = 0;
    foreach ($files as $f) {
        if (!in_array(basename($f), $applied, true)) {
            $pending++;
        }
    }
    return $pending;
}

// =============================================================================
// POST-VERARBEITUNG
// =============================================================================
$results = [];   // [ ['title'=>..., 'ok'=>bool, 'lines'=>[]] ]
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $formError = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.';
    } else {
        if (!empty($_POST['do_gitpull'])) {
            $r = actionGitPull($projectRoot);
            $results[] = ['title' => 'Code-Update (git pull)'] + $r;
        }
        if (!empty($_POST['do_migrate'])) {
            $r = actionMigrate($projectRoot);
            $results[] = ['title' => 'DB-Migrationen'] + $r;
        }
        if (!empty($_POST['do_housekeeping'])) {
            $r = actionHousekeeping($projectRoot);
            $results[] = ['title' => 'Housekeeping'] + $r;
        }
        if (empty($results)) {
            $formError = 'Keine Aktion ausgewählt.';
        }
    }
}

// =============================================================================
// SETUP-CHECKS (read-only, immer angezeigt)
// =============================================================================
$checks = [];
$checks[] = ['.env vorhanden', is_readable($projectRoot . '/.env')];
$checks[] = ['logs/ beschreibbar', is_writable($projectRoot . '/logs')];
$checks[] = ['PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>=')];
$checks[] = ['DB-Verbindung', isset($pdo) && $pdo instanceof PDO];
$checks[] = ['Tabelle users', Database::tableExists('users')];
$checks[] = ['Tabelle devices', Database::tableExists('devices')];
$checks[] = ['exec() verfügbar (git pull)', function_exists('exec')];

$pending = pendingMigrationCount($projectRoot);
$csrf = htmlspecialchars(Auth::generateCSRFToken());
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Update – Stromtracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: system-ui, sans-serif; padding: 2rem 1rem; }
        .card { background: #1e293b; border: 1px solid #334155; }
        pre { background: #020617; color: #22c55e; padding: 1rem; border-radius: .5rem; white-space: pre-wrap; word-break: break-word; }
        .check-ok { color: #22c55e; } .check-bad { color: #ef4444; }
        h1 { font-size: 1.5rem; } h2 { font-size: 1.15rem; }
    </style>
</head>
<body>
<div class="container" style="max-width: 860px;">
    <h1 class="mb-1"><i class="bi bi-arrow-repeat"></i> Admin-Update</h1>
    <p class="text-secondary">Angemeldet als <?= htmlspecialchars(Auth::getUser()['email']) ?></p>

    <?php if ($formError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($formError) ?></div>
    <?php endif; ?>

    <!-- Setup-Checks -->
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="mb-3"><i class="bi bi-clipboard-check"></i> Setup-Checks</h2>
            <ul class="list-unstyled mb-0">
                <?php foreach ($checks as [$label, $pass]): ?>
                    <li>
                        <?php if ($pass): ?>
                            <span class="check-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($label) ?></span>
                        <?php else: ?>
                            <span class="check-bad"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($label) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-3 mb-0">
                <i class="bi bi-database"></i> Ausstehende Migrationen:
                <strong class="<?= $pending > 0 ? 'text-warning' : 'text-success' ?>"><?= $pending ?></strong>
            </p>
        </div>
    </div>

    <!-- Aktionen -->
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="mb-3"><i class="bi bi-lightning-charge"></i> Update ausführen</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="do_gitpull" id="do_gitpull" value="1" checked>
                    <label class="form-check-label" for="do_gitpull">Code aktualisieren (git pull)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="do_migrate" id="do_migrate" value="1" checked>
                    <label class="form-check-label" for="do_migrate">DB-Migrationen anwenden</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="do_housekeeping" id="do_housekeeping" value="1">
                    <label class="form-check-label" for="do_housekeeping">Housekeeping (Logs kürzen, OPcache)</label>
                </div>
                <button type="submit" class="btn btn-warning"
                        onclick="return confirm('Update jetzt ausführen?');">
                    <i class="bi bi-play-fill"></i> Ausführen
                </button>
            </form>
        </div>
    </div>

    <!-- Ergebnisse -->
    <?php if (!empty($results)): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="mb-3"><i class="bi bi-terminal"></i> Ergebnis</h2>
                <?php foreach ($results as $res): ?>
                    <h3 class="h6 <?= $res['ok'] ? 'text-success' : 'text-danger' ?>">
                        <?= $res['ok'] ? '✅' : '❌' ?> <?= htmlspecialchars($res['title']) ?>
                    </h3>
                    <pre><?= htmlspecialchars(implode("\n", $res['lines'])) ?></pre>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <p><a href="dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left"></i> Zurück zum Dashboard</a></p>
</div>
</body>
</html>
