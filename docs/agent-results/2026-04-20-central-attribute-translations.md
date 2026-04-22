# Task

Attributverarbeitung auf das reale Schema von `afs_extras.attribute_translations` umstellen, damit neue Attribute dort zentral angelegt werden und die Pipeline ihre Attributzeilen kuenftig direkt aus dieser Tabelle zieht.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/sources.php`
- `config/normalize.php`
- `config/expand.php`
- `src/Importer/ExtraImporter.php`
- `src/Service/ImportWorkflow.php`
- `src/Service/AfsExtrasBootstrapService.php`
- `src/Service/AttributeTranslationDictionaryService.php`
- `src/Service/ExpandService.php`
- `src/Web/Repository/MigrationRepository.php`
- `migrations/018_create_raw_extra_attribute_translations.sql`
- `migrations/019_add_attribute_values_to_raw_extra_attribute_translations.sql`
- `run_import_all.php`

# Changed files

- `config/expand.php`
- `config/normalize.php`
- `config/sources.php`
- `src/Importer/ExtraImporter.php`
- `src/Service/AfsExtrasBootstrapService.php`
- `src/Service/AttributeTranslationDictionaryService.php`
- `src/Service/ExpandService.php`
- `src/Service/ImportWorkflow.php`
- `src/Web/Repository/MigrationRepository.php`
- `database.sql`
- `migrations/018_create_raw_extra_attribute_translations.sql`
- `migrations/019_add_attribute_values_to_raw_extra_attribute_translations.sql`
- `docs/agent-results/2026-04-20-central-attribute-translations.md`

# Summary

- Die zentrale Quelle ist jetzt das echte Extras-Schema `afs_extras.attribute_translations`.
- Neue Attribute aus `raw_afs_articles` werden dort automatisch je Artikel und `sort_order` angelegt:
  - `de` mit `attribute_name` + `attribute_value`
  - `en`, `fr`, `nl` als leere Platzhalter
- Der Import liest diese Tabelle nach `stage_sync.raw_extra_attribute_translations`.
- Der Expand-Lauf baut `stage_attribute_translations` jetzt direkt aus `raw_extra_attribute_translations` auf.
- Die fruehere Sonderlogik ueber die separate Tabelle `attribute_name_translations` ist fuer den eigentlichen Lauf nicht mehr die Quelle.
- Geprueftes Beispiel fuer `64080`, `64082`, `64083`:
  - `afs_extras.attribute_translations` enthaelt pro Attributslot `de/en/fr/nl`
  - `raw_extra_attribute_translations` enthaelt dieselben Zeilen mit Quelle `afs_auto`
  - `stage_attribute_translations` enthaelt die befuellten `de`-Zeilen aus dieser Quelle, z. B. `Durchmesser=160mm`, `Laenge=3m`

# Open points

- Leere Platzhalter fuer `en/fr/nl` werden bewusst nicht in `stage_attribute_translations` uebernommen, solange Name und Wert leer sind.
- Die bestehende Tabelle `schema_migrations` ist fuer lange Versionsnamen zu knapp; die partielle 019-Nachziehung greift trotzdem, aber das Migrations-Logging sollte separat bereinigt werden.
- Historische Altzeilen in `afs_extras.attribute_translations` bleiben unveraendert bestehen.

# Validation steps

- `docker compose exec -T php php -l src/Service/AttributeTranslationDictionaryService.php`
- `docker compose exec -T php php -l src/Service/ImportWorkflow.php`
- `docker compose exec -T php php -l src/Service/ExpandService.php`
- `docker compose exec -T php php -l src/Service/AfsExtrasBootstrapService.php`
- `docker compose exec -T php php run_import_all.php`
- `docker compose exec -T php php run_merge.php`
- `docker compose exec -T php php run_expand.php`
- SQL-Spotchecks auf:
  - `afs_extras.attribute_translations`
  - `stage_sync.raw_extra_attribute_translations`
  - `stage_sync.stage_attribute_translations`

# Recommended next step

Die leeren `en/fr/nl`-Platzhalter in `afs_extras.attribute_translations` mit eurer Uebersetzungssoftware fuellen, damit sie in den naechsten Laeufen automatisch in Stage und Shop erscheinen.
