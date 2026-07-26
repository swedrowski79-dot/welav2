## Task

Analyse und Umsetzung, damit `wela-api` den XT-Bootstrap nicht mehr bei jedem Request laedt, sondern nur noch bei echter Bildverarbeitung.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `wela-api/index.php`
- `wela-api/bootstrap/xtcommerce.php`
- `wela-api/config.php`
- `wela-api/xt_image_helpers.php`
- `wela-api/seo_helpers.php`

## Changed files

- `wela-api/index.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/XtMediaDocumentWriter.php`
- `wela-api/seo_helpers.php`
- `docs/agent-results/2026-07-17-wela-api-performance-review.md`

## Summary

- Der globale XT-Bootstrap am Anfang von `wela-api/index.php` wurde entfernt.
- `XT_COMMERCE_ROOT` bleibt als Konfiguration erhalten, aber `bootstrap/xtcommerce.php` wird nicht mehr pauschal bei jedem Request geladen.
- Neue Funktion `wela_require_xt_bootstrap()` laedt den XT-Kontext erst dann, wenn er wirklich benoetigt wird.
- Der Upload-/Bildpfad ruft den Bootstrap jetzt erst direkt vor `wela_process_xt_commerce_image()` auf.
- Reine PDO-Actions wie `sync_product`, `sync_category`, `lookup_map`, `fetch_rows` und `refresh_shop_state` laufen damit ohne XT-Bootstrap.
- `wela_upsert_row()` besitzt jetzt einen nativen Upsert-Pfad per `INSERT ... ON DUPLICATE KEY UPDATE` fuer die haeufigen Child-/Relationstabellen.
- Native Upserts sind bewusst nur fuer diese Tabellen aktiv:
  - `xt_categories_description`
  - `xt_products_description`
  - `xt_products_to_categories`
  - `xt_plg_products_attributes`
  - `xt_plg_products_attributes_description`
  - `xt_plg_products_to_attributes`
  - `xt_seo_url`
- Die Haupttabellen `xt_products` und `xt_categories` bleiben vorerst auf dem alten Select-then-update-Pfad, damit kein Risiko durch unbekannte `external_id`-Indexdefinitionen entsteht.
- Batch-Unterstuetzung gilt jetzt nicht mehr nur fuer Produkte:
  - Produkte nutzen weiter echten API-Batch ueber `sync_products_batch`
  - Kategorien nutzen jetzt ebenfalls echten API-Batch ueber `sync_categories_batch`
  - Media und Dokumente unterstuetzen jetzt den Batch-Pfad des Workers ebenfalls, laufen dort gesammelt, verwenden intern aber weiter ihre bestehenden Einzeloperationen
- Folgefehler nach der Batch-Erweiterung behoben:
  - `Call to undefined method XtCategoryWriter::decodeQueuePayload()`
  - Ursache: der Payload-Decoder lag nur als private Methode im Produkt-Writer
  - Fix: `decodeQueuePayload()` in die gemeinsame Basisklasse `AbstractXtWriter` verschoben
- Produkt-Batch in `wela-api/index.php` wurde fuer den Hauptproduktsatz optimiert:
  - `sync_products_batch` sammelt jetzt die `external_id`-Werte des gesamten Batches
  - vorhandene `xt_products` werden mit einem gemeinsamen `SELECT ... WHERE external_id IN (...)` vorab geladen
  - `wela_sync_product_request()` bekommt den vorab geladenen Produktdatensatz als Kontext
  - fuer bestehende Produkte entfallen dadurch im Batch-Pfad die bisherigen Einzel-`SELECT`s fuer Existenzpruefung und Produkt-Upsert
  - bestehende Produkte werden dann gezielt per `products_id` aktualisiert
  - neue Produkte werden weiterhin einzeln eingefuegt, aber ohne vorherigen extra Existenz-Lookup aus dem Batch-Pfad
