# Task

Implement `T-037` by making delta and export queue behavior diagnosable when the worker processes no items.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `README.md`
- `database.sql`
- `docs/tickets/open/T-037 - Add queue and delta visibility to diagnose why export queue worker processes no items.md`
- `docs/agent-results/2026-04-15-T-028-media-document-delta-and-export-queue.md`
- `config/delta.php`
- `run_delta.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/DeltaRunnerService.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/View/pipeline/index.php`

# Changed files

- `src/Service/ProductDeltaService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `docs/tickets/done/T-037-queue-and-delta-visibility.md`
- `docs/agent-results/2026-04-15-T-037-queue-and-delta-visibility.md`

# Summary

- Added explicit delta-side queue visibility:
  - queue counts before/after
  - number of queue rows actually created
  - a normalized `result_reason`
- Added explicit worker-side claim visibility:
  - pending ready vs delayed vs processing snapshot before claim
  - `no_work_reason` when zero items are processed
- Added UI visibility on `/pipeline`:
  - `Delta-Sichtbarkeit`
  - `Worker-Sichtbarkeit`
  - queue summary by entity type
- Expanded queue filtering to `product`, `media`, and `document`.

# Open points

- The worker visibility card shows the latest worker run. If a dedicated diagnostic or validation run is executed afterwards, that latest run will be shown intentionally.
- The current productive queue still contains many retrying entries with `Ungueltige Signatur.`; T-037 makes that visible but does not change export behavior.

# Validation steps

- Executed syntax checks:
  - `docker compose exec -T php php -l /app/src/Service/ProductDeltaService.php`
  - `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/MonitoringRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/PipelineAdminRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
- Executed runtime validation:
  - `docker compose exec -T php php /app/run_delta.php`
  - `docker compose exec -T php php /app/run_export_queue.php`
  - `docker compose exec -T php php /app/run_export_queue.php`
  - one-off empty worker validation via `php -r` using the same `ExportQueueWorker` and `SyncMonitor`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT ... FROM sync_runs ..."`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT ... FROM sync_logs ..."`
  - `docker compose exec -T php php -r '$ctx = stream_context_create([...]); file_get_contents(\"http://127.0.0.1:8080/pipeline\", false, $ctx); ...'`
- Observed:
  - `/pipeline` returned `200`
  - `/pipeline` contains:
    - `Delta-Sichtbarkeit`
    - `Worker-Sichtbarkeit`
    - `Queue nach Entity-Typ`
    - the zero-work reason text `Es gab keine claimbaren pending Queue-Eintraege.`
  - Delta run `#92` logged per-entity queue visibility with:
    - `queue_created = 0`
    - `result_reason = existing_pending_or_processing_entries`
  - Productive worker run `#93` logged per-entity pre-claim visibility with:
    - `pending_ready_before`
    - `pending_delayed_before`
    - `processing_before`
  - Zero-work validation run `#94` finished `success` with:
    - `claimed = 0`
    - `processed = 0`
    - `no_work_reason = no_pending_queue_items`
    - log message `Export Queue Worker fand keine claimbaren Eintraege.`

# Recommended next step

Use the new queue and worker visibility to triage the existing retry-heavy queue backlog, especially the repeated `Ungueltige Signatur.` worker failures that are now clearly visible in the latest worker summaries.
