## Task

API-Struktur im Verzeichnis `wela-api/` aufraeumen, damit die grossen Sync-/Upload-Pfade nicht weiter als schwer wartbarer Block in `index.php` liegen. Zusaetzlich den offenen Test-Run vor dem Umbau zuruecksetzen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `wela-api/index.php`
- `wela-api/src/ProductSyncService.php`
- `wela-api/src/SchemaInspector.php`
- `wela-api/src/CategorySyncService.php`
- `wela-api/src/FileTransferService.php`
- `wela-api/src/ShopMaintenanceService.php`

## Changed files

- `wela-api/index.php`
- `wela-api/src/ProductSyncService.php`
- `wela-api/src/SchemaInspector.php`
- `wela-api/src/CategorySyncService.php`
- `wela-api/src/FileTransferService.php`
- `wela-api/src/ShopMaintenanceService.php`

## Summary

- Der offene Monitoring-Lauf `sync_runs.id = 86` wurde vor dem Umbau geloescht; danach verblieben `0` Runs mit `status = 'running'`.
- Der Produkt-Sync laeuft jetzt ueber `WelaApi\ProductSyncService` statt ueber einen grossen monolithischen Funktionsblock in `wela-api/index.php`.
- Tabellen-/Index-Inspektion wurde in `WelaApi\SchemaInspector` ausgelagert.
- Kategorie-Sync und Kategorie-Batch laufen jetzt ueber `WelaApi\CategorySyncService`.
- Upload/Verzeichnislogik liegt jetzt in `WelaApi\FileTransferService`.
- Cache-/Template-Refresh liegt jetzt in `WelaApi\ShopMaintenanceService`.
- `wela-api/index.php` enthaelt fuer Produkt-, Kategorie-, Upload- und Maintenance-Pfade jetzt nur noch schlanke Wrapper-Funktionen fuer Service-Erzeugung und Dispatch.
- Die alten duplizierten Produkt-, Kategorie-, Upload- und Cache-Helfer wurden aus `index.php` entfernt.
- Dadurch ist `wela-api/index.php` jetzt auf `1418` Zeilen geschrumpft.

## Open points

- Request-Bootstrap, Authentifizierung und Action-Routing liegen weiterhin direkt in `wela-api/index.php`.
- Generische DB-Helfer wie `wela_upsert_row()`, `wela_batch_upsert_rows()` und Logging-Funktionen sind weiterhin global und koennen in einem naechsten Schritt in gemeinsame Utility-Klassen verschoben werden.

## Validation steps

- `docker compose exec -T mysql mysql -uroot -proot stage_sync -e "DELETE FROM sync_logs WHERE sync_run_id=86; DELETE FROM sync_errors WHERE sync_run_id=86; DELETE FROM sync_runs WHERE id=86; SELECT COUNT(*) AS remaining_open_runs FROM sync_runs WHERE status='running';"`
- `docker compose exec -T php php -l /app/wela-api/index.php`
- `docker compose exec -T php php -l /app/wela-api/src/ProductSyncService.php`
- `docker compose exec -T php php -l /app/wela-api/src/SchemaInspector.php`
- `docker compose exec -T php php -l /app/wela-api/src/CategorySyncService.php`
- `docker compose exec -T php php -l /app/wela-api/src/FileTransferService.php`
- `docker compose exec -T php php -l /app/wela-api/src/ShopMaintenanceService.php`

## Recommended next step

Als naechstes den verbleibenden Bootstrap-/Routing-Teil in kleine Dispatcher/Helfer zerlegen und die globalen generischen DB-Utilities kapseln, damit `wela-api/index.php` nur noch einen sehr duennen HTTP-Einstiegspunkt bildet.
