# Betroffene Artikel bei wartenden Medien und Dokumenten

## Task

Die Artikel ermitteln, deren Medien oder Dokumente wegen einer fehlenden XT-Produkt-Referenz warten.

## Files read

- `export_queue`
- `stage_products`
- `stage_product_media`
- `stage_product_documents`
- `sync_logs`

## Changed files

- `docs/agent-results/2026-07-20-pending-media-document-affected-products.md`

## Summary

Betroffen sind 551 Artikel mit 454 wartenden Medien und 190 wartenden Dokumenten. Die Produkt-Queue dieser Artikel steht überwiegend bereits auf `done`, aber der Medien-/Dokument-Writer kann die XT-Produkt-Referenz trotzdem nicht auflösen.

Die größte Gruppe sind Abzweige der Reihen `GABP-45-*` und `GABP-90-*`, zum Beispiel:

- `GABP-45-100-080-080-verz`
- `GABP-45-160-125-160-verz`
- `GABP-45-250-150-250-verz`
- `GABP-45-400-300-400-verz`
- `GABP-90-080-080-080-verz.`
- `GABP-90-450-450-450-verz.`

Weitere Beispiele sind `ZWR-03`, `ZWR-05`, `STBP-038-038-verz`, `GASI-500-0,5-verz` sowie die Reihe `BSBP-45-*`.

## Open points

- Prüfen, warum die XT-Produktauflösung bei diesen als erfolgreich exportierten Artikeln keine Referenz findet.

## Validation steps

- Wartende Media-/Document-Queue gegen `stage_products` gruppiert.
- Produktstatus und Retry-Fehler je betroffenem Artikel geprüft.

## Recommended next step

Die externe ID eines Beispielartikels wie `GABP-45-100-080-080-verz` in XT und im lokalen XT-Mirror vergleichen.
