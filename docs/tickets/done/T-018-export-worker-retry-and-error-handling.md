# Ticket: T-018

## Status
done

## Title
Add retry policy and clearer error handling to export processing

## Problem
Export failures are currently visible, but the worker needs a clearer retry model so transient failures and permanent invalid payload errors are easier to distinguish.

## Goal
Improve retry handling and error visibility for export queue processing.

## Scope
- introduce a limited retry strategy for retryable export failures
- keep invalid payload or permanent failure cases clearly marked
- improve error context recorded in monitoring and queue state
- avoid hiding repeated failures behind generic error rows

## Acceptance Criteria
- [ ] retryable failures can be retried in a controlled way
- [ ] permanent failures remain clearly visible
- [ ] queue status and error details are more actionable for admins
- [ ] successful retries still update confirmed export state correctly

## Files / Areas
- `src/Service/ExportQueueWorker.php`
- `run_export_queue.php`
- monitoring repositories or services used by export processing
- `export_queue`

## Notes
This ticket is about worker behavior and observability, not UI redesign.

## Implementation Notes
- Added configurable retry limits and retry delay settings in `config/delta.php`.
- Extended `export_queue` with retry metadata: `attempt_count`, `available_at`, `processed_at`, and `last_error`.
- Distinguished permanent payload validation failures from retryable runtime failures in `ExportQueueWorker`.
- Retryable failures now return the entry to `pending` with a delayed `available_at`; permanent or exhausted failures end on `error` with persisted error context.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
