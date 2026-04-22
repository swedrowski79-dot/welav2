## Task

Gesamten Pipeline-Ablauf pruefen, den Blocker im `expand`-/Delta-Pfad beheben und den Export Worker auf Batchgroesse `5000` ausrichten.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docker-compose.yml`
- `config/delta.php`
- `config/sources.php`
- `run_import_all.php`
- `run_merge.php`
- `run_expand.php`
- `run_export_queue.php`
- `run_full_pipeline.php`
- `src/Database/ConnectionFactory.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Service/ExpandService.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/XtProductWriter.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/SyncLauncher.php`

## Changed files

- `.env`
- `config/delta.php`
- `docker-compose.yml`
- `docs/agent-results/2026-04-19-full-pipeline-fix-and-batch-5000.md`

## Summary

- Der bisherige Haenger war kein eigentlicher `expand`-Fehler, sondern ein MySQL-Abbruch waehrend des Delta-/Queue-Commits.
- Ursache war lokales Binary Logging im MySQL-Container:
  - `log_bin=1`
  - `binlog_error_action=ABORT_SERVER`
  - beim Commit eines `export_queue`-Inserts wurde der Server beendet
- Das wurde fuer das lokale Stage-Setup korrigiert, indem MySQL jetzt mit `--skip-log-bin` startet.
- Zusaetzlich wurde die Produkt-Delta-Queue-Insert-Groesse reduziert:
  - `product_export_queue.queue_insert_batch_size` von `200` auf `50`
  - damit werden grosse Produkt-Payloads vorsichtiger in die Queue geschrieben
- Die Worker-Defaults wurden konsistent auf `5000` gezogen:
  - `worker_batch_size` in `config/delta.php` fuer alle Queue-Entities auf `5000`
  - `.env` enthaelt `EXPORT_WORKER_BATCH_SIZE="5000"`
  - `.env` enthaelt jetzt auch `XT_PRODUCT_BATCH_REQUEST_SIZE="5000"`

## Open points

- Nach dem validierten Komplettlauf bleiben noch `1786` Produkt-Eintraege auf `pending`; ein weiterer Worker-Lauf ist dafuer noetig.
- Fuer den erneut gestarteten MySQL-Container mussten `afs_extras`-Grant und Bootstrap-Daten einmalig wiederhergestellt werden.

## Validation steps

- Syntax:
  - `docker compose exec -T php php -l /app/config/delta.php`
  - `docker compose exec -T php php -l /app/run_export_queue.php`
- MySQL-Konfiguration nach Fix:
  - `docker compose exec -T mysql mysql -uroot -proot -Nse "SELECT @@log_bin, @@binlog_error_action, @@sync_binlog, @@innodb_flush_log_at_trx_commit;" stage_sync`
  - Ergebnis: `log_bin = 0`
- Extras wiederhergestellt:
  - `docker compose exec -T mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS afs_extras ... GRANT ... FLUSH PRIVILEGES;"`
  - `docker compose exec -T php php /app/run_sync_afs_extras.php`
  - Ergebnis:
    - `afs_extras.article_translations = 16168`
    - `afs_extras.category_translations = 280`
- Volltest:
  - `docker compose exec -T php php /app/run_full_pipeline.php`
  - Ergebnis:
    - `import_all = success`
    - `merge = success`
    - `xt_mirror = success`
    - `expand = success`
    - `export_queue_worker = success`
    - `full_pipeline = success`
- Laufzeitbeobachtung:
  - `sync_logs` zeigten fortlaufend `Export Queue Eintrag verarbeitet`
  - Queue-Stand nach dem erfolgreichen Lauf:
    - `category done = 365`
    - `document done = 3852`
    - `media done = 5331`
    - `product done = 6000`
    - `product pending = 1786`

## Recommended next step

Den laufenden Worker zu Ende laufen lassen und danach bei Bedarf weitere Worker-Laeufe starten, bis keine `pending`-Eintraege mehr vorhanden sind.
