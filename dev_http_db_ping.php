<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/app_path.php';

if (!app_is_local_dev()) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$envDrv = getenv('PONDOK_DB_DRIVER');
echo 'getenv_PONDOK_DB_DRIVER=' . ($envDrv === false ? '(unset)' : (string) $envDrv) . "\n";
echo 'pondok_db_driver=' . pondok_db_driver() . "\n";
echo 'pdo_driver=' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
echo 'pondok_database_name=' . pondok_database_name($pdo) . "\n";
