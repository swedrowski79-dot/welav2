# Konfigurierbarer Cron-Zeitplan im Webinterface

## Task

Das Intervall des automatischen Docker-Crons soll im bestehenden
Webinterface einstellbar sein. Die Aenderung soll ohne Container-Neustart
wirken.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/cron.php`
- `config/sources.php`
- `cron.php`
- `docker-compose.yml`
- `docker/php/cron.d/welav2`
- `public/index.php`
- `scripts/setup-database.php`
- `src/Database/ConnectionFactory.php`
- `src/Service/PipelineConfig.php`
- `src/Web/bootstrap.php`
- `src/Web/Core/Controller.php`
- `src/Web/Core/Html.php`
- `src/Web/Core/Request.php`
- `src/Web/Core/Response.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Web/Repository/StageConnection.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/status/index.php`
- relevante vorhandene Migrationen unter `migrations/`
- `docs/agent-results/2026-07-29-five-minute-cron-pipeline.md`

## Changed files

- `config/cron.php`
- `cron.php`
- `database.sql`
- `migrations/023_create_cron_settings.sql`
- `docker/php/cron.d/welav2`
- `src/Service/CronScheduleService.php`
- `src/Web/bootstrap.php`
- `src/Web/Controller/CronController.php`
- `src/Web/View/cron/index.php`
- `src/Web/View/layouts/app.php`
- `public/index.php`
- `docs/agent-results/2026-07-29-five-minute-cron-pipeline.md`
- `docs/agent-results/2026-07-29-web-cron-schedule.md`

## Summary

- Unter `/cron` gibt es eine neue Seite `Cron-Zeitplan`.
- Einstellbar sind:
  - automatischer Lauf aktiv/inaktiv
  - Intervall von 1 bis 1440 Minuten
- Die Einstellungen und der Laufstatus liegen persistent in der
  Stage-Tabelle `cron_settings`.
- Der Docker-Cron ruft `cron.php` jede Minute auf.
- `cron.php` beansprucht einen Lauf atomar nur dann, wenn:
  - der Zeitplan aktiviert ist und
  - seit dem letzten Laufstart mindestens das konfigurierte Intervall
    vergangen ist.
- Die vorhandene Dateisperre verhindert weiterhin parallele Cronprozesse.
- Start, Ende, Status und letzte Meldung werden gespeichert und in der
  Weboberflaeche angezeigt.
- Beim erneuten Aktivieren wird der naechste Lauf fuer die folgende
  minuetliche Pruefung freigegeben.
- Aenderungen im Webinterface werden von jedem neuen Cronaufruf direkt aus
  der Datenbank gelesen; dafuer ist kein Container-Neustart erforderlich.
- Die Schrittfolge bleibt unveraendert:
  1. Full Pipeline
  2. Dokument-Scan
  3. Dokument-Upload
  4. Bild-Scan
  5. Bild-Upload

## Open points

- Der dauerhafte `cron`-Service wurde nicht gestartet, damit keine echte
  Pipeline gegen das konfigurierte Shopsystem ungeplant ausgeloest wird.
- Der bestehende Datenbank-Setup-Lauf meldet weiterhin zwei unabhaengige
  Berechtigungswarnungen fuer `afs_extras` beziehungsweise `RELOAD`. Der
  Setup-Prozess wurde trotzdem erfolgreich beendet und `cron_settings`
  angelegt.
- Das Cron-Lock umfasst automatische Cronaufrufe. Ein parallel manuell im
  Dashboard gestarteter Pipeline-Lauf verwendet dieses Lock weiterhin nicht.

## Validation steps

Erfolgreich ausgefuehrt:

- PHP-Syntaxpruefung aller neuen und angepassten PHP-Dateien
- `docker compose config --quiet`
- `git diff --check`
- `docker compose exec -T php php scripts/setup-database.php`
- Migration `023_create_cron_settings.sql` angewendet
- Diensttest fuer `disabled`, `not_due` und `due` mit Wiederherstellung der
  urspruenglichen Einstellungen
- `docker compose exec -T php php /app/cron.php --dry-run`
- Web-GET `/cron` mit HTTP 200 und erwarteten Seitenelementen
- Web-POST `/cron/save` fuer Deaktivieren und erneutes Aktivieren jeweils mit
  HTTP 302
- deaktivierter echter Cronaufruf wird mit Exit-Code 0 uebersprungen
- aktuelle Einstellung nach dem Test: aktiv, 5 Minuten, noch kein Laufstart
- `docker compose build cron`
- neues Cron-Image enthaelt `/usr/sbin/cron`
- neues Cron-Image enthaelt den Zeitplan `* * * * *`
- Dry-run im neu gebauten Cron-Image

Nicht ausgefuehrt:

- kein dauerhafter Start des Cron-Services
- keine echte Full Pipeline
- kein Dokument- oder Bild-Scan
- kein Dokument- oder Bild-Upload

## Recommended next step

Auf dem Zielserver nach dem Git-Pull die Container aktualisieren:

```bash
docker compose up -d --build
```

Danach unter `/cron` Aktivstatus und Intervall kontrollieren und die
Cron-Ausgabe beobachten:

```bash
docker compose logs -f cron
```
