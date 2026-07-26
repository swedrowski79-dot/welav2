# Delta: Export-State vor Mirror-Abweichung

## Task

Verhindern, dass unveränderte Produkte und Dokumente bei jedem Full-Pipeline-Lauf erneut in die Export-Queue eingeordnet werden.

## Files read

- `src/Service/ProductDeltaService.php`
- `config/delta.php`
- `sync_runs` und `export_queue`

## Changed files

- `src/Service/ProductDeltaService.php`
- `docs/agent-results/2026-07-23-delta-state-priority-fix.md`

## Summary

`nextAction()` gab bei aktivem Mirror bisher für jeden vorhandenen Datensatz am Ende immer `update` zurück. Dadurch wurden auch Datensätze mit identischem `last_exported_hash` erneut exportiert, sobald der Mirror eine Abweichung meldete.

Der Export-State-Hash hat nun Vorrang:

- kein State: `insert` oder `update`, abhängig davon, ob der Mirror den Datensatz kennt;
- anderer Hash: `update`;
- gleicher Hash: keine Queue-Aktion.

## Validation steps

- PHP-Syntaxprüfung erfolgreich.
- Manueller Delta-Lauf über 13.998 Datensätze: `changed = 0`, `queue_created = 0` für Produkte, Dokumente, Medien und Kategorien.

## Recommended next step

Die bereits aus früheren Läufen vorhandenen wartenden Dokument-Einträge kontrolliert abarbeiten oder bereinigen; künftige unveränderte Full-Pipeline-Läufe erzeugen keine neuen Einträge.
