## Task

Beheben, dass Kategorie-Exporte ueber die XT-API mit `Maximum execution time of 120 seconds exceeded` abbrechen und dadurch kein gueltiges JSON zurueckkommt.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `src/Service/XtCategoryWriter.php`
- `src/Service/WelaApiClient.php`
- `config/sources.php`
- `wela-api/index.php`
- `wela-api/src/CategorySyncService.php`

## Changed files

- `src/Service/XtCategoryWriter.php`
- `src/Service/WelaApiClient.php`
- `config/sources.php`
- `docs/agent-results/2026-07-18-category-batch-timeout-fix.md`

## Summary

- Ursache eingegrenzt: Der Kategorie-Writer hat bisher alle Kategorien eines Worker-Laufs in genau einem einzigen API-Batch gesendet.
- Wenn die Kategorie-SEO in der Gegenseite zu lange braucht, laeuft der PHP/XAMPP-Server dort nach 120 Sekunden ins Timeout und liefert statt JSON nur den Fatal Error zurueck.
- Fix umgesetzt: Kategorien werden jetzt wie Produkte in feste Teil-Batches aufgeteilt.
- Neue Konfiguration:
  - `XT_CATEGORY_BATCH_REQUEST_SIZE` mit Default `25`
  - `XT_CATEGORY_BATCH_REQUEST_MAX_PAYLOAD_BYTES` mit Default `0`
- Kein dynamisches Downshifting eingebaut. Die Schnittstelle bleibt fachlich gleich; nur die Aufteilung der Requests wurde geaendert.

## Open points

- Der Fix beseitigt das strukturelle Timeout-Risiko durch zu grosse Kategorie-Batches. Falls die SEO-Erzeugung fuer einzelne Kategorien selbst extrem langsam ist, muss die Gegenseiten-API separat weiter optimiert werden.

## Validation steps

- `php -l src/Service/XtCategoryWriter.php`
- `php -l src/Service/WelaApiClient.php`
- `php -l config/sources.php`

## Recommended next step

- Kategorie-Eintraege erneut auf `pending` setzen und den Exportlauf mit den neuen kleineren Kategorie-Batches erneut starten.
