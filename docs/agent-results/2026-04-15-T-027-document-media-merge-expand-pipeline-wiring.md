# Task

Implement ticket `T-027` by wiring product-linked document and media rows from raw stage inputs into `stage_product_documents` and `stage_product_media`.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-027-document-media-merge-expand-pipeline-wiring.md`
- `docs/agent-results/2026-04-15-document-image-gap-analysis.md`
- `docs/agent-results/2026-04-15-T-024-image-path-normalization.md`
- `docs/agent-results/2026-04-15-T-025-afs-document-raw-import-and-normalization.md`
- `docs/agent-results/2026-04-15-T-026-document-and-product-media-stage-model.md`
- `docs/IMPLEMENTATION_NOTES.md`
- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `config/expand.php`
- `config/pipeline.php`
- `config/xt_write.php`
- `run_merge.php`
- `run_expand.php`
- `src/Importer/AfsImporter.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Service/Normalizer.php`
- `src/Service/StageWriter.php`
- `src/Service/MergeService.php`
- `src/Service/ExpandService.php`
- `src/Service/ImportWorkflow.php`
- `src/Web/Repository/MigrationRepository.php`
- `migrations/007_create_stage_product_media_and_documents.sql`
- `migrations/008_add_document_title_fields.sql`

# Changed files

- `database.sql`
- `migrations/009_add_raw_article_image_slots.sql`
- `config/normalize.php`
- `config/merge.php`
- `config/expand.php`
- `config/pipeline.php`
- `src/Service/MergeService.php`
- `src/Service/ExpandService.php`
- `run_merge.php`
- `run_expand.php`
- `src/Web/Repository/MigrationRepository.php`
- `docs/tickets/done/T-027-document-media-merge-expand-pipeline-wiring.md`
- `docs/agent-results/2026-04-15-T-027-document-media-merge-expand-pipeline-wiring.md`

# Summary

- Added raw article image slot persistence (`image_1` .. `image_10`) so the already selected AFS `Bild1..Bild10` values now survive import into `raw_afs_articles`.
- Added migration `009_add_raw_article_image_slots` and skip handling so existing databases and fresh schema imports both remain compatible.
- Extended `MergeService` to execute all configured merge definitions and added `required_fields` support for stage rows that must be complete before insertion.
- Wired `raw_afs_documents` into `stage_product_documents` with explicit `afs_artikel_id` linkage.
- Mapped document fields so:
  - `title` comes from normalized AFS `Titel`
  - `file_name` comes from the normalized basename of AFS `Dateiname`
  - `path` is the normalized stage-side filename/path value used downstream
  - `source_path` preserves the original technical AFS path
  - `document_type` comes from AFS `Art`
  - `sort_order` and `position` fall back to `afs_document_id` when the source provides no explicit sort value
- Extended `ExpandService` with a focused `media_slots` mode and wired `raw_afs_articles.image_1..image_10` into `stage_product_media`.
- `stage_product_media` now carries stable `media_external_id` values (`afs-article-{afs_artikel_id}-{slot}`), product linkage, source slot metadata, normalized filenames, original source paths, and slot-based positions.
- Updated merge/expand monitoring totals so the new stage tables are included in run metrics.
- Updated the conceptual pipeline order so document rows are represented as a merge step and image/media rows remain part of expand.
- Did not implement XT export/write logic.

# Open points

- No category-linked media source relation is configured in the current AFS source setup, so media handling remains product-only.
- The live AFS document source still does not expose a real sort field, so document `sort_order` / `position` currently fall back to `afs_document_id`.
- `stage_product_documents.path` is finalized here as the normalized filename-level stage path, while `source_path` keeps the original AFS location for traceability.

# Validation steps

- Executed:
  - `docker compose up -d --build`
  - `docker compose exec -T php php -l /app/config/normalize.php`
  - `docker compose exec -T php php -l /app/config/merge.php`
  - `docker compose exec -T php php -l /app/config/expand.php`
  - `docker compose exec -T php php -l /app/src/Service/MergeService.php`
  - `docker compose exec -T php php -l /app/src/Service/ExpandService.php`
  - `docker compose exec -T php php -l /app/run_merge.php`
  - `docker compose exec -T php php -l /app/run_expand.php`
  - `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
  - `curl -s -o /tmp/t027_migrations.out -w "%{http_code}" -X POST http://localhost:8080/status/migrations`
  - `docker compose exec -T php php /app/run_import_all.php`
  - `docker compose exec -T php php /app/run_merge.php`
  - `docker compose exec -T php php /app/run_expand.php`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT 'raw_afs_documents', COUNT(*) FROM raw_afs_documents UNION ALL SELECT 'stage_product_documents', COUNT(*) FROM stage_product_documents UNION ALL SELECT 'stage_product_media', COUNT(*) FROM stage_product_media UNION ALL SELECT 'raw_afs_articles_with_images', COUNT(*) FROM raw_afs_articles WHERE image_1 IS NOT NULL OR image_2 IS NOT NULL OR image_3 IS NOT NULL OR image_4 IS NOT NULL OR image_5 IS NOT NULL OR image_6 IS NOT NULL OR image_7 IS NOT NULL OR image_8 IS NOT NULL OR image_9 IS NOT NULL OR image_10 IS NOT NULL;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT afs_document_id, afs_artikel_id, title, file_name, path, source_path, document_type, position FROM stage_product_documents ORDER BY id DESC LIMIT 5;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT media_external_id, afs_artikel_id, source_slot, file_name, path, type, position FROM stage_product_media ORDER BY afs_artikel_id DESC, position ASC LIMIT 10;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT version FROM schema_migrations WHERE version IN ('009_add_raw_article_image_slots') ORDER BY version;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT run_type, status, message FROM sync_runs WHERE run_type IN ('import_all','merge','expand') ORDER BY id DESC LIMIT 6;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT 'missing_file_name', COUNT(*) FROM stage_product_documents WHERE file_name IS NULL OR file_name = '' UNION ALL SELECT 'missing_product_link', COUNT(*) FROM stage_product_documents d LEFT JOIN stage_products p ON p.afs_artikel_id = d.afs_artikel_id WHERE p.afs_artikel_id IS NULL;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT 'missing_file_name', COUNT(*) FROM stage_product_media WHERE file_name IS NULL OR file_name = '' UNION ALL SELECT 'missing_product_link', COUNT(*) FROM stage_product_media m LEFT JOIN stage_products p ON p.afs_artikel_id = m.afs_artikel_id WHERE p.afs_artikel_id IS NULL UNION ALL SELECT 'multi_slot_rows', COUNT(*) FROM stage_product_media WHERE position > 1;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT afs_artikel_id, source_slot, file_name, position FROM stage_product_media WHERE position > 1 ORDER BY afs_artikel_id DESC, position ASC LIMIT 10;"`
- Observed:
  - the migration endpoint returned HTTP `302`
  - `009_add_raw_article_image_slots` is recorded in `schema_migrations`
  - `raw_afs_documents = 2853`
  - `stage_product_documents = 2853`
  - `stage_product_media = 5331`
  - `raw_afs_articles_with_images = 5330`
  - document sample rows now show correct `title`, `file_name`, `path`, `source_path`, and `document_type` mapping
  - media sample rows now show stable external IDs, normalized filenames, original image paths, and explicit slot positions
  - `stage_product_documents` rows with missing `file_name`: `0`
  - `stage_product_documents` rows without matching `stage_products` linkage: `0`
  - `stage_product_media` rows with missing `file_name`: `0`
  - `stage_product_media` rows without matching `stage_products` linkage: `0`
  - at least one product produces a second image slot row (`position = 2`)
  - latest `import_all`, `merge`, and `expand` runs finished successfully

# Recommended next step

Use the populated `stage_product_documents` and `stage_product_media` tables in the future media/document delta or XT writer tickets, without changing the new source-to-stage wiring.
