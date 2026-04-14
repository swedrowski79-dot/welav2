# Task

Implement Ticket `T-021` by adding concise monitoring summaries and visible run durations.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-021-monitoring-summary-and-step-durations.md`
- relevant pipeline status and schema result files
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/View/sync-runs/index.php`
- `src/Web/View/sync-runs/show.php`

# Changed files

- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/View/sync-runs/index.php`
- `docs/tickets/done/T-021-monitoring-summary-and-step-durations.md`
- `docs/agent-results/2026-04-14-T-021-monitoring-summary-and-step-durations.md`

# Summary

- Added compact monitoring summary metrics for recent run health directly from `sync_runs`.
- Exposed run duration in seconds in the repository layer using `TIMESTAMPDIFF`.
- Updated `/sync-runs` with:
  - summary cards for active runs, recent successes, recent failures, and average recent duration
  - a new duration column in the run history table
- Kept the monitoring model unchanged and reused only the existing monitoring tables.

# Open points

- No manual browser review was executed for the final summary card layout.
- Duration values depend on the database server time behavior for `UTC_TIMESTAMP()` and the stored timestamps, which is acceptable for the current repository setup.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Web/Repository/MonitoringRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/SyncRunController.php`
  - `docker compose exec -T php php -l /app/src/Web/View/sync-runs/index.php`
- Not executed:
  - manual browser check on `/sync-runs`

# Recommended next step

Open `/sync-runs` and verify that the summary cards and duration column make recent bottlenecks and failure clusters easier to spot at a glance.