- Dieser erste Schritt beschleunigt bewusst nur den Hauptsatz `xt_products`.
- Zweiter Produkt-Optimierungsblock fuer SEO wurde umgesetzt:
  - `XtProductWriter` liefert jetzt `auto_generate_text` aus den bereits vorhandenen Uebersetzungen mit
  - die Produkt-SEO-Generierung nutzt damit fuer den Slug bevorzugt den Request-Text statt erneut `xt_products_description` zu lesen
  - bestehende Produkt-SEO-Zeilen werden im Batch fuer bekannte Produkte vorab geladen
  - Existenzpruefung und Redirect-Vergleich fuer bestehende Produkt-SEO-Zeilen koennen dadurch den Batch-Cache nutzen statt pro Sprache erneut in `xt_seo_url` zu lesen
- Redirect-Sonderlogik fuer SEO wurde ebenfalls auf Sammelverarbeitung umgebaut:
  - Redirect-Faelle werden jetzt erst gepuffert statt sofort einzeln geschrieben
  - pro Request werden sie vor dem Commit gesammelt geflusht
  - vorhandene Redirects werden vor dem Insert gesammelt vorab geladen
  - doppelte Redirects im selben Request werden dedupliziert
  - bei Fehlern wird der Redirect-Puffer verworfen
- Produkt-Kindtabellen werden im API-Pfad jetzt blockweise statt einzeln geschrieben:
  - `xt_products_description`
  - `xt_products_to_categories`
  - `xt_plg_products_attributes_description`
  - `xt_plg_products_to_attributes`
  - `xt_seo_url`
- Diese Tabellen werden jetzt pro Produkt-Request als Multi-Row-Upsert gruppiert geschrieben, statt fuer jede einzelne Zeile eine separate Upsert-Abfrage auszufuehren.
- Dritter Produkt-Optimierungsblock fuer SEO-Hilfsreads wurde umgesetzt:
  - der Produkt-SEO-Pfad nimmt die Master-Kategorie jetzt bevorzugt direkt aus den aktuellen `category_relations` des Request-Payloads
  - dadurch entfaellt im Batch fuer Produkt-SEO typischerweise der bisherige Zusatz-Read auf `xt_products_to_categories`
  - wiederholte Reads auf `xt_products_description`/`xt_categories_description`, `xt_categories.parent_id` und `xt_seo_url.url_text` werden jetzt pro API-Request gecacht
  - vor allem bei vielen Produkten in denselben Kategorien reduziert das die wiederholten SEO-Hilfsabfragen innerhalb eines Batches deutlich
- Vierter Produkt-Optimierungsblock fuer Attribute wurde umgesetzt:
  - `sync_products_batch` sammelt jetzt auch alle `attribute_model`-Werte des Batches
  - vorhandene `xt_plg_products_attributes`-Zeilen werden mit einem gemeinsamen `SELECT ... WHERE attributes_model IN (...)` vorab geladen
  - `wela_sync_product_request()` nutzt diese vorab geladenen Attributzeilen als Kontext
  - fuer bestehende Attribute entfallen dadurch im Batch-Pfad die bisherigen Einzel-`SELECT`s zur Existenzpruefung
  - bestehende Attribute werden danach gezielt per `attributes_id` aktualisiert
  - auch im Einzel-Request wird bei vorhandenen Attributen zuerst einmal gesammelt vorab geladen statt pro Attribut einzeln gesucht
- Fuenfter Produkt-Optimierungsblock fuer SEO-Kollisionen wurde umgesetzt:
  - `wela_validate_seo_db_key_link()` laedt Konflikte jetzt gesammelt pro Basis-Slug statt pro Kandidat einzeln
  - fuer denselben Basis-Slug werden vorhandene XT-URLs jetzt mit einem gebuendelten Lookup geladen und in PHP ausgewertet
  - innerhalb desselben Requests werden bereits vergebene Kandidaten zusaetzlich reserviert, damit sie nicht erneut einzeln geprueft werden
- Sechster Produkt-Optimierungsblock fuer Restpfade wurde umgesetzt:
  - im Einzel-Request wird ein bestehendes Produkt jetzt einmal vorab geladen und direkt weitergereicht
  - dadurch entfaellt der bisherige zusaetzliche Existenzcheck in `wela_prepare_product_columns()`
  - bestehende `xt_products_to_categories`-Relationen werden jetzt batchweise vorab geladen
  - bestehende `xt_plg_products_to_attributes`-Relationen werden jetzt batchweise vorab geladen
  - `replace_categories` und `replace_attributes` loeschen jetzt nur noch veraltete Relationen statt pauschal alle Relationen des Produkts
