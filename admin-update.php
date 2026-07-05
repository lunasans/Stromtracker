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
 *
 * $force = true verwirft vorher alle lokalen Änderungen an getrackten
 * Dateien (git fetch + reset --hard auf den Origin-Stand). Nötig, wenn
 * per FTP hochgeladene Dateien den Pull blockieren ("would be overwritten").
 * Untracked Dateien (.env, uploads/, logs/) bleiben unberührt.
 */
function actionGitPull(string $root, bool $force = false): array {
    if (!function_exists('exec')) {
        return ['ok' => false, 'lines' => ['exec() ist auf diesem Server deaktiviert – git pull nicht möglich.']];
    }
    if (!is_dir($root . '/.git')) {
        return ['ok' => false, 'lines' => ['Kein Git-Repository gefunden (.git fehlt).']];
    }

    $rootArg = escapeshellarg($root);
    $lines = [];

    if ($force) {
        // Aktuellen Branch ermitteln
        $branchOut = [];
        $code = 0;
        exec("git -C {$rootArg} rev-parse --abbrev-ref HEAD 2>&1", $branchOut, $code);
        $branch = trim($branchOut[0] ?? '');
        if ($code !== 0 || $branch === '' || $branch === 'HEAD') {
            return ['ok' => false, 'lines' => array_merge(['Branch konnte nicht ermittelt werden:'], $branchOut)];
        }
        $lines[] = "Branch: {$branch}";

        $out = [];
        exec("git -C {$rootArg} fetch origin 2>&1", $out, $code);
        $lines = array_merge($lines, $out);
        if ($code !== 0) {
            return ['ok' => false, 'lines' => array_merge($lines, ['git fetch fehlgeschlagen.'])];
        }

        $out = [];
        exec("git -C {$rootArg} reset --hard " . escapeshellarg("origin/{$branch}") . " 2>&1", $out, $code);
        $lines = array_merge($lines, $out);
        if (empty($out)) {
            $lines[] = '(keine Ausgabe)';
        }
        return ['ok' => $code === 0, 'lines' => $lines];
    }

    $out = [];
    $code = 0;
    exec("git -C {$rootArg} pull 2>&1", $out, $code);
    if (empty($out)) {
        $out[] = '(keine Ausgabe)';
    }
    if ($code !== 0 && stripos(implode("\n", $out), 'overwritten by merge') !== false) {
        $out[] = '';
        $out[] = '💡 Lokale Datei-Änderungen (z.B. FTP-Uploads) blockieren den Pull.';
        $out[] = '   Aktiviere die Option "Lokale Änderungen verwerfen" und führe das Update erneut aus.';
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
                // Tolerierbar (idempotentes Re-Run):
                //   1050 = Tabelle existiert, 1060 = Spalte existiert,
                //   1061 = Index existiert, 1091 = Spalte/Index existiert nicht (DROP)
                if (in_array($driverCode, [1050, 1060, 1061, 1091], true)) {
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
            $force = !empty($_POST['git_force']);
            $r = actionGitPull($projectRoot, $force);
            $results[] = ['title' => $force ? 'Code-Update (fetch + reset --hard)' : 'Code-Update (git pull)'] + $r;
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
$envOutside = is_readable(dirname($projectRoot) . '/.env');
$envInside  = is_readable($projectRoot . '/.env');
$checks[] = ['.env vorhanden' . ($envOutside ? ' (außerhalb Web-Root ✓)' : ($envInside ? ' (im Web-Root – besser eine Ebene höher!)' : '')), $envOutside || $envInside];
$checks[] = ['logs/ beschreibbar', is_writable($projectRoot . '/logs')];
$checks[] = ['PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>=')];
$checks[] = ['DB-Verbindung', isset($pdo) && $pdo instanceof PDO];
$checks[] = ['Tabelle users', Database::tableExists('users')];
$checks[] = ['Tabelle devices', Database::tableExists('devices')];
$checks[] = ['exec() verfügbar (git pull)', function_exists('exec')];

$pending = pendingMigrationCount($projectRoot);
$csrf = htmlspecialchars(Auth::generateCSRFToken());

$pageTitle = 'System-Update - Stromtracker';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<!-- Seiten-spezifische Styles (Theme-Variablen aus style.css) -->
<style>
    .update-console {
        background: var(--gray-900, #111827);
        color: var(--success, #22c55e);
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', monospace;
        font-size: 0.85rem;
        padding: var(--space-4, 1rem);
        border-radius: var(--radius-lg, 8px);
        white-space: pre-wrap;
        word-break: break-word;
        margin-bottom: 0;
    }
    .check-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0;
    }
    .action-tile {
        border: 1px solid var(--gray-200, #e5e7eb);
        border-radius: var(--radius-lg, 8px);
        padding: var(--space-4, 1rem);
        height: 100%;
        transition: border-color 0.2s ease;
    }
    .action-tile:hover {
        border-color: var(--energy, #f59e0b);
    }
    [data-theme="dark"] .action-tile {
        border-color: var(--gray-600, #4b5563);
    }
    .action-tile .form-check-label {
        font-weight: 600;
        cursor: pointer;
    }
    .action-tile small {
        display: block;
        margin-top: 0.25rem;
    }
</style>

<div class="container-fluid py-4">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card glass p-4">
                <h1 class="text-energy mb-2">
                    <i class="bi bi-arrow-repeat"></i>
                    System-Update
                </h1>
                <p class="text-muted mb-0">
                    Setup-Checks, Code-Update, Datenbank-Migrationen und Housekeeping –
                    zentral an einem Ort.
                </p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">

            <?php if ($formError): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($formError) ?>
                </div>
            <?php endif; ?>

            <!-- Ergebnisse (direkt oben, wenn vorhanden) -->
            <?php if (!empty($results)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-terminal text-energy"></i>
                            Ergebnis
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($results as $res): ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge <?= $res['ok'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $res['ok'] ? 'OK' : 'Fehler' ?>
                                </span>
                                <strong><?= htmlspecialchars($res['title']) ?></strong>
                            </div>
                            <pre class="update-console mb-4"><?= htmlspecialchars(implode("\n", $res['lines'])) ?></pre>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Setup-Checks -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard-check text-info"></i>
                        Setup-Checks
                    </h5>
                    <span class="badge <?= $pending > 0 ? 'badge-warning' : 'badge-success' ?>">
                        <?= $pending ?> Migration<?= $pending === 1 ? '' : 'en' ?> ausstehend
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($checks as [$label, $pass]): ?>
                            <div class="col-md-6">
                                <div class="check-item">
                                    <?php if ($pass): ?>
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger"></i>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($label) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Aktionen -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-charge text-energy"></i>
                        Update ausführen
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="action-tile">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="do_gitpull" id="do_gitpull" value="1" checked>
                                        <label class="form-check-label" for="do_gitpull">
                                            <i class="bi bi-git text-energy"></i> Code
                                        </label>
                                        <small class="text-muted">Neueste Version per git pull holen.</small>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="git_force" id="git_force" value="1">
                                        <label class="form-check-label text-warning" for="git_force" style="font-weight: 500;">
                                            Lokale Änderungen verwerfen
                                        </label>
                                        <small class="text-muted">Nur bei Pull-Fehlern durch FTP-Überreste
                                            (fetch + reset --hard; .env/uploads bleiben unberührt).</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="action-tile">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="do_migrate" id="do_migrate" value="1" checked>
                                        <label class="form-check-label" for="do_migrate">
                                            <i class="bi bi-database text-energy"></i> Datenbank
                                        </label>
                                        <small class="text-muted">Ausstehende Migrationen anwenden.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="action-tile">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="do_housekeeping" id="do_housekeeping" value="1">
                                        <label class="form-check-label" for="do_housekeeping">
                                            <i class="bi bi-stars text-energy"></i> Housekeeping
                                        </label>
                                        <small class="text-muted">Logs kürzen, OPcache leeren.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-energy"
                                onclick="return confirm('Update jetzt ausführen?');">
                            <i class="bi bi-play-fill"></i> Update ausführen
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
