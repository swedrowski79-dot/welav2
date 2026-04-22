## Task

MySQL standardmaessig immer im RAM-Disk-Modus starten, ausser der Modus wird explizit abgeschaltet.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `docker-compose.yml`
- `docker/mysql/entrypoint.sh`
- `Makefile`
- `README.md`

## Changed files

- `docker-compose.yml`
- `docker/mysql/entrypoint.sh`
- `Makefile`
- `README.md`
- `docs/agent-results/2026-04-17-ramdisk-default.md`

## Summary

- Der Default fuer `MYSQL_RAMDISK_ENABLED` wurde von `0` auf `1` umgestellt.
- Damit startet MySQL kuenftig standardmaessig mit `--datadir=/mnt/mysql-ram`.
- Fuer den expliziten Opt-out gibt es jetzt weiterhin `MYSQL_RAMDISK_ENABLED=0` und zusaetzlich das Make-Target `up-persistent`.
- Die README beschreibt den RAM-Disk-Modus jetzt als Standard und den persistenten Start als bewussten Opt-out.

## Open points

- Der bereits laufende MySQL-Container uebernimmt den neuen Default erst nach einem Recreate/Neustart.

## Validation steps

- `docker compose config`
- Laufzeitpruefung nach Recreate:
  - `docker inspect ...`
  - `SELECT @@datadir`

## Recommended next step

MySQL einmal mit dem neuen Default neu erstellen und anschliessend `@@datadir` auf `/mnt/mysql-ram/` pruefen.
