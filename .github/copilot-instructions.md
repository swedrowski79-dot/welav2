# Copilot Instructions

## Read first
Vor Änderungen zuerst lesen:
- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/*.php`

## Change boundaries
- Do not rewrite the whole architecture
- Do not replace the current CLI pipeline
- Prefer incremental repository-consistent changes
- Preserve current admin UI behavior unless the task explicitly requires UI changes
- Do not introduce direct XT integration

## Build, run, and validation commands

- Start the local stack: `docker compose up -d --build` or `make up`
- Open the admin UI: `http://localhost:8080`
- Import the MySQL schema: `docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql` or `make schema-import`
- Run the pipeline steps individually:
  - Import raw source data: `docker compose exec php php run_import_all.php` or `make import-all`
  - Merge raw tables into stage tables: `docker compose exec php php run_merge.php` or `make merge`
  - Expand translated attribute slots into `stage_attribute_translations`: `docker compose exec php php run_expand.php` or `make expand`
- Quick container/runtime checks from the README:
  - `docker compose exec php php -v`
  - `docker compose exec php php -m | grep -E 'pdo_mysql|pdo_sqlite|pdo_sqlsrv|sqlsrv'`
- There is no checked-in automated test suite or linter configuration in this repository (no Composer, PHPUnit, Pest, PHPCS, PHPStan, or ESLint config). There is also no single-test command to document.

## High-level architecture

- This repository is a Dockerized PHP 8.2 sync app that pulls product/category data from two upstream sources into a MySQL stage database:
  - **AFS MSSQL** for base products and categories
  - **Extra SQLite** for translated content
- The sync pipeline is intentionally split into three CLI entrypoints and should be kept in this order:
  1. `run_import_all.php` loads both upstream sources into `raw_*` tables
  2. `run_merge.php` combines raw tables into `stage_*` tables using config-driven field mapping and fallback rules
  3. `run_expand.php` denormalizes the four attribute name/value slots from `stage_product_translations` into `stage_attribute_translations`
- Table structure lives in `database.sql`. The important layers are:
  - `raw_*` tables: direct normalized imports from AFS/SQLite
  - `stage_*` tables: merged records used by downstream consumers
  - `sync_runs`, `sync_logs`, `sync_errors`: monitoring tables used by both CLI jobs and the admin UI
- The admin application in `public/index.php` is a small custom MVC app under `src/Web/`. It shows dashboard metrics, run history, logs, errors, stage table browsing, and source/status checks. It launches the same three CLI jobs in the background and edits connection settings by writing `.env`.
- `wela-api/` is a separate deployable snippet for the XT shop, not part of the local web app. The sync admin only health-checks it via HMAC-signed HTTP requests using `XT_API_URL` and `XT_API_KEY`.

## Key repository-specific conventions

- **Configuration drives most behavior.** Before changing importer/service code, check `config/*.php`:
  - `config/sources.php` defines connection details and entity table names
  - `config/normalize.php` maps source columns to raw-stage columns and declares calculated resolvers
  - `config/merge.php` defines how raw tables combine into stage tables, including `first_not_empty` fallback behavior
  - `config/expand.php` defines which repeated fields are expanded into attribute rows
- **The ETL code is not Composer-based.** The three `run_*.php` scripts manually `require` the non-web classes they need. The web app has its own lightweight autoloader in `src/Web/bootstrap.php`, but it only autoloads the `App\Web\...` namespace. If you add non-web classes used by CLI entrypoints, wire them in explicitly.
- **Web and ETL code follow different PHP styles.** The `src/Web/*` layer consistently uses `declare(strict_types=1);` and `App\Web\...` namespaces. The importer/service layer is mostly global-scope classes without namespaces. Match the style already used in the area you touch.
- **Each pipeline step fully rebuilds its target tables.** Import, merge, and expand all `TRUNCATE` before re-inserting. Preserve that full-refresh behavior unless the task is explicitly about incremental sync.
- **Monitoring is part of the contract.** Long-running jobs should use `SyncMonitor` to create `sync_runs` entries, append logs/errors, and finish with metrics. The admin dashboard assumes those tables are kept up to date.
- **Environment handling is centralized in `config/sources.php`.** It reads `.env` first, then process environment variables, then defaults. For SQLite, it also auto-detects several fallback file paths before defaulting to `data/extra.sqlite`.
- **Language handling is hard-coded around `de`, `en`, `fr`, and `nl`.** `Normalizer` collapses language values to lowercase codes, and `config/languages.php` contains store/fallback/SEO prefix settings for those same languages.
- **The stage browser is intentionally whitelisted.** Only tables listed in `config/admin.php` are exposed through the admin UI; add new stage tables there if they should become browsable.

## Sync pipeline extension (important)

After expand, the pipeline will later include:

4. Delta calculation (stage vs mirror)
5. Export queue generation (no direct XT write!)

Important:
- DO NOT write directly to XT
- DO NOT implement HTTP/API calls
- Only prepare export data in stage tables

Future tables:
- delta_*
- sync_export_queue
- sync_export_payloads

## Expand behavior

- Source: stage_product_translations
- Fields:
  - attribute_name1..4
  - attribute_value1..4

- Target: stage_attribute_translations

Rules:
- Each non-empty pair becomes one row
- Ignore empty names or values
- Preserve language_code
- Preserve sku and afs_artikel_id
- Preserve source_directory
- Add sort_order (1–4)

## Language handling

Allowed language codes:
- de
- en
- fr
- nl

Rules:
- Always lowercase
- Never invent new language codes
- Fallback: use 'de'

## Performance rules

- Always use batch inserts where possible
- Avoid per-row SELECT queries inside loops
- Prefer bulk reads into memory maps
- Use indexed fields for lookups:
  - afs_artikel_id
  - sku
  - afs_wg_id

## Error handling

- Never crash entire run on single row error
- Log error to sync_errors
- Continue processing next rows
- Always finalize sync_runs with status:
  - success
  - failed (only if critical failure)

## XT integration rule

- DO NOT:
  - connect directly to XT database
  - call any XT API
  - implement HTTP clients

- ONLY:
  - prepare data in stage tables
  - prepare export queue

XT integration is handled by a separate system (Codex).
