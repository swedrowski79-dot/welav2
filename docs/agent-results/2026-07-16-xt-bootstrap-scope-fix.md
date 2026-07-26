## Task

xt:Commerce-Bootstrap in der `wela-api` ohne Änderungen am Shop-Core reparieren: `xtCore/main.php` darf nicht innerhalb von Funktionen geladen werden, sondern muss global aus der Entry-Datei gebootstrapped werden.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `wela-api/index.php`
- `wela-api/xt_image_helpers.php`
- `wela-api/config.php.example`
- `wela-api/README.md`

## Changed files

- `wela-api/bootstrap/xtcommerce.php`
- `wela-api/bin/test-xt-image.php`
- `wela-api/config.php.example`
- `wela-api/index.php`
- `wela-api/xt_image_helpers.php`
- `wela-api/README.md`
- `docs/agent-results/2026-07-16-xt-bootstrap-scope-fix.md`

## Summary

Die `wela-api` lädt `xtCore/main.php` jetzt nicht mehr aus einer Hilfsfunktion, sondern global in der Entry-Datei:

- neue Datei `wela-api/bootstrap/xtcommerce.php`
- globaler Bootstrap ohne Namespace
- `index.php` setzt `XT_COMMERCE_ROOT` aus `config.php` und bindet den Bootstrap im Dateiscope ein
- `xt_image_helpers.php` erwartet danach nur noch eine bereits initialisierte globale `\MediaImages`-Klasse

Zusätzlich wurde ein Testskript ergänzt:

- `wela-api/bin/test-xt-image.php`

Damit kann die Bildverarbeitung ausserhalb des API-Routers direkt getestet werden.

## Open points

- Auf dem XT-Server muss `xt_commerce_root` in `wela-api/config.php` korrekt gesetzt sein.
- Nach dem Deploy sollte geprüft werden, ob die Diagnosezeilen zu `_SYSTEM_INSTALL_SUCCESS`, `MediaImages` und `xtPlugin` in den PHP-Logs erscheinen.

## Validation steps

- `docker compose exec -T php php -l wela-api/bootstrap/xtcommerce.php`
- `docker compose exec -T php php -l wela-api/bin/test-xt-image.php`
- `docker compose exec -T php php -l wela-api/index.php`
- `docker compose exec -T php php -l wela-api/xt_image_helpers.php`
- `docker compose exec -T php php -l wela-api/config.php.example`

## Recommended next step

Die geaenderten `wela-api`-Dateien auf den XT-Server deployen, `xt_commerce_root` in `config.php` setzen und anschliessend zuerst `php wela-api/bin/test-xt-image.php DATEI category` beziehungsweise `product` direkt auf dem Server ausfuehren.
