# Ticket: T-012

## Title
Add granular import actions to admin UI

## Status
done

## Problem
The current import action triggers the full import process (import_all),
which is slow (~7 minutes) and inefficient for testing.

## Goal
Allow triggering specific import types individually.

## Requirements
- Add separate buttons:
  - Run Product Import
  - Run Category Import
  - Run Document Import (if applicable)
- Each button should trigger the corresponding importer method
- Reuse existing importer logic
- Do not duplicate code
- Keep UI consistent with existing buttons

## Acceptance Criteria
- [x] Individual import actions are visible in the UI
- [x] Each action only processes its respective data
- [x] Execution is significantly faster than full import
- [x] Existing full import still works

## Notes
This improves development speed and debugging efficiency.

## Implementation Notes
- Added dedicated product and category import jobs to the admin UI and launcher.
- Introduced `src/Service/ImportWorkflow.php` to reuse the existing importer logic without duplicating runner code.
- Added `run_import_products.php` and `run_import_categories.php` for granular imports.
- Kept `Run Import` as the existing full import entry point.
- No document import action was added because there is currently no corresponding document raw table/import workflow in the repository.
