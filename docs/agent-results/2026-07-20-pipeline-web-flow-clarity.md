# Task

Die Pipeline-Steuerung im Webinterface so darstellen, dass die Reihenfolge der Schritte sowie in Buttons zusammengefasste Teilschritte sofort erkennbar sind.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/pipeline.php`
- `run_full_pipeline.php`
- `src/Service/PipelineConfig.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `docs/agent-results/2026-04-15-T-035-web-pipeline-flow-alignment.md`
- `docs/agent-results/2026-04-15-T-036-web-pipeline-runner-alignment.md`

# Changed files

- `config/pipeline.php`
- `src/Web/View/pipeline/index.php`
- `docs/agent-results/2026-07-20-pipeline-web-flow-clarity.md`

# Summary

- Die Seite `/pipeline` zeigt jetzt oberhalb der Steuerung den konfigurierten Standardablauf als fünf nummerierte Schritte: Import, Merge, XT Mirror, Expand inklusive Delta und Export Worker.
- Die manuellen Bereiche folgen derselben Reihenfolge; XT Mirror steht damit vor Expand und Delta, wie in der Full-Pipeline-Konfiguration.
- Zusammengefasste Aktionen tragen ein sichtbares Label `Sammelaktion` und nennen ihre Teilschritte:
  - Import startet Produkt- und Kategorie-Import.
  - Expand startet Stage-Expand und Delta/Queue-Befuellung.
  - Full Pipeline startet alle fünf Standard-Schritte.
- Nicht zusammengefasste Aktionen sind explizit als `Einzelschritt` gekennzeichnet.
- Ausführungslogik, Queue-Verarbeitung und CLI-Runner wurden nicht verändert.

# Open points

- Die Detailansicht zeigt weiterhin den jeweils laufenden oder zuletzt ausgeführten technischen Run; eine eigene grafische Fortschrittsanzeige für einen gerade laufenden Full-Pipeline-Run wäre ein separater Ausbau.

# Validation steps

- `php -l config/pipeline.php`
- `php -l src/Web/View/pipeline/index.php`
- `git diff --check`
- `curl -fsS http://localhost:8080/pipeline` und Prüfung auf `Standardablauf`, `5 Schritte`, `Sammelaktion`, `4. Expand & Delta` und `6. Komplettlauf`.

# Recommended next step

Bei einem späteren Ausbau den aktiven Full-Pipeline-Fortschritt anhand seiner vorhandenen `sync_logs` als Schrittanzeige im selben Standardablauf markieren.
