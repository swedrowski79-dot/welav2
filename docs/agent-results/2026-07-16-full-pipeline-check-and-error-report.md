Task
- Full Pipeline ausfuehren, pruefen ob sie komplett durchlaeuft, Logs/Fehler auswerten und einen Fehlerbericht fuer die Behebung schreiben.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- docs/CODEX_WORKFLOW.md
- run_full_pipeline.php
- run_import_all.php
- run_export_queue.php
- config/pipeline.php
- config/delta.php
- config/sources.php
- src/Service/ImportWorkflow.php
- src/Service/AfsExtrasBootstrapService.php
- src/Service/AttributeTranslationDictionaryService.php
- src/Service/ExportQueueWorker.php
- src/Service/XtProductWriter.php
- src/Service/WelaApiClient.php

Changed files
- docs/agent-results/2026-07-16-full-pipeline-check-and-error-report.md

Summary
- Die Full Pipeline wurde am 2026-07-16 im Container mit `docker compose exec php php run_full_pipeline.php` gestartet.
- Die Pipeline laeuft nicht sauber bis zum Ende durch.
- Erfolgreich abgeschlossen:
  - `import_all`
  - `merge`
  - `xt_mirror`
  - `expand`
- Blockiert/fehlerhaft:
  - `export_queue_worker`
- Der Full-Run blieb dadurch auf `running` stehen und war zum Pruefzeitpunkt nicht beendet.

Open points
- Der laufende `full_pipeline`-Prozess war zum Pruefzeitpunkt noch aktiv.
- Der `export_queue_worker` hinterlaesst tausende `processing`-Eintraege und tausende offene `sync_errors`.

Validation steps
- `docker compose exec php php run_full_pipeline.php`
- Auswertung von:
  - `sync_runs`
  - `sync_logs`
  - `sync_errors`
  - `export_queue`
  - `SHOW PROCESSLIST`
  - `docker compose top php`

Recommended next step
- Zuerst das Schema-/Retry-Problem im Export Worker beheben.
- Danach die haengenden `processing`-Eintraege bereinigen bzw. resetten.
- Anschliessend den Export Worker mit kleinerem Batch erneut testen, bevor die komplette Full Pipeline wieder gestartet wird.

Fehlerbericht

1. Pipeline-Endstatus
- `full_pipeline` (`sync_runs.id = 1`) blieb auf `status = running`.
- `export_queue_worker` (`sync_runs.id = 6`) blieb auf `status = running`.
- Zum Pruefzeitpunkt war kein sauberer Abschluss (`success` oder `failed`) fuer diese beiden Runs vorhanden.

2. Erfolgreiche Schritte mit Zeiten
- `import_all`: `2026-07-16 21:35:02` bis `2026-07-16 21:39:29` UTC, `success`
- `merge`: `2026-07-16 21:39:29` bis `2026-07-16 21:41:52` UTC, `success`
- `xt_mirror`: `2026-07-16 21:41:52` bis `2026-07-16 21:43:06` UTC, `success`
- `expand`: `2026-07-16 21:43:06` bis `2026-07-16 21:46:52` UTC, `success`

3. Auffaelliger Performance-Engpass im Import
- Zwischen `AFS Artikel importiert` (`2026-07-16 21:35:16` UTC) und `Zentrale Attribut-Uebersetzungen in afs_extras synchronisiert` (`2026-07-16 21:39:23` UTC) lag ein deutlicher Engpass.
- Ursache ist sehr wahrscheinlich die zeilenweise Insert-Logik in [src/Service/AttributeTranslationDictionaryService.php](/programming/welav2/src/Service/AttributeTranslationDictionaryService.php), insbesondere `insertAttributeRow()`.
- Beobachtete Werte:
  - erwartete Attribut-Basiszeilen: `10.127`
  - resultierende `afs_extras.attribute_translations`: `40.508`
- Das ist kein funktionaler Abbruch, aber ein klarer Performance-Bottleneck.

4. Eigentliche technische Fehlerursache im Export Worker
- Die ersten echten Exportfehler kamen aus der XT-API:
  - `original_exception = "XT-API lieferte kein gueltiges JSON."`
- Diese Fehler haetten vom Worker in einen Retry/Error-Zustand ueberfuehrt werden muessen.
- Stattdessen scheitert die Fehlerbehandlung selbst mit:
  - `failure_handler_exception = "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'next_retry_at' in 'field list'"`
- Das ist in [src/Service/ExportQueueWorker.php](/programming/welav2/src/Service/ExportQueueWorker.php) direkt sichtbar:
  - In `handleFailure()` wird `next_retry_at` geschrieben.
  - Die aktuelle Tabelle `export_queue` hat diese Spalte nach Reset/Neuimport aus `database.sql` nicht.

5. Konkrete Folge des Bugs
- Produkt-Queue-Eintraege wurden auf `processing` geclaimt, konnten aber bei Fehlern nicht korrekt auf `pending` oder `error` zurueckgeschrieben werden.
- Dadurch blieb die Queue in einem inkonsistenten Zustand stehen.
- Beobachteter Queue-Status zum Pruefzeitpunkt:
  - `done category = 280`
  - `processing product = 6872`
  - `pending document = 2956`
  - `pending media = 187`
- Beispiel-Eintraege:
  - `export_queue.id = 1925`, `1924`, `1923`
  - `status = processing`
  - `attempt_count = 1`
  - `claimed_at = 2026-07-16 21:48:32`
  - `processed_at = NULL`
  - `last_error = NULL`

6. Sichtbares Fehlersymptom im Monitoring
- Fuer `sync_run_id = 6` wurden massenhaft Fehler geschrieben:
  - `message = "Export Queue Fehlerbehandlung fehlgeschlagen."`
- Offene Fehler zum Pruefzeitpunkt:
  - `5475`
- Beispiel aus `sync_errors.details`:
  - `original_exception: XT-API lieferte kein gueltiges JSON.`
  - `failure_handler_exception: Unknown column 'next_retry_at' in 'field list'`

7. Wahrscheinliche Root Cause
- Nach dem kompletten DB-Reset wurde nur `database.sql` importiert.
- `export_queue` ist damit auf dem Stand aus `database.sql`.
- Der Code in `ExportQueueWorker` erwartet jedoch zusaetzliche Retry-Spalten, mindestens:
  - `next_retry_at`
- Diese Spalte fehlt im aktuellen Schema.
- Damit ist der Export Worker nicht schema-kompatibel zum frisch importierten Basis-Schema.

8. Sekundaerer Risikofaktor
- In `.env` ist `EXPORT_WORKER_BATCH_SIZE="10000"` gesetzt.
- Gleichzeitig ist in `config/delta.php` fuer Produkte `worker_batch_size = 5000` hinterlegt.
- Der Worker claimed dadurch sehr grosse Produktmengen auf einmal.
- Das ist nicht die primaere Ursache des Defekts, vergroessert aber die Auswirkung massiv, weil tausende Eintraege gleichzeitig in `processing` landen.

9. Behebungsreihenfolge
- Schema angleichen:
  - sicherstellen, dass `export_queue.next_retry_at` und alle vom Worker erwarteten Retry-/Claim-Spalten nach einem Fresh-Reset vorhanden sind
  - entweder in `database.sql` nachziehen oder die Migrationen automatisiert nach dem Reset ausfuehren
- Danach Queue bereinigen:
  - `processing`-Eintraege zuruecksetzen oder Queue neu aufbauen
- Danach Export Worker konservativ testen:
  - mit kleinerem Batch
  - zuerst nur `run_export_queue.php 50` oder aehnlich
- Danach erst wieder `run_full_pipeline.php`
