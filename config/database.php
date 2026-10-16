<?php

/**
 * Lokal (XAMPP): config/database.local.php → pwa_nailulmuna / root / pass kosong.
 * Hosting: config/database.local.hosting.php → salin ke database.local.php di server,
 * atau set env PONDOK_DB_PROFILE=hosting.
 * Turso: env PONDOK_DB_DRIVER=turso + config/database.turso.local.php (staging) atau TURSO_DATABASE_URL + TURSO_AUTH_TOKEN (remote/CI)
 *
 * Prioritas MySQL: getenv DB_* → database.local.php → default lokal di bawah (tanpa password produksi).
 */
require_once __DIR__ . '/../helpers/pondok_db.php';

$host = '127.0.0.1';
$port = '3306';
$dbName = 'pwa_nailulmuna';
$dbUser = 'root';
$dbPass = '';

$envHost = getenv('DB_HOST');
if ($envHost !== false && trim((string) $envHost) !== '') {
    $host = trim((string) $envHost);
}
$envPort = getenv('DB_PORT');
if ($envPort !== false && trim((string) $envPort) !== '') {
    $port = trim((string) $envPort);
}
$envName = getenv('DB_NAME');
if ($envName !== false && trim((string) $envName) !== '') {
    $dbName = trim((string) $envName);
}
$envUser = getenv('DB_USER');
if ($envUser !== false && trim((string) $envUser) !== '') {
    $dbUser = trim((string) $envUser);
}
$envPass = getenv('DB_PASS');
if ($envPass !== false) {
    $dbPass = (string) $envPass;
}

$localFile = __DIR__ . '/database.local.php';
$hostingFile = __DIR__ . '/database.local.hosting.php';
$dbProfile = strtolower(trim((string) (getenv('PONDOK_DB_PROFILE') ?: '')));
if ($dbProfile === 'hosting' && is_file($hostingFile)) {
    $localFile = $hostingFile;
}
$GLOBALS['pondok_env'] = 'production';
if (pondok_db_driver() === 'mysql' && is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        if (!empty($local['environment'])) {
            $GLOBALS['pondok_env'] = strtolower(trim((string) $local['environment']));
        } else {
            $GLOBALS['pondok_env'] = 'local';
        }
        if (!empty($local['host'])) {
            $host = (string) $local['host'];
        }
        if (isset($local['port']) && (string) $local['port'] !== '') {
            $port = (string) $local['port'];
        }
        if (!empty($local['dbname'])) {
            $dbName = (string) $local['dbname'];
        }
        if (!empty($local['user'])) {
            $dbUser = (string) $local['user'];
        }
        if (array_key_exists('pass', $local)) {
            $dbPass = (string) $local['pass'];
        }
    }
}

try {
    $connected = pondok_db_connect([
        'host' => $host,
        'port' => $port,
        'dbname' => $dbName,
        'user' => $dbUser,
        'pass' => $dbPass,
    ]);
    $pdo = $connected['pdo'];
    $GLOBALS['pondok_pdo'] = $pdo;
    $GLOBALS['pondok_pdo_dsn_config'] = $connected['dsn_config'];
    if (pondok_db_driver() === 'turso') {
        $GLOBALS['pondok_env'] = 'local';
    }
} catch (Throwable $exception) {
    die('Koneksi database gagal: ' . $exception->getMessage());
}

/** PDO terpusat — aman dipanggil dari fungsi/layout (require_once tidak mengisi $pdo lokal). */
function pondok_pdo(): PDO
{
    if (isset($GLOBALS['pondok_pdo']) && $GLOBALS['pondok_pdo'] instanceof PDO) {
        return $GLOBALS['pondok_pdo'];
    }
    require_once __DIR__ . '/database.php';
    if (isset($GLOBALS['pondok_pdo']) && $GLOBALS['pondok_pdo'] instanceof PDO) {
        return $GLOBALS['pondok_pdo'];
    }

    throw new RuntimeException('Koneksi database tidak tersedia.');
}

/** Koneksi putus (timeout / MySQL restart) — umum saat sinkron presensi panjang. */
function pondok_pdo_is_gone_away(Throwable $e): bool
{
    if (pondok_db_driver() !== 'mysql') {
        return false;
    }
    $msg = strtolower($e->getMessage());

    return str_contains($msg, 'server has gone away')
        || str_contains($msg, 'lost connection')
        || str_contains($msg, '2006')
        || str_contains($msg, '2013');
}

