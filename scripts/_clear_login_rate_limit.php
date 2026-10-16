<?php

declare(strict_types=1);

putenv('PONDOK_DB_DRIVER=turso');
require_once dirname(__DIR__) . '/config/database.php';

if (!table_exists($pdo, 'login_attempt')) {
    echo "no login_attempt table\n";
    exit(0);
}
$n = (int) $pdo->exec('DELETE FROM login_attempt');
echo "deleted login_attempt rows (exec return=$n)\n";
