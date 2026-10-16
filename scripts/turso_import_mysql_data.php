<?php

declare(strict_types=1);

/**
 * Salin data MySQL → staging SQLite (preserve id).
 * CLI: php scripts/turso_import_mysql_data.php           # dry-run counts
 *      php scripts/turso_import_mysql_data.php --apply
 */
$root = dirname(__DIR__);
$apply = in_array('--apply', $argv ?? [], true);

require_once $root . '/helpers/pondok_db.php';

putenv('PONDOK_DB_DRIVER=mysql');
$local = $root . '/config/database.local.php';
if (!is_file($local)) {
    fwrite(STDERR, "Missing database.local.php\n");
    exit(1);
}
$cfg = require $local;
$dbName = (string) ($cfg['dbname'] ?? 'pwa_nailulmuna');
$mysql = pondok_db_connect(is_array($cfg) ? $cfg : [])['pdo'];

$tursoCfgFile = $root . '/config/database.turso.local.php';
if (!is_file($tursoCfgFile)) {
    fwrite(STDERR, "Missing database.turso.local.php\n");
    exit(1);
}
$tCfg = require $tursoCfgFile;
require_once $root . '/helpers/pondok_turso_libsql.php';
$pdo = pondok_turso_open_pdo(is_array($tCfg) ? $tCfg : [], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$st = $mysql->prepare(
    'SELECT table_name FROM information_schema.tables
     WHERE table_schema = ? AND table_type = ? ORDER BY table_name'
);
$st->execute([$dbName, 'BASE TABLE']);
$tables = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

$pdo->exec('PRAGMA foreign_keys = OFF');

$diffs = [];
$imported = 0;

foreach ($tables as $table) {
    $table = (string) $table;
    if (!table_exists($pdo, $table)) {
        $diffs[] = ['table' => $table, 'mysql' => '-', 'sqlite' => '-', 'note' => 'missing in sqlite'];
        continue;
    }
    $mysqlCount = (int) $mysql->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn();
    $quoted = '"' . str_replace('"', '""', $table) . '"';
    $sqliteCount = (int) $pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();

    if (!$apply) {
        if ($mysqlCount !== $sqliteCount) {
            $diffs[] = ['table' => $table, 'mysql' => $mysqlCount, 'sqlite' => $sqliteCount, 'note' => 'count mismatch'];
        }
        continue;
    }

    if ($mysqlCount === 0) {
        continue;
    }

    $colsSt = $mysql->prepare(
        'SELECT column_name FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position'
    );
    $colsSt->execute([$dbName, $table]);
    $mysqlCols = $colsSt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $sqliteColMap = pondok_schema_columns($pdo, $table);
    $cols = [];
    foreach ($mysqlCols as $c) {
        if (isset($sqliteColMap[strtolower((string) $c)])) {
            $cols[] = (string) $c;
        }
    }
    if ($cols === []) {
        $diffs[] = ['table' => $table, 'mysql' => $mysqlCount, 'sqlite' => $sqliteCount, 'note' => 'no shared columns'];
        continue;
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM {$quoted}");
        $colList = implode(', ', array_map(static fn ($c) => '"' . str_replace('"', '""', $c) . '"', $cols));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $ins = $pdo->prepare("INSERT INTO {$quoted} ({$colList}) VALUES ({$placeholders})");

        $sel = $mysql->query('SELECT ' . implode(', ', array_map(static fn ($c) => '`' . str_replace('`', '``', $c) . '`', $cols)) . ' FROM `' . str_replace('`', '``', $table) . '`');
        while ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
            $vals = [];
            foreach ($cols as $c) {
                $v = $row[$c] ?? null;
                if ($v === null) {
                    $vals[] = null;
                    continue;
                }
                if (is_bool($v)) {
                    $vals[] = $v ? 1 : 0;
                    continue;
                }
                $vals[] = is_string($v) || is_numeric($v) ? $v : (string) $v;
            }
            $ins->execute($vals);
        }
        $pdo->commit();
        $imported++;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $diffs[] = ['table' => $table, 'mysql' => $mysqlCount, 'sqlite' => $sqliteCount, 'note' => 'import fail: ' . $e->getMessage()];
    }

    $sqliteAfter = (int) $pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
    if ($mysqlCount !== $sqliteAfter) {
        $diffs[] = ['table' => $table, 'mysql' => $mysqlCount, 'sqlite' => $sqliteAfter, 'note' => 'post-import mismatch'];
    }
}

if ($apply) {
    $pdo->exec('PRAGMA foreign_keys = ON');
    foreach ($tables as $table) {
        $table = (string) $table;
        if (!table_exists($pdo, $table)) {
            continue;
        }
        $quoted = '"' . str_replace('"', '""', $table) . '"';
        if (!column_exists($pdo, $table, 'id')) {
            continue;
        }
        $maxId = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM {$quoted}")->fetchColumn();
        if ($maxId > 0) {
            $pdo->exec('UPDATE sqlite_sequence SET seq = ' . $maxId . " WHERE name = " . $pdo->quote($table));
        }
    }
}

echo $apply ? "Turso data import (--apply)\n" : "Turso data dry-run\n";
echo str_repeat('-', 60) . "\n";
echo 'Tables processed: ' . count($tables) . "\n";
if ($apply) {
    echo 'Tables imported: ' . $imported . "\n";
}
echo 'Diff rows: ' . count($diffs) . "\n";
foreach (array_slice($diffs, 0, 40) as $d) {
    echo sprintf(
        "  %-35s mysql=%s sqlite=%s %s\n",
        $d['table'],
        (string) $d['mysql'],
        (string) $d['sqlite'],
        $d['note'] ?? ''
    );
}
if (count($diffs) > 40) {
    echo '  ... and ' . (count($diffs) - 40) . " more\n";
}

exit($diffs !== [] ? 1 : 0);
