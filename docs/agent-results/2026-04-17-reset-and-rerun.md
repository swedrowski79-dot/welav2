## Task

Lokale Schnittstellen-Datenbank erneut komplett leeren und anschliessend einen neuen Voll-Lauf starten.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `database.sql`

## Changed files

- `docs/agent-results/2026-04-17-reset-and-rerun.md`

## Summary

- Die lokale Datenbank `stage_sync` wurde erneut komplett neu erstellt und das Schema frisch importiert.
- Danach wurde die komplette Pipeline neu gestartet.
- Der Durchlauf lief ohne Fehler bis zum Ende durch.
- MySQL lief dabei wieder auf der RAM-Disk.

## Open points

- Der Export-Worker hat in diesem einen Lauf nur einen Teil der Queue abgearbeitet.
- Offene Queue nach dem Lauf:
  - `pending document = 1853`
  - `pending media = 4331`
  - `pending product = 5791`

## Validation steps

- `DROP DATABASE IF EXISTS stage_sync; CREATE DATABASE stage_sync ...`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql`
- `docker compose exec -T php php run_full_pipeline.php`
- Endstatus:
  - `full_pipeline = success`
  - `import_all = success`
  - `merge = success`
  - `xt_mirror = success`
  - `expand = success`
  - `export_queue_worker = success`
  - `sync_errors = 0`

## Recommended next step

Den Export-Worker weiterlaufen lassen, bis die verbleibenden `pending`-Eintraege abgearbeitet sind.
