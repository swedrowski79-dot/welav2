# Task

AFS-Zusatzfelder `Zusatzfeld03-06` und `Zusatzfeld15-18` in den Attribut-Flow aufnehmen, so dass fehlende `de`-Eintraege in den Artikel-Uebersetzungen aus AFS aufgefuellt und bis in den Shop exportiert werden.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `src/Service/ImportWorkflow.php`
- `src/Web/Repository/MigrationRepository.php`

# Changed files

- `database.sql`
- `config/normalize.php`
- `config/merge.php`
- `src/Service/ImportWorkflow.php`
- `src/Web/Repository/MigrationRepository.php`
- `migrations/017_add_raw_article_attribute_slots.sql`

# Summary

- `raw_afs_articles` speichert jetzt `attribute_name1-4` und `attribute_value1-4` aus `Zusatzfeld03-06` bzw. `Zusatzfeld15-18`.
- Beim Produktimport werden fehlende `de`-Zeilen in `raw_extra_article_translations` aus AFS nachgezogen, wenn AFS Attributdaten vorhanden sind. Dadurch landen die Attribute auch fuer Artikel ohne Extra-Uebersetzungsdatei in der bestehenden Attribut-Pipeline.
- Der Merge nutzt fuer `stage_product_translations.attribute_*` jetzt Extra-Werte zuerst und AFS-Werte als Fallback.
- Validierung:
  - `run_import_all.php`, `run_merge.php`, `run_expand.php`, `run_full_pipeline.php`, `run_xt_mirror.php` liefen erfolgreich.
  - `raw_extra_article_translations` enthaelt `150` erzeugte Fallback-Zeilen mit `source_directory = 'afs_attribute_fallback'`.
  - Beispiel `AUAA-160-1-2`:
    - `raw_extra_article_translations`: `de`-Fallback mit `Durchmesser=160mm`, `Gelenk=1x`
    - `stage_attribute_translations`: expandierte Attributzeilen vorhanden
    - `xt_mirror_*`: Attributverknuepfungen nach Export vorhanden
- `LPE-000` bleibt weiterhin ohne Attribute, weil die Quelle fuer diesen Artikel in AFS selbst keine Werte in `Zusatzfeld03-06/15-18` liefert.

# Open points

- Fallback-Attribute erzeugen aktuell nur eine synthetische `de`-Grundzeile. Wenn fuer reine AFS-Artikel auch vollstaendige lokalisierte Attributnamen in `en/fr/nl` benoetigt werden, braucht es dafuer eine eigene Uebersetzungsquelle oder eine explizite Ableitungsregel.
- `LPE-000` muss in der Quelle gepflegt werden, falls der Artikel im Shop Attribute zeigen soll.

# Validation steps

- `docker compose exec -T mysql mysql -uroot -proot stage_sync < migrations/017_add_raw_article_attribute_slots.sql`
- `docker compose exec -T php php run_import_all.php`
- `docker compose exec -T php php run_merge.php`
- `docker compose exec -T php php run_expand.php`
- `docker compose exec -T php php run_full_pipeline.php`
- `docker compose exec -T php php run_xt_mirror.php`
- SQL-Spotchecks auf `raw_afs_articles`, `raw_extra_article_translations`, `stage_attribute_translations`, `xt_mirror_products`, `xt_mirror_plg_products_to_attributes`, `xt_mirror_plg_products_attributes_description`

# Recommended next step

Quelldaten fuer `LPE-000` in AFS pruefen und dort `Zusatzfeld03-06/15-18` befuellen, falls dieser Artikel Attribute im Shop erhalten soll.
