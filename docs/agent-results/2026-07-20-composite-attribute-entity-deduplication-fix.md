# Parent-spezifische Attribut-Deduplizierung

## Task

Die fehlerhafte Attributanlage korrigieren, wenn derselbe Wert unter mehreren Parents eines Produkts vorkommt.

## Files read

- `src/Service/XtProductWriter.php`
- `wela-api/src/ProductSyncService.php`
- `docs/agent-results/2026-07-20-product-export-attribute-log-diagnosis.md`

## Changed files

- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-07-20-composite-attribute-entity-deduplication-fix.md`

## Summary

Die Deduplizierung von Attribut-Entitäten und -Beschreibungen verwendet nun den zusammengesetzten Schlüssel aus Parent-Modell und Wert. Der gleiche Wert bleibt damit unter verschiedenen Parents getrennt.

Beispiel: `560mm` wird für `Durchmesser A` und `Durchmesser C` jeweils als eigene Child-Entität in den API-Payload geschrieben.

## Open points

- Die 574 wartenden Produkt-Einträge müssen erneut exportiert werden.

## Validation steps

- `php -l src/Service/XtProductWriter.php` erfolgreich.
- Payload-Prüfung für Queue 87109: beide Entitäten `560mm` mit den Parents `Durchmesser A` und `Durchmesser C` vorhanden.

## Recommended next step

Wartende Produkte erneut exportieren und danach die Queue auf verbleibende Attributfehler prüfen.
