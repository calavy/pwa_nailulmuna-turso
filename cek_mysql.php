<?php
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=pwa_nailulmuna;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "JUMLAH TABEL MYSQL: " . count($tables) . PHP_EOL;
echo str_repeat("-", 65) . PHP_EOL;

foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $count = $pdo->query("SELECT COUNT(*) FROM $quoted")->fetchColumn();
    echo str_pad($table, 45) . $count . " baris" . PHP_EOL;
}
