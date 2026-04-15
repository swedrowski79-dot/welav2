# Ticket: T-027

## Status
done

## Title
Wire document/media data through merge and expand pipeline steps

## Problem
The conceptual pipeline already names steps like `expand_stage_media` and `expand_stage_documents`, but there is no active implementation wiring documents/images from raw import into stage tables usable by downstream export.

## Goal
Complete the pipeline wiring from raw document/image data to stage media/document rows.

## Scope
- add merge/expand config and service support for document/media stage rows
- relate document/image rows to products (and categories only if source relation exists)
- keep the implementation incremental and repository-consistent
- do not implement direct XT writes in this ticket

## Acceptance Criteria
- document/media rows flow from raw into stage tables
- product linkage is explicit and queryable
- resulting stage rows match downstream needs of `xt_media_documents` and related media link config
- monitoring/logging still works for the new pipeline parts

## Files / Areas
- `config/merge.php`
- `config/pipeline.php`
- `src/Service/MergeService.php`
- a focused expand/media service if required
- runner wiring and result docs

## Notes
If category-linked media exists in the AFS source, document it explicitly before implementing category media handling.

## Implementation Notes
- Wired `raw_afs_documents` into `stage_product_documents` through merge config and generic merge execution.
- Mapped `title`, `file_name`, `path`, `source_path`, `document_type`, `sort_order`, and `position` so document rows are product-linked by `afs_artikel_id` and usable for downstream media-document linking.
- Finalized document path handling by using the normalized `file_name` as stage `path` and preserving the original technical AFS path in `source_path`.
- Added raw article image slot persistence for `Bild1..Bild10` and expanded those slots into `stage_product_media`.
- `stage_product_media` now gets stable `media_external_id` values, explicit `source_slot`, `type = images`, `document_type = image`, and slot-based `sort_order` / `position`.
- No category-linked media source relation is configured, so this ticket keeps media handling product-only.
- No XT write/export logic was added.
