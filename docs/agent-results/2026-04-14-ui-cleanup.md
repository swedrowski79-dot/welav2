# Task

Improve UX by moving monitoring reset actions from `/pipeline` to the logs area, while keeping the existing reset logic unchanged.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/ui-task.md`
- `docs/agent-results/2026-04-14-pipeline-status-and-log-reset.md`
- `src/Web/Controller/LogController.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/logs/index.php`
- `src/Web/View/pipeline/index.php`

# Changed files

- `src/Web/Controller/LogController.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/logs/index.php`
- `src/Web/View/pipeline/index.php`

# Summary

- Moved these actions from `/pipeline` to `/logs`:
  - `Reset Logs`
  - `Reset Errors`
  - `Reset Runs`
- Placed the monitoring reset buttons clearly above the logs table in their own warning-styled panel.
- Kept confirmation dialogs unchanged by reusing the same `confirm()` form pattern.
- Kept backend reset logic unchanged by continuing to post to the existing `/pipeline/reset` handler.
- Updated redirects so log-related reset actions return to `/logs` with success or error messages.
- Cleaned up `/pipeline` so it now keeps only:
  - pipeline controls
  - execution status
  - queue view
  - queue/stage/delta reset actions

# Open points

- Reset execution is still handled by `PipelineController`, which is acceptable for now because the task required placement cleanup rather than backend restructuring.
- No visual changes were made outside the affected logs and pipeline panels.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Web/Controller/LogController.php`
  - `docker compose exec php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec php php -l /app/src/Web/View/logs/index.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - manual browser check on `/logs` and `/pipeline`
  - actual reset action clicks

# Recommended next step

Open `/logs` and `/pipeline` in the browser and verify that monitoring resets are only shown on `/logs`, that the confirmation dialogs still appear, and that successful resets redirect back to `/logs`.
