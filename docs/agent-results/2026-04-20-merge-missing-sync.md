## Task

`run_merge.php` ausfuehren, damit der Missing-Translation-Sync die Missing-Tabellen in `afs_extras` wieder befuellt.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `run_merge.php`
- `src/Service/MissingTranslationRepository.php`
- `src/Service/MissingTranslationSyncService.php`

## Changed files

- `docs/agent-results/2026-04-20-merge-missing-sync.md`

## Summary

- `docker compose exec -T php php /app/run_merge.php` wurde erfolgreich ausgefuehrt.
- Der Merge-Lauf hat auch den Missing-Translation-Sync ausgefuehrt und die Tabellen in `afs_extras` wieder befuellt.
- Aktueller Stand:
  - `missing_article_translations = 6088`
  - `missing_category_translations = 16`
  - `articles_missing = 6088`
  - `articles_done = 0`
  - `categories_missing = 16`
  - `categories_done = 0`
- Der letzte `sync_runs`-Eintrag steht auf `success` mit folgendem Kontext:
  - `articles_done_updates = 15312`
  - `categories_done_updates = 268`
  - `articles_missing_upserts = 6088`
  - `categories_missing_upserts = 16`

## Open points

- `articles_done` und `categories_done` in `afs_extras` bleiben nur dann > 0, wenn fuer dieselben Entitaeten im Stage-Stand passende Uebersetzungen fuer die konfigurierten Sprachen vorhanden sind.

## Validation steps

- `docker compose exec -T php php /app/run_merge.php`
- `docker compose exec -T mysql mysql -uroot -proot -Nse "SELECT 'missing_article_translations', COUNT(*) FROM afs_extras.missing_article_translations UNION ALL SELECT 'missing_category_translations', COUNT(*) FROM afs_extras.missing_category_translations; SELECT 'articles_missing', COUNT(*) FROM afs_extras.missing_article_translations WHERE status='missing' UNION ALL SELECT 'articles_done', COUNT(*) FROM afs_extras.missing_article_translations WHERE status='done' UNION ALL SELECT 'categories_missing', COUNT(*) FROM afs_extras.missing_category_translations WHERE status='missing' UNION ALL SELECT 'categories_done', COUNT(*) FROM afs_extras.missing_category_translations WHERE status='done';"`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "DESCRIBE sync_runs;"`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT * FROM sync_runs ORDER BY id DESC LIMIT 1;"`

## Recommended next step

- Falls gewuenscht, als Naechstes `run_expand.php` starten und danach im Admin-UI bzw. Stage-Browser die aktualisierten Merge-/Translation-Ergebnisse kontrollieren.
