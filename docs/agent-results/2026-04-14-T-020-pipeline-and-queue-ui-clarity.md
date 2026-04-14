# Task

Implement Ticket `T-020` by improving pipeline and export queue UI clarity for daily admin use.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-020-pipeline-and-queue-ui-clarity.md`
- recent pipeline progress, monitoring, and consistency result files
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `src/Web/Repository/PipelineAdminRepository.php`

# Changed files

- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `docs/tickets/done/T-020-pipeline-and-queue-ui-clarity.md`
- `docs/agent-results/2026-04-14-T-020-pipeline-and-queue-ui-clarity.md`

# Summary

- Added a clearer operator-oriented separation between pipeline controls, run status, and queue handling.
- Extended queue summaries and filtering to include `processing` rows.
- Reworked queue table readability by surfacing:
  - action badges
  - retry counts
  - created/available/claimed/processed timestamps
  - collapsible payload and error details
- Improved the export state page with summary cards and shorter hash presentation for faster scanning.
- Kept all existing endpoints and backend behavior unchanged.

# Open points

- No manual browser review was executed for final readability on desktop and mobile.
- The queue table is clearer now, but a future ticket could still add dedicated row detail views if operators need even more space for payload inspection.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Web/Repository/PipelineAdminRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/View/pipeline/index.php`
  - `docker compose exec -T php php -l /app/src/Web/View/pipeline/state.php`
- Not executed:
  - manual browser check on `/pipeline` and `/pipeline/state`

# Recommended next step

Open `/pipeline` and `/pipeline/state` in the browser and verify that operators can now distinguish queue retries, active processing rows, and export-state scans more quickly without opening raw JSON immediately.
