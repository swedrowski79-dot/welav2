## Task

Verify the full config-driven attribute assignment flow after the shop reset, find the proven reason broken XT attribute assignments were not corrected, and fix it minimally and safely.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/pipeline.php`
- `config/delta.php`
- `config/expand.php`
- `config/xt_write.php`
- `config/xt_mirror.php`
- `run_full_pipeline.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtSnapshotService.php`
- `src/Service/PipelineConfig.php`
- `wela-api/index.php`

## Changed files

- `config/pipeline.php`
- `docs/agent-results/2026-04-16-attribute-assignment-reset-fix.md`

## Summary

- Root cause was proven in the config-driven full pipeline order:
  - `full_pipeline` ran `import_all -> merge -> expand -> export_queue_worker`
  - `expand` includes delta
  - delta compares against XT mirror
  - after the shop reset, the mirror was stale because `xt_mirror` had not run before delta
- Result: delta used an outdated XT comparison state and skipped required repair updates, including stale attribute links left in the reset shop.
- Minimal safe fix:
  - updated `config/pipeline.php`
  - changed `full_pipeline.sequence` to:
    - `import_all`
    - `merge`
    - `xt_mirror`
    - `expand`
    - `export_queue_worker`
  - updated the `full_pipeline` help text accordingly

## Open points

- Manual single-step runs still behave exactly as configured:
  - if someone runs `expand` without a fresh `xt_mirror`, delta will compare against the last available mirror state
  - this task only fixed the authoritative full pipeline sequence, which is the correct minimal scope for the proven reset issue

## Validation steps

- Ran the updated full pipeline:
  - `docker compose exec -T php php /app/run_full_pipeline.php`
- Drained the export queue by repeated:
  - `docker compose exec -T php php /app/run_export_queue.php`
- Refreshed XT mirror after export:
  - `docker compose exec -T php php /app/run_xt_mirror.php`
- Rechecked global attribute assignment consistency:
  - `stage_no_attrs_xt_has = 0`
  - `stage_has_attrs_xt_missing = 0`
- Rechecked the 6 previously broken products:
  - `46016`
  - `46017`
  - `51725`
  - `51726`
  - `51727`
  - `51728`
  - all now have no remaining XT attribute links when stage contains no attributes

## Recommended next step

If you want reset recovery to also be guaranteed for manual partial runs, create a separate scoped ticket to decide whether `expand`/delta should require or trigger a fresh mirror explicitly via config.
