# Task

Improve the admin pipeline UI with reset actions for monitoring data and a visible execution status area on `/pipeline`.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/ui-task.md`
- `docs/agent-results/2026-04-14-admin-pipeline.md`
- `docs/agent-results/2026-04-14-reset-fix.md`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/Core/Html.php`

# Changed files

- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/View/pipeline/index.php`

# Summary

- Added new safe reset actions for:
  - `sync_logs`
  - `sync_errors`
  - `sync_runs` history
- Kept the existing confirmation dialog behavior and warning-panel style.
- Kept reset logging where possible:
  - log reset deletes the old log rows and then writes a fresh audit entry into `sync_logs`
  - error and run resets also write audit entries into `sync_logs`
- Added a new visible status area to `/pipeline` showing:
  - whether a run is currently active (`running` or `idle`)
  - current or last pipeline step
  - last start time
  - last finish time
  - latest error message and timestamp if available
- Reused the existing monitoring tables and extended `MonitoringRepository` instead of adding a separate status mechanism.

# Open points

- `Reset Runs` removes `sync_runs` history only; logs and errors remain separate reset actions.
- The status block uses the latest row in `sync_runs` and `sync_errors`, so its accuracy depends on the existing CLI monitoring writes remaining consistent.
- No extra browser polling was added; the page reflects the state at load time.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Web/Repository/MonitoringRepository.php`
  - `docker compose exec php php -l /app/src/Web/Repository/PipelineAdminRepository.php`
  - `docker compose exec php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - manual browser check on `/pipeline`
  - actual reset actions against a live dataset
  - live pipeline run to verify status transitions visually

# Recommended next step

Open `/pipeline`, verify the new reset buttons and status card, then start one pipeline step and refresh the page to confirm `running`, timestamps, and last error rendering behave as expected.
