# shop2 / shop_norm: SEO-Vergleich für HA-000

## Task

SEO-Einträge des Masters HA-000 und seiner Slaves in `shop2` und `shop_norm` vergleichen.

## Files read

- direkter, vom Nutzer freigegebener Read-only-Zugriff auf `10.0.1.151:3307`
- Tabellen `xt_products` und `xt_seo_url` in `shop2` und `shop_norm`

## Changed files

- `docs/agent-results/2026-07-20-shop2-shop-norm-ha-000-seo-comparison.md`

## Summary

`shop_norm` enthält für HA-000 und jeden Slave genau eine deutsche SEO-Zeile mit der alten URL- und Metadatenvariante.

`shop2` enthält:

- für den Master HA-000 vier Sprachzeilen (de/en/fr/nl);
- für jeden der vier Slaves fünf SEO-Zeilen: en/fr/nl sowie zwei deutsche Zeilen mit unterschiedlichen URLs und Metadaten.

Die doppelten deutschen Slave-Zeilen sind:

- alte URL unter `de/absaugarme-zubehoer/absaugarme/...` mit Metatitel wie `HA-1620`;
- neue URL unter `de/absaugarme-zubehoer/absaugarme-econ/...` mit ausgeschriebenem Produkttitel.

Diese SEO-Duplikate können falsche Weiterleitungen, uneindeutige kanonische URLs oder Cache-Effekte auslösen. Sie erklären jedoch nicht direkt die Attributfilterung, die über die Attributrelationen erfolgt.

## Open points

- Festlegen, welche deutsche URL pro Slave kanonisch bleiben soll.
- Danach die jeweils andere deutsche SEO-Zeile kontrolliert bereinigen oder in eine Weiterleitung überführen.

## Validation steps

- SEO-Tabellenschema beider Datenbanken verglichen.
- Alle SEO-Zeilen für HA-000 und die vier Slaves direkt ausgelesen.

## Recommended next step

Die doppelten deutschen SEO-Zeilen in `shop2` konsolidieren. Vorher sollte eine Sicherung von `xt_seo_url` erstellt werden.
