# Ticket: T-005

## Status
done

## Title
Sanitize empty numeric values from AFS import

## Problem
AFS sends empty strings for numeric fields such as `min_qty`.
MySQL rejects these values for numeric columns.

## Goal
Convert invalid or empty numeric values centrally before writing stage/raw rows.

## Scope
- sanitize numeric fields from AFS import
- convert empty string to `NULL` or a valid numeric fallback
- keep logging intact
- implement centrally where possible

## Acceptance Criteria
- [x] import no longer fails on empty numeric values
- [x] `min_qty` is handled safely
- [x] other numeric article fields are handled consistently
- [x] no fragile one-off fixes per field unless necessary

## Files / Areas
- `src/Importer/AfsImporter.php`
- `src/Service/StageWriter.php`
- related config / normalization areas

## Notes
Triggered by SQL error 1366 on `min_qty`.

## Implementation Notes
- Implemented centrally in `src/Service/StageWriter.php`.
- `StageWriter::insert()` now normalizes row data based on the target MySQL table schema before executing inserts.
- Empty strings in numeric MySQL columns are converted to `NULL`, which covers `min_qty` and the other numeric article fields without per-field importer patches.
- Importer structure, logging, and pipeline order remain unchanged.
- Original code implementation commit: `8c549d2` (`fix(import): handle empty numeric values from AFS to prevent SQL decimal errors`)
