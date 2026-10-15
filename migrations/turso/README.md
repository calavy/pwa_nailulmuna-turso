# Migrasi skema ke Turso (libSQL)

1. **Jangan** jalankan `impor_lokal_pwa_nailulmuna.sql` langsung di Turso — sintaks MySQL (ENUM, AUTO_INCREMENT, ENGINE).
2. Konversi skema dengan alat eksternal (mis. [sqlglot](https://github.com/tobymao/sqlglot) atau dump manual), lalu review FK dan tipe.
3. Terapkan file SQL hasil konversi ke staging:
   - Set `PONDOK_DB_DRIVER=turso`
   - Salin `config/database.turso.example.php` → `config/database.turso.local.php`
   - Buat DB: `storage/turso_staging.db` via `01_bootstrap.sql`
4. Pilot read-only: `app_settings`, `users`, `santri` sebelum modul keuangan/presensi.
5. Semua perubahan skema **versi di folder ini** — runtime `ALTER` di PHP dinonaktifkan untuk driver turso.
