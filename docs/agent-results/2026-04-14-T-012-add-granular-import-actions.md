# Task

Implement Ticket `T-012` by adding granular import actions to the admin UI without duplicating importer logic.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-012 — Add granular import actions.md`
- `docs/agent-results/2026-04-14-T-010-add-import-action-to-pipeline-admin-ui.md`
- `docs/agent-results/2026-04-14-import-fix-and-async-pipeline.md`
- `run_import_all.php`
- `run_import.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Importer/AfsImporter.php`
- `src/Importer/ExtraImporter.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/sync-runs/index.php`

# Changed files

- `src/Service/ImportWorkflow.php`
- `run_import_all.php`
- `run_import.php`
- `run_import_products.php`
- `run_import_categories.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/sync-runs/index.php`
- `docs/tickets/done/T-012 — Add granular import actions.md`
- `docs/agent-results/2026-04-14-T-012-add-granular-import-actions.md`

# Summary

- Added `Run Product Import` and `Run Category Import` actions to the admin UI.
- Reused the existing importer logic through a shared `ImportWorkflow` service instead of duplicating CLI runner code.
- Added dedicated runner scripts for product and category imports while keeping full import unchanged.
- Did not add a document import action because the repository currently has no matching document import pipeline to execute.

# Open points

- No manual browser test was executed for the new buttons.
- No live runtime measurement was taken; the expectation of faster execution is based on importing fewer datasets per run.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Service/ImportWorkflow.php`
  - `docker compose exec php php -l /app/run_import_all.php`
  - `docker compose exec php php -l /app/run_import.php`
  - `docker compose exec php php -l /app/run_import_products.php`
  - `docker compose exec php php -l /app/run_import_categories.php`
  - `docker compose exec php php -l /app/src/Web/Repository/SyncLauncher.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
  - `docker compose exec php php -l /app/src/Web/View/sync-runs/index.php`
- Not executed:
  - manual click test for the new import actions
  - real product/category import runs

# Recommended next step

Open the admin UI, trigger `Run Product Import` and `Run Category Import`, and verify that each run updates only the corresponding raw import tables and appears with its own run type in the status/history views.
