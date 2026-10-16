<?php

declare(strict_types=1);

/**
 * Apply migrations/turso/*.sql ke DB aktif (sqlite staging atau Turso remote).
 * CLI: PONDOK_DB_DRIVER=turso php scripts/turso_apply_migrations.php
 */
putenv('PONDOK_DB_DRIVER=turso');

require_once dirname(__DIR__) . '/config/database.php';

$dir = dirname(__DIR__) . '/migrations/turso';
$files = glob($dir . '/*.sql') ?: [];
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if ($name === 'README.md') {
        continue;
    }
    echo "Applying {$name}...\n";
    $sql = file_get_contents($file);
    if (!is_string($sql) || trim($sql) === '') {
        continue;
    }
    $statements = split_sql_statements($sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'already exists') || str_contains($msg, 'duplicate column')) {
                echo "  skip: {$e->getMessage()}\n";
                continue;
            }
            fwrite(STDERR, "FAIL in {$name}: {$stmt}\n{$e->getMessage()}\n");
            exit(1);
        }
    }
}

echo "Done.\n";

/** @return list<string> */
function split_sql_statements(string $sql): array
{
    $parts = preg_split('/;\s*\n/', $sql) ?: [];

    return array_map(static fn ($p) => rtrim($p, ';') . ';', $parts);
}
