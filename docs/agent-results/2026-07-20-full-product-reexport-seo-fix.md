# Vollständiger Produkt-Reexport nach SEO-Korrektur

## Task

Alle Produkt-Queue-Einträge erneut nach XT exportieren, nachdem die SEO-Kanonisierung mit Redirects in die aktive API übernommen wurde.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/delta.php`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/XtBatchQueueWriter.php`
- `src/Service/XtCompositeWriter.php`

## Changed files

- `docs/agent-results/2026-07-20-full-product-reexport-seo-fix.md`

## Summary

Alle 5.489 Produkt-Queue-Einträge wurden zurück auf `pending` gesetzt und ausschließlich als Produkte erneut exportiert. Kategorien, Medien und Dokumente wurden nicht angefasst. Der Produkt-Worker lief in 500er-Batches.

Ergebnis:

- 4.909 Produkte: erfolgreich nach XT übertragen.
- 6 Produkte: Fehler, weil die referenzierten XT-Kategorien mit den External IDs `379` oder `380` fehlen.
- 574 Produkte: weiter wartend; darunter nicht auflösbare Attributwerte.

Der Exportlauf selbst ist mit Status `success` abgeschlossen. Die verbleibenden Einträge müssen fachlich bereinigt werden, bevor sie erfolgreich exportiert werden können.

## Open points

- Kategorien `379` und `380` in XT bereitstellen oder die betroffenen Produktkategorien korrigieren.
- Die wartenden Produkte mit nicht auflösbaren Attributwerten separat analysieren und korrigieren.

## Validation steps

- Queue-Status vor dem Lauf: 5.489 Produkte `pending`.
- Queue-Status nach dem Lauf: 4.909 `done`, 6 `error`, 574 `pending`.
- Lauf `sync_runs.id = 44`: `success`, 4.909 verarbeitete Datensätze, 6 Fehler.

## Recommended next step

SEO im Shop testen und anschließend die verbliebenen Kategorie- und Attributblocker beheben, bevor die Restqueue erneut gestartet wird.
