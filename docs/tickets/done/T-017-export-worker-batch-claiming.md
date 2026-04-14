# Ticket: T-017

## Status
done

## Title
Add batch claiming and controlled processing to the export worker

## Problem
The export worker currently processes queue entries in a simple flow.
As queue volume grows, the worker needs clearer batch boundaries and safer claim behavior.

## Goal
Let the export worker process queue entries in controlled batches without changing the external integration boundary.

## Scope
- define a batch size strategy for pending queue rows
- claim or reserve a bounded set of rows per worker execution
- keep queue status transitions clear and auditable
- preserve confirmed export state updates after success

## Acceptance Criteria
- [ ] worker processes queue rows in bounded batches
- [ ] queue rows are not processed twice within the same worker run
- [ ] successful rows still update confirmed export state only after success
- [ ] failed rows remain visible for later retry handling

## Files / Areas
- `src/Service/ExportQueueWorker.php`
- `run_export_queue.php`
- `export_queue`
- `product_export_state`

## Notes
Do not add real XT write logic in this repository.
Keep the worker safe and local.

## Implementation Notes
- Added a configurable worker batch size in `config/delta.php`.
- Extended `export_queue` claim metadata with `claim_token` and `claimed_at`.
- Reworked `ExportQueueWorker` so each run first claims a bounded queue batch, then only processes the rows it claimed itself.
- Successful and failed processing now validate claim ownership before changing queue status.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
