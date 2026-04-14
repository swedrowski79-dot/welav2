# Task

Implement Ticket `T-005` by ensuring empty numeric values from AFS are sanitized centrally before MySQL stage inserts.

# Changed files

- `docs/tickets/open/T-005-afs-numeric-import-sanitizing.md`
- `docs/agent-results/2026-04-14-T-005-afs-numeric-import-sanitizing.md`

# Summary

- The required numeric sanitizing fix is already present in `src/Service/StageWriter.php`.
- `StageWriter::insert()` normalizes incoming row data against the target table schema.
- For numeric MySQL columns, empty string values are converted to `NULL` before insert.
- This covers `min_qty` and the other numeric article fields centrally, without adding brittle field-specific handling in the importer.
- `T-005` was marked as complete and linked to the implementation commit below.

# Open points

- The sanitizing logic converts empty numeric strings to `NULL`; it does not coerce arbitrary invalid numeric strings to `0`, to avoid hiding source data issues.
- End-to-end validation against the original failing AFS record was not executed in this ticket-close step.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Service/StageWriter.php`
- Reviewed:
  - `src/Service/StageWriter.php`
  - `docs/agent-results/2026-04-14-numeric-fix.md`
- Not executed:
  - `php run_import_all.php`
  - live AFS import against the original failing source row

# Commit hash

- Original implementation commit: `8c549d2`
