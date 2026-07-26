# Task

Implement T-043 so the active pipeline, delta execution, XT mirror refresh, and web launch surfaces follow config as the single source of truth without inventing new behavior.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/README.md`
- `docs/tickets/TEMPLATE.md`
- `docs/tickets/open/T-043-enforce-config-as-single-source-of-truth-for-pipeline-and-data-flow.md`
- `docs/agent-results/2026-04-15-consistency-audit.md`
- `config/pipeline.php`
- `config/delta.php`
- `config/xt_mirror.php`
- `config/xt_snapshot.php`
- `config/xt_write.php`
- `run_full_pipeline.php`
- `run_xt_snapshot.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtSnapshotService.php`
- `src/Web/bootstrap.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Web/Repository/StatusRepository.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `src/Web/View/status/index.php`
- `src/Web/View/sync-runs/index.php`

# Changed files

- `config/delta.php`
- `config/pipeline.php`
- `config/xt_mirror.php`
- `config/xt_write.php`
- `docs/agent-results/2026-04-15-T-043-config-single-source-of-truth.md`
- `docs/agent-results/2026-04-15-consistency-audit.md`
- `docs/tickets/open/T-043-enforce-config-as-single-source-of-truth-for-pipeline-and-data-flow.md`
- `run_full_pipeline.php`
- `run_xt_snapshot.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/PipelineConfig.php`
- `src/Service/XtSnapshotService.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/Repository/PipelineAdminRepository.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Web/Repository/StatusRepository.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `src/Web/View/status/index.php`
- `src/Web/View/sync-runs/index.php`
- `src/Web/bootstrap.php`

# Summary

- Replaced the dead oversized `config/pipeline.php` step list with the actual supported job model: runnable jobs, config-defined labels/help, config-defined UI grouping, and the active `full_pipeline` sequence.
- Added `src/Service/PipelineConfig.php` and switched the full pipeline runner, web launcher, pipeline progress labels, pipeline page, and sync-runs page to consume that config instead of hardcoded step metadata.
- Made XT mirror refresh consume `config/xt_mirror.php` directly by loading mirror table definitions from config and removing the runtime dependency on `xt_write.php` for snapshot table selection.
- Reduced `config/delta.php` to the active export-queue entity definitions, removed the dead legacy `delta` block, and tightened delta/worker startup so they fail if `export_queue_entities` is missing instead of silently falling back to product-only behavior.
- Removed unreachable category/category-SEO write definitions from `config/xt_write.php`, keeping only the currently implemented product/media/document XT write behavior.
- Kept the previously proven web/admin consistency fixes in place: multi-entity export-state views, config-driven status table counts, `xt_snapshot` launcher visibility, and media/document export-state consistency checks.
- Fixed the pipeline page runtime failure by making the new media/document consistency joins collation-safe with binary comparisons.

# Open points

- `ProductDeltaService` and `ExportQueueWorker` still keep some defensive per-key defaults for active config entries, although the active config now defines those keys explicitly.
- End-to-end XT mirror refresh and export execution were not run because they depend on live external/system data, so validation stayed at config loading, syntax, and rendered web surfaces.
- The worktree contains many unrelated user changes outside this task and they were intentionally left untouched.

# Validation steps

- `docker compose exec -T php sh -lc 'php -l /app/src/Service/PipelineConfig.php && php -l /app/config/pipeline.php && php -l /app/config/delta.php && php -l /app/config/xt_mirror.php && php -l /app/config/xt_write.php && php -l /app/run_full_pipeline.php && php -l /app/run_xt_snapshot.php && php -l /app/src/Web/bootstrap.php && php -l /app/src/Web/Repository/SyncLauncher.php && php -l /app/src/Web/Controller/PipelineController.php && php -l /app/src/Web/Controller/SyncRunController.php && php -l /app/src/Web/View/pipeline/index.php && php -l /app/src/Web/View/sync-runs/index.php && php -l /app/src/Service/DeltaRunnerService.php && php -l /app/src/Service/ExportQueueWorker.php && php -l /app/src/Service/XtSnapshotService.php && php -l /app/src/Web/Repository/StageConsistencyRepository.php'`
- `docker compose exec -T php php -r 'require "/app/src/Service/PipelineConfig.php"; echo json_encode(["full_pipeline" => PipelineConfig::fullPipelineSteps(), "pipeline_sections" => count(PipelineConfig::sections("pipeline")), "sync_runs_sections" => count(PipelineConfig::sections("sync_runs"))], JSON_PRETTY_PRINT), PHP_EOL;'`
- `docker compose exec -T php php -r '$delta = require "/app/config/delta.php"; echo json_encode(["export_queue_entities" => $delta["export_queue_entities"], "has_dead_delta_block" => array_key_exists("delta", $delta)], JSON_PRETTY_PRINT), PHP_EOL;'`
- `docker compose exec -T php php -r '$mirror = require "/app/config/xt_mirror.php"; echo json_encode(array_map(static fn(array $definition): string => $definition["mirror_table"], $mirror["mirror"]), JSON_PRETTY_PRINT), PHP_EOL;'`
- `curl -fsS http://localhost:8080/pipeline`
- `curl -fsS http://localhost:8080/sync-runs`

# Recommended next step

Run one representative manual cycle of `run_full_pipeline.php`, `run_xt_snapshot.php`, and `run_export_queue.php` against a known-good environment if you want runtime proof beyond config loading and rendered admin surfaces.
