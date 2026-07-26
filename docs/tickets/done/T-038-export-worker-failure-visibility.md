# Ticket: T-038

## Status
done

## Title
Diagnose export queue worker failures and make XT export errors visible

## Goal
Make retrying XT export failures and queue-level export errors visible in the web interface so it is clear why the export queue worker is not making progress.

## Requirements
- [x] review current export queue worker failure handling
- [x] identify where XT export failures are already logged or stored
- [x] make retry-faehige XT export failures visible in the web interface
- [x] make queue entries with `last_error` visible in the web interface
- [x] keep current export behavior unchanged
- [x] do not break product, media, or document sync

## Root Cause
The system already stored useful export-failure data, but it was split across:

- `sync_logs` warning entries from `export_queue_worker`
- `export_queue.last_error` on retrying or failed queue rows
- `sync_errors` only for terminal error cases

Retrying XT export failures therefore stayed mostly hidden unless someone manually searched logs or individual queue rows.

## Implementation Notes
- Added `MonitoringRepository::recentExportWorkerIssues()` to load warning/error log entries from recent `export_queue_worker` runs.
- Added `PipelineAdminRepository::queueIssueSummary()` and `recentQueueIssues()` to surface queue rows that already carry `last_error`.
- Extended `/pipeline` with an `XT-/Exportprobleme` panel showing:
  - queue problem counters
  - recent export worker warnings/errors
  - recent queue entries with `last_error`
- Extended `/errors` with the same focused export-problem visibility so retryable XT export issues are visible even before they become terminal `sync_errors`.
- Kept all export logic unchanged; this ticket only improves diagnosis and visibility.

## Validation
- [x] `/pipeline` renders with `XT-/Exportprobleme`
- [x] `/errors` renders with `XT-/Exportprobleme`
- [x] live queue rows with `last_error` are shown
- [x] live export worker warning logs are shown
- [x] no export behavior change was introduced

## Runtime Evidence
- Current live queue summary with `last_error`:
  - `product / pending = 800`
  - `media / pending = 800`
  - `document / pending = 800`
- Current live worker warning logs include:
  - `Export Queue Eintrag fuer Retry vorgemerkt.`
  - `exception = Ungueltige Signatur.`

## Notes
The source ticket file in `docs/tickets/open/` was incorrectly filled with unrelated `T-034` content. This done ticket reflects the actual `T-038` task indicated by the filename and the implemented scope.
