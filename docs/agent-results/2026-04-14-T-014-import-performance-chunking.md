# Task

Implement Ticket `T-014` by improving import throughput with batched writes and more selective source reads.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-014-import-performance-chunking.md`
- relevant recent import and numeric-fix result files
- `config/sources.php`
- `src/Importer/AfsImporter.php`
- `src/Importer/ExtraImporter.php`
- `src/Service/StageWriter.php`
- `src/Service/ImportWorkflow.php`
- `run_import_all.php`

# Changed files

- `config/sources.php`
- `src/Importer/AfsImporter.php`
- `src/Importer/ExtraImporter.php`
- `src/Service/StageWriter.php`
- `docs/tickets/done/T-014-import-performance-chunking.md`
- `docs/agent-results/2026-04-14-T-014-import-performance-chunking.md`

# Summary

- Added configurable import write batch sizes for AFS and Extra sources.
- Reworked both importers to normalize rows into batches and flush them through `StageWriter::insertMany()` instead of doing one insert per row.
- Added statement caching for repeated single-row inserts and a multi-row insert path for batch writes.
- Removed `SELECT *` from `ExtraImporter` and aligned it with the config-driven column selection already used for AFS.

# Open points

- No real import run against MSSQL and SQLite sources was executed, so the runtime improvement is code-level only at this stage.
- The chosen default batch size is conservative; it may still be worth tuning per environment after one measured import run.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/src/Service/StageWriter.php`
  - `docker compose exec -T php php -l /app/src/Importer/AfsImporter.php`
  - `docker compose exec -T php php -l /app/src/Importer/ExtraImporter.php`
  - `docker compose exec -T php php -l /app/config/sources.php`
  - `docker compose exec -T php php -l /app/run_import_all.php`
- Not executed:
  - `docker compose exec -T php php /app/run_import_all.php`

# Recommended next step

Run the full import once and compare runtime before and after the batching change to confirm that import time drops without affecting raw row counts.
