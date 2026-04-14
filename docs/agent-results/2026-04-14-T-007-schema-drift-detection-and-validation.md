# Task

Implement Ticket `T-007` by detecting missing stage/delta schema elements and showing a visible warning in the admin UI.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-007-Schema Drift Detection and Validation.md`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Repository/StageConnection.php`
- `database.sql`

# Changed files

- `src/Web/Repository/SchemaHealthRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `docs/tickets/done/T-007-Schema Drift Detection and Validation.md`
- `docs/agent-results/2026-04-14-T-007-schema-drift-detection-and-validation.md`

# Summary

- Added a focused schema validator for the required delta-related tables and columns.
- `/pipeline` now shows a clear warning banner when required tables or columns are missing.
- The warning is visible without blocking the admin UI, so schema drift no longer fails silently.

# Open points

- The warning is currently shown on `/pipeline`, which is the admin area most directly affected by stage/delta schema drift.
- No live database with intentionally missing columns was used for an end-to-end UI verification.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Web/Repository/SchemaHealthRepository.php`
  - `docker compose exec php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - manual browser validation with a deliberately incomplete schema

# Recommended next step

Open `/pipeline` against a database with a missing required column or table and confirm that the warning banner lists the exact schema issue.
