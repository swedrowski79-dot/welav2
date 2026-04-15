# Task

Fix `ExportQueueWorker::claimPendingEntries()` so pending queue entries with `available_at = NULL` are selected and actually claimed.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `agents/codex/ticket-task.md`
- `docs/agent-results/2026-04-14-T-017-export-worker-batch-claiming.md`
- `docs/agent-results/2026-04-14-T-018-export-worker-retry-and-error-handling.md`
- `config/delta.php`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`

# Changed files

- `src/Service/ExportQueueWorker.php`
- `docs/agent-results/2026-04-15-export-worker-claim-fix.md`

# Summary

- Fixed pending claim selection to include rows where `available_at` is `NULL` as well as rows due at or before `NOW()`.
- Restricted claim selection and claim update to rows with:
  - `status = 'pending'`
  - `claim_token IS NULL`
  - matching `entity_type`
  - `(available_at IS NULL OR available_at <= NOW())`
- Added claim debug logging for:
  - selected row count
  - updated row count
- Added explicit row-count validation so the worker fails fast if the claim update does not actually reserve the selected rows.

# Open points

- No live queue run was executed against a populated database.
- The worker still marks successfully claimed rows as `processing`, which remains consistent with the existing queue flow.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec -T php php -l /app/run_export_queue.php`
- Not executed:
  - `docker compose exec -T php php /app/run_export_queue.php`

# Recommended next step

Run the export worker once against a queue containing `pending` rows with `available_at = NULL` and confirm that the rows move to `processing` with populated `claim_token`, `claimed_at`, and incremented `attempt_count`.
