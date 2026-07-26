## Task

Clean up the obsolete XT snapshot-based runtime implementation after the migration to XT mirror tables, without touching the active mirror refresh behavior or changing export business logic.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/agent-results/2026-04-15-T-040-xt-snapshot-import.md`
- `docs/agent-results/2026-04-15-T-041-target-aware-delta-from-xt-snapshot.md`
- `docs/agent-results/2026-04-15-T-042-xt-mirror-dependency-derivation.md`
- `config/delta.php`
- `config/admin.php`
- `config/xt_snapshot.php`
- `run_xt_snapshot.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtSnapshotService.php`
- `src/Service/WelaApiClient.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Web/View/pipeline/index.php`
- `wela-api/README.md`
- `migrations/012_create_xt_snapshot_tables.sql`
- `migrations/014_create_xt_mirror_tables.sql`

## Changed files

- `config/admin.php`
- `config/delta.php`
- `config/xt_snapshot.php`
- `run_xt_snapshot.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtSnapshotService.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `wela-api/README.md`

## Summary

- Removed active snapshot-table usage from delta by switching `ProductDeltaService` to mirror-derived comparison rows for products, media, and documents.
- Removed legacy `xt_*_snapshot` writes from `XtSnapshotService`; the refresh now only repopulates mirror tables.
- Removed snapshot tables from the admin browser and updated live pipeline/runner wording from snapshot import to mirror refresh.
- Replaced snapshot-specific delta config keys with mirror-specific keys.

Removed snapshot references from active runtime paths:

- `config/delta.php`
  - removed `snapshot_table`
  - removed `snapshot_identity_field`
  - removed `snapshot_compare_fields`
  - removed `snapshot_translation_hash_field`
  - removed `snapshot_attribute_hash_field`
  - removed `snapshot_seo_hash_field`
- `src/Service/ProductDeltaService.php`
  - removed snapshot-table reads
  - removed snapshot-based delta counters/context fields
- `src/Service/XtSnapshotService.php`
  - removed snapshot table clearing
  - removed snapshot table inserts
- `config/admin.php`
  - removed snapshot tables from active admin exposure
- `src/Web/View/pipeline/index.php`
  - removed active `XT Snapshot` UI wording
- `src/Web/Controller/PipelineController.php`
  - removed active `XT Snapshot` run label

Snapshot-related files intentionally kept because they are still the active mirror refresh entrypoint:

- `run_xt_snapshot.php`
- `src/Service/XtSnapshotService.php`
- `config/xt_snapshot.php`

Snapshot migration checks intentionally remain in `src/Web/Repository/MigrationRepository.php` because the snapshot tables are still present in the schema and were explicitly not dropped in this task.

## Open points

- The runner/service/config filenames and the run type `xt_snapshot` remain as compatibility names for the active mirror refresh entrypoint.
- Historical tickets and historical agent result documents still reference the former snapshot implementation and were not rewritten.
- Snapshot database tables and snapshot migrations still exist and were not removed.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/ProductDeltaService.php`
- `docker compose exec -T php php -l /app/src/Service/XtSnapshotService.php`
- `docker compose exec -T php php -l /app/config/delta.php`
- `docker compose exec -T php php -l /app/config/admin.php`
- `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
- `docker compose exec -T php php -l /app/src/Web/View/pipeline/index.php`
- `docker compose exec -T php php -l /app/run_xt_snapshot.php`
- `docker compose exec -T php php -l /app/config/xt_snapshot.php`
- executed an isolated in-container Delta smoke test with temporary stage, queue, state, `sync_runs`, and `xt_mirror_*` tables while the real snapshot tables stayed empty; product/media/document delta all reported `mirror_matched = 1`, `changed = 0`, and created `0` queue items

## Recommended next step

If the compatibility naming is no longer needed, do a separate follow-up to rename `run_xt_snapshot.php`, `XtSnapshotService`, `config/xt_snapshot.php`, and the `xt_snapshot` run type/job key to mirror-specific names.
