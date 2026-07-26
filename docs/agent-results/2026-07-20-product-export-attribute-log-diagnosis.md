# Diagnose: fehlende Attribute im Produkt-Export

## Task

Das Protokoll des letzten Produkt-Exports auf die wiederholt fehlenden Attribute prüfen.

## Files read

- `src/Service/XtProductWriter.php`
- `wela-api/src/ProductSyncService.php`
- `sync_runs`, `sync_logs`, `sync_errors` und `export_queue` der Stage-Datenbank

## Changed files

- `docs/agent-results/2026-07-20-product-export-attribute-log-diagnosis.md`

## Summary

Der Produkt-Export hat 574 Einträge erneut zurückgestellt. Die Ursache sind wiederholte API-Fehler wie `Produkt-Sync kennt kein XT-Attribut fuer '560mm'`.

Konkreter Nachweis am Produkt `GABP-45-560-250-560-verz` (Queue 87109):

- Parent `Durchmesser A` → Wert `560mm`
- Parent `Durchmesser B` → Wert `250mm`
- Parent `Durchmesser C` → Wert `560mm`

Der Writer bildet in `buildAttributeEntities()` die Deduplizierung nur aus `attribute_model`. Dadurch wird die zweite Entität `560mm` unter `Durchmesser C` weggelassen. Die Relation wird anschließend trotzdem gesendet und die API kann `560mm` unter diesem Parent nicht auflösen.

## Open points

- Die Deduplizierung im Writer muss den zusammengesetzten Schlüssel aus Parent-Modell und Wert verwenden.

## Validation steps

- Exportläufe 46 bis 50 geprüft.
- Queue-Fehler und Payload des konkreten Artikels ausgelesen.

## Recommended next step

`buildAttributeEntities()` auf Parent-plus-Wert-Deduplizierung umstellen und die wartenden Produkt-Einträge danach erneut exportieren.
