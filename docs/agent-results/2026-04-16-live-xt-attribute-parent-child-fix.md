## Task

Check the real attribute structure in the live XT shop database, compare it with the current export logic, and fix the product attribute export so the shop receives the correct attribute name/value pairs.

## Files read

- `config/xt_write.php`
- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/WelaApiClient.php`
- `wela-api/index.php`
- `database.sql`

## Changed files

- `config/xt_write.php`
- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`
- `wela-api/index.php`
- `docs/agent-results/2026-04-16-live-xt-attribute-parent-child-fix.md`

## Summary

- The live XT shop confirmed the actual attribute model:
  - parent attribute row = attribute name
  - child attribute row = attribute value
  - the product relation points to the child attribute
  - `xt_plg_products_to_attributes.attributes_parent_id` stores the parent attribute id
  - both parent and child labels are stored in `xt_plg_products_attributes_description.attributes_name`
  - `attributes_desc` stays empty
- The previous export logic was wrong for this shop model:
  - it wrote one flat attribute per slot
  - `attributes_name = name`
  - `attributes_desc = value`
  - no parent/child attribute tree was created
- Minimal aligned fix:
  - `config/xt_write.php`
    - attribute entities now read `attributes_parent` from the prepared attribute payload
    - attribute descriptions now write `display_name` into `attributes_name`
    - `attributes_desc` is written as empty
    - product-to-attribute relations now read `attributes_parent_id` from the prepared relation payload
  - `XtProductWriter`
    - builds one parent entity per attribute name
    - builds one child entity per attribute value under that parent
    - relates products only to the child entity
    - passes the parent attribute model so the XT API can resolve parent ids inside the same sync call
  - `wela-api/index.php`
    - `sync_product` now resolves `parent_attribute_model` for attribute entities and attribute relations
    - this allows parent ids to be assigned correctly in one transaction
  - `ProductDeltaService`
    - mirror attribute comparison now reconstructs `attribute_name -> attribute_value` from the live XT parent/child structure instead of reading only child description rows

## Open points

- The storefront HTML itself was not usable for inspection because the public shop page currently crashes with an XT runtime/license/bootstrap error. Validation therefore used the live XT database through the API, which is the authoritative source for this task.
- Existing legacy attributes already present in the shop use older ids/models and remain untouched. The new export writes the correct structure for the synced products without needing to rewrite unrelated historical rows.

## Validation steps

- Updated `wela-api/index.php` locally and paused until the file was copied to the XT host.
- Rebuilt/exported with the updated logic:
  - `docker compose exec -T php php /app/run_expand.php`
  - repeated `docker compose exec -T php php /app/run_export_queue.php`
  - `docker compose exec -T php php /app/run_xt_mirror.php`
- Final queue status:
  - `category done = 487`
  - `document done = 9559`
  - `media done = 5331`
  - `product done = 13131`
  - no pending/error rows remained
- Direct live XT API check for `W02-01-1020` (`products_id = 2277`) returned the correct parent/child pairs:
  - `Durchmesser -> 100mm`
  - `Ausführung -> hängend`
  - `Länge -> 2,0m`
- Direct live XT API check also confirmed `W02-000` remains the master product with `products_master_flag = 1`.

## Recommended next step

If you want, the next focused pass should inspect a second and third product family with different attribute labels (for example one of the `GABD` or `GABP` products) to confirm the parent/child reuse pattern across other product groups.
