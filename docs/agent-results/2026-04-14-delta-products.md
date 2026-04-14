# Task

Implement a product delta system and export queue after the `expand` step without adding XT write logic.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/backend-task.md`
- `README.md`
- `database.sql`
- `config/admin.php`
- `config/delta.php`
- `config/expand.php`
- `config/merge.php`
- `config/pipeline.php`
- `config/sources.php`
- `run_merge.php`
- `run_expand.php`
- `src/Database/ConnectionFactory.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Service/ExpandService.php`
- `src/Service/MergeService.php`
- `src/Service/StageWriter.php`
- `src/Web/Repository/SyncLauncher.php`
- `docker-compose.yml`

# Changed files

- `database.sql`
- `config/admin.php`
- `config/delta.php`
- `config/merge.php`
- `run_expand.php`
- `run_delta.php`
- `src/Service/MergeService.php`
- `src/Service/ProductDeltaService.php`

# Summary

- Extended `stage_products` with `hash` and `last_exported_hash`.
- Added `export_queue` with `entity_type`, `entity_id`, `action`, `payload`, `status`, and `created_at`.
- Preserved `last_exported_hash` across the `merge` step so delta comparison survives the existing `TRUNCATE`-based rebuild of `stage_products`.
- Added `ProductDeltaService` that:
  - builds a SHA-256 hash from relevant product fields, translations, and expanded attributes
  - writes the current hash back to `stage_products.hash`
  - creates `pending` queue entries for `insert` or `update` when `hash != last_exported_hash`
  - detects deleted products from previously `done` export history and enqueues `delete`
  - logs per-record errors through the existing monitoring tables
- Integrated product delta execution directly into `run_expand.php`.
- Added `run_delta.php` for a dedicated CLI delta run.
- Exposed `export_queue` in the stage browser config.

# SQL changes

```sql
ALTER TABLE stage_products
    ADD COLUMN hash VARCHAR(64) NULL,
    ADD COLUMN last_exported_hash VARCHAR(64) NULL,
    ADD KEY idx_stage_products_hash (hash);

CREATE TABLE export_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    action VARCHAR(20) NOT NULL,
    payload JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    KEY idx_export_queue_entity (entity_type, entity_id),
    KEY idx_export_queue_status (status),
    KEY idx_export_queue_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

# Open points

- `last_exported_hash` is now preserved and compared, but a future export worker still needs to set `stage_products.last_exported_hash` after a successful export by marking the relevant queue entry as `done`.
- Delete detection is intentionally based on previously `done` queue entries. Products that were never successfully exported are not treated as deletions.
- I did not wire a separate admin button for `run_delta.php`; the delta is already executed automatically after `run_expand.php`.

# Validation steps

- Executed container-based syntax checks:
  - `docker compose exec php php -l /app/src/Service/ProductDeltaService.php`
  - `docker compose exec php php -l /app/src/Service/MergeService.php`
  - `docker compose exec php php -l /app/run_expand.php`
  - `docker compose exec php php -l /app/run_delta.php`
- Not executed:
  - full schema import
  - `run_merge.php`
  - `run_expand.php`
  - `run_delta.php`

# Recommended next step

Apply the schema changes to MySQL, run `merge` and `expand`, then implement the export worker that consumes `export_queue`, writes to the external target, and updates `last_exported_hash` plus queue `status`.
