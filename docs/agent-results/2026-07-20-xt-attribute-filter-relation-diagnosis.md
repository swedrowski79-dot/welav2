# XT-Attributfilter: Relation-Diagnose

## Task

Prüfen, weshalb im Shop Attribute auswählbar sind, die Auswahl aber keine Produkte liefert.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `src/Service/XtProductWriter.php`
- `wela-api/src/ProductSyncService.php`
- `docs/agent-results/2026-04-16-live-xt-attribute-parent-child-fix.md`

## Changed files

- `docs/agent-results/2026-07-20-xt-attribute-filter-relation-diagnosis.md`

## Summary

Die aktuelle XT-Mirror-Datenbasis enthält 10.615 Produkt-Attribut-Links für 5.332 Produkte. Alle 10.615 Links verweisen auf Unterwerte eines Attributes; kein einziger Produkt-Link verweist direkt auf eine Attributgruppe (Parent).

`XtProductWriter::normalizeAttributes()` baut genau diese Struktur: Parent = Attributname, Child = Attributwert. Die Relation im Export-Payload verwendet ausschließlich das Child-Modell; `ProductSyncService` setzt dazu `attributes_parent_id` auf die Parent-ID.

Damit existiert zwar die Parent/Child-Hierarchie, aber keine direkte Produkt-zu-Parent-Relation. Falls der aktive Shop-Filter nach Produkten mit der ausgewählten Parent-`attributes_id` sucht, findet er daher keine Produkte. Das passt exakt zum gemeldeten Verhalten.

Ein älterer Bericht dokumentiert die Child-only-Relation als damals korrektes Shopmodell. Das aktuelle Storefront-Verhalten steht dazu im Widerspruch und muss vor einem Umbau gegen die tatsächlich verwendete Filterabfrage beziehungsweise XT-Konfiguration verifiziert werden.

## Open points

- Verifizieren, ob der aktuelle XT-Filter direkt nach Parent-Attribut-IDs filtert oder die Child-Relation mit `attributes_parent_id` auswertet.
- Falls Parent-Links erforderlich sind: Export und Ersetzungslogik so erweitern, dass Parent- und Child-Links nebeneinander erhalten bleiben.

## Validation steps

Ausgeführt (nur lesend) gegen den lokalen XT-Mirror:

```sql
product_attribute_links      10615
linked_products               5332
direct_parent_product_links      0
child_product_links          10615
links_with_parent_pointer    10615
```

## Recommended next step

Die XT-Filterlogik beziehungsweise das API-Verhalten gezielt prüfen. Wenn die Parent-ID für die Auswahl verwendet wird, anschließend einen minimalen Export-Fix für zusätzliche Parent-Produkt-Links implementieren und mit einem Testprodukt validieren.
