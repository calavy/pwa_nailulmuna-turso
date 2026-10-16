<?php

declare(strict_types=1);

/**
 * Seed minimal produksi (admin + app_settings) — BUKAN impor lokal penuh.
 * CLI: PONDOK_DB_DRIVER=turso php scripts/turso_seed_minimal.php
 */
putenv('PONDOK_DB_DRIVER=turso');

require_once dirname(__DIR__) . '/config/database.php';

$adminHash = '$2y$10$U.rw/BkcSe4XLgm3cX5N6eDv.ZDjMTnI3pY4cv9k43HShBYWk/7pe';

$pdo->exec("
    INSERT OR IGNORE INTO users (id, nama, username, password, role, is_super_admin)
    VALUES (1, 'Administrator', 'admin', " . $pdo->quote($adminHash) . ", 'admin', 1)
");

if (table_exists($pdo, 'app_settings')) {
    $pairs = [
        ['pondok_nama', 'Pondok Nailul Muna'],
        ['pondok_schema_deploy_ready', '1'],
    ];
    foreach ($pairs as [$key, $val]) {
        if (column_exists($pdo, 'app_settings', 'setting_key')) {
            $st = $pdo->prepare('
                INSERT INTO app_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value
            ');
            $st->execute([$key, $val]);
        }
    }
}

echo "Seed minimal OK. Login: admin / admin123 (ganti password setelah login).\n";
