## Task

Die bisher direkt genutzte SQLite-Quelle durch eine zweite MySQL-Datenbank `afs_extras` ersetzen. In `afs_extras` sollen zwei Tabellen fuer die Extra-Daten liegen, aus der Alt-SQLite befuellt werden und die Import-Pipeline soll danach nur noch gegen `afs_extras` lesen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `config/sources.php`
- `config/normalize.php`
- `docker-compose.yml`
- `run_import.php`
- `run_import_all.php`
- `run_import_products.php`
- `run_import_categories.php`
- `src/Database/ConnectionFactory.php`
- `src/Importer/ExtraImporter.php`
- `src/Service/ImportWorkflow.php`
- `src/Service/StageWriter.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/SourceStatusRepository.php`
- `src/Web/View/status/index.php`

## Changed files

- `config/sources.php`
- `database.sql`
- `docker-compose.yml`
- `README.md`
- `run_import.php`
- `run_import_all.php`
- `run_import_products.php`
- `run_import_categories.php`
- `run_sync_afs_extras.php`
- `src/Importer/ExtraImporter.php`
- `src/Service/AfsExtrasBootstrapService.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/SourceStatusRepository.php`
- `src/Web/View/status/index.php`
- `docs/agent-results/2026-04-19-afs-extras-bootstrap.md`

## Summary

- `sources.extra` liest jetzt aus der zweiten MySQL-Datenbank `afs_extras` statt direkt aus SQLite.
- Die alte SQLite ist nur noch als separates Bootstrap-Source-Config `extra_sqlite_bootstrap` hinterlegt.
- Neues Skript `run_sync_afs_extras.php` befuellt `afs_extras.article_translations` und `afs_extras.category_translations` aus der Alt-SQLite.
- `database.sql` legt `afs_extras` plus beide Tabellen an; lokal wurde die Datenbank zusaetzlich direkt im laufenden MySQL-Container angelegt und fuer den App-User freigeschaltet.
- Die Statusseite zeigt jetzt `AFS Extras` als eigene Datenbankquelle und den SQLite-Pfad nur noch als Bootstrap-Quelle.
- Lokal wurde der Bootstrap erfolgreich ausgefuehrt. Importierte Zeilen:
  - `article_translations`: `16168`
  - `category_translations`: `280`

## Open points

- `database.sql` vergibt Rechte standardmaessig an den lokalen Standard-User `stage`; bei abweichenden Zugangsdaten muessen die Grants im Zielsystem entsprechend angepasst werden.
- Der eigentliche Stage-Importlauf (`run_import_all.php`) wurde in diesem Durchgang nicht komplett gestartet, um bestehende Raw-/Stage-Daten nicht ungefragt zu ueberschreiben.

## Validation steps

- `docker compose exec -T php php -l /app/config/sources.php`
- `docker compose exec -T php php -l /app/src/Service/AfsExtrasBootstrapService.php`
- `docker compose exec -T php php -l /app/src/Importer/ExtraImporter.php`
- `docker compose exec -T php php -l /app/run_sync_afs_extras.php`
- `docker compose exec -T php php -l /app/src/Web/View/status/index.php`
- `docker compose exec -T php php -l /app/src/Web/Repository/SourceStatusRepository.php`
- `docker compose exec -T php php -l /app/src/Web/Controller/StatusController.php`
- `docker compose exec -T php php -l /app/run_import_all.php`
- `docker compose exec -T php php -l /app/run_import.php`
- `docker compose exec -T php php -l /app/run_import_products.php`
- `docker compose exec -T php php -l /app/run_import_categories.php`
- `docker compose exec -T mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS afs_extras CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON afs_extras.* TO 'stage'@'%'; FLUSH PRIVILEGES;"`
- `docker compose exec -T php php /app/run_sync_afs_extras.php`
- `docker compose exec -T mysql mysql -uroot -proot -Nse "SELECT 'article_translations', COUNT(*) FROM afs_extras.article_translations UNION ALL SELECT 'category_translations', COUNT(*) FROM afs_extras.category_translations;"`

## Recommended next step

Den naechsten echten Importlauf bewusst aus `afs_extras` starten, z. B. mit `docker compose exec -T php php /app/run_import_all.php`, und danach Merge/Expand plus eine kurze Sichtpruefung im Stage-Browser ausfuehren.
