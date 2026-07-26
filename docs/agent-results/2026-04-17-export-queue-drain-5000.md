## Task

Die komplette Export-Queue vollstaendig abarbeiten und dabei fuer Produkt-Batches `XT_PRODUCT_BATCH_REQUEST_SIZE=5000` verwenden.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `src/Service/ExportQueueWorker.php`
- `config/delta.php`
- `config/sources.php`
- `src/Service/XtProductWriter.php`

## Changed files

- `docs/agent-results/2026-04-17-export-queue-drain-5000.md`

## Summary

- Die Export-Queue wurde nicht nur einmal angestossen, sondern in einer Schleife so oft abgearbeitet, bis keine `pending`-Eintraege mehr vorhanden waren.
- Fuer die Produkt-API-Aufrufe wurde dabei `XT_PRODUCT_BATCH_REQUEST_SIZE=5000` gesetzt.
- Insgesamt wurden nach dem initialen Pipeline-Lauf noch sechs weitere erfolgreiche Worker-Laeufe benoetigt, um die Queue vollstaendig zu leeren.
- Am Ende stehen alle Queue-Eintraege auf `done`.

## Open points

- Keine offenen Queue-Eintraege mehr.

## Validation steps

- Wiederholte Worker-Laeufe mit:
  - `XT_PRODUCT_BATCH_REQUEST_SIZE=5000 docker compose exec -T php php run_export_queue.php`
- Fortschrittskontrolle ueber `stage_sync.export_queue`
- Endstatus:
  - `done category = 279`
  - `done document = 2853`
  - `done media = 5331`
  - `done product = 6791`
  - `sync_errors = 0`

## Recommended next step

Shopinhalt fachlich pruefen, jetzt wo die Queue vollstaendig abgearbeitet ist.
