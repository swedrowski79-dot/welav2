## Task

Den verbleibenden SEO-Performance-Bottleneck in `wela-api` weiter optimieren, nachdem klar war, dass nicht die Batch-Groesse selbst, sondern der SEO-Pfad pro Produkt die Laufzeit dominiert.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/xt_write.php`
- `config/languages.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtProductWriter.php`
- `src/Service/StageCategoryMap.php`
- `wela-api/index.php`
- `wela-api/seo_helpers.php`
- `wela-api-xt/index.php`
- `wela-api-xt/seo_helpers.php`
- `scripts/benchmark_wela_api_products.php`

## Changed files

- `wela-api/seo_helpers.php`
- `wela-api/index.php`
- `wela-api-xt/seo_helpers.php`
- `wela-api-xt/index.php`
- `docs/agent-results/2026-07-17-seo-batch-prefetch-optimization.md`

## Summary

- Der teure Teil lag weiter in der SEO-Kandidatenpruefung gegen `xt_seo_url`.
- Vorher lief diese Suche im Batch weiterhin praktisch pro Produkt und pro Sprache einzeln.
- Jetzt werden die SEO-Konfliktkandidaten fuer den gesamten Produkt-Batch gesammelt vorgewaermt.
- Dazu wurden die zu erwartenden Produkt-SEO-Basis-URLs pro Sprache/Store im Batch vorab berechnet.
- Anschliessend werden passende `xt_seo_url`-Kandidaten in Sammelabfragen geladen und in einen Request-Cache gelegt.
- Die eigentliche SEO-Logik bleibt unveraendert:
  - gleiche URL-Generierung
  - gleiche Kollisionslogik
  - gleiche Redirect-Logik
  - gleiche Datenverarbeitung

## Measured results

Benchmark ausgefuehrt am Freitag, 17. Juli 2026 mit:

- `docker compose exec -T php php /app/scripts/benchmark_wela_api_products.php 2 10`

Vorher:

- `size=2 normal`: `5.5720s`
- `size=10 normal`: `18.0890s`

Nachher:

- `size=2 normal`: `4.2681s`
- `size=10 normal`: `7.5465s`

Vergleich:

- `2` Produkte: spuerbar besser
- `10` Produkte: deutlich besser, von rund `18.1s` auf rund `7.55s`

Kontrollwerte nach dem Fix:

- `size=2 no_seo`: `0.1142s`
- `size=10 no_seo`: `0.1996s`

Das bestaetigt weiter:

- SEO bleibt der dominante Restblock
- aber die Batch-Skalierung ist jetzt deutlich verbessert

## Open points

- Echte SEO-Aenderungsfaelle bleiben naturgemaess teurer als unveraenderte Produkte.
- Wenn noch mehr Leistung noetig ist, ist der naechste Kandidat die eigentliche URL-Neuberechnung inklusive Redirect-/Kollisionspfad bei echten Namensaenderungen.

## Validation steps

- `docker compose exec -T php php -l /app/wela-api/seo_helpers.php`
- `docker compose exec -T php php -l /app/wela-api/index.php`
- `docker compose exec -T php php -l /app/wela-api-xt/seo_helpers.php`
- `docker compose exec -T php php -l /app/wela-api-xt/index.php`
- `docker compose exec -T php php /app/scripts/benchmark_wela_api_products.php 2 10`

## Recommended next step

- Wenn du weiter optimieren willst, als naechstes die echten SEO-Aenderungsfaelle separat benchmarken und dann den Redirect-/Kollisionspfad fuer geaenderte URLs angehen.
