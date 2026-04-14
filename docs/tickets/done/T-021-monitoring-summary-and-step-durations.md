# Ticket: T-021

## Status
done

## Title
Add monitoring summaries and step duration visibility

## Problem
Monitoring pages show raw runs, logs, and errors, but it is still hard to judge pipeline health and bottlenecks at a glance.

## Goal
Provide better visibility into recent run outcomes and step durations using the existing monitoring data.

## Scope
- add concise monitoring summaries for recent runs
- expose useful duration or timing information where available
- improve visibility of failed versus successful recent activity
- keep the monitoring model based on `sync_runs`, `sync_logs`, and `sync_errors`

## Acceptance Criteria
- [ ] admins can see recent pipeline health more quickly
- [ ] run duration or step timing is visible where the data exists
- [ ] the UI remains usable without a full monitoring redesign
- [ ] no new external monitoring dependency is introduced

## Files / Areas
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/View/sync-runs/index.php`
- related monitoring views if needed

## Notes
Prefer small high-signal additions over broad dashboard changes.

## Implementation Notes
- Added monitoring summary metrics for running runs, successful runs in the last 24 hours, failed runs in the last 24 hours, and average run duration in the last 24 hours.
- Extended run queries to expose `duration_seconds` directly from `sync_runs`.
- Updated `/sync-runs` to show compact summary cards plus a duration column in the run table.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
