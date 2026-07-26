## Task

Die Prüfung des XT-Bootstrap-Fixes über die normale Schnittstelle auf Windows erleichtern, ohne separaten Direktaufruf zu erzwingen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `wela-api/index.php`
- `wela-api/bootstrap/xtcommerce.php`

## Changed files

- `wela-api/index.php`
- `wela-api/bootstrap/xtcommerce.php`
- `docs/agent-results/2026-07-16-normal-interface-bootstrap-marker.md`

## Summary

Es wurde ein eindeutiger Laufzeitmarker fuer die normale API-Nutzung eingebaut:

- `runtime_version = 2026-07-16-bootstrap-fix-1`
- Logeintrag `Global XT bootstrap file loaded from API entry scope.`
- PHP-Error-Log-Eintrag `XT bootstrap scope fix active: 2026-07-16-bootstrap-fix-1`

Damit kann nach einem normalen Upload ueber die bestehende Schnittstelle direkt im `wela-api.log` erkannt werden, ob wirklich der neue globale Bootstrap-Codepfad aktiv ist.

## Open points

- Wenn diese Marker nach einem Upload nicht im Log auftauchen, laeuft auf dem XT-Server weiterhin alter Code.

## Validation steps

- `docker compose exec -T php php -l wela-api/index.php`
- `docker compose exec -T php php -l wela-api/bootstrap/xtcommerce.php`

## Recommended next step

Die geaenderten Dateien auf den XT-Server kopieren, einen normalen Bild-Upload ausfuehren und im `wela-api.log` gezielt nach `2026-07-16-bootstrap-fix-1` suchen.
