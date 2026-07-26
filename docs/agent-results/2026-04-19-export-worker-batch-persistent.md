## Task

Export-Worker-Batchgroesse im Frontend persistent speicherbar machen, sodass derselbe Wert auch innerhalb der `Full Pipeline` automatisch verwendet wird.

## Files read

- `config/delta.php`
- `run_full_pipeline.php`
- `run_export_queue.php`
- `src/Web/Repository/EnvFileRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`

## Changed files

- `.env`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `run_export_queue.php`
- `docs/agent-results/2026-04-19-export-worker-batch-persistent.md`

## Summary

- Es gibt jetzt einen persistenten Wert `EXPORT_WORKER_BATCH_SIZE` in `.env`.
- Im Frontend wird dieser Wert bei `Export Worker` und `Full Pipeline` als Eingabefeld angezeigt.
- Wenn du dort einen neuen Wert eintraegst und startest, wird er in `.env` gespeichert.
- `run_export_queue.php` nutzt zuerst ein optionales CLI-Argument und faellt sonst automatisch auf den gespeicherten `.env`-Wert zurueck.
- Dadurch gilt der gespeicherte Batchwert auch dann, wenn der Export Worker innerhalb von `Full Pipeline` spaeter gestartet wird.

## Open points

- Der gespeicherte Wert wird aktuell beim Start still in `.env` aktualisiert; es gibt noch keine gesonderte UI-Meldung wie „Batchgroesse gespeichert“.
- `config/delta.php` bleibt unveraendert; der persistente Wert wird direkt im Worker-Skript eingelesen.

## Validation steps

- `docker compose exec -T php php -l /app/src/Web/Controller/PipelineController.php`
- `docker compose exec -T php php -l /app/src/Web/View/pipeline/index.php`
- `docker compose exec -T php php -l /app/run_export_queue.php`
- `.env` enthaelt jetzt `EXPORT_WORKER_BATCH_SIZE="1000"`

## Recommended next step

Einmal im Frontend bei `Full Pipeline` oder `Export Worker` testweise einen kleineren Wert wie `25` eintragen und starten; danach pruefen, dass `.env` den neuen Wert enthaelt und der Worker mit genau diesem Limit laeuft.
