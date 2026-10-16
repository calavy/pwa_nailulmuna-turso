<?php

/**
 * Produksi Turso cloud — salin ke config/database.turso.local.php di server (gitignored).
 * Set env: PONDOK_DB_DRIVER=turso
 *
 * Jangan isi sqlite_path di produksi (kosongkan) agar koneksi remote dipakai.
 * Token/URL bisa juga dari env TURSO_DATABASE_URL dan TURSO_AUTH_TOKEN.
 */
return [
    'sqlite_path' => '',

    // Kosongkan di file; isi via env TURSO_DATABASE_URL dan TURSO_AUTH_TOKEN (server / GitHub Actions).
    'url' => '',
    'auth_token' => '',

    /** Cache file lokal untuk libsql sync (writable di server). */
    'sync_local_path' => dirname(__DIR__) . '/storage/turso_sync_cache.db',
];
