# Task

Implement Ticket `T-015` by reducing merge and expand performance hotspots.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-015-merge-expand-performance-hotspots.md`
- relevant recent delta and import result files
- `config/merge.php`
- `config/expand.php`
- `src/Service/MergeService.php`
- `src/Service/ExpandService.php`
- `run_merge.php`
- `run_expand.php`

# Changed files

- `config/merge.php`
- `config/expand.php`
- `src/Service/MergeService.php`
- `src/Service/ExpandService.php`
- `docs/tickets/done/T-015-merge-expand-performance-hotspots.md`
- `docs/agent-results/2026-04-14-T-015-merge-expand-performance-hotspots.md`

# Summary

- Added configurable batch sizes for merge and expand inserts.
- Reworked `MergeService` so match tables are preloaded once into lookup indexes instead of using per-row `SELECT ... LIMIT 1` calls.
- Replaced row-by-row merge inserts with batched inserts.
- Replaced row-by-row expand inserts with buffered batched writes.
- Kept table outputs, runner scripts, and stage-first behavior unchanged.

# Open points

- No end-to-end merge or expand run was executed, so performance improvement is currently based on the changed query/write pattern rather than measured runtime.
- The current merge lookup keeps the first seen matching row per foreign key, which matches the previous `LIMIT 1` behavior but still assumes the source side should be effectively unique.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Service/MergeService.php`
  - `docker compose exec -T php php -l /app/src/Service/ExpandService.php`
  - `docker compose exec -T php php -l /app/config/merge.php`
  - `docker compose exec -T php php -l /app/config/expand.php`
  - `docker compose exec -T php php -l /app/run_merge.php`
  - `docker compose exec -T php php -l /app/run_expand.php`
- Not executed:
  - `docker compose exec -T php php /app/run_merge.php`
  - `docker compose exec -T php php /app/run_expand.php`

# Recommended next step

Run merge and expand once on a representative dataset and compare runtime plus output row counts to confirm the optimized code path stays functionally equivalent.
