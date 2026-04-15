# Ticket: T-035

## Status
done

## Title
Adjust web interface pipeline flow to support the current sync process beyond export worker

## Goal
Update the web interface and pipeline control flow so the sync no longer stops before the final implemented export step and correctly reflects the current process end to end.

## Requirements
- [x] review the current web interface pipeline flow and step handling
- [x] identify why the process currently stopped before the final export step
- [x] adjust the UI pipeline sequence to match the implemented backend process
- [x] ensure all intended steps after expand can be triggered or completed correctly
- [x] update progress/status handling in the web interface
- [x] ensure success, running, and error states are shown correctly for all steps
- [x] keep existing manual step execution working
- [x] keep CLI-based execution behavior unchanged
- [x] do not break existing import, merge, expand, delta, queue, or export functionality

## Root Cause
`SyncLauncher` still defined `full_pipeline` as:

- `run_import_all.php`
- `run_merge.php`
- `run_expand.php`

The web-triggered full pipeline therefore ended before `run_export_queue.php`, even though the currently implemented end-to-end backend flow continues through the export worker. In parallel, the pipeline UI still described `expand` and `delta` as fully separate steps although `run_expand.php` already runs delta internally.

## Implementation Notes
- Extended the web-only `full_pipeline` launcher command to append `run_export_queue.php`.
- Kept manual buttons for `delta` and `export_queue_worker` unchanged.
- Updated pipeline and sync-runs UI copy so it now reflects:
  - `expand` includes delta
  - `full_pipeline` runs through the export worker
  - manual delta remains available as a standalone rerun
- Updated the pipeline controller run label for `expand` to `Expand + Delta`.

## Validation
- [x] `/pipeline` renders successfully and shows the updated flow text
- [x] `/sync-runs` renders successfully and shows the updated flow text
- [x] launching `full_pipeline` through the same `SyncLauncher` path used by the web UI creates:
  - `import_all`
  - `merge`
  - `expand`
  - `export_queue_worker`
- [x] manual step launcher behavior remained unchanged, including a standalone `delta` launch
- [x] CLI runner files were not changed

## Notes
This ticket intentionally updates only web orchestration and web-facing flow/status wording. No CLI pipeline runner was changed.
