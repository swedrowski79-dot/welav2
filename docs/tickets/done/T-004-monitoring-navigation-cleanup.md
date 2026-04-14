# Ticket: T-004

## Status
done

## Title
Clean up monitoring navigation and log/error separation

## Problem
The current menu structure for Logs and Errors is still confusing and partly duplicated.

## Goal
Make monitoring navigation easier to understand.

## Scope
- clarify difference between logs and errors
- improve menu labels or grouping
- keep existing functionality

## Acceptance Criteria
- [x] logs and errors are clearly separated in UI
- [x] reset actions are placed logically
- [x] navigation is easier to understand

## Files / Areas
- layout / navigation
- logs view
- errors view
- monitoring-related controllers and views

## Notes
This is a later cleanup ticket and not urgent before core pipeline stability.

## Implementation Notes
- Updated the sidebar labels to make the monitoring sections clearer: runs, logs, and errors are now explicitly named as monitoring areas.
- Added short explainer panels and cross-links on the logs and errors pages to make the difference between both views explicit.
- Moved `Reset Errors` to the errors page and `Reset Runs` to the runs page, while `Reset Logs` stays on the logs page.
- Kept the existing reset logic and monitoring behavior; only placement and wording were cleaned up.
