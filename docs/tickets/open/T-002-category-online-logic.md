# Ticket: T-002

## Status
done

## Title
Implement category online inversion logic from AFS

## Problem
For AFS categories the source semantics are inverted:
`internet = 0` means the category is online.

## Goal
Import only relevant AFS category rows and normalize them to the internal
field `online = 1`.

## Scope
- load only AFS categories with `internet = 0`
- normalize to internal `online = 1`
- keep downstream pipeline based only on internal `online`

## Acceptance Criteria
- [x] AFS category import filters by `internet = 0`
- [x] internal normalized category field is `online = 1`
- [x] downstream stages no longer depend on raw AFS internet semantics
- [x] implementation is config-driven and repository-consistent

## Files / Areas
- `config/sources.php`
- `config/normalize.php`
- category importer / merge / normalize code

## Notes
The real AFS source name is `Warengruppe`, not `Warengruppen`.

## Implementation Notes
- Added an AFS category source filter in `config/sources.php` so only rows with `Internet = 0` are imported.
- Kept the category source configurable through the existing `AFS_CATEGORIES_TABLE` config path.
- Added category-specific normalization in `config/normalize.php` and `src/Service/Normalizer.php` so imported AFS categories always store internal `online_flag = 1`.
- Downstream merge stays unchanged and now consumes the normalized internal value instead of depending on raw AFS semantics.
