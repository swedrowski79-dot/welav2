# Task

Button in der Dokument-Datei-Ansicht ergänzen, der gezielt die nicht gefundenen Dokumente anzeigt, damit diese nachgearbeitet werden können.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`
- `src/Web/Core/Html.php`

# Changed files

- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`
- `docs/agent-results/2026-05-07-document-missing-filter.md`

# Summary

- Die Dokument-Seite kennt jetzt den Listenfilter `filter=missing`.
- Oben in `documents_file` gibt es zwei Buttons:
  - `Alle Dokumente`
  - `Nicht gefundene Dokumente`
- Der neue Button zeigt die aktuelle Anzahl fehlender Dokumente direkt im Label an.
- `DocumentFileRepository` filtert die Liste fuer diesen Modus auf Datensaetze ohne `local_path`.
- Wenn im Filter keine Treffer vorhanden sind, erscheint eine passende Leermeldung statt einer leeren Tabelle.

# Open points

- Der Filter basiert bewusst auf fehlendem `local_path`, weil dieser Zustand direkt aus dem Dokument-Scan stammt.
- Falls spaeter zwischen mehreren Fehlerarten unterschieden werden soll, kann die Liste zusaetzlich auf `last_error` oder weitere Statuswerte erweitert werden.

# Validation steps

- `docker compose exec -T php php -l /app/src/Web/Controller/DocumentFileController.php`
- `docker compose exec -T php php -l /app/src/Web/Repository/DocumentFileRepository.php`
- `docker compose exec -T php php -l /app/src/Web/View/document-files/index.php`
- `curl http://localhost:8080/document-files?filter=missing | grep "Nicht gefundene Dokumente"`

# Recommended next step

Den Dokumentpfad erneut scannen und danach die gefilterte Liste `Nicht gefundene Dokumente` verwenden, um fehlende Quelldateien gezielt nachzupflegen.
