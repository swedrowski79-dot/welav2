## Task

`afs_extras` erneut anlegen und aus der vorhandenen SQLite-Quelle wieder befuellen, weil die Datenbank in MySQL nicht mehr vorhanden war.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/sources.php`
- `run_sync_afs_extras.php`
- `src/Service/AfsExtrasBootstrapService.php`
- `docs/agent-results/2026-04-19-afs-extras-bootstrap.md`
- `docs/agent-results/2026-04-19-missing-translations-to-afs-extras.md`

## Changed files

- `docs/agent-results/2026-04-20-afs-extras-recreate.md`

## Summary

- Im laufenden MySQL-Container war `afs_extras` nicht vorhanden (`ERROR 1049 Unknown database 'afs_extras'`).
- Das Repository-Schema wurde erneut mit `database.sql` eingespielt, wodurch `afs_extras` und die vorgesehenen Tabellen wieder angelegt wurden.
- Danach wurde `run_sync_afs_extras.php` erneut ausgefuehrt und die Inhalte aus `/app/data/extra.sqlite` in `afs_extras` uebernommen.
- Ergebnis nach dem Rebootstrap:
  - `afs_extras.article_translations = 16168`
  - `afs_extras.category_translations = 280`
  - `afs_extras.missing_article_translations = 0`
  - `afs_extras.missing_category_translations = 0`

## Open points

- Die Missing-Translation-Tabellen wurden durch das Schema wieder angelegt, aber in diesem Durchgang nicht erneut per Missing-Sync befuellt.
- Ein nachgelagerter Stage-Import (`run_import_all.php`) wurde nicht automatisch gestartet.

## Validation steps

- `docker compose exec -T mysql mysql -uroot -proot -Nse "SHOW DATABASES LIKE 'afs_extras'; SHOW TABLES FROM afs_extras;"`
- `bash -lc 'docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql'`
- `docker compose exec -T php php /app/run_sync_afs_extras.php`
- `docker compose exec -T mysql mysql -uroot -proot -Nse "SELECT 'article_translations', COUNT(*) FROM afs_extras.article_translations UNION ALL SELECT 'category_translations', COUNT(*) FROM afs_extras.category_translations UNION ALL SELECT 'missing_article_translations', COUNT(*) FROM afs_extras.missing_article_translations UNION ALL SELECT 'missing_category_translations', COUNT(*) FROM afs_extras.missing_category_translations;"`

## Recommended next step

- Falls die Missing-Translation-Listen wieder benoetigt werden, den Missing-Sync erneut ausfuehren und danach bei Bedarf `run_import_all.php`, `run_merge.php` und `run_expand.php` bewusst starten.
