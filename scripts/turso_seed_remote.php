<?php

declare(strict_types=1);

/**
 * Sekali jalan: skema migrations → remote, lalu salin data dari staging SQLite.
 * Wajib env: PONDOK_DB_DRIVER=turso, TURSO_DATABASE_URL, TURSO_AUTH_TOKEN
 *
 * CLI: php scripts/turso_seed_remote.php              # dry-run data only (after schema check)
 *      php scripts/turso_seed_remote.php --apply     # DDL + data ke remote
 */
$root = dirname(__DIR__);
$apply = in_array('--apply', $argv ?? [], true);

require_once $root . '/helpers/pondok_db.php';
require_once $root . '/helpers/pondok_turso_load_env.php';

pondok_turso_load_env_file();
putenv('PONDOK_DB_DRIVER=turso');

$urlSet = trim((string) (getenv('TURSO_DATABASE_URL') ?: '')) !== '';
$tokenSet = trim((string) (getenv('TURSO_AUTH_TOKEN') ?: '')) !== '';
echo 'TURSO_DATABASE_URL set=' . ($urlSet ? 'yes' : 'no') . "\n";
echo 'TURSO_AUTH_TOKEN set=' . ($tokenSet ? 'yes' : 'no') . "\n";
if (!$urlSet || !$tokenSet) {
    fwrite(STDERR, "Set TURSO_DATABASE_URL and TURSO_AUTH_TOKEN (libsql://... dashboard).\n");
    exit(1);
}

$staging = $root . '/storage/turso_staging.db';
if (!is_file($staging)) {
    fwrite(STDERR, "Missing storage/turso_staging.db — build staging first.\n");
    exit(1);
}

$php = PHP_BINARY;
$steps = [];

if ($apply) {
    $steps[] = ['label' => 'Apply migrations (remote)', 'cmd' => escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/turso_apply_migrations.php')];
}

$importFlags = escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/turso_import_sqlite_file.php')
    . ' --source ' . escapeshellarg($staging);
if ($apply) {
    $importFlags .= ' --apply';
}
$steps[] = ['label' => 'Import staging SQLite → remote', 'cmd' => $importFlags];

if ($apply) {
    $steps[] = ['label' => 'CI remote validate', 'cmd' => escapeshellarg($php) . ' ' . escapeshellarg($root . '/scripts/ci_turso_validate.php')];
}

$fail = 0;
foreach ($steps as $step) {
    echo "\n=== {$step['label']} ===\n";
    passthru($step['cmd'], $code);
    if ($code !== 0) {
        $fail++;
        echo "[FAIL] exit={$code}\n";
        if ($apply) {
            exit(1);
        }
    }
}

exit($fail > 0 ? 1 : 0);
