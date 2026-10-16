<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/pondok_db.php';
putenv('PONDOK_DB_DRIVER=mysql');
$cfg = require $root . '/config/database.local.php';
$mysql = pondok_db_connect(is_array($cfg) ? $cfg : [])['pdo'];
$tCfg = require $root . '/config/database.turso.local.php';
require_once $root . '/helpers/pondok_turso_libsql.php';
$sqlite = pondok_turso_open_pdo(is_array($tCfg) ? $tCfg : [], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$tables = ['santri', 'users', 'keuangan_pembayaran', 'presensi', 'kegiatan', 'app_settings'];
echo "table\tmysql\tsqlite\tdiff\n";
foreach ($tables as $t) {
    $m = (int) $mysql->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $t) . '`')->fetchColumn();
    $q = '"' . str_replace('"', '""', $t) . '"';
    $s = (int) $sqlite->query("SELECT COUNT(*) FROM {$q}")->fetchColumn();
    echo "{$t}\t{$m}\t{$s}\t" . ($m - $s) . "\n";
}
