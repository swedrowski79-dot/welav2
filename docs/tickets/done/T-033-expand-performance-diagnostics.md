# T-033 - Expand performance diagnostics

## Title

Measure expand and delta runtime separately and add expand performance diagnostics.

## Goal

Make expand and delta runtime visible separately and collect enough diagnostics to identify the main performance bottlenecks without changing functional behavior.

## Requirements

- measure and log total runtime for expand
- measure and log total runtime for delta
- measure and log runtime per expand definition
- log number of source rows read per expand definition
- log number of target rows written per expand definition
- log number of insert batches per expand definition
- replace `SELECT *` queries in expand with explicit column lists
- keep current expand results functionally unchanged
- keep current delta behavior unchanged
- do not break existing product, media, or document sync

## Validation

- verify runtime for expand and delta is shown separately
- verify runtime per expand definition is shown
- verify row counts and batch counts are logged
- verify expand output is unchanged compared to current behavior
- verify delta output is unchanged compared to current behavior

## Implementation notes

- `ExpandService` now returns structured diagnostics for the whole expand run and for each definition:
  - runtime
  - source rows read
  - target rows written
  - insert batches
- expand queries now build explicit source column lists from the configured slots instead of using `SELECT *`
- `DeltaRunnerService` now measures and logs total delta runtime
- `run_expand.php` stores separate `expand` and `delta` diagnostics in `sync_runs.context_json`
- `run_delta.php` stores structured delta diagnostics under `context_json.delta`
- `/sync-runs/show` now renders dedicated expand and delta diagnostic sections instead of requiring manual JSON inspection

## Validation notes

- baseline `run_expand.php` output before the change:
  - `stage_attribute_translations = 24820` rows, checksum aggregate `53271065191496`
  - `stage_product_media = 5331` rows, checksum aggregate `11474933604910`
  - queue state unchanged at:
    - `product / insert / pending = 5350`
    - `media / insert / pending = 5331`
    - `document / insert / pending = 2853`
- after the change, the same counts and checksum aggregates were produced again
- latest expand run detail renders:
  - `Expand-Diagnostik`
  - separate `Expand Laufzeit` and `Delta Laufzeit`
  - per-definition metrics including `product_attributes_from_translations`
- latest delta run detail renders `Delta-Diagnostik` with the measured runtime

## Status

- [x] implemented
- [x] validated
- [x] moved to `docs/tickets/done/`
- [x] result file written
- [ ] pushed

## Result

Completed locally. Commit created as part of the ticket workflow; push remains intentionally pending until explicitly requested.
