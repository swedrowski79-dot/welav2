# T-032 - Preserve existing SEO URLs but update SEO metadata

## Title

Preserve existing XT SEO URLs while continuing to update SEO metadata on product sync.

## Goal

Keep manually maintained or previously generated SEO URL fields unchanged in XT while allowing metadata updates and creation of missing SEO rows during product sync.

## Requirements

- do not overwrite existing SEO URL fields such as `url_text` and `url_md5`
- if an SEO row already exists, update only the allowed metadata fields
- if no SEO row exists, create the full SEO entry
- keep the implementation idempotent
- do not create duplicate SEO rows
- keep queue/export-state confirmation behavior correct
- do not break existing product, media, or document sync

## Implementation notes

- The product SEO change was kept inside the existing `sync_product` XT API transaction.
- SEO handling now works per target row instead of skipping the whole SEO block when any product SEO row already exists.
- For an existing `xt_seo_url` identity (`link_type`, `link_id`, `language_code`, `store_id`):
  - `url_text` stays unchanged
  - `url_md5` stays unchanged
  - `meta_title`, `meta_description`, and `meta_keywords` may still update through the normal upsert path
- Missing SEO rows are still created with the full generated payload, so partial-language backfill remains possible without duplicate rows.
- Product, media, and document sync flow were not otherwise changed.

## Validation notes

- syntax checks passed for:
  - `wela-api/index.php`
  - `src/Service/WelaApiClient.php`
  - `src/Service/XtProductWriter.php`
- isolated XT/API validation used a temporary MySQL database plus temporary local `wela-api` server
- observed behavior:
  - initial sync created SEO rows for `de` and `en`
  - after manually changing the existing German URL to `manual-existing-url`, the next sync:
    - preserved `manual-existing-url`
    - updated German meta fields
    - recreated the missing English row with the generated URL
  - a third identical sync produced no duplicate rows
- queue/export-state behavior stayed on the same `sync_product` transaction path

## Status

- [x] implemented
- [x] validated
- [x] moved to `docs/tickets/done/`
- [x] result file written
- [ ] pushed

## Result

Completed locally; commit hash will be recorded after commit creation.
