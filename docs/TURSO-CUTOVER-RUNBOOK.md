# Runbook cutover MySQL → Turso

## Prasyarat

- Staging Turso dengan skema dikonversi (`migrations/turso/`)
- `PONDOK_DB_DRIVER=turso` diuji di staging dengan modul pilot (settings, daftar santri)
- Inventaris SQL: `php scripts/inventory_mysql_compat.php`
- Backup penuh MySQL produksi (Export SQL phpMyAdmin)

## Fase data (read-only di produksi)

1. Freeze penulisan: maintenance window atau baca-only di aplikasi (opsional flag).
2. Export data MySQL → transform ke INSERT compat SQLite (script ETL terpisah; uji di staging).
3. Import ke Turso staging; validasi jumlah baris per tabel kritis (`santri`, `keuangan_pembayaran`, `presensi`).
4. Regression: login, satu pembayaran test, satu scan presensi test, sync offline antrian kosong.

## Cutover

1. Set env produksi: `PONDOK_DB_DRIVER=turso` + `database.turso.local.php` remote.
2. Matikan cron WA sementara; nyalakan setelah smoke test.
3. Monitor error log PHP 30–60 menit.
4. **Rollback:** kembalikan `PONDOK_DB_DRIVER=mysql` + `database.local.php`; MySQL tetap source of truth sampai cutover sukses 48 jam.

## Modul kritis (urutan porting query)

1. Keuangan / jurnal / cashless — transaksi `beginTransaction`
2. Presensi + `api/offline/*`
3. WA cron (`cron/wa_auto.php`) — latensi query
4. Midtrans webhook, Google Calendar webhook

## Post-cutover

- Set `PONDOK_SCHEMA_READY=1` (DDL runtime sudah off untuk turso)
- Backup rutin Turso (CLI `turso db dump`)
- Arsip MySQL read-only 90 hari
