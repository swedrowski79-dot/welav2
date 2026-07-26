## Task

Reset-Button fuer den Dokumentlauf ergaenzen, um `documents_file` direkt aus der Admin-Oberflaeche zu leeren.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `public/index.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`

## Changed files

- `public/index.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`
- `docs/agent-results/2026-07-16-document-table-reset.md`

## Summary

Die Dokument-Verwaltung hat jetzt einen eigenen Reset-Button fuer `documents_file`.

Umsetzung:

- neue POST-Route `/document-files/reset`
- neue Controller-Aktion `reset()`
- neue Repository-Methode `resetTable()`
- UI-Button `Tabelle leeren` mit Bestaetigungsdialog
- Erfolgsmeldung nach dem Leeren

Es wird bewusst nur `documents_file` geleert. Pipeline-Tabellen und andere Monitoring-Daten bleiben unberuehrt.

## Open points

- Fuer `images_file` gibt es in diesem Schritt noch keinen entsprechenden Reset-Button.

## Validation steps

- Syntaxpruefung fuer:
  - `public/index.php`
  - `src/Web/Controller/DocumentFileController.php`
  - `src/Web/Repository/DocumentFileRepository.php`
  - `src/Web/View/document-files/index.php`

## Recommended next step

Nach dem Reset einmal `Dokumentenpfad scannen` ausfuehren, damit `documents_file` wieder aus `stage_product_documents` aufgebaut und neu mit Pfaden/Hashes befuellt wird.
