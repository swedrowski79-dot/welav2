# Ticket: T-043

## Status
done

## Title
Enforce config as single source of truth for pipeline and data flow

## Problem
Pipeline orchestration, web step presentation, delta behavior, and XT mirror behavior are not fully driven by config. Some config files are unused, some config entries are unreachable, and several runtime paths still hardcode step order or labels.

## Goal
Make the system follow the active config files exactly, remove dead or broken config definitions, and ensure pipeline, delta, mirror, and web interface behavior are controlled by config instead of hardcoded logic.

## Scope
- make `config/pipeline.php` authoritative for pipeline jobs, ordering, labels, and UI grouping
- make CLI/web orchestration consume pipeline config
- make delta consume only active `config/delta.php` definitions
- align XT mirror config usage or remove dead mirror config
- remove unreachable or broken config entries without inventing new behavior

## Acceptance Criteria
- [x] `config/pipeline.php` defines the active pipeline steps and order used by CLI and web
- [x] web UI shows only config-defined steps with config-defined labels/help
- [x] no hardcoded step sequences remain in runners, `SyncLauncher`, or controllers for active pipeline flow
- [x] `config/delta.php` contains only active, executable delta definitions
- [x] XT mirror runtime either uses `config/xt_mirror.php` or that dead config is removed
- [x] broken or unreachable config entries are removed
- [x] config is no longer overwritten by code for the covered pipeline/data-flow areas

## Files / Areas
- `config/pipeline.php`
- `config/delta.php`
- `config/xt_mirror.php`
- `config/xt_write.php`
- `run_*.php`
- `src/Service/*`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/Controller/*`
- `src/Web/View/pipeline/*`
- `src/Web/View/sync-runs/index.php`

## Notes
- Do not invent new behavior.
- Prefer reducing config to the actually supported runtime model over implementing new step families.
- Keep current working pipeline behavior intact: import, merge, expand including inline delta, optional mirror refresh, delta rerun, export worker, full pipeline.

## Result
- changed files
  - `config/pipeline.php`
  - `config/delta.php`
  - `config/xt_mirror.php`
  - `config/xt_write.php`
  - `run_full_pipeline.php`
  - `run_xt_snapshot.php`
  - `src/Service/PipelineConfig.php`
  - `src/Service/DeltaRunnerService.php`
  - `src/Service/ExportQueueWorker.php`
  - `src/Service/XtSnapshotService.php`
  - `src/Web/bootstrap.php`
  - `src/Web/Repository/SyncLauncher.php`
  - `src/Web/Controller/PipelineController.php`
  - `src/Web/Controller/SyncRunController.php`
  - `src/Web/View/pipeline/index.php`
  - `src/Web/View/sync-runs/index.php`
  - `src/Web/Repository/StageConsistencyRepository.php`
  - `docs/agent-results/2026-04-15-T-043-config-single-source-of-truth.md`
- summary
  - replaced the dead `run_order` model in `config/pipeline.php` with the active job/label/section/full-pipeline definition
  - switched CLI orchestration, web launchers, and web labels to shared `PipelineConfig` consumption
  - made XT snapshot use `config/xt_mirror.php`
  - removed the dead legacy delta block and unreachable category/category-SEO XT write config
  - tightened delta/export worker startup so missing `export_queue_entities` fails instead of silently falling back
  - fixed the `/pipeline` runtime error caused by collation-sensitive consistency joins
- validation
  - container PHP syntax checks for changed PHP files
  - config-loading checks for pipeline sequence, delta entities, and mirror tables
  - live GET checks for `/pipeline` and `/sync-runs`
- commit hash
  - recorded in the git history for this task