- Siebter Produkt-Optimierungsblock fuer Attribut-Hauptwrites wurde umgesetzt:
  - Parent- und Child-Attribute werden jetzt gesammelt vorbereitet statt sofort einzeln geschrieben
  - wenn `xt_plg_products_attributes` zur Laufzeit einen passenden Unique-Key auf `attributes_model` hat, werden diese Attribut-Hauptsaetze blockweise per Batch-Upsert geschrieben
  - danach werden die geschriebenen Attributzeilen gesammelt neu geladen, damit IDs fuer Beschreibungen und Relationen direkt weiterverwendet werden koennen
  - falls der Unique-Key nicht vorhanden ist, bleibt automatisch der bisherige sichere Einzel-Fallback aktiv
- Achter Produkt-Optimierungsblock fuer den Produkt-Hauptsatz wurde umgesetzt:
  - `xt_products` nutzt jetzt ebenfalls den nativen Upsert-Pfad, sobald zur Laufzeit ein passender Unique-Key auf `external_id` vorhanden ist
  - ohne passenden Unique-Key bleibt automatisch der bisherige sichere Insert/Update-Fallback aktiv
- Der naechste groessere Restblock ist jetzt vor allem allgemeine XT-seitige Write-Last.

## Open points

- Optional spaeter auch `xt_products` und `xt_categories` auf native Upserts umstellen, sobald die Unique-Key-Situation fuer `external_id` sicher bekannt ist.
- Prüfen, welche XT-Tabellen eindeutige Keys für `INSERT ... ON DUPLICATE KEY UPDATE` bereits besitzen.
- Prüfen, ob ungültiges JSON durch PHP-Warnings/Notices oder durch vorgeschaltete Serverausgaben entsteht.
- Optional `JSON_PRETTY_PRINT` aus API-Responses entfernen.
- Optional spaeter echte API-Batch-Endpunkte auch fuer Media/Dokumente bauen, falls deren Volumen kuenftig relevant wird.
- Naechster Performance-Hebel fuer Produkte:
  - pruefen, ob die XT-Seite durch viele einzelne `UPDATE`s auf Haupttabellen limitiert
  - messen, ob danach eher API/PHP oder XT-MySQL der Flaschenhals ist
- Stand der verbliebenen Einzelabfragen im Produktpfad nach den bisherigen Optimierungen:
  - `wela_upsert_xt_product_row()` bleibt nur noch Fallback fuer Shops ohne passenden Unique-Key auf `external_id`
  - `wela_upsert_xt_attribute_row()` bleibt nur noch Fallback fuer Shops ohne passenden Unique-Key auf `attributes_model`
  - Delete-Schritte fuer `xt_products_to_categories` und `xt_plg_products_to_attributes` koennen weiterhin pro Produkt ein `DELETE` ausloesen, aber nur noch fuer wirklich veraltete Relationen
  - Redirect-Hilfsabfragen auf `xt_seo_url_redirect` sind bereits gebuendelt, aber die optionale `master_key`-Ermittlung nutzt noch einen einzelnen Initial-Read je Request

## Validation steps

- `docker compose exec -T php php -l /app/wela-api/index.php`
- `docker compose exec -T php php -l /app/wela-api/seo_helpers.php`
- `docker compose exec -T php php -l /app/src/Service/AbstractXtWriter.php`
- `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
- `docker compose exec -T php php -l /app/src/Service/XtCategoryWriter.php`
- `docker compose exec -T php php -l /app/src/Service/XtMediaDocumentWriter.php`
- `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- Smoke-Check des API-Einstiegs:
  - `docker compose exec -T php php /app/wela-api/index.php`
  - erwartetes Ergebnis ohne Header-Kontext: API-Key-Fehler

## Recommended next step

- Als naechstes einen echten Messlauf mit kleinem Batch fahren und danach pruefen, ob die verbleibende Last nun ueberwiegend von XT selbst und nicht mehr von der API-Read/Write-Orchestrierung kommt.
