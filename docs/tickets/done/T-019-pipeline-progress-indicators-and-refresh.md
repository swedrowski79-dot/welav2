# Ticket: T-019

## Status
done

## Title
Improve pipeline progress indicators and refresh behavior in the admin UI

## Problem
The pipeline UI shows status information, but long-running operations are still not easy enough to follow without manual page refresh and guesswork.

## Goal
Make current pipeline activity easier to understand during active runs.

## Scope
- improve progress messaging on `/pipeline`
- show clearer active-step and recent-step visibility
- add lightweight refresh behavior if needed
- keep the current PHP admin structure and monitoring tables

## Acceptance Criteria
- [ ] active runs expose clearer progress than running/idle alone
- [ ] the current or last meaningful sub-step is easy to see
- [ ] the page remains usable during long-running operations
- [ ] no heavy frontend dependency is introduced

## Files / Areas
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/View/pipeline/index.php`

## Notes
This ticket should build on the existing `/pipeline` status work rather than replace it.

## Implementation Notes
- Added lightweight auto-refresh on `/pipeline` while a run is active.
- Extended the status area with duration, latest update timestamp, run/log context, and a short progress summary.
- Added a compact progress timeline of the latest status-relevant log entries for the active or latest run.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
