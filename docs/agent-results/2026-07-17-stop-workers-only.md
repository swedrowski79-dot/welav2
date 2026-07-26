## Task

Nur die laufenden Export-Worker stoppen, ohne Queue, Logs oder Runs zurueckzusetzen.

## Files read

- `AGENTS.md`
- `docs/agent-results/2026-07-17-worker-stop-and-queue-reset.md`

## Changed files

- `docs/agent-results/2026-07-17-stop-workers-only.md`

## Summary

- Export-Worker im Container `welav2-php` gezielt ueber `/proc/*/cmdline` beendet.
- Verifiziert, dass nur noch der PHP-Webserver (`php -S 0.0.0.0:8080 -t public`) laeuft.
- Queue, Logs und Runs wurden nicht veraendert.

## Open points

- In `stage_sync.export_queue` standen direkt danach noch `300` Eintraege auf `processing`; das sind bereits beanspruchte Jobs und wurde bewusst nicht zurueckgesetzt.

## Validation steps

- Prozessliste im Container ueber `/proc/[0-9]*/cmdline` auf `run_export_queue.php` geprueft.
- Queue-Status per MySQL-Abfrage geprueft.

## Recommended next step

Falls die `processing`-Eintraege wieder auf `pending` muessen, die Claims separat zuruecksetzen.
