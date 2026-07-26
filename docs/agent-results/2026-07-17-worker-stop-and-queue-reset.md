## Task

Alle laufenden Export-Worker stoppen, die komplette `export_queue` auf `pending` zurücksetzen und die Monitoring-Tabellen leeren.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `run_export_queue.php`

## Changed files

- `docs/agent-results/2026-07-17-worker-stop-and-queue-reset.md`

## Summary

- Der laufende Export-Worker im PHP-Container wurde beendet, indem der `php`-Container neu gestartet wurde.
- Die Tabelle `stage_sync.export_queue` wurde vollständig auf `pending` zurückgesetzt.
- Dabei wurden zusätzlich zurückgesetzt:
  - `attempt_count = 0`
  - `claim_token = NULL`
  - `claimed_at = NULL`
  - `processed_at = NULL`
  - `last_error = NULL`
  - `next_retry_at = NULL`
  - `available_at = UTC_TIMESTAMP()`
- Die Monitoring-Tabellen wurden geleert:
  - `stage_sync.sync_runs`
  - `stage_sync.sync_logs`
  - `stage_sync.sync_errors`

## Open points

- Keine.

## Validation steps

- `docker top welav2-php`
  - Ergebnis: nur noch `php -S 0.0.0.0:8080 -t public`
- `SELECT status, COUNT(*) FROM stage_sync.export_queue GROUP BY status;`
  - Ergebnis: `pending = 5412`
- `SELECT COUNT(*) FROM stage_sync.sync_runs;`
  - Ergebnis: `0`
- `SELECT COUNT(*) FROM stage_sync.sync_logs;`
  - Ergebnis: `0`
- `SELECT COUNT(*) FROM stage_sync.sync_errors;`
  - Ergebnis: `0`

## Recommended next step

- Den nächsten Export-Lauf bewusst kontrolliert neu starten, damit die Performance-Änderungen isoliert geprüft werden können.
