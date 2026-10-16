<?php

declare(strict_types=1);

/**
 * Buka PDO (sqlite) backed by Turso embedded replica atau file staging.
 *
 * @param array<string, mixed> $cfg database.turso.local.php
 * @param array<int, mixed> $pdoOptions
 */
function pondok_turso_open_pdo(array $cfg, array $pdoOptions): PDO
{
    $sqlitePath = trim((string) ($cfg['sqlite_path'] ?? ''));
    $url = trim((string) ($cfg['url'] ?? getenv('TURSO_DATABASE_URL') ?: ''));
    $token = (string) ($cfg['auth_token'] ?? getenv('TURSO_AUTH_TOKEN') ?: '');

    if ($url !== '' && $token !== '') {
        $syncPath = trim((string) ($cfg['sync_local_path'] ?? ''));
        if ($syncPath === '') {
            $syncPath = dirname(__DIR__) . '/storage/turso_sync_cache.db';
        }
        $dir = dirname($syncPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException(
                'Turso remote membutuhkan composer install (paket turso/libsql). Jalankan: composer install'
            );
        }
        require_once $autoload;

        if (!class_exists(\Libsql\Database::class)) {
            throw new RuntimeException('Class Libsql\\Database tidak ditemukan. Periksa paket turso/libsql.');
        }

        $libPath = str_starts_with($syncPath, 'file:') ? $syncPath : 'file:' . $syncPath;
        $database = new \Libsql\Database(
            path: $libPath,
            url: $url,
            authToken: $token,
        );
        $database->connect();
        if (method_exists($database, 'sync')) {
            try {
                $database->sync();
            } catch (Throwable $e) {
                $requireSync = getenv('PONDOK_TURSO_REQUIRE_SYNC') === '1'
                    || strtolower(trim((string) (getenv('GITHUB_ACTIONS') ?: ''))) === 'true';
                if ($requireSync) {
                    throw new RuntimeException('Turso remote sync failed');
                }
                error_log('[pondok_turso] sync warning: ' . $e->getMessage());
            }
        }

        return new PDO('sqlite:' . $syncPath, null, null, $pdoOptions);
    }

    if ($sqlitePath === '') {
        throw new RuntimeException('Turso: isi url+auth_token (remote) atau sqlite_path (staging).');
    }

    if (!str_starts_with($sqlitePath, ':') && !is_file($sqlitePath)) {
        $dir = dirname($sqlitePath);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    return new PDO('sqlite:' . $sqlitePath, null, null, $pdoOptions);
}
