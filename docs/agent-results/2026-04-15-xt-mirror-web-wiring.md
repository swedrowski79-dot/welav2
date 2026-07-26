# Task

Fix remaining `xt_snapshot` references so the web interface exposes and launches XT mirror without reintroducing snapshot config or changing mirror logic.

# Files read

- `config/pipeline.php`
- `run_xt_snapshot.php`
- `src/Service/PipelineConfig.php`
- `src/Service/ProductDeltaService.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/SyncRunController.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/sync-runs/index.php`

# Changed files

- `config/pipeline.php`
- `run_xt_mirror.php`
- `run_xt_snapshot.php`
- `src/Service/ProductDeltaService.php`
- `docs/agent-results/2026-04-15-xt-mirror-web-wiring.md`

# Summary

- Replaced the active config-driven job key `xt_snapshot` with `xt_mirror`.
- Switched the active mirror runner entrypoint to `run_xt_mirror.php`.
- Removed the runtime dependency on the deleted `config/xt_snapshot.php`.
- Kept `run_xt_snapshot.php` only as a backward-compatible wrapper to the mirror runner.
- Kept historical `xt_snapshot` run labeling and delta mirror-run checks as compatibility paths so existing monitoring data and prior successful runs still work.

# Open points

- `src/Web/Repository/MigrationRepository.php` still references migration version `012_create_xt_snapshot_tables`; that is legacy migration bookkeeping, not active launch/runtime wiring.
- `XtSnapshotService` keeps its legacy class name; the runtime behavior now runs under `xt_mirror`.

# Validation steps

- `docker compose exec -T php sh -lc 'php -l /app/run_xt_mirror.php && php -l /app/run_xt_snapshot.php && php -l /app/config/pipeline.php && php -l /app/src/Service/ProductDeltaService.php'`
- `docker compose exec -T php php -r 'require "/app/src/Service/PipelineConfig.php"; echo json_encode(["xt_mirror_script" => PipelineConfig::script("xt_mirror"), "xt_mirror_label" => PipelineConfig::labelForRunType("xt_mirror"), "legacy_xt_snapshot_label" => PipelineConfig::labelForRunType("xt_snapshot")], JSON_PRETTY_PRINT), PHP_EOL;'`
- `curl -fsS http://localhost:8080/pipeline`
- `curl -fsS http://localhost:8080/sync-runs`

# Recommended next step

If you want to remove the last compatibility traces too, do that as a separate cleanup that renames `XtSnapshotService` and retires legacy migration naming once old run history is no longer needed.
