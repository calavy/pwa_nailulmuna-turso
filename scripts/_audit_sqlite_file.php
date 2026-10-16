<?php

declare(strict_types=1);

/** Read-only audit — CLI: php scripts/_audit_sqlite_file.php <path> */
$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    echo "missing\n";
    exit(1);
}
$p = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$t = (int) $p->query(
    "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
)->fetchColumn();
echo basename($path) . ' tables=' . $t;
foreach (['santri', 'users', 'app_settings'] as $tbl) {
    try {
        $c = (int) $p->query('SELECT COUNT(*) FROM ' . preg_replace('/[^a-zA-Z0-9_]/', '', $tbl))->fetchColumn();
        echo ' ' . $tbl . '=' . $c;
    } catch (Throwable $e) {
        echo ' ' . $tbl . '=n/a';
    }
}
echo ' size=' . filesize($path) . "\n";
