# Task

Fix the AFS category import source and make web-triggered pipeline actions return immediately instead of blocking the browser request.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/backend-task.md`
- `docs/agent-results/2026-04-14-admin-pipeline.md`
- `docs/agent-results/2026-04-14-pipeline-status-and-log-reset.md`
- `README.md`
- `config/sources.php`
- `src/Importer/AfsImporter.php`
- `run_import_all.php`
- `run_import.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Monitoring/SyncMonitor.php`

# Changed files

- `config/sources.php`
- `src/Importer/AfsImporter.php`
- `src/Web/Repository/SyncLauncher.php`

# Summary

- Made AFS source tables configurable and schema-aware through `config/sources.php`:
  - `AFS_DB_SCHEMA`
  - `AFS_ARTICLES_TABLE`
  - `AFS_CATEGORIES_TABLE`
  - `AFS_DOCUMENTS_TABLE`
- Default category source is now rendered as `dbo.Warengruppen` instead of an unqualified `Warengruppen`.
- Reworked `AfsImporter` so it no longer uses hardcoded `SELECT * FROM {table}`:
  - builds the query from configured `columns`
  - applies configured `where` filters
  - safely quotes schema/table segments and column identifiers for MSSQL
- Fixed web-triggered async execution in `SyncLauncher` by wrapping the full command chain in a subshell before backgrounding it.
  - This specifically avoids `full_pipeline` blocking the browser when the command contains `&&`.
- Existing monitoring/status flow remains intact, so `/pipeline` can keep showing `running / idle`, current step, and last error via the existing monitoring tables.

# Open points

- I did not execute a real AFS import against the live MSSQL source, so the exact production table/view name still depends on the environment configuration.
- If the actual categories source is not `Warengruppen`, it can now be overridden cleanly with `AFS_CATEGORIES_TABLE` instead of code changes.
- `run_import.php` still uses an outdated constructor pattern, but it was outside the requested fix and was left untouched.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/config/sources.php`
  - `docker compose exec php php -l /app/src/Importer/AfsImporter.php`
  - `docker compose exec php php -l /app/src/Web/Repository/SyncLauncher.php`
  - `docker compose exec php php -r '$config = require "/app/config/sources.php"; echo $config["sources"]["afs"]["entities"]["categories"]["table"], PHP_EOL;'`
- Observed:
  - configured AFS categories table resolves to `dbo.Warengruppen`
- Not executed:
  - `docker compose exec php php run_import_all.php`
  - web-triggered manual full pipeline test in browser

# Recommended next step

Set `AFS_CATEGORIES_TABLE` if the real source is a different table or view, then run `docker compose exec php php run_import_all.php` once and verify that `/pipeline` immediately returns after starting `Run Full Pipeline` while the status area switches to `running`.
