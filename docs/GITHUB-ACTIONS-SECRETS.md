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

**Jangan** simpan `TURSO_AUTH_TOKEN` atau password DB di GitHub Secrets — hanya di server (`config/database.turso.local.php`).

## Menjalankan deploy

1. **Actions → Deploy Production (SFTP) → Run workflow**
2. Input `confirm`: ketik **`deploy`**
3. `deploy_mode`: **`full`** (rilis awal) atau **`allowlist`** (hotfix)

Atau push tag `release-YYYY-MM-DD` untuk allowlist otomatis.

Setelah deploy pertama, buat manual di server:

- `uploads/` (+ subfolder logos, profiles, …)
- `config/database.turso.local.php` dari template
- Opsional `config/app.local.php` (`public_url` domain baru)
