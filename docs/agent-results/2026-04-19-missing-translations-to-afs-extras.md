## Task

Die Missing-/Konsistenz-Eintraege von der separaten SQLite auf `afs_extras` umstellen und direkt in `afs_extras` befuellen.

## Files read

- `config/sources.php`
- `database.sql`
- `run_merge.php`
- `src/Service/MissingTranslationRepository.php`
- `src/Service/MissingTranslationSyncService.php`
- `src/Web/Repository/StageConsistencyRepository.php`

## Changed files

- `config/sources.php`
- `database.sql`
- `src/Service/MissingTranslationRepository.php`
- `src/Service/MissingTranslationSyncService.php`
- `docs/agent-results/2026-04-19-missing-translations-to-afs-extras.md`

## Summary

- Die Source `missing_translations` zeigt jetzt auf MySQL `afs_extras` statt auf die separate SQLite.
- `MissingTranslationRepository` unterstuetzt jetzt sowohl SQLite als auch MySQL und verwendet fuer MySQL passende `CREATE TABLE`- und UPSERT-Syntax.
- `database.sql` legt die beiden Missing-Tabellen nun auch direkt in `afs_extras` an:
  - `afs_extras.missing_article_translations`
  - `afs_extras.missing_category_translations`
- Der Missing-Sync wurde direkt ausgefuehrt und hat die Tabellen in `afs_extras` befuellt.

Aktueller Stand in `afs_extras`:
- `missing_article_translations = 6088`
- `missing_category_translations = 16`

## Open points

- Die alte SQLite-Datei bleibt technisch bestehen, wird fuer Missing-Translations jetzt aber nicht mehr von der Schnittstelle verwendet.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/MissingTranslationRepository.php`
- `docker compose exec -T php php -l /app/src/Service/MissingTranslationSyncService.php`
- `docker compose exec -T php php -l /app/config/sources.php`
- `docker compose exec -T php php -l /app/run_merge.php`
- Direkter Missing-Sync:
  - `docker compose exec -T php php -r '... MissingTranslationSyncService->sync() ...'`
- Datenbankpruefung:
  - `SHOW TABLES FROM afs_extras LIKE 'missing_%'`
  - `SELECT COUNT(*) FROM afs_extras.missing_article_translations`
  - `SELECT COUNT(*) FROM afs_extras.missing_category_translations`

## Recommended next step

Bei Gelegenheit die alte Missing-SQLite-Datei als Altbestand kennzeichnen oder entfernen, damit keine Verwechslung mit der jetzt aktiven MySQL-Loesung entsteht.
