# Task

Fix the product delta system so missing products are never treated as deletes, and move persistent delta state into a dedicated `product_export_state` table.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `agents/codex/review-with-subagents.md`
- `docs/agent-results/2026-04-14-delta-products.md`
- `database.sql`
- `config/admin.php`
- `config/delta.php`
- `config/merge.php`
- `run_expand.php`
- `run_delta.php`
- `src/Service/MergeService.php`
- `src/Service/ProductDeltaService.php`

# Changed files

- `database.sql`
- `config/admin.php`
- `config/delta.php`
- `config/merge.php`
- `src/Service/MergeService.php`
- `src/Service/ProductDeltaService.php`

# Summary

- Removed `last_exported_hash` from `stage_products` so delta state is no longer tied to a table that gets truncated during `merge`.
- Added `product_export_state` with `product_id`, `last_exported_hash`, and `last_seen_at`.
- Reworked `ProductDeltaService` to compare current `stage_products` hashes against `product_export_state`.
- Missing products are no longer enqueued as `delete`; they now create `update` queue entries with payload `{"online":0}`.
- New products enqueue `insert`, changed known products enqueue `update`, unchanged products are skipped.
- After each delta run, the current hash is written to `product_export_state` and `last_seen_at` is refreshed for all seen products.
- Deactivation detection is now independent from `export_queue`; queue history is no longer used as delta state.
- Removed the temporary merge preservation logic that was only needed for `stage_products.last_exported_hash`.

# Open points

- `product_export_state.last_exported_hash` now acts as the persistent delta baseline updated immediately after delta, not as confirmed export success from a downstream worker.
- Existing databases need a schema migration path if `stage_products.last_exported_hash` already exists in MySQL.
- The export worker remains out of scope and still needs to consume `export_queue`.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Service/ProductDeltaService.php`
  - `docker compose exec php php -l /app/src/Service/MergeService.php`
  - `docker compose exec php php -l /app/run_expand.php`
  - `docker compose exec php php -l /app/run_delta.php`
- Not executed:
  - schema import
  - `docker compose exec php php run_expand.php`
  - `docker compose exec php php run_delta.php`

# Recommended next step

Apply the schema change in MySQL, then run `merge` and `expand` once and inspect `product_export_state` plus `export_queue` to confirm `insert`, `update`, and offline `update` cases.
