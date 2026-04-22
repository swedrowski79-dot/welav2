## Task

Doppelte Attributauswahl auf Master-Produkten beheben, indem veraltete Slave-Produkte im Zielshop erkannt und automatisch offline gesetzt werden. Zusaetzlich die verbleibenden Produktfehler mit fehlender Kategorie-Referenz bereinigen.

## Files read

- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/StageCategoryMap.php`
- `config/delta.php`
- `config/xt_write.php`
- `run_delta.php`
- `run_export_queue.php`
- `docs/agent-results/2026-04-16-batch-export-path.md`

## Changed files

- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-04-17-stale-product-cleanup.md`

## Summary

- `ProductDeltaService` erkennt jetzt veraltete Live-Slaves unter weiterhin aktiven Master-Familien auch dann, wenn nach einem Reset keine Export-State-Historie mehr vorhanden ist.
- Die Bereinigung greift jetzt auch fuer alte XT-Produkte mit gleicher SKU, aber veralteter `external_id`.
- Dadurch wurden die veralteten Live-Slaves `59190`, `51903` und `57962` im Zielshop auf `products_status = 0` gesetzt.
- Der Dublettenfall auf `W02-000` ist damit beseitigt; im Shop bleibt fuer `Durchmesser`, `Laenge` und `Ausfuehrung` jeweils nur noch die aktuelle Attribut-ID sichtbar.
- In `XtProductWriter` wurde ausserdem die Kategorie-Fallback-Zuordnung korrigiert: die gefundene Master-Kategorie ueberschreibt jetzt die tote Slave-Kategorie wirklich.
- Die zuvor fehlerhaften Produkte `2860` bis `2867` lassen sich damit wieder erfolgreich exportieren.

## Open points

- Durch die wiederholten Delta-Laeufe existieren weiterhin normale `pending` Queue-Eintraege aus dem Gesamtbestand; das ist keine Fehlersituation dieses Fixes.
- Fuer den hier bearbeiteten Fehlerbereich bleiben keine veralteten Online-Slaves unter aktiven Master-Familien zurueck.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/ProductDeltaService.php`
- `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- `docker compose exec -T php php /app/run_delta.php`
- mehrfache `docker compose exec -T php php /app/run_export_queue.php`
- `docker compose exec -T php php /app/run_xt_mirror.php`
- Live-Pruefung der XT-Produkte `external_id in (59190, 51903, 57962)` auf `products_status = 0`
- Shop-Pruefung von `W02-000` via `curl --resolve ...` auf nur noch eine sichtbare Attribut-ID je Auswahlwert

## Recommended next step

Die restliche mehrsprachige Shop-Stichprobe fuer Produkte und Kategorien weiterfuehren, jetzt auf dem bereinigten Master-/Slave-Bestand.
