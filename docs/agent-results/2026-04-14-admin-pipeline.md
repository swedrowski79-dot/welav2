# Task

Extend the admin UI to control the pipeline, monitor `export_queue` and `product_export_state`, and provide safe reset actions.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/ui-task.md`
- `docs/agent-results/2026-04-14-delta-products-fix.md`
- `README.md`
- `public/index.php`
- `config/admin.php`
- `src/Web/bootstrap.php`
- `src/Web/Core/Controller.php`
- `src/Web/Core/Html.php`
- `src/Web/Core/Request.php`
- `src/Web/Core/Response.php`
- `src/Web/Core/Router.php`
- `src/Web/Core/View.php`
- `src/Web/Controller/DashboardController.php`
- `src/Web/Controller/StageBrowserController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/Repository/DashboardRepository.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Repository/StageBrowserRepository.php`
- `src/Web/Repository/StageConnection.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/dashboard/index.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/stage-browser/index.php`
- `src/Web/View/stage-browser/show.php`
- `src/Web/View/status/index.php`
- `src/Web/View/sync-runs/index.php`

# Changed files

- `public/index.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `src/Web/View/sync-runs/index.php`

# Summary

- Added a new admin page at `/pipeline` with:
  - buttons for `Run Merge`, `Run Expand`, `Run Delta`, and `Run Full Pipeline`
  - queue summary cards
  - reset actions with warning text and browser confirmation dialog
  - filtered `export_queue` list for `entity_type`, `status`, and `action`
- Added a second view at `/pipeline/state` for `product_export_state` with `product_id`, `last_exported_hash`, and `last_seen_at`.
- Extended `SyncLauncher` so the web UI can start `delta` and `full_pipeline` using the existing launcher structure.
- Extended the existing `sync-runs` start area with `Delta starten` and `Full Pipeline`.
- Logged reset actions into `sync_logs` using the existing monitoring tables.

# UI description

- Navigation now includes a `Pipeline` entry.
- The `Pipeline & Export Queue` page is the operational screen for admins:
  - top section for pipeline start actions
  - warning section for resets
  - table for queue monitoring with JSON payload display
- The `Produkt Export State` page is a simple read-only table with optional search.
- Existing layout and Bootstrap-based styling were preserved.

# Open points

- Reset actions currently log to `sync_logs` with `sync_run_id = NULL`, which is consistent with admin-triggered actions but separate from pipeline run records.
- `Run Full Pipeline` starts `import -> merge -> expand`; since `expand` already triggers delta in the current backend, no extra delta call is chained there.
- No dedicated detail page for queue entries was added because the payload JSON is already visible in the list view.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/public/index.php`
  - `docker compose exec php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec php php -l /app/src/Web/Repository/PipelineAdminRepository.php`
  - `docker compose exec php php -l /app/src/Web/Repository/SyncLauncher.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/state.php`
  - `docker compose exec php php -l /app/src/Web/View/sync-runs/index.php`
- Not executed:
  - manual browser check at `http://localhost:8080`
  - actual start/reset actions against a running database

# Recommended next step

Open the admin UI, verify `/pipeline` and `/pipeline/state`, then test one safe start action and one reset action against a disposable local dataset to confirm logs, redirects, and table updates.
