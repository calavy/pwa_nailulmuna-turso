<?php

declare(strict_types=1);

/**
 * Inisialisasi file SQLite staging dari migrations/turso/01_bootstrap.sql
 * CLI: php scripts/turso_staging_init.php
 */
putenv('PONDOK_DB_DRIVER=turso');

$example = dirname(__DIR__) . '/config/database.turso.example.php';
$local = dirname(__DIR__) . '/config/database.turso.local.php';
if (!is_file($local) && is_file($example)) {
    copy($example, $local);
    echo "Created config/database.turso.local.php from example.\n";
}

require_once dirname(__DIR__) . '/helpers/pondok_db.php';

$cfgFile = $local;
if (!is_file($cfgFile)) {
    fwrite(STDERR, "Missing config/database.turso.local.php\n");
    exit(1);
}
$cfg = require $cfgFile;
$sqlitePath = (string) ($cfg['sqlite_path'] ?? '');
if ($sqlitePath === '') {
    fwrite(STDERR, "sqlite_path empty in database.turso.local.php\n");
    exit(1);
}
if (!str_starts_with($sqlitePath, '/') && !preg_match('#^[A-Za-z]:\\\\#', $sqlitePath)) {
    $sqlitePath = dirname(__DIR__) . '/' . ltrim(str_replace('__DIR__', '', $sqlitePath), '/');
}
if (str_contains($sqlitePath, '__DIR__')) {
    $sqlitePath = str_replace('__DIR__', dirname(__DIR__) . '/config', $sqlitePath);
    $sqlitePath = realpath(dirname($sqlitePath)) . '/' . basename($sqlitePath);
}

$bootstrap = dirname(__DIR__) . '/migrations/turso/01_bootstrap.sql';
if (!is_file($bootstrap)) {
    fwrite(STDERR, "Missing migrations/turso/01_bootstrap.sql\n");
    exit(1);
}

$dir = dirname($sqlitePath);
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$sql = file_get_contents($bootstrap);
if (!is_string($sql)) {
    exit(1);
}
$pdo->exec($sql);
echo "Turso staging DB ready: {$sqlitePath}\n";
echo "Test: set PONDOK_DB_DRIVER=turso and open cek-server.php (local only).\n";
