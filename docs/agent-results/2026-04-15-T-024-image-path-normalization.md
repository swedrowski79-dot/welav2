# Task

Implement ticket `T-024` so image path normalization happens in the normalization model/mapping layer.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `docs/tickets/open/T-024.md`
- `docs/agent-results/2026-04-14-category-source-name-fix.md`
- `docs/agent-results/2026-04-14-import-fix-and-async-pipeline.md`
- `config/normalize.php`
- `src/Service/Normalizer.php`
- `src/Importer/AfsImporter.php`
- `src/Service/StageWriter.php`

# Changed files

- `config/normalize.php`
- `src/Service/Normalizer.php`
- `docs/tickets/done/T-024.md`
- `docs/agent-results/2026-04-15-T-024-image-path-normalization.md`

# Summary

- Added field-level transform support to `Normalizer`, so mapping entries can apply normalization logic directly in the mapping layer.
- Added `calc:normalize_image_filename` to strip directory prefixes and keep only the filename.
- Applied that transform to category image fields in `config/normalize.php`.
- The transform handles both Unix (`/path/file.jpg`) and Windows (`C:\\path\\file.jpg`) paths.
- Empty values remain unchanged.
- No export or UI code was changed.

# Open points

- The current active article/stage schema does not expose dedicated product image columns in the normalization config, so this ticket now provides the reusable mapping-layer transform and applies it to the image fields currently present there.

# Validation steps

- Executed:
  - `docker compose exec -T php php -l /app/config/normalize.php`
  - `docker compose exec -T php php -l /app/src/Service/Normalizer.php`
  - `docker compose exec -T php php -r '$config = require "/app/config/normalize.php"; require "/app/src/Service/Normalizer.php"; $n = new Normalizer($config); $row = ["Bild" => "C:\\\\images\\\\cat\\\\hero.png", "Bild_gross" => "/srv/media/header/banner.jpg", "Warengruppe" => 1, "Anhang" => 0, "Ebene" => 1, "Bezeichnung" => "Test", "Beschreibung" => "Test", "Internet" => 0]; var_export($n->normalize("afs.categories", $row));'`
- Observed:
  - `image => 'hero.png'`
  - `header_image => 'banner.jpg'`

# Recommended next step

Run the AFS category import once and confirm newly imported `raw_afs_categories` / `stage_categories` rows contain only filenames in `image` and `header_image`.
