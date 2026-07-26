# Diagnose: Shop-Attributfilter

## Task

Prüfen, weshalb die Attributauswahl im Shop trotz korrekter Produktdaten nicht funktioniert.

## Files read

- Referenzexport `backups/xt-attribute-mirror-reference-20260720-164515.sql.gz`
- aktuelle XT-Mirror-Attributtabellen

## Changed files

- `docs/agent-results/2026-07-20-shop-attribute-filter-diagnosis.md`

## Summary

Für `HA-1620` sind die Daten vor dem Backup und aktuell vollständig identisch:

- `160mm` unter `Durchmesser`, Parent-Sortierung 1
- `2,0m` unter `Länge`, Parent-Sortierung 2
- `hängend` unter `Ausführung`, Parent-Sortierung 1
- Child-Sortierungen jeweils 0
- Status aktiv

Die Produktdaten von HA-1620 erklären daher kein fehlerhaftes Filterergebnis.

Im aktuellen Shop-Mirror existieren jedoch noch doppelte Parent-/Modellpaare. Besonders relevant sind zwei Parent-Attribute mit Modell `Durchmesser` (IDs 1 und 125). Weitere Duplikate betreffen `für Schleuse Typ` sowie die Werte `ja` und `nein` unter Parent 555.

Wenn der Shop-Filter eine der doppelten Attributgruppen auswählt, findet er nur Produkte, die an genau diese Parent-ID gebunden sind. Produkte an der anderen Duplikat-ID werden dann nicht angezeigt. Das ist eine plausible direkte Ursache für das Filterproblem.

## Open points

- Ermitteln, welche Produkte noch an die doppelten Parent-IDs gebunden sind.
- Doppelte Attribute konsolidieren und die Produktrelationen auf die jeweils kanonische ID umhängen.

## Validation steps

- XT-Mirror aktualisiert.
- HA-1620 mit dem Referenzexport verglichen.
- Globale Prüfung auf doppelte Kombinationen aus `attributes_parent` und `attributes_model` ausgeführt.

## Recommended next step

Eine kontrollierte Konsolidierung der sechs Duplikatgruppen durchführen: eine kanonische Attribut-ID behalten, Produktrelationen umhängen und erst danach die unbenutzten Duplikate entfernen.
