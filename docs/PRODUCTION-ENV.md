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

## Turso (staging / cutover / CI)

Database cloud: **`pwanailulmuna`** (region Tokyo). Driver PHP: paket **`turso/libsql`** (libSQL).

| Variabel | Keterangan |
|----------|------------|
| `PONDOK_DB_DRIVER` | `turso` untuk libSQL/SQLite staging atau remote; default `mysql` |
| `TURSO_DATABASE_URL` | URL libsql remote — prioritas atas `sqlite_path` staging |
| `TURSO_AUTH_TOKEN` | Token API Turso |

**Prioritas koneksi:** env `TURSO_*` (CI, produksi) → override file lokal. Staging lokal: `config/database.turso.local.php` dengan `sqlite_path` ke `storage/turso_staging.db` (salin dari `database.turso.example.php`). Produksi remote: `database.turso.production.example.php` — kosongkan `sqlite_path`, isi URL/token via env atau file gitignored.

Server produksi / GitHub Actions: PHP **8.3** disarankan; `composer install --no-dev` (paket `turso/libsql`, ekstensi **FFI** + **pdo_sqlite** wajib).

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
