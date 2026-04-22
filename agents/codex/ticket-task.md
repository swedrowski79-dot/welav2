## Ticket Handling

If a task references a ticket ID (e.g. T-001):
- load the ticket from docs/tickets/open/
- treat the ticket as the full specification
- do not require additional prompt details
- follow all requirements and acceptance criteria in the ticket

---

## Markdown Context Rules

Before implementing any ticket, load and consider all relevant markdown files.

Always read:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- docs/CODEX_WORKFLOW.md if it exists
- the assigned ticket file in docs/tickets/open/
- relevant files in docs/agent-results/

Also read any other relevant .md files directly related to the task.

Rules:
- Treat the assigned ticket as the primary specification
- Treat AGENTS.md and copilot-instructions as global rules
- Treat PROJECT_CONTEXT.md as project context
- Treat recent result files as implementation history
- Do not start code changes before reading required markdown files
- Ignore unrelated or outdated markdown files

---

ROLE: Senior Codex implementation agent for this repository

---

RULES:

- Work strictly ticket-driven
- Keep changes incremental and repository-consistent
- Do not redesign unrelated parts
- Reuse existing services, controllers, repositories and monitoring
- Do not modify importer, delta or database logic unless explicitly required by the ticket

---

WHEN TASK IS COMPLETE:

1. Update the ticket file:
   - mark status as "done"
   - add short implementation notes if useful

2. Move the ticket:
   - from docs/tickets/open/
   - to docs/tickets/done/

3. Write a result file:
   - docs/agent-results/YYYY-MM-DD-[TICKET-ID]-[short-name].md

4. Create a git commit:
   - format: [T-XXX] short description

5. Push to current branch

---

DO NOT PUSH IF:
- task is incomplete
- errors are still present

---

OUTPUT FORMAT:

1. Changed files
2. Summary
3. Open points
4. Validation steps
5. Commit hash
