# Ticket: T-036

## Status
done

## Title
Align web pipeline orchestration with backend runner behavior

## Goal
Make the web-triggered sync use the same effective step flow and completion behavior as the CLI runners so both execution paths behave consistently.

## Requirements
- [x] compare web-triggered pipeline execution with CLI runner behavior
- [x] identify differences in executed steps, stop conditions, and completion handling
- [x] align web orchestration with the current backend runner sequence
- [x] ensure follow-up steps are not skipped due to outdated web orchestration
- [x] keep execution idempotent
- [x] keep logging and error handling consistent with current backend behavior
- [x] do not break existing product, media, or document sync

## Root Cause
After `T-035`, the web layer used the correct step order, but `full_pipeline` was still only a shell chain inside `SyncLauncher`.

That meant:
- web orchestration itself had no dedicated backend runner
- overall completion and failure state existed only indirectly through child runs
- the web entrypoint was not using the same runner-style abstraction as the CLI scripts

## Implementation Notes
- Added `run_full_pipeline.php` as a dedicated backend runner.
- The new runner:
  - starts its own `full_pipeline` monitoring run
  - executes the existing CLI step runners sequentially:
    - `run_import_all.php`
    - `run_merge.php`
    - `run_expand.php`
    - `run_export_queue.php`
  - stops on the first non-zero exit code
  - logs per-step start and completion into `sync_logs`
  - finishes the top-level `full_pipeline` run as `success` or `failed`
- Updated `SyncLauncher` so web-triggered `full_pipeline` now launches `php /app/run_full_pipeline.php` instead of an inline shell chain.
- Existing individual step launchers remained unchanged.

## Validation
- [x] `run_full_pipeline.php` passes PHP syntax validation
- [x] web `SyncLauncher` points `full_pipeline` to the new backend runner
- [x] direct CLI execution creates:
  - one `full_pipeline` run
  - the child runs `import_all`, `merge`, `expand`, `export_queue_worker`
  - step-by-step `sync_logs` under the `full_pipeline` run
- [x] web-triggered execution via `SyncLauncher` creates the same run structure
- [x] both execution paths finish successfully with the same effective sequence

## Notes
This ticket does not change the individual import/merge/expand/export worker scripts. It only moves web full-pipeline orchestration onto a dedicated backend runner so CLI and web now share the same orchestration behavior.
