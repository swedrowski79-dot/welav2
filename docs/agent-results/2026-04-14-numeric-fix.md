# Task

Fix numeric field handling for AFS import so empty strings in numeric fields do not cause MySQL decimal errors during inserts.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/backend-task.md`
- `src/Service/StageWriter.php`
- `src/Service/Normalizer.php`
- `config/normalize.php`
- `src/Importer/AfsImporter.php`
- `run_import_all.php`

# Changed files

- `src/Service/StageWriter.php`

# Summary

- Added a central normalization step in `StageWriter::insert()`.
- Before inserting into MySQL, the writer now inspects the target table schema via `SHOW COLUMNS`.
- For numeric target columns, empty strings like `""` are converted to `NULL`.
- This applies automatically to `min_qty` and the other numeric fields in article import, because the conversion happens per target column type rather than per importer field.
- Existing importer, normalizer, pipeline flow, and logging remain unchanged.

# Open points

- The fix currently converts empty numeric strings to `NULL`; it does not aggressively coerce other invalid numeric strings to `0`, because that would be riskier and could hide bad source data.
- The logic applies to all inserts through `StageWriter`, not only AFS article rows, which is intended because it is schema-driven and central.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Service/StageWriter.php`
- Not executed:
  - end-to-end `run_import_all.php`
  - live import against the AFS source that produced the original decimal error

# Recommended next step

Run the AFS import again and confirm that empty numeric source values such as `min_qty = ''` are inserted as `NULL` instead of causing MySQL `Incorrect decimal value` errors.
