# Ticket: T-014

## Status
done

## Title
Optimize import throughput with chunked reads and writes

## Problem
The import step is currently the slowest part of the pipeline during local testing.
Large source reads and row-by-row writes make repeated development runs expensive.

## Goal
Improve import throughput without changing the current CLI pipeline structure.

## Scope
- profile the current import flow for the main source entities
- introduce chunked processing where it fits the existing importer structure
- reduce avoidable per-row database overhead
- keep monitoring and error logging intact

## Acceptance Criteria
- [ ] import processing is measurably faster on the same dataset
- [ ] importer logic stays source-isolated and repository-consistent
- [ ] no direct XT logic is introduced
- [ ] sync monitoring continues to record import progress and failures

## Files / Areas
- `src/Importer/`
- `src/Service/StageWriter.php`
- `config/sources.php`
- `run_import_all.php`
- related import runner files

## Notes
Keep this ticket limited to import throughput.
Do not combine it with merge, expand, or UI work.

## Implementation Notes
- Added configurable write batch sizes for AFS and Extra imports in `config/sources.php`.
- Reworked `AfsImporter` and `ExtraImporter` to collect normalized rows and write them in batches instead of one insert per row.
- Extended `StageWriter` with cached single-row statements plus a multi-row `insertMany()` path for batched writes.
- `ExtraImporter` now also uses configured column lists instead of `SELECT *`.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
