## Task

Projekt-`.env` vollstaendig mit den aktuell verwendeten und relevanten Konfigurationswerten ergaenzen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `config/sources.php`
- `docker-compose.yml`
- `.env`

## Changed files

- `.env`
- `docs/agent-results/2026-04-20-env-complete.md`

## Summary

- Die bisherige `.env` enthielt nur `EXPORT_WORKER_BATCH_SIZE`.
- Die Datei wurde auf einen vollstaendigen Satz der im Projekt verwendeten Konfigurationsvariablen erweitert:
  - AFS / MSSQL
  - Stage / MySQL
  - AFS Extras / MySQL
  - SQLite-Pfade
  - XT-API
  - Export-Worker
  - MySQL-RAM-Disk
- Nach dem Update liest `config/sources.php` die AFS-Verbindung aus der `.env` korrekt ein.

Verifizierter Effekt:
- `host = 10.0.1.104`
- `port = 1435`
- `database = AFS_2018`
- `username = sa`
- Artikelquelle = `dbo.Artikel`

## Open points

- `XT_API_KEY` wurde nicht geraten und bleibt absichtlich leer, bis der korrekte Wert eingetragen wird.
- Wenn du auf eine andere AFS-Datenbank umstellen willst, muessen die `AFS_DB_*`-Werte in `.env` jetzt bewusst auf diese neue Quelle gesetzt werden.

## Validation steps

- `cat .env`
- `docker compose exec -T php php -r 'require "/app/config/sources.php"; $c=require "/app/config/sources.php"; var_export($c["sources"]["afs"]["connection"]); echo PHP_EOL; echo $c["sources"]["afs"]["entities"]["articles"]["table"], PHP_EOL;'`

## Recommended next step

- Falls du eine andere MSSQL-Quelle meinst, jetzt konkret `AFS_DB_HOST`, `AFS_DB_PORT`, `AFS_DB_NAME`, `AFS_DB_USER`, `AFS_DB_PASS` und gegebenenfalls `AFS_DB_SCHEMA` in `.env` auf die Zielquelle aendern und danach einen frischen Importlauf starten.
