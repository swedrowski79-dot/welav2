## Task

AFS-Passwort testweise auf `Welafix` setzen, die MSSQL-Verbindung pruefen und danach den Importlauf erneut starten.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `.env`
- `config/sources.php`
- `run_import_all.php`
- `src/Database/ConnectionFactory.php`

## Changed files

- `.env`
- `docs/agent-results/2026-04-20-afs-password-welafix-run.md`

## Summary

- `AFS_DB_PASS` wurde in `.env` auf `Welafix` gesetzt.
- Die AFS-MSSQL-Verbindung gegen `10.2.3.100:1434` funktioniert damit.
- Direkter Test auf `dbo.Artikel`:
  - `COUNT(*) = 13322`
- Danach wurde `run_import_all.php` erfolgreich ausgefuehrt.

Aktuelle RAW-Staende nach dem Lauf:
- `raw_afs_articles = 5471`
- `raw_afs_categories = 72`
- `raw_afs_documents = 2957`
- `raw_extra_article_translations = 21692`
- `raw_extra_category_translations = 280`

## Open points

- In diesem Schritt wurde nur der Importlauf bestaetigt, kein anschliessender Merge-/Expand-Lauf.

## Validation steps

- `docker compose exec -T php php -r 'require "/app/src/Database/ConnectionFactory.php"; $c=require "/app/config/sources.php"; $pdo=ConnectionFactory::create($c["sources"]["afs"]); echo $pdo->query("SELECT COUNT(*) FROM dbo.Artikel")->fetchColumn(), PHP_EOL;'`
- `docker compose exec -T php php /app/run_import_all.php`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT ... COUNT(*) ... FROM raw_*;"`

## Recommended next step

- Wenn der neue AFS-Bestand jetzt komplett uebernommen werden soll, direkt `run_merge.php` und danach `run_expand.php` starten.
