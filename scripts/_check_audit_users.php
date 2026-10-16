<?php

declare(strict_types=1);

putenv('PONDOK_DB_DRIVER=turso');
require_once dirname(__DIR__) . '/config/database.php';

$rows = $pdo->query('SELECT username, role FROM users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
echo 'users_count=' . count($rows) . "\n";
foreach ($rows as $row) {
    echo ($row['username'] ?? '') . '|' . ($row['role'] ?? '') . "\n";
}
