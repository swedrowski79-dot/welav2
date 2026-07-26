## Task

Batchgroesse fuer den Export Worker im Frontend verfuegbar machen, ohne die bestehende Pipeline-Struktur umzubauen.

## Files read

- `src/Web/Core/Request.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/SyncLauncher.php`
- `run_export_queue.php`
- `src/Web/View/pipeline/index.php`
- `config/pipeline.php`
- `src/Service/ExportQueueWorker.php`

## Changed files

- `src/Web/Controller/PipelineController.php`
- `src/Web/Repository/SyncLauncher.php`
- `run_export_queue.php`
- `src/Web/View/pipeline/index.php`
- `docs/agent-results/2026-04-19-export-worker-batch-ui.md`

## Summary

- Im Frontend gibt es jetzt beim Job `Export Worker` ein Eingabefeld `Batchgroesse`.
- Der Wert wird nur fuer `export_queue_worker` ausgewertet; andere Pipeline-Jobs bleiben unveraendert.
- `PipelineController` uebergibt die Batchgroesse an `SyncLauncher`.
- `SyncLauncher` haengt den Wert als CLI-Argument an `run_export_queue.php`.
- `run_export_queue.php` liest das Argument ein und reicht es als optionales Limit an `ExportQueueWorker->run($limit)` weiter.
- Wenn das Feld leer bleibt, gilt weiterhin der Standardwert aus `config/delta.php`.

## Open points

- Die Batchgroesse ist aktuell nur fuer den direkten Frontend-Start des Jobs `Export Worker` vorhanden, nicht fuer `Full Pipeline`.
- Die Start-Erfolgsmeldung im Frontend zeigt derzeit nur an, dass ein Job gestartet wurde, aber nicht mit welcher Batchgroesse.

## Validation steps

- `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
- `docker compose exec -T php php -l /app/src/Web/Repository/SyncLauncher.php`
- `docker compose exec -T php php -l /app/run_export_queue.php`
- `docker compose exec -T php php -l /app/src/Web/View/pipeline/index.php`

## Recommended next step

Den Export Worker einmal im Frontend mit einer kleinen Batchgroesse testen, z. B. `10`, und danach auf der Pipeline-Seite sowie in den Run-Logs pruefen, dass nur entsprechend viele claimbare Queue-Eintraege verarbeitet wurden.
