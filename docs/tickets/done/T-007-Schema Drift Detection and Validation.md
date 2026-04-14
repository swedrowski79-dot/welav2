# Ticket: T-007

## Title
Schema drift detection and validation for stage and delta tables

## Status
done

## Problem
The application logic depends on specific database columns (e.g. stage_products.hash),
but schema updates are currently applied manually.

This can lead to runtime errors such as:
- "Unknown column 'hash' in field list"

There is currently no mechanism to detect missing schema elements.

## Goal
Ensure that required schema changes are always present and detectable.

## Requirements
- Define required tables and columns for:
  - stage_products
  - product_export_state
  - export_queue
- Add a validation mechanism that:
  - checks if required columns exist
  - detects missing tables
- Provide a clear error message in the admin UI if schema is incomplete
- Do not block the entire application, but clearly indicate issues

## Acceptance Criteria
- [x] Missing columns like `hash` are detected automatically
- [x] Admin UI shows a visible warning if schema is incomplete
- [x] No silent failures due to missing schema
- [x] Works without requiring manual DB inspection

## Notes
This is a safety layer to prevent runtime failures due to missing migrations.

## Implementation Notes
- Added `src/Web/Repository/SchemaHealthRepository.php` as a focused schema validation layer for the stage/delta-related tables.
- The validator checks for required tables and columns in `stage_products`, `product_export_state`, and `export_queue`.
- `/pipeline` now renders a visible warning banner when required schema elements are missing.
- The UI remains usable; the warning is informational and does not block the application.
