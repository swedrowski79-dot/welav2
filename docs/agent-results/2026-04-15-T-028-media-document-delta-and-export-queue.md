# Task

Implement `T-028` by adding delta calculation and export queue handling for `stage_product_media` and `stage_product_documents`.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `README.md`
- `database.sql`
- `config/delta.php`
- `run_expand.php`
- `run_delta.php`
- `run_export_queue.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Web/Repository/SchemaHealthRepository.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/sync-runs/index.php`
- `migrations/002_create_export_queue.sql`
- `migrations/003_create_product_export_state.sql`
- `migrations/004_add_export_queue_claim_fields.sql`
- `migrations/005_add_export_queue_retry_fields.sql`
- recent delta/export result files:
  - `docs/agent-results/2026-04-14-delta-products.md`
  - `docs/agent-results/2026-04-14-T-003-export-worker-and-confirmed-state.md`
  - `docs/agent-results/2026-04-14-T-016-delta-batching-and-deduplication.md`
  - `docs/agent-results/2026-04-14-T-017-export-worker-batch-claiming.md`
  - `docs/agent-results/2026-04-14-T-018-export-worker-retry-and-error-handling.md`

# Changed files

- `database.sql`
- `config/admin.php`
- `config/delta.php`
- `run_expand.php`
- `run_delta.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Web/Repository/SchemaHealthRepository.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `migrations/010_alter_export_queue_entity_id.sql`
- `migrations/011_add_media_document_delta_state.sql`
- `docs/tickets/done/T-028-media-document-delta-and-export-queue.md`
- `docs/agent-results/2026-04-15-T-028-media-document-delta-and-export-queue.md`

# Summary

- Added dedicated export-state tables for media and documents:
  - `product_media_export_state`
  - `product_document_export_state`
- Extended `stage_product_media` and `stage_product_documents` with `hash` fields so current delta hashes are visible on the stage side, matching the product pattern.
- Changed `export_queue.entity_id` from integer to `VARCHAR(255)` so stable media identities like `media_external_id` can be queued without lossy conversion.
- Extended `config/delta.php` with `media_export_queue` and `document_export_queue` definitions and a shared execution order for all exportable entities.
- Generalized `ProductDeltaService` so it can now process:
  - the existing product payload with translations and attributes
  - flat media/document stage payloads
  - numeric and string entity identifiers
  - removal updates via a terminal removal hash
- Added `DeltaRunnerService` and wired it into both `run_expand.php` and `run_delta.php`, so product, media, and document delta calculation now run together.
- Extended `ExportQueueWorker` so one worker run now processes product, media, and document queue rows and confirms the matching export-state hashes after success.
- Strengthened queue de-duplication to allow only one active pending/processing queue row per entity, which prevents repeated delta runs from stacking `insert` plus `update` rows for the same media/document item before confirmation.
- Added admin/schema support for the new state tables and made delta-state reset clear all three state tables.
- Fixed `MigrationRepository` to tolerate MySQL DDL auto-commit behavior in multi-statement migrations.

# Open points

- The queue still stores only one active row per entity, so later changes that happen while an older pending row exists are intentionally coalesced instead of creating multiple active queue rows.
- The pipeline admin state page remains product-focused; media/document state is available in the stage browser but not yet surfaced in a dedicated admin summary view.
- No XT write/export behavior was added; queue processing still acts as a local confirmation worker only.

# Validation steps

- Executed:
  - `docker compose up -d --build`
  - `docker compose exec -T php php -l /app/config/delta.php`
  - `docker compose exec -T php php -l /app/src/Service/ProductDeltaService.php`
  - `docker compose exec -T php php -l /app/src/Service/DeltaRunnerService.php`
  - `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec -T php php -l /app/run_expand.php`
  - `docker compose exec -T php php -l /app/run_delta.php`
  - `docker compose exec -T php php -l /app/run_export_queue.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/SchemaHealthRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/PipelineAdminRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
  - `docker compose exec -T php php -r "require '/app/src/Web/bootstrap.php'; $db = App\Web\Repository\StageConnection::make(); $repo = new App\Web\Repository\MigrationRepository($db, '/app/migrations'); foreach ($repo->runPending() as $file) { echo $file, PHP_EOL; }"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SHOW TABLES LIKE 'product_media_export_state'; SHOW TABLES LIKE 'product_document_export_state'; SHOW COLUMNS FROM export_queue LIKE 'entity_id'; SHOW COLUMNS FROM stage_product_media LIKE 'hash'; SHOW COLUMNS FROM stage_product_documents LIKE 'hash';"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "TRUNCATE TABLE export_queue; TRUNCATE TABLE product_export_state; TRUNCATE TABLE product_media_export_state; TRUNCATE TABLE product_document_export_state;"`
  - `docker compose exec -T php php /app/run_merge.php`
  - `docker compose exec -T php php /app/run_expand.php`
  - `docker compose exec -T php php /app/run_delta.php`
  - `docker compose exec -T php php /app/run_export_queue.php`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT entity_type, status, COUNT(*) FROM export_queue WHERE entity_type IN ('product','media','document') GROUP BY entity_type, status ORDER BY entity_type, status;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT 'product_export_state', COUNT(*) FROM product_export_state UNION ALL SELECT 'product_media_export_state', COUNT(*) FROM product_media_export_state UNION ALL SELECT 'product_document_export_state', COUNT(*) FROM product_document_export_state;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT entity_type, COUNT(*) FROM export_queue WHERE status = 'done' AND entity_type IN ('product','media','document') GROUP BY entity_type ORDER BY entity_type;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT 'product_non_null_hash', COUNT(*) FROM product_export_state WHERE last_exported_hash IS NOT NULL UNION ALL SELECT 'media_non_null_hash', COUNT(*) FROM product_media_export_state WHERE last_exported_hash IS NOT NULL UNION ALL SELECT 'document_non_null_hash', COUNT(*) FROM product_document_export_state WHERE last_exported_hash IS NOT NULL;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "INSERT INTO product_media_export_state (entity_id, last_exported_hash, last_seen_at) VALUES ('t028-temp-media', 'temp-hash', NOW()) ON DUPLICATE KEY UPDATE last_exported_hash = VALUES(last_exported_hash), last_seen_at = VALUES(last_seen_at); INSERT INTO product_document_export_state (entity_id, last_exported_hash, last_seen_at) VALUES ('t028-temp-document', 'temp-hash', NOW()) ON DUPLICATE KEY UPDATE last_exported_hash = VALUES(last_exported_hash), last_seen_at = VALUES(last_seen_at);"`
  - `docker compose exec -T php php /app/run_delta.php`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT entity_type, entity_id, action, status FROM export_queue WHERE entity_id IN ('t028-temp-media', 't028-temp-document') ORDER BY entity_type, id;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "DELETE FROM export_queue WHERE entity_id IN ('t028-temp-media', 't028-temp-document'); DELETE FROM product_media_export_state WHERE entity_id = 't028-temp-media'; DELETE FROM product_document_export_state WHERE entity_id = 't028-temp-document';"`
- Observed:
  - migrations created both new export-state tables and added `hash` columns to stage media/documents
  - `export_queue.entity_id` is now `VARCHAR(255)`
  - first delta pass after a clean state produced:
    - `product pending = 5350`
    - `media pending = 5331`
    - `document pending = 2853`
  - the second delta pass left those pending counts unchanged, confirming duplicate prevention for active queue rows
  - one export worker run processed `100` rows for each of `product`, `media`, and `document`
  - confirmed export-state rows with non-null hashes after the worker run:
    - `product = 100`
    - `media = 100`
    - `document = 100`
  - temporary missing state rows produced pending `update` queue entries for:
    - `media / t028-temp-media`
    - `document / t028-temp-document`
  - temporary validation rows were cleaned up after verification

# Recommended next step

When the XT writer for media/documents is implemented, consume the already prepared queue payloads and keep using the existing worker-confirmed hash model as the post-success export baseline.
