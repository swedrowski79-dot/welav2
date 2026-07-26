## Task

Normale `stage_sync`-Tabellen zuruecksetzen, `afs_extras` unangetastet lassen, die Schnittstellen erneut pruefen und die komplette Pipeline einmal neu durchlaufen lassen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `src/Service/ImportWorkflow.php`
- `run_expand.php`
- `run_delta.php`
- `run_xt_mirror.php`
- `run_full_pipeline.php`
- `src/Service/XtProductWriter.php`

## Changed files

- `docs/agent-results/2026-04-20-full-reset-and-pipeline-check.md`

## Summary

- Alle normalen Daten- und Laufzeittabellen in `stage_sync` wurden geleert:
  - RAW
  - STAGE
  - Export Queue / Export State
  - XT Mirror / Snapshot
  - Monitoring (`sync_runs`, `sync_logs`, `sync_errors`)
- `afs_extras` wurde nicht angefasst.
- XT-API-Health war vor dem Lauf erfolgreich.
- Danach wurde `run_full_pipeline.php` auf leerem `stage_sync` erfolgreich ausgefuehrt.

Run-Status:
- `import_all = success`
- `merge = success`
- `xt_mirror = success`
- `expand = success`
- `export_queue_worker = success`
- `full_pipeline = success`

Tabellenstand nach dem Lauf:
- `raw_afs_articles = 5471`
- `raw_afs_categories = 72`
- `raw_afs_documents = 2957`
- `stage_products = 5471`
- `stage_product_translations = 22400`
- `stage_categories = 72`
- `stage_category_translations = 296`
- `stage_attribute_translations = 39788`
- `stage_product_media = 5455`
- `stage_product_documents = 2957`
- `xt_mirror_products = 6791`

Queue-Endstand:
- `done = 4671`
- `error = 8`
- `pending = 50`
- `processing = 2544`

## Open points

- Obwohl `export_queue_worker` und `full_pipeline` auf `success` stehen, bleiben `2544` Queue-Eintraege auf `processing`.
- Verteilung der verbleibenden `processing`-Eintraege:
  - `category = 228`
  - `product = 1000`
  - `media = 316`
  - `document = 1000`
- Die `50` verbleibenden `pending`-Eintraege sind Produkte mit Retry-Fehler:
  - `SQLSTATE[HY000]: General error: 1364 Field 'products_master_slave_order' doesn't have a default value`
- Die `8` `error`-Eintraege sind Dokumente mit fehlender XT-Produktreferenz:
  - `XT-Referenz fuer 'xt_products' mit external_id '...' wurde nicht gefunden.`

## Validation steps

- Reset der `stage_sync`-Tabellen per `TRUNCATE`
- XT-API-Health:
  - `docker compose exec -T php php -r '... WelaApiClient->health() ...'`
- kompletter Lauf:
  - `docker compose exec -T php php /app/run_full_pipeline.php`
- Fortschritts- und Endpruefung ueber:
  - `sync_runs`
  - `export_queue`
  - zentrale RAW-/STAGE-/Mirror-Tabellen

## Recommended next step

- Den Export-Worker-Endzustand gesondert korrigieren:
  - warum `processing`-Eintraege trotz `success` nicht freigegeben werden
  - Produktinsert-Fix fuer `products_master_slave_order`
  - Dokumente erst exportieren, wenn die zugehoerigen XT-Produkte sicher vorhanden sind
