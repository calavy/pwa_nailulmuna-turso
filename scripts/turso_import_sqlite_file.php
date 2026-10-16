<?php

declare(strict_types=1);

/**
 * Salin data SQLite (staging) → target Turso (remote via env TURSO_*).
 * CLI: php scripts/turso_import_sqlite_file.php --source path/to.db           # dry-run counts
 *      php scripts/turso_import_sqlite_file.php --source path/to.db --apply
 */
$root = dirname(__DIR__);
$apply = in_array('--apply', $argv ?? [], true);
$source = '';
foreach ($argv ?? [] as $i => $arg) {
    if ($arg === '--source' && isset($argv[$i + 1])) {
        $source = (string) $argv[$i + 1];
    }
}
if ($source === '') {
    $source = $root . '/storage/turso_staging.db';
}
if (!is_file($source)) {
    fwrite(STDERR, "Source SQLite file not found.\n");
    exit(1);
}

require_once $root . '/helpers/pondok_db.php';
require_once $root . '/helpers/pondok_turso_load_env.php';

pondok_turso_load_env_file();
putenv('PONDOK_DB_DRIVER=turso');

$urlSet = trim((string) (getenv('TURSO_DATABASE_URL') ?: '')) !== '';
$tokenSet = trim((string) (getenv('TURSO_AUTH_TOKEN') ?: '')) !== '';
echo 'TURSO_DATABASE_URL set=' . ($urlSet ? 'yes' : 'no') . "\n";
echo 'TURSO_AUTH_TOKEN set=' . ($tokenSet ? 'yes' : 'no') . "\n";
if (!$urlSet || !$tokenSet) {
    fwrite(STDERR, "Set TURSO_DATABASE_URL and TURSO_AUTH_TOKEN for remote target.\n");
    exit(1);
}

$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Run composer install first.\n");
    exit(1);
}
require_once $autoload;

try {
    $cfg = pondok_turso_config();
    require_once $root . '/helpers/pondok_turso_libsql.php';
    $pdo = pondok_turso_open_pdo($cfg, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Remote connection failed.\n");
    exit(1);
}

$src = new PDO('sqlite:' . $source, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$st = $src->query(
    "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
);
$tables = $st ? ($st->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];

$pdo->exec('PRAGMA foreign_keys = OFF');

$diffs = [];
$imported = 0;

foreach ($tables as $table) {
    $table = (string) $table;
    if (!table_exists($pdo, $table)) {
        $diffs[] = ['table' => $table, 'src' => '-', 'dest' => '-', 'note' => 'missing on remote'];
        continue;
    }
    $quoted = '"' . str_replace('"', '""', $table) . '"';
    $srcCount = (int) $src->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
    $destCount = (int) $pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();

    if (!$apply) {
        if ($srcCount !== $destCount) {
            $diffs[] = ['table' => $table, 'src' => $srcCount, 'dest' => $destCount, 'note' => 'count mismatch'];
        }
        continue;
    }

    if ($srcCount === 0) {
        continue;
    }

    $srcCols = array_keys(pondok_schema_columns($src, $table));
    $destColMap = pondok_schema_columns($pdo, $table);
    $cols = [];
    foreach ($srcCols as $c) {
        if (isset($destColMap[strtolower($c)])) {
            $cols[] = $c;
        }
    }
    if ($cols === []) {
        $diffs[] = ['table' => $table, 'src' => $srcCount, 'dest' => $destCount, 'note' => 'no shared columns'];
        continue;
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM {$quoted}");
        $colList = implode(', ', array_map(static fn ($c) => '"' . str_replace('"', '""', $c) . '"', $cols));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $ins = $pdo->prepare("INSERT INTO {$quoted} ({$colList}) VALUES ({$placeholders})");

        $sel = $src->query('SELECT ' . $colList . ' FROM ' . $quoted);
        while ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
            $vals = [];
            foreach ($cols as $c) {
                $vals[] = $row[$c] ?? null;
            }
            $ins->execute($vals);
        }
        $pdo->commit();
        $imported++;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $diffs[] = ['table' => $table, 'src' => $srcCount, 'dest' => $destCount, 'note' => 'import fail'];
    }

    $destAfter = (int) $pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
    if ($srcCount !== $destAfter) {
        $diffs[] = ['table' => $table, 'src' => $srcCount, 'dest' => $destAfter, 'note' => 'post-import mismatch'];
    }
}

if ($apply) {
    $pdo->exec('PRAGMA foreign_keys = ON');
}

echo $apply ? "Turso SQLite file import (--apply)\n" : "Turso SQLite file dry-run\n";
echo 'Source: ' . basename($source) . "\n";
echo str_repeat('-', 60) . "\n";
echo 'Tables in source: ' . count($tables) . "\n";
if ($apply) {
    echo 'Tables imported: ' . $imported . "\n";
}
echo 'Diff rows: ' . count($diffs) . "\n";
foreach (array_slice($diffs, 0, 40) as $d) {
    echo sprintf(
        "  %-35s src=%s dest=%s %s\n",
        $d['table'],
        (string) $d['src'],
        (string) $d['dest'],
        $d['note'] ?? ''
    );
}
if (count($diffs) > 40) {
    echo '  ... and ' . (count($diffs) - 40) . " more\n";
}

exit($diffs !== [] ? 1 : 0);
