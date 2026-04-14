# Task

Fix migration handling and move migration execution to the config/system area.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/ticket-task.md`
- relevant result files in `docs/agent-results/`
- `public/index.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Web/Repository/StatusRepository.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/status/index.php`

# Changed files

- `docs/tickets/done/T-023-migration-handling-and-system-page.md`
- `docs/agent-results/2026-04-14-T-023-migration-handling-and-system-page.md`
- `public/index.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/status/index.php`

# Summary

- Fixed the `/pipeline` crash on older schemas by making queue selects schema-aware and returning `NULL` aliases for optional `export_queue` columns such as `attempt_count`.
- Removed migration execution from `/pipeline` so the page stays focused on operational pipeline controls.
- Added migration execution to `/status` and exposed:
  - pending migrations
  - migration totals/applied counts
  - run migrations action
  - last migration result from existing logs/errors when available
- Kept pipeline actions, queue actions, and existing operational behavior unchanged otherwise.

# Open points

- No live browser check was executed for `/pipeline` or `/status`.
- The last migration result display depends on existing `sync_logs` / `sync_errors` entries and will remain empty until a migration run has been logged.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/PipelineAdminRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/StatusController.php`
  - `docker compose exec -T php php -l /app/public/index.php`
  - `docker compose exec -T php php -l /app/src/Web/View/pipeline/index.php`
  - `docker compose exec -T php php -l /app/src/Web/View/status/index.php`
- Not executed:
  - manual browser check on `/pipeline` and `/status`
  - actual migration run through the web UI

# Recommended next step

Open `/pipeline` and `/status`, confirm that `/pipeline` loads on the current schema, then run migrations from `/status` once and verify that the migration result panel updates afterwards.
