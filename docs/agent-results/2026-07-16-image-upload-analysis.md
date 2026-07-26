## Task

Pruefung, wie der Bildupload in der Schnittstelle ablaeuft und welche Daten/Felder dabei verarbeitet werden.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docs/agent-results/2026-05-07-image-files.md`
- `public/index.php`
- `config/normalize.php`
- `config/expand.php`
- `src/Service/Normalizer.php`
- `src/Service/ExpandService.php`
- `src/Service/WelaApiClient.php`
- `src/Web/Controller/ImageFileController.php`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Web/View/image-files/index.php`

## Changed files

- `docs/agent-results/2026-07-16-image-upload-analysis.md`

## Summary

Der Bildupload ist in diesem Repository kein Browser-Upload mit `$_FILES`, sondern ein separater Admin-Prozess:

1. Bildreferenzen werden aus `stage_product_media` nach `images_file` synchronisiert.
2. Ein lokaler Bildordner (`IMAGES_ROOT_PATH`) wird rekursiv gescannt.
3. Gefundene Dateien werden per Dateiname gegen `images_file.file_name` gematcht.
4. Bei Abweichung oder fehlendem Shop-Pfad wird `upload = 1` gesetzt.
5. Offene Bilder werden lokal gelesen, Base64-kodiert und ueber die Wela-/XT-API hochgeladen.

Verarbeitet werden bei Bildern vor allem:
- Rohdatenfelder `image_1` bis `image_10` aus `raw_afs_articles`
- daraus erzeugt in `stage_product_media`: `afs_artikel_id`, `source_slot`, `file_name`, `path`, `type`, `document_type`, `sort_order`, `position`
- im Upload-Tracking `images_file`: `file_name`, `reference_count`, `local_path`, `file_hash`, `file_size`, `file_created_at`, `file_modified_at`, `upload`, `uploaded_at`, `shop_server_path`, `last_error`

Es findet keine inhaltliche Bildbearbeitung statt:
- keine Validierung von MIME-Type oder Bildformat
- keine Skalierung/Komprimierung
- keine Thumbnail-Erzeugung im Repo
- keine direkte Verarbeitung von Browser-Uploads

Die eigentliche Bildgroessenerzeugung wird an die Gegenstelle delegiert. Der Upload gilt nur dann als erfolgreich, wenn die API `image_generation_verified = true` zurueckliefert.

## Open points

- Der API-Action-Name ist weiterhin `upload_document_file`, auch fuer Bilder.
- Das Matching erfolgt nur ueber den Dateinamen, nicht ueber Unterordner oder Metadaten.
- Bei mehrfach vorkommenden Dateinamen im Scan-Pfad gewinnt der kuerzere Pfad.

## Validation steps

- Codepfade gelesen und abgeglichen:
  - `src/Web/Controller/ImageFileController.php`
  - `src/Web/Repository/ImageFileRepository.php`
  - `src/Service/ExpandService.php`
  - `src/Service/WelaApiClient.php`
  - `src/Service/Normalizer.php`
  - `config/normalize.php`
  - `config/expand.php`
  - `database.sql`
- Keine Laufzeitvalidierung ausgefuehrt.

## Recommended next step

Falls du willst, kann ich als Nächstes den konkreten API-Response fuer den Bildupload und die XT-Zielstruktur noch bis zur Gegenstelle rueckverfolgen, damit klar ist, wo die Dateien nach dem Upload genau landen und welche Formate die API dort effektiv akzeptiert.
