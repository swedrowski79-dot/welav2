# T-034 - Incremental expand

## Title

Optimize expand to avoid full rebuilds and process only affected products.

## Goal

Reduce expand runtime by replacing full-table rebuild behavior with product-scoped updates so only affected products are recalculated and rewritten.

## Requirements

- remove global truncate behavior from expand targets
- determine affected products before writing expand results
- delete and rebuild expand target rows only for affected products
- keep expand idempotent
- do not create duplicate stage rows
- preserve current expand data structure and content
- keep delta processing compatible with the new expand behavior
- keep queue/export-state confirmation behavior correct
- do not break existing product, media, or document sync

## Validation

- verify unchanged products are not rewritten unnecessarily
- verify changed products are expanded correctly
- verify repeated runs create no duplicate rows
- verify expand output for affected products matches previous full rebuild behavior
- verify downstream delta and export continue to work correctly

## Implementation notes

- `ExpandService` no longer truncates `stage_attribute_translations` or `stage_product_media`.
- expand now rebuilds source-derived rows in memory per product scope (`afs_artikel_id`) and compares them against the current target rows for the same product scope.
- only affected scopes are deleted and reinserted; unchanged scopes are left untouched, including their existing target row IDs and downstream hashes.
- products that now produce zero expand rows are handled by targeted deletion only, without a full-table rebuild.
- the small `NULL afs_artikel_id` translation bucket is handled as one dedicated scope so anomalous rows do not force a global truncate.
- existing expand diagnostics from T-033 were preserved and extended with:
  - `affected_products`
  - `unchanged_products`
  - `deleted_rows`

## Validation notes

- no-change expand-only rerun now reports:
  - `affected_products = 0`
  - `written_rows = 0`
  - `insert_batches = 0`
- targeted product test:
  - sample product `68`
  - untouched control product `72`
  - language `de`
- with a temporary source change on product `68`:
  - attribute expand rewrote exactly `4` rows for `1` affected product
  - media expand rewrote exactly `1` row for `1` affected product
  - control product `72` kept the same target row IDs
- after reverting the temporary source change:
  - the target content reverted correctly
  - a final no-change rerun again produced `affected_products = 0` and `written_rows = 0`
- final stable `run_expand.php` and `run_delta.php` runs kept:
  - `stage_attribute_translations = 24820`, checksum aggregate `53271065191496`
  - `stage_product_media = 5331`, checksum aggregate `11474933604910`
  - queue state unchanged:
    - `product / insert / pending = 5350`
    - `media / insert / pending = 5331`
    - `document / insert / pending = 2853`

## Status

- [x] implemented
- [x] validated
- [x] moved to `docs/tickets/done/`
- [x] result file written
- [ ] pushed

## Result

Completed locally in commit `24192d4` (`Implement T-034 incremental expand`). Push remains intentionally pending until explicitly requested.
