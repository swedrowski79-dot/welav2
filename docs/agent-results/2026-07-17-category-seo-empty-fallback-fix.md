Task
- Ursache fuer leere Kategorie-SEO-URLs (`category-2-empty`) analysieren und den Kategorie-Export so absichern, dass fehlende Kategorie-Uebersetzungen mit Stage-Defaultwerten aufgefuellt werden.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- config/sources.php
- config/xt_write.php
- src/Service/StageCategoryMap.php
- src/Service/XtCategoryWriter.php
- wela-api/index.php
- wela-api/seo_helpers.php

Changed files
- src/Service/XtCategoryWriter.php
- docs/agent-results/2026-07-17-category-seo-empty-fallback-fix.md

Summary
- Das Problem lag nicht an der reinen SEO-URL-Berechnung, sondern an leeren Kategorie-Uebersetzungen im Exportpfad.
- Die API generiert Kategorie-SEO-URLs aus `xt_categories_description.categories_name`.
- Fuer Kategorien ohne `stage_category_translations` wurde bisher im Export eine leere Uebersetzung geschrieben.
- Dadurch erzeugte die API Fallback-SEO-URLs wie `category-2-empty`.
- `XtCategoryWriter` fuellt fehlende Kategorie-Uebersetzungen jetzt aus `stage_categories.name_default` und `stage_categories.description_default` auf.
- Zusaetzlich werden `meta_title` und `meta_description` bei fehlenden Uebersetzungswerten ebenfalls sinnvoll aus den Stage-Defaults befuellt.

Open points
- Die Korrektur aendert den Exportpfad, nicht bereits vorhandene fehlerhafte SEO-URLs im XT-Shop. Fuer bestehende Kategorien ist daher ein erneuter Kategorie-Export noetig.
- Wenn bestimmte Kategorien bewusst sprachspezifisch leer bleiben sollen, wird nun stattdessen der Stage-Default verwendet.

Validation steps
- `docker compose exec php php -l src/Service/XtCategoryWriter.php`
- One-off-Pruefung fuer Kategorie `afs_wg_id = 2`:
  - Fallback-Uebersetzung liefert `name = "E-Stutzen GANI"`
  - `buildTranslationWrites()` erzeugt `categories_name = "E-Stutzen GANI"` fuer `de/en/fr/nl`

Recommended next step
- Kategorie-Queue fuer betroffene Kategorien auf Retry setzen oder einen gezielten Kategorie-Exportlauf starten, damit die fehlerhaften `category-*-empty`-SEO-URLs im XT-Shop ersetzt werden.
