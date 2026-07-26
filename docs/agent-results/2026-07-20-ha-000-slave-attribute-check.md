# Live-Prüfung HA-000 und Slaves

## Task

Den online synchronisierten Masterartikel `HA-000`, seine Slaves und deren Attribute prüfen.

## Files read

- `src/Service/WelaApiClient.php`
- `wela-api/index.php`
- `database.sql`

## Changed files

- `docs/agent-results/2026-07-20-ha-000-slave-attribute-check.md`

## Summary

Der unmittelbar vorher ausgeführte XT-Mirror-Refresh bestätigte:

- Master: `HA-000` (`products_id` 745, `external_id` 51624, Master-Flag gesetzt)
- Slaves: `HA-1620`, `HA-1620-P`, `HA-1630`, `HA-1630-P`
- Alle vier Slaves referenzieren online `HA-000` als Master.

Jeder Slave hat drei konkrete Attributlinks. Jeder Link verweist mit `attributes_parent_id` auf seine Attributgruppe; das ist die erwartete Parent/Child-Struktur.

| Slave | Durchmesser | Länge | Ausführung |
| --- | --- | --- | --- |
| HA-1620 | 160mm | 2,0m | hängend |
| HA-1620-P | 160mm | 2,0m | stehend |
| HA-1630 | 160mm | 3,0m | hängend |
| HA-1630-P | 160mm | 3,0m | stehend |

## Open points

- Der Master selbst hat keine eigenen Attribute. Die Variantenattribute liegen korrekt auf den Slaves.

## Validation steps

- XT-Mirror-Refresh über die laufende Wela-API ausgeführt.
- Master/Slave-Beziehungen im aktualisierten Mirror geprüft.
- Attribute in `stage_attribute_translations` und die live gespiegelten Parent/Child-Links verglichen.
- Nach der Relation-Korrektur hat `HA-1620` exakt drei Live-Links, alle auf Child-Attribute mit gesetzter Parent-ID.

## Recommended next step

Im Shop `HA-000` aufrufen und die Filterkombinationen Durchmesser, Länge und Ausführung manuell testen.

## Nach Reset und API-Duplikatfix

Nach Leerung der Attributtabellen, erneuter Übernahme der API und Re-Export wurde `HA-1620` direkt im XT-Mirror geprüft. Es hat genau drei Produktrelationen:

- `160mm` unter Parent `Durchmesser` (IDs `8` / `1`)
- `hängend` unter Parent `Ausführung` (IDs `385` / `383`)
- `2,0m` unter Parent `Länge` (IDs `389` / `117`)

Für diese Parents gibt es keine doppelten Kombinationen aus `attributes_parent` und `attributes_model`.
