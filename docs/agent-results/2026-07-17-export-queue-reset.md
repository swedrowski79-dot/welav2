## Task

Laufende Export-Worker beenden, die komplette `export_queue` auf `pending` zuruecksetzen und Export-Logs leeren.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`

## Changed files

- `docs/agent-results/2026-07-17-export-queue-reset.md`

## Summary

- Alle laufenden `run_export_queue.php`-Prozesse wurden beendet.
- Die komplette `export_queue` wurde auf einen frischen Retry-Zustand gesetzt.
- `sync_logs` und `sync_errors` wurden geleert.
- Temporäre Worker-Logdateien unter `/tmp/export_queue_worker_*.log` wurden auf leer gesetzt.

## Open points

- `sync_runs` wurde bewusst nicht geleert.
- Falls auch alte Laufhistorie entfernt werden soll, muss das separat entschieden werden.

## Validation steps

- Prozesspruefung mit `ps -ef | grep '[r]un_export_queue.php'`
- Datenbankpruefung:
  - `SELECT status, COUNT(*) FROM export_queue GROUP BY status`
  - `SELECT COUNT(*) FROM sync_logs`
  - `SELECT COUNT(*) FROM sync_errors`

## Recommended next step

- Export-Worker mit kleiner Batchgroesse und kontrollierter Worker-Anzahl erneut starten und das Verhalten danach beobachten.
