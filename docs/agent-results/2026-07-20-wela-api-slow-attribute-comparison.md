# Vergleich: `wela-api slow` und aktueller Attributexport

## Task

Den früher funktionierenden Stand unter `wela-api slow` mit der aktuellen API-Attributlogik vergleichen.

## Files read

- `wela-api slow/index.php`
- `wela-api/index.php`
- `wela-api/src/ProductSyncService.php`

## Changed files

- `docs/agent-results/2026-07-20-wela-api-slow-attribute-comparison.md`

## Summary

Der alte Stand verarbeitet die Produkt-Batch-Einträge sequenziell ohne vorab aufgebauten Attribut-Batch-Kontext. Der Attribut-Upsert wird pro Produkt unmittelbar ausgeführt.

Der aktuelle Stand führt zur Performance-Optimierung einen Batch-Kontext ein, der Attributdaten vorab lädt. Dieser Kontext wird innerhalb eines Batches nicht automatisch durch neu angelegte Child-Attribute ergänzt. Das erklärt die beobachteten mehrfachen Einträge wie `stehend` unter demselben Parent.

Die aktuelle lokale API-Vorlage enthält deshalb zusätzlich einen frischen Lookup von `attributes_model` und `attributes_parent` direkt vor jedem Child-Insert. Damit bleibt die Performance-Struktur erhalten, aber das Verhalten entspricht bei der Eindeutigkeit dem alten Einzelverarbeitungsmodell.

## Open points

- Nach vollständigem Re-Export per Mirror prüfen, ob keine Duplikate mehr entstehen.
- Falls parallele API-Requests möglich sind, sollte die XT-Datenbank zusätzlich einen eindeutigen Index auf `(attributes_model, attributes_parent)` erhalten.

## Validation steps

- Attribut-Upsert und Batch-Ablauf beider Versionen zeilenweise verglichen.

## Recommended next step

Den aktuellen API-Fix beibehalten und nach Abschluss des laufenden Exports die Duplikatprüfung über alle Parent-/Modellpaare ausführen.
