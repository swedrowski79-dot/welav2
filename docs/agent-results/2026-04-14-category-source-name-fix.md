# Task

Adjust the default configured AFS category source name from `Warengruppen` to `Warengruppe`, while keeping the source configurable.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/backend-task.md`
- `docs/agent-results/2026-04-14-import-fix-and-async-pipeline.md`
- `config/sources.php`

# Changed files

- `config/sources.php`

# Summary

- Updated the default value of `AFS_CATEGORIES_TABLE` from `Warengruppen` to `Warengruppe`.
- Kept the category source configurable through the existing environment-based config.
- Did not change the async pipeline launch logic or any unrelated import behavior.

# Open points

- If the target environment uses a different category table or view name, it can still override the default with `AFS_CATEGORIES_TABLE`.

# Validation steps

- Recommended:
  - `docker compose exec php php -l /app/config/sources.php`
  - `docker compose exec php php -r '$config = require "/app/config/sources.php"; echo $config["sources"]["afs"]["entities"]["categories"]["table"], PHP_EOL;'`

# Recommended next step

Run the AFS import once and confirm that the configured category source now resolves to `dbo.Warengruppe` unless an explicit override is set.
