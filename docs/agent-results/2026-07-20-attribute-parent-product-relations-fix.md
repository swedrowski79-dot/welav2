# Produkt-Attributgruppen wieder verknüpfen

## Task

Die Attributzuweisungen so korrigieren, dass die im Shop ausgewählten Attributgruppen wieder die zugehörigen Produkte liefern.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/xt_write.php`
- `config/delta.php`
- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/ExportQueueWorker.php`
- `run_delta.php`
- `run_export_queue.php`
- `wela-api/index.php`
- `wela-api/src/ProductSyncService.php`
- `docs/agent-results/2026-07-20-xt-attribute-filter-relation-diagnosis.md`
- `docs/agent-results/2026-04-16-live-xt-attribute-parent-child-fix.md`

## Changed files

- `src/Service/XtProductWriter.php`
- `src/Service/ProductDeltaService.php`
- `docs/agent-results/2026-07-20-attribute-parent-product-relations-fix.md`

## Summary

Der Produkt-Export erzeugt jetzt pro Attribut zwei Produktzuordnungen:

1. direkte Zuordnung zur Attributgruppe (Parent), damit eine Auswahl der Gruppe im Shop Produkte findet;
2. bestehende Zuordnung zum konkreten Attributwert (Child), inklusive `attributes_parent_id`, damit die Name/Wert-Hierarchie erhalten bleibt.

Doppelte Parent-Zuordnungen innerhalb eines Produktes werden vor dem Export entfernt.

Der Mirror-Abgleich prüft zusätzlich, ob für jeden verwendeten Attributwert die direkte Parent-Produktzuordnung vorhanden ist. Fehlt sie, wird das Produkt erneut in die Export-Queue eingeplant. Direkte Parent-Links werden aus dem inhaltlichen Attribut-Hash ausgeklammert, damit ein erfolgreicher Export nicht dauerhaft neue Deltas erzeugt.

## Validation steps

- PHP-Syntaxprüfung lokal und im PHP-Container erfolgreich:
  - `src/Service/XtProductWriter.php`
  - `src/Service/ProductDeltaService.php`
- Delta ausgeführt. Dadurch wurden 5.489 Produkt-Queue-Einträge für die korrigierte Zuordnung bereitgestellt.
- Ein auf Produkte beschränkter Exportlauf wurde gestartet.
- Der erzeugte Payload für Produkt `68` enthält nachweisbar beide Relationen:
  - Parent-Modell `afs-attr-parent-durchmesser-9678162d`, `attributes_parent_id = 0`
  - Child-Modell `afs-attr-value-durchmesser-080mm-9dd9b33d`, Parent-Modell gesetzt

## Open points

Der laufende XT-API-Endpunkt konnte den Gesamtexport nicht zuverlässig bestätigen. Der Produktlauf endete mit:

- 23 erfolgreich bestätigten Produkten;
- 5.460 erneut vorgemerkten Produkten;
- 6 permanenten Produktfehlern wegen fehlender Kategorien.

Die Retry-Fehler stammen aus der API-/Shop-Umgebung und lauten unter anderem:

- `Table 'shop2.xt_seo_url' doesn't exist` (5.000 Einträge)
- `Field 'attributes_id' doesn't have a default value` (387 Einträge)
- `Field 'products_id' doesn't have a default value` (73 Einträge)

Die betroffenen Produkt-Queue-Einträge bleiben bewusst erhalten und können nach Korrektur der API-Umgebung erneut verarbeitet werden.

## Recommended next step

Die laufende Wela-API auf die korrekte XT-Datenbank und das erwartete Schema prüfen, insbesondere die Tabelle `xt_seo_url`. Danach die wartenden Produkt-Queue-Einträge erneut exportieren und anschließend den XT-Mirror aktualisieren. Die direkte Parent-Relation sollte danach im Shop mit einer Attributauswahl getestet werden.

## Retry 2026-07-20

Ein erneuter Produkt-Export lief anschließend erfolgreich:

- Zunächst wurden 5.000 Produkt-Queue-Einträge bestätigt.
- Die verbleibenden 460 Einträge wurden in Batches zu 50 verarbeitet: 460 verarbeitet, 460 erfolgreich, keine Retries oder Fehler.
- Nach dem XT-Mirror-Refresh existieren 10.125 direkte Produkt-zu-Attributgruppen-Links.
- Für die aktuell synchronisierten Stage-Produkte fehlen keine Parent-Links mehr (`0`).

Die noch 281 Child-only-Links gehören ausschließlich zu historischen Shop-Produkten ohne Zuordnung zu `stage_products`; sie werden durch diesen Sync bewusst nicht verändert.

## Korrektur der Relationserwartung

Die Annahme einer zusätzlichen direkten Produkt-zu-Parent-Relation war falsch. Das XT-Schema erwartet pro Attribut **genau eine** Zeile in `xt_plg_products_to_attributes`:

- `attributes_id` = konkreter Attributwert (Child)
- `attributes_parent_id` = zugehörige Attributgruppe (Parent)

Die zusätzliche Parent-Zeile wurde aus dem Writer und dem Delta-Abgleich entfernt. Ein erneuter Delta-Lauf stellte 5.214 Produkte bereit und exportierte die bereinigte Child-only-Struktur. Ergebnis: 5.208 erfolgreich, 6 neue permanente Fehler; insgesamt verbleiben 18 bereits vorhandene Produktfehler in der Queue.

Ein anschließender XT-Mirror-Refresh bestätigt für `HA-1620` exakt drei Relationseinträge, alle mit Child-`attributes_id` und gesetzter `attributes_parent_id`; direkte Parent-Relationen sind nicht mehr vorhanden.
