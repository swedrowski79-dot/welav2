# Task

Implement `T-029` by adding the XT-Commerce writer path for `media` and `document` export queue entries.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-029.md`
- `docs/IMPLEMENTATION_NOTES.md`
- `docs/agent-results/2026-04-15-T-028-media-document-delta-and-export-queue.md`
- `config/sources.php`
- `config/delta.php`
- `config/xt_write.php`
- `config/xt_mirror.php`
- `run_export_queue.php`
- `src/Database/ConnectionFactory.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/StageWriter.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/ExpandService.php`
- `wela-api/README.md`
- `wela-api/index.php`

# Changed files

- `config/xt_write.php`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtMediaDocumentWriter.php`
- `wela-api/README.md`
- `wela-api/index.php`
- `docs/tickets/done/T-029.md`
- `docs/agent-results/2026-04-15-T-029-xt-media-document-writer.md`

# Summary

- Added a focused `WelaApiClient` plus `XtMediaDocumentWriter` so the existing export worker can now consume `media` and `document` queue entries and write them into XT through `wela-api` instead of using direct XT database access.
- Kept the existing queue/state architecture intact: the writer runs before stage-state confirmation, and `ExportQueueWorker` only updates the confirmed export hash after the XT write succeeded.
- Reused `config/xt_write.php` for the stage-to-XT mapping and extended the media/document relation configs with queue-safe `identity_columns` and `delete_match_columns` so link upserts stay idempotent and delete-style payloads can unlink by `m_id` + `type`.
- Extended `wela-api` with minimal safe write actions:
  - `lookup_map`
  - `upsert_row`
  - `delete_rows`
- Verified both success and failure behavior in an isolated XT test database:
  - success path wrote one image media row, one document media row, and both product links, then marked queue rows `done`
  - repeated processing of the same payloads left XT at exactly two media rows and two link rows
  - a missing product reference produced queue `error`, no confirmed export state, and no additional XT rows

# Open points

- Product queue entries still use the previous local confirmation flow; this ticket only adds XT writing for `media` and `document`.
- Removal handling currently unlinks XT media relations for delete-style payloads; orphaned `xt_media` rows are not garbage-collected in this ticket.
- The local validation had to override the configured XT API endpoint directly in the isolated test script because `config/sources.php` prefers `.env` values over process environment variables.

# Validation steps

- Executed:
  - `docker compose up -d --build`
  - `docker compose exec -T php php -l /app/run_export_queue.php`
  - `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
  - `docker compose exec -T php php -l /app/src/Service/XtMediaDocumentWriter.php`
  - `docker compose exec -T php php -l /app/wela-api/index.php`
  - started an isolated `wela-api` server inside the PHP container on `127.0.0.1:8090`
  - created isolated test tables:
    - `xt_writer_test.xt_products`
    - `xt_writer_test.xt_media`
    - `xt_writer_test.xt_media_link`
    - `stage_sync.export_queue_t029_test`
    - `stage_sync.product_media_export_state_t029_test`
    - `stage_sync.product_document_export_state_t029_test`
    - `stage_sync.export_queue_t029_error_test`
    - `stage_sync.product_media_export_state_t029_error_test`
  - ran `ExportQueueWorker` against the isolated temp queue with a direct XT endpoint override in the script
  - reran the same temp media/document payloads a second time
  - ran an isolated error-path worker pass with `afs_artikel_id = missing-product`
- Observed:
  - first success run: `done = 2`, `error = 0`
  - XT test DB after success: `xt_media = 2`, `xt_media_link = 2`
  - temp state tables after success: `product_media_export_state_t029_test = 1`, `product_document_export_state_t029_test = 1`
  - second success run kept XT counts at `xt_media = 2` and `xt_media_link = 2`
  - failure-path run produced `status = error`, `last_error = XT-Referenz fuer 'xt_products' mit external_id 'missing-product' wurde nicht gefunden.`, state rows stayed `0`, and XT counts stayed unchanged

# Recommended next step

Extend the same XT writer pattern to product queue entries once the desired XT product write surface and transaction boundaries are defined.
