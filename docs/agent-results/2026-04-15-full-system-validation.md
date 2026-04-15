# Task

Create and execute a full end-to-end validation plan for the current pipeline system, fix real bugs found during validation, and document the current acceptance status.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/ticket-task.md`
- ticket inventory in `docs/tickets/done/`
- relevant result files in `docs/agent-results/`
- `run_import_all.php`
- `run_merge.php`
- `run_expand.php`
- `run_delta.php`
- `run_export_queue.php`
- `config/delta.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/Repository/SyncLauncher.php`
- `public/index.php`

# Changed files

- `src/Service/ProductDeltaService.php`
- `src/Monitoring/SyncMonitor.php`
- `docs/agent-results/2026-04-15-full-system-validation.md`

# Summary

## Tested scenarios

- Full CLI pipeline:
  - `docker compose exec -T php php /app/run_import_all.php`
  - `docker compose exec -T php php /app/run_merge.php`
  - `docker compose exec -T php php /app/run_expand.php`
  - `docker compose exec -T php php /app/run_delta.php`
  - `docker compose exec -T php php /app/run_export_queue.php`
- Full pipeline web trigger:
  - `POST /sync-runs/start` with `job=full_pipeline`
  - observed resulting `import_all -> merge -> expand` runs in `sync_runs`
- Queue transitions:
  - `pending -> processing -> done`
  - terminal error handling with `processed_at` and retained `claim_token`
- Confirmed export state:
  - `product_export_state.last_seen_at`
  - `product_export_state.last_exported_hash`
- Business rules:
  - missing product creates `update` with `online = 0`
  - no `delete` queue rows for missing products
  - category online rule checked against live data availability
- Admin UI visibility via HTTP:
  - `/pipeline`
  - `/pipeline/state`
  - `/sync-runs`
  - `/status`

## Passed checks

- Import, merge, expand+delta, standalone delta, and export worker all completed successfully after fixes.
- Web-triggered `full_pipeline` launch worked and created the expected monitored run chain:
  - `#38 import_all success`
  - `#39 merge success`
  - `#40 expand success`
- Queue worker transitions validated with real data:
  - before final worker run: `pending=9503`, `done=699`, `error=1`
  - after final worker run and cleanup: `pending=9403`, `done=798`, `error=0`
- Terminal queue rows now keep:
  - `claim_token`
  - `claimed_at`
  - `processed_at`
- Confirmed export state matched processed queue hashes for sampled `done` rows.
- Missing-product rule validated with a controlled synthetic state row:
  - produced `action = update`
  - payload contained `online = 0`
  - produced no `delete` queue row
  - updated state to offline hash
- Error isolation was validated earlier and remained consistent:
  - one failing queue row did not stop valid rows
  - error was logged in monitoring
  - terminal error row retained `claim_token` and `processed_at`
- Admin pages loaded successfully with HTTP `200` and exposed the expected monitoring/queue/state sections.
- Final validation state after cleanup:
  - `sync_runs` running count: `0`
  - pending/processing duplicate groups by `entity_id + action`: `0`

## Failed checks

- Initial `run_expand.php` failed during validation with:
  - `Allowed memory size of 134217728 bytes exhausted`
  - root cause: delta deduplication loaded the entire pending queue with large JSON payloads through a buffered query
- A historical crashed `expand` run remained stuck as `status = running` and polluted the monitoring view.
- Historical identical pending queue rows existed in large numbers:
  - `4851` duplicate groups
  - `9602` duplicate rows removed during cleanup

## Bugs fixed during validation

- `ProductDeltaService`
  - reduced memory pressure in queue deduplication by streaming queue signature reads and avoiding large payload buffering
  - simplified pending/processing deduplication to `entity_id + action`, which matches actual queue semantics and prevents duplicate pending work across repeated delta runs
- `SyncMonitor`
  - closes stale `running` rows of the same `run_type` before a new run starts, preventing old crashed runs from dominating the admin status
- Operational cleanup performed during validation:
  - manually closed historical stale run `#36`
  - removed `9602` historical identical pending duplicates from `export_queue`
  - removed synthetic queue/state test artifacts after validation

# Open points

- The category business rule `AFS internet = 0 -> internal online = 1` could not be validated with live source data in this environment because the current imported `raw_afs_categories` set contains `0` rows with `online_flag = 0`.
- The UI validation was HTTP/content based, not a human visual browser review.
- Monitoring history now correctly shows the historical failed run, but old validation runs and synthetic monitoring entries remain in `sync_runs` and `sync_logs` as audit history.

# Validation steps

- Runtime/environment:
  - `docker compose exec -T php php -v`
  - `docker compose exec -T php php -m | rg 'pdo_mysql|pdo_sqlite|pdo_sqlsrv|sqlsrv'`
- End-to-end pipeline:
  - `docker compose exec -T php php /app/run_import_all.php`
  - `docker compose exec -T php php /app/run_merge.php`
  - `docker compose exec -T php php /app/run_expand.php`
  - `docker compose exec -T php php /app/run_delta.php`
  - `docker compose exec -T php php /app/run_export_queue.php`
- Web-trigger validation:
  - `curl -X POST -d "job=full_pipeline" http://localhost:8080/sync-runs/start`
  - polling `sync_runs` until `import_all`, `merge`, and `expand` completed
- SQL validation:
  - stage/raw/export/state counts
  - queue transition counts
  - duplicate pending group counts
  - `product_export_state` spot checks against processed queue payload hashes
  - monitoring rows in `sync_runs`, `sync_logs`, and `sync_errors`
- UI visibility checks:
  - `curl http://localhost:8080/pipeline`
  - `curl http://localhost:8080/pipeline/state`
  - `curl http://localhost:8080/sync-runs`
  - `curl http://localhost:8080/status`
- Syntax validation:
  - `docker compose exec -T php php -l /app/src/Service/ProductDeltaService.php`
  - `docker compose exec -T php php -l /app/src/Monitoring/SyncMonitor.php`

# Recommended next step

Not ready for final signoff yet, because one required live business rule remains unproven: the category mapping case `raw_afs_categories.online_flag = 0 -> stage_categories.online_flag = 1` is not present in the current source dataset. The system is ready for continued staging use and targeted acceptance once a real category sample with that source flag is available.
