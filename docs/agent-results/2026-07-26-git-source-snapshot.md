# Git-Quellstand-Sicherung

## Task

Den aktuellen Projektstand in Git sichern.

## Files read

- `AGENTS.md`
- `.gitignore`
- aktueller Git-Status und Remote-Konfiguration

## Changed files

- `docs/agent-results/2026-07-26-git-source-snapshot.md`

## Summary

- Der vollständige Code-, Konfigurations- und Dokumentationsstand wurde für einen Git-Commit vorbereitet.
- Lokale Daten und Backups bleiben außerhalb des Commits, da sie große Binärdateien und potenziell sensible Inhalte enthalten.

## Open points

- `backups/` und `data/` bleiben lokale Sicherungen und werden nicht zu GitHub übertragen.
- Laufzeit-Konfigurationen `wela-api-xt/config.php` und `wela-api slow/config.php` werden nicht versioniert.

## Validation steps

- Git-Status und Staging-Umfang prüfen.
- Commit erstellen und an `origin/master` pushen.

## Recommended next step

Für Datenbank-Backups und Medien einen separaten Backup-Speicher statt Git verwenden.
