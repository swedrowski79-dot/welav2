## Task

Alle laufenden Worker stoppen, Monitoring-Tabellen leeren und alle Artikel in der Export-Queue wieder auf `pending` setzen.

## Files read

- `AGENTS.md`

## Changed files

- `docs/agent-results/2026-07-17-stop-workers-reset-monitoring-and-pending.md`

## Summary

- Alle `run_export_queue.php`-Worker im Container wurden beendet.
- Produkt-Queue wurde komplett auf `pending` zurueckgesetzt.
- `sync_logs`, `sync_errors` und `sync_runs` wurden geleert.

## Open points

- Keine.

## Validation steps

- Prozesspruefung im Container: nur noch `php -S 0.0.0.0:8080 -t public` aktiv.
- Queue-Status geprueft: `product pending 5412`
- Monitoring geprueft: `sync_logs 0`, `sync_errors 0`, `sync_runs 0`

## Recommended next step

- Die gewuenschte Zahl Worker neu starten und den ersten Durchlauf zeitlich beobachten.
