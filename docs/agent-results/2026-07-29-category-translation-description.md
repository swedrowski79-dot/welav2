# Warengruppenbeschreibungen aus AFS Extras

## Task

Die sprachabhängige Warengruppenbeschreibung aus `afs_extras.category_translations` vollständig durch den Import und Merge bis `stage_category_translations` führen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `src/Importer/ExtraImporter.php`
- `src/Service/AfsExtrasBootstrapService.php`
- `src/Web/Repository/MigrationRepository.php`
- `scripts/setup-database.php`

## Changed files

- `database.sql`
- `migrations/022_add_category_translation_description.sql`
- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `src/Service/AfsExtrasBootstrapService.php`
- `src/Web/Repository/MigrationRepository.php`
- `scripts/setup-database.php`

## Summary

- `description` wurde in das Schema von `afs_extras.category_translations` und `raw_extra_category_translations` aufgenommen.
- Der Extra-Import liest und normalisiert die neue Spalte.
- Der Kategorie-Merge bevorzugt die sprachabhängige Zusatzbeschreibung und verwendet die deutsche AFS-Beschreibung weiterhin als Fallback.
- Der SQLite-Bootstrap bleibt mit älteren Quelldatenbanken ohne Beschreibungsfeld kompatibel; zusätzliche Zielspalten sind nun zulässig.
- Bestehende Stage-Installationen erhalten die RAW-Spalte über Migration `022`.
- Der Migrationslauf erkennt eine bereits vorhandene Beschreibungsspalte und kann Migration `022` idempotent nachtragen.
- Das automatische Datenbank-Setup repariert die RAW-Spalte auch beim Containerstart.

## Open points

- Fremdsprachige Beschreibungen bleiben leer, bis sie im Übersetzungs-Webinterface gepflegt werden.

## Validation steps

- PHP-Syntaxprüfung der geänderten PHP-Dateien.
- Migration `022` gegen die laufende Stage-Datenbank ausführen.
- Extra-Import und Merge ausführen.
- Prüfen, dass eine gespeicherte Beschreibung in `raw_extra_category_translations` und `stage_category_translations` ankommt.

## Recommended next step

Die noch leeren Beschreibungen für EN, FR und NL im Übersetzungs-Webinterface ergänzen und anschließend Import und Merge erneut ausführen.
