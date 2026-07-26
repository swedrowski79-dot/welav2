## Task

Fehlende Artikel- und Kategorieuebersetzungen persistent in eine separate SQLite-Datenbank schreiben, ohne bestehende funktionierende Ablaeufe umzubauen. Dabei die vorhandene Erkennung fuer fehlende Uebersetzungen weiterverwenden und fehlende Eintraege per UPSERT in Missing-Tabellen pflegen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `config/sources.php`
- `config/languages.php`
- `run_merge.php`
- `src/Database/ConnectionFactory.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Service/MergeService.php`
- `src/Importer/ExtraImporter.php`
- `src/Service/ImportWorkflow.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/AbstractXtWriter.php`

## Changed files

- `config/sources.php`
- `run_merge.php`
- `src/Service/MissingTranslationRepository.php`
- `src/Service/MissingTranslationSyncService.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Web/Repository/MigrationRepository.php`
- `docs/agent-results/2026-04-19-missing-translation-sqlite.md`

## Summary

- Die bestehende Frontend-Datenbasis aus `StageConsistencyRepository` wird jetzt direkt wiederverwendet, um Produkte und Kategorien ohne Uebersetzungen zu identifizieren.
- Neue Klasse `MissingTranslationRepository` kapselt die separate SQLite-Verbindung, Tabellenanlage und Prepared-Statement-Operationen fuer Missing-/Done-Status.
- Neue Klasse `MissingTranslationSyncService` laeuft nach `merge` und synchronisiert fehlende bzw. erledigte Uebersetzungen in die Missing-SQLite.
- Die Missing-SQLite wird ueber `config/sources.php` unter `sources.missing_translations` konfiguriert.
- Der bestehende Merge-Ablauf wurde nur minimal erweitert: nach erfolgreichem `MergeService->run()` wird der Missing-Sync ausgefuehrt und ins Monitoring geloggt.
- Quelle fuer die eigentlichen Uebersetzungen bleibt die bestehende Stage-/Extra-Datenbasis; die neue SQLite wird nur fuer den persistenten Missing-Status verwendet.
- Zusaetzlich wurde `MigrationRepository` fuer Migration `005_add_export_queue_retry_fields` idempotent gemacht, damit vorhandene Spalten `last_error` und `next_retry_at` keinen Duplicate-Column-Fehler mehr ausloesen.

## Open points

- Die aktuelle Persistierung arbeitet auf Basis der gemergten Stage-Tabellen. Das ist bewusst minimalinvasiv, bedeutet aber: der Missing-Status wird nach `merge` aktualisiert, nicht schon waehrend `import`.
- Fuer vorhandene Uebersetzungen wird `status='done'` nur auf bereits vorhandenen Missing-Eintraegen gesetzt; es werden keine zusaetzlichen `done`-Datensaetze fuer komplett unauffaellige Inhalte erzeugt.
- Die Extra-Datenquelle fuer Uebersetzungen wurde in dieser Aufgabe nicht umgebaut; die neue Missing-SQLite ist davon getrennt.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/MissingTranslationRepository.php`
- `docker compose exec -T php php -l /app/src/Service/MissingTranslationSyncService.php`
- `docker compose exec -T php php -l /app/src/Web/Repository/StageConsistencyRepository.php`
- `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
- `docker compose exec -T php php -l /app/run_merge.php`
- `docker compose exec -T php php -l /app/config/sources.php`
- gezielter Missing-Sync ohne kompletten Merge:
  - `docker compose exec -T php php -r '... MissingTranslationSyncService->sync() ...'`
- Tabellenpruefung in der Missing-SQLite:
  - `missing_article_translations`
  - `missing_category_translations`
- aktuelle Sync-Statistik aus dem Testlauf:
  - `articles_missing_upserts = 6088`
  - `articles_done_updates = 15312`
  - `categories_missing_upserts = 16`
  - `categories_done_updates = 268`
- aktuelle Zeilenzahlen in der Missing-SQLite:
  - `missing_article_translations = 6088`
  - `missing_category_translations = 16`

## Recommended next step

Den normalen Lauf `docker compose exec -T php php /app/run_merge.php` im Zielablauf einmal bewusst starten, damit die Missing-SQLite kuenftig automatisch bei jedem Merge-Lauf mit gepflegt wird. Danach kann bei Bedarf das Frontend spaeter gezielt auf diese persistente Missing-Datenbasis erweitert werden.
