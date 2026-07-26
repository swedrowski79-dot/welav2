# Task

Implement ticket `T-031` by adding XT SEO URL writing for synced products while preserving existing SEO URLs.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-031.md`
- `docs/agent-results/2026-04-15-T-030-full-xt-product-writer.md`
- `config/xt_write.php`
- `config/languages.php`
- `config/xt_mirror.php`
- `docs/IMPLEMENTATION_NOTES.md`
- `src/Service/AbstractXtWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtCompositeWriter.php`
- `src/Service/WelaApiClient.php`
- `src/Service/ExportQueueWorker.php`
- `run_export_queue.php`
- `wela-api/index.php`
- `wela-api/README.md`

# Changed files

- `config/xt_write.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`
- `wela-api/README.md`
- `docs/tickets/done/T-031.md`
- `docs/agent-results/2026-04-15-T-031-xt-product-seo-writer.md`

# Summary

- Reused the existing `XtProductWriter` plus `sync_product` API path instead of adding a separate SEO writer.
- Added product SEO payload generation in `XtProductWriter` based on the existing `xt_seo_url_products` config.
- Product SEO URLs are generated per language from the translated product name plus the AFS product identity, producing stable and idempotent URLs.
- `sync_product` now writes SEO rows into `xt_seo_url` inside the same XT transaction as the product sync.
- Existing SEO rows are preserved strictly:
  - if any SEO row already exists for the XT product, SEO writing is skipped entirely
  - no existing `url_text` or `url_md5` is overwritten
  - repeated syncs do not create duplicates
- SEO errors still fail the product sync transaction, so queue handling remains correct and export state is not falsely confirmed.

# Open points

- The generated product SEO URL currently uses a stable language prefix plus product slug and AFS identity, not a full category-path-derived URL.
- Category SEO handling was not changed in this ticket.
- The SEO create-on-missing rule is intentionally stricter than a partial-language backfill: if any SEO row exists for the product, all SEO creation is skipped.

# Validation steps

- Executed syntax checks:
  - `docker compose exec -T php php -l /app/config/xt_write.php`
  - `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
  - `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
  - `docker compose exec -T php php -l /app/wela-api/index.php`
- Executed isolated XT/API validation:
  - created temporary XT test databases `xt_t031_ok` and `xt_t031_fail`
  - started temporary `wela-api` servers on `127.0.0.1:8091` and `127.0.0.1:8092`
  - ran isolated product writer sync against `xt_t031_ok`
  - manually changed an existing SEO URL in `xt_t031_ok`
  - ran the same product writer sync again
  - ran isolated `ExportQueueWorker` against `xt_t031_fail` where `xt_seo_url` was intentionally missing
- Observed:
  - first SEO sync created `4` product SEO rows for the new XT product
  - created URLs were:
    - `de/seo-produkt-de-501`
    - `en/seo-product-en-501`
    - `fr/seo-produkt-de-501`
    - `nl/seo-produkt-de-501`
  - after manually setting the existing German URL to `manual-existing-url`, the second sync:
    - kept row count at `4`
    - preserved `manual-existing-url`
    - created no duplicates
  - isolated worker error run against missing `xt_seo_url` produced:
    - queue status `error`
    - `last_error = SQLSTATE[42S02]... xt_seo_url doesn't exist`
    - `state_count = 0`

# Recommended next step

If storefront requirements demand category-path-based product URLs later, extend the SEO generator on top of the same `XtProductWriter`/`sync_product` path without changing the existing preserve-on-present behavior.
