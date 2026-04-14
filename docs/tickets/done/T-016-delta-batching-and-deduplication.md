# Ticket: T-016

## Status
done

## Title
Improve delta batching and duplicate queue prevention

## Problem
Delta generation already creates queue entries, but repeated runs can still become noisy and inefficient if queue creation is too granular or duplicates are not suppressed strongly enough.

## Goal
Make delta generation more efficient and more predictable for repeated pipeline runs.

## Scope
- review current product delta queue creation
- improve batching or grouped writes where appropriate
- harden duplicate suppression for pending queue entries
- preserve the current confirmed-state model

## Acceptance Criteria
- [ ] delta runs avoid unnecessary duplicate queue rows
- [ ] queue creation remains correct for insert, update, and offline update cases
- [ ] implementation stays separate from external XT write behavior
- [ ] monitoring remains intact for delta execution and failures

## Files / Areas
- `src/Service/ProductDeltaService.php`
- `config/delta.php`
- `run_delta.php`
- `run_expand.php`
- `export_queue`

## Notes
Keep this ticket focused on delta generation efficiency, not export worker execution.

## Implementation Notes
- Added a configurable delta queue insert batch size in `config/delta.php`.
- Reworked `ProductDeltaService` to preload existing `pending` and `processing` queue signatures once per run.
- Replaced per-row duplicate lookup queries with in-memory signature tracking.
- New queue rows are now buffered and written in chunks, with row-by-row fallback if a batch insert fails.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
