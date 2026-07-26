# XT-Attribut-Mirror: Referenzexport

## Task

Eine Referenzsicherung der drei gespiegelten XT-Attributtabellen erstellen, bevor ein Shop-Backup eingespielt wird.

## Files read

- `database.sql`

## Changed files

- `backups/xt-attribute-mirror-reference-20260720-164515.sql.gz`
- `docs/agent-results/2026-07-20-xt-attribute-mirror-reference-export.md`

## Summary

Der Export enthält die aktuellen Daten aus dem zuletzt aktualisierten XT-Mirror für:

- `xt_mirror_plg_products_attributes`
- `xt_mirror_plg_products_attributes_description`
- `xt_mirror_plg_products_to_attributes`

## Open points

- Nach dem Einspielen des Shop-Backups den XT-Mirror aktualisieren und die drei Tabellen mit dieser Referenz vergleichen.

## Validation steps

- SQL-Dump der drei Tabellen erstellt und gzip-komprimiert.
- Erzeugte Datei: 615 KiB.

## Recommended next step

Shop-Backup einspielen, danach Bescheid geben. Anschließend den Mirror neu laden und die Attributstruktur vergleichen.
