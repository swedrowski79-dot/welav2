## Task

`wela-api` nach `wela-api-xt` angleichen und die Produkt-Exportschnittstelle so optimieren, dass die Artikelsynchronisation schneller laeuft, ohne Dateninhalt oder Verarbeitungslogik zu aendern. Schwerpunkt: groessere effektive Batches, weniger JSON-Probleme bei grossen Requests.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/sources.php`
- `config/delta.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`
- `wela-api/src/ProductSyncService.php`
- `wela-api/README.md`
- `docs/agent-results/2026-07-17-wela-api-performance-review.md`
- `docs/agent-results/2026-07-17-export-worker-batch-timing.md`

## Changed files

- `config/sources.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`
- `wela-api-xt/index.php`
- `wela-api-xt/README.md`
- `wela-api-xt/config.php.example`
- `wela-api-xt/seo_helpers.php`
- `wela-api-xt/xt_image_helpers.php`
- `wela-api-xt/bootstrap/xtcommerce.php`
- `wela-api-xt/bin/test-xt-image.php`
- `wela-api-xt/src/CategorySyncService.php`
- `wela-api-xt/src/FileTransferService.php`
- `wela-api-xt/src/ProductSyncService.php`
- `wela-api-xt/src/SchemaInspector.php`
- `wela-api-xt/src/ShopMaintenanceService.php`
- `docs/agent-results/2026-07-17-product-batch-json-performance.md`

## Summary

- `wela-api` wurde in den relevanten Entwicklungsdateien nach `wela-api-xt` gespiegelt. Unterschied bleibt nur der zusaetzliche Ordner `wela-api-xt/test`.
- Der Produkt-Writer baut Requests jetzt nicht mehr nur nach fester Stueckzahl, sondern optional auch nach maximaler Payload-Groesse.
- Neue XT-Config:
  - `XT_PRODUCT_BATCH_REQUEST_MAX_PAYLOAD_BYTES`
  - Standard `0` = deaktiviert
- Wenn ein Produkt-Batch auf typische Groessen-/Transportprobleme laeuft, wird er automatisch rekursiv in kleinere Teilbatches zerlegt statt komplett zu scheitern.
- Erkannt werden dabei bewusst nur typische technische Fehlerbilder wie:
  - invalides JSON
  - Request-/Transportfehler
  - HTTP 500
  - Timeout-/Gateway-/Memory-Probleme
- Die XT-API liefert fuer `sync_products_batch` auf Wunsch keine per-Item-`data` mehr mit.
- Der interne Client nutzt diese kompakte Antwort jetzt standardmaessig, wodurch grosse Batch-Responses deutlich kleiner werden.
- Die eigentliche Schnittstellenlogik bleibt unveraendert:
  - gleiche Produktdaten
  - gleiche Attribut-/Kategorie-/SEO-Verarbeitung
  - gleiche API-Action `sync_products_batch`
  - nur die Batch-Zusammenstellung und die Antwortgroesse wurden optimiert
- Der API-Client meldet bei JSON-Fehlern jetzt den HTTP-Status und den Anfang der Serverantwort mit, damit kuenftige Fehlerursachen schneller sichtbar werden.

## Open points

- Es wurde bewusst kein fixer Max-Byte-Wert aktiviert, weil die sinnvolle Groesse von der echten Shop-Umgebung abhaengt.
- Falls der Gegenshop weiterhin bei groesseren Requests instabil ist, sollte als naechster Lauf ein konkreter Startwert getestet werden, z. B. `XT_PRODUCT_BATCH_REQUEST_MAX_PAYLOAD_BYTES=750000` oder `1000000`.
- Es wurde kein echter Export-Messlauf gegen den Zielshop gefahren; die Performance-Verbesserung ist implementiert, aber nicht live benchmarked.
- Weitere moegliche Ursache fuer JSON-Probleme bleibt zusaetzliche Serverausgabe/Warnungen im Gegensystem. Die neue Fehlerausgabe im Client hilft genau dabei.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
- `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- `docker compose exec -T php php -l /app/config/sources.php`
- `docker compose exec -T php php -l /app/wela-api/index.php`
- `docker compose exec -T php php -l /app/wela-api-xt/index.php`
- `diff -qr wela-api wela-api-xt`
  - Ergebnis: nur `Only in wela-api-xt: test`

## Recommended next step

- Einen echten Export-Worker-Lauf mit mehreren Batch-Konfigurationen fahren und dabei die neuen Performance-Logs plus die kompakte API-Antwort beobachten. Sinnvolle Reihenfolge:
  - `XT_PRODUCT_BATCH_REQUEST_SIZE=1000`
  - zusaetzlich `XT_PRODUCT_BATCH_REQUEST_MAX_PAYLOAD_BYTES=750000`
  - danach schrittweise groesser, bis der beste stabile Durchsatz gefunden ist
