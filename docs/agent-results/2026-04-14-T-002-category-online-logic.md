# Task

Implement Ticket `T-002` by fixing inverted AFS category online semantics without modifying the product pipeline.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/ticket-task.md`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-002-category-online-logic.md`
- `docs/agent-results/2026-04-14-category-source-name-fix.md`
- `config/sources.php`
- `config/normalize.php`
- `src/Service/Normalizer.php`
- `src/Importer/AfsImporter.php`
- `config/merge.php`
- `database.sql`

# Changed files

- `config/sources.php`
- `config/normalize.php`
- `src/Service/Normalizer.php`
- `docs/tickets/open/T-002-category-online-logic.md`
- `docs/agent-results/2026-04-14-T-002-category-online-logic.md`

# Summary

- AFS category import now filters by `Internet = 0` at the source config level.
- Category normalization now converts the imported AFS category state to internal `online_flag = 1`.
- `stage_categories` continues to reuse `raw_afs_categories.online_flag`, but that value is now already normalized and no longer tied to raw AFS semantics.
- Product import, product delta logic, and export queue behavior were not changed.

# Open points

- The category online normalization is intentionally narrow and only applies to `afs.categories`.
- No end-to-end import/merge run was executed in this task step.

# Validation steps

- Planned:
  - `docker compose exec php php -l /app/config/sources.php`
  - `docker compose exec php php -l /app/config/normalize.php`
  - `docker compose exec php php -l /app/src/Service/Normalizer.php`
  - `docker compose exec php php run_import_all.php`
  - `docker compose exec php php run_merge.php`

# Recommended next step

Run `import` and `merge`, then verify that imported AFS categories come only from `Internet = 0` rows and appear in `raw_afs_categories` / `stage_categories` with `online_flag = 1`.
