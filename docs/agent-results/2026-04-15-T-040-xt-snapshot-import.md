# Task

Implement `T-040` by adding an XT snapshot import step that imports products, categories, media, and documents into dedicated local snapshot tables without changing current delta behavior.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `README.md`
- `database.sql`
- `docs/tickets/open/T-040 - Introduce XT snapshot import to enable target-aware delta and avoid unnecessary exports.md`
- `docs/tickets/open/T-041 -Use XT snapshot data for target-aware delta and queue creation.md`
- `docs/agent-results/2026-04-15-T-028-media-document-delta-and-export-queue.md`
- `docs/agent-results/2026-04-15-T-031-xt-product-seo-writer.md`
- `docs/agent-results/2026-04-15-T-039-export-worker-monitoring-count-fix.md`
- `config/sources.php`
- `config/xt_mirror.php`
- `config/xt_write.php`
- `config/pipeline.php`
- `config/admin.php`
- `src/Service/WelaApiClient.php`
- `src/Web/Repository/XtApiClient.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/Repository/MigrationRepository.php`
- `wela-api/index.php`
- `wela-api/README.md`

# Changed files

- `database.sql`
- `migrations/012_create_xt_snapshot_tables.sql`
- `config/admin.php`
- `config/xt_snapshot.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtSnapshotService.php`
- `run_xt_snapshot.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `wela-api/index.php`
- `wela-api/README.md`
- `docs/tickets/done/T-040-xt-snapshot-import.md`
- `docs/agent-results/2026-04-15-T-040-xt-snapshot-import.md`

# Summary

- Added a new manual XT snapshot import step with dedicated snapshot tables for:
  - products
  - categories
  - media
  - documents
- Added a new read-only `fetch_rows` XT API action so the sync app can import paginated XT rows without direct XT DB access.
- Added `XtSnapshotService` plus `run_xt_snapshot.php`.
- Added pipeline UI support through `Run XT Snapshot`.
- Kept delta and export behavior unchanged.

# Open points

- The current `.env` loading order prefers values from the file over temporary process environment overrides. That did not block implementation, but it made isolated runtime validation more cumbersome.
- Snapshot import currently focuses on the fields needed for future target-aware delta work, not on a full mirror of every XT table column.

# Validation steps

- Executed syntax checks:
  - `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
  - `docker compose exec -T php php -l /app/src/Service/XtSnapshotService.php`
  - `docker compose exec -T php php -l /app/run_xt_snapshot.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/SyncLauncher.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec -T php php -l /app/src/Web/View/pipeline/index.php`
  - `docker compose exec -T php php -l /app/wela-api/index.php`
- Applied migration:
  - `curl -s -o /tmp/t040_migrations.out -w '%{http_code}' -X POST http://localhost:8080/status/migrations`
  - verified `012_create_xt_snapshot_tables` in `schema_migrations`
- Built isolated XT validation setup:
  - created temporary MySQL database `xt_t040`
  - created temporary XT tables:
    - `xt_products`
    - `xt_categories`
    - `xt_media`
    - `xt_media_link`
  - inserted representative product/category/media/document rows
  - created temporary local `wela-api/config.php`
  - started local test API on `http://127.0.0.1:8093`
- Executed live snapshot validation:
  - direct `WelaApiClient->health()`
  - direct `WelaApiClient->fetchRows("xt_products", ...)`
  - `php /app/run_xt_snapshot.php`
  - repeated `php /app/run_xt_snapshot.php`
  - web launcher run through `SyncLauncher()->launch("xt_snapshot")`
- Executed live data checks:
  - snapshot table counts
  - duplicate checks by unique external identity
  - sample mapping queries for products/categories/media/documents
  - export queue count before/after snapshot
  - latest `sync_runs` entries for `xt_snapshot`
  - HTTP check for `/pipeline` and `Run XT Snapshot`

- Bugs found and fixed during validation:
  - `WelaApiClient` used a slightly different URL/header construction than the working web XT client and failed against the local XT API signature path. Fixed by aligning the URL and request headers.
  - `XtSnapshotService` used `TRUNCATE` inside a transaction; MySQL auto-commit semantics broke the transaction. Fixed by switching to transactional `DELETE` refreshes for snapshot tables.

- Observed final validation state:
  - `xt_products_snapshot = 2`
  - `xt_categories_snapshot = 2`
  - `xt_media_snapshot = 2`
  - `xt_documents_snapshot = 1`
  - duplicate groups after repeated runs:
    - products = `0`
    - categories = `0`
    - media = `0`
    - documents = `0`
  - sample mappings:
    - products `101`, `102`
    - categories `10`, `11` with `parent_afs_id = 10`
    - media `afs-article-101-image_1`, `afs-article-102-image_1`
    - document `5001`
  - `export_queue` count remained `13534`
  - recent successful runs:
    - `107 xt_snapshot success`
    - `108 xt_snapshot success`
    - `109 xt_snapshot success`
  - `/pipeline` returned `200` and contains `Run XT Snapshot`

# Recommended next step

Implement `T-041` next by switching delta comparison for products, media, and documents from pure internal state to the new XT snapshot tables, while preserving the existing queue dedup safeguards.
