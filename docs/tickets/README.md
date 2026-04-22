# Ticket Workflow

## Structure
- `open/` = active tickets
- `done/` = completed tickets
- `TEMPLATE.md` = template for new tickets

## Rules
- Every larger task gets a ticket.
- Codex should read the ticket before changing code.
- When the task is finished:
  1. update the ticket status
  2. write a result file in `docs/agent-results/`
  3. create a git commit
  4. do **not** push unless explicitly requested

## Recommended Flow
1. Pick a ticket from `docs/tickets/open/`
2. Run Codex with the ticket file + project instructions
3. Validate manually
4. Move ticket to `docs/tickets/done/` when accepted
