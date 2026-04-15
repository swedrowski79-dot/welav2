# Task

Implement ticket `T-025` by adding raw import and normalization for AFS document records only, then refine it so raw import keeps only product-relevant document rows.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-025-afs-document-raw-import-and-normalization.md`
- `docs/agent-results/2026-04-15-document-image-gap-analysis.md`
- `docs/agent-results/2026-04-15-T-024-image-path-normalization.md`
- `config/sources.php`
- `config/normalize.php`
- `src/Importer/AfsImporter.php`
- `src/Service/Normalizer.php`
- `src/Service/ImportWorkflow.php`
- `src/Web/Repository/MigrationRepository.php`
- `migrations/001_add_stage_products_hash.sql`
- `migrations/002_create_export_queue.sql`
- `migrations/003_create_product_export_state.sql`
- `migrations/004_add_export_queue_claim_fields.sql`
- `migrations/005_add_export_queue_retry_fields.sql`

# Changed files

- `database.sql`
- `migrations/006_create_raw_afs_documents.sql`
- `config/normalize.php`
- `config/sources.php`
- `src/Importer/AfsImporter.php`
- `src/Service/ImportWorkflow.php`
- `docs/tickets/done/T-025-afs-document-raw-import-and-normalization.md`
- `docs/agent-results/2026-04-15-T-025-afs-document-raw-import-and-normalization.md`

# Summary

- Added a dedicated `raw_afs_documents` table to the schema and as migration `006`.
- Added `afs.documents` normalization mapping for the live AFS `Dokument` source rows.
- Aligned the default AFS documents source name to `Dokument`, while keeping `AFS_DOCUMENTS_TABLE` as an override.
- Wired the AFS importer and import workflow to load document rows during product/raw imports.
- Normalized `Dateiname` to `file_name` in the mapping layer so only the filename is persisted there.
- Persisted the live AFS `Dateiname` value into `path` for source traceability because the current source table has no separate `Pfad` column.
- Refined the documents source so raw import only keeps rows whose `Artikel` belongs to the same relevant/imported AFS article set as the product import.
- Validated the live import path end to end: migration `006` is registered, `run_import_products.php` succeeds, and `raw_afs_documents` now contains only product-linked relevant rows.
- Did not add any stage, merge, expand, delta, or export logic.

# Open points

- The raw document import is now available, but no stage/media model exists yet for downstream use.
- `Art` is persisted as `document_type`, but its business semantics are still unresolved and belong in a later ticket.
- Product image fields `Bild1..Bild10` are still outside this ticket scope.
- The live AFS `Dokument` table does not expose `Dokument`, `Artikelnummer`, `Pfad`, `Typ`, or `Sortierung`; the raw mapping therefore uses the actual live columns `Zaehler`, `Artikel`, `Titel`, `Dateiname`, and `Art`.
- Some live document rows have no `Dateiname`; those rows import with `file_name` and `path` as `NULL`, which matches the current raw-only scope.
- The raw table still keeps `sku` and `sort_order` columns reserved, but the live source does not currently provide values for them.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/config/sources.php`
  - `docker compose exec -T php php -l /app/config/normalize.php`
  - `docker compose exec -T php php -l /app/src/Importer/AfsImporter.php`
  - `docker compose exec -T php php -l /app/src/Service/ImportWorkflow.php`
  - `docker compose exec -T php php -r "require '/app/src/Database/ConnectionFactory.php'; $cfg = require '/app/config/sources.php'; $db = ConnectionFactory::create($cfg['sources']['afs']); $sql = \"SELECT TOP 20 TABLE_SCHEMA + '.' + TABLE_NAME AS name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE', 'VIEW') AND TABLE_NAME LIKE 'Dokument%' ORDER BY TABLE_NAME\"; foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) { echo $row['name'], PHP_EOL; }"`
  - `docker compose exec -T php php -r '$config = require "/app/config/normalize.php"; require "/app/src/Service/Normalizer.php"; $n = new Normalizer($config); $row = ["Zaehler" => 11, "Artikel" => 22, "Titel" => "PDF", "Dateiname" => "C:\\\\docs\\\\manual.pdf", "Art" => "pdf"]; var_export($n->normalize("afs.documents", $row));'`
  - `curl -s -o /tmp/t025_migrations.out -w "%{http_code}" -X POST http://localhost:8080/status/migrations`
  - `docker compose exec -T php php -r "require '/app/src/Database/ConnectionFactory.php'; $cfg = require '/app/config/sources.php'; $db = ConnectionFactory::create($cfg['sources']['afs']); $sql = \"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'Dokument' ORDER BY ORDINAL_POSITION\"; foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) { echo $row['COLUMN_NAME'], PHP_EOL; }"`
  - `docker compose exec -T php php -r "require '/app/src/Database/ConnectionFactory.php'; $cfg = require '/app/config/sources.php'; $db = ConnectionFactory::create($cfg['sources']['afs']); echo 'all=' . $db->query(\"SELECT COUNT(*) FROM dbo.Dokument\")->fetchColumn() . PHP_EOL; echo 'linked=' . $db->query(\"SELECT COUNT(*) FROM dbo.Dokument d WHERE d.Artikel IN (SELECT a.Artikel FROM dbo.Artikel a WHERE a.Internet = 1 AND a.Art < 255 AND a.Mandant = 1)\")->fetchColumn() . PHP_EOL; echo 'null_article=' . $db->query(\"SELECT COUNT(*) FROM dbo.Dokument WHERE Artikel IS NULL\")->fetchColumn() . PHP_EOL;"`
  - `docker compose exec -T php php /app/run_import_products.php`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT COUNT(*) FROM raw_afs_documents;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT COUNT(*) FROM raw_afs_documents d LEFT JOIN raw_afs_articles a ON a.afs_artikel_id = d.afs_artikel_id WHERE a.afs_artikel_id IS NULL;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT afs_document_id, afs_artikel_id, file_name, path, document_type FROM raw_afs_documents ORDER BY id DESC LIMIT 3;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT message FROM sync_logs WHERE message = 'AFS Dokumente importiert.' ORDER BY id DESC LIMIT 1;"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT version FROM schema_migrations WHERE version = '006_create_raw_afs_documents';"`
  - `docker compose exec -T mysql mysql -uroot -proot stage_sync -Nse "SELECT imported_records, status, message FROM sync_runs WHERE run_type = 'import_products' ORDER BY id DESC LIMIT 1;"`
- Observed:
  - AFS documents source resolves in the live environment as `dbo.Dokument`
  - the migration endpoint returned HTTP `302`
  - the live documents table exposes `Zaehler`, `Artikel`, `Titel`, `Dateiname`, and `Art`
  - live source counts are `all = 25584`, `linked = 2853`, `null_article = 22426`
  - `file_name => 'manual.pdf'`
  - `path => 'C:\\docs\\manual.pdf'`
  - `run_import_products.php` completed successfully after the relevance filter was added
  - `raw_afs_documents` contains `2853` rows after the live import
  - `raw_afs_documents` rows without a matching imported article: `0`
  - sample imported rows remain normalized and product-linked
  - monitoring contains the log entry `AFS Dokumente importiert.`
  - migration `006_create_raw_afs_documents` is recorded in `schema_migrations`
  - latest `import_products` run finished `success` with `imported_records = 24371`

# Recommended next step

Implement `T-026` to define the stage-level model for product-linked documents and images now that raw document rows are available in normalized form.
