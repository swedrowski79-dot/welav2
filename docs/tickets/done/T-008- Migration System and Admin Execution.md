# Ticket: T-008

## Title
Introduce database migration system with admin execution

## Status
done

## Problem
Database schema updates are currently applied manually via SQL commands.

This is error-prone and not suitable for a growing system with frequent schema changes.

## Goal
Introduce a controlled migration system that allows schema updates to be executed safely and reproducibly.

## Requirements
- Create a `schema_migrations` table to track executed migrations
- Introduce a `migrations/` directory with versioned SQL files
  - e.g. 001_add_stage_products_hash.sql
- Implement a migration runner service:
  - executes only pending migrations
  - records execution in `schema_migrations`
- Add an admin UI action:
  - "Run migrations"
  - shows success or error feedback
- Log migration execution in monitoring tables

## Acceptance Criteria
- [x] Migrations can be executed via the web interface
- [x] Already executed migrations are skipped
- [x] Errors are clearly visible in the UI
- [x] Schema updates no longer require manual SQL execution

## Notes
Do not re-import full database.sql.
Only incremental, versioned migrations should be used.

## Implementation Notes
- Added `migrations/` with versioned SQL files for the current delta/export schema additions.
- Added `src/Web/Repository/MigrationRepository.php` to discover, run, and record pending migrations.
- `schema_migrations` is created automatically by the runner if it does not exist yet.
- Added a `Run Migrations` action to `/pipeline` with success/error feedback.
- Migration execution writes audit entries to `sync_logs` and records failures in `sync_errors` when available.
