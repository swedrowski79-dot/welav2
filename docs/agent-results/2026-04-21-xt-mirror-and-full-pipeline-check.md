# Task

XT-Mirror-Haenger beheben und anschliessend die komplette Pipeline erneut bis zum Ende pruefen.

# Files read

- `run_xt_mirror.php`
- `config/xt_mirror.php`
- `src/Service/XtSnapshotService.php`
- `src/Service/ExportQueueWorker.php`
- `database.sql`

# Changed files

- `src/Service/XtSnapshotService.php`
- `docs/agent-results/2026-04-21-xt-mirror-and-full-pipeline-check.md`

# Summary

- Der Haenger an `xt_mirror` war kein echter Stillstand, sondern ein Abbruch beim Schreiben in die Mirror-Tabellen.
- Ursache war ein XT-Datensatz mit ungueltigem Null-Datum:
  - `xt_media.date_added = '0000-00-00 00:00:00'`
- `XtSnapshotService` normalisiert solche XT-Null-Datumswerte jetzt vor dem Insert auf `NULL`.
- Danach lief `run_xt_mirror.php` wieder erfolgreich durch.
- Anschliessend wurde die komplette Pipeline erneut gefahren:
  - `import_all`
  - `merge`
  - `xt_mirror`
  - `expand`
  - `export_queue_worker`
  - Abschluss `full_pipeline = success`
- Fuer die Endvalidierung nach dem Export wurde `run_xt_mirror.php` noch einmal manuell nachgezogen.
- Ein einzelner Dokument-Queue-Eintrag blieb kurz wegen `next_retry_at` offen und wurde nach dem Retry-Fenster erfolgreich verarbeitet.

# Open points

- Kein technischer Restfehler aus diesem Lauf offen.
- Die erweiterte Shop-`wela-api` fuer Dokumentbrowser/-upload ist weiterhin ein getrenntes Deployment-Thema und war fuer diesen Pipeline-Fix nicht noetig.

# Validation steps

- `docker compose exec -T php php -l src/Service/XtSnapshotService.php`
- `docker compose exec -T php php run_xt_mirror.php`
- `docker compose exec -T php php run_full_pipeline.php`
- `docker compose exec -T php php run_xt_mirror.php`
- Queue- und Lauf-Spotchecks in `stage_sync`:
  - `sync_runs`
  - `export_queue`

# Recommended next step

Den Shop jetzt fachlich stichprobenartig gegen den Mirror pruefen, z. B. bei Produkten, Dokumenten und SEO-Daten, die in diesem Lauf exportiert wurden.
