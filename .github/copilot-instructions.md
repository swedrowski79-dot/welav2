# Repository Instructions for Codex and Chat-Based Agents

This file remains in `.github/` because several coding agents automatically look there for repository guidance.
Use it together with `AGENTS.md` and `PROJECT_CONTEXT.md`.

## Read first
Before changing code, read in this order:
- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- relevant files under `config/`, `src/`, `public/`, and `run_*.php`

## Build, run, and validation commands
- Start the stack: `docker compose up -d --build` or `make up`
- Open the admin UI: `http://localhost:8080`
- Import schema: `docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql` or `make schema-import`
- Run import: `docker compose exec php php run_import_all.php` or `make import-all`
- Run merge: `docker compose exec php php run_merge.php` or `make merge`
- Run expand: `docker compose exec php php run_expand.php` or `make expand`
- Runtime checks:
  - `docker compose exec php php -v`
  - `docker compose exec php php -m | grep -E 'pdo_mysql|pdo_sqlite|pdo_sqlsrv|sqlsrv'`

There is no checked-in automated test suite or linter configuration. Do not invent test commands.

## Architecture summary
This repository is a Dockerized PHP 8.2 sync application that imports product and category data from:
- AFS MSSQL
- Extra SQLite

The core pipeline is:
1. `run_import_all.php`
2. `run_merge.php`
3. `run_expand.php`
4. future delta step
5. future export queue step

Important layers:
- `raw_*` tables = source-normalized imports
- `stage_*` tables = merged internal truth
- `sync_runs`, `sync_logs`, `sync_errors` = monitoring

## Repository-specific rules
- Prefer config-driven changes through `config/*.php`
- The CLI ETL code is not Composer-based; manually wire new non-web classes where needed
- Match the style of the area you edit:
  - web layer uses `declare(strict_types=1);` and namespaces
  - ETL/service layer is mostly non-namespaced procedural wiring with class files
- Preserve monitoring and run status handling
- Keep language handling limited to `de`, `en`, `fr`, `nl` unless explicitly requested otherwise
- Only expose tables in admin UI if they are listed in `config/admin.php`

## Change boundaries
- Do not rewrite the whole architecture
- Do not replace the current CLI entrypoint layout
- Do not add direct XT write behavior
- Do not add unnecessary dependencies
- Prefer incremental repository-consistent changes
- Preserve current admin UI behavior unless the task explicitly requests UI changes

## Codex operating mode
Default mode:
- one main Codex agent
- no subagents for small tasks

Use subagents only when the task clearly benefits from parallel or separated analysis, such as:
- backend + UI + database work in one task
- broad debugging across multiple directories
- implementation plus structured result reporting

## Result report requirement
After each meaningful task, create a markdown report in:
- `docs/agent-results/`

The report must include:
- Task
- Files read
- Changed files
- Summary
- Open points
- Validation steps
- Recommended next step

## Output contract for agents
Respond in this order:
1. Files read
2. Plan
3. Changed files
4. Implementation
5. Validation
6. Result report path

## XT rule
For sync work:
- do not connect directly to XT database
- do not implement XT HTTP write calls
- only prepare stage, delta, queue, or mirror data unless the task explicitly targets `wela-api/`
