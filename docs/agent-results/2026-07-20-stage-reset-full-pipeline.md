# Stage-Reset mit Full Pipeline

## Task

Die Stage-Datenbank zurücksetzen, die Extra-Datenbank erhalten und die Full Pipeline neu starten.

## Files read

- `database.sql`
- `scripts/setup-database.php`
- `run_full_pipeline.php`
- `config/sources.php`

## Changed files

- `docs/agent-results/2026-07-20-stage-reset-full-pipeline.md`

## Summary

`stage_sync` wurde neu erstellt und das Schema erneut importiert. `afs_extras` und `stage_sync_attribute_reference` wurden nicht verändert. Die Full Pipeline wurde anschließend gestartet.

## Open points

- Die Full Pipeline läuft noch.

## Validation steps

- `raw_afs_articles`: 5.489 Datensätze nach dem Import.
- `raw_extra_article_translations`: 19.750 Datensätze nach dem Import.
- `afs_extras` enthält weiterhin die Tabellen für Artikel-, Attribut- und Kategorieübersetzungen.

## Recommended next step

Nach Pipeline-Abschluss Queue- und Exportstatus prüfen.
