# AGENTS.md

This repository uses a **Codex-first orchestration workflow**.
The human owner coordinates tasks with ChatGPT, then executes them in Codex.
Codex may use subagents for larger tasks, but only when explicitly instructed.

## Read first order
For any non-trivial task, read files in this order:
1. `AGENTS.md`
2. `.github/copilot-instructions.md`
3. `PROJECT_CONTEXT.md`
4. `README.md`
5. `database.sql`
6. Relevant files under `config/`, `src/`, `public/`, and `run_*.php`

## Architecture contract
This project is a staged sync pipeline:

`import -> merge -> expand -> delta -> export queue`

Current production-safe principles:
- Keep source imports isolated from stage logic
- Keep stage tables as the internal source of truth
- Prefer config-driven behavior over hard-coded branching
- Preserve monitoring through `sync_runs`, `sync_logs`, and `sync_errors`
- Prefer minimal, repository-consistent changes over large rewrites

## Hard rules
- Do not connect directly to XT shop databases
- Do not add direct XT write logic
- Do not add outbound HTTP/API write logic for XT sync work unless the task explicitly targets `wela-api/`
- Do not replace the current CLI pipeline structure
- Do not rewrite the web UI from scratch
- Do not introduce Composer, frameworks, or heavy dependencies unless explicitly requested
- Do not change unrelated files just to make code style consistent

## Change boundaries
Default to incremental changes only.
Before editing, identify:
- exact files to change
- files to leave untouched
- validation steps to run after changes

When uncertain, prefer:
- new focused service/helper file
- minimal changes to existing runner scripts
- config additions instead of branch-heavy code

## Result file rule
After every meaningful task, always create or update one file in:

`docs/agent-results/`

Suggested filename pattern:
- `YYYY-MM-DD-topic.md`

Each result file must contain:
- Task
- Files read
- Changed files
- Summary
- Open points
- Validation steps
- Recommended next step

## When to use subagents
Use **no subagents** for:
- small bug fixes
- one-file changes
- simple UI text/layout changes
- straightforward config updates

Use **subagents** only for tasks that split cleanly, for example:
- backend + database + UI in one feature
- broad debugging across multiple layers
- architecture review plus implementation
- code change plus documentation/report generation

## Allowed subagent roles
### 1. backend-analyst
Focus:
- CLI pipeline
- services under `src/Service/`
- DB schema in `database.sql`
- config under `config/`

Output:
- findings
- affected files
- implementation plan
- risks

### 2. ui-analyst
Focus:
- `public/index.php`
- `src/Web/`
- admin usability
- table/status/dashboard rendering

Output:
- UI impact
- changed files
- implementation notes
- validation checklist

### 3. result-writer
Focus:
- produce `docs/agent-results/*.md`
- summarize what changed and what remains open

Output:
- one concise markdown report only

## Subagent operating model
If subagents are used:
- keep each subagent scoped to a narrow task
- do not give every subagent the full repository mission statement
- require each subagent to return structured findings
- main Codex agent integrates results and applies final changes

## Preferred final answer format from Codex
1. Files read
2. Plan
3. Changed files
4. Code changes
5. Validation steps
6. Result report path

## Validation mindset
Because the repo has no formal test suite, validate with repository-native checks when relevant:
- `docker compose up -d --build`
- `docker compose exec php php run_import_all.php`
- `docker compose exec php php run_merge.php`
- `docker compose exec php php run_expand.php`
- manual admin UI check at `http://localhost:8080`

Do not claim validation succeeded unless it was actually run.

## Ticket Workflow

All development tasks are defined as tickets in:
docs/tickets/open/

When a task is requested like:
"Implement T-001"

You must:
1. Load all relevant markdown files first:
   - `AGENTS.md`
   - `.github/copilot-instructions.md`
   - `PROJECT_CONTEXT.md`
   - `docs/CODEX_WORKFLOW.md` if it exists
   - the assigned ticket in `docs/tickets/open/`
   - relevant files in `docs/agent-results/`
   - other directly task-relevant `.md` files
2. Follow all requirements
3. Apply changes only in relevant areas
4. Write result file in docs/agent-results/
5. Update the ticket
6. Commit and push changes

Never ignore ticket requirements.

## Documentation Priority

Before implementing a ticket, load the relevant markdown documentation first.

Priority order:
1. AGENTS.md
2. .github/copilot-instructions.md
3. PROJECT_CONTEXT.md
4. docs/CODEX_WORKFLOW.md
5. assigned ticket in docs/tickets/open/
6. relevant files in docs/agent-results/
7. other task-relevant markdown files

Do not start implementation until the relevant markdown files have been read.
