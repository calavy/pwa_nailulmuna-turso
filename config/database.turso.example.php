<?php

/**
 * Salin ke config/database.turso.local.php (gitignored).
 * Aktifkan: set env PONDOK_DB_DRIVER=turso
 *
 * Staging lokal: sqlite_path menunjuk ke file .db hasil konversi skema (lihat migrations/turso/).
 * Produksi Turso remote: composer install (turso/libsql), kosongkan sqlite_path, isi url + auth_token.
 */
return [
    /** Path file SQLite untuk staging (relatif ke root proyek atau absolut). */
    'sqlite_path' => dirname(__DIR__) . '/storage/turso_staging.db',

    /** Opsional — koneksi remote (butuh turso/libsql-php). */
    'url' => '',
    'auth_token' => '',
    'sync_local_path' => '',
];
