# Task

Webinterface, insbesondere die Pipeline-Seite, nach einem Backup übersichtlicher und kompakter gestalten.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `public/index.php`
- `config/pipeline.php`
- `run_full_pipeline.php`
- `src/Service/PipelineConfig.php`
- `src/Web/Controller/DashboardController.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/dashboard/index.php`
- `src/Web/View/pipeline/index.php`
- `src/Web/View/status/index.php`

# Changed files

- `backups/welav2-before-ui-cleanup-20260720-095623.tar.gz`
- `config/pipeline.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/pipeline/index.php`
- `docs/agent-results/2026-07-20-webinterface-compact-navigation.md`

# Summary

- Vor den Änderungen wurde ein vollständiges lokales Arbeitsstand-Backup ohne `.git` erstellt (492 MB).
- Die Navigation ist jetzt in die Aufgabenbereiche `Betrieb`, `Daten` und `System` gruppiert und nutzt passende Icons.
- Die Pipeline-Seite zeigt den Standardablauf als kompakte Schrittfolge statt als große Kartenreihe.
- `Full Pipeline` steht als empfohlene Aktion zuerst und ist direkt geöffnet.
- Alle manuellen Bereiche sind standardmäßig eingeklappt und mit `Manuell` markiert; sie bleiben vollständig verfügbar.
- Keine Pipeline-, Queue- oder Exportlogik wurde geändert.

# Open points

- Weitere Spezialseiten wie Stage-Browser und Datei-Verwaltung wurden nicht strukturell umgebaut; sie profitieren jedoch von der neuen gruppierten Navigation.

# Validation steps

- `php -l config/pipeline.php`
- `php -l src/Web/View/pipeline/index.php`
- `php -l src/Web/View/layouts/app.php`
- `git diff --check`
- Lokale Abrufe von `/pipeline` und `/` mit Prüfung auf Standardablauf, Full Pipeline, manuelle Bereiche sowie die Navigationsgruppen.

# Recommended next step

Im nächsten UI-Schritt die Stage-Browser- und Datei-Listen mit einer gemeinsamen Filter-/Suchleiste vereinheitlichen, falls diese Ansichten ebenfalls häufig genutzt werden.
