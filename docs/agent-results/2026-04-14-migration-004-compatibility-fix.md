# Task

Fix migration `004_add_export_queue_claim_fields.sql` for MySQL compatibility by removing unsupported `IF NOT EXISTS` usage from `ALTER TABLE ... ADD COLUMN` and handling safe existence checks in migration logic.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `migrations/004_add_export_queue_claim_fields.sql`
- `src/Web/Repository/MigrationRepository.php`
- `docs/agent-results/2026-04-14-migration-sql-compatibility-fix.md`

# Changed files

- `migrations/004_add_export_queue_claim_fields.sql`
- `src/Web/Repository/MigrationRepository.php`
- `docs/agent-results/2026-04-14-migration-004-compatibility-fix.md`

# Summary

- Replaced the unsupported MySQL syntax in `004_add_export_queue_claim_fields.sql` with plain `ADD COLUMN` clauses.
- Extended the migration runner with a narrow skip precheck for `004_add_export_queue_claim_fields` so it is recorded safely if both `claim_token` and `claimed_at` already exist.
- Kept the fix limited to migration-related files.

# Open points

- The skip logic is intentionally narrow and only targets the known failing migration `004`.
- No full migration run was executed against a live database in this task.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
- Not executed:
  - actual migration execution through the web UI or runner

# Recommended next step

Run migrations once against the current MySQL database and verify that `004_add_export_queue_claim_fields.sql` either applies cleanly on older schemas or is recorded without error when both claim fields already exist.
