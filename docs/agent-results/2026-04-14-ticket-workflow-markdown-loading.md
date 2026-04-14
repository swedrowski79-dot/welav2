# Task

Update the Codex ticket workflow so all relevant markdown files are explicitly loaded before ticket implementation starts.

# Files read

- `AGENTS.md`
- `agents/codex/ticket-task.md`

# Changed files

- `AGENTS.md`
- `agents/codex/ticket-task.md`
- `docs/agent-results/2026-04-14-ticket-workflow-markdown-loading.md`

# Summary

- Tightened the global ticket workflow in `AGENTS.md` so ticket work now explicitly requires loading all relevant markdown context before implementation.
- Simplified `agents/codex/ticket-task.md` to the same rule set, including a hard stop on code changes until the markdown context has been read.
- Kept the rules short and limited the change to workflow documentation only.

# Open points

- None.

# Validation steps

- Reviewed both workflow files after editing for consistency.

# Recommended next step

Use the updated ticket workflow wording as the standard for future ticket-driven tasks.
