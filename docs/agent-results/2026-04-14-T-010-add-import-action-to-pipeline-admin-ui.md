# Task

Implement Ticket `T-010` by adding a direct import action to the pipeline admin UI.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-010 — Add Import Action to Pipeline Admin UI.md`
- `docs/agent-results/2026-04-14-admin-pipeline.md`
- `docs/agent-results/2026-04-14-pipeline-status-and-log-reset.md`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/sync-runs/index.php`
- `public/index.php`

# Changed files

- `src/Web/View/pipeline/index.php`
- `docs/tickets/done/T-010 — Add Import Action to Pipeline Admin UI.md`
- `docs/agent-results/2026-04-14-T-010-add-import-action-to-pipeline-admin-ui.md`

# Summary

- Added a visible `Run Import` button to the pipeline control area on `/pipeline`.
- Reused the existing `/pipeline/start` endpoint and `SyncLauncher` support for `import_all`.
- Kept the admin layout unchanged and left the existing merge, expand, delta, and full pipeline actions intact.
- No importer, delta, queue, or database logic was changed.

# Open points

- No manual browser click test was executed.
- The status block still updates on page load/refresh; no polling was added.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - open `/pipeline`
  - click `Run Import`
  - verify redirect returns quickly and status shows `import_all` when running or as the latest run

# Recommended next step

Open `/pipeline`, trigger `Run Import`, and verify that the request returns immediately and the status area shows the import run like the other pipeline actions.
