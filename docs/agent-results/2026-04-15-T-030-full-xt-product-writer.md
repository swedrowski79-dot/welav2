# Task

Implement `T-030` by adding the full XT-Commerce writer for product queue entries as an end-to-end sync.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `README.md`
- `docs/tickets/open/T-030 — Full Product XT Sync (End-to-End).md`
- `config/xt_write.php`
- `config/delta.php`
- `config/sources.php`
- `database.sql`
- `docs/IMPLEMENTATION_NOTES.md`
- `docs/agent-results/2026-04-15-T-029-xt-media-document-writer.md`
- `docs/agent-results/2026-04-15-T-028-media-document-delta-and-export-queue.md`
- `run_export_queue.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtMediaDocumentWriter.php`
- `wela-api/index.php`
- `wela-api/README.md`

# Changed files

- `config/xt_write.php`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtQueueWriter.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/XtCompositeWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtMediaDocumentWriter.php`
- `wela-api/index.php`
- `wela-api/README.md`
- `docs/tickets/done/T-030.md`
- `docs/agent-results/2026-04-15-T-030-full-xt-product-writer.md`

# Summary

- Added a dedicated `XtProductWriter` and plugged it into the existing export worker through a new `XtCompositeWriter`, so `product`, `media`, and `document` queue rows now share one queue-processing flow while keeping entity-specific XT logic isolated.
- Reused and extended the T-029 XT API path instead of bypassing it:
  - new `XtQueueWriter` contract
  - shared `AbstractXtWriter` for config-driven expression resolution, lookup maps, and queue payload handling
  - `WelaApiClient::syncProduct()` plus `wela-api` action `sync_product`
- `sync_product` performs one transactional XT write for:
  - base product row
  - product translations
  - category relation replacement
  - attribute entity upserts
  - attribute description upserts
  - product-to-attribute relation replacement
- Updated `config/xt_write.php` so product translation mapping matches the real queue payload shape (`translation.*`) and added XT attribute definitions plus replace metadata for category relations.
- Kept queue/state semantics unchanged:
  - queue rows become `done` only after the XT write completed successfully
  - `product_export_state` is only confirmed after success
  - category/reference failures leave the queue row at `error`
  - repeated product updates modify existing XT rows without creating duplicates

# Open points

- Product removal payloads are handled as offline updates (`products_status = 0`); they do not remove category or attribute structures from XT in this ticket.
- SEO URL writing remains outside this ticket even though `config/xt_write.php` already contains SEO definitions.
- The isolated validation needed explicit XT endpoint overrides in the runner scripts because `config/sources.php` prefers `.env` values over transient process environment variables.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/run_export_queue.php`
  - `docker compose exec -T php php -l /app/src/Service/XtQueueWriter.php`
  - `docker compose exec -T php php -l /app/src/Service/AbstractXtWriter.php`
  - `docker compose exec -T php php -l /app/src/Service/XtCompositeWriter.php`
  - `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
  - `docker compose exec -T php php -l /app/src/Service/XtMediaDocumentWriter.php`
  - `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
  - `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec -T php php -l /app/wela-api/index.php`
  - started isolated `wela-api` test servers inside the PHP container
  - created isolated XT test tables and temporary queue/state tables
  - ran isolated worker passes with direct XT endpoint override for:
    - combined product + media + document compatibility
    - product with translations and attributes
    - product update
    - idempotent repeat update
    - product error path
- Observed:
  - combined compatibility run for product `1113`:
    - worker result: `product done = 1`, `media done = 1`, `document done = 1`
    - XT result: `xt_products = 1`, `xt_media = 2`, `xt_media_link = 2`
    - temp state result: `product_state = 1`, `media_state = 1`, `document_state = 1`
  - detailed product run for product `68`:
    - XT result: `xt_products = 1`
    - `xt_products_description = 4`
    - `xt_products_to_categories = 1`
    - `xt_plg_products_attributes = 1`
    - `xt_plg_products_attributes_description = 4`
    - `xt_plg_products_to_attributes = 1`
  - update run for product `68` changed XT values in place:
    - `products_quantity` updated to `222.0000`
    - English product name updated to `E spigot GANI updated`
    - XT row counts remained unchanged
  - repeated update run kept counts stable:
    - `xt_products = 1`
    - `xt_products_description = 4`
    - `xt_products_to_categories = 1`
    - `xt_plg_products_attributes = 1`
    - `xt_plg_products_attributes_description = 4`
    - `xt_plg_products_to_attributes = 1`
  - error-path run with invalid category `999999` produced:
    - queue status `error`
    - `last_error = XT-Referenz fuer 'xt_categories' mit external_id '999999' wurde nicht gefunden.`
    - temp product export state stayed `0`
    - XT product/translation/attribute row counts stayed unchanged
  - isolated XT test database, temp queue/state tables, and local API processes were removed after validation

# Recommended next step

If XT SEO handling becomes part of the export scope, add it on top of the same `AbstractXtWriter`/`sync_product` pattern instead of introducing a second XT write path.
