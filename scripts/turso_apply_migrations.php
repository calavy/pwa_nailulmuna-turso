<?php

declare(strict_types=1);

/**
 * Apply migrations/turso/*.sql ke DB aktif (sqlite staging atau Turso remote).
 * CLI: PONDOK_DB_DRIVER=turso php scripts/turso_apply_migrations.php
 */
putenv('PONDOK_DB_DRIVER=turso');

$root = dirname(__DIR__);
require_once $root . '/config/database.php';
require_once $root . '/helpers/turso_schema_convert.php';

$dir = $root . '/migrations/turso';
$files = glob($dir . '/*.sql') ?: [];
sort($files);

$pdo->exec('PRAGMA foreign_keys = OFF');

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
    $statements = turso_split_migration_statements($sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--')) {
            continue;
        }
        if (turso_migration_should_skip($pdo, $stmt)) {
            echo "  skip (already applied): " . turso_migration_summary($stmt) . "\n";
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (PDOException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'already exists')
                || str_contains($msg, 'duplicate column')
                || str_contains($msg, 'duplicate column name')
            ) {
                echo "  skip: {$e->getMessage()}\n";
                continue;
            }
            fwrite(STDERR, "FAIL in {$name}:\n{$stmt}\n{$e->getMessage()}\n");
            exit(1);
        }
    }
}

$pdo->exec('PRAGMA foreign_keys = ON');
echo "Done.\n";

/** @return list<string> */
function turso_split_migration_statements(string $sql): array
{
    $parts = preg_split('/;\s*\n/', $sql) ?: [];

    return array_map(static fn ($p) => rtrim(trim($p), ';') . ';', $parts);
}

function turso_migration_summary(string $stmt): string
{
    $one = preg_replace('/\s+/', ' ', $stmt) ?? $stmt;

    return strlen($one) > 100 ? substr($one, 0, 97) . '...' : $one;
}

function turso_migration_should_skip(PDO $pdo, string $stmt): bool
{
    if (preg_match(
        '/^ALTER\s+TABLE\s+(\w+)\s+ADD\s+COLUMN\s+(?:IF\s+NOT\s+EXISTS\s+)?(\w+)\b/i',
        $stmt,
        $m
    )) {
        return column_exists($pdo, $m[1], $m[2]);
    }
    if (preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+IF\s+NOT\s+EXISTS\s+(\w+)\s+ON\s+(\w+)/i', $stmt, $m)) {
        return turso_sqlite_index_exists($pdo, $m[1], $m[2]);
    }

    return false;
}

function turso_sqlite_index_exists(PDO $pdo, string $indexName, string $tableName): bool
{
    $st = $pdo->prepare(
        "SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = ? AND tbl_name = ? LIMIT 1"
    );
    $st->execute([$indexName, $tableName]);

    return (bool) $st->fetchColumn();
}
