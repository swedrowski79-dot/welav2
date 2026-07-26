## Task

Alle relevanten Schema- und Pipeline-Schritte sauber nachziehen und die betroffenen Tabellen erneut befuellen, inklusive Master-/Slave-Verknuepfung in `stage_products`.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `run_import_all.php`
- `run_merge.php`
- `run_expand.php`
- `src/Web/Repository/MigrationRepository.php`
- `docs/agent-results/2026-04-20-master-slave-linking.md`

## Changed files

- `docs/agent-results/2026-04-20-full-refresh-master-slave.md`

## Summary

- Migrationsstand geprueft: `16/16` Migrationen angewendet, `0` pending.
- `run_import_all.php`, `run_merge.php` und `run_expand.php` erfolgreich ausgefuehrt.
- Die relevanten Raw- und Stage-Tabellen wurden damit erneut aufgebaut bzw. befuellt.
- Die Master-/Slave-Verknuepfung ist aktiv und setzt `master_afs_artikel_id` nach dem Merge.

Aktuelle Tabellenstaende:
- `raw_afs_articles = 5350`
- `raw_extra_article_translations = 16168`
- `raw_extra_category_translations = 280`
- `stage_products = 5350`
- `stage_product_translations = 16168`
- `stage_categories = 71`
- `stage_category_translations = 280`
- `stage_attribute_translations = 24820`
- `stage_product_media = 5330`
- `stage_product_documents = 2853`

Master-/Slave-Stand:
- `masters = 257`
- `slaves = 5005`
- `resolved_slave_links = 4928`
- `unresolved_slave_links = 77`

Beispiel:
- `GANI-080` ist ein Slaveartikel mit `master_sku = GANI-000` und `master_afs_artikel_id = 51369`.

## Open points

- 77 Slaveartikel haben weiterhin ein `master_sku`, aber keinen passenden Master mit exakt derselben `sku` im aktuellen `stage_products`.
- Das ist ein Quelldaten-/Mapping-Thema, nicht mehr ein fehlender Stage-Linking-Schritt.

## Validation steps

- `docker compose exec -T php php -r 'require "/app/src/Database/ConnectionFactory.php"; require "/app/src/Web/Repository/MigrationRepository.php"; $config=require "/app/config/sources.php"; $db=ConnectionFactory::create($config["sources"]["stage"]); $repo=new App\\Web\\Repository\\MigrationRepository($db, "/app/migrations"); echo json_encode($repo->summary(), JSON_UNESCAPED_SLASHES), PHP_EOL;'`
- `docker compose exec -T php php /app/run_import_all.php`
- `docker compose exec -T php php /app/run_merge.php`
- `docker compose exec -T php php /app/run_expand.php`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT ... COUNT(*) ..."`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT afs_artikel_id, sku, is_master, is_slave, master_sku, master_afs_artikel_id FROM stage_products ..."`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT run_type, status, started_at, ended_at FROM sync_runs ORDER BY id DESC LIMIT 3;"`

## Recommended next step

- Die 77 offenen `master_sku`-Referenzen gesondert analysieren und die Quellwerte oder das vorgelagerte Mapping dafuer gezielt bereinigen.
