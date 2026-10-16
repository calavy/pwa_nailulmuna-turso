<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/pondok_turso_load_env.php';

pondok_turso_load_env_file();

echo 'env_file=' . (is_file(dirname(__DIR__) . '/config/turso.remote.env') ? 'yes' : 'no') . "\n";
$url = trim((string) (getenv('TURSO_DATABASE_URL') ?: ''));
$token = trim((string) (getenv('TURSO_AUTH_TOKEN') ?: ''));
echo 'TURSO_DATABASE_URL set=' . ($url !== '' ? 'yes' : 'no') . "\n";
echo 'TURSO_AUTH_TOKEN set=' . ($token !== '' ? 'yes' : 'no') . "\n";
if ($url !== '') {
    echo 'url_scheme=' . (str_starts_with($url, 'libsql://') ? 'libsql_ok' : 'BAD_not_libsql') . "\n";
    $host = parse_url($url, PHP_URL_HOST);
    echo 'url_host=' . ($host !== false && $host !== null ? (string) $host : '') . "\n";
    $expected = 'pwanailulmuna-developernme.aws-ap-northeast-1.turso.io';
    echo 'url_host_match=' . ($host === $expected ? 'yes' : 'no_check_dashboard') . "\n";
}
