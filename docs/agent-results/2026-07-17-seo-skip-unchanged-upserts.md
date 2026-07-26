## Task

Die SEO-Performance weiter verbessern, indem unveraenderte `xt_seo_url`-Zeilen nicht mehr erneut geschrieben werden.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `wela-api/src/ProductSyncService.php`
- `wela-api/index.php`
- `wela-api/seo_helpers.php`
- `wela-api-xt/src/ProductSyncService.php`
- `scripts/benchmark_wela_api_products.php`

## Changed files

- `wela-api/src/ProductSyncService.php`
- `wela-api-xt/src/ProductSyncService.php`
- `docs/agent-results/2026-07-17-seo-skip-unchanged-upserts.md`

## Summary

- Der SEO-Pfad laedt bestehende Produkt-SEO-Zeilen jetzt inklusive `meta_title`, `meta_description` und `meta_keywords` vorab.
- Vor dem Batch-Upsert in `xt_seo_url` wird jetzt pro SEO-Zeile geprueft, ob sich gegenueber der vorhandenen Zeile ueberhaupt etwas geaendert hat.
- Wenn URL und Meta-Felder identisch sind, wird das Upsert komplett uebersprungen.
- Das aendert die fachliche Verarbeitung nicht, reduziert aber unnoetige XT-Schreiblast bei bereits synchronen Produkten.

## Measured results

Benchmark:

- `docker compose exec -T php php /app/scripts/benchmark_wela_api_products.php 2 10`

Stand direkt vor diesem Schritt:

- `size=2 normal`: `4.2681s`
- `size=10 normal`: `7.5465s`

Nach diesem Schritt:

- `size=2 normal`: `4.4420s`
- `size=10 normal`: `7.2561s`

Interpretation:

- Bei `10` Produkten nochmals leicht besser.
- Bei `2` Produkten leichte Schwankung nach oben, also kein stabiler Vorteil im sehr kleinen Sample.
- Der grosse Sprung kam aus der vorherigen SEO-Batch-Prefetch-Optimierung; dieser Schritt ist eher eine Zusatzentlastung fuer bestehende Produkte.

## Open points

- Der verbleibende SEO-Restblock sitzt weiterhin hauptsaechlich in echter SEO-Neuberechnung und Kollisionsbehandlung.
- Fuer weitere groessere Gewinne muss als naechstes der Pfad fuer wirklich geaenderte SEO-URLs optimiert werden.

## Validation steps

- `docker compose exec -T php php -l /app/wela-api/src/ProductSyncService.php`
- `docker compose exec -T php php -l /app/wela-api-xt/src/ProductSyncService.php`
- `docker compose exec -T php php /app/scripts/benchmark_wela_api_products.php 2 10`

## Recommended next step

- Gezielt Produkte mit geaendertem Namen/SEO-Basis benchmarken und danach den verbliebenen SEO-Kollisions- und Redirect-Pfad optimieren.
