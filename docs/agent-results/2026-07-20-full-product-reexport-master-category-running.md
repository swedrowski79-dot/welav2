# Vollständiger Produkt-Reexport mit Master-Kategorien

## Task

Alle Artikel nach der Korrektur der Slave-Kategorie erneut nach XT exportieren.

## Files read

- `config/delta.php`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/XtProductWriter.php`

## Changed files

- `docs/agent-results/2026-07-20-full-product-reexport-master-category-running.md`

## Summary

Alle 5.489 Produkt-Einträge wurden erneut auf `pending` gesetzt und ein auf Produkte begrenzter Export-Worker in 500er-Batches gestartet. Kategorien, Medien und Dokumente werden nicht verarbeitet.

## Open points

- Der Produkt-Export läuft noch.

## Validation steps

- Queue vor Start: 5.489 Produkte `pending`.
- Zwischenstand: 2.264 `done`, 500 `processing`, 2.725 `pending`.
- Monitoring-Lauf: `sync_runs.id = 46`, Status `running`.

## Recommended next step

Nach Abschluss die Queue-Fehler und die Slave-SEO stichprobenartig prüfen.
