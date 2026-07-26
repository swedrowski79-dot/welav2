# Task

Die lokalen Docker-Compose-Dienste nach der RAM-Disk-Konfigurationsanpassung neu starten und den aktiven MySQL-Speicherort prüfen.

# Files read

- `AGENTS.md`
- `docker-compose.yml`
- `docker/mysql/entrypoint.sh`
- `.env`

# Changed files

- `docs/agent-results/2026-07-20-ramdisk-restart-verification.md`

# Summary

- `mysql` und `php` wurden mit `docker compose up -d --force-recreate mysql php` neu erstellt und gestartet.
- MySQL ist gesund und verwendet jetzt `/mnt/mysql-ram/` als aktives Datadir.
- Der Entrypoint bestätigt im Log den RAM-Disk-Modus und das Kopieren des persistenten Stands nach `tmpfs`.
- Die RAM-Disk ist 8 GB groß und belegt nach dem Start etwa 308 MB.
- Die persistente Kopie unter `/var/lib/mysql-persistent` belegt etwa 295 MB.
- Die Weboberfläche antwortet mit HTTP `200`.

# Open points

- Änderungen während dieses RAM-Disk-Betriebs werden ohne spätere Rücksicherung beim Neustart nicht in die persistente Kopie übernommen.

# Validation steps

- `docker compose ps`
- `SELECT @@datadir` mit Ergebnis `/mnt/mysql-ram/`
- `df -Pk /mnt/mysql-ram`
- `docker compose logs --tail=30 mysql`
- HTTP-Prüfung von `http://localhost:8080/` mit Ergebnis `200`

# Recommended next step

Vor künftigen MySQL-/Compose-Neustarts eine Datenbanksicherung erstellen oder zuerst eine kontrollierte Rücksicherung der RAM-Disk implementieren.
