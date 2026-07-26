# Task

Implement `T-035` by aligning the web pipeline flow with the currently implemented backend sync process.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `README.md`
- `database.sql`
- `docs/tickets/open/T-035 - Adjust web interface pipeline flow to support the current sync process beyond export worker.md`
- `docs/agent-results/2026-04-15-T-028-media-document-delta-and-export-queue.md`
- `docs/tickets/done/T-029.md`
- `docs/tickets/done/T-030.md`
- `docs/tickets/done/T-031.md`
- `config/pipeline.php`
- `public/index.php`
- `run_expand.php`
- `run_delta.php`
- `run_export_queue.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Service/XtCompositeWriter.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/sync-runs/index.php`

# Changed files

- `src/Web/Repository/SyncLauncher.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/sync-runs/index.php`
- `docs/tickets/done/T-035-web-pipeline-flow-alignment.md`
- `docs/agent-results/2026-04-15-T-035-web-pipeline-flow-alignment.md`

# Summary

- Fixed the actual orchestration gap in the web layer:
  - `full_pipeline` now launches `import_all -> merge -> expand -> export_queue_worker`
- Left CLI runners unchanged.
- Updated web copy so the UI now matches the implemented backend behavior:
  - `expand` is described as `Expand + Delta`
  - manual `delta` remains available as a standalone rerun
  - the full pipeline is described as ending only after the export worker
- Kept manual step execution intact.

# Open points

- The web UI still reports progress by the currently active or latest concrete run step, not by a separate synthetic `full_pipeline` run row. That remains consistent with the existing monitoring model.
- The export worker run in validation completed with retries logged for some queue items; that behavior was pre-existing and outside the scope of this ticket.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Web/Repository/SyncLauncher.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec -T php php -r '$ctx = stream_context_create(["http" => ["method" => "GET", "ignore_errors" => true]]); $html = file_get_contents("http://127.0.0.1:8080/pipeline", false, $ctx); ...'`
  - `docker compose exec -T php php -r '$ctx = stream_context_create(["http" => ["method" => "GET", "ignore_errors" => true]]); $html = file_get_contents("http://127.0.0.1:8080/sync-runs", false, $ctx); ...'`
  - `docker compose exec -T php php -r 'require "/app/src/Web/bootstrap.php"; (new App\Web\Repository\SyncLauncher())->launch("full_pipeline"); echo "launched", PHP_EOL;'`
  - `docker compose exec -T php php -r 'require "/app/src/Web/bootstrap.php"; (new App\Web\Repository\SyncLauncher())->launch("delta"); echo "launched", PHP_EOL;'`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, run_type, status, started_at, ended_at FROM sync_runs ORDER BY id DESC LIMIT 15;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, message, created_at FROM sync_logs ORDER BY id DESC LIMIT 30;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT status, COUNT(*) FROM export_queue GROUP BY status ORDER BY status;"`
- Observed:
  - `/pipeline` returned `200` and contains updated text for `Expand inklusive Delta`
  - `/sync-runs` returned `200` and contains updated flow wording
  - the web launcher path produced the expected run sequence:
    - `75 import_all success`
    - `76 merge success`
    - `77 expand success`
    - `78 export_queue_worker success`
  - a manual standalone launch still works:
    - `79 delta running` followed by `Delta gestartet.` in `sync_logs`

# Recommended next step

If a more explicit top-level pipeline progress bar is needed later, add a lightweight web-only orchestration run summary on top of the existing per-step `sync_runs` model instead of changing the CLI runner structure.
