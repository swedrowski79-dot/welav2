# Task

Implement Ticket `T-017` by adding bounded batch claiming to the export worker.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-017-export-worker-batch-claiming.md`
- `docs/tickets/open/T-018-export-worker-retry-and-error-handling.md`
- `docs/tickets/open/T-016-delta-batching-and-deduplication.md`
- `docs/tickets/done/T-003-export-worker-and-confirmed-state.md`
- `docs/agent-results/2026-04-14-delta-products.md`
- `docs/agent-results/2026-04-14-delta-products-fix.md`
- `config/delta.php`
- `database.sql`
- `run_export_queue.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Service/ExportQueueWorker.php`

# Changed files

- `config/delta.php`
- `database.sql`
- `migrations/004_add_export_queue_claim_fields.sql`
- `src/Service/ExportQueueWorker.php`
- `docs/tickets/done/T-017-export-worker-batch-claiming.md`
- `docs/agent-results/2026-04-14-T-017-export-worker-batch-claiming.md`

# Summary

- Added a configurable export worker batch size through `config/delta.php`.
- Extended `export_queue` with claim metadata fields so worker runs can reserve a bounded queue slice before processing.
- Reworked `ExportQueueWorker` so it:
  - claims pending rows in one bounded batch
  - processes only rows matching its own claim token
  - verifies claim ownership before marking rows `done` or `error`
- Kept confirmed export state updates unchanged: they still happen only after successful processing.

# Open points

- No end-to-end worker run against a populated queue was executed.
- Claimed rows now use transient status `processing`, so any later UI improvement for queue visibility can expose that status if needed.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec -T php php -l /app/config/delta.php`
  - `docker compose exec -T php php -l /app/run_export_queue.php`
- Not executed:
  - migration execution
  - `docker compose exec -T php php /app/run_export_queue.php`

# Recommended next step

Apply migrations, populate a small test queue, and run the export worker once to verify that only the claimed batch moves through `processing` to `done` or `error`.
