# Task

MySQL/Docker so anpassen, dass bei leerem Datadir auf tmpfs das Stage-Grundschema automatisch angelegt wird.

# Files read

- `docker-compose.yml`
- `docker/mysql/entrypoint.sh`
- `database.sql`
- `README.md`

# Changed files

- `docker-compose.yml`
- `README.md`
- `docs/agent-results/2026-04-22-auto-init-stage-schema-on-mysql-start.md`

# Summary

- `database.sql` wird jetzt in den MySQL-Container nach `/docker-entrypoint-initdb.d/20-stage-schema.sql` gemountet.
- Dadurch importiert der offizielle MySQL-Entrypoint das Stage-Grundschema automatisch, sobald das Datadir leer ist.
- Das ist genau der relevante Fall fuer frische Starts mit tmpfs/RAM-Disk.
- Ein manueller `schema-import` bleibt nur noch fuer bereits initialisierte, aber unvollstaendige Datadirs als Sonderfall noetig.

# Open points

- Damit die Aenderung auf einem bestehenden System greift, muss der MySQL-Container mit der neuen Compose-Konfiguration neu erzeugt werden.
- Wenn bereits ein initialisierter Datadir ohne Anwendungsschema existiert, startet MySQL zwar, aber der automatische Init-Import greift nicht rueckwirkend; dann bleibt der manuelle Schema-Import erforderlich.

# Validation steps

- `docker compose config`
- pruefen, dass `database.sql` nach `/docker-entrypoint-initdb.d/20-stage-schema.sql` gemountet wird

# Recommended next step

Auf dem neuen Server `docker compose down` und danach `docker compose up -d --build` ausfuehren, damit der MySQL-Container mit der neuen Init-Mount neu startet.
