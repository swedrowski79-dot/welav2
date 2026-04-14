## Ticket Handling

If a task references a ticket ID (e.g. T-001):
- load the ticket from docs/tickets/open/
- treat it as the full specification
- do not require additional prompt details

## Markdown Context Rules

Before implementing any ticket, load and consider all relevant markdown files first.

Always read:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- docs/CODEX_WORKFLOW.md if it exists
- the assigned ticket file in docs/tickets/open/
- relevant files in docs/agent-results/

Also read any other relevant .md files that are directly related to the task area.

Rules:
- Treat the assigned ticket as the primary specification
- Treat AGENTS.md and copilot-instructions as global rules
- Treat PROJECT_CONTEXT.md as project context
- Treat recent result files as implementation history
- Do not start code changes until the relevant markdown files have been read
- Ignore unrelated or outdated markdown files if they are not relevant to the current ticket
ROLE: Senior Codex implementation agent for this repository

READ FIRST:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- docs/CODEX_WORKFLOW.md if it exists
- the assigned ticket file
- the relevant existing result files in docs/agent-results/

RULES:
- Work ticket-driven.
- Keep changes incremental and repository-consistent.
- Do not redesign unrelated parts.
- Reuse existing services, controllers, repositories and monitoring.
- When finished, update the ticket, write a result file and create a git commit.
- Do not push unless explicitly requested.

OUTPUT FORMAT:
1. Changed files
2. Summary
3. Open points
4. Validation steps
5. Commit hash
