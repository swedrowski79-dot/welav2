# Task

Die aktuelle Docker-Compose-Konfiguration wieder mit dem vorhandenen MySQL-RAM-Disk-Entrypoint verbinden, ohne die aktuellen PHP-, Container-, Healthcheck- und Restart-Ergänzungen zu entfernen.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docker-compose.yml`
- `docker/mysql/entrypoint.sh`
- `Makefile`
- `.env`

# Changed files

- `docker-compose.yml`
- `docs/agent-results/2026-07-20-enable-mysql-ramdisk-compose.md`

# Summary

- Der MySQL-Service nutzt wieder `docker/mysql/entrypoint.sh`.
- Das bestehende Docker-Volume `mysql_data` bleibt erhalten und wird als persistente Kopie unter `/var/lib/mysql-persistent` eingebunden.
- Die aktive MySQL-Datenablage wird bei `MYSQL_RAMDISK_ENABLED=1` auf `/mnt/mysql-ram` gestartet; dieser Pfad ist als `tmpfs` konfiguriert.
- `MYSQL_RAMDISK_ENABLED` wird aus `.env` an MySQL übergeben; der vorhandene Wert `1` aktiviert den Modus nach dem Neustart.
- `database.sql` ist wieder als Init-Datei eingebunden, falls ein frisches Datadir initialisiert werden muss.
- Aktuelle Ergänzungen wurden beibehalten: Container-Namen, Restart-Regeln, PHP-Setup-Datenbankschritt und aktueller Healthcheck.
- Es wurde kein Container neu gestartet.

# Open points

- Änderungen in der RAM-Datenbank werden weiterhin nicht automatisch zurück in das persistente Volume kopiert. Ein Container-Neustart lädt wieder den letzten persistenten Datenstand.
- Die RAM-Disk ist mit 8 GB konfiguriert; das geprüfte persistente Volume belegt derzeit etwa 1,3 GB und passt somit hinein.

# Validation steps

- `bash -n docker/mysql/entrypoint.sh`
- `docker compose config`
- Aufgelöste Compose-Konfiguration geprüft:
  - `MYSQL_RAMDISK_ENABLED=1`
  - `tmpfs /mnt/mysql-ram:size=8589934592`
  - Volume nach `/var/lib/mysql-persistent`
  - RAM-Disk-Entrypoint
- Persistente Volume-Größe geprüft: etwa `1.304.904 KB`.

# Recommended next step

Vor dem bewussten Neustart die aktuellen Stage-Daten sichern, falls seit dem letzten persistenten Stand wichtige Änderungen vorliegen. Anschließend mit `docker compose up -d --force-recreate mysql php` starten und Datadir sowie Entrypoint-Log kontrollieren.
