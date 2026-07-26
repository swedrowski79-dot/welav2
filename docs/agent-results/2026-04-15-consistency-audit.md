# Task

Perform a consistency audit with `config/` as the authoritative source of truth across backend pipeline implementation, CLI/web runners, web step handling, and monitoring/analysis surfaces; then apply only minimal proven fixes.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/admin.php`
- `config/delta.php`
- `config/expand.php`
- `config/languages.php`
- `config/merge.php`
- `config/normalize.php`
- `config/pipeline.php`
- `config/sources.php`
- `config/xt_mirror.php`
- `config/xt_snapshot.php`
- `config/xt_write.php`
- `run_import_all.php`
- `run_import.php`
- `run_import_products.php`
- `run_import_categories.php`
- `run_merge.php`
- `run_expand.php`
- `run_delta.php`
- `run_export_queue.php`
- `run_full_pipeline.php`
- `run_xt_snapshot.php`
- `src/Importer/AfsImporter.php`
- `src/Importer/ExtraImporter.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Service/ImportWorkflow.php`
- `src/Service/Normalizer.php`
- `src/Service/StageWriter.php`
- `src/Service/MergeService.php`
- `src/Service/ExpandService.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/XtSnapshotService.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Service/XtCompositeWriter.php`
- `src/Service/XtWriteDependencyMap.php`
- `public/index.php`
- `src/Web/Controller/DashboardController.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/Repository/DashboardRepository.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/SchemaHealthRepository.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Web/Repository/StatusRepository.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/dashboard/index.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `src/Web/View/status/index.php`
- `src/Web/View/sync-runs/index.php`
- `src/Web/View/sync-runs/show.php`

# Changed files

- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Web/Repository/StatusRepository.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `src/Web/View/status/index.php`
- `src/Web/View/sync-runs/index.php`

# Summary

- Confirmed that `sources.php`, `normalize.php`, `merge.php`, `expand.php`, the active `delta.php` export-queue entities, `languages.php`, `xt_snapshot.php`, and the product/media/document sections of `xt_write.php` are wired into the current runtime.
- Confirmed major dead or bypassed config areas:
  - `config/pipeline.php` is not consumed by CLI or web orchestration.
  - `config/xt_mirror.php` is not consumed; XT mirror refresh derives mirror tables from `config/xt_write.php`.
  - `config/delta.php` entries for `categories`, `products_description`, `seo_products`, and `seo_categories` are declared but never executed.
  - `config/xt_write.php` category and category SEO definitions are unreachable and also reference stage fields that do not exist in the current schema.
  - Some source-select fields are loaded but never reach normalization/merge outputs; the clearest case is `normalize.php` category `online_flag`, which is overwritten by a calculated resolver.
- Applied minimal safe fixes to the proven UI/monitoring mismatches:
  - export state UI now reflects all configured state tables (`product`, `media`, `document`) instead of product only
  - sync-run launcher UI now exposes `xt_snapshot`
  - status table counts are now driven by `config/admin.php`
  - stage consistency checks now cover media/document export states as well

# Open points

- `config/pipeline.php` still overstates the runnable step model compared with the actual script-based orchestration.
- `config/xt_mirror.php` remains dead configuration.
- Category/category-SEO delta and XT-write config remain declared but not implemented.
- Unused source columns and the overwritten category `online_flag` mapping remain as audit findings only; they were not removed to avoid broader config cleanup beyond the proven UI/monitoring mismatches.

# Validation steps

- `docker compose exec -T php php -l src/Web/Repository/PipelineAdminRepository.php`
- `docker compose exec -T php php -l src/Web/Controller/PipelineController.php`
- `docker compose exec -T php php -l src/Web/View/pipeline/index.php`
- `docker compose exec -T php php -l src/Web/View/pipeline/state.php`
- `docker compose exec -T php php -l src/Web/Controller/StatusController.php`
- `docker compose exec -T php php -l src/Web/Repository/StatusRepository.php`
- `docker compose exec -T php php -l src/Web/View/status/index.php`
- `docker compose exec -T php php -l src/Web/View/sync-runs/index.php`
- `docker compose exec -T php php -l src/Web/Repository/StageConsistencyRepository.php`
- `curl -fsS http://localhost:8080/pipeline`
- `curl -fsS http://localhost:8080/pipeline/state`
- `curl -fsS http://localhost:8080/sync-runs`
- `curl -fsS http://localhost:8080/status`

# Recommended next step

Decide whether `config/pipeline.php`, `config/xt_mirror.php`, and the unused category/SEO delta/write definitions should become real executable surfaces or be reduced to the currently implemented runtime model.
