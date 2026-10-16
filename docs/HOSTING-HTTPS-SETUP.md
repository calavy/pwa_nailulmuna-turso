# Hosting HTTPS — checklist operasional

Repo: [calavy/pwa_nailulmuna-turso](https://github.com/calavy/pwa_nailulmuna-turso)

## Persyaratan server

- PHP **8.2+** dengan `pdo`, `pdo_sqlite`, **FFI enabled** (Turso/libsql)
- HTTPS (Let's Encrypt atau SSL panel)
- SFTP/SSH untuk deploy Actions
- Cron CLI untuk [`cron/wa_auto.php`](../cron/wa_auto.php)
- Outbound HTTPS ke Turso (`*.turso.io`)

## Setelah deploy pertama (manual di server)

1. `composer install --no-dev --optimize-autoloader`
2. `config/database.turso.local.php` dari [`config/database.turso.production.example.php`](../config/database.turso.production.example.php)
3. Env `PONDOK_DB_DRIVER=turso` (+ token Turso **hanya** di server)
4. Folder `uploads/` dengan subfolder logos/profiles (tidak ditimpa deploy)
5. Jalankan skema: `php scripts/turso_apply_migrations.php` (sekali, dari server atau CI dengan secret)
6. Seed: `php scripts/turso_seed_minimal.php`

## GitHub Actions

Lihat [GITHUB-ACTIONS-SECRETS.md](GITHUB-ACTIONS-SECRETS.md) — environment `production`, workflow **Deploy Production (SFTP)**, mode **full**.

## Verifikasi PWA

[PWA-PRODUCTION-CHECKLIST.md](PWA-PRODUCTION-CHECKLIST.md)

## UAT sebelum traffic

```powershell
$env:PONDOK_DB_DRIVER="turso"
php scripts/uat_turso_smoke.php
php scripts/turso_precutover_checklist.php
```
