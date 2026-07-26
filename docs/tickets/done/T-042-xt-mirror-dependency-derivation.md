# T-042 - XT mirror dependency derivation

## Title

Derive and complete the XT mirror strictly from writer dependencies.

## Goal

Ensure the local XT mirror includes every sync-relevant XT table and field required by the active write logic, without inventing schema outside the export configuration and writer flow.

## Requirements

- use the XT write logic as the single source of truth
- inspect all writer implementations, especially:
  - `XtCompositeWriter`
  - `XtProductWriter`
  - `XtMediaDocumentWriter`
  - logic referenced from `config/xt_write.php`
- identify all XT tables written during export
- map entity types to XT tables and required fields
- include:
  - base tables
  - relation tables
  - description/language tables
- do not guess tables that are not used by the writers
- compare writer dependencies against the current mirror implementation
- extend the mirror with exactly the missing tables, fields, and relations
- preserve XT primary keys and relation keys needed by the writers
- keep mirror refresh idempotent
- avoid duplicate mirror rows
- keep mirror refresh independent
- do not change export behavior yet
- do not break product, media, or document sync

## Implementation notes

- Added `XtWriteDependencyMap` to derive XT table dependencies directly from `config/xt_write.php`.
- `XtSnapshotService` now derives its XT source list from that dependency map instead of maintaining a separate manual source registry.
- Snapshot refresh now writes raw mirror tables for all writer-relevant XT tables:
  - `xt_mirror_products`
  - `xt_mirror_categories`
  - `xt_mirror_categories_description`
  - `xt_mirror_products_description`
  - `xt_mirror_products_to_categories`
  - `xt_mirror_media`
  - `xt_mirror_media_link`
  - `xt_mirror_plg_products_attributes`
  - `xt_mirror_plg_products_attributes_description`
  - `xt_mirror_plg_products_to_attributes`
  - `xt_mirror_seo_url`
- Existing aggregate snapshot tables from T-040/T-041 were preserved; the new raw mirror tables are refreshed in the same transactional run.
- `wela-api/index.php` now derives allowed XT read fields from the same dependency map so `fetch_rows` exposes all writer-relevant fields and tables, including `xt_categories_description`.
- Added migration `014_create_xt_mirror_tables.sql` and updated `database.sql`, admin table registration, and migration skip detection.

## Validation notes

- syntax checks passed for:
  - `src/Service/XtWriteDependencyMap.php`
  - `src/Service/XtSnapshotService.php`
  - `wela-api/index.php`
  - `src/Web/Repository/MigrationRepository.php`
- applied `migrations/014_create_xt_mirror_tables.sql`
- verified the derived dependency map in the PHP container and confirmed all writer-used XT tables resolve to mirror tables
- executed `XtSnapshotService` twice against a local HTTP XT-API stub with real Stage MySQL tables and observed stable, duplicate-free mirror refreshes
- verified resulting counts after repeated refresh:
  - `xt_products_snapshot = 1`
  - `xt_categories_snapshot = 1`
  - `xt_media_snapshot = 1`
  - `xt_documents_snapshot = 1`
  - `xt_mirror_products = 1`
  - `xt_mirror_categories = 1`
  - `xt_mirror_categories_description = 1`
  - `xt_mirror_products_description = 1`
  - `xt_mirror_products_to_categories = 1`
  - `xt_mirror_media = 2`
  - `xt_mirror_media_link = 2`
  - `xt_mirror_plg_products_attributes = 1`
  - `xt_mirror_plg_products_attributes_description = 1`
  - `xt_mirror_plg_products_to_attributes = 1`
  - `xt_mirror_seo_url = 2`
- export-path files and writer behavior were left unchanged; this ticket only extends mirror/read surfaces

## Status

- [x] implemented
- [x] validated
- [x] moved to `docs/tickets/done/`
- [x] result file written
- [ ] pushed

## Result

Completed locally in commit `615b8c7` (`Implement T-042 XT mirror derivation`). Push remains intentionally pending until explicitly requested.
