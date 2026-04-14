# Task

Implement Ticket `T-004` by cleaning up monitoring navigation and clarifying the separation between logs, errors, and run history.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-004-monitoring-navigation-cleanup.md`
- `src/Web/View/layouts/app.php`
- `src/Web/View/logs/index.php`
- `src/Web/View/errors/index.php`
- `src/Web/View/sync-runs/index.php`
- `src/Web/Controller/LogController.php`
- `src/Web/Controller/ErrorController.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/Controller/PipelineController.php`

# Changed files

- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/ErrorController.php`
- `src/Web/Controller/LogController.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/logs/index.php`
- `src/Web/View/errors/index.php`
- `src/Web/View/sync-runs/index.php`
- `docs/tickets/done/T-004-monitoring-navigation-cleanup.md`
- `docs/agent-results/2026-04-14-T-004-monitoring-navigation-cleanup.md`

# Summary

- Clarified monitoring navigation by labeling runs, logs, and errors explicitly as monitoring sections.
- Added simple explanatory panels so users can distinguish between logs and error records quickly.
- Placed reset actions where they belong: logs reset on logs, errors reset on errors, runs reset on run history.
- Existing functionality and reset handlers remain unchanged apart from more logical redirects and placement.

# Open points

- No manual browser walkthrough was executed to review the wording and flow in the live UI.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec php php -l /app/src/Web/Controller/ErrorController.php`
  - `docker compose exec php php -l /app/src/Web/Controller/LogController.php`
  - `docker compose exec php php -l /app/src/Web/Controller/SyncRunController.php`
  - `docker compose exec php php -l /app/src/Web/View/layouts/app.php`
  - `docker compose exec php php -l /app/src/Web/View/logs/index.php`
  - `docker compose exec php php -l /app/src/Web/View/errors/index.php`
  - `docker compose exec php php -l /app/src/Web/View/sync-runs/index.php`
- Not executed:
  - manual browser navigation test across the monitoring pages

# Recommended next step

Open the monitoring pages in the browser and verify that users can clearly distinguish logs, errors, and run history without relying on prior project knowledge.
