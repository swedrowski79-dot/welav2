# Task

Alle Daten aus den lokalen Datenbanken `stage_sync` und `afs_extras` entfernen, ohne Datenbanken oder Tabellenstruktur zu löschen.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `src/Web/Repository/PipelineAdminRepository.php`
- `docker-compose.yml`

# Changed files

- `backups/stage-and-extras-before-empty-reset-20260720-102548.sql.gz`
- `docs/agent-results/2026-07-20-full-local-database-empty-reset.md`

# Summary

- Vor dem Reset wurde ein komprimierter SQL-Backup-Schnappschuss beider lokalen Datenbanken erstellt.
- Alle 48 Basistabellen in `stage_sync` und `afs_extras` wurden geleert.
- Dies umfasst RAW-, Stage-, Mirror-, Snapshot-, Queue-, Export-State-, Monitoring- und Attributübersetzungsdaten.
- Shop-Datenbanken wurden nicht angesprochen.
- Datenbanken, Tabellen, Spalten, Indizes und sonstige Struktur wurden beibehalten.

# Open points

- `stage_sync.schema_migrations` wurde auf Wunsch ebenfalls geleert. Beim nächsten Start kann der Setup-/Migrationsschritt dadurch bereits bekannte Migrationen erneut prüfen bzw. ausführen.
- `afs_extras.attribute_translations` ist ebenfalls leer; vor einem neuen vollständigen Attribut-Export muss das Dictionary wieder aufgebaut oder importiert werden.

# Validation steps

- Vorher geprüft: keine Pipeline- oder Export-Worker aktiv.
- Vorher geprüft: `stage_sync` mit 39 und `afs_extras` mit 9 Basistabellen.
- Nachher über eine exakte `COUNT(*)`-Summenabfrage aller 48 Tabellen geprüft: `0` Zeilen.

# Recommended next step

Für einen sauberen Neuaufbau zuerst die Migrationen/Schema-Gesundheit prüfen, dann Import, Merge, XT-Mirror, Expand und Export in der vorgesehenen Reihenfolge ausführen.
