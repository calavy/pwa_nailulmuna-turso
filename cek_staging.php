<?php
$dbFile = __DIR__ . '/storage/turso_staging.db';

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = $pdo->query("
        SELECT name
        FROM sqlite_master
        WHERE type = 'table'
          AND name NOT LIKE 'sqlite_%'
        ORDER BY name
    ")->fetchAll(PDO::FETCH_COLUMN);

    echo "KONEKSI SQLITE: BERHASIL\n";
    echo "JUMLAH TABEL: " . count($tables) . "\n";
    echo "DAFTAR TABEL DAN JUMLAH BARIS:\n";

    foreach ($tables as $table) {
        $quoted = '"' . str_replace('"', '""', $table) . '"';
        $count = $pdo->query(
            "SELECT COUNT(*) FROM $quoted"
        )->fetchColumn();

        echo $table . ": " . $count . " baris\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}