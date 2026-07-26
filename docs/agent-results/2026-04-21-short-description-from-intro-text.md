# Task

`products_short_description` nicht mehr aus `raw_afs_articles.short_text`, sondern pro Sprache aus dem Einleitungstext `intro_text` der Extras-Translationstabelle ableiten.

# Files read

- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `database.sql`
- `src/Service/ImportWorkflow.php`
- `src/Service/AfsExtrasBootstrapService.php`
- `src/Web/Repository/MigrationRepository.php`
- `config/xt_write.php`

# Changed files

- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `database.sql`
- `src/Service/ImportWorkflow.php`
- `src/Service/AfsExtrasBootstrapService.php`
- `src/Web/Repository/MigrationRepository.php`
- `migrations/021_add_intro_text_to_raw_extra_article_translations.sql`
- `docs/agent-results/2026-04-21-short-description-from-intro-text.md`

# Summary

- Die Extras-Quelle `article_translations` liest jetzt die Spalte `intro_text` mit ein.
- `raw_extra_article_translations` besitzt jetzt ebenfalls die Spalte `intro_text`.
- `stage_product_translations.short_description` wird jetzt aus `raw_extra_article_translations.intro_text` gebildet.
- Der XT-Export musste nicht angepasst werden, weil `xt_write` bereits `translation.short_description` nach `products_short_description` schreibt.
- Fuer synthetische DE-Fallback-Zeilen aus `raw_afs_articles` wird `intro_text` weiterhin mit `short_text` befuellt, damit Artikel ohne echte Extra-Translations nicht leer laufen.

# Open points

- Falls du spaeter auch `stage_products.short_description_default` auf `intro_text` umstellen willst, ist das eine getrennte Entscheidung. Aktuell wurde nur die sprachbezogene Exportquelle fuer `products_short_description` angepasst.
- Die bereits laufende Shop-API braucht fuer diese Aenderung kein Update; das wirkt komplett in Import/Merge/Export der Sync-App.

# Validation steps

- `docker compose exec -T php php -l config/sources.php`
- `docker compose exec -T php php -l config/normalize.php`
- `docker compose exec -T php php -l config/merge.php`
- `docker compose exec -T php php -l src/Service/ImportWorkflow.php`
- `docker compose exec -T php php -l src/Service/AfsExtrasBootstrapService.php`
- `docker compose exec -T php php -l src/Web/Repository/MigrationRepository.php`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync < migrations/021_add_intro_text_to_raw_extra_article_translations.sql`
- `docker compose exec -T php php run_import_products.php`
- `docker compose exec -T php php run_merge.php`
- Spotcheck fuer `A-000`:
  - `afs_extras.article_translations.intro_text` ist befuellt fuer `de` und `en`
  - `stage_product_translations.short_description` zeigt fuer `A-000` denselben Einleitungstext in `de` und `en`

# Recommended next step

Wenn du willst, kann ich als Nächstes noch einen gezielten Export eines Beispielartikels fahren und im XT-Mirror prüfen, dass `products_short_description` im Shop genauso ankommt.
