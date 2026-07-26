# Task

Die Bild-Dateiverwaltung auf den Stand der Dokument-Verwaltung bringen: fehlende Bilder filtern, Pagination ober- und unterhalb der Tabelle, Referenz-Overlay mit Artikelnummern, lokale Pfadwahl sowie Upload-/Scan-Oberflaeche fertigstellen.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `public/index.php`
- `config/admin.php`
- `src/Web/Core/Controller.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Controller/ImageFileController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Web/View/document-files/index.php`
- `src/Web/View/document-files/references-overlay.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/status/index.php`

# Changed files

- `public/index.php`
- `config/admin.php`
- `src/Web/Controller/ImageFileController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Web/View/image-files/index.php`
- `src/Web/View/image-files/browse.php`
- `src/Web/View/image-files/references-overlay.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/status/index.php`

# Summary

Die neue Admin-Seite `Bild-Dateien` ist jetzt vollstaendig verdrahtet. Sie zeigt `images_file` mit Filter fuer nicht gefundene Bilder, einstellbarer Seitengroesse, Pagination oben und unten sowie klickbaren Referenzzahlen.

Das Referenz-Overlay laedt per AJAX und zeigt Artikelnummer, AFS Artikel-ID, Produktname, Slot, Sortierung und Media-ID. Fuer lokale Bildpfade gibt es eine eigene Browse-Ansicht, und in der Status-Seite koennen jetzt sowohl lokaler Bildpfad als auch Shop-Zielpfad gepflegt und per Browser ausgewaehlt werden.

# Open points

- Fuer echte Inhalte im Referenz-Overlay muss `images_file` bereits befuellt sein, z. B. nach einem Scan.
- Der Upload nutzt wie bei Dokumenten den vorhandenen XT-API-Datei-Upload und schreibt in den konfigurierten Bild-Zielpfad.

# Validation steps

- `docker compose exec -T php php -l src/Web/Controller/ImageFileController.php`
- `docker compose exec -T php php -l src/Web/Repository/ImageFileRepository.php`
- `docker compose exec -T php php -l src/Web/View/image-files/index.php`
- `docker compose exec -T php php -l src/Web/View/image-files/browse.php`
- `docker compose exec -T php php -l src/Web/View/image-files/references-overlay.php`
- `docker compose exec -T php php -l src/Web/Controller/StatusController.php`
- `docker compose exec -T php php -l src/Web/View/status/index.php`
- `docker compose exec -T php php -l public/index.php`
- `curl -fsS http://localhost:8080/image-files`
- `curl -fsS 'http://localhost:8080/image-files?filter=missing'`
- `curl -fsS 'http://localhost:8080/image-files/references?id=1'`
- `curl -fsS http://localhost:8080/status`

# Recommended next step

Einmal `Bildpfad scannen` in der Admin-Oberflaeche ausfuehren, damit `images_file` initial aus `stage_product_media` befuellt und die Referenz-/Upload-Ansicht mit echten Bilddateien arbeitet.
