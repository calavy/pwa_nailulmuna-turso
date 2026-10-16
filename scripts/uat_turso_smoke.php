<?php

declare(strict_types=1);

/**
 * UAT smoke read-only — modul kritis (koneksi, tabel inti).
 * CLI: PONDOK_DB_DRIVER=turso php scripts/uat_turso_smoke.php
 */
putenv('PONDOK_DB_DRIVER=turso');

require_once dirname(__DIR__) . '/config/database.php';

$checks = [
    ['PDO driver', static fn () => (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)],
    ['users table', static fn () => table_exists($pdo, 'users') ? 'ok' : 'missing'],
    ['santri table', static fn () => table_exists($pdo, 'santri') ? 'ok' : 'missing'],
    ['app_settings', static fn () => table_exists($pdo, 'app_settings') ? 'ok' : 'missing'],
    ['keuangan_pembayaran', static fn () => table_exists($pdo, 'keuangan_pembayaran') ? 'ok' : 'optional'],
    ['presensi', static fn () => table_exists($pdo, 'presensi') ? 'ok' : 'optional'],
    ['admin user', static function () use ($pdo) {
        if (!table_exists($pdo, 'users')) {
            return 'missing users';
        }
        $n = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();

        return $n > 0 ? 'ok' : 'no admin';
    }],
];

$fail = 0;
echo "UAT Turso smoke\n";
foreach ($checks as [$label, $fn]) {
    try {
        $detail = $fn();
        $ok = !in_array($detail, ['missing', 'missing users', 'no admin'], true);
        if (!$ok) {
            $fail++;
        }
        echo ($ok ? '[OK] ' : '[!!] ') . $label . ' — ' . $detail . "\n";
    } catch (Throwable $e) {
        $fail++;
        echo '[!!] ' . $label . ' — ' . $e->getMessage() . "\n";
    }
}

if (function_exists('pondok_db_supports_mysql_ddl') && !pondok_db_supports_mysql_ddl()) {
    echo "[OK] DDL runtime disabled (turso driver)\n";
} else {
    echo "[!!] Expected turso driver — set PONDOK_DB_DRIVER=turso\n";
    $fail++;
}

exit($fail > 0 ? 1 : 0);
