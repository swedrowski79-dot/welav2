# Task

Implement Ticket `T-018` by adding retry policy and clearer error handling to export queue processing.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-018-export-worker-retry-and-error-handling.md`
- `docs/tickets/open/T-016-delta-batching-and-deduplication.md`
- `docs/tickets/done/T-003-export-worker-and-confirmed-state.md`
- `docs/tickets/done/T-017-export-worker-batch-claiming.md`
- `docs/agent-results/2026-04-14-T-017-export-worker-batch-claiming.md`
- `docs/agent-results/2026-04-14-delta-products.md`
- `docs/agent-results/2026-04-14-delta-products-fix.md`
- `config/delta.php`
- `database.sql`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`

# Changed files

- `config/delta.php`
- `database.sql`
- `migrations/005_add_export_queue_retry_fields.sql`
- `src/Service/ExportQueueWorker.php`
- `docs/tickets/done/T-018-export-worker-retry-and-error-handling.md`
- `docs/agent-results/2026-04-14-T-018-export-worker-retry-and-error-handling.md`

# Summary

- Added configurable retry controls with `worker_max_attempts` and `worker_retry_delay_seconds`.
- Extended `export_queue` so queue rows now persist:
  - attempt counter
  - next available retry time
  - processed timestamp
  - last error message
- Reworked `ExportQueueWorker` to classify failures into:
  - permanent queue/payload errors
  - retryable runtime failures
  - exhausted retry failures
- Retryable failures now move back to `pending` with delayed reprocessing, while permanent or exhausted failures stay on `error` with richer monitoring context.
- Successful rows still update `product_export_state` only after a committed success path.

# Open points

- No end-to-end worker execution against a queue with real retry cases was run.
- Queue UI does not yet surface the new retry metadata fields; this ticket kept the change to worker behavior and monitoring only.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec -T php php -l /app/config/delta.php`
  - `docker compose exec -T php php -l /app/run_export_queue.php`
- Not executed:
  - migration execution
  - `docker compose exec -T php php /app/run_export_queue.php`

# Recommended next step

Apply migrations, create one retryable and one permanent failing queue entry, then run the worker to verify `pending -> processing -> pending` with delay and `pending -> processing -> error` for permanent failures.
