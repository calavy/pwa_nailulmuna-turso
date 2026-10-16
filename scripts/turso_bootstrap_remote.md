# Bootstrap Turso Cloud (kosong)

Jalankan di mesin dev (Turso CLI terpasang, login `turso auth login`).

```bash
turso db create pwa-nailulmuna-prod --region nrt
turso db show pwa-nailulmuna-prod --url
turso db tokens create pwa-nailulmuna-prod
```

Simpan URL + token di password manager. **Jangan** commit ke git.

Apply skema (dari folder proyek, set env):

```powershell
$env:PONDOK_DB_DRIVER="turso"
$env:TURSO_DATABASE_URL="libsql://...."
$env:TURSO_AUTH_TOKEN="..."
cd "C:\xampp\htdocs\pwa_nailulmuna - turso"
php scripts/turso_apply_migrations.php
php scripts/turso_seed_minimal.php
php scripts/uat_turso_smoke.php
```

Skema dihasilkan dari struktur `impor_lokal_pwa_nailulmuna.sql` **tanpa data lokal** (hanya DDL + seed minimal terpisah).
