# GitHub Actions — secrets deploy SFTP

Repo: **https://github.com/calavy/pwa_nailulmuna-turso**

## Environment

1. **Settings → Environments → New environment** → nama: `production`
2. (Disarankan) Required reviewers + protection rules.

## Repository secrets (atau environment secrets)

| Secret | Contoh | Keterangan |
|--------|--------|------------|
| `SFTP_HOST` | `ftpupload.net` | Hostname SFTP/SSH |
| `SFTP_PORT` | `22` atau `65002` | Port panel hosting |
| `SFTP_USER` | `if0_xxxxx` | User SFTP |
| `SFTP_PRIVATE_KEY` | isi file `id_ed25519` | **Private key** (bukan passphrase di secret terpisah jika key tanpa passphrase) |
| `SFTP_KNOWN_HOSTS` | baris `ssh-keyscan` | Jalankan: `ssh-keyscan -p PORT HOST` |
| `SFTP_REMOTE_ROOT` | `/domains/domain.com/public_html` | Path document root di server |

### Turso (CI smoke read-only)

| Secret | Keterangan |
|--------|------------|
| `TURSO_DATABASE_URL` | URL libsql database Turso (`pwanailulmuna`, region Tokyo) — dipakai job **turso_remote_smoke** |
| `TURSO_AUTH_TOKEN` | Token API Turso — **jangan** commit ke repo atau tampilkan di log Actions |

Workflow [`ci-php.yml`](../.github/workflows/ci-php.yml) menjalankan `scripts/ci_turso_validate.php` (hanya `SELECT` read-only). Nilai secret tidak dicetak.

Di server produksi, URL/token bisa juga hanya via env (`TURSO_*`) tanpa hardcode di `config/database.turso.local.php`.

**Jangan** simpan password MySQL produksi di GitHub Secrets kecuali ada kebutuhan khusus; Turso token hanya untuk CI read-only + konfigurasi server (bukan di file yang di-commit).

## CI (push / PR ke `main`)

- **CI PHP lint** — syntax, `composer validate` / install, `ci_turso_config_check`, smoke remote Turso.
- **Turso migrate (manual)** — `workflow_dispatch` saja; tidak migrate/import otomatis on push.

## Menjalankan deploy

1. **Actions → Deploy Production (SFTP) → Run workflow**
2. Input `confirm`: ketik **`deploy`**
3. `deploy_mode`: **`full`** (rilis awal) atau **`allowlist`** (hotfix)

Atau push tag `release-YYYY-MM-DD` untuk allowlist otomatis.

Setelah deploy pertama, buat manual di server:

- `uploads/` (+ subfolder logos, profiles, …)
- `config/database.turso.local.php` dari template
- Opsional `config/app.local.php` (`public_url` domain baru)
