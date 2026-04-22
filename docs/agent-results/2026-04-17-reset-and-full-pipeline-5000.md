## Task

Lokale Schnittstellen-Datenbank komplett zuruecksetzen und danach einen kompletten Pipeline-Durchlauf mit `XT_PRODUCT_BATCH_REQUEST_SIZE=5000` starten.

## Files read

- `AGENTS.md`
- `README.md`
- `Makefile`
- `database.sql`

## Changed files

- `docs/agent-results/2026-04-17-reset-and-full-pipeline-5000.md`

## Summary

- Die lokale MySQL-Datenbank `stage_sync` wurde komplett neu erstellt und das Schema aus `database.sql` frisch importiert.
- Damit sind auch Queue, Mirror, Snapshot-, State-, Log- und Error-Tabellen leer neu aufgebaut worden.
- Beim ersten Reset-Versuch blockierte eine offene `stage`-Verbindung aus dem PHP-Container einen `DROP DATABASE` per Metadata-Lock.
- Der Lock wurde sauber geloest, indem der PHP-Container gestoppt und MySQL neu gestartet wurde; danach lief der Reset korrekt durch.
- Anschliessend wurde die komplette Pipeline mit Batchgroesse `5000` gestartet und erfolgreich abgeschlossen.

## Open points

- Der Export-Worker hat den Shop in diesem Lauf nur teilweise abgearbeitet; es bleiben noch Queue-Eintraege offen.
- Offene Queue nach dem Lauf:
  - `pending document = 1854`
  - `pending media = 4331`
  - `pending product = 5791`

## Validation steps

- Reset:
  - `DROP DATABASE IF EXISTS stage_sync; CREATE DATABASE stage_sync ...`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql`
- Lauf:
  - `XT_PRODUCT_BATCH_REQUEST_SIZE=5000 docker compose exec -T php php run_full_pipeline.php`
- Endstatus:
  - `full_pipeline = success`
  - `import_all = success`
  - `merge = success`
  - `xt_mirror = success`
  - `expand = success`
  - `export_queue_worker = success`
  - `sync_errors = 0`
- Queue-Stand nach dem Lauf:
  - `done category = 350`
  - `done document = 999`
  - `done media = 1000`
  - `done product = 1000`

## Recommended next step

Den Export-Worker weiterlaufen lassen, bis die verbleibenden `pending`-Eintraege abgearbeitet sind.
