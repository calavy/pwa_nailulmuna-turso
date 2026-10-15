<?php

declare(strict_types=1);

/**
 * Inventaris pola SQL MySQL-specific (read-only). CLI: php scripts/inventory_mysql_compat.php
 */
$root = dirname(__DIR__);
$patterns = [
    'SHOW TABLES' => 'SHOW\\s+TABLES',
    'SHOW COLUMNS' => 'SHOW\\s+COLUMNS',
    'SELECT DATABASE()' => 'SELECT\\s+DATABASE\\(\\)',
    'AUTO_INCREMENT' => 'AUTO_INCREMENT',
    'ADD COLUMN IF NOT EXISTS' => 'ADD\\s+COLUMN\\s+IF\\s+NOT\\s+EXISTS',
    'ON DUPLICATE KEY' => 'ON\\s+DUPLICATE\\s+KEY',
    'INSERT IGNORE' => 'INSERT\\s+IGNORE',
    'GROUP_CONCAT' => 'GROUP_CONCAT',
    'FIND_IN_SET' => 'FIND_IN_SET',
    'SET time_zone' => 'SET\\s+time_zone',
    'UNSIGNED' => 'INT\\s+UNSIGNED|UNSIGNED',
    'ENUM(' => 'ENUM\\s*\\(',
];

$extensions = ['php', 'sql'];
$counts = [];
foreach (array_keys($patterns) as $label) {
    $counts[$label] = 0;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)
    ) {
        continue;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions, true)) {
        continue;
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        continue;
    }
    foreach ($patterns as $label => $regex) {
        if (preg_match_all('/' . $regex . '/i', $content)) {
            $counts[$label] += preg_match_all('/' . $regex . '/i', $content);
        }
    }
}

echo "MySQL / Turso compatibility inventory\n";
echo "Root: {$root}\n";
echo str_repeat('-', 48) . "\n";
foreach ($counts as $label => $n) {
    echo str_pad($label, 28) . (string) $n . "\n";
}
echo str_repeat('-', 48) . "\n";
echo "Schema introspection now dual-path in helpers/pondok_db.php (MySQL + sqlite).\n";
echo "Runtime DDL skipped when PONDOK_DB_DRIVER=turso.\n";
