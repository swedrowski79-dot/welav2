# Task

Implement `T-042` by deriving XT mirror coverage directly from the active writer configuration and extending the mirror with all writer-relevant base, relation, and description tables.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-042-Extend XT mirror to include all sync-relevant relation and description tables.md`
- `docs/agent-results/2026-04-15-T-040-xt-snapshot-import.md`
- `docs/agent-results/2026-04-15-T-041-target-aware-delta-from-xt-snapshot.md`
- `config/admin.php`
- `config/xt_snapshot.php`
- `config/xt_write.php`
- `run_export_queue.php`
- `run_xt_snapshot.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtCompositeWriter.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtSnapshotService.php`
- `src/Web/Repository/MigrationRepository.php`
- `wela-api/index.php`
- `migrations/012_create_xt_snapshot_tables.sql`
- `migrations/013_add_xt_product_snapshot_compare_fields.sql`

# Changed files

- `src/Service/XtWriteDependencyMap.php`
- `src/Service/XtSnapshotService.php`
- `config/xt_snapshot.php`
- `wela-api/index.php`
- `database.sql`
- `migrations/014_create_xt_mirror_tables.sql`
- `src/Web/Repository/MigrationRepository.php`
- `config/admin.php`
- `docs/tickets/done/T-042-xt-mirror-dependency-derivation.md`
- `docs/agent-results/2026-04-15-T-042-xt-mirror-dependency-derivation.md`

# Summary

- Added `XtWriteDependencyMap` so XT mirror dependencies are derived from `config/xt_write.php` instead of being maintained manually in a separate mirror source list.
- Extended snapshot refresh to populate dedicated raw mirror tables for every XT table touched by the active writers:
  - products
  - categories
  - category descriptions
  - product descriptions
  - product-category relations
  - media
  - media links
  - product attributes
  - attribute descriptions
  - product-attribute relations
  - SEO URLs
- Kept the existing aggregate snapshot tables in place, so delta/export behavior stays unchanged.
- Extended `wela-api` read exposure from the same dependency map so mirror refresh can fetch all writer-relevant tables and fields, including previously missing description tables.
- Added schema and migration support for the new mirror tables and registered them in the admin table browser.

# Open points

- The configured production-like `XT_API_URL` in this environment still points to an external XT API endpoint outside this repository, so the live `run_xt_snapshot.php` path could not be end-to-end verified against the repository-local `wela-api` code here.
- Validation therefore used a local HTTP XT-API stub with the real service code and real Stage MySQL tables to prove mirror derivation, schema compatibility, and duplicate-free refresh behavior.
- Push was not performed because repository workflow requires an explicit user request before pushing.

# Validation steps

- Syntax checks:
  - `docker compose exec -T php php -l src/Service/XtWriteDependencyMap.php`
  - `docker compose exec -T php php -l src/Service/XtSnapshotService.php`
  - `docker compose exec -T php php -l wela-api/index.php`
  - `docker compose exec -T php php -l src/Web/Repository/MigrationRepository.php`
- Applied migration:
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync < migrations/014_create_xt_mirror_tables.sql`
- Verified dependency derivation:
  - `docker compose exec -T php php -r 'require "src/Service/XtWriteDependencyMap.php"; ...'`
  - confirmed derived XT tables:
    - `xt_categories`
    - `xt_categories_description`
    - `xt_media`
    - `xt_media_link`
    - `xt_plg_products_attributes`
    - `xt_plg_products_attributes_description`
    - `xt_plg_products_to_attributes`
    - `xt_products`
    - `xt_products_description`
    - `xt_products_to_categories`
    - `xt_seo_url`
- Executed isolated HTTP validation:
  - started a temporary local XT-API stub on port `8091`
  - executed `XtSnapshotService->run()` twice against `http://host.docker.internal:8091`
  - used real Stage MySQL tables and the real `WelaApiClient` HTTP path
- Verified final table counts after repeated refresh:
  - `xt_products_snapshot = 1`
  - `xt_categories_snapshot = 1`
  - `xt_media_snapshot = 1`
  - `xt_documents_snapshot = 1`
  - `xt_mirror_products = 1`
  - `xt_mirror_categories = 1`
  - `xt_mirror_categories_description = 1`
  - `xt_mirror_products_description = 1`
  - `xt_mirror_products_to_categories = 1`
  - `xt_mirror_media = 2`
  - `xt_mirror_media_link = 2`
  - `xt_mirror_plg_products_attributes = 1`
  - `xt_mirror_plg_products_attributes_description = 1`
  - `xt_mirror_plg_products_to_attributes = 1`
  - `xt_mirror_seo_url = 2`
- Verified returned runtime stats were identical across both stub-backed runs:
  - `products = 1`
  - `categories = 1`
  - `media = 1`
  - `documents = 1`
  - all `source_counts` and `mirror_counts` unchanged on rerun

# Recommended next step

Run one real XT snapshot refresh against the intended live XT API endpoint after that endpoint is updated to the current `wela-api` code, then compare the mirrored raw tables against the live XT database contents as a final environment-level confirmation.
