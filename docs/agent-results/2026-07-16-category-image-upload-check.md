Task
- Pruefen, ob fuer den Upload in die Datenbank nur Produktbilder oder auch Kategoriebilder geschrieben werden, und den Upload-Pfad fuer Kategoriebilder implementieren.

Files read
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- README.md
- database.sql
- config/normalize.php
- config/expand.php
- config/delta.php
- config/xt_write.php
- src/Service/MergeService.php
- src/Service/ExpandService.php
- src/Service/StageCategoryMap.php
- src/Service/ProductDeltaService.php
- src/Web/Repository/ImageFileRepository.php

Changed files
- src/Web/Repository/ImageFileRepository.php
- src/Web/View/image-files/index.php
- src/Web/View/image-files/references-overlay.php
- docs/agent-results/2026-07-16-category-image-upload-check.md

Summary
- `images_file` wurde erweitert und sammelt jetzt Produktbilder aus `stage_product_media` sowie Kategoriebilder aus `stage_categories.image` und `stage_categories.header_image`.
- Der bestehende Scan-/Upload-Pfad wird damit fuer Produkt- und Kategoriebilder gemeinsam verwendet.
- Die Referenzansicht zeigt jetzt gemischte Referenzen fuer Produkte und Kategorien an.
- Die bestehende XT-Kategorie-Schreiblogik bleibt unveraendert; der neue Teil sorgt dafuer, dass die benoetigten Bilddateien ebenfalls im Upload-Lauf auftauchen.

Open points
- Es wurde keine Docker-/UI-Laufzeitpruefung ausgefuehrt.
- Im aktuellen Shell-Umfeld war `php` nicht verfuegbar, daher kein lokales `php -l`.

Validation steps
- Statische Codepruefung der Import-, Expand-, Upload- und XT-Write-Konfigurationen.
- `docker compose exec php php -l src/Web/Repository/ImageFileRepository.php`
- `docker compose exec php php -l src/Web/View/image-files/index.php`
- `docker compose exec php php -l src/Web/View/image-files/references-overlay.php`
- `docker compose exec php php run_image_scan.php`
- SQL-Pruefung in `stage_sync`:
  - `stage_categories` mit Bildreferenzen: `72`
  - `images_file` Gesamtzahl nach Scan: `399`
  - `images_file`-Eintraege mit Kategorie-Referenzen: `43`
- Kein echter Upload-Lauf ausgefuehrt, um keine externen Schreibvorgaenge auszulösen.

Recommended next step
- Im Admin unter `/image-files` pruefen, ob bei den Referenzen jetzt `Kategorie` erscheint; erst danach gezielt `docker compose exec php php run_image_upload.php` starten, wenn der externe Upload bewusst ausgefuehrt werden soll.
