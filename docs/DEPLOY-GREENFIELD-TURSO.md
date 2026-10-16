# Deploy greenfield — domain baru + Turso

Keputusan default proyek **pwa_nailulmuna-turso**: **Turso-only** (tanpa MySQL hosting). MySQL tetap dipakai hanya untuk development XAMPP jika `PONDOK_DB_DRIVER` tidak diset.

## Checklist infrastruktur (isi sebelum go-live)

| Item | Isi Anda | Catatan |
|------|----------|---------|
| Domain HTTPS | `https://________________` | Document root = folder aplikasi |
| Subdomain wali (opsional) | `https://________________` | DNS + vhost sama dengan root app |
| Hosting | SFTP/SSH, PHP **8.2+**, **FFI enabled** | Wajib untuk `turso/libsql` |
| Repo GitHub | `calavy/pwa_nailulmuna-turso` | Secrets SFTP di environment `production` |
| Turso DB | `________________` | Buat kosong via CLI; **jangan** impor dump lokal |

## Larangan (produksi)

- Jangan commit `database.turso.local.php`, token Turso, atau kunci SFTP ke GitHub.
- Jangan impor `impor_lokal_pwa_nailulmuna.sql` / `impor_lengkap_*` ke Turso produksi.
- Jangan jalankan `migrasi_*.sql` MySQL di server live Turso.

## Urutan eksekusi

1. Buat database Turso + token (lihat [`scripts/turso_bootstrap_remote.md`](../scripts/turso_bootstrap_remote.md)).
2. Generate/apply skema libSQL: `php scripts/turso_apply_migrations.php` (target remote via env).
3. Seed minimal: `php scripts/turso_seed_minimal.php`.
4. UAT: `php scripts/uat_turso_smoke.php`.
5. GitHub Actions secrets → deploy **full** (lihat [`GITHUB-ACTIONS-SECRETS.md`](GITHUB-ACTIONS-SECRETS.md)).
6. Di server: `composer install --no-dev`, salin [`config/database.turso.production.example.php`](../config/database.turso.production.example.php) → `database.turso.local.php`, set `PONDOK_DB_DRIVER=turso` (env panel atau `.user.ini` jika ada).
7. [`PWA-PRODUCTION-CHECKLIST.md`](PWA-PRODUCTION-CHECKLIST.md).
