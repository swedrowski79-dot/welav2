# Ticket: T-003

## Status
done

## Title
Implement export worker and confirmed export state handling

## Problem
The delta baseline currently exists, but export confirmation is not yet complete.
Long term the persisted state should reflect successful export, not only delta detection.

## Goal
Create an export worker that consumes `export_queue` and updates confirmed state after successful export.

## Scope
- export worker for queue processing
- mark queue entries as `done` / `error`
- update confirmed export state after successful export
- keep XT integration separated and safe

## Acceptance Criteria
- [x] queue entries are processed by a worker
- [x] successful exports mark queue entries as `done`
- [x] failed exports mark queue entries as `error`
- [x] confirmed export state is updated only after success

## Files / Areas
- export worker
- `export_queue`
- `product_export_state`
- later category export state

## Notes
No direct XT DB writes unless explicitly intended by architecture.

## Implementation Notes
- Added `src/Service/ExportQueueWorker.php` plus `run_export_queue.php` as a safe local export confirmation worker.
- The worker consumes pending `export_queue` entries, validates payloads, marks successful entries as `done`, and marks invalid entries as `error`.
- `product_export_state.last_exported_hash` is now updated by the worker only after successful processing.
- `ProductDeltaService` now refreshes `last_seen_at` during delta runs, but no longer overwrites the confirmed export hash before export success.
- Added deduplication for identical pending queue entries to avoid repeated queue spam while waiting for worker confirmation.
