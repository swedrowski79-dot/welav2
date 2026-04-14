# Ticket: T-020

## Status
done

## Title
Improve pipeline and export queue UI clarity for daily admin use

## Problem
The pipeline page now has more controls, but queue details and action groupings can still feel dense during manual operations.

## Goal
Improve usability and readability of the pipeline and queue screens without rewriting the admin UI.

## Scope
- refine section wording and grouping where the current flow is still unclear
- improve queue table readability and high-signal columns
- make common operator tasks easier to scan
- preserve current endpoints and backend behavior

## Acceptance Criteria
- [ ] admins can distinguish pipeline control, status, and queue areas more quickly
- [ ] queue rows are easier to scan for status and action type
- [ ] the UI stays repository-consistent and incremental
- [ ] existing functionality continues to work unchanged

## Files / Areas
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- related web controllers or repositories only if required for display

## Notes
Keep this ticket focused on clarity and usability.
Do not merge it with backend queue processing changes.

## Implementation Notes
- Added a short operator-facing orientation block between pipeline control and queue details.
- Extended queue summary and filter handling to include `processing`.
- Reworked queue rows so retry counts, time window fields, payload, and latest error are easier to scan.
- Improved the state page with summary cards and shortened hash display.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
