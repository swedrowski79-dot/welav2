# Codex Start Prompts by Ticket

## Generic prompt
```text
Read first:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- docs/CODEX_WORKFLOW.md
- agents/codex/ticket-task.md
- docs/tickets/open/T-XXX-<slug>.md

Task:
Implement the assigned ticket completely.

Requirements:
- keep changes incremental
- update the ticket when finished
- write a result file in docs/agent-results/
- create a git commit with a clear message
- include the commit hash in the result file
- do not push unless explicitly requested
```

## Example
```text
Read first:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- docs/CODEX_WORKFLOW.md
- agents/codex/ticket-task.md
- docs/tickets/open/T-005-afs-numeric-import-sanitizing.md

Task:
Implement the assigned ticket completely.

Requirements:
- keep changes incremental
- update the ticket when finished
- write a result file in docs/agent-results/
- create a git commit with a clear message
- include the commit hash in the result file
- do not push unless explicitly requested
```
