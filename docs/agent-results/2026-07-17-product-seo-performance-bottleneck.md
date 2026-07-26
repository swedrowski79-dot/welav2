## Task

Die echte Performance-Ursache der langsamen Artikelsynchronisation in `wela-api` herausfinden, mit realen Messungen belegen und den Hotspot gezielt optimieren, ohne die fachliche Verarbeitung der Schnittstelle zu aendern.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/sources.php`
- `config/xt_write.php`
- `config/languages.php`
- `run_export_queue.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtProductWriter.php`
- `src/Service/StageCategoryMap.php`
- `wela-api/index.php`
- `wela-api/seo_helpers.php`
- `wela-api/src/ProductSyncService.php`
- `wela-api/config.php`
- `wela-api/config.php.example`
- `docs/agent-results/2026-07-17-export-worker-batch-timing.md`
- `docs/agent-results/2026-07-17-wela-api-performance-review.md`
- `docs/agent-results/2026-07-17-product-batch-json-performance.md`

## Changed files

- `wela-api/seo_helpers.php`
- `wela-api/src/ProductSyncService.php`
- `wela-api-xt/seo_helpers.php`
- `wela-api-xt/src/ProductSyncService.php`
- `scripts/benchmark_wela_api_products.php`
- `docs/agent-results/2026-07-17-product-seo-performance-bottleneck.md`

## Summary

- Die Verlangsamung liegt nicht an der Batch-Groesse selbst.
- Reale Messungen gegen die aktuell konfigurierte XT-API `http://10.0.1.49/wela-api` zeigen:
  - `2` Produkte im Batch: ca. `6.65s`
  - `10` Produkte im Batch: ca. `31.50s`
  - `2` Produkte einzeln: ca. `6.92s`
  - `10` Produkte einzeln: ca. `41.57s`
- Damit war klar:
  - der Batch-Endpunkt selbst bringt nur begrenzt Entlastung
  - die teure Arbeit passiert pro Produkt innerhalb der API
- Der Isolations-Test mit echten Queue-Payloads hat den Hotspot sauber belegt:
  - `size=2 normal`: `5.57s`
  - `size=2 no_seo`: `0.13s`
  - `size=10 normal`: `18.09s`
  - `size=10 no_seo`: `0.18s`
- Damit ist der SEO-Pfad in `sync_product` der klare Haupt-Bottleneck.

## Root cause

- Auch fuer sehr einfache Produkte ohne Attribute und ohne Uebersetzungen erzeugt die API pro Produkt `4` SEO-Zeilen (`de`, `en`, `fr`, `nl`).
- Im Auto-Generate-Pfad wird bisher fuer jede dieser SEO-Zeilen erneut die Kollisionspruefung gegen `xt_seo_url` ausgefuehrt.
- Bei bereits existierenden Produkten mit bereits gueltiger bestehender URL war diese Pruefung in der Regel redundant, aber trotzdem teuer.
- Genau dieser Fall war in den gemessenen Queue-Beispielen dominant.

## Implemented fix

- `wela-api/seo_helpers.php` verwendet bestehende gueltige SEO-URLs desselben Datensatzes jetzt direkt wieder, wenn:
  - `link_type`, `link_id`, `language_code` und `store_id` identisch sind
  - die vorhandene URL genau dem neu generierten Basiswert entspricht
  - oder nur denselben numerischen Kollisionssuffix weiterverwendet
- Dadurch wird die teure SEO-Kollisionspruefung fuer stabile, bereits vorhandene Produkt-URLs uebersprungen.
- `wela-api/src/ProductSyncService.php` uebergibt dafuer die bereits vorab geladenen existierenden SEO-Zeilen explizit an den Auto-Generate-Pfad.
- Der fachliche Effekt bleibt gleich:
  - gleiche vorhandene URL bleibt erhalten
  - Redirect-Logik bleibt erhalten
  - neue oder geaenderte URLs laufen weiter durch die normale Validierung

## Benchmark script

- Neues Repro-Skript:
  - `scripts/benchmark_wela_api_products.php`
- Es benchmarkt mit echten `product`-Queue-Eintraegen:
  - `normal`
  - `no_seo`
  - `no_categories`
- Beispiel:
  - `docker compose exec -T php php /app/scripts/benchmark_wela_api_products.php 2 10`

## Open points

- Der API-Fix ist im Repository und in `wela-api-xt` umgesetzt, aber der live genutzte Zielshop braucht dafuer weiterhin ein separates Deployment von `wela-api`.
- Die echte Nachher-Messung gegen den produktiven Ziel-Endpunkt kann erst nach diesem Deploy erfolgen.
- Falls danach immer noch ein nennenswerter Rest bleibt, ist der naechste Kandidat nicht mehr die Batch-Groesse, sondern:
  - verbleibende SEO-Neugenerierung bei wirklich geaenderten URLs
  - oder XT-seitige Writes in `xt_products` / `xt_products_to_categories`

## Validation steps

- `docker compose exec -T php php -l /app/wela-api/seo_helpers.php`
- `docker compose exec -T php php -l /app/wela-api/src/ProductSyncService.php`
- `docker compose exec -T php php -l /app/wela-api-xt/seo_helpers.php`
- `docker compose exec -T php php -l /app/wela-api-xt/src/ProductSyncService.php`
- `docker compose exec -T php php -l /app/scripts/benchmark_wela_api_products.php`
- `docker compose exec -T php php /app/scripts/benchmark_wela_api_products.php 2 10`
- `diff -qr wela-api wela-api-xt`
  - Ergebnis: nur `Only in wela-api-xt: test`

## Recommended next step

- `wela-api` auf dem Zielshop deployen und sofort danach den Benchmark erneut fahren:
  - `docker compose exec -T php php /app/scripts/benchmark_wela_api_products.php 2 10`
- Erwartung nach Deploy:
  - `normal` sollte sich deutlich an `no_seo` annaehern, zumindest fuer bereits existierende und unveraenderte Produkt-SEO-Faelle
