<?php

declare(strict_types=1);

/**
 * CI / smoke read-only ke Turso remote via env TURSO_DATABASE_URL + TURSO_AUTH_TOKEN.
 * Tidak menulis data; tidak mencetak URL/token.
 */
putenv('PONDOK_DB_DRIVER=turso');

require_once dirname(__DIR__) . '/helpers/pondok_db.php';

$fail = 0;

function tv_fail(string $msg): void
{
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}

function tv_ok(string $msg): void
{
    echo "[OK] {$msg}\n";
}

$urlSet = trim((string) (getenv('TURSO_DATABASE_URL') ?: '')) !== '';
$tokenSet = trim((string) (getenv('TURSO_AUTH_TOKEN') ?: '')) !== '';
echo 'TURSO_DATABASE_URL set=' . ($urlSet ? 'yes' : 'no') . "\n";
echo 'TURSO_AUTH_TOKEN set=' . ($tokenSet ? 'yes' : 'no') . "\n";

if (!$urlSet || !$tokenSet) {
    tv_fail('Turso remote credentials incomplete in environment');
    exit(1);
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    tv_fail('vendor/autoload.php missing — run composer install');
    exit(1);
}
require_once $autoload;

if (!class_exists(\Libsql\Database::class)) {
    tv_fail('Libsql\\Database not found — check turso/libsql package');
    exit(1);
}
tv_ok('composer autoload + turso/libsql');

try {
    require_once dirname(__DIR__) . '/config/database.php';
} catch (Throwable $e) {
    tv_fail('database connection failed');
    exit(1);
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    tv_fail('PDO not available after bootstrap');
    exit(1);
}

$driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo 'pdo_driver=' . $driver . "\n";
if ($driver !== 'sqlite') {
    tv_fail('expected sqlite-backed libsql PDO');
}

try {
    $one = (int) $pdo->query('SELECT 1')->fetchColumn();
    if ($one !== 1) {
        tv_fail('SELECT 1 unexpected result');
    } else {
        tv_ok('SELECT 1');
    }

    $tables = (int) $pdo->query(
        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
    )->fetchColumn();
    echo 'table_count=' . $tables . "\n";
    if ($tables < 1) {
        tv_fail('no user tables visible (remote empty or wrong database)');
    } else {
        tv_ok('sqlite_master table count');
    }
} catch (Throwable $e) {
    tv_fail('read-only query failed');
    exit(1);
}

if (function_exists('pondok_db_supports_mysql_ddl') && !pondok_db_supports_mysql_ddl()) {
    tv_ok('DDL runtime disabled (turso)');
} else {
    tv_fail('mysql DDL should be disabled under turso driver');
}

echo $fail === 0 ? "CI Turso remote validate passed\n" : "CI Turso remote validate failed ({$fail})\n";
exit($fail > 0 ? 1 : 0);
