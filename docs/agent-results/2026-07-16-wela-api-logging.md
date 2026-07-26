## Task

Schaltbares Logging fuer `wela-api` ergaenzen, damit bei aktivem Logging der komplette API-Ablauf, insbesondere Bild-Upload und XT-Bildnachverarbeitung, nachvollziehbar protokolliert wird.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `wela-api/config.php.example`
- `wela-api/index.php`
- `wela-api/xt_image_helpers.php`
- `wela-api/README.md`

## Changed files

- `wela-api/config.php.example`
- `wela-api/index.php`
- `wela-api/xt_image_helpers.php`
- `wela-api/README.md`
- `docs/agent-results/2026-07-16-wela-api-logging.md`

## Summary

Die `wela-api` kann jetzt auf Wunsch ein Log im JSON-Lines-Format schreiben.

Aktivierung in `config.php`:

```php
'logging' => true,
'log_file' => __DIR__ . '/wela-api.log',
```

oder alternativ:

```php
'logging' => [
    'enabled' => true,
    'file' => __DIR__ . '/wela-api.log',
],
```

Das Logging erfasst jetzt unter anderem:

- Request-Eingang
- Action-Dispatch
- Upload-Start und Zielpfad
- physisches Dateischreiben
- XT-Bootstrap
- Bildklassenerkennung `product` / `category`
- geladene XT-Bildtypen
- `MediaImages::processImage(...)`
- Verifikation der erzeugten Bilddateien
- API-Response
- Exceptions

`content_base64` wird dabei aus Datenschutz-/Groessengruenden maskiert.

## Open points

- Das Logging muss auf dem XT-Server in der echten `config.php` aktiv gesetzt werden.
- Die Logdatei muss auf dem XT-Server in ein beschreibbares Verzeichnis zeigen.

## Validation steps

- `docker compose exec -T php php -l wela-api/index.php`
- `docker compose exec -T php php -l wela-api/xt_image_helpers.php`
- `docker compose exec -T php php -l wela-api/config.php.example`

## Recommended next step

Auf dem XT-Server in `wela-api/config.php` Logging aktivieren, einen Bild-Upload erneut starten und danach die Datei `wela-api.log` auf Eintraege zu `MediaImages::processImage(...)`, den geladenen Bildtypen und der Dateiverifikation pruefen.