function pondok_pdo_reconnect(): PDO
{
    $cfg = $GLOBALS['pondok_pdo_dsn_config'] ?? null;
    if (!is_array($cfg)) {
        throw new RuntimeException('Konfigurasi PDO tidak tersedia untuk reconnect.');
    }
    $driver = (string) ($cfg['driver'] ?? 'mysql');
    if ($driver === 'turso') {
        $connected = pondok_db_connect_turso((array) ($cfg['options'] ?? []));
    } else {
        $mysql = is_array($cfg['mysql'] ?? null) ? $cfg['mysql'] : [];
        $connected = pondok_db_connect([
            'host' => (string) ($mysql['host'] ?? parse_mysql_host_from_dsn((string) ($cfg['dsn'] ?? ''))),
            'port' => (string) ($mysql['port'] ?? '3306'),
            'dbname' => (string) ($mysql['dbname'] ?? parse_mysql_db_from_dsn((string) ($cfg['dsn'] ?? ''))),
            'user' => (string) ($mysql['user'] ?? $cfg['user'] ?? ''),
            'pass' => (string) ($mysql['pass'] ?? $cfg['pass'] ?? ''),
        ]);
    }
    $pdo = $connected['pdo'];
    $GLOBALS['pondok_pdo'] = $pdo;
    $GLOBALS['pondok_pdo_dsn_config'] = $connected['dsn_config'];

    return $pdo;
}

function parse_mysql_host_from_dsn(string $dsn): string
{
    if (preg_match('/host=([^;]+)/', $dsn, $m)) {
        return $m[1];
    }

    return '127.0.0.1';
}

function parse_mysql_db_from_dsn(string $dsn): string
{
    if (preg_match('/dbname=([^;]+)/', $dsn, $m)) {
        return $m[1];
    }

    return 'pwa_nailulmuna';
}

/**
 * @template T
 * @param callable(PDO): T $callback
 * @return T
 */
function pondok_pdo_run_with_retry(callable $callback, ?PDO $pdo = null): mixed
{
    $pdo = pondok_pdo_ping($pdo ?? pondok_pdo());
    try {
        return $callback($pdo);
    } catch (PDOException $e) {
        if (!pondok_pdo_is_gone_away($e)) {
            throw $e;
        }

        return $callback(pondok_pdo_reconnect());
    }
}

function pondok_pdo_ping(?PDO $pdo = null): PDO
{
    $pdo = $pdo ?? pondok_pdo();
    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        if (!pondok_pdo_is_gone_away($e)) {
            throw $e;
        }
        $pdo = pondok_pdo_reconnect();
        $pdo->query('SELECT 1');
    }

    return $pdo;
}

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Jakarta');
}

function ensure_santri_compat_schema(PDO $pdo): void
{
    if (!pondok_db_supports_mysql_ddl()) {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['pondok_santri_compat_v1'])) {
        return;
    }
    static $doneCli = false;
    if (session_status() !== PHP_SESSION_ACTIVE && $doneCli) {
        return;
    }
    if (!table_exists($pdo, 'santri')) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['pondok_santri_compat_v1'] = 1;
        }

        return;
    }

    $addColumnSafe = static function (string $sqlIfNotExists, string $sqlFallback) use ($pdo): void {
        try {
            $pdo->exec($sqlIfNotExists);
        } catch (PDOException $e) {
            try {
                $pdo->exec($sqlFallback);
            } catch (PDOException $e2) {
                $msg = strtolower($e2->getMessage());
                if (str_contains($msg, 'duplicate column') || str_contains($msg, '1060')) {
                    return;
                }
                throw $e2;
            }
        }
    };

    $addColumnSafe(
        'ALTER TABLE santri ADD COLUMN IF NOT EXISTS nama_santri VARCHAR(100) NULL',
        'ALTER TABLE santri ADD COLUMN nama_santri VARCHAR(100) NULL'
    );
    $addColumnSafe(
        'ALTER TABLE santri ADD COLUMN IF NOT EXISTS tingkatan VARCHAR(80) NULL',
        'ALTER TABLE santri ADD COLUMN tingkatan VARCHAR(80) NULL'
    );
    $addColumnSafe(
        'ALTER TABLE santri ADD COLUMN IF NOT EXISTS qr VARCHAR(120) NULL',
        'ALTER TABLE santri ADD COLUMN qr VARCHAR(120) NULL'
    );

    if (column_exists($pdo, 'santri', 'nama') && column_exists($pdo, 'santri', 'nama_santri')) {
        $pdo->exec("UPDATE santri SET nama_santri = nama WHERE (nama_santri IS NULL OR nama_santri = '') AND nama IS NOT NULL AND nama <> ''");
    }
    if (column_exists($pdo, 'santri', 'kelas_id') && column_exists($pdo, 'santri', 'tingkatan') && table_exists($pdo, 'kelas')) {
        $pdo->exec("
            UPDATE santri s
            LEFT JOIN kelas k ON k.id = s.kelas_id
            SET s.tingkatan = COALESCE(k.nama_kelas, s.tingkatan)
            WHERE (s.tingkatan IS NULL OR s.tingkatan = '')
        ");
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['pondok_santri_compat_v1'] = 1;
    } else {
        $doneCli = true;
    }
}

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/helpers/app_path.php';
    if (function_exists('app_wali_host_enforce')) {
        app_wali_host_enforce();
    }
}
