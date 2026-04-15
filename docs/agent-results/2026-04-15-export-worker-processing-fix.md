# Task

Fix export worker processing after queue entries have been claimed.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `agents/codex/ticket-task.md`
- `docs/agent-results/2026-04-14-T-017-export-worker-batch-claiming.md`
- `docs/agent-results/2026-04-14-T-018-export-worker-retry-and-error-handling.md`
- `docs/agent-results/2026-04-15-export-worker-claim-fix.md`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`

# Changed files

- `src/Service/ExportQueueWorker.php`
- `docs/agent-results/2026-04-15-export-worker-processing-fix.md`

# Summary

- Moved per-entry processing into a dedicated `processEntry()` method.
- Kept claim logic unchanged.
- Successful entries now complete in a clear order:
  - validate entry
  - update confirmed export state
  - mark queue row as `done`
  - set `processed_at` through the existing `markDone()` update
- Added per-entry success logging after a committed `done` transition.
- Wrapped error handling in `handleFailureSafely()` so one broken failure path does not stop the remaining claimed entries from being processed.

# Open points

- No live worker run against a populated queue was executed.
- This change keeps the existing retry and permanent-error schema behavior unchanged.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec -T php php -l /app/run_export_queue.php`
- Not executed:
  - `docker compose exec -T php php /app/run_export_queue.php`

# Recommended next step

Run the export worker once with a mix of valid and invalid claimed entries and confirm that successful rows move to `done` with `processed_at` set while failing rows are handled individually without stopping the batch.
