# Task

Implement Ticket `T-001` to improve pipeline status visibility in the admin UI.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/ticket-task.md`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-001-pipeline-status-detail.md`
- `docs/agent-results/2026-04-14-pipeline-status-and-log-reset.md`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/View/pipeline/index.php`

# Changed files

- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/View/pipeline/index.php`
- `docs/tickets/open/T-001-pipeline-status-detail.md`

# Summary

- Reused `MonitoringRepository` and the existing `sync_runs`, `sync_logs`, and `sync_errors` tables.
- Kept the existing status block on `/pipeline` for:
  - `running / idle`
  - current or last step
  - last start time
  - last finish time
  - last error message
- Added recent log visibility directly on `/pipeline`:
  - last 10 log lines of the active run, or the most recent run if nothing is running
  - direct link to the run detail page
- Kept the existing page structure and styling intact.
- Updated the ticket status to `done` and checked all acceptance criteria.

# Open points

- No polling or auto-refresh was added; the page reflects the status at load time.
- Manual browser verification is still recommended for the final UX check.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Web/Repository/MonitoringRepository.php`
  - `docker compose exec php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - manual browser test on `/pipeline`
  - live long-running pipeline execution

# Commit hash

- `PENDING`

# Recommended next step

Open `/pipeline`, start a pipeline action, refresh once or twice, and verify that the active step, latest error state, and recent log entries are understandable during manual testing.
