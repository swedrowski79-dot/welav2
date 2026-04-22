## Task

Master-/Slave-Status im Stage-Bestand klar nutzbar machen und die Verknuepfung eines Artikels zu seinem Masterartikel direkt im `stage_products` speichern.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `run_merge.php`
- `config/normalize.php`
- `config/merge.php`
- `src/Service/MergeService.php`
- `src/Web/Repository/MigrationRepository.php`
- `src/Service/XtProductWriter.php`
- `src/Web/Repository/StageBrowserRepository.php`

## Changed files

- `database.sql`
- `migrations/016_add_stage_product_master_link.sql`
- `run_merge.php`
- `src/Service/StageProductVariantLinkService.php`
- `src/Web/Repository/MigrationRepository.php`
- `docs/agent-results/2026-04-20-master-slave-linking.md`

## Summary

- `stage_products` hat jetzt zusaetzlich die Spalte `master_afs_artikel_id`.
- Neuer Service `StageProductVariantLinkService` loest nach dem Merge `master_sku` gegen `stage_products.sku` auf.
- Fuer Masterartikel wird `master_afs_artikel_id` auf die eigene `afs_artikel_id` gesetzt.
- Fuer Slaveartikel wird `master_afs_artikel_id` auf die AFS-ID des passenden Masterartikels gesetzt.
- `run_merge.php` fuehrt diese Verknuepfung jetzt automatisch nach dem eigentlichen Merge aus und schreibt die Kennzahlen ins Monitoring.
- Migration `016_add_stage_product_master_link.sql` fuegt die neue Spalte auch in bestehende Umgebungen ein.

Aktueller Validierungsstand nach echtem Merge:
- `masters = 257`
- `slaves = 5005`
- `resolved_master_links = 4928`
- `unresolved_master_links = 77`

Beispiel fuer erfolgreich aufgeloeste Verknuepfung:
- `GANI-080` ist Slave und zeigt ueber `master_sku = GANI-000` auf `master_afs_artikel_id = 51369`.

## Open points

- 77 Slaveartikel haben aktuell zwar ein `master_sku`, aber keinen passenden Datensatz mit genau diesem `sku` im `stage_products`.
- Beispiele fuer ungelöste Referenzen:
  - `A-S-120-Edelstahl -> AS-0000`
  - `A-M-220 -> AM-220`
  - mehrere `PE 457 ... -> PE-000`
- Diese Restfaelle deuten auf fehlende oder abweichende Master-SKUs in den Quelldaten hin, nicht auf einen Fehler im neuen Linking-Schritt.

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/StageProductVariantLinkService.php`
- `docker compose exec -T php php -l /app/run_merge.php`
- `docker compose exec -T php php -l /app/src/Web/Repository/MigrationRepository.php`
- `docker compose exec -T php php -r 'require "/app/src/Database/ConnectionFactory.php"; require "/app/src/Web/Repository/MigrationRepository.php"; $config=require "/app/config/sources.php"; $db=ConnectionFactory::create($config["sources"]["stage"]); $repo=new App\\Web\\Repository\\MigrationRepository($db, "/app/migrations"); $executed=$repo->runPending(); echo json_encode($executed, JSON_UNESCAPED_SLASHES), PHP_EOL;'`
- `docker compose exec -T php php /app/run_merge.php`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT COUNT(*) AS masters FROM stage_products WHERE is_master = 1; SELECT COUNT(*) AS slaves FROM stage_products WHERE is_slave = 1; SELECT COUNT(*) AS resolved_slave_links FROM stage_products WHERE is_slave = 1 AND master_afs_artikel_id IS NOT NULL; SELECT COUNT(*) AS unresolved_slave_links FROM stage_products WHERE is_slave = 1 AND master_sku IS NOT NULL AND master_sku <> '' AND master_afs_artikel_id IS NULL;"`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT afs_artikel_id, sku, is_master, is_slave, master_sku, master_afs_artikel_id FROM stage_products WHERE is_master = 1 OR is_slave = 1 ORDER BY is_slave DESC, afs_artikel_id ASC LIMIT 10;"`

## Recommended next step

- Die 77 offenen `master_sku`-Faelle gezielt gegen AFS oder die Vorstufen pruefen, damit auch diese Slaveartikel auf einen existierenden Master aufgeloest werden koennen.
