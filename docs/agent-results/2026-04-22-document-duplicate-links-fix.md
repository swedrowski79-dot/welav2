# Task

Doppelte Dokumentzuordnungen im Shop pruefen und beheben.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `config/merge.php`
- `config/delta.php`
- `config/xt_write.php`
- `src/Service/MergeService.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/WelaApiClient.php`
- `wela-api/index.php`

# Changed files

- `config/merge.php`
- `src/Service/MergeService.php`
- `src/Service/XtMediaDocumentWriter.php`
- `docs/agent-results/2026-04-22-document-duplicate-links-fix.md`

# Summary

- Die Doppelanzeige kam aus zwei unterschiedlichen Ursachen:
  1. in `stage_product_documents` konnte dieselbe Dokumentzeile mehrfach landen
  2. im Shop gab es historische `xt_media_link`-Reste fuer dieselbe Produktdatei, z. B. alte Links auf `xt_media`-Zeilen ohne oder mit veralteter `external_id`
- `stage_product_documents` wird jetzt beim Merge ueber
  - `afs_artikel_id`
  - `file_name`
  - `title`
  - `source_path`
  dedupliziert.
- `XtMediaDocumentWriter` raeumt beim Dokument-Export jetzt konkurrierende Dokument-Links fuer dasselbe Produkt und denselben Dateinamen weg und laesst nur den aktuell synchronisierten Link stehen.
- Nach dem Fix:
  - `stage_duplicate_groups = 0`
  - `duplicate_product_file_groups = 0`

# Open points

- Kein offener technischer Rest aus dem Dublettenproblem gefunden.
- Falls im Shop noch alte Darstellungen gecacht sind, kann ein weiterer Shop-Refresh sinnvoll sein; die Mirror-Daten selbst sind jetzt sauber.

# Validation steps

- `docker compose exec -T php php -l config/merge.php`
- `docker compose exec -T php php -l src/Service/MergeService.php`
- `docker compose exec -T php php -l src/Service/XtMediaDocumentWriter.php`
- `docker compose exec -T php php run_merge.php`
- gezielter Dokument-Reexport fuer `afs_document_id = 5126`
- `docker compose exec -T php php run_xt_mirror.php`
- SQL-Pruefungen:
  - keine Dubletten mehr in `stage_product_documents`
  - keine doppelten `xt_mirror_media_link`-Zuordnungen mehr pro Produkt/Datei

# Recommended next step

Im Shop einmal die betroffenen Artikel mit Dokumentreiter neu aufrufen und pruefen, dass jedes Dokument nur noch einmal angezeigt wird.
