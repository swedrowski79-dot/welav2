# Ticket: T-039

## Status
done

## Title
Fix export queue worker monitoring counts in web overview

## Goal
Make the web monitoring for `export_queue_worker` reflect terminal errors correctly so retry-only runs do not appear as failed/error-heavy worker runs.

## Requirements
- [x] review how `export_queue_worker` writes counts into `sync_runs`
- [x] identify why retry-only runs show inflated error counts in the web overview
- [x] keep retry information visible without counting retries as terminal errors
- [x] improve run detail visibility for worker-specific retry vs terminal error counts
- [x] keep export worker processing behavior unchanged
- [x] do not break product, media, or document sync

## Root Cause
`run_export_queue.php` persisted `error_count` from `ExportQueueWorker` stats. Inside the worker, every exception path incremented `error`, including retryable failures. As a result, successful worker runs with only retries appeared in the web overview as if they had many errors, even when `permanent_error = 0`.

## Implementation Notes
- `ExportQueueWorker`
  - added a separate `issue` counter for all non-success processing issues
  - keeps `retried` for retryable failures
  - keeps `permanent_error` for terminal failures
  - now increments `error` only for terminal failures so `sync_runs.error_count` matches real permanent errors
- `/sync-runs/show`
  - now decodes worker `context_json`
  - shows separate summary metrics for:
    - `Retries`
    - `Terminale Fehler`

## Validation
- [x] worker run with retry-only failures now stores `error_count = 0`
- [x] retry counts remain visible in `context_json`
- [x] `/sync-runs/show` renders separate retry and terminal error metrics
- [x] no worker claim/process behavior changed

## Runtime Evidence
- Historical broken run `#96`:
  - `status = success`
  - `error_count = 300`
  - `retried = 300`
  - `permanent_error = 0`
- Fixed run `#97`:
  - `status = success`
  - `error_count = 0`
  - `retried = 300`
  - `permanent_error = 0`
  - `issue = 300`

## Notes
The source ticket file in `docs/tickets/open/` was incorrectly filled with unrelated `T-034` content. This done ticket reflects the actual `T-039` task indicated by the filename and the implemented fix.
