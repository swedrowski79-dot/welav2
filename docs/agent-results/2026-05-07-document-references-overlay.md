# Task

In der `documents_file`-Liste einen Overlay-Dialog ergaenzen, der beim Klick auf `Refs` zeigt, von welchen Artikeln ein Dokument referenziert wird, inklusive Pagination.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `public/index.php`
- `src/Web/Core/View.php`
- `src/Web/Core/Response.php`
- `src/Web/Core/Router.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`
- `src/Web/View/document-files/browse.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/partials/pagination.php`

# Changed files

- `public/index.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`
- `src/Web/View/document-files/references-overlay.php`
- `docs/agent-results/2026-05-07-document-references-overlay.md`

# Summary

- Die Spalte `Refs` in `documents_file` ist jetzt klickbar, sofern ein Dokument mindestens eine Referenz hat.
- Ein Klick oeffnet ein Overlay ueber der Dokumentseite.
- Das Overlay laedt die Referenzen per AJAX ueber den neuen Endpoint `GET /document-files/references`.
- Im Overlay wird eine paginierte Tabelle mit den referenzierenden Artikeln angezeigt.
- Die Tabelle zeigt:
  - `AFS Artikel`
  - `SKU`
  - `Produktname`
  - `Dateiname`
  - `Typ`
  - `Sort`
- Auch im Overlay gibt es wieder `Pro Seite` sowie Pagination oben und unten.

# Open points

- Die Zuordnung erfolgt aktuell ueber `documents_file.title = stage_product_documents.title`, weil genau darauf auch die bestehende Dokument-Datei-Logik basiert.
- Falls spaeter eine noch eindeutigere technische Dokument-ID durchgaengig verfuegbar sein soll, kann die Overlay-Abfrage auf diese umgestellt werden.

# Validation steps

- `docker compose exec -T php php -l /app/public/index.php`
- `docker compose exec -T php php -l /app/src/Web/Controller/DocumentFileController.php`
- `docker compose exec -T php php -l /app/src/Web/Repository/DocumentFileRepository.php`
- `docker compose exec -T php php -l /app/src/Web/View/document-files/index.php`
- `docker compose exec -T php php -l /app/src/Web/View/document-files/references-overlay.php`
- `curl http://localhost:8080/document-files | grep 'data-document-references-open'`
- `curl 'http://localhost:8080/document-files/references?id=1&per_page=10' | grep -E 'Referenzen|AFS Artikel|Keine Referenzen'`

# Recommended next step

Im Browser in `/document-files` auf einige `Refs`-Buttons klicken und die Referenzlisten fuer fehlende oder problematische Dokumente direkt zur Nachpflege der betroffenen Artikel verwenden.
