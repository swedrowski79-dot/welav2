## Task

Pruefen, warum neue Produkte im Shop nicht eingebunden werden, und den Exportpfad so korrigieren, dass Produkt-Kategorie-Zuordnungen auch dann geschrieben werden, wenn die Kategorie nicht in `stage_categories`, aber bereits im Shop vorhanden ist.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `src/Service/XtProductWriter.php`
- `src/Service/StageCategoryMap.php`
- `src/Service/ProductDeltaService.php`
- `config/xt_write.php`
- `wela-api/index.php`

## Changed files

- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-04-20-new-products-category-link-fix.md`

## Summary

- Ursache: `XtProductWriter` hat Produkt-Kategorie-Links nur dann gebaut, wenn `category_afs_id` auch in `stage_categories` vorhanden war.
- Das war zu streng. Fuer neue Produkte mit Kategorien, die bereits im Shop existieren, aber lokal nicht in `stage_categories` liegen, wurde dadurch `category_relations = []` erzeugt.
- Folge: Produkte wurden im Shop angelegt/aktualisiert, aber ohne Eintrag in `xt_products_to_categories`.

Fix:
- In `resolvedCategoryId()` und `resolvedCategoryIdForSku()` wird `category_afs_id` jetzt direkt verwendet, sobald es gesetzt ist.
- Ob die Kategorie in XT existiert, prueft danach ohnehin die `ref:xt_categories...`-Aufloesung. Dadurch gibt es keine stille Unterdrueckung mehr.

Direkt validiert:
- Vor dem Fix lieferte `prepareProductSyncPayload()` fuer Produkt `60039` keine Kategoriezuordnung.
- Nach dem Fix liefert derselbe Produkt-Payload:
  - `category_relations = [{"columns":{"categories_id":186,"master_link":1,"store_id":1}}]`

Nachgezogener Lauf:
- `run_delta.php` erneut ausgefuehrt
- betroffene Produkte wurden erneut als `pending update` in die Queue geschrieben
- `run_export_queue.php 500` gestartet

Live-Shop-Bestaetigung ueber XT-API:
- Fuer die RSSG-Produkte sind die Kategorie-Links jetzt vorhanden:
  - `59492 -> products_id 10723 -> categories_id 244`
  - `59494 -> products_id 10726 -> categories_id 244`
  - `59495 -> products_id 10727 -> categories_id 244`
  - `59498 -> products_id 10730 -> categories_id 244`
  - `59499 -> products_id 10731 -> categories_id 244`
  - `59500 -> products_id 10729 -> categories_id 244`

## Open points

- Der Export-Worker lief zum Zeitpunkt der Pruefung noch weiter.
- Die HOTR-/S-BSBE-Faelle (`60039`, `60040`, `60909`) standen noch als neue `pending update` in der Queue und waren noch nicht verarbeitet.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- Reflection-Test auf `prepareProductSyncPayload()` fuer Produkt `60039`
- `docker compose exec -T php php /app/run_delta.php`
- `docker compose exec -T php php /app/run_export_queue.php 500`
- XT-API-Abfrage auf `xt_products_to_categories` fuer die betroffenen Live-Produkt-IDs
- Queue-Pruefung:
  - `SELECT ... FROM export_queue WHERE entity_type='product' AND entity_id IN (...)`

## Recommended next step

- Den laufenden Export-Worker fertig arbeiten lassen und danach die noch offenen Produkt-IDs `60039`, `60040` und `60909` erneut live gegen `xt_products_to_categories` pruefen.
