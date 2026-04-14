# Ticket: T-022

## Status
done

## Title
Add stage data consistency checks and validation reporting

## Problem
The pipeline now builds stage, delta, and export state data, but there is limited visibility into cross-table consistency problems such as missing translations, orphaned attributes, or incomplete stage records.

## Goal
Detect and surface the most important stage data consistency problems before export processing relies on bad data.

## Scope
- define a focused set of consistency checks for stage and export-related tables
- surface findings in a repository-consistent admin view or warning area
- keep validation informative rather than destructive
- reuse existing monitoring or admin patterns where practical

## Acceptance Criteria
- [ ] common consistency problems are detected automatically
- [ ] admins can see validation findings without manual SQL inspection
- [ ] the checks do not block the entire application
- [ ] implementation stays incremental and stage-focused

## Files / Areas
- validation service or repository under `src/`
- relevant admin controller or view for displaying findings
- `stage_products`
- `stage_product_translations`
- `stage_categories`
- `stage_category_translations`
- `stage_attribute_translations`
- export-related state tables if needed

## Notes
Keep the first version focused on a small set of high-value checks.

## Implementation Notes
- Added `StageConsistencyRepository` with focused stage/export consistency checks.
- The first check set covers:
  - products without translations
  - product translations without parent product
  - categories without translations
  - attribute rows without matching product translation
  - export state rows without current stage product
- `/pipeline` now shows a visible non-blocking warning section with counts and sample identifiers.

## Result
To be filled by Codex:
- changed files
- summary
- validation
- commit hash
