<?php

declare(strict_types=1);

/** mysql (default) | turso — turso memakai libSQL/SQLite-compat via config turso local. */
function pondok_db_driver(): string
{
    static $driver = null;
    if ($driver !== null) {
        return $driver;
    }
    $fromEnv = strtolower(trim((string) (getenv('PONDOK_DB_DRIVER') ?: '')));
    if ($fromEnv === 'turso' || $fromEnv === 'libsql') {
        $driver = 'turso';

        return $driver;
    }
    $driver = 'mysql';

    return $driver;
}

function pondok_db_supports_mysql_ddl(): bool
{
    return pondok_db_driver() === 'mysql';
}

function pondok_db_is_sqlite_family(PDO $pdo): bool
{
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

    return $driver === 'sqlite';
}

/** URL + token Turso dari environment (tanpa mencetak nilai). */
function pondok_turso_env_credentials(): array
{
    $url = trim((string) (getenv('TURSO_DATABASE_URL') ?: ''));
    $token = trim((string) (getenv('TURSO_AUTH_TOKEN') ?: ''));

    return ['url' => $url, 'auth_token' => $token];
}

function pondok_turso_has_remote_credentials(): bool
{
    $c = pondok_turso_env_credentials();

    return $c['url'] !== '' && $c['auth_token'] !== '';
}

/**
 * Konfigurasi Turso: file lokal (staging) + override remote via env (CI/produksi).
 *
 * @return array{sqlite_path?:string,url?:string,auth_token?:string,sync_local_path?:string}
 */
function pondok_turso_config(): array
{
    $tursoFile = __DIR__ . '/../config/database.turso.local.php';
    $cfg = [];
    if (is_file($tursoFile)) {
        $loaded = require $tursoFile;
        if (!is_array($loaded)) {
            throw new RuntimeException('config/database.turso.local.php must return an array.');
        }
        $cfg = $loaded;
    }

    $env = pondok_turso_env_credentials();
    if ($env['url'] !== '') {
        $cfg['url'] = $env['url'];
    }
    if ($env['auth_token'] !== '') {
        $cfg['auth_token'] = $env['auth_token'];
    }

    if ($env['url'] !== '' && $env['auth_token'] !== '') {
        $cfg['sqlite_path'] = '';
    }

    if (($cfg['sync_local_path'] ?? '') === '') {
        $cfg['sync_local_path'] = dirname(__DIR__) . '/storage/turso_sync_cache.db';
    }

    $sqlitePath = trim((string) ($cfg['sqlite_path'] ?? ''));
    $url = trim((string) ($cfg['url'] ?? ''));
    $token = trim((string) ($cfg['auth_token'] ?? ''));

    if ($sqlitePath === '' && ($url === '' || $token === '')) {
        throw new RuntimeException(
            'Turso: set TURSO_DATABASE_URL + TURSO_AUTH_TOKEN (remote) or sqlite_path in config/database.turso.local.php (staging).'
        );
    }

    return $cfg;
}

/**
 * @param array<string, mixed> $mysqlConfig
 * @return array{pdo: PDO, dsn_config: array<string, mixed>}
 */
function pondok_db_connect(array $mysqlConfig): array
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    if (pondok_db_driver() === 'turso') {
        return pondok_db_connect_turso($options);
    }

    $host = (string) ($mysqlConfig['host'] ?? '127.0.0.1');
    $port = (string) ($mysqlConfig['port'] ?? '3306');
    $dbName = (string) ($mysqlConfig['dbname'] ?? 'pwa_nailulmuna');
    $dbUser = (string) ($mysqlConfig['user'] ?? 'root');
    $dbPass = (string) ($mysqlConfig['pass'] ?? '');

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    try {
        $pdo->exec("SET time_zone = '+07:00'");
    } catch (PDOException $e) {
    }

    return [
        'pdo' => $pdo,
        'dsn_config' => [
            'driver' => 'mysql',
            'dsn' => $dsn,
            'user' => $dbUser,
            'pass' => $dbPass,
            'options' => $options,
            'mysql' => [
                'host' => $host,
                'port' => $port,
                'dbname' => $dbName,
                'user' => $dbUser,
                'pass' => $dbPass,
            ],
        ],
    ];
}

