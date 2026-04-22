## Task

Export-Queue-Worker so anpassen, dass die konfigurierte Batch-Groesse nur die Batch-Groesse pro Claim/XT-Aufruf ist und der Worker im selben Lauf weiterarbeitet, bis keine claimbaren Queue-Eintraege mehr uebrig sind.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `src/Service/ExportQueueWorker.php`
- `config/pipeline.php`
- `src/Web/Repository/SyncLauncher.php`

## Changed files

- `src/Service/ExportQueueWorker.php`
- `docs/agent-results/2026-04-20-export-worker-looping.md`

## Summary

- Vorher verarbeitete der Worker pro Start und Entity-Typ nur genau einen Claim-Batch und beendete sich danach.
- Jetzt loopt `runCurrentEntity()` ueber mehrere Claim-Batches weiter, bis keine claimbaren Eintraege mehr vorhanden sind.
- Die Batch-Groesse bleibt dabei pro Claim/XT-Aufruf erhalten.
- Zusaetzlich wird jetzt `batches` im Statistikblock mitgefuehrt.

Verifizierung:
- Syntaxcheck erfolgreich.
- Testlauf mit `run_export_queue.php 1` gestartet.
- Nach kurzer Laufzeit war der Worker weiterhin aktiv und hatte bereits deutlich mehr als einen einzelnen 4er-Minidurchlauf abgearbeitet:
  - vorher `done = 9423`, `pending = 9956`
  - kurz danach `done = 9593`, `pending = 9785`
- Der zugehoerige `sync_runs`-Eintrag stand waehrenddessen auf `running` mit `batch_size = 1`.

## Open points

- Der aktuell gestartete Testlauf wurde bewusst nicht kuenstlich beendet, damit der Worker die Queue weiter abbauen kann.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/ExportQueueWorker.php`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT COUNT(*) FROM export_queue WHERE status='done'; SELECT COUNT(*) FROM export_queue WHERE status='pending';"`
- `docker compose exec -T php php /app/run_export_queue.php 1`
- erneute Pruefung waehrend des Laufs:
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT COUNT(*) FROM export_queue WHERE status='done'; SELECT COUNT(*) FROM export_queue WHERE status='pending'; SELECT id, status, context_json FROM sync_runs WHERE run_type='export_queue_worker' ORDER BY id DESC LIMIT 1;"`

## Recommended next step

- Den Worker jetzt mit der gewuenschten produktiven Batch-Groesse weiterlaufen lassen oder ueber das UI erneut starten, falls nach dem Testlauf ein groesserer Durchsatz gewuenscht ist.
