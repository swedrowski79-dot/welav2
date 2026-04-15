# Task

Implement `T-039` by fixing export queue worker monitoring counts in the web overview.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-039 - Fix export queue worker monitoring counts in web overview.md`
- `docs/agent-results/2026-04-15-T-037-queue-and-delta-visibility.md`
- `docs/agent-results/2026-04-15-T-038-export-worker-failure-visibility.md`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`
- `src/Web/View/sync-runs/show.php`

# Changed files

- `src/Service/ExportQueueWorker.php`
- `src/Web/View/sync-runs/show.php`
- `docs/tickets/done/T-039-export-worker-monitoring-count-fix.md`
- `docs/agent-results/2026-04-15-T-039-export-worker-monitoring-count-fix.md`

# Summary

- Fixed `export_queue_worker` monitoring so retryable failures no longer inflate `sync_runs.error_count`.
- Added a dedicated `issue` counter for all non-success worker outcomes, while keeping:
  - `retried` for retryable failures
  - `permanent_error` for terminal failures
  - `error` aligned to terminal failures only
- Extended `/sync-runs/show` so export worker runs now show:
  - `Retries`
  - `Terminale Fehler`

# Open points

- The list page `/sync-runs` still shows the generic `Fehler` column from `sync_runs.error_count`; this is now semantically correct for terminal failures, but retry counts remain visible only in the worker detail view.
- The original open T-039 ticket file contained unrelated T-034 content and was replaced with the correct done ticket.

# Validation steps

- Executed syntax checks:
  - `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
  - `docker compose exec -T php php -l /app/src/Web/View/sync-runs/show.php`
- Executed live worker run:
  - `docker compose exec -T php php /app/run_export_queue.php`
- Executed live data check:
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, run_type, status, merged_records, error_count, context_json FROM sync_runs WHERE run_type='export_queue_worker' ORDER BY id DESC LIMIT 3;"`
- Executed live UI check:
  - `docker compose exec -T php php -r '$ctx = stream_context_create(["http" => ["method" => "GET", "ignore_errors" => true]]); $html = file_get_contents("http://127.0.0.1:8080/sync-runs/show?id=97", false, $ctx); ...'`
- Observed:
  - broken historical run `#96` stored:
    - `error_count = 300`
    - `retried = 300`
    - `permanent_error = 0`
  - fixed run `#97` stored:
    - `error_count = 0`
    - `retried = 300`
    - `permanent_error = 0`
    - `issue = 300`
  - `/sync-runs/show?id=97` returned HTTP `200`
  - `/sync-runs/show?id=97` contains `Retries`
  - `/sync-runs/show?id=97` contains `Terminale Fehler`

# Recommended next step

If desired, add the same retry/permanent-error split to the `/sync-runs` list view so worker runs are diagnosable without opening the detail page.
