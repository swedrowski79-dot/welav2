# Ticket: T-041

## Status
done

## Title
Use XT snapshot data for target-aware delta and queue creation

## Goal
Use the imported XT snapshot as an additional comparison source so delta only creates queue entries when Stage data really differs from the current XT state.

## Requirements
- [x] use XT snapshot tables as an additional comparison source for delta processing
- [x] compare Stage product data against XT product snapshot data
- [x] compare Stage category assignments against XT product/category snapshot data where relevant
- [x] compare Stage media data against XT media snapshot data
- [x] compare Stage document data against XT document snapshot data
- [x] enqueue export queue items only when a real difference between Stage and XT snapshot exists
- [x] keep the implementation idempotent
- [x] avoid duplicate queue rows for unchanged entities
- [x] preserve current export behavior for genuinely new or changed entities
- [x] keep the existing internal state safeguards as fallback
- [x] do not break existing product, media, or document sync

## Implementation Notes
- `ProductDeltaService`
  - now supports optional snapshot comparison per export-queue entity
  - keeps the existing state-table logic as fallback when no valid snapshot run exists
  - suppresses queue creation when the snapshot already matches Stage
  - still queues updates when the snapshot proves the target is different
  - treats missing snapshot rows as missing XT target rows and therefore queues `insert`
  - skips removal queue entries when the snapshot already shows the target row is gone
- `config/delta.php`
  - added snapshot config for:
    - `product_export_queue`
    - `media_export_queue`
    - `document_export_queue`
- `xt_products_snapshot`
  - extended with:
    - `category_afs_id`
    - `translation_hash`
    - `attribute_hash`
    - `seo_hash`
- `XtSnapshotService`
  - now also imports and condenses:
    - `xt_products_to_categories`
    - `xt_products_description`
    - `xt_plg_products_attributes`
    - `xt_plg_products_attributes_description`
    - `xt_plg_products_to_attributes`
    - `xt_seo_url`
  - builds compact compare hashes for product translations, attributes, and SEO metadata
- `wela-api`
  - expanded read-field whitelists for the additional read-only snapshot tables

## Validation
- [x] unchanged products matching the XT snapshot are not queued again
- [x] changed products are still queued
- [x] product category assignment differences are detected as changes
- [x] unchanged media are not queued again
- [x] changed media are still queued
- [x] unchanged documents are not queued again
- [x] changed documents are still queued
- [x] repeated delta runs remain idempotent and create no duplicate active queue rows

## Runtime Evidence
- Isolated delta integration with temporary Stage/Snapshot/Queue tables produced on first run:
  - `product`: `3 processed`, `1 unchanged`, `2 update`
  - `media`: `2 processed`, `1 unchanged`, `1 update`
  - `document`: `2 processed`, `1 unchanged`, `1 update`
- Queue rows after first run:
  - `product / 91002 / update / pending`
  - `product / 91003 / update / pending`
  - `media / t041-media-91002 / update / pending`
  - `document / 92002 / update / pending`
- Second run:
  - created `0` new queue rows
  - reported `deduplicated = 4`
  - duplicate active queue groups = `0`

## Notes
- Product snapshot comparison is now broad enough to cover:
  - top-level product fields
  - category assignment
  - translated product description fields
  - attribute values
  - SEO meta fields
- The source open ticket filename had a formatting issue (`-Use` without spacing). The done ticket reflects the implemented scope cleanly.
