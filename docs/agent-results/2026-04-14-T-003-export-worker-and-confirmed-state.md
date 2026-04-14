# Task

Implement Ticket `T-003` by adding an export worker that processes `export_queue` and updates confirmed export state only after success.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-003-export-worker-and-confirmed-state.md`
- `docs/agent-results/2026-04-14-delta-products-fix.md`
- `src/Service/ProductDeltaService.php`
- `config/delta.php`
- `database.sql`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/sync-runs/index.php`
- `run_delta.php`

# Changed files

- `src/Service/ExportQueueWorker.php`
- `run_export_queue.php`
- `src/Service/ProductDeltaService.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/sync-runs/index.php`
- `docs/tickets/done/T-003-export-worker-and-confirmed-state.md`
- `docs/agent-results/2026-04-14-T-003-export-worker-and-confirmed-state.md`

# Summary

- Added a queue worker that processes pending export entries for products.
- Successful queue processing now marks entries as `done` and updates `product_export_state.last_exported_hash`.
- Failed queue processing marks entries as `error` and logs the problem through the existing monitoring tables.
- `ProductDeltaService` now keeps `last_seen_at` current without prematurely confirming the export hash.
- Added a launcher/UI entry for the export worker to support manual testing.

# Open points

- The worker is intentionally a safe local confirmation worker; it does not write to XT or call external APIs.
- No live queue processing run was executed in this task step.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec php php -l /app/run_export_queue.php`
  - `docker compose exec php php -l /app/src/Service/ProductDeltaService.php`
  - `docker compose exec php php -l /app/src/Web/Repository/SyncLauncher.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
  - `docker compose exec php php -l /app/src/Web/View/sync-runs/index.php`
- Not executed:
  - `docker compose exec php php /app/run_delta.php`
  - `docker compose exec php php /app/run_export_queue.php`

# Recommended next step

Run delta once, then run the export worker and verify that pending queue rows move to `done` and the confirmed hash in `product_export_state` changes only after the worker run.
