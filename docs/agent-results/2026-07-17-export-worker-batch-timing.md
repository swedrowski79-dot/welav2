## Task

Timing-Logging fuer den Produkt-Export-Worker einbauen, um zu messen, warum kleine Batches schneller laufen als groessere.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `run_export_queue.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/XtMediaDocumentWriter.php`

## Changed files

- `run_export_queue.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtProductWriter.php`

## Summary

- Der Produkt-Writer kann jetzt Performance-Logs in `sync_logs` schreiben.
- Der Export-Worker verdrahtet dafuer einen Logger in den `XtProductWriter`.
- Fuer Produkt-Batches werden jetzt drei Zeitpunkte geloggt:
  - Batch vorbereitet
  - Batch-Request gestartet
  - Batch-Request abgeschlossen
- Der XT-API-Client liefert fuer `sync_products_batch` jetzt Request-Metadaten zurueck:
  - HTTP-Dauer
  - HTTP-Status
  - Request-Bytes
  - Response-Bytes
- Dadurch kann im naechsten Testlauf sauber unterschieden werden zwischen:
  - Payload-Bau im Worker
  - eigentlicher HTTP-Wartezeit
  - Ergebnisgroesse pro Batch

## Open points

- Das Logging misst aktuell die Client-Seite des Batch-Requests, nicht die interne Einzelschritt-Zeit innerhalb der XT-API.
- Falls die Batch-Zeit weiter unklar bleibt, sollte als naechster Schritt auch serverseitiges Timing in `wela-api` fuer `sync_products_batch` ergaenzt werden.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/AbstractXtWriter.php`
- `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
- `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- `docker compose exec -T php php -l /app/run_export_queue.php`

## Recommended next step

- Einen Testlauf mit Batch `2`, `5` und `10` starten und in `sync_logs` die neuen Eintraege `Produkt-Batch vorbereitet.`, `Produkt-Batch Request gestartet.` und `Produkt-Batch Request abgeschlossen.` vergleichen.
