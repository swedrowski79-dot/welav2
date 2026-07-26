## Task

Reset-Button fuer `images_file` ergaenzen und die Bestaetigungswarnung fuer die Reset-Buttons bei Dokumenten und Bildern klarer formulieren.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `public/index.php`
- `src/Web/Controller/ImageFileController.php`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Web/View/image-files/index.php`
- `src/Web/View/document-files/index.php`

## Changed files

- `public/index.php`
- `src/Web/Controller/ImageFileController.php`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Web/View/image-files/index.php`
- `src/Web/View/document-files/index.php`
- `docs/agent-results/2026-07-16-image-table-reset-and-confirm.md`

## Summary

Die Bild-Verwaltung hat jetzt denselben Reset-Mechanismus wie die Dokument-Verwaltung:

- neue POST-Route `/image-files/reset`
- neue Controller-Aktion `reset()`
- neue Repository-Methode `resetTable()`
- UI-Button `Tabelle leeren`
- Erfolgsmeldung nach dem Leeren

Zusätzlich wurden die Confirm-Texte fuer beide Reset-Buttons auf eine deutlichere Warnung umgestellt:

- "Wollen Sie wirklich ..."
- Hinweis, dass die Aktion nicht rueckgaengig gemacht werden kann

## Open points

- Die Reset-Aktionen leeren jeweils nur `images_file` bzw. `documents_file`.
- Es gibt in diesem Schritt keinen gemeinsamen Sammelreset fuer beide Tabellen.

## Validation steps

- Syntaxpruefung fuer:
  - `public/index.php`
  - `src/Web/Controller/ImageFileController.php`
  - `src/Web/Repository/ImageFileRepository.php`
  - `src/Web/View/image-files/index.php`
  - `src/Web/View/document-files/index.php`

## Recommended next step

Nach einem Reset jeweils den passenden Scan erneut ausfuehren, damit `images_file` oder `documents_file` wieder aus den Stage-Daten aufgebaut werden.
