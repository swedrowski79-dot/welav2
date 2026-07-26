# HA-1620: Vergleich vor/nach Shop-Backup

## Task

Die Attributzuweisungen von `HA-1620` vor und nach dem Einspielen des Shop-Backups vergleichen.

## Files read

- `backups/xt-attribute-mirror-reference-20260720-164515.sql.gz`

## Changed files

- lokale Vergleichsdatenbank `stage_sync_attribute_reference`
- `docs/agent-results/2026-07-20-ha-1620-before-after-backup-compare.md`

## Summary

Die Referenz wurde in eine getrennte lokale Vergleichsdatenbank importiert. Anschließend wurde der XT-Mirror vom wiederhergestellten Shop aktualisiert.

Die fachlichen Zuordnungen sind identisch:

- `160mm` unter `Durchmesser`
- `2,0m` unter `Länge`
- `hängend` unter `Ausführung`

Unterschiedlich sind nur die internen XT-IDs der Attributwerte und Parents. Das ist nach einem Shop-Backup normal und keine fachliche Abweichung.

## Open points

- Keine Abweichung bei HA-1620 festgestellt.

## Validation steps

- Referenzexport in `stage_sync_attribute_reference` importiert.
- XT-Mirror-Refresh nach Backup ausgeführt.
- Produkt-ID `3361` (`HA-1620`) in Referenz und aktuellem Mirror verglichen.

## Recommended next step

Weitere Varianten oder die globale Duplikatanzahl nach Parent/Modell vergleichen, falls weiterhin ein Fehler im Shop sichtbar ist.
