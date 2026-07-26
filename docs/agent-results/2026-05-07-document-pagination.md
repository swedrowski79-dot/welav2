# Task

Die `documents_file`-Liste mit echter Pagination, waehlbarer Seitengroesse und uebersichtlicher Navigation ober- und unterhalb der Tabelle erweitern.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `src/Web/Core/Controller.php`
- `src/Web/Core/Paginator.php`
- `src/Web/Core/Request.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`
- `src/Web/View/stage-browser/index.php`
- `src/Web/View/logs/index.php`
- `src/Web/View/partials/pagination.php`

# Changed files

- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`
- `docs/agent-results/2026-05-07-document-pagination.md`

# Summary

- Die Dokumentliste verwendet jetzt denselben `Paginator` wie die restliche Admin-Oberflaeche.
- Auf `/document-files` gibt es jetzt eine Auswahl `Pro Seite` mit `10`, `20`, `50`, `100`.
- Die Liste wird serverseitig paginiert; `page` und `per_page` werden sauber ueber Query-Parameter verarbeitet.
- Die Pagination wird bewusst **oberhalb und unterhalb** der Tabelle angezeigt.
- Der vorhandene Filter `Nicht gefundene Dokumente` bleibt erhalten und funktioniert gemeinsam mit `per_page` und `page`.

# Open points

- Aktuell ist die Liste nach `upload DESC`, `updated_at DESC`, `title ASC` sortiert; falls spaeter andere Arbeitsmodi wichtiger sind, koennte zusaetzlich eine Sortierauswahl sinnvoll sein.
- Der Filterbereich ist aktuell bewusst schlank gehalten und enthaelt nur die Seitengroesse sowie die vorhandenen Schnellfilter.

# Validation steps

- `docker compose exec -T php php -l /app/src/Web/Controller/DocumentFileController.php`
- `docker compose exec -T php php -l /app/src/Web/Repository/DocumentFileRepository.php`
- `docker compose exec -T php php -l /app/src/Web/View/document-files/index.php`
- `curl 'http://localhost:8080/document-files?per_page=10&page=2' | grep 'Seite 2 von'`
- `curl 'http://localhost:8080/document-files?filter=missing&per_page=10' | grep 'Nicht gefundene Dokumente'`

# Recommended next step

Die Dokumentliste einmal im Browser mit den Groessen `10` und `50` durchklicken und den Filter `Nicht gefundene Dokumente` fuer die Nachpflege der fehlenden Dateien verwenden.
