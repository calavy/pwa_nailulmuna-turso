<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/storage/turso_staging.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "KONEKSI SQLITE: BERHASIL\n";
    $tables = $db->query(
        "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"
    )->fetchAll(PDO::FETCH_COLUMN);

    echo "DAFTAR TABEL:\n";
    foreach ($tables as $table) {
        echo "- " . $table . "\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
