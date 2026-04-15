# Task

Implement `T-038` by making export queue worker failures and XT export issues visible in the web interface.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `README.md`
- `database.sql`
- `docs/tickets/open/T-038 - Diagnose export queue worker failures and make XT export errors visible.md`
- `docs/agent-results/2026-04-15-T-037-queue-and-delta-visibility.md`
- `docs/agent-results/2026-04-15-T-031-xt-product-seo-writer.md`
- `src/Service/WelaApiClient.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/ErrorController.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/errors/index.php`
- `src/Web/View/errors/show.php`

# Changed files

- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/ErrorController.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/errors/index.php`
- `docs/tickets/done/T-038-export-worker-failure-visibility.md`
- `docs/agent-results/2026-04-15-T-038-export-worker-failure-visibility.md`

# Summary

- Added a focused export-problem visibility path for retrying XT export failures.
- `/pipeline` now shows `XT-/Exportprobleme` with:
  - aggregate queue problem counts
  - recent export worker warnings/errors from `sync_logs`
  - recent queue rows with `last_error`
- `/errors` now shows the same export-specific diagnostic block before the generic terminal `sync_errors` list.
- No export logic or queue processing behavior was changed.

# Open points

- The original `T-038` ticket file in `docs/tickets/open/` contained unrelated `T-034` content. I replaced it with a correct done ticket matching the actual filename/task.
- This ticket makes retrying XT export failures visible, but it does not resolve the underlying `Ungueltige Signatur.` issue currently present in the live worker runs.

# Validation steps

- Executed syntax checks:
  - `docker compose exec -T php php -l /app/src/Web/Repository/MonitoringRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/PipelineAdminRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/ErrorController.php`
- Executed live UI checks:
  - `docker compose exec -T php php -r '$ctx = stream_context_create([...]); file_get_contents("http://127.0.0.1:8080/pipeline", false, $ctx); ...'`
  - `docker compose exec -T php php -r '$ctx = stream_context_create([...]); file_get_contents("http://127.0.0.1:8080/errors", false, $ctx); ...'`
- Executed live data checks:
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT entity_type, status, COUNT(*) FROM export_queue WHERE COALESCE(last_error, '') <> '' GROUP BY entity_type, status ORDER BY entity_type, status;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, sync_run_id, level, message, context_json FROM sync_logs WHERE level IN ('warning','error') AND message LIKE 'Export Queue %' ORDER BY id DESC LIMIT 10;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, entity_type, entity_id, status, attempt_count, last_error, available_at FROM export_queue WHERE COALESCE(last_error, '') <> '' ORDER BY id DESC LIMIT 10;"`
- Observed:
  - `/pipeline` returned `200` and contains `XT-/Exportprobleme`
  - `/errors` returned `200` and contains `XT-/Exportprobleme`
  - `/errors` also visibly contains `last_error`-based export issue text
  - live queue issue counts are currently:
    - `document pending 800`
    - `media pending 800`
    - `product pending 800`
  - live export worker warning logs currently show retry warnings with:
    - `message = Export Queue Eintrag fuer Retry vorgemerkt.`
    - `exception = Ungueltige Signatur.`

# Recommended next step

Use the newly visible export-problem panels to fix the actual live XT export issue behind `Ungueltige Signatur.`, then re-run the export worker and confirm the pending-with-error counts drop.
