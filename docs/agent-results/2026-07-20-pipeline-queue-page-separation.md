# Task

Die Pipeline-Seite weiter verdichten und umfangreiche Queue-Verwaltung auf eine eigene Seite auslagern.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `public/index.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/state.php`
- `src/Web/Repository/PipelineAdminRepository.php`

# Changed files

- `public/index.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/pipeline/queue.php`
- `docs/agent-results/2026-07-20-pipeline-queue-page-separation.md`

# Summary

- Neue Seite `/pipeline/queue` bündelt Queue-Zähler, Delta-/Worker-Kurzstatus, Datentyp-Übersicht, Retry-Aktionen, Filter und Eintragsliste.
- `/pipeline` ist jetzt auf Ablauf, Startaktionen und einen direkten Verweis zur Queue-Verwaltung reduziert.
- Reset-Aktionen bleiben auf `/pipeline` verfügbar, liegen aber unter dem eingeklappten Bereich `Erweiterte Wartung`.
- Die Navigation enthält einen direkten Eintrag `Export Queue`.
- Queue-Datenzugriff und bestehende Retry-Logik wurden weiterverwendet; es wurden keine Synchronisations- oder Exportabläufe geändert.

# Open points

- Die bisherige ausführliche Pipeline-Laufhistorie ist auf `/pipeline` bewusst ausgeblendet. Laufdetails bleiben über `Laeufe` verfügbar.

# Validation steps

- `php -l public/index.php`
- `php -l src/Web/Controller/PipelineController.php`
- `php -l src/Web/View/layouts/app.php`
- `php -l src/Web/View/pipeline/index.php`
- `php -l src/Web/View/pipeline/queue.php`
- `git diff --check`
- Lokaler Abruf von `/pipeline` und `/pipeline/queue`.

# Recommended next step

Die Nutzung im Alltag prüfen: Wenn der Queue-Bereich häufig nur auf Fehler kontrolliert wird, kann später ein zusätzlicher Direktfilter `Nur Fehler` in der Seitenleiste ergänzt werden.
