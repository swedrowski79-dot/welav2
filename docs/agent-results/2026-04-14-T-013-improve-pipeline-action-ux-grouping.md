# Task

Implement Ticket `T-013` by grouping pipeline actions more clearly on `/pipeline`.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-013 — Improve pipeline action UX grouping.md`
- `docs/agent-results/2026-04-14-T-001-pipeline-status.md`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`

# Changed files

- `src/Web/View/pipeline/index.php`
- `docs/tickets/done/T-013 — Improve pipeline action UX grouping.md`
- `docs/agent-results/2026-04-14-T-013-improve-pipeline-action-ux-grouping.md`

# Summary

- Reworked the pipeline action area into four visually separated sections:
  - Import
  - Processing
  - Delta
  - Full Pipeline
- Added short section descriptions and one-line button descriptions to make the flow understandable without prior project knowledge.
- Kept all existing actions and endpoints unchanged.

# Open points

- No manual browser review was executed to judge the final readability on desktop and mobile.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - manual browser test on `/pipeline`

# Recommended next step

Open `/pipeline` in the browser and verify that a non-technical user can identify the correct section and action without needing repository knowledge.
