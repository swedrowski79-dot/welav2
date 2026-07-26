# Diagnose: Eindeutigkeit von Attributmodell und Parent

## Task

Prüfen, wie doppelte Attribute mit gleichem Modell und Parent verhindert werden können.

## Files read

- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`

## Changed files

- `docs/agent-results/2026-07-20-attribute-model-parent-uniqueness-diagnosis.md`

## Summary

Die aktuelle Writer-Logik behandelt `attributes_model` allein als Identität. Damit kann ein gleicher Wert wie `160mm` nicht sicher unter zwei verschiedenen Parents geführt werden: die Parent-Zuordnung wäre nicht Teil der Identität.

Für die gewünschte Regel muss die Identität eines Child-Attributes aus beiden Feldern bestehen:

- `attributes_model` = deutscher Wert, beispielsweise `160mm`
- `attributes_parent` = ID der deutschen Gruppe, beispielsweise die ID von `Durchmesser`

Die API müsste diesen zusammengesetzten Schlüssel beim Suchen und Upserten von Child-Attributen verwenden. Nur die Writer-Logik zu ändern wäre nicht ausreichend, da die API aktuell anhand von `attributes_model` sucht.

## Open points

- Bestätigen, dass derselbe Wert unter unterschiedlichen Gruppen getrennte Attributzeilen erhalten soll (z. B. `160mm` unter zwei verschiedenen Parents).

## Validation steps

- Deduplizierung im Writer geprüft: sie verwendet derzeit nur `attributes_model`.
- API- und Mirror-Abfrage der Kombination `attributes_parent` plus `attributes_model` vorbereitet.

## Recommended next step

Den API-Upsert für Child-Attribute auf die Kombination aus `attributes_model` und `attributes_parent` umstellen; Parent-Attribute bleiben weiterhin über ihr Modell eindeutig.
