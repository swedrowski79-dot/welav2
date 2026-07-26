# Task

Fix localized SEO generation for products and categories so all languages use the same structure as German:

- category paths use translated category names
- product paths use translated category names plus translated product name
- path order starts at the lowest category level and then walks upward
- existing SEO URLs must not be overwritten

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/xt_write.php`
- `config/languages.php`
- `run_export_queue.php`
- `src/Service/StageCategoryMap.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`

# Changed files

- `src/Service/StageCategoryMap.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-04-16-localized-seo-generation.md`

# Summary

- Hard-reset the local stage database and rebuilt internal data with:
  - `run_import_all.php`
  - `run_merge.php`
  - `run_expand.php`
- Added `StageCategoryMap::pathSegmentsLeafFirst()` so SEO generation can build paths from the lowest category upward.
- Changed category SEO generation in `XtCategoryWriter` to use `leaf -> parent -> ...` path order for all languages.
- Changed product SEO generation in `XtProductWriter` to:
  - use the resolved export category
  - use `leaf -> parent -> ... -> product`
  - keep translated category names and translated product names per language
- Existing SEO row protection remains unchanged in `wela-api/index.php` via `wela_preserve_existing_seo_url_columns()`, so existing `url_text` / `url_md5` values are still preserved when a matching SEO row already exists.
- Live category export succeeded against XT:
  - `71` category queue rows processed
  - `71` category queue rows finished with `done`
- Product SEO generation is implemented in code and verified locally, but full live product export remains blocked by the known remote `wela-api` mismatch for `attributes_templates_id`.

# Open points

- The remote deployed `10.0.1.104/wela-api` still rejects product attribute writes with `Unzulaessige XT-Feldbelegung.` Because of that, product exports were not pushed live in this task even though product SEO path generation in the repository code is correct.
- After the reset and category-only export, the remaining queue is intentionally still pending:
  - `product pending = 5350`
  - `media pending = 5331`
  - `document pending = 2853`
- Existing SEO URL preservation is code-backed and left enabled, but the live shop database had already been reset before this task, so there were no prior shop SEO rows available for a destructive overwrite test.

# Validation steps

- Ran syntax checks:
  - `docker compose exec -T php php -l /app/src/Service/StageCategoryMap.php`
  - `docker compose exec -T php php -l /app/src/Service/XtCategoryWriter.php`
  - `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- Hard-reset stage DB:
  - dropped and recreated `stage_sync`
  - re-imported `database.sql`
- Rebuilt stage data:
  - `docker compose exec -T php php /app/run_import_all.php`
  - `docker compose exec -T php php /app/run_merge.php`
  - `docker compose exec -T php php /app/run_expand.php`
- Generated sample SEO URLs locally via writer logic:
  - category `281`
    - `de/absaugrohre/absaugrohre-zubeh-r`
    - `en/extraction-pipes/extraction-ducts-accessories`
    - `fr/tuyaux-d-aspiration/tuyauteries-d-aspiration-accessoires`
    - `nl/afzuigbuizen/afzuigbuizen-toebehoren`
  - product `68`
    - `de/erg-nzende-rohrbauteile/absaugrohre-zubeh-r/e-stutzen-gani-nw080-verzinkt`
    - `en/supplementary-duct-components/extraction-ducts-accessories/e-spigot-gani-nw080-galvanized`
    - `fr/composants-de-tuyauterie-compl-mentaires/tuyauteries-d-aspiration-accessoires/embout-e-gani-nw080-zingu`
    - `nl/aanvullende-leidingcomponenten/afzuigbuizen-toebehoren/e-tule-gani-nw080-verzinkt`
- Ran category-only live export and mirror refresh:
  - category export worker processed `71/71` successfully
  - `xt_mirror_seo_url` returned localized category SEO rows, e.g.
    - `en/extraction-ducts-accessories`
    - `fr/tuyauteries-d-aspiration-accessoires`
    - `nl/afzuigbuizen-toebehoren`
    - deeper paths such as `en/fire-dampers/fire-and-explosion-protection-incl-accessories`

# Recommended next step

Deploy the updated repository `wela-api/index.php` to `10.0.1.104/wela-api`, then run the pending product queue so the new localized product SEO paths can be written live as well.
