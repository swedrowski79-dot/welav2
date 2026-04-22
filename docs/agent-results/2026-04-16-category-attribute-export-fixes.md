# Task

Fix three reported issues in the export flow:

1. product attributes must always write `attributes_templates_id = 1`
2. category image/header image positions were swapped
3. category handling for products must respect missing categories and slave/master inheritance while importing **only** AFS categories with `Internet = 0`

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `config/xt_write.php`
- `config/delta.php`
- `config/xt_mirror.php`
- `run_merge.php`
- `run_full_pipeline.php`
- `run_export_queue.php`
- `src/Database/ConnectionFactory.php`
- `src/Service/Normalizer.php`
- `src/Service/MergeService.php`
- `src/Service/StageCategoryMap.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/WelaApiClient.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtSnapshotService.php`
- `wela-api/index.php`
- `docs/agent-results/2026-04-15-full-afs-xt-verification.md`
- `docs/agent-results/2026-04-15-T-030-full-xt-product-writer.md`

# Changed files

- `config/normalize.php`
- `config/xt_write.php`
- `src/Service/StageCategoryMap.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`
- `docs/agent-results/2026-04-16-category-attribute-export-fixes.md`

# Summary

- AFS category import must remain restricted to `Internet = 0`. The repository is now aligned with that requirement again.
- Swapped category image ingestion at the source mapping layer:
  - `stage_categories.image` now comes from `Bild_gross`
  - `stage_categories.header_image` now comes from `Bild`
  This keeps stage, delta, and XT export semantics aligned instead of only swapping values late in the writer.
- Added `attributes_templates_id => 1` to the XT attribute entity mapping in `config/xt_write.php`.
- Extended `wela-api/index.php` so the repository version of `wela-api` accepts `attributes_templates_id` for `xt_plg_products_attributes`.
- Added `StageCategoryMap::hasCategory()` and updated `XtProductWriter` category resolution:
  - keep the product's own category when it exists in stage
  - if the product is a slave and its own category is missing, inherit the master's category
  - if no valid category exists after that, export the product without a category relation
- Confirmed locally in the PHP container that:
  - a slave product without an imported own category resolves to the master's imported category
  - generated attribute entity payloads now include `"attributes_templates_id": 1`

# Open points

- The remote live endpoint currently configured in `XT_API_URL` (`10.0.1.104/wela-api`) still rejects the new attribute field with `Unzulaessige XT-Feldbelegung.` This means the repository-side `wela-api/index.php` change is correct, but the remote deployed `wela-api` must also be updated before live product exports with `attributes_templates_id` can succeed.
- The requested `testshop` marker was not present on the resolved `shop.welafix.de -> 10.0.1.104` responses during verification. The fetched `/produkte` page still rendered the normal WELAfix frontend and only showed `Paketgruppen`, so the exact intended target shop instance could not be confirmed from the HTTP response.
- With the explicit `Internet = 0` rule, categories that only exist in AFS as `Internet = 1` stay absent from stage by design. Products therefore either use:
  - their own imported category,
  - or, if they are slaves, the imported category of their master,
  - or no category assignment.

# Validation steps

- Ran syntax checks:
  - `docker compose exec -T php php -l /app/config/normalize.php`
  - `docker compose exec -T php php -l /app/config/xt_write.php`
  - `docker compose exec -T php php -l /app/wela-api/index.php`
  - `docker compose exec -T php php -l /app/src/Service/StageCategoryMap.php`
  - `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- Queried local runtime behavior in Docker to confirm:
  - slave/master category inheritance resolves correctly via `XtProductWriter`
  - attribute entity payload generation includes `attributes_templates_id = 1`
- Confirmed `config/sources.php` is back on `Internet = 0` for AFS category import.
- Queried the live product queue and reset transient product `error` / `processing` rows from the failed remote `wela-api` mismatch back to `pending`
- Fetched `https://shop.welafix.de/produkte` via `curl --resolve shop.welafix.de:443:10.0.1.104` and checked returned HTML markers

# Recommended next step

Deploy the updated repository version of `wela-api/index.php` to the live `10.0.1.104/wela-api` endpoint, then rerun the product export worker so the new `attributes_templates_id = 1` writes can succeed against the real shop.
