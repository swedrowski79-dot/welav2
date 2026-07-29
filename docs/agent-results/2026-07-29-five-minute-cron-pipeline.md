# Automatischer Docker-Cron fuer Pipeline und Datei-Uploads

> Hinweis: Der feste Fuenf-Minuten-Zeitplan wurde im anschliessenden Task durch
> ein im Webinterface konfigurierbares Intervall ersetzt. Der System-Cron
> prueft seitdem jede Minute die Einstellung in `cron_settings`. Aktueller
> Stand: `docs/agent-results/2026-07-29-web-cron-schedule.md`.

## Task

Ein CLI-Script `cron.php` anlegen, das bei jedem Aufruf nacheinander die Full
Pipeline, Dokument-Scan, Dokument-Upload, Bild-Scan und Bild-Upload ausfuehrt.
Der Fuenf-Minuten-Zeitplan soll beim Docker-Start automatisch aktiviert werden,
ohne manuellen Host-Crontab-Eintrag.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `Dockerfile`
- `docker-compose.yml`
- `config/pipeline.php`
- `src/Service/PipelineConfig.php`
- `run_full_pipeline.php`
- `run_document_scan.php`
- `run_document_upload.php`
- `run_image_scan.php`
- `run_image_upload.php`
- `docs/manual/runs.md`
- `docs/manual/bedienungsanleitung.md`

## Changed files

- `cron.php`
- `config/cron.php`
- `docker-compose.yml`
- `Dockerfile` wurde nach `docker/php/Dockerfile` verschoben
- `docker/php/cron.d/welav2`
- `docs/agent-results/2026-07-29-five-minute-cron-pipeline.md`

## Summary

- `cron.php` ist ein reiner CLI-Orchestrator.
- Die Reihenfolge ist konfigurierbar in `config/cron.php`:
  1. `run_full_pipeline.php`
  2. `run_document_scan.php`
  3. `run_document_upload.php`
  4. `run_image_scan.php`
  5. `run_image_upload.php`
- Ein nicht blockierendes `flock` auf
  `/tmp/welav2-cron.lock` verhindert ueberlappende Cronlaeufe.
- Ist der vorherige Cronlauf nach fuenf Minuten noch aktiv, wird der neue
  Aufruf mit Exit-Code `0` uebersprungen und protokolliert.
- Bei einem fehlgeschlagenen Schritt bricht die Folge mit Exit-Code `1` ab.
  Der naechste Crontermin versucht die komplette Folge erneut.
- `--dry-run` prueft Konfiguration, Scripts und Locking, ohne Pipeline oder
  Uploads auszufuehren.
- Webaufrufe werden mit HTTP 403 abgelehnt.
- Das PHP-Image enthaelt jetzt den Debian-Cron-Daemon.
- Ein eigener Compose-Service `cron` startet `cron -f` und verwendet:
  - dasselbe PHP-Image
  - dasselbe Projekt-Volume `/app`
  - dieselben Verbindungsvariablen wie der PHP-Webcontainer
  - dieselbe MySQL-Healthcheck-Abhaengigkeit
- `/etc/cron.d/welav2` startet `/app/cron.php` alle fuenf Minuten.
- Die Ausgabe erscheint direkt in den Docker-Logs des Cron-Containers.
- Der PHP-Buildkontext liegt jetzt unter `docker/php/`. Dadurch wird der Build
  nicht mehr von dem separaten, aktuell nicht lesbaren CIFS-Mount
  `wela-api-xt/` im Repository-Root blockiert.
- Ein manueller Host-Crontab-Eintrag ist nicht mehr erforderlich.

## Open points

- Das Lock verhindert parallele Cronaufrufe. Ein gleichzeitig manuell ueber
  das Dashboard gestarteter Full-Pipeline-Lauf verwendet dieses Lock noch
  nicht.
- Ein Fuenf-Minuten-Intervall kann erhebliche Last auf AFS, Stage-MySQL und
  XT-API erzeugen. Durch das Lock entsteht keine Ueberlappung, lange Laeufe
  fuehren aber zu uebersprungenen Terminen.
- Der dauerhafte `cron`-Service wurde waehrend der Validierung bewusst nicht
  gestartet, damit keine echte Full Pipeline ungeplant ausgeloest wird.

## Validation steps

Erfolgreich ausgefuehrt:

- `php -l config/cron.php`
- `php -l cron.php`
- `php cron.php --dry-run`
- Lock-Kollision mit bereits gehaltener Lock-Datei
- `docker compose exec -T php php /app/cron.php --dry-run`
- `docker compose config --quiet`
- `docker compose build cron`
- Cron-Image enthaelt `/usr/sbin/cron`
- `/etc/cron.d/welav2` besitzt Modus `0644`
- Schedule im Image ist `*/5 * * * *`
- `docker compose run --rm --no-deps --entrypoint sh cron ...`
- `php /app/cron.php --dry-run` im neuen Cron-Image
- `git diff --check -- config/cron.php cron.php`

Nicht ausgefuehrt:

- kein dauerhafter Start des Cron-Services
- keine echte Full Pipeline durch den Cron-Service
- kein Dokument- oder Bild-Scan
- kein Dokument- oder Bild-Upload

## Recommended next step

Auf dem vorgesehenen Zielserver nach Git-Pull einmal ausfuehren:

```bash
docker compose up -d --build
```

Danach den ersten echten Lauf kontrollieren:

```bash
docker compose ps cron
docker compose logs -f cron
```

Vor der Aktivierung die dortige `XT_API_URL`, Verzeichnis-Pfade und
Upload-Zielpfade kontrollieren.
