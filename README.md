# PWA Nailul Muna (Turso)

Portal digital pondok — presensi, keuangan, santri, portal wali. Monolith **PHP 8.2+** + PWA (service worker, offline).

- **Repo:** https://github.com/calavy/pwa_nailulmuna-turso  
- **Produksi (target):** HTTPS + database **Turso** ([DEPLOY-GREENFIELD-TURSO.md](docs/DEPLOY-GREENFIELD-TURSO.md))  
- **Lokal:** XAMPP + MySQL — [CARA-PAKAI.md](CARA-PAKAI.md)

## Quick start (lokal MySQL)

1. XAMPP: Apache + MySQL  
2. Impor [`impor_lokal_pwa_nailulmuna.sql`](impor_lokal_pwa_nailulmuna.sql)  
3. Salin `config/database.local.example.php` → `config/database.local.php`  
4. Buka `/login.php` (path mengikuti nama folder di `htdocs`)

## Turso (staging)

```powershell
cd "C:\xampp\htdocs\pwa_nailulmuna - turso"
composer install
php scripts/turso_staging_init.php
php scripts/turso_apply_migrations.php
php scripts/turso_seed_minimal.php
$env:PONDOK_DB_DRIVER="turso"
php scripts/uat_turso_smoke.php
```

Skema: [`migrations/turso/`](migrations/turso/) — regenerate DDL: `php scripts/build_turso_schema.php` (review `02_full_schema.sql` sebelum produksi).

## Deploy

- GitHub Actions: [`.github/workflows/deploy-production.yml`](.github/workflows/deploy-production.yml)  
- Secrets & hosting: [docs/GITHUB-ACTIONS-SECRETS.md](docs/GITHUB-ACTIONS-SECRETS.md), [docs/HOSTING-HTTPS-SETUP.md](docs/HOSTING-HTTPS-SETUP.md)  
- Env: [docs/PRODUCTION-ENV.md](docs/PRODUCTION-ENV.md)

## Dokumen penting

| Topik | File |
|--------|------|
| Strategi MySQL vs Turso | [docs/DEPLOYMENT-STRATEGY.md](docs/DEPLOYMENT-STRATEGY.md) |
| Cutover | [docs/TURSO-CUTOVER-RUNBOOK.md](docs/TURSO-CUTOVER-RUNBOOK.md) |
| PWA live | [docs/PWA-PRODUCTION-CHECKLIST.md](docs/PWA-PRODUCTION-CHECKLIST.md) |
| Kompatibilitas SQL | [docs/MYSQL-TURSO-INVENTORY.md](docs/MYSQL-TURSO-INVENTORY.md) |

**Keamanan:** jangan commit `config/database.turso.local.php`, token Turso, atau `storage/*.db`.
