# Ticket: T-028

## Status
done

## Title
Add delta calculation and export queue handling for product media and documents

## Problem
`stage_product_media` and `stage_product_documents` are populated, but the delta/export queue flow still only tracks products. Media and document changes therefore do not reach the export queue or confirmed export state.

## Goal
Integrate product media and document stage rows into the existing delta and export queue system without adding XT write behavior.

## Scope
- extend delta handling to include `stage_product_media` and `stage_product_documents`
- detect new, changed, and removed media/document entries
- enqueue create/update work for media and documents without duplicate pending work
- extend confirmed export-state handling so queue processing can mark media/document hashes as exported
- keep the implementation aligned with existing product delta and queue patterns

## Acceptance Criteria
- [ ] media delta detects new, changed, and removed rows
- [ ] document delta detects new and changed rows, with removal handling kept consistent with the queue/state model
- [ ] export queue receives media/document create/update work without duplicate pending or processing rows
- [ ] export queue confirmation updates dedicated media/document export state after success
- [ ] no XT write logic is added

## Files / Areas
- `database.sql`
- `migrations/*`
- `config/delta.php`
- `run_expand.php`
- `run_delta.php`
- `run_export_queue.php`
- `src/Service/ProductDeltaService.php`
- focused media/document delta or queue services if needed
- tightly coupled admin/schema validation files if required

## Notes
- Reuse the existing queue batching and deduplication approach instead of inventing a second queue model.
- Stable media identity must remain compatible with the `stage_product_media.media_external_id` approach introduced earlier.

## Implementation Notes
- Added dedicated media/document export-state tables plus stage hash fields so delta baselines are persisted independently from the stage rebuild.
- Extended the delta config with `media_export_queue` and `document_export_queue` definitions and a shared execution order for all exportable entities.
- Generalized `ProductDeltaService` so it can also process flat stage tables with string or numeric identities, while keeping product payload hashing with translations and attributes.
- Added `DeltaRunnerService` so `run_expand.php` and `run_delta.php` now execute product, media, and document delta calculation in one pass.
- Updated queue de-duplication to keep at most one active pending/processing queue entry per entity, which prevents `insert` + `update` duplication on repeated delta runs before export confirmation.
- Extended `ExportQueueWorker` to process all configured entity types and confirm hashes into the matching export-state table after success.
- Media/document removals are represented as `update` queue entries with a terminal removal hash, following the same local-confirmation pattern already used for products.
- Fixed `MigrationRepository` so migration runs no longer fail when a migration script contains MySQL DDL that auto-commits before the repository reaches its explicit `commit()` call.
