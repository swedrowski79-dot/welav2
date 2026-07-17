Task
- Laufenden Export-Worker stoppen, die Produkt-Queue wieder auf einen sauberen `pending`-Stand bringen und die Produkt-API-Unterbatchgroesse reduzieren.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- .env
- config/sources.php
- src/Service/XtProductWriter.php

Changed files
- .env
- docs/agent-results/2026-07-17-product-batch-size-reduction.md

Summary
- Der laufende `run_export_queue.php 1`-Prozess wurde am 2026-07-17 gezielt beendet.
- Anschliessend wurden alle `product`-Queue-Eintraege mit `status in (processing, error)` auf `pending` zurueckgesetzt.
- Der danach verifizierte Produkt-Queue-Stand war:
  - `done = 128`
  - `pending = 6744`
- Die Produkt-Writer-Logik nutzt bereits API-Unterbatches ueber `XT_PRODUCT_BATCH_REQUEST_SIZE`.
- Dieser Wert war bisher auf `1000` gesetzt und damit faktisch groesser als der Worker-Batch.
- Der Wert wurde auf `50` reduziert, damit ein Worker-Lauf mit z. B. `500` Queue-Eintraegen nur noch in 50er API-Unterrequests an die XT-API sendet.

Open points
- Noch offen ist, ob `50` bereits stabil genug ist oder weiter auf `25` reduziert werden muss.
- Ein neuer Produkt-Exportlauf mit der geaenderten Unterbatchgroesse wurde in diesem Schritt noch nicht ausgefuehrt.

Validation steps
- Prozesspruefung im Container ueber `/proc/*/cmdline`
- Queue-Reset:
  - `UPDATE export_queue ... WHERE entity_type = "product" AND status IN ("processing", "error")`
- Queue-Kontrolle:
  - `SELECT status, COUNT(*) FROM export_queue WHERE entity_type = "product" GROUP BY status`
- Konfigurationskontrolle:
  - `docker compose exec php php -r '... require \"config/sources.php\"; echo $c["sources"]["xt"]["connection"]["product_batch_request_size"];'`

Recommended next step
- Den Export-Worker erneut starten und beobachten, ob ein Lauf mit groesserem Worker-Batch jetzt sauber ueber mehrere 50er Produkt-Unterbatches durchlaeuft.
