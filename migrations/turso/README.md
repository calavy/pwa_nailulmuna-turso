# Migrasi skema ke Turso (libSQL)

1. **Jangan** jalankan `impor_lokal_pwa_nailulmuna.sql` langsung di Turso — sintaks MySQL (ENUM, AUTO_INCREMENT, ENGINE).
2. Konversi skema dengan alat eksternal (mis. [sqlglot](https://github.com/tobymao/sqlglot) atau dump manual), lalu review FK dan tipe.
3. Regenerate DDL (setelah ubah skema MySQL): `php scripts/build_turso_schema.php` → review `02_full_schema.sql`.
4. Terapkan: `php scripts/turso_apply_migrations.php` (staging: `sqlite_path` di config).
5. Pilot modul read-only, lalu keuangan/presensi setelah UAT query.
6. Semua perubahan skema **versi di folder ini** — runtime `ALTER` di PHP dinonaktifkan untuk driver turso.
