<?php

declare(strict_types=1);

/**
 * Checklist otomatis (read-only) sebelum cutover Turso. CLI.
 */
require_once dirname(__DIR__) . '/config/database.php';

$checks = [];

$checks[] = [
    'name' => 'Driver aktif',
    'ok' => function_exists('pondok_db_driver'),
    'detail' => function_exists('pondok_db_driver') ? pondok_db_driver() : '-',
];

$checks[] = [
    'name' => 'Koneksi PDO',
    'ok' => isset($pdo) && $pdo instanceof PDO,
    'detail' => isset($pdo) ? (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : '-',
];

$checks[] = [
    'name' => 'Tabel app_settings',
    'ok' => isset($pdo) && table_exists($pdo, 'app_settings'),
    'detail' => '',
];

$checks[] = [
    'name' => 'Tabel santri',
    'ok' => isset($pdo) && table_exists($pdo, 'santri'),
    'detail' => '',
];

$checks[] = [
    'name' => 'DDL runtime dimatikan (turso)',
    'ok' => !function_exists('pondok_db_supports_mysql_ddl') || !pondok_db_supports_mysql_ddl()
        ? true
        : (trim((string) getenv('PONDOK_SCHEMA_READY')) === '1'),
    'detail' => pondok_db_supports_mysql_ddl() ? 'MySQL: set PONDOK_SCHEMA_READY=1 di prod' : 'turso/sqlite OK',
];

$fail = 0;
echo "Turso pre-cutover checklist\n";
foreach ($checks as $c) {
    $ok = (bool) $c['ok'];
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? '[OK] ' : '[!!] ') . $c['name'];
    if (($c['detail'] ?? '') !== '') {
        echo ' — ' . $c['detail'];
    }
    echo "\n";
}
exit($fail > 0 ? 1 : 0);
