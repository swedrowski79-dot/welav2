## Task

Verify the full config-driven AFS -> XT-Commerce flow, run the complete pipeline, drain the export queue, refresh the XT mirror, and compare 3 live shop articles against current stage/AFS-driven category and text data.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/pipeline.php`
- `config/merge.php`
- `config/expand.php`
- `config/xt_write.php`
- `config/xt_mirror.php`
- `config/sources.php`
- `run_full_pipeline.php`
- `run_export_queue.php`
- `run_xt_mirror.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/XtProductWriter.php`
- `src/Service/WelaApiClient.php`
- `src/Service/AbstractXtWriter.php`
- `wela-api/index.php`

## Changed files

- `docs/agent-results/2026-04-15-full-afs-xt-verification.md`

## Summary

- Ran the configured full pipeline successfully: `import_all -> merge -> expand -> export_queue_worker`.
- Drained the remaining `export_queue` to `0` pending entries by repeated `run_export_queue.php` passes.
- Refreshed XT mirror after export completion.
- Verified global category alignment for all current stage products:
  - `total = 5350`
  - `missing_product = 0`
  - `missing_category_link = 0`
  - `wrong_category = 0`
- Verified 3 live shop articles against current config-driven stage data:
  - `68 / GANI-080`
  - `72 / GANI-100`
  - `73 / GANI-120`
- For all 3 samples:
  - assigned XT category matched `stage_products.category_afs_id`
  - `de/en/fr/nl` names matched
  - `de/en/fr/nl` descriptions matched
  - `de/en/fr/nl` short descriptions matched

## Open points

- No current config/backend mismatch was proven in the active AFS -> XT flow, so no production code was changed.
- There are still residual exact-string differences in some `short_description` values when comparing stage text to mirrored XT text. The sampled cases point to formatting normalization only:
  - CR/LF or trailing control-character differences in RTF-like content
  - whitespace-only stage values becoming empty strings in XT
- Those residuals were not changed because there is no existing config-backed normalization hook for this path, and adding one here would introduce new behavior instead of aligning existing config.
- Earlier apparent missing `en/fr/nl` rows were not reproducible for the current stage-backed product set after the final queue drain and mirror refresh.

## Validation steps

- Ran `docker compose exec -T php php /app/run_full_pipeline.php`
- Re-ran `docker compose exec -T php php /app/run_export_queue.php` until `export_queue` had no pending entries left
- Ran `docker compose exec -T php php /app/run_xt_mirror.php`
- Queried `sync_runs`, `export_queue`, `stage_products`, `stage_product_translations`, `raw_afs_articles`, `xt_mirror_products`, `xt_mirror_products_description`, `xt_mirror_products_to_categories`, `xt_mirror_categories`, and `xt_mirror_categories_description`
- Compared the 3 sample articles directly across stage/AFS-derived data and live XT mirror data

## Recommended next step

If exact `short_description` byte-for-byte parity is required, add a separately scoped ticket for config-backed text normalization so whitespace/line-ending cleanup can be introduced deliberately and validated as an intentional behavior change.
