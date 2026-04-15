# Task

Implement `T-032` by preserving existing XT SEO URL fields on product sync while still updating SEO metadata and creating missing SEO rows.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-032 — Preserve existing SEO URLs but update SEO metadata.md`
- `docs/agent-results/2026-04-15-T-030-full-xt-product-writer.md`
- `docs/agent-results/2026-04-15-T-031-xt-product-seo-writer.md`
- `docs/IMPLEMENTATION_NOTES.md`
- `config/xt_write.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`
- `wela-api/README.md`
- `run_export_queue.php`

# Changed files

- `wela-api/index.php`
- `wela-api/README.md`
- `docs/IMPLEMENTATION_NOTES.md`
- `docs/tickets/done/T-032-preserve-existing-seo-urls-update-metadata.md`
- `docs/agent-results/2026-04-15-T-032-preserve-existing-seo-urls-update-metadata.md`

# Summary

- Changed the `sync_product` SEO path from global skip behavior to per-row handling.
- Existing `xt_seo_url` rows now preserve `url_text` and `url_md5` while still allowing metadata updates.
- Missing SEO rows are still inserted with the full generated payload, so partial-language backfill now works.
- The existing product sync transaction and duplicate protection via XT identity upserts were preserved.
- Updated the local `wela-api` README and implementation notes to match the new behavior.

# Open points

- This ticket did not change the SEO URL generation algorithm itself; it only changed how existing rows are updated versus preserved.
- Validation used a temporary isolated XT database and local `wela-api` server because the configured XT endpoint in this environment points outside the repository-local `wela-api` code.
- Push was not performed because the repository workflow requires an explicit user request before pushing.

# Validation steps

- Syntax checks:
  - `docker compose exec -T php php -l /app/wela-api/index.php`
  - `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
  - `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- Isolated XT validation setup:
  - created temporary MySQL database `xt_t032`
  - created minimal XT tables:
    - `xt_products`
    - `xt_seo_url`
  - created temporary local `wela-api/config.php`
  - started temporary local `wela-api` server on `127.0.0.1:8094` inside the PHP container
- Executed live validation through the real HTTP API client:
  - first `WelaApiClient::syncProduct()` inserted product `501` and SEO rows for `de` and `en`
  - manually changed German SEO URL to `manual-existing-url`
  - deleted the English SEO row
  - second `WelaApiClient::syncProduct()` updated metadata and recreated the missing English row
  - third identical `WelaApiClient::syncProduct()` verified duplicate-free repeated sync
- Observed final validation state:
  - German SEO row:
    - `url_text = manual-existing-url`
    - `meta_title = Meta DE v2`
    - `meta_description = Beschreibung DE v2`
    - `meta_keywords = kw-de`
  - English SEO row:
    - `url_text = en/regenerated-501`
    - `meta_title = Meta EN v2`
    - `meta_description = Description EN v2`
    - `meta_keywords = kw-en`
  - total SEO rows for product `501` = `2`
  - duplicate groups for `(language_code, store_id)` = `0`

# Recommended next step

If category SEO should follow the same behavior, apply the same per-row preserve/update rule to category SEO writes on top of the existing config-driven SEO path.
