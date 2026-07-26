Task
- Datenbank frisch zuruecksetzen, aktuelles Schema wiederherstellen und danach einen neuen Full-Run starten.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- database.sql
- src/Web/Repository/MigrationRepository.php
- run_full_pipeline.php

Changed files
- docs/agent-results/2026-07-17-fresh-reset-and-full-run.md

Summary
- `stage_sync` und `afs_extras` wurden frisch neu angelegt.
- `database.sql` wurde neu importiert.
- Danach wurden die Pending-Migrationen ueber `MigrationRepository` ausgefuehrt.
- Verifiziert:
  - `schema_migrations` ist befuellt
  - `export_queue.next_retry_at` ist vorhanden
- Anschliessend wurde ein neuer Full-Run gestartet.
- Endstand des Laufs:
  - `import_all`: `success`
  - `merge`: `success`
  - `xt_mirror`: `success`
  - `expand`: `success`
  - `export_queue_worker`: `success`
  - `full_pipeline`: `success`
- Laufzeit Full Pipeline:
  - Start: `2026-07-17 08:36:35 UTC`
  - Ende: `2026-07-17 09:07:37 UTC`
- Positiver Befund:
  - der fruehere Schemafehler im Export-Worker (`next_retry_at` fehlt) ist in diesem Fresh-Run nicht mehr aufgetreten
  - `sync_errors_total = 0`
- Queue-Endstand nach erfolgreichem Full-Run:
  - `done category = 280`
  - `done document = 2948`
  - `pending document = 8`
  - `pending media = 90`
  - `pending product = 6872`
- Das bedeutet:
  - der technische Full-Run selbst lief bis zum Ende durch
  - aber nicht alle Queue-Eintraege wurden im selben Worker-Lauf abgearbeitet

Open points
- Trotz `success` im Full-Run bleiben Queue-Eintraege offen:
  - `6872` Produkte
  - `90` Medien
  - `8` Dokumente
- Das sollte als naechster fachlicher/technischer Befund separat untersucht werden.

Validation steps
- `docker compose exec mysql mysql -uroot -proot -e "DROP DATABASE ...; CREATE DATABASE ...;"`
- `docker compose exec -T mysql mysql -uroot -proot < database.sql`
- `docker compose exec php php -r '... MigrationRepository->runPending() ...'`
- `docker compose exec php php run_full_pipeline.php`
- Monitoring-Auswertung ueber:
  - `sync_runs`
  - `sync_logs`
  - `sync_errors`
  - `export_queue`

Recommended next step
- Als naechstes pruefen, warum der Export Queue Worker mit `success` beendet wurde, obwohl noch `pending`-Eintraege fuer `product`, `media` und `document` uebrig sind.
