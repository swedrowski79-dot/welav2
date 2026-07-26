## Task

Export-Queue-Worker analysieren und den aktuellen Ausfall beheben.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `run_export_queue.php`
- `run_full_pipeline.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/WelaApiClient.php`

## Changed files

- `run_export_queue.php`
- `docs/agent-results/2026-04-20-export-queue-worker-fix.md`

## Summary

- Ursache des Ausfalls: `run_export_queue.php` hat die XT-Writer geladen, aber nicht `src/Service/WelaApiClient.php`.
- Dadurch ist der Worker bereits beim Start mit `Class "WelaApiClient" not found` abgebrochen.
- Der Fix ist minimal: fehlendes `require __DIR__ . '/src/Service/WelaApiClient.php';` in `run_export_queue.php` ergänzt.
- Danach wurde der Worker erfolgreich validiert und ausgefuehrt.

Verifizierter Testlauf:
- `run_type = export_queue_worker`
- `status = success`
- `claimed = 40`
- `done = 40`
- `error = 0`

Bearbeitete Entitaeten im Testlauf:
- `category = 10`
- `product = 10`
- `media = 10`
- `document = 10`

Queue-Stand danach:
- `done = 3389`
- `pending = 13949`

## Open points

- Die alten `sync_errors`-Eintraege mit `Class "WelaApiClient" not found` bleiben als Historie bestehen, sind aber durch den erfolgreichen Worker-Lauf technisch ueberholt.

## Validation steps

- `docker compose exec -T php php -l /app/run_export_queue.php`
- `docker compose exec -T php php /app/run_export_queue.php 10`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, run_type, status, started_at, ended_at, message, context_json FROM sync_runs WHERE run_type='export_queue_worker' ORDER BY id DESC LIMIT 3;"`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT status, COUNT(*) FROM export_queue GROUP BY status;"`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT id, sync_run_id, source, record_identifier, message, created_at FROM sync_errors ORDER BY id DESC LIMIT 10;"`

## Recommended next step

- Den Worker jetzt wieder regulär mit der gewuenschten Batch-Size laufen lassen oder die Full Pipeline erneut starten, falls der Export wieder komplett weiterlaufen soll.
