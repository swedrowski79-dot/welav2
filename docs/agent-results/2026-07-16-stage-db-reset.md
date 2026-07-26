Task
- Komplette Datenbank zuruecksetzen.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- database.sql

Changed files
- docs/agent-results/2026-07-16-stage-db-reset.md

Summary
- `stage_sync` wurde geloescht und neu angelegt.
- `afs_extras` wurde geloescht und neu angelegt.
- `database.sql` wurde neu importiert.
- Verifiziert: `raw_afs_articles`, `stage_products` und `stage_categories` sind vorhanden und leer.
- Hinweis: `images_file` existiert direkt nach dem Reset noch nicht, weil die Tabelle erst bei Web-/Scan-Zugriff dynamisch erstellt wird.

Open points
- Keine weiteren offenen Punkte.

Validation steps
- `docker compose exec mysql mysql -uroot -proot -e "DROP DATABASE IF EXISTS stage_sync; CREATE DATABASE stage_sync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`
- `docker compose exec mysql mysql -uroot -proot -e "DROP DATABASE IF EXISTS afs_extras; CREATE DATABASE afs_extras CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`
- `docker compose exec -T mysql mysql -uroot -proot < database.sql`
- `docker compose exec mysql mysql -uroot -proot stage_sync -e "SHOW TABLES; ..."`

Recommended next step
- Falls gewuenscht, als Naechstes `docker compose exec php php run_import_all.php` starten, um die Stage wieder zu befuellen.
