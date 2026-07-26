## Task

Klaeren, ob die Missing-/Konsistenz-Eintraege bereits persistiert werden, und den automatischen Shop-Cache-Refresh aus der Sync-Schnittstelle entfernen.

## Files read

- `config/sources.php`
- `run_merge.php`
- `run_export_queue.php`
- `src/Service/MissingTranslationRepository.php`
- `src/Service/MissingTranslationSyncService.php`
- `src/Web/Repository/StageConsistencyRepository.php`
- `src/Service/WelaApiClient.php`
- `wela-api/README.md`

## Changed files

- `run_export_queue.php`
- `wela-api/README.md`
- `docs/agent-results/2026-04-19-missing-translations-and-cache-refresh.md`

## Summary

- Die Missing-/Konsistenz-Eintraege fuer fehlende Uebersetzungen sind bereits eingebaut.
- Sie landen aktuell **nicht** in `afs_extras`, sondern in einer separaten SQLite-Quelle `missing_translations`.
- Konfigurationsquelle:
  - `config/sources.php`
  - `type = sqlite`
  - `path = /app/data/missing_translations.sqlite`
- Aktueller Stand der Tabellen:
  - `missing_article_translations = 6088`
  - `missing_category_translations = 16`
- Der automatische Shop-Cache-Refresh nach dem Export-Worker wurde aus der Sync-Schnittstelle entfernt.
- Die API-Aktion `refresh_shop_state` bleibt als separate XT-API-Funktion erhalten, wird aber nicht mehr automatisch vom Exportlauf aufgerufen.

## Open points

- Falls die Missing-/Konsistenz-Eintraege statt in der SQLite kuenftig direkt in `afs_extras` geschrieben werden sollen, muss die Repository-Anbindung gezielt von SQLite auf MySQL `afs_extras` umgestellt werden.

## Validation steps

- `docker compose exec -T php php -l /app/run_export_queue.php`
- `docker compose exec -T php php -r 'require "/app/config/sources.php"; $c=require "/app/config/sources.php"; echo ($c["sources"]["missing_translations"]["type"] ?? "")."\n"; echo ($c["sources"]["missing_translations"]["connection"]["path"] ?? "")."\n";'`
- `docker compose exec -T php php -r 'require "/app/src/Database/ConnectionFactory.php"; require "/app/config/sources.php"; $c=require "/app/config/sources.php"; $pdo=ConnectionFactory::create($c["sources"]["missing_translations"]); foreach (["missing_article_translations","missing_category_translations"] as $t){$n=$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn(); echo $t,":",$n,"\n";}'`

## Recommended next step

Entscheiden, ob die Missing-/Konsistenz-Eintraege bewusst in der separaten SQLite bleiben sollen oder ob sie in `afs_extras` migriert werden sollen.
