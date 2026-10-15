-- Bootstrap minimal libSQL/SQLite untuk spike staging (bukan skema produksi lengkap).
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key TEXT NOT NULL PRIMARY KEY,
    setting_value TEXT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'pengurus',
    is_super_admin INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS santri (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nis TEXT NOT NULL UNIQUE,
    nama_santri TEXT NOT NULL,
    qr TEXT NULL,
    tingkatan TEXT NULL,
    is_aktif INTEGER NOT NULL DEFAULT 1,
    created_at TEXT DEFAULT (datetime('now'))
);
