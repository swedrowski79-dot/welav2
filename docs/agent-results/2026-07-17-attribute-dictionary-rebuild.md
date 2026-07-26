Task
- Die alte artikelbezogene `afs_extras.attribute_translations`-Tabelle in ein flaches Attribut-Dictionary umbauen, vorhandene Uebersetzungen migrieren und die Import-Pipeline so anpassen, dass daraus weiterhin `raw_extra_attribute_translations` fuer die Stage erzeugt werden.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- database.sql
- config/sources.php
- config/normalize.php
- run_import_all.php
- run_import.php
- run_import_products.php
- run_import_categories.php
- src/Importer/ExtraImporter.php
- src/Service/AfsExtrasBootstrapService.php
- src/Service/AttributeTranslationDictionaryService.php
- src/Service/ImportWorkflow.php

Changed files
- config/sources.php
- config/normalize.php
- run_import_all.php
- run_import.php
- run_import_products.php
- run_import_categories.php
- run_delta.php
- run_expand.php
- src/Importer/ExtraImporter.php
- src/Web/Core/Router.php
- src/Web/Controller/AttributeDictionaryController.php
- src/Web/Repository/AttributeDictionaryRepository.php
- src/Web/Repository/ExtraConnection.php
- src/Web/View/attribute-dictionary/index.php
- src/Web/View/layouts/app.php
- public/index.php
- src/Service/AfsExtrasBootstrapService.php
- src/Service/AttributeTranslationDictionaryService.php
- src/Service/AttributeTranslationProjectionService.php
- src/Service/DeltaRunnerService.php
- src/Service/ImportWorkflow.php
- src/Service/ProductDeltaService.php
- docs/agent-results/2026-07-17-attribute-dictionary-rebuild.md

Summary
- `afs_extras.attribute_translations` wurde von der alten artikelbezogenen Struktur auf ein flaches Dictionary umgebaut.
- Neue Struktur:
  - `source_text`
  - `normalized_key`
  - `de`
  - `en`
  - `fr`
  - `nl`
  - `source_directory`
  - `is_active`
- Beim ersten Lauf wurde die alte Tabelle automatisch migriert:
  - `42398` Legacy-Zeilen gelesen
  - `614` Dictionary-Begriffe erzeugt
  - Backup-Tabelle `attribute_translations_legacy_20260717` angelegt
- Der laufende Sync setzt Begriffe aus den aktuellen `raw_afs_articles` auf `is_active = 1` und deaktiviert nicht mehr vorkommende Begriffe mit `is_active = 0`.
- Fuer die Stage-Pipeline gibt es jetzt einen neuen Projektions-Service:
  - Er liest das flache Dictionary aus `afs_extras`
  - und erzeugt daraus wieder artikelbezogene `raw_extra_attribute_translations`
- Der alte direkte Extra-Import fuer Attribute wurde bewusst deaktiviert, damit niemand versehentlich wieder das alte Schema verwendet.
- Das Webinterface greift jetzt direkt auf die neue Dictionary-Tabelle zu:
  - eingebettete Sektion `Attribut-Dictionary` unter `/translations`
  - Filter fuer Suche und `is_active`
  - Inline-Bearbeitung fuer `en`, `fr`, `nl`, `source_directory` und `is_active`
  - kein eigener Menuepunkt mehr im Sidebar-Menue
- Nach der Projektion wurde `raw_extra_attribute_translations` erfolgreich neu aufgebaut:
  - `40508` Zeilen
  - `10127` pro Sprache (`de`, `en`, `fr`, `nl`)
- Der Produkt-Export wurde anschliessend von der projizierten Stage-Attribut-Tabelle entkoppelt.
- `ProductDeltaService` baut Produktattribute jetzt direkt aus:
  - den Roh-Attributen in `stage_products`
  - plus den aktiven Begriffen aus `afs_extras.attribute_translations`
- Der XT-Export liest fuer Produkte damit Attribut-Uebersetzungen nicht mehr aus `stage_attribute_translations`.
- Fehlende Begriffe im Dictionary erzeugen keine fremdsprachigen Attributzeilen mehr; `de` bleibt immer aus den AFS-Quelldaten erhalten.
- `run_delta.php` und der in `run_expand.php` enthaltene Delta-Schritt oeffnen dafuer jetzt zusaetzlich die Extra-DB-Verbindung.

Open points
- `normalized_key` normalisiert aktuell nur ueber `trim`, `lowercase` und kollabierte Leerzeichen. Falls spaeter Schreibvarianten wie `080mm` vs `080 mm` fachlich zusammengefasst werden sollen, braucht es eine staerkere Normalisierung.
- Das Dictionary arbeitet bewusst ohne Trennung zwischen Attributname und Attributwert, wie besprochen. Dadurch koennen spaeter bei semantisch gleichen Texten in unterschiedlichen Kontexten Kollisionen entstehen.
- `stage_attribute_translations` und die Projektion existieren aktuell noch weiter fuer Kompatibilitaet und bestehende UIs, sind aber nicht mehr die Quelle fuer den Produkt-Export.

Validation steps
- `docker compose exec php php -l src/Service/AttributeTranslationDictionaryService.php`
- `docker compose exec php php -l src/Service/AttributeTranslationProjectionService.php`
- `docker compose exec php php -l src/Service/ImportWorkflow.php`
- `docker compose exec php php -l src/Importer/ExtraImporter.php`
- `docker compose exec php php -l src/Service/AfsExtrasBootstrapService.php`
- `docker compose exec php php -l src/Web/Core/Router.php`
- `docker compose exec php php -l src/Web/Repository/ExtraConnection.php`
- `docker compose exec php php -l src/Web/Repository/AttributeDictionaryRepository.php`
- `docker compose exec php php -l src/Web/Controller/AttributeDictionaryController.php`
- `docker compose exec php php -l src/Web/View/attribute-dictionary/index.php`
- `docker compose exec php php -l src/Web/View/layouts/app.php`
- `docker compose exec php php -l config/sources.php`
- `docker compose exec php php -l config/normalize.php`
- `docker compose exec php php -l src/Service/ProductDeltaService.php`
- `docker compose exec php php -l src/Service/DeltaRunnerService.php`
- `docker compose exec php php -l run_delta.php`
- `docker compose exec php php -l run_expand.php`
- `docker compose exec php php -r '... AttributeTranslationDictionaryService->sync() ...'`
- `docker compose exec -T mysql mysql -ustage -pstage afs_extras -e "SHOW CREATE TABLE attribute_translations\G"`
- `docker compose exec -T mysql mysql -ustage -pstage afs_extras -e "SHOW TABLES LIKE 'attribute_translations_legacy_20260717';"`
- `docker compose exec php php -r '... AttributeTranslationProjectionService->rebuild() ...'`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -e "SELECT COUNT(*) FROM raw_extra_attribute_translations;"`
- `curl -s http://localhost:8080/translations`
- `curl -s -X POST http://localhost:8080/attribute-dictionary/update -d 'id=12&field=is_active&value=1'`

Recommended next step
- Die zweite KI ab jetzt nur noch gegen das flache Dictionary in `afs_extras.attribute_translations` arbeiten lassen und danach einmal `run_import_products.php` plus `run_expand.php` laufen lassen, um den kompletten neuen Attributpfad im normalen Pipeline-Ablauf zu pruefen.
