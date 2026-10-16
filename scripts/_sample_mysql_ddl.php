<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('PONDOK_DB_DRIVER=mysql');
require_once $root . '/helpers/pondok_db.php';
$cfg = require $root . '/config/database.local.php';
$pdo = pondok_db_connect(is_array($cfg) ? $cfg : [])['pdo'];
foreach (['kegiatan', 'operasional_audit_log', 'santri'] as $t) {
    $row = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $t) . '`')->fetch(PDO::FETCH_ASSOC);
    echo "=== {$t} ===\n";
    echo ($row['Create Table'] ?? '') . "\n\n";
}
