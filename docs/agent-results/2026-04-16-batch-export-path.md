## Task

Produkt-Export von Einzelrequests auf konfigurierbaren Batch-Transport umstellen, sodass der Worker grosse Produktmengen pro API-Aufruf senden kann.

## Files read

- `src/Service/ExportQueueWorker.php`
- `src/Service/XtQueueWriter.php`
- `src/Service/XtCompositeWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Service/WelaApiClient.php`
- `wela-api/index.php`
- `config/delta.php`
- `config/xt_write.php`
- `run_export_queue.php`

## Changed files

- `src/Service/XtBatchQueueWriter.php`
- `src/Service/XtCompositeWriter.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/XtProductWriter.php`
- `src/Service/WelaApiClient.php`
- `wela-api/index.php`
- `src/Service/AbstractXtWriter.php`
- `config/xt_write.php`
- `run_export_queue.php`
- `docs/agent-results/2026-04-16-batch-export-path.md`

## Summary

- Der Produkt-Export kann im Repo jetzt als echter Batch-Transport laufen:
  - `ExportQueueWorker` erkennt Batch-Writer und verarbeitet einen ganzen Claim-Block gesammelt.
  - `XtProductWriter` kann mehrere Produkt-Queue-Eintraege in einem API-Request senden.
  - `wela-api` hat dafuer den neuen Endpunkt `sync_products_batch`.
- Die Batch-Groesse bleibt ueber `config/delta.php -> product_export_queue -> worker_batch_size` steuerbar und kann damit z. B. auf `1000`, `5000` oder `10000` gesetzt werden.
- Fuer Debugging bleibt der Einzelpfad erhalten.
- Nebenbei wurde ein echter Kategorie-Typfehler behoben:
  - `xt_categories.permission_id` wird jetzt als `null` statt als Leerstring exportiert.

## Open points

- Die aktuell deployte Ziel-`wela-api` kennt den neuen Endpunkt noch nicht.
- Die Live-Probe direkt vor dem grossen Exporttest antwortete auf `sync_products_batch` weiterhin mit `Unbekannte Aktion.`
- Der erste Produkt-Batch-Test gegen den Zielshop endete deshalb ebenfalls fuer alle 20 Testeintraege mit `Unbekannte Aktion.`
- Bevor der Batch-Export live validiert werden kann, muss die aktualisierte `wela-api` erneut eingespielt werden.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/XtBatchQueueWriter.php`
- `docker compose exec -T php php -l /app/src/Service/XtCompositeWriter.php`
- `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
- `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
- `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- `docker compose exec -T php php -l /app/src/Service/AbstractXtWriter.php`
- `docker compose exec -T php php -l /app/run_export_queue.php`
- `docker compose exec -T php php -l /app/wela-api/index.php`
- Live-Probe des Endpunkts `sync_products_batch` gegen den Zielshop
- Produkt-Batch-Test mit `ExportQueueWorker->run(20)` gegen den Zielshop

## Recommended next step

Die neue `wela-api` erneut auf dem Zielshop deployen und danach den Produkt-Export noch einmal starten, damit der Batch-Pfad live gegen echte Queue-Daten validiert werden kann. Der aktuell sichtbare Zielshop-Stand ist noch nicht der Batch-Stand aus diesem Repository.
