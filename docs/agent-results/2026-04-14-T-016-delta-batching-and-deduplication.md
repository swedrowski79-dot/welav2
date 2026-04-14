# Task

Implement Ticket `T-016` by improving delta queue batching and duplicate queue prevention.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-016-delta-batching-and-deduplication.md`
- `docs/tickets/done/T-003-export-worker-and-confirmed-state.md`
- `docs/tickets/done/T-017-export-worker-batch-claiming.md`
- `docs/tickets/done/T-018-export-worker-retry-and-error-handling.md`
- `docs/agent-results/2026-04-14-delta-products.md`
- `docs/agent-results/2026-04-14-delta-products-fix.md`
- `docs/agent-results/2026-04-14-T-018-export-worker-retry-and-error-handling.md`
- `config/delta.php`
- `run_delta.php`
- `run_expand.php`
- `src/Service/ProductDeltaService.php`

# Changed files

- `config/delta.php`
- `src/Service/ProductDeltaService.php`
- `docs/tickets/done/T-016-delta-batching-and-deduplication.md`
- `docs/agent-results/2026-04-14-T-016-delta-batching-and-deduplication.md`

# Summary

- Added `queue_insert_batch_size` to the product export queue config.
- Reworked `ProductDeltaService` to preload existing queue signatures for `pending` and `processing` rows once per delta run.
- Removed the previous per-row duplicate lookup query pattern and replaced it with in-memory signature checks.
- New queue rows are now buffered and inserted in chunks, with single-row fallback if a batch insert fails.
- Deduplicated rows are now counted explicitly, which makes repeated delta runs more predictable without changing confirmed export state handling.

# Open points

- No end-to-end delta run against a populated stage dataset was executed.
- The queue deduplication is now stronger within and across active pending/processing rows, but there is still no database-level uniqueness constraint for payload signatures.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Service/ProductDeltaService.php`
  - `docker compose exec -T php php -l /app/config/delta.php`
  - `docker compose exec -T php php -l /app/run_delta.php`
  - `docker compose exec -T php php -l /app/run_expand.php`
- Not executed:
  - `docker compose exec -T php php /app/run_delta.php`
  - `docker compose exec -T php php /app/run_expand.php`

# Recommended next step

Run delta repeatedly against an unchanged dataset and confirm that the queue stays stable while `deduplicated` increases instead of creating new pending rows.
