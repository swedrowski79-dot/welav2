# Task

Temporarily unblock XT mirror reads by making `wela-api` `fetch_rows` permissive for all existing XT tables and columns without changing write behavior.

# Files read

- `wela-api/index.php`
- `config/xt_mirror.php`

# Changed files

- `wela-api/index.php`
- `docs/agent-results/2026-04-15-wela-api-read-unblock.md`

# Summary

- Replaced the `fetch_rows` hardcoded table allowlist with live table-existence validation.
- Replaced the `fetch_rows` hardcoded `read_fields` allowlist with live column-existence validation.
- Added primary-key discovery for arbitrary readable tables so pagination ordering still works without per-table config.
- Made `wela-api/index.php` boot safely even when the deployed API does not have access to `../src/Service/XtWriteDependencyMap.php` and `../config/xt_write.php`.
- Preserved all existing write allowlists and write endpoint behavior.

# Open points

- `lookup_map` still uses its existing allowlist; this change only unblocks `fetch_rows` as requested.

# Validation steps

- `docker compose exec -T php php -l /app/wela-api/index.php`
- Verified `fetch_rows` now uses `wela_existing_table()`, `wela_existing_field_list()`, and `wela_table_primary_key()` instead of `wela_allowed_tables()` / `read_fields`.
- Captured live external API responses for `health` and `fetch_rows(xt_products, ...)`; both returned valid JSON.
- Verified live `fetch_rows` success for all mirror source tables: `xt_products`, `xt_products_description`, `xt_categories`, `xt_categories_description`, `xt_products_to_categories`, `xt_media`, `xt_media_link`, `xt_plg_products_attributes`, `xt_plg_products_attributes_description`, `xt_plg_products_to_attributes`, `xt_seo_url`.
- Ran `docker compose exec -T php php /app/run_xt_mirror.php`; the mirror refresh completed successfully.

# Recommended next step

No immediate next step required for the XT mirror unblock.
