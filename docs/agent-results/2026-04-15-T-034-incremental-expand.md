# Task

Implement `T-034` by replacing full-table expand rebuilds with product-scoped incremental updates while preserving expand output and downstream delta/export behavior.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-034 – Expand inkrementell statt Full Rebuild vorbereiten.md`
- relevant result files:
  - `docs/agent-results/2026-04-15-T-033-expand-performance-diagnostics.md`
- `config/expand.php`
- `config/delta.php`
- `run_expand.php`
- `src/Service/ExpandService.php`
- `src/Service/MergeService.php`
- `src/Service/StageWriter.php`

# Changed files

- `src/Service/ExpandService.php`
- `docs/tickets/done/T-034-incremental-expand.md`
- `docs/agent-results/2026-04-15-T-034-incremental-expand.md`

# Summary

- Removed global truncate behavior from expand target handling in `ExpandService`.
- Expand now:
  - rebuilds candidate target rows per product scope
  - loads current target rows per product scope
  - compares rebuilt rows against existing rows
  - deletes and reinserts rows only for affected scopes
- Unchanged product scopes are no longer rewritten, so their target row IDs and downstream hashes remain untouched.
- Products that no longer produce rows are handled via targeted deletion, without falling back to a full rebuild.
- Preserved the existing row shape and content for both expand definitions:
  - `product_attributes_from_translations`
  - `product_media_from_articles`
- Extended expand diagnostics with:
  - `affected_products`
  - `unchanged_products`
  - `deleted_rows`

# Open points

- Expand still reads the full source tables to determine whether a product scope changed; this ticket optimizes the write path and product-scoped rebuilding, not source-side change detection persistence.
- Push was not performed because the repository workflow requires an explicit user request before pushing.

# Validation steps

- Syntax check:
  - `docker compose exec -T php php -l /app/src/Service/ExpandService.php`
- No-change validation:
  - `docker compose exec -T php php /app/run_expand.php`
  - observed latest expand context:
    - attribute definition: `affected_products = 0`, `written_rows = 0`, `insert_batches = 0`
    - media definition: `affected_products = 0`, `written_rows = 0`, `insert_batches = 0`
- Targeted product-scoped validation with expand-only execution:
  - selected sample product `68`
  - selected untouched control product `72`
  - selected language `de`
  - captured target row IDs for sample and control products in:
    - `stage_attribute_translations`
    - `stage_product_media`
  - executed expand-only no-change rerun via:
    - `docker compose exec -T php php -r '... (new ExpandService(...))->run(); ...'`
  - temporarily changed source rows for product `68`:
    - `stage_product_translations.attribute_value1 += ' [T034_TMP]'`
    - `raw_afs_articles.image_1 += '__t034tmp'`
  - executed expand-only rerun again
  - reverted both temporary source changes
  - executed expand-only rerun again
  - executed one final no-change expand-only rerun
- Final downstream validation:
  - `docker compose exec -T php php /app/run_expand.php`
  - `docker compose exec -T php php /app/run_delta.php`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT 'stage_attribute_translations', COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS('|', afs_artikel_id, sku, language_code, sort_order, attribute_name, attribute_value, source_directory))),0) FROM stage_attribute_translations UNION ALL SELECT 'stage_product_media', COUNT(*), COALESCE(SUM(CRC32(CONCAT_WS('|', media_external_id, afs_artikel_id, source_slot, file_name, path, type, document_type, sort_order, position))),0) FROM stage_product_media;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT entity_type, action, status, COUNT(*) FROM export_queue GROUP BY entity_type, action, status ORDER BY entity_type, action, status;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, context_json FROM sync_runs WHERE run_type='expand' ORDER BY id DESC LIMIT 1;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, context_json FROM sync_runs WHERE run_type='delta' ORDER BY id DESC LIMIT 1;"`

# Observed

- No-change expand-only rerun left sample and control row IDs unchanged in both target tables.
- After the temporary source change on product `68`:
  - attribute target row IDs changed for the sample product and the new value reached `stage_attribute_translations`
  - media target row IDs changed for the sample product and the new path reached `stage_product_media`
  - control product `72` kept the same target row IDs
  - expand-only stats reported:
    - attributes: `affected_products = 1`, `deleted_rows = 4`, `written_rows = 4`
    - media: `affected_products = 1`, `deleted_rows = 1`, `written_rows = 1`
- After reverting the temporary source change:
  - target content reverted correctly
  - a final no-change rerun again produced `affected_products = 0` and `written_rows = 0`
- Final stable pipeline state matched the pre-change baseline:
  - `stage_attribute_translations = 24820`, checksum aggregate `53271065191496`
  - `stage_product_media = 5331`, checksum aggregate `11474933604910`
  - queue state unchanged:
    - `product / insert / pending = 5350`
    - `media / insert / pending = 5331`
    - `document / insert / pending = 2853`
- Final `run_expand.php` context showed both expand definitions at:
  - `affected_products = 0`
  - `written_rows = 0`
  - `insert_batches = 0`
- Final `run_delta.php` context remained unchanged apart from runtime measurement and reported no new changes or errors.

# Recommended next step

If expand runtime needs to drop further, add persistent per-product source fingerprints so unchanged products can be skipped before row reconstruction, not only before delete/reinsert.

# Commit

- Pending
