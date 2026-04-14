# Ticket: T-015

## Status
done

## Title
Reduce merge and expand performance hotspots

## Problem
Merge and expand rebuild stage data repeatedly and may contain avoidable full-table or per-row overhead.
That slows down validation cycles after import changes.

## Goal
Optimize the merge and expand steps while preserving the current stage-first architecture.

## Scope
- review the heaviest merge and expand operations
- remove avoidable repeated queries or row-by-row work
- improve processing structure where small focused changes are sufficient
- keep output tables and monitoring behavior unchanged

## Acceptance Criteria
- [ ] merge and/or expand runtime is measurably improved
- [ ] stage tables remain the internal source of truth
- [ ] no large rewrite of the current CLI steps is introduced
- [ ] existing pipeline outputs remain functionally equivalent

## Files / Areas
- `src/Service/MergeService.php`
- `src/Service/ExpandService.php`
- `config/merge.php`
- `config/expand.php`
- `run_merge.php`
- `run_expand.php`

## Notes
Do not include delta generation or export worker changes here.

## Implementation Notes
- Added configurable insert batch sizes to merge and expand config.
- Reworked `MergeService` to preload match tables once into in-memory indexes instead of querying the same match table for every base row.
- `MergeService` now writes merged rows in batches instead of one insert per row.
- `ExpandService` now buffers expanded attribute rows and inserts them in batches.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
