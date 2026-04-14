# Task

Fix the migration SQL syntax for MySQL compatibility by removing unsupported `IF NOT EXISTS` usage from `ALTER TABLE ... ADD COLUMN` and moving the existence handling into migration logic.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `migrations/001_add_stage_products_hash.sql`
- `src/Web/Repository/MigrationRepository.php`

# Changed files

- `migrations/001_add_stage_products_hash.sql`
- `src/Web/Repository/MigrationRepository.php`
- `docs/agent-results/2026-04-14-migration-sql-compatibility-fix.md`

# Summary

- Replaced the unsupported MySQL syntax in `001_add_stage_products_hash.sql` with a plain `ADD COLUMN`.
- Added a migration-runner precheck so migration `001_add_stage_products_hash` is skipped cleanly when `stage_products.hash` already exists but the migration has not yet been recorded.
- Kept the change limited to migration-related files.

# Open points

- The skip logic is intentionally narrow and only targets the known failing migration rather than introducing broad SQL parsing.
- No full migration run was executed against a live database in this task.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
- Not executed:
  - `docker compose exec -T php php /app/run_migrations...` equivalent
  - manual migration run through the admin UI

# Recommended next step

Run the migration action once against the current MySQL database and verify that `001_add_stage_products_hash.sql` either applies successfully on fresh schemas or is recorded without error when the `hash` column already exists.
