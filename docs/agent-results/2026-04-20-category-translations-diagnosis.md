## Task

Fehlerbild `Base table or view not found: 1146 Table 'stage_sync.category_translations' doesn't exist` pruefen und verifizieren, welche Tabelle im aktuellen Setup korrekt ist.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `config/sources.php`
- `src/Database/ConnectionFactory.php`
- `src/Importer/ExtraImporter.php`
- `src/Service/MissingTranslationRepository.php`
- `src/Service/MissingTranslationSyncService.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `run_import_all.php`
- `run_merge.php`

## Changed files

- `docs/agent-results/2026-04-20-category-translations-diagnosis.md`

## Summary

- In `stage_sync` existiert `stage_category_translations`, aber nicht `category_translations`.
- In `afs_extras` existiert `category_translations`.
- Die effektive Extra-Source-Konfiguration im PHP-Container zeigt korrekt auf `afs_extras`.
- Ein direkter Query-Test auf `afs_extras.category_translations` funktioniert.
- `docker compose exec -T php php /app/run_import_all.php` laeuft aktuell erfolgreich durch.

## Open points

- Das urspruengliche Fehlerbild kam sehr wahrscheinlich aus einer falschen Tabellenreferenz gegen `stage_sync`, nicht aus dem aktuellen Import-Code.
- Falls der Fehler erneut auftritt, muss der ausloesende konkrete Befehl oder Stacktrace mitgeprueft werden.

## Validation steps

- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SHOW TABLES;"`
- `docker compose exec -T php php -r 'require "/app/src/Database/ConnectionFactory.php"; $c=require "/app/config/sources.php"; $pdo=ConnectionFactory::create($c["sources"]["extra"]); echo $pdo->query("SELECT DATABASE()")->fetchColumn(), PHP_EOL; echo $pdo->query("SELECT COUNT(*) FROM category_translations")->fetchColumn(), PHP_EOL;'`
- `docker compose exec -T php php /app/run_import_all.php`

## Recommended next step

- Wenn der Fehler wieder auftaucht, den genauen aufrufenden Befehl oder Stacktrace vergleichen. Korrekt sind nur `afs_extras.category_translations` oder `stage_sync.stage_category_translations`, nicht `stage_sync.category_translations`.
