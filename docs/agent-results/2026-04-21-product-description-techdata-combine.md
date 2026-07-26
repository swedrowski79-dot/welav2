# Task

`xt_products_description.products_description` so anpassen, dass aus den Produkt-Translationen nicht nur die Beschreibung, sondern Beschreibung **plus** technische Daten exportiert werden.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/merge.php`
- `config/xt_write.php`
- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`

# Changed files

- `config/xt_write.php`
- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`
- `docs/agent-results/2026-04-21-product-description-techdata-combine.md`

# Summary

- `xt_products_description.products_description` wird nicht mehr nur aus `translation.description` befuellt.
- Stattdessen wird jetzt zentral ein kombinierter Text aus:
  - `translation.description`
  - `translation.technical_data_html`
  gebildet.
- Die Verkettung erfolgt mit einer Leerzeile dazwischen und ignoriert leere Teile.
- Der Delta-/Mirror-Vergleich wurde auf dieselbe Logik angepasst, damit Produkte nach dem Export nicht wegen unterschiedlicher Vergleichsbasis erneut dauerhaft als abweichend erkannt werden.

# Open points

- Kein offener technischer Punkt aus dieser Aenderung.
- Ein echter Export-/Mirror-Spotcheck fuer einen Beispielartikel ist weiterhin sinnvoll, wenn du den Text direkt im Shop gegenpruefen willst.

# Validation steps

- `docker compose exec -T php php -l config/xt_write.php`
- `docker compose exec -T php php -l src/Service/XtProductWriter.php`
- `docker compose exec -T php php -l src/Service/ProductDeltaService.php`
- Mapping-Spotcheck:
  - `xt_products_description.products_description` zeigt jetzt auf `calc:product_translation_description`

# Recommended next step

Einen gezielten Produkt-Export fuer einen Beispielartikel fahren und danach im XT-Mirror oder direkt im Shop pruefen, dass `products_description` Beschreibung und technische Daten gemeinsam enthaelt.
