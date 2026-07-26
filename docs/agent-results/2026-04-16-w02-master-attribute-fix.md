## Task

Investigate the remaining attribute problem using master article `W02-000`, verify whether the article online state is wrong, and fix only the proven cause without resetting the shop.

## Files read

- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `config/expand.php`
- `config/xt_write.php`
- `src/Service/Normalizer.php`
- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`
- `database.sql`

## Changed files

- `src/Service/Normalizer.php`
- `docs/agent-results/2026-04-16-w02-master-attribute-fix.md`

## Summary

- `W02-000` was not failing because its own translation rows lost attributes. The master article simply has no attribute slots in `raw_extra_article_translations` / `stage_product_translations`; the attributes exist on its slave variants.
- The proven bug was in master detection:
  - AFS normalized data contained `variant_flag = master` for `W02-000`
  - `Normalizer` only treated exact `Master` as a master
  - result: `product_type = slave`, `is_master = 0`, and the master article was exported to XT as a normal product
- Minimal fix:
  - made `calc:product_type_from_variant_flag` and `calc:is_master` case-insensitive
  - reused the same normalized variant flag handling for the related slave/master calculations
- After rerunning the configured flow:
  - `W02-000` is `is_master = 1` in stage
  - `W02-000` is `products_master_flag = 1` in `xt_mirror_products`
  - slave products still keep `products_master_model = W02-000`
  - slave products keep their multilingual attribute rows in XT mirror

## Open points

- No separate online-state defect was proven for this family:
  - `W02-000` was already `online_flag = 1` in stage
  - `W02-000` was already `products_status = 1` in XT mirror
- The repository currently still imports AFS articles with `Internet = 1` in `config/sources.php`. This pass did not change article import semantics because the reported online issue was not reproducible on the checked family.
- There is still an unrelated old XT product with `products_master_model = W02-000` and `products_model = 7948` in the mirror. That row was not introduced by this fix and was left untouched.

## Validation steps

- Ran the configured pipeline without resetting the shop:
  - `docker compose exec -T php php /app/run_full_pipeline.php`
- Processed additional queue work and refreshed mirror:
  - `docker compose exec -T php php /app/run_export_queue.php`
  - `docker compose exec -T php php /app/run_export_queue.php`
  - `docker compose exec -T php php /app/run_xt_mirror.php`
- Rechecked `W02-000` family in raw/stage/XT mirror:
  - stage now marks `W02-000` as master
  - XT mirror now shows `products_master_flag = 1` for `W02-000`
  - XT mirror still contains multilingual attribute rows on the slave products of the family

## Recommended next step

If you want the article `Internet` semantics changed from `1 = online` to `0 = online` for products as well, make that an explicit config task so article source filtering and article online normalization can be updated together and then revalidated end-to-end.
