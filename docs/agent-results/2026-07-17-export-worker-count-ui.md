Task
- Export-Worker im Pipeline-Webinterface um eine konfigurierbare Worker-Anzahl erweitern und zusammen mit der Batchgroesse persistent steuerbar machen.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- src/Web/Controller/PipelineController.php
- src/Web/View/pipeline/index.php
- src/Web/Repository/SyncLauncher.php
- src/Web/Repository/EnvFileRepository.php
- run_export_queue.php
- run_full_pipeline.php
- config/pipeline.php

Changed files
- src/Web/Controller/PipelineController.php
- src/Web/View/pipeline/index.php
- run_export_queue.php
- docs/agent-results/2026-07-17-export-worker-count-ui.md

Summary
- Im Bereich `/pipeline` gibt es jetzt fuer `Export Worker` und `Full Pipeline` zwei persistente Eingabefelder:
  - `Export-Worker Batchgroesse`
  - `Export-Worker Anzahl`
- Beide Werte werden in `.env` gespeichert:
  - `EXPORT_WORKER_BATCH_SIZE`
  - `EXPORT_WORKER_COUNT`
- Beim Start eines reinen Export-Worker-Laufs speichert das UI die Werte und startet danach `run_export_queue.php`.
- `run_export_queue.php` liest die gespeicherte Worker-Anzahl.
- Wenn `EXPORT_WORKER_COUNT > 1` gesetzt ist, startet das Script mehrere parallele Child-Worker-Prozesse mit derselben Batchgroesse.
- Der Parent wartet auf alle Child-Worker und beendet sich erst, wenn alle Worker fertig sind.
- Dadurch funktioniert die Worker-Anzahl sowohl fuer den direkten `Export Worker`-Button als auch fuer `Full Pipeline`, weil der Full-Pipeline-Schritt am Ende ebenfalls `run_export_queue.php` ausfuehrt.

Open points
- Jeder Child-Worker erzeugt weiterhin einen eigenen Export-Worker-Lauf im Monitoring. Das ist technisch korrekt, kann aber die Run-Liste sichtbarer aufblasen.
- Die Einstellung gilt global fuer den Export-Worker-Lauf und nicht getrennt pro Entity-Typ.

Validation steps
- `docker compose exec -T php php -l src/Web/Controller/PipelineController.php`
- `docker compose exec -T php php -l src/Web/View/pipeline/index.php`
- `docker compose exec -T php php -l run_export_queue.php`
- `curl -s http://localhost:8080/pipeline | rg -n "Export-Worker Batchgroesse|Export-Worker Anzahl|Export Worker|Full Pipeline"`

Recommended next step
- Einen kurzen Testlauf mit kleiner Batchgroesse und `2` Workern starten und danach auf `/pipeline` pruefen, ob `processing` und `done` wie erwartet ansteigen.
