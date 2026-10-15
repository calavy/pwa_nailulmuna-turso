# Inventaris kompatibilitas MySQL → Turso

Jalankan di mesin dev (read-only):

```powershell
C:\xampp\php\php.exe scripts/inventory_mysql_compat.php
```

Pola yang dihitung: `SHOW TABLES`, `SHOW COLUMNS`, `AUTO_INCREMENT`, `ON DUPLICATE KEY`, `GROUP_CONCAT`, `ADD COLUMN IF NOT EXISTS`, dll.

Perbaikan sudah diterapkan di codebase:

- [`helpers/pondok_db.php`](../helpers/pondok_db.php) — introspeksi skema MySQL + SQLite/libSQL
- `PONDOK_DB_DRIVER=turso` — DDL runtime dinonaktifkan (`app_ensure_schema_deferred`, `ensure_santri_compat_schema`)
- Staging: `php scripts/turso_staging_init.php` + [`migrations/turso/01_bootstrap.sql`](../migrations/turso/01_bootstrap.sql)

Cutover produksi: [TURSO-CUTOVER-RUNBOOK.md](TURSO-CUTOVER-RUNBOOK.md)
