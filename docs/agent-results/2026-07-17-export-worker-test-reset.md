## Task

Alle Export-Worker beenden und den Testzustand fuer Queue, Logs und Runs vollstaendig zuruecksetzen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`

## Changed files

- `docs/agent-results/2026-07-17-export-worker-test-reset.md`

## Summary

- Alle laufenden `run_export_queue.php`-Prozesse wurden beendet.
- `export_queue` wurde fuer den erneuten Test komplett auf `pending` zurueckgesetzt.
- `sync_logs`, `sync_errors` und `sync_runs` wurden geleert.
- Temporaere Worker-Logdateien unter `/tmp/export_queue_worker*.log` wurden geleert.
- Aktueller Stand nach dem Reset: `5412` Queue-Eintraege, alle auf `product/pending`, `0` Eintraege in `processing`, `0` in `error`.
- Der Reset wurde am 17.07.2026 erneut ausgefuehrt, damit der Worker mit kleinerer Batch-Groesse neu gestartet werden kann.
- Der Reset wurde am 17.07.2026 ein weiteres Mal ausgefuehrt, um nach der API-Optimierung einen frischen Zwischentest zu ermoeglichen.
- Der Reset wurde am 17.07.2026 erneut ausgefuehrt, um nach dem API-Klassenumbau alle Worker zu beenden und Queue plus Monitoring vollstaendig in den Test-Ausgangszustand zu setzen.

## Open points

- Die Queue enthaelt nach dem Reset nur noch `product`-Eintraege.
- Export-State-Tabellen wurden nicht veraendert.

## Validation steps

- Prozesspruefung mit `ps -ef | grep '[r]un_export_queue.php'`
- `SELECT entity_type, status, COUNT(*) FROM export_queue GROUP BY entity_type, status`
- `SELECT COUNT(*) FROM export_queue WHERE status='processing'`
- `SELECT COUNT(*) FROM export_queue WHERE status='error'`
- `SELECT COUNT(*) FROM sync_logs`
- `SELECT COUNT(*) FROM sync_errors`
- `SELECT COUNT(*) FROM sync_runs`
- `find /tmp -maxdepth 1 -type f -name 'export_queue_worker*.log' -delete`

## Recommended next step

- Export-Worker jetzt erneut ueber das Webinterface starten und beobachten, ob `processing` sauber nach `done` oder `retry/error` uebergeht.
