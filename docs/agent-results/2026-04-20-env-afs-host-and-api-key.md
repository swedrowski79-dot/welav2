## Task

AFS-Host in der Projekt-`.env` auf `10.2.3.100` umstellen und den XT-API-Key setzen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `.env`
- `config/sources.php`

## Changed files

- `.env`
- `docs/agent-results/2026-04-20-env-afs-host-and-api-key.md`

## Summary

- `AFS_DB_HOST` wurde in `.env` von `10.0.1.104` auf `10.2.3.100` geaendert.
- `XT_API_KEY` wurde in `.env` gesetzt.
- Die effektive Konfiguration wurde direkt im laufenden PHP-Kontext geprueft.

Verifiziert aktiv:
- `AFS_DB_HOST = 10.2.3.100`
- `AFS_DB_PORT = 1435`
- `AFS_DB_NAME = AFS_2018`
- `XT_API_URL = http://10.0.1.104/wela-api`
- `XT_API_KEY` ist gesetzt

## Open points

- Es wurde in diesem Schritt noch kein neuer Import gegen den geaenderten AFS-Host gestartet.

## Validation steps

- `sed -n '1,40p' .env`
- `docker compose exec -T php php -r 'require "/app/config/sources.php"; $c=require "/app/config/sources.php"; var_export(["afs"=>$c["sources"]["afs"]["connection"], "xt"=>$c["sources"]["xt"]["connection"]]); echo PHP_EOL;'`

## Recommended next step

- Einen frischen Importlauf gegen den neuen AFS-Host starten und danach die Raw-/Stage-Daten gegen den bisherigen Stand vergleichen.
