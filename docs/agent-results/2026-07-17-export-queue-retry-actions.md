Task
- Retry-Funktionen im Pipeline-Webinterface ergaenzen, damit fehlgeschlagene Export-Queue-Eintraege gezielt erneut auf `pending` gesetzt werden koennen.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- public/index.php
- src/Web/Core/Request.php
- src/Web/Core/Paginator.php
- src/Web/Controller/PipelineController.php
- src/Web/Repository/PipelineAdminRepository.php
- src/Web/View/pipeline/index.php

Changed files
- public/index.php
- src/Web/Controller/PipelineController.php
- src/Web/Repository/PipelineAdminRepository.php
- src/Web/View/pipeline/index.php
- docs/agent-results/2026-07-17-export-queue-retry-actions.md

Summary
- Es wurde eine neue POST-Route `/pipeline/retry` hinzugefuegt.
- In der Pipeline-Ansicht gibt es jetzt drei Retry-Ebenen:
  - einen globalen Button fuer alle fehlgeschlagenen Queue-Eintraege
  - einen Retry-Button pro Entity-Typ (`product`, `media`, `category`, `document`)
  - einen Retry-Button pro einzelner fehlerhafter Queue-Zeile
- Beim Retry werden nur Eintraege mit `status = error` zurueckgesetzt.
- Die Ruecksetzung setzt `status` auf `pending`, leert Claim-/Fehlerfelder, setzt `available_at` auf `NOW()` und setzt `attempt_count` wieder auf `0`.
- Nach einer Retry-Aktion bleibt der aktuelle Filterzustand der Pipeline-Ansicht erhalten.
- Zusaetzlich wurden am 2026-07-17 einmalig `500` haengende `product`-Eintraege von `processing` manuell auf `pending` zurueckgesetzt, nachdem ein laufender Worker gezielt beendet wurde.

Open points
- Es gibt noch keine gesonderte Retry-Aktion fuer `processing`-Eintraege. Fuer haengende `processing`-Faelle ist weiterhin der bestehende Claim-Release-/Worker-Pfad massgeblich.
- Die Funktion wurde per Syntaxcheck validiert, aber noch nicht ueber die Weboberflaeche manuell durchgeklickt.

Validation steps
- `docker compose exec php php -l public/index.php`
- `docker compose exec php php -l src/Web/Controller/PipelineController.php`
- `docker compose exec php php -l src/Web/Repository/PipelineAdminRepository.php`
- `docker compose exec php php -l src/Web/View/pipeline/index.php`
- `docker compose exec php php -r '... UPDATE export_queue ... WHERE entity_type = "product" AND status = "processing"'`
- `docker compose exec php php -r '... SELECT status, COUNT(*) FROM export_queue WHERE entity_type = "product" GROUP BY status'`

Recommended next step
- Im Browser `/pipeline` oeffnen, einen einzelnen fehlerhaften Eintrag und danach einen Entity-Typ-Retry testen und anschliessend den Export-Worker erneut starten.
