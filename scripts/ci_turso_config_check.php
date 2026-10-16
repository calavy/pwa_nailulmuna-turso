<?php

declare(strict_types=1);

/**
 * CI: validasi file contoh + helper Turso tanpa koneksi remote (tanpa secrets).
 */
require_once dirname(__DIR__) . '/helpers/pondok_db.php';

$fail = 0;

function ci_fail(string $msg): void
{
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}

function ci_ok(string $msg): void
{
    echo "[OK] {$msg}\n";
}

$root = dirname(__DIR__);
foreach ([
    'config/database.turso.example.php',
    'config/database.turso.production.example.php',
] as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        ci_fail("missing {$rel}");
        continue;
    }
    $arr = require $path;
    if (!is_array($arr)) {
        ci_fail("{$rel} must return array");
    } else {
        ci_ok("{$rel} loads");
    }
}

putenv('PONDOK_DB_DRIVER=turso');
if (pondok_db_driver() !== 'turso') {
    ci_fail('pondok_db_driver should be turso when env set');
} else {
    ci_ok('pondok_db_driver=turso');
}

putenv('TURSO_DATABASE_URL');
putenv('TURSO_AUTH_TOKEN');
$tursoLocal = $root . '/config/database.turso.local.php';
if (!is_file($tursoLocal)) {
    try {
        pondok_turso_config();
        ci_fail('pondok_turso_config should fail without local file and without env');
    } catch (RuntimeException $e) {
        ci_ok('pondok_turso_config rejects missing config (expected)');
    }
} else {
    try {
        pondok_turso_config();
        ci_ok('pondok_turso_config loads from database.turso.local.php');
    } catch (RuntimeException $e) {
        ci_fail('pondok_turso_config failed with local file present');
    }
}

if (!pondok_db_supports_mysql_ddl()) {
    ci_ok('DDL runtime disabled for turso driver');
} else {
    ci_fail('expected turso driver to disable mysql DDL');
}

echo $fail === 0 ? "CI Turso config check passed\n" : "CI Turso config check failed ({$fail})\n";
exit($fail > 0 ? 1 : 0);
