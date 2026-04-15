# Ticket: T-037

## Status
done

## Title
Add queue and delta visibility to diagnose why export queue worker processes no items

## Goal
Make it visible why the export queue worker runs successfully but processes no items by exposing queue creation, queue status, and delta results more clearly.

## Requirements
- [x] review how delta writes entries into `export_queue`
- [x] identify why `export_queue_worker` can end with `0` processed items
- [x] add clear logging for queue items created by delta
- [x] add clear logging for queue items picked up by `export_queue_worker`
- [x] show whether no changes were detected or no pending queue items existed
- [x] improve web interface visibility for queue state and worker result
- [x] keep current sync behavior unchanged
- [x] do not break existing product, media, or document sync

## Root Cause
The system already exposed queue data, but the decisive diagnostic context was missing:

- delta only logged aggregate entity stats, not whether new queue rows were actually written versus deduplicated against existing pending/processing rows
- the worker logged claims, but not a clear reason when nothing was claimable
- the pipeline page did not surface the latest delta outcome and latest worker pre-claim snapshot in a compact diagnostic view

## Implementation Notes
- `ProductDeltaService`
  - now captures queue counts before and after delta per entity type
  - adds `queue_created`, `pending_before`, `pending_after`, `processing_before`, `processing_after`, and `result_reason`
  - writes an additional visibility log entry after each entity delta run
- `ExportQueueWorker`
  - now captures a queue availability snapshot before claiming:
    - `pending_total`
    - `pending_ready`
    - `pending_delayed`
    - `processing_total`
  - logs a dedicated message when no claimable entries exist
  - stores `no_work_reason` in run context so the UI can explain zero-item runs
- `MonitoringRepository`
  - added lookup for latest run by multiple run types
- `PipelineAdminRepository`
  - added queue summary grouped by entity type
- `/pipeline`
  - added `Delta-Sichtbarkeit`
  - added `Worker-Sichtbarkeit`
  - added queue summary by entity type
  - expanded queue filter entity types to `product`, `media`, `document`

## Validation
- [x] delta logs now show whether queue rows were created or skipped due to existing active queue entries
- [x] worker logs now show queue state before claim and a dedicated zero-work reason
- [x] `/pipeline` shows the new visibility panels and queue-by-entity summary
- [x] repeated delta runs remain idempotent
- [x] zero-item worker case is visible and explainable

## Runtime Evidence
- Delta run `#92` logged:
  - `Produkt-Queue-Sichtbarkeit aktualisiert.`
  - `Medien-Queue-Sichtbarkeit aktualisiert.`
  - `Dokument-Queue-Sichtbarkeit aktualisiert.`
  - with `result_reason = existing_pending_or_processing_entries`
- Worker run `#93` logged:
  - queue status before claim for `product`, `media`, `document`
  - processed `300` claimed rows total
  - `pending_delayed_before = 100` per entity after retries were scheduled
- Zero-work validation run `#94` logged:
  - `Export Queue Status vor Worker-Claim ermittelt.`
  - `Export Queue Worker fand keine claimbaren Eintraege.`
  - `no_work_reason = no_pending_queue_items`

## Notes
The zero-item validation used a dedicated empty entity type via a one-off diagnostic run so the new no-work reason could be verified without modifying productive queue data.
