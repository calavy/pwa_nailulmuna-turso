# Migrasi skema ke Turso (libSQL)

1. **Jangan** jalankan `impor_lokal_pwa_nailulmuna.sql` langsung di Turso — sintaks MySQL (ENUM, AUTO_INCREMENT, ENGINE).
2. Konversi skema dengan alat eksternal (mis. [sqlglot](https://github.com/tobymao/sqlglot) atau dump manual), lalu review FK dan tipe.
3. Regenerate DDL dari MySQL live: `php scripts/build_turso_schema_from_mysql.php` → review `02_full_schema.sql`.
   Fallback dump file: `php scripts/build_turso_schema.php`.
4. Terapkan: `php scripts/turso_staging_fresh_apply.php` (backup + apply) atau `PONDOK_DB_DRIVER=turso php scripts/turso_apply_migrations.php`.
5. Verifikasi skema: `php scripts/turso_verify_schema.php`.
6. Import data: `php scripts/turso_import_mysql_data.php` lalu `php scripts/turso_import_mysql_data.php --apply`.
7. UAT: `PONDOK_DB_DRIVER=turso php scripts/uat_turso_smoke.php`.
8. Semua perubahan skema **versi di folder ini** — runtime `ALTER` di PHP dinonaktifkan untuk driver turso.
