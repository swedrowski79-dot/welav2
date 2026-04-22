## Task

Produkt-Export erneut ausfuehren, Laufzeit messen, akute Export-Blocker im XT-Ziel beheben und den Transferstatus verifizieren.

## Files read

- `src/Service/XtProductWriter.php`
- `config/xt_write.php`
- `config/delta.php`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`
- `database.sql`
- `wela-api/index.php`

## Changed files

- `src/Service/WelaApiClient.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`
- `docs/agent-results/2026-04-16-product-export-retry-fix.md`

## Summary

- `XtProductWriter` normalisiert jetzt problematische Produktspalten fuer das echte XT-Zielschema:
  - `products_shippingtime_nostock` -> `null` statt Leerstring
  - `products_unit` -> `0`, wenn aus Stage kein numerischer XT-Wert kommt
  - `google_product_cat` -> `null` statt Leerstring
- Der Produkt-Export wurde im Repo auf einen robusteren Attribut-Pfad umgestellt:
  - `sync_product` schreibt nur noch Produkt, Uebersetzungen, Kategorien und SEO
  - Attribut-Stammdaten, Attribut-Uebersetzungen und Produkt-Attribut-Links werden separat ueber `upsert_row` / `delete_rows` geschrieben
- Die Repo-`wela-api` wurde dafuer erweitert:
  - `upsert_row` erlaubt jetzt auch `xt_plg_products_attributes`, `xt_plg_products_attributes_description`, `xt_plg_products_to_attributes`
  - `delete_rows` erlaubt jetzt auch `xt_plg_products_to_attributes`
  - `upsert_row` akzeptiert dabei auch zusammengesetzte Primaerschluessel
- Ein erfolgreicher Remote-Spotcheck bestaetigte `external_id=63152 -> products_id=13253` im XT-Ziel.
- Der verbleibende Export-Blocker liegt ausschliesslich im Attribut-Pfad der deployten Ziel-API:
  - mit `attributes_templates_id` im `sync_product`-Attributblock: `Unzulaessige XT-Feldbelegung.`
  - ohne `attributes_templates_id`: `Field 'attributes_templates_id' doesn't have a default value`
  - generische API-Wege fuer Attributtabellen (`upsert_row`, `delete_rows`) sind auf dem Ziel fuer diese Tabellen nicht freigeschaltet und antworten mit `Unzulaessige XT-Tabelle.`
- Aktueller Produkt-Queue-Stand nach den Untersuchungen:
  - `1870` done
  - `2348` error
  - `1132` pending

## Open points

- Alle verbleibenden Produktfehler betreffen Produkte mit Attributen.
- Der Zielshop ist aktuell widerspruechlich konfiguriert: Das Schema verlangt `attributes_templates_id`, der deployte `sync_product`-Pfad akzeptiert es aber nicht.
- Ohne Update der deployten Ziel-`wela-api` oder des Zielschemas kann der Attribut-Export lokal nicht sauber zu Ende gebracht werden.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
- `docker compose exec -T php php -l /app/wela-api/index.php`
- gezielte Produkt-Exportlaeufe mit `ExportQueueWorker` nur fuer `product_export_queue`
- Queue-Pruefung in `stage_sync.export_queue`
- Remote-Lookup in `xt_products` fuer einen erfolgreich exportierten Artikel
- Remote-Probe fuer Attributtabellen und API-Aktionen (`upsert_row` aktuell noch `Unzulaessige XT-Tabelle.` auf dem deployten Ziel)

## Recommended next step

Die deployte `wela-api` auf dem Zielshop muss jetzt neu eingespielt werden, damit der Repo-Fix greift. Erst mit dieser aktualisierten API stehen die separaten Attribut-Upserts/Deletes zur Verfuegung und danach koennen die verbleibenden `product`-Queue-Eintraege erfolgreich neu verarbeitet werden.
