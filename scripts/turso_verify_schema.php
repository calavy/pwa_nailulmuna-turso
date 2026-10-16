<?php

declare(strict_types=1);

/**
 * Bandingkan skema MySQL live vs staging SQLite (read-only).
 * CLI: php scripts/turso_verify_schema.php
 */
$root = dirname(__DIR__);
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
$sqlite = pondok_turso_open_pdo(is_array($tCfg) ? $tCfg : [], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$st = $mysql->prepare(
    'SELECT table_name FROM information_schema.tables
     WHERE table_schema = ? AND table_type = ? ORDER BY table_name'
);
$st->execute([$dbName, 'BASE TABLE']);
$mysqlTables = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

$sqliteTables = array_keys(pondok_schema_tables($sqlite));
sort($sqliteTables);

$missingInSqlite = [];
foreach ($mysqlTables as $t) {
    if (!table_exists($sqlite, (string) $t)) {
        $missingInSqlite[] = $t;
    }
}

$extraInSqlite = [];
$mysqlMap = array_fill_keys(array_map('strtolower', $mysqlTables), true);
foreach ($sqliteTables as $t) {
    if (!isset($mysqlMap[strtolower($t)])) {
        $extraInSqlite[] = $t;
    }
}

$columnMismatches = [];
foreach ($mysqlTables as $table) {
    $table = (string) $table;
    if (!table_exists($sqlite, $table)) {
        continue;
    }
    $cst = $mysql->prepare(
        'SELECT column_name FROM information_schema.columns
         WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position'
    );
    $cst->execute([$dbName, $table]);
    $mysqlCols = array_map('strtolower', $cst->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $sqliteCols = array_keys(pondok_schema_columns($sqlite, $table));
    sort($mysqlCols);
    sort($sqliteCols);
    $missingCols = array_diff($mysqlCols, $sqliteCols);
    if ($table === 'app_settings' && $missingCols === ['id'] && in_array('setting_key', $sqliteCols, true)) {
        continue;
    }
    if ($missingCols !== []) {
        $columnMismatches[$table] = array_values($missingCols);
    }
}

$fkViolations = $sqlite->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$integrity = (string) $sqlite->query('PRAGMA integrity_check')->fetchColumn();

echo "Turso schema verify\n";
echo str_repeat('-', 48) . "\n";
echo 'MySQL tables: ' . count($mysqlTables) . "\n";
echo 'SQLite tables: ' . count($sqliteTables) . "\n";
echo 'Missing in SQLite: ' . count($missingInSqlite) . "\n";
echo 'Extra in SQLite: ' . count($extraInSqlite) . "\n";
echo 'Tables with missing columns: ' . count($columnMismatches) . "\n";
echo 'integrity_check: ' . $integrity . "\n";
echo 'foreign_key_check rows: ' . count($fkViolations) . "\n";

$fail = 0;
if ($missingInSqlite !== []) {
    $fail++;
    echo "\nMissing tables:\n";
    foreach ($missingInSqlite as $t) {
        echo "  - {$t}\n";
    }
}
if ($columnMismatches !== []) {
    $fail++;
    echo "\nMissing columns (first 15 tables):\n";
    $n = 0;
    foreach ($columnMismatches as $tbl => $cols) {
        echo "  {$tbl}: " . implode(', ', $cols) . "\n";
        if (++$n >= 15) {
            break;
        }
    }
}
if ($integrity !== 'ok') {
    $fail++;
}
if ($fkViolations !== []) {
    $fail++;
    echo "\nFK violations (first 5):\n";
    foreach (array_slice($fkViolations, 0, 5) as $row) {
        echo '  ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

exit($fail > 0 ? 1 : 0);
