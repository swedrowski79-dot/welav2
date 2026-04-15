# Task

Implement `T-033` by adding expand performance diagnostics and separate expand/delta runtime visibility without changing expand or delta behavior.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-033 – Expand-Performance analysieren und vorbereiten.md`
- relevant result files:
  - `docs/agent-results/2026-04-15-T-028-media-document-delta-and-export-queue.md`
  - `docs/agent-results/2026-04-15-T-035-web-pipeline-flow-alignment.md`
  - `docs/agent-results/2026-04-15-T-038-export-worker-failure-visibility.md`
  - `docs/agent-results/2026-04-15-T-039-export-worker-monitoring-count-fix.md`
- `config/expand.php`
- `config/delta.php`
- `run_expand.php`
- `run_delta.php`
- `src/Service/ExpandService.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ProductDeltaService.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/View/sync-runs/index.php`
- `src/Web/View/sync-runs/show.php`

# Changed files

- `src/Service/ExpandService.php`
- `src/Service/DeltaRunnerService.php`
- `run_expand.php`
- `run_delta.php`
- `src/Web/View/sync-runs/show.php`
- `docs/tickets/done/T-033-expand-performance-diagnostics.md`
- `docs/agent-results/2026-04-15-T-033-expand-performance-diagnostics.md`

# Summary

- Added structured expand diagnostics in `ExpandService`:
  - total expand runtime
  - per-definition runtime
  - per-definition source row counts
  - per-definition target row counts
  - per-definition insert batch counts
- Replaced `SELECT *` in expand processing with explicit column lists derived from the configured slots.
- Added total delta runtime measurement in `DeltaRunnerService`.
- Updated `run_expand.php` so the expand run now stores separate `expand` and `delta` diagnostic blocks in `sync_runs.context_json`.
- Updated `run_delta.php` so delta diagnostics are stored under `context_json.delta`.
- Extended `/sync-runs/show` with dedicated `Expand-Diagnostik` and `Delta-Diagnostik` sections, including separate `Expand Laufzeit` and `Delta Laufzeit` display.

# Open points

- The expand step still intentionally includes delta execution in `run_expand.php`; this ticket only made the two runtimes visible separately.
- Push was not performed because the repository workflow requires an explicit user request before pushing.

# Validation steps

- Baseline before changes:
  - `docker compose exec -T php php /app/run_expand.php`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT 'stage_attribute_translations', COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS('|', afs_artikel_id, sku, language_code, sort_order, attribute_name, attribute_value, source_directory))),0) FROM stage_attribute_translations UNION ALL SELECT 'stage_product_media', COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS('|', media_external_id, afs_artikel_id, source_slot, file_name, path, type, document_type, sort_order, position))),0) FROM stage_product_media;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT entity_type, action, status, COUNT(*) FROM export_queue GROUP BY entity_type, action, status ORDER BY entity_type, action, status;"`
- Syntax checks after changes:
  - `docker compose exec -T php php -l /app/src/Service/ExpandService.php`
  - `docker compose exec -T php php -l /app/src/Service/DeltaRunnerService.php`
  - `docker compose exec -T php php -l /app/run_expand.php`
  - `docker compose exec -T php php -l /app/run_delta.php`
  - `docker compose exec -T php php -l /app/src/Web/View/sync-runs/show.php`
- Runtime validation after changes:
  - `docker compose exec -T php php /app/run_expand.php`
  - `docker compose exec -T php php /app/run_delta.php`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT 'stage_attribute_translations', COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS('|', afs_artikel_id, sku, language_code, sort_order, attribute_name, attribute_value, source_directory))),0) FROM stage_attribute_translations UNION ALL SELECT 'stage_product_media', COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS('|', media_external_id, afs_artikel_id, source_slot, file_name, path, type, document_type, sort_order, position))),0) FROM stage_product_media;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT entity_type, action, status, COUNT(*) FROM export_queue GROUP BY entity_type, action, status ORDER BY entity_type, action, status;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, run_type, context_json FROM sync_runs WHERE run_type IN ('expand','delta') ORDER BY id DESC LIMIT 2;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, level, message, context_json FROM sync_logs WHERE sync_run_id IN (111,112) AND message LIKE 'Expand%' OR (sync_run_id IN (111,112) AND message = 'Delta abgeschlossen.') ORDER BY id DESC LIMIT 10;"`
  - `docker compose exec -T php php -r '$ctx = stream_context_create(["http" => ["method" => "GET", "ignore_errors" => true]]); $html = file_get_contents("http://127.0.0.1:8080/sync-runs/show?id=111", false, $ctx); echo (strpos($html, "Expand-Diagnostik") !== false ? "expand diagnostics ok\n" : "expand diagnostics missing\n"); echo (strpos($html, "Delta Laufzeit") !== false ? "delta runtime ok\n" : "delta runtime missing\n"); echo (strpos($html, "product_attributes_from_translations") !== false ? "definition ok\n" : "definition missing\n");'`
  - `docker compose exec -T php php -r '$ctx = stream_context_create(["http" => ["method" => "GET", "ignore_errors" => true]]); $html = file_get_contents("http://127.0.0.1:8080/sync-runs/show?id=112", false, $ctx); echo (strpos($html, "Delta-Diagnostik") !== false ? "delta diagnostics ok\n" : "delta diagnostics missing\n"); echo (strpos($html, "153.038 s") !== false ? "delta duration shown\n" : "delta duration missing\n");'`
- Observed:
  - expand output remained unchanged:
    - `stage_attribute_translations = 24820` and checksum aggregate `53271065191496`
    - `stage_product_media = 5331` and checksum aggregate `11474933604910`
  - delta/queue output remained unchanged:
    - `product / insert / pending = 5350`
    - `media / insert / pending = 5331`
    - `document / insert / pending = 2853`
  - latest expand run context now contains:
    - `expand.duration_seconds = 2.822`
    - `delta.duration_seconds = 216.725`
    - definition metrics for `product_attributes_from_translations` and `product_media_from_articles`
  - latest delta run context now contains `delta.duration_seconds = 153.038`
  - `/sync-runs/show?id=111` renders the expand diagnostics section and separate expand/delta runtime labels
  - `/sync-runs/show?id=112` renders the delta diagnostics section and measured runtime

# Recommended next step

If expand becomes a recurrent bottleneck, use the new per-definition diagnostics to decide whether the next step should be batching changes within a specific definition or splitting expand and delta into separately triggerable pipeline steps.

# Commit

- `5bde387` - `Implement T-033 expand diagnostics`
