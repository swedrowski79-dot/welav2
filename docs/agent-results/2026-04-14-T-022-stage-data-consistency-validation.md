# Task

Implement Ticket `T-022` by adding focused stage data consistency checks and visible validation reporting.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-022-stage-data-consistency-validation.md`
- relevant schema and pipeline result files
- `src/Web/Repository/SchemaHealthRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`

# Changed files

- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `docs/tickets/done/T-022-stage-data-consistency-validation.md`
- `docs/agent-results/2026-04-14-T-022-stage-data-consistency-validation.md`

# Summary

- Added a focused repository for non-blocking consistency validation of stage and export-related tables.
- Implemented high-value checks for missing translations, orphaned translation/attribute rows, and stale export-state rows.
- Reused the existing `/pipeline` admin page to surface validation findings with:
  - affected row counts
  - severity
  - short descriptions
  - sample identifiers
- Kept the validation informational only; no processing path is blocked.

# Open points

- The checks currently focus on a first high-signal set and do not yet cover every possible cross-table integrity rule.
- No manual browser review was executed to verify final readability of the new consistency section.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Web/Repository/StageConsistencyRepository.php`
  - `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec -T php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - manual browser check on `/pipeline`
  - live validation against a deliberately inconsistent dataset

# Recommended next step

Open `/pipeline` against a populated database and verify that the new consistency block stays readable and highlights the most important data issues without overwhelming the operator.
