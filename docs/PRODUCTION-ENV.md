# Variabel lingkungan produksi

Hosting PHP (`pwa.nailulmuna.id`) — **jangan** commit kredensial ke GitHub.

## Database MySQL (default)

| Variabel | Wajib | Keterangan |
|----------|-------|------------|
| `DB_HOST` | Ya* | Host MySQL panel hosting |
| `DB_PORT` | Tidak | Default `3306` |
| `DB_NAME` | Ya* | Nama database |
| `DB_USER` | Ya* | User MySQL |
| `DB_PASS` | Ya* | Password MySQL |
| `PONDOK_DB_PROFILE` | Tidak | `hosting` → muat `database.local.hosting.php` |
| `PONDOK_SCHEMA_READY` | Disarankan | `1` = lewati ALTER otomatis saat login (DB sudah migrasi) |

\* Alternatif: file `config/database.local.php` di server (gitignored).

## Turso (staging / rencana cutover)

| Variabel | Keterangan |
|----------|------------|
| `PONDOK_DB_DRIVER` | `turso` untuk libSQL; default `mysql` |
| `TURSO_DATABASE_URL` | URL database Turso (remote, opsional) |
| `TURSO_AUTH_TOKEN` | Token API Turso (opsional) |

File: `config/database.turso.local.php` (salin dari `database.turso.example.php` staging atau `database.turso.production.example.php` live).

Server produksi: `composer install --no-dev` (paket `turso/libsql`, ekstensi **FFI** wajib).

## Aplikasi

| File | Keterangan |
|------|------------|
| `config/app.local.php` | Override `public_url`, `wali_public_url` jika perlu |
| `config/firebase.local.php` | FCM (opsional; bisa lewat Settings) |
| `config/midtrans.local.php` | Midtrans sandbox/live |

## PWA / HTTPS

- Pastikan situs dilayani lewat **HTTPS** agar service worker dan cookie `secure` aktif.
- Setelah deploy: **Pengaturan → Identitas pesantren** → simpan ulang logo agar ikon PWA terbaru.
- Checklist manual: [PWA-PRODUCTION-CHECKLIST.md](PWA-PRODUCTION-CHECKLIST.md)

## GitHub Actions deploy

Secrets: `SFTP_HOST`, `SFTP_PORT`, `SFTP_USER`, `SFTP_PRIVATE_KEY`, `SFTP_KNOWN_HOSTS`, `SFTP_REMOTE_ROOT`.

Workflow: pilih mode **full** untuk upload seluruh tree (kecuali exclude), atau **allowlist** untuk hotfix terbatas.