/**
 * Turso: file SQLite lokal (staging) atau libsql-php jika vendor tersedia.
 *
 * @param array<int, mixed> $options
 * @return array{pdo: PDO, dsn_config: array<string, mixed>}
 */
function pondok_db_connect_turso(array $options): array
{
    $cfg = pondok_turso_config();

    require_once __DIR__ . '/pondok_turso_libsql.php';
    $pdo = pondok_turso_open_pdo($cfg, $options);
    $dsn = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'sqlite:turso'
        : 'libsql:remote';

    return [
        'pdo' => $pdo,
        'dsn_config' => [
            'driver' => 'turso',
            'dsn' => $dsn,
            'user' => '',
            'pass' => '',
            'options' => $options,
            'turso_cfg' => [
                'sqlite_path' => (string) ($cfg['sqlite_path'] ?? ''),
                'url' => trim((string) ($cfg['url'] ?? '')),
                'sync_local_path' => (string) ($cfg['sync_local_path'] ?? ''),
            ],
        ],
    ];
}

function pondok_database_name(PDO $pdo): string
{
    if (pondok_db_is_sqlite_family($pdo)) {
        return 'sqlite';
    }
    try {
        return (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '-');
    } catch (PDOException $e) {
        return '-';
    }
}

/** Daftar tabel DB — di-cache per request. */
function pondok_schema_tables(PDO $pdo): array
{
    static $cacheByPdo = [];
    $pdoKey = spl_object_id($pdo);
    if (isset($cacheByPdo[$pdoKey])) {
        return $cacheByPdo[$pdoKey];
    }
    $map = [];
    try {
        if (pondok_db_is_sqlite_family($pdo)) {
            $rows = $pdo->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $name = strtolower((string) ($row['name'] ?? ''));
                if ($name !== '') {
                    $map[$name] = true;
                }
            }
        } else {
            $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) ?: [];
            foreach ($rows as $row) {
                $name = strtolower((string) ($row[0] ?? ''));
                if ($name !== '') {
                    $map[$name] = true;
                }
            }
        }
    } catch (PDOException $e) {
        $map = [];
    }
    $cacheByPdo[$pdoKey] = $map;

    return $map;
}

function table_exists(PDO $pdo, string $tableName): bool
{
    $tables = pondok_schema_tables($pdo);

    return isset($tables[strtolower($tableName)]);
}

/** Kolom per tabel — di-cache per request. */
function pondok_schema_columns(PDO $pdo, string $tableName): array
{
    static $cacheByPdoTable = [];
    $tableKey = strtolower($tableName);
    $pdoKey = spl_object_id($pdo) . ':' . $tableKey;
    if (isset($cacheByPdoTable[$pdoKey])) {
        return $cacheByPdoTable[$pdoKey];
    }
    $map = [];
    if (!table_exists($pdo, $tableName)) {
        $cacheByPdoTable[$pdoKey] = $map;

        return $map;
    }
    try {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName) ?? $tableName;
        if (pondok_db_is_sqlite_family($pdo)) {
            $st = $pdo->query('PRAGMA table_info(' . $safeTable . ')');
            $rows = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            foreach ($rows as $row) {
                $col = strtolower((string) ($row['name'] ?? ''));
                if ($col !== '') {
                    $map[$col] = true;
                }
            }
        } else {
            $rows = $pdo->query('SHOW COLUMNS FROM `' . $safeTable . '`')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $col = strtolower((string) ($row['Field'] ?? ''));
                if ($col !== '') {
                    $map[$col] = true;
                }
            }
        }
    } catch (PDOException $e) {
        $map = [];
    }
    $cacheByPdoTable[$pdoKey] = $map;

    return $map;
}

function column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    $columns = pondok_schema_columns($pdo, $tableName);

    return isset($columns[strtolower($columnName)]);
}

/** Ekspresi CONCAT yang kompatibel MySQL / SQLite. */
function pondok_sql_concat(PDO $pdo, string ...$sqlParts): string
{
    if ($sqlParts === []) {
        return "''";
    }
    if (pondok_db_is_sqlite_family($pdo)) {
        return implode(' || ', array_map(static fn (string $p): string => '(' . $p . ')', $sqlParts));
    }

    return 'CONCAT(' . implode(', ', $sqlParts) . ')';
}
