# Task

Implement Ticket `T-008` by introducing a migration system with versioned SQL files and admin execution from `/pipeline`.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-008- Migration System and Admin Execution.md`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `public/index.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/StageConnection.php`
- `database.sql`

# Changed files

- `src/Web/Repository/MigrationRepository.php`
- `migrations/001_add_stage_products_hash.sql`
- `migrations/002_create_export_queue.sql`
- `migrations/003_create_product_export_state.sql`
- `src/Web/Controller/PipelineController.php`
- `public/index.php`
- `src/Web/View/pipeline/index.php`
- `docs/tickets/done/T-008- Migration System and Admin Execution.md`
- `docs/agent-results/2026-04-14-T-008-migration-system-and-admin-execution.md`

# Summary

- Added a migration runner that discovers versioned SQL files, executes only pending migrations, and records them in `schema_migrations`.
- Added a `Run Migrations` action to `/pipeline` with success feedback and existing error handling via redirect.
- Added incremental migration files for `stage_products.hash`, `export_queue`, and `product_export_state`.
- Migration runs are logged to `sync_logs`, and migration failures are also written to `sync_errors` where available.

# Open points

- The migration SQL assumes MySQL support for the used `IF NOT EXISTS` syntax in the configured environment.
- No live migration run against a real database was executed in this task step.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Web/Repository/MigrationRepository.php`
  - `docker compose exec php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec php php -l /app/public/index.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - manual click test for `Run Migrations`
  - real migration execution against the database

# Recommended next step

Open `/pipeline`, run migrations once, then confirm that `schema_migrations` contains the executed versions and the schema warning disappears when the database is up to date.
