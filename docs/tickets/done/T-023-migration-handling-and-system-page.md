# Ticket: T-023

## Status
done

## Title
Harden migration handling and move migration execution to config/system

## Problem
The pipeline page currently mixes operational pipeline actions with migration administration.
It can also fail when newer `export_queue` fields such as `attempt_count` are missing in older schemas.

## Goal
Keep `/pipeline` operational even on incomplete schemas and move migration execution into the config/system area.

## Scope
- make queue access on `/pipeline` safe when optional migration columns are missing
- move the `Run Migrations` action from `/pipeline` to `/status`
- show migration status on `/status`
- keep pipeline actions unchanged otherwise

## Acceptance Criteria
- [ ] `/pipeline` loads even if `export_queue.attempt_count` and related newer fields do not exist yet
- [ ] migration execution is no longer triggered from `/pipeline`
- [ ] `/status` shows pending migrations and a run action
- [ ] `/status` shows the latest migration result if available

## Files / Areas
- `public/index.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/status/index.php`

## Notes
Keep the change incremental and focused on migration handling plus safe pipeline loading.

## Implementation Notes
- Hardened `PipelineAdminRepository` so `/pipeline` can read `export_queue` safely even when newer optional columns do not exist yet.
- Moved migration execution from `PipelineController` to `StatusController` and changed the route to `/status/migrations`.
- Removed migration controls from `/pipeline` and added a migration status/action block to `/status`.
- Added latest migration result visibility based on existing migration logs and errors.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
