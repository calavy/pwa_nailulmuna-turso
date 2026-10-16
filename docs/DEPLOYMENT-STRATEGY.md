# Strategi deployment — repo `pwa_nailulmuna-turso`

## Keputusan (default proyek)

| Lingkungan | Database | Catatan |
|------------|----------|---------|
| **Lokal XAMPP** | MySQL (`config/database.local.php`) | `PONDOK_DB_DRIVER` tidak diset |
| **Staging** | Turso file SQLite (`storage/turso_staging.db`) atau Turso cloud dev | `PONDOK_DB_DRIVER=turso` |
| **Produksi (target)** | **Turso cloud only** | Lihat [DEPLOY-GREENFIELD-TURSO.md](DEPLOY-GREENFIELD-TURSO.md) |

**Jalur transisi (opsional):** publik HTTPS dengan MySQL di hosting existing ([DEPLOY-LIVE.md](../DEPLOY-LIVE.md)) sambil menyelesaikan port query ke libSQL, lalu cutover ([TURSO-CUTOVER-RUNBOOK.md](TURSO-CUTOVER-RUNBOOK.md)).

Produksi greenfield **tidak** memakai MySQL di server aplikasi.
