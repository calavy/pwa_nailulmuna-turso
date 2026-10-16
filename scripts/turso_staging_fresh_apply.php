<?php

declare(strict_types=1);

/**
 * Backup staging SQLite, buat file kosong, apply migrations/turso/*.sql
 * CLI: php scripts/turso_staging_fresh_apply.php
 */
$root = dirname(__DIR__);
$cfgFile = $root . '/config/database.turso.local.php';
if (!is_file($cfgFile)) {
    fwrite(STDERR, "Missing config/database.turso.local.php\n");
    exit(1);
}
$cfg = require $cfgFile;
$dbPath = (string) ($cfg['sqlite_path'] ?? $root . '/storage/turso_staging.db');
$backupDir = $root . '/storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
if (is_file($dbPath)) {
    $dest = $backupDir . '/turso_staging_' . date('Ymd_His') . '.db';
    if (!copy($dbPath, $dest)) {
        fwrite(STDERR, "Backup failed: {$dbPath}\n");
        exit(1);
    }
    echo "Backed up to {$dest}\n";
    if (!unlink($dbPath)) {
        fwrite(STDERR, "Could not remove old staging file: {$dbPath}\n");
        exit(1);
    }
}

putenv('PONDOK_DB_DRIVER=turso');
passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/scripts/turso_apply_migrations.php'), $code);
exit($code);
