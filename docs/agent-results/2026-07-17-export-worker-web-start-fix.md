## Task

Den Export-Worker-Start aus dem Webinterface reparieren.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docker-compose.yml`
- `public/index.php`
- `src/Web/bootstrap.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/SyncLauncher.php`
- `src/Service/PipelineConfig.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/XtProductWriter.php`

## Changed files

- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-07-17-export-worker-web-start-fix.md`

## Summary

- Der Web-Startpfad selbst war funktionsfaehig.
- Der Export-Worker ist aber sofort mit einem Fatal Error im Produkt-Writer abgestuerzt.
- Ursache:
  - `AbstractXtWriter::decodeQueuePayload()` war `protected`
  - `XtProductWriter::decodeQueuePayload()` war noch `private`
  - dadurch starb `run_export_queue.php` beim Start mit einem Visibility-Fehler
- Fix:
  - `XtProductWriter::decodeQueuePayload()` auf `protected` angehoben
- Danach wurde der Launcher erfolgreich verifiziert:
  - Start ueber denselben `SyncLauncher`-Pfad wie im Webinterface
  - neue `export_queue_worker`-Runs wurden angelegt
  - Prozesse fuer Parent und Child-Worker liefen an
- Die Test-Worker wurden anschliessend wieder beendet und ihre `sync_runs` als manuell beendet markiert.

## Open points

- Die Queue selbst wurde bei diesem Fix nicht inhaltlich veraendert.
- Falls weitere Startprobleme auftreten, sollte als naechstes `/tmp/export_queue_worker.log` geprueft werden.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/XtProductWriter.php`
- `docker compose exec -T php php /app/run_export_queue.php 10`
- `docker compose exec -T php sh -lc 'php -r "... SyncLauncher()->launch(\"export_queue_worker\", [\"batch_size\" => 10]) ..."'`
- Prozesspruefung mit `ps -ef | grep '[r]un_export_queue.php'`
- DB-Pruefung der angelegten `sync_runs` fuer `export_queue_worker`

## Recommended next step

- Den Export-Worker jetzt erneut normal ueber das Webinterface starten und die Queue-Verarbeitung beobachten.
