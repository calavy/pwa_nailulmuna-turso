<?php

declare(strict_types=1);

/** Audit read-only — CLI: php scripts/_audit_turso_env.php */
$root = dirname(__DIR__);

echo "=== PHP / SQLite ===\n";
echo 'pdo_sqlite: ' . (extension_loaded('pdo_sqlite') ? 'yes' : 'no') . "\n";
echo 'pdo_mysql: ' . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";
echo 'ffi: ' . (extension_loaded('ffi') ? 'yes' : 'no') . "\n";
if (class_exists('SQLite3')) {
    $v = SQLite3::version();
    echo 'SQLite: ' . ($v['versionString'] ?? '?') . "\n";
}
try {
    $p = new PDO('sqlite::memory:');
    $p->exec('CREATE TABLE t(a INTEGER);');
    $p->exec('ALTER TABLE t ADD COLUMN IF NOT EXISTS b INTEGER;');
    echo "ADD COLUMN IF NOT EXISTS: supported\n";
} catch (Throwable $e) {
    echo 'ADD COLUMN IF NOT EXISTS: NOT supported — ' . $e->getMessage() . "\n";
}

echo "\n=== MySQL source ===\n";
putenv('PONDOK_DB_DRIVER=mysql');
require_once $root . '/helpers/pondok_db.php';
$local = $root . '/config/database.local.php';
if (!is_file($local)) {
    echo "database.local.php missing\n";
    exit(1);
}
$cfg = require $local;
$connected = pondok_db_connect(is_array($cfg) ? $cfg : []);
$mysql = $connected['pdo'];
$dbName = (string) ($cfg['dbname'] ?? 'pwa_nailulmuna');
$st = $mysql->prepare(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_type = ?'
);
$st->execute([$dbName, 'BASE TABLE']);
echo "MySQL tables ({$dbName}): " . $st->fetchColumn() . "\n";

echo "\n=== Staging SQLite ===\n";
$tursoCfgFile = $root . '/config/database.turso.local.php';
echo 'database.turso.local.php: ' . (is_file($tursoCfgFile) ? 'yes' : 'no') . "\n";
$tCfg = is_file($tursoCfgFile) ? require $tursoCfgFile : [];
$dbFile = (string) ($tCfg['sqlite_path'] ?? $root . '/storage/turso_staging.db');
echo 'sqlite_path exists: ' . (is_file($dbFile) ? 'yes' : 'no') . "\n";
if (is_file($dbFile)) {
    $sq = new PDO('sqlite:' . $dbFile);
    $sq->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $n = (int) $sq->query(
        "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
    )->fetchColumn();
    echo "Staging table count: {$n}\n";
    $ic = $sq->query('PRAGMA integrity_check')->fetchColumn();
    echo 'integrity_check: ' . $ic . "\n";
    $fk = $sq->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
    echo 'foreign_key_check violations: ' . count($fk) . "\n";
}

echo "\n=== 02_full_schema.sql ===\n";
$schema = $root . '/migrations/turso/02_full_schema.sql';
if (is_file($schema)) {
    $content = file_get_contents($schema) ?: '';
    preg_match_all('/^CREATE TABLE IF NOT EXISTS\s+(\w+)/m', $content, $m);
    echo 'CREATE TABLE statements: ' . count($m[1]) . "\n";
    echo 'unique table names: ' . count(array_unique($m[1])) . "\n";
}
