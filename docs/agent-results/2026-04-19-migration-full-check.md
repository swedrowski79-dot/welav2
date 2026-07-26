## Task

Kompletten Migrationssatz pruefen, den aktuellen Fehler bei `005_add_export_queue_retry_fields.sql` beheben und den vollstaendigen Pending-Lauf erfolgreich ausfuehren.

## Files read

- `src/Web/Repository/MigrationRepository.php`
- `migrations/005_add_export_queue_retry_fields.sql`
- `migrations/*.sql`

## Changed files

- `src/Web/Repository/MigrationRepository.php`
- `docs/agent-results/2026-04-19-migration-full-check.md`

## Summary

- Ursache war ein partieller Zustand der Migration `005_add_export_queue_retry_fields`: Spalte `last_error` existierte bereits, `next_retry_at` noch nicht.
- Die bisherige Skip-Logik reichte dafuer nicht aus, weil sie nur den Fall "beide Spalten existieren bereits" abgedeckt hat.
- `MigrationRepository` wurde minimal erweitert, sodass `005` auch in partiellen Zwischenzustaenden sicher nachgezogen werden kann.
- Danach wurde der komplette Pending-Lauf erfolgreich ausgefuehrt.
- Aktueller Stand: `15/15` Migrationen angewendet, `0` pending.

## Open points

- Fuer den hier geprueften Migrationssatz gibt es aktuell keine offenen Pending-Migrationen mehr.
- Wenn kuenftig weitere alte Migrationen in aehnlich partielle Zwischenzustaende laufen, sollte dieselbe partielle Idempotenz auch dort gezielt ergänzt werden.

## Validation steps

- `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
- kompletter Lauf:
  - `docker compose exec -T php php -r '... MigrationRepository->runPending() ...'`
- Endstand:
  - `docker compose exec -T php php -r '... MigrationRepository->summary() ...'`
  - Ergebnis: `applied = 15`, `pending = 0`

## Recommended next step

Jetzt den eigentlichen operativen Ablauf wieder ueber das Frontend oder per Script testen, z. B. `Import -> Merge -> Expand/Delta -> Export Worker`, weil der Schema-Stand nun vollstaendig ist.
