# shop2 / shop_norm: HA-000-Attributvergleich

## Task

Die live verwendete Datenbank `shop2` mit der funktionierenden Datenbank `shop_norm` für `HA-000` und dessen Slaves vergleichen.

## Files read

- direkter, vom Nutzer freigegebener Read-only-Zugriff auf `10.0.1.151:3307`
- Tabellen `xt_products`, `xt_plg_products_attributes`, `xt_plg_products_attributes_description`, `xt_plg_products_to_attributes`

## Changed files

- `docs/agent-results/2026-07-20-shop2-shop-norm-ha-000-comparison.md`

## Summary

Beide Datenbanken enthalten denselben Master `HA-000` und dieselben vier Slaves mit denselben fachlichen Attributwerten und aktivem Status.

Der entscheidende Unterschied betrifft `Durchmesser`:

- `shop_norm` (funktionierend): HA-Slaves nutzen Child `160mm` mit Parent-ID `1` (`Durchmesser`).
- `shop2` (aktiv, Filterproblem): HA-Slaves nutzen Child `160mm` mit Parent-ID `125` (`Durchmesser`).

Beide Parent-Zeilen mit Modell `Durchmesser` existieren in beiden Datenbanken. Weil ein Shop-Filter typischerweise eine konkrete Parent-ID auswählt, kann eine Auswahl der ersten Gruppe (ID 1) keine Produkte finden, die an die Duplikatgruppe ID 125 gebunden sind.

`Länge` (Parent 89) und `Ausführung` (Parent 187) stimmen für HA-000 und seine Slaves in beiden Datenbanken überein.

Die sehr hohen Parent-Sortierungen in `shop_norm` zeigen außerdem: Die Sortierung ist nicht die Ursache, da diese Datenbank laut Nutzer korrekt filtert.

## Open points

- Prüfen, welche weiteren Produkte in `shop2` an Parent-ID 125 statt an die kanonische Parent-ID 1 gebunden sind.

## Validation steps

- Schema beider Datenbanken verglichen.
- HA-000 und alle vier Slaves mitsamt Child-/Parent-ID, Modell, Status und Sortierung gelesen.
- Doppelte Parent-/Modellkombinationen in beiden Datenbanken verglichen.

## Recommended next step

In `shop2` die Attributgruppe `Durchmesser` auf eine kanonische ID konsolidieren (voraussichtlich ID 1): Child-Attribute und Produktrelationen von Parent 125 auf die kanonische Gruppe umhängen, danach die unbenutzte Duplikatgruppe kontrolliert entfernen.
