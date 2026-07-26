# Hauptbild-Delta-Korrektur

## Task

Prüfen und korrigieren, warum das Hauptbild (`xt_products.products_image`) bei bestehenden Artikeln nicht gesetzt wurde.

## Files read

- `AGENTS.md`
- `config/delta.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`

## Changed files

- `config/delta.php`
- `src/Service/ProductDeltaService.php`
- `docs/agent-results/2026-07-23-primary-product-image-delta-fix.md`

## Summary

- Der Produkt-Writer übernimmt das Bild auf Position 1 aus `stage_product_media` in `products_image`.
- Das Hauptbild ist nun Teil des Produkt-Delta-Hashs und des XT-Mirror-Vergleichs.
- Dadurch löst eine Änderung des ersten Produktbilds künftig einen Produkt-Export aus.
- 333 bestehende Produkte mit abweichendem oder fehlendem Hauptbild wurden gezielt einmalig neu eingeplant und exportiert.
- `HA-000` wurde in XT mit `ECON-160.jpg` als Hauptbild geprüft.

## Open points

- Der XT-Mirror wird erst beim nächsten Mirror-Lauf den neuen Bildstand anzeigen; der Export selbst ist abgeschlossen.

## Validation steps

- `php -l src/Service/ProductDeltaService.php`
- `php -l config/delta.php`
- `php run_delta.php`
- `php run_export_queue.php`
- Direkte Lesekontrolle von `xt_products.products_image` für `HA-000`.

## Recommended next step

Beim nächsten regulären Pipeline-Lauf prüfen, dass ein unveränderter Durchlauf keine neuen Produkt-Queue-Einträge erzeugt.
