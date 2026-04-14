# Task

Implement Ticket `T-019` by improving pipeline progress indicators and lightweight refresh behavior on `/pipeline`.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-019-pipeline-progress-indicators-and-refresh.md`
- recent pipeline, monitoring, and consistency result files
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/View/pipeline/index.php`

# Changed files

- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `docs/tickets/done/T-019-pipeline-progress-indicators-and-refresh.md`
- `docs/agent-results/2026-04-14-T-019-pipeline-progress-indicators-and-refresh.md`

# Summary

- Added lightweight auto-refresh to `/pipeline` while a run is active, without introducing any frontend dependency.
- Improved the status area with:
  - progress headline
  - latest status message
  - visible run duration
  - last update timestamp
  - run ID and log count
- Added a separate progress timeline showing the latest 5 progress-relevant log entries for the active or latest run.
- Kept the change fully based on the existing monitoring tables.

# Open points

- No manual browser check was executed to review the refresh behavior and readability during a real long-running job.
- The auto-refresh uses a simple page reload every 10 seconds while `running`, which is intentionally minimal.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Web/Repository/MonitoringRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec -T php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - manual browser check on `/pipeline`
  - live long-running pipeline run

# Recommended next step

Start one pipeline job from `/pipeline` and confirm that the new summary, timeline, and auto-refresh make the current step easier to follow without manual reloading.
