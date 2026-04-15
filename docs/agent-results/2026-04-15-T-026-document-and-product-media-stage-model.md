# Task

Implement ticket `T-026` by adding the missing stage tables for product-linked documents and product media/images without implementing merge or expand logic yet.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-026-document-and-product-media-stage-model.md`
- `docs/tickets/open/T-027-document-media-merge-expand-pipeline-wiring.md`
- `docs/agent-results/2026-04-15-document-image-gap-analysis.md`
- `docs/agent-results/2026-04-15-T-025-afs-document-raw-import-and-normalization.md`
- `config/xt_write.php`
- `docs/IMPLEMENTATION_NOTES.md`
- existing migration files in `migrations/`

# Changed files

- `database.sql`
- `migrations/007_create_stage_product_media_and_documents.sql`
- `docs/tickets/done/T-026-document-and-product-media-stage-model.md`
- `docs/agent-results/2026-04-15-T-026-document-and-product-media-stage-model.md`

# Summary

- Added `stage_product_documents` as the stage table for product-linked document/file rows.
- Added `stage_product_media` as the stage table for product-linked images/media rows.
- Both tables use `afs_artikel_id` as the product-side relation, matching the current stage/product identity model.
- `stage_product_documents` uses `afs_document_id` as the source-side document identity for downstream XT media export.
- `stage_product_media` includes `media_external_id` as the future stable media identity for XT media export.
- Both tables model `file_name`, `path`, `document_type`, and `sort_order`.
- Added `stage_product_documents.title` for the normalized human-readable AFS `Titel` value.
- Added `stage_product_documents.source_path` so the original technical source path can remain separate from `title` and `file_name`.
- Added `position` to both tables to stay compatible with the already existing `config/xt_write.php` references to `stage.position`.
- Updated the raw document normalization so `Titel` is normalized into `raw_afs_documents.title` via basename stripping and is no longer treated like a technical filename.
- No merge, expand, delta, or XT write logic was added.

# Open points

- `stage_product_media.media_external_id` is only a reserved identity field at this stage; its generation strategy belongs to `T-027`.
- `stage_product_media.type` is modeled for compatibility with `config/xt_write.php`, but population rules are intentionally deferred.
- `product_id` was not added because the current stage schema consistently uses source-side AFS identity (`afs_artikel_id`) before downstream resolution.
- No population logic exists yet for either table; this ticket only establishes the stage schema and identity model.
- `raw_afs_documents.path` still stores the current technical source path from `Dateiname`; stage-level path/source-path usage will be finalized in `T-027`.
- Existing databases keep the old `raw_afs_documents.name` column as a legacy field from `T-025`; fresh installs use `title` from `database.sql`.

# Validation steps

- Executed:
  - `curl -s -o /tmp/t026_migrations.out -w "%{http_code}" -X POST http://localhost:8080/status/migrations`
  - `docker compose exec -T php php -l /app/config/normalize.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
  - `docker compose exec -T php php /app/run_import_products.php`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SHOW TABLES LIKE 'stage_product_media';"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SHOW TABLES LIKE 'stage_product_documents';"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SHOW COLUMNS FROM stage_product_media;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SHOW COLUMNS FROM stage_product_documents;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SHOW COLUMNS FROM raw_afs_documents;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT afs_document_id, title, file_name, path FROM raw_afs_documents ORDER BY id DESC LIMIT 5;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT COUNT(*) FROM raw_afs_documents WHERE LOCATE('/', title) > 0 OR LOCATE(CHAR(92), title) > 0;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT version FROM schema_migrations WHERE version = '007_create_stage_product_media_and_documents';"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT version FROM schema_migrations WHERE version = '008_add_document_title_fields';"`
- Observed:
  - migration endpoint returned HTTP `302`
  - both new stage tables exist in MySQL
  - both tables expose the modeled identity and metadata columns
  - migration `007_create_stage_product_media_and_documents` is recorded in `schema_migrations`
  - `raw_afs_documents` now exposes `title`
  - imported document titles are normalized to basename-only values
  - sample rows show `title = 'Produktinfo RVA_ATEX.pdf'` while `file_name = '20250813__7.pdf'` and `path = 'ARCHIV\\BRIEFE\\20250813__7.pdf'`
  - imported document titles with remaining `/` or `\\` prefixes: `0`
  - migration `008_add_document_title_fields` is recorded in `schema_migrations`

# Recommended next step

Implement `T-027` to wire `raw_afs_documents` and future product image/media source rows into `stage_product_documents` and `stage_product_media`.
