# Ticket: T-010

## Title
Add import action to pipeline admin UI

## Status
done

## Problem
The admin pipeline page currently provides actions for merge, expand, delta and full pipeline,
but there is no dedicated import action.

This forces manual tests to use the full pipeline even when only the import step should be executed.

## Goal
Allow admins to trigger the import step directly from the pipeline page.

## Requirements
- Add a new button:
  - Run Import
- Place it next to the existing pipeline action buttons
- Reuse the existing SyncLauncher / launcher structure
- Trigger the existing import process
- Keep the current admin UI layout
- Do not break existing actions for merge, expand, delta and full pipeline
- Show execution status like the other pipeline actions

## Acceptance Criteria
- A Run Import button is visible on the pipeline page
- Clicking it starts the import process
- The browser returns correctly without blocking longer than necessary
- The status area reflects the import run
- Existing pipeline buttons continue to work unchanged

## Notes
This ticket is meant to improve testability and reduce the need for full pipeline runs during development.

## Implementation Notes
- Added a `Run Import` button to the pipeline action area on `/pipeline`.
- Reused the existing `/pipeline/start` flow and `SyncLauncher` job `import_all`; no new launcher logic was needed.
- Kept the existing layout and existing actions for merge, expand, delta, and full pipeline intact.
- The existing status area already reflects the latest or running `sync_runs` entry, so import runs appear there without additional monitoring changes.
