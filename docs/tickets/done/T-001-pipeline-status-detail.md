# Ticket: T-001

## Status
done

## Title
Improve pipeline status detail in admin UI

## Problem
The current `/pipeline` page shows only a very small amount of run information.
During manual tests it is not yet easy enough to understand what is happening.

## Goal
Make the pipeline status more useful during long-running operations.

## Scope
- show more detailed current status
- show recent log lines directly on `/pipeline`
- optionally add auto-refresh or polling
- keep the current UI structure simple

## Acceptance Criteria
- [x] `/pipeline` shows more than `running/idle`
- [x] current or last sub-step is visible
- [x] recent logs are visible on the same page
- [x] the page stays usable during long-running actions

## Files / Areas
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/View/pipeline/index.php`

## Notes
This is a UX and monitoring improvement ticket, not a full redesign.

## Implementation Notes
- Added a dedicated status block on `/pipeline` using `sync_runs` and `sync_errors`.
- Added the last 10 log lines of the active or most recent pipeline run directly on `/pipeline`.
- Reused `MonitoringRepository` and existing monitoring tables only.
