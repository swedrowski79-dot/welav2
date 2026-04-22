## Task

Lokale Schnittstellen-Datenbank komplett leeren, Logs und Errors mit zuruecksetzen und danach die komplette Schnittstelle erneut laufen lassen. Der Export-Worker sollte Produkt-Batches von `5000` verarbeiten und die Queue vollstaendig abarbeiten.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `database.sql`
- `src/Service/ExportQueueWorker.php`
- `config/delta.php`
- `config/sources.php`
- `src/Service/XtProductWriter.php`

## Changed files

- `docs/agent-results/2026-04-17-reset-run-and-drain-5000.md`

## Summary

- `stage_sync` wurde komplett neu erstellt und das Schema aus `database.sql` frisch importiert.
- Dadurch wurden auch Queue, Monitoring-Logs und Fehlerdaten vollstaendig zurueckgesetzt.
- Anschliessend wurde die volle Pipeline erneut gestartet.
- Fuer Produkt-Exporte wurde `XT_PRODUCT_BATCH_REQUEST_SIZE=5000` gesetzt.
- Nach dem initialen Pipeline-Lauf wurde der Export-Worker in einer Schleife weiter ausgefuehrt, bis keine `pending`-Eintraege mehr vorhanden waren.

## Open points

- Keine offenen Queue-Eintraege mehr.

## Validation steps

- Reset:
  - `DROP DATABASE IF EXISTS stage_sync; CREATE DATABASE stage_sync ...`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql`
- Pipeline:
  - `docker compose exec -T -e XT_PRODUCT_BATCH_REQUEST_SIZE=5000 php php run_full_pipeline.php`
- Queue-Drain:
  - wiederholte `run_export_queue.php`-Laeufe mit `XT_PRODUCT_BATCH_REQUEST_SIZE=5000`
- Endstatus:
  - `full_pipeline = success`
  - `import_all = success`
  - `merge = success`
  - `xt_mirror = success`
  - `expand = success`
  - `export_queue_worker` mehrfach `success`
  - `done category = 279`
  - `done document = 2853`
  - `done media = 5331`
  - `done product = 6791`
  - `sync_errors = 0`

## Recommended next step

Jetzt den Shop fachlich pruefen, da die komplette Queue nach dem Reset wieder vollstaendig verarbeitet wurde.
