## Task

Fix the remaining config-driven XT export issues after the shop reset: restore reliable attribute assignments, generate product SEO URLs from the category path, enable category export including multi-language data, raise the export worker batch size to 1000, add a mirror reset action, rerun the full configured flow, and verify 3 real shop products against stage data.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/sources.php`
- `config/delta.php`
- `config/xt_write.php`
- `config/xt_mirror.php`
- `config/admin.php`
- `run_export_queue.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtSnapshotService.php`
- `src/Service/WelaApiClient.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/SchemaHealthRepository.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `wela-api/index.php`

## Changed files

- `config/sources.php`
- `config/delta.php`
- `config/xt_write.php`
- `config/admin.php`
- `database.sql`
- `migrations/015_add_category_delta_support.sql`
- `run_export_queue.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/StageCategoryMap.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtSnapshotService.php`
- `src/Service/WelaApiClient.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/SchemaHealthRepository.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `wela-api/index.php`
- `docs/agent-results/2026-04-16-category-seo-attribute-export-fix.md`

## Summary

- Proven config/data issues fixed:
  - `config/sources.php`: corrected the AFS category import filter from `Internet = 0` to `Internet = 1`, which was the direct reason stage category data stayed empty.
  - `config/delta.php`: added category delta/export support, made category export run before product export, and raised worker batch sizes to `1000`.
  - `config/xt_write.php`: restored active config-driven XT category write definitions for `xt_categories`, `xt_categories_description`, and category SEO URLs.
- Runtime fixes kept config-driven:
  - added `XtCategoryWriter` and `StageCategoryMap` so category entity, translations, hierarchy values, and SEO path generation are written from existing config instead of hardcoded ad-hoc logic
  - extended `ProductDeltaService` so category queue entries, mirror comparison, and entity ordering follow delta config
  - updated `run_export_queue.php` to register the category writer in the existing export worker flow
  - changed `XtProductWriter` SEO generation to use the configured category path instead of the former `slug-id` form
  - implemented writer-side SEO uniqueness handling so the existing `ensure_unique_url` config is actually honored
  - fixed XT mirror inserts to deduplicate by the configured mirror key before writing local mirror rows
- Admin/runtime surface fixes:
  - added mirror reset support in the pipeline admin controller/repository/view
  - updated pipeline/state/schema/consistency surfaces to include category export state and config-driven entity types
  - added monitoring checks for orphan category and attribute translation rows so analysis no longer points at XT when the mismatch is already in stage data
- XT API changes:
  - added `sync_category`
  - allowed category/category-description writes needed by the existing export config
  - kept the permissive read path for mirror fetching unchanged

## Open points

- Remaining stage anomalies are now data-shape issues, not active XT export failures:
  - `stage_category_translations` still contains orphan rows for categories that do not exist in `stage_categories`
  - `stage_attribute_translations` still contains orphan rows for products that do not exist in `stage_products`
- These orphan rows explain the previously misleading residual mismatch counts. For real stage categories/products that are part of the active export flow, the final mirror comparison came back clean.
- Any future change to `wela-api/index.php` still requires copying the file to the XT host before live validation.

## Validation steps

- Applied schema update:
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync < /app/migrations/015_add_category_delta_support.sql`
- Reset intermediate state/mirror/export tables and reran the full configured flow:
  - `docker compose exec -T php php /app/run_full_pipeline.php`
  - repeated `docker compose exec -T php php /app/run_export_queue.php` until the queue was empty
  - `docker compose exec -T php php /app/run_xt_mirror.php`
- Final queue result:
  - `pending = 0`
  - `error = 0`
  - `category done = 193`
  - `product done = 5350`
  - `media done = 5331`
  - `document done = 2853`
- Final targeted consistency checks:
  - real stage category translations missing in XT: `0`
  - real stage attribute tuples missing in XT: `0`
  - extra XT attribute tuples for active stage products: `0`
- Verified 3 real sample products against stage and mirror:
  - `68`
  - `72`
  - `73`
  - texts, attributes, assigned category, and SEO URLs matched the final stage-driven export state

## Recommended next step

If you want the stage analysis to be completely clean as well, the next scoped task should decide whether orphan translation rows in stage should be pruned during merge/expand or preserved but only reported as stage data hygiene findings.
