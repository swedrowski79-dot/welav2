# Ticket: T-040

## Status
done

## Title
Introduce XT snapshot import to enable target-aware delta and avoid unnecessary exports

## Goal
Import the current XT state into dedicated snapshot tables so later delta processing can compare Stage data against real target data instead of relying only on internal state.

## Requirements
- [x] implement a new XT snapshot import step
- [x] fetch XT data for products
- [x] fetch XT data for categories
- [x] fetch XT data for media
- [x] fetch XT data for documents if available
- [x] store XT data in dedicated snapshot tables
- [x] ensure snapshot import is idempotent
- [x] avoid duplicate snapshot rows
- [x] define a clear AFS-to-XT mapping
- [x] keep snapshot import independent from export queue worker
- [x] do not change existing delta behavior yet
- [x] do not break existing product, media, or document sync
- [x] make the snapshot step executable manually and from the pipeline UI

## Implementation Notes
- Added snapshot tables:
  - `xt_products_snapshot`
  - `xt_categories_snapshot`
  - `xt_media_snapshot`
  - `xt_documents_snapshot`
- Added migration `012_create_xt_snapshot_tables.sql`.
- Added config-driven snapshot source definition in `config/xt_snapshot.php`.
- Added `run_xt_snapshot.php` and `XtSnapshotService`.
- Snapshot import uses the existing XT API path, not a direct XT database connection.
- Added a read-only XT API action `fetch_rows` with paginated table reads.
- Snapshot mapping is now explicit:
  - products: `xt_products.external_id -> afs_artikel_id`
  - categories: `xt_categories.external_id -> afs_wg_id`
  - media: `xt_media.external_id -> media_external_id`, linked to products via `xt_media_link.link_id -> xt_products.products_id -> xt_products.external_id`
  - documents: `xt_media.external_id -> afs_document_id/document_external_id`, linked to products through the same product relation
- Snapshot refresh is transactional and uses `DELETE` instead of `TRUNCATE` so MySQL does not silently break the transaction.
- No delta logic was changed yet.

## Validation
- [x] snapshot migration applied successfully
- [x] snapshot import runs successfully from CLI
- [x] repeated snapshot imports remain idempotent
- [x] snapshot tables contain mapped product/category/media/document rows
- [x] no duplicate snapshot rows are created
- [x] export queue count stays unchanged by snapshot import
- [x] pipeline UI exposes `Run XT Snapshot`
- [x] web launcher can start the snapshot job

## Runtime Evidence
- Isolated XT test data produced:
  - `xt_products_snapshot = 2`
  - `xt_categories_snapshot = 2`
  - `xt_media_snapshot = 2`
  - `xt_documents_snapshot = 1`
- Duplicate checks after repeated runs:
  - products = `0`
  - categories = `0`
  - media = `0`
  - documents = `0`
- Queue remained unchanged during validation:
  - `export_queue` count stayed `13534`
- Successful runs recorded:
  - `#107 xt_snapshot success`
  - `#108 xt_snapshot success`
  - `#109 xt_snapshot success`

## Notes
- Validation used an isolated local XT test database plus local `wela-api` instance so the new snapshot flow could be verified end-to-end without depending on an external shop endpoint.
- The current `.env` loading order prefers file values over temporary shell overrides. Validation therefore used a temporary `.env` switch and restored it afterwards.
