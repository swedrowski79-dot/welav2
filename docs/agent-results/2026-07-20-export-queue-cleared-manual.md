# Task

Die lokale Export-Queue auf ausdrückliche Anweisung leeren.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`

# Changed files

- Daten in `stage_sync.export_queue` gelöscht
- `docs/agent-results/2026-07-20-export-queue-cleared-manual.md`

# Summary

- Alle Einträge aus der lokalen Export-Queue wurden gelöscht.
- Stage-, Mirror-, Dictionary- und Shop-Daten wurden nicht verändert.

# Open points

- Ein nachfolgender Delta-Lauf kann abhängig vom Mirror-Vergleich wieder neue Queue-Einträge erzeugen.

# Validation steps

- `SELECT COUNT(*) FROM stage_sync.export_queue` ergab `0`.

# Recommended next step

Vor einem erneuten Delta-Lauf zuerst die Attributprojektion und XT-Mirror-Zuordnung korrigieren bzw. prüfen.
