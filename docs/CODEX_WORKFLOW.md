# Codex Ticket Workflow

## Always read first
- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- the selected ticket from `docs/tickets/open/`
- the matching agent prompt file

## Required behavior
When a task is fully complete:
1. update the ticket with implementation notes
2. write a result file in `docs/agent-results/`
3. create a git commit with a clear message
4. do **not** push unless explicitly requested

## Commit rule
Use a single clear commit for the ticket when possible.

## Push rule
Push only when the user explicitly asks for it.

## Suggested prompt ending
When finished:
- update the ticket
- write the result file
- create a git commit
- include the commit hash in the result file
- do not push unless explicitly requested
