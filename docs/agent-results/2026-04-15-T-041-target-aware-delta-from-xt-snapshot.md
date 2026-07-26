# Task

Implement `T-041` by making delta compare Stage entities against XT snapshot tables so only real differences are queued.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `README.md`
- `database.sql`
- `docs/tickets/open/T-041 -Use XT snapshot data for target-aware delta and queue creation.md`
- `docs/agent-results/2026-04-15-T-040-xt-snapshot-import.md`
- `docs/agent-results/2026-04-15-T-028-media-document-delta-and-export-queue.md`
- `config/delta.php`
- `config/xt_snapshot.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/XtSnapshotService.php`
- `wela-api/index.php`
- `src/Web/Repository/MigrationRepository.php`

# Changed files

- `database.sql`
- `migrations/013_add_xt_product_snapshot_compare_fields.sql`
- `config/delta.php`
- `config/xt_snapshot.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtSnapshotService.php`
- `wela-api/index.php`
- `src/Web/Repository/MigrationRepository.php`
- `docs/tickets/done/T-041-target-aware-delta-from-xt-snapshot.md`
- `docs/agent-results/2026-04-15-T-041-target-aware-delta-from-xt-snapshot.md`

# Summary

- Added snapshot-aware delta logic for:
  - products
  - media
  - documents
- Delta now only queues entries when the XT snapshot proves the target is missing or different.
- Existing internal state tables remain in place as fallback when no valid snapshot run is available.
- Product snapshot comparison was extended so it can cover:
  - top-level product fields
  - category assignment
  - translated product text fields
  - product attributes
  - SEO meta fields

# Open points

- This ticket did not add a standalone category export queue entity; category relevance is currently applied where it affects product comparison.
- Snapshot-aware delta depends on at least one successful `xt_snapshot` run in normal operation. For isolated validation, that requirement was explicitly disabled in the temporary config.

# Validation steps

- Executed syntax checks:
  - `docker compose exec -T php php -l /app/src/Service/ProductDeltaService.php`
  - `docker compose exec -T php php -l /app/src/Service/XtSnapshotService.php`
  - `docker compose exec -T php php -l /app/config/delta.php`
  - `docker compose exec -T php php -l /app/config/xt_snapshot.php`
  - `docker compose exec -T php php -l /app/wela-api/index.php`
  - `docker compose exec -T php php -l /app/run_delta.php`
  - `docker compose exec -T php php -l /app/src/Service/DeltaRunnerService.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
- Applied migration:
  - `curl -s -o /tmp/t041_migrations.out -w '%{http_code}' -X POST http://localhost:8080/status/migrations`
  - verified `013_add_xt_product_snapshot_compare_fields` in `schema_migrations`
- Executed isolated delta integration validation with temporary tables:
  - created temporary Stage, Snapshot, Queue, and State tables prefixed with `t041_`
  - inserted:
    - `3` product Stage rows
    - `2` media Stage rows
    - `2` document Stage rows
    - matching/mismatching XT snapshot rows
  - executed `DeltaRunnerService` twice against only those temporary tables
- Queried validation results:
  - queue contents by entity
  - duplicate active queue groups
  - updated stage hash columns
- Executed web safety check:
  - `/pipeline` returned HTTP `200`

- Observed isolated integration result:
  - first run:
    - unchanged snapshot matches were not queued
    - changed snapshot mismatches were queued
    - totals:
      - `product update = 2`
      - `media update = 1`
      - `document update = 1`
  - queued rows:
    - `product / 91002 / update`
    - `product / 91003 / update`
    - `media / t041-media-91002 / update`
    - `document / 92002 / update`
  - second run:
    - `queue_created = 0`
    - `deduplicated = 4`
    - duplicate active queue groups = `0`

# Recommended next step

Run a real `xt_snapshot` followed by a real `run_delta.php` against the current environment and compare pending queue volume before/after to confirm the expected reduction in unnecessary exports on live data.
