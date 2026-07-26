# Task

Implement `T-036` by moving web full-pipeline orchestration onto a dedicated backend runner so web and CLI execution use the same process model.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-036 - Align web pipeline orchestration with backend runner behavior.md`
- `docs/agent-results/2026-04-15-T-035-web-pipeline-flow-alignment.md`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/MonitoringRepository.php`
- `public/index.php`
- `run_import_all.php`
- `run_merge.php`
- `run_expand.php`
- `run_export_queue.php`
- `src/Monitoring/SyncMonitor.php`

# Changed files

- `run_full_pipeline.php`
- `src/Web/Repository/SyncLauncher.php`
- `docs/tickets/done/T-036-web-pipeline-runner-alignment.md`
- `docs/agent-results/2026-04-15-T-036-web-pipeline-runner-alignment.md`

# Summary

- Added a dedicated backend runner `run_full_pipeline.php`.
- The runner starts a top-level `full_pipeline` run in monitoring and then executes the existing step runners sequentially:
  - `import_all`
  - `merge`
  - `expand`
  - `export_queue_worker`
- On every step it logs start/completion into `sync_logs`.
- On the first non-zero child exit code it stops and marks the top-level `full_pipeline` as failed.
- Updated `SyncLauncher` so the web UI now launches the new backend runner instead of an inline shell `&&` chain.

# Open points

- The monitoring UI still shows both the top-level `full_pipeline` run and the concrete child runs. That is intentional and keeps the detailed per-step history intact.
- This ticket does not introduce a new visible orchestration dashboard; it only aligns the execution model behind the existing UI.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/run_full_pipeline.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/SyncLauncher.php`
  - `docker compose exec -T php php /app/run_full_pipeline.php`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, run_type, status, started_at, ended_at, message FROM sync_runs ORDER BY id DESC LIMIT 12;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, message, created_at FROM sync_logs WHERE sync_run_id = 80 ORDER BY id ASC;"`
  - `docker compose exec -T php php -r 'require "/app/src/Web/bootstrap.php"; (new App\Web\Repository\SyncLauncher())->launch("full_pipeline"); echo "launched", PHP_EOL;'`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, run_type, status, started_at, ended_at, message FROM sync_runs ORDER BY id DESC LIMIT 12;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, message, created_at FROM sync_logs WHERE sync_run_id = 85 ORDER BY id ASC;"`
  - `docker compose exec -T php php -r '$ctx = stream_context_create(["http" => ["method" => "GET", "ignore_errors" => true]]); $html = file_get_contents("http://127.0.0.1:8080/pipeline", false, $ctx); preg_match("#HTTP/[0-9.]+ ([0-9]{3})#", $http_response_header[0] ?? "", $m); echo ($m[1] ?? "000"), PHP_EOL;'`
- Observed:
  - CLI run created:
    - `80 full_pipeline success`
    - `81 import_all success`
    - `82 merge success`
    - `83 expand success`
    - `84 export_queue_worker success`
  - Web launch created the same structure:
    - `85 full_pipeline success`
    - `86 import_all success`
    - `87 merge success`
    - `88 expand success`
    - `89 export_queue_worker success`
  - Both `full_pipeline` runs logged step-by-step orchestration messages in `sync_logs`.
  - `/pipeline` returned HTTP `200` after the change.

# Recommended next step

If the top-level pipeline status should become more prominent in the UI later, surface the `full_pipeline` `context_json` step counters in the pipeline status card instead of inferring everything only from the latest child run.
