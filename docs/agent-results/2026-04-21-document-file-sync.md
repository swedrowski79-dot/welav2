# Task

Getrennte Dokument-Datei-Logik ausserhalb der Pipeline fertig verdrahten: Admin-Seite, API-Uploadpfad und lazy geladenen Overlay-Ordnerbrowser fuer lokalen Dokumentpfad und shopseitigen Zielpfad.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `database.sql`
- `public/index.php`
- `src/Service/WelaApiClient.php`
- `wela-api/index.php`
- `wela-api/config.php.example`
- `src/Web/Core/Request.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`
- `src/Web/View/status/index.php`
- `src/Web/View/layouts/app.php`

# Changed files

- `public/index.php`
- `src/Web/Core/Request.php`
- `src/Service/WelaApiClient.php`
- `src/Web/Controller/StatusController.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/View/document-files/index.php`
- `src/Web/View/status/index.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/status/browse-api.php`
- `wela-api/index.php`
- `wela-api/config.php.example`
- `wela-api/README.md`
- `src/Web/View/document-files/browse.php`
- `docs/agent-results/2026-04-21-document-file-sync.md`

# Summary

- Die Dokument-Seite ist jetzt voll an den Web-Router angeschlossen:
  - `GET /document-files`
  - `GET /document-files/browse`
  - `GET /document-files/browse-tree`
  - `POST /document-files/path`
  - `POST /document-files/scan`
  - `POST /document-files/upload`
- Zusaetzlich gibt es fuer den Shop-Browser den JSON-Endpunkt `GET /status/browse-api-tree`.
- Der bisherige Browser ist jetzt als Overlay direkt in die Oberflaeche integriert:
  - Windows-aehnlicher Tree mit `+` / `-`
  - aktuelle Auswahl wird markiert
  - unten nur ein Button `Ordner auswaehlen`
  - Unterordner werden erst beim Expandieren nachgeladen
- Der Overlay-Browser wird fuer beide Pfade verwendet:
  - lokalen `DOCUMENTS_ROOT_PATH`
  - shopseitigen `XT_DOCUMENTS_TARGET_PATH`
- Dadurch bleibt der Browser performant, weil nicht der komplette Verzeichnisbaum auf einmal geladen wird.
- `WelaApiClient` kennt jetzt `uploadDocumentFile()`.
- `wela-api/index.php` versteht jetzt die neue Action `upload_document_file` und schreibt die Datei physisch in einen konfigurierbaren Zielordner auf dem Shop-Server.
- Der Upload liefert den kompletten Shop-Zielpfad und die Anzahl geschriebener Bytes zurueck.
- Zusaetzlich gibt es jetzt die neue API-Action `browse_server_directories`, damit der Zielpfad im Shop ueber die Weboberflaeche gebrowst werden kann.
- In `Konfiguration/Status` gibt es jetzt das neue Setting `XT_DOCUMENTS_TARGET_PATH` inklusive Browser-Button. Die Auswahl laeuft direkt ueber die XT-API und wird anschliessend fuer Dokument-Uploads verwendet.
- `StatusController::save()` speichert jetzt nur noch wirklich gepostete Felder. Dadurch kann der neue Browse-Dialog einen einzelnen Pfad sichern, ohne andere `.env`-Werte zu leeren.
- Der Web-Scan wurde gegen die laufende Admin-Oberflaeche getestet:
  - `DOCUMENTS_ROOT_PATH=/app`
  - danach wurden `245` eindeutige Titel aus `stage_product_documents` nach `documents_file` synchronisiert
  - da unter `/app` keine passenden Quelldateien fuer diese Titel lagen, blieben diese erwartungsgemaess ohne lokale Treffer

# Open points

- Die konfigurierte XT-API-Instanz kennt die neuen Action-Namen `upload_document_file` und `browse_server_directories` noch nicht. Die neuen API-Pfade sind im Repo implementiert, aber auf der laufenden Shop-API noch nicht ausgerollt.
- Deshalb ist der echte Datei-Upload aus der Admin-Seite erst nach API-Update produktiv nutzbar.
- Die spaetere Bild-Logik ist hiervon bewusst getrennt und noch nicht begonnen.

# Validation steps

- `docker compose exec -T php php -l src/Web/Repository/DocumentFileRepository.php`
- `docker compose exec -T php php -l src/Web/Controller/DocumentFileController.php`
- `docker compose exec -T php php -l src/Web/Controller/StatusController.php`
- `docker compose exec -T php php -l src/Web/Core/Request.php`
- `docker compose exec -T php php -l src/Service/WelaApiClient.php`
- `docker compose exec -T php php -l wela-api/index.php`
- `docker compose exec -T php php -l public/index.php`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync < migrations/020_create_documents_file_table.sql`
- `curl http://localhost:8080/document-files`
- `curl http://localhost:8080/status`
- `curl 'http://localhost:8080/document-files/browse-tree?path=%2Fapp'`
- `curl -X POST -d 'DOCUMENTS_ROOT_PATH=/app' http://localhost:8080/document-files/path`
- `curl -X POST http://localhost:8080/document-files/scan`
- XT-API-Spotcheck ueber den aktuellen Client:
  - `browse_server_directories` liefert aktuell noch `Unbekannte Aktion`, solange die Shop-API nicht mit dem neuen Repo-Stand deployed ist
- Datenbank-Spotcheck:
  - `documents_file` enthaelt danach `245` Datensaetze

# Recommended next step

Die laufende XT-API mit dem neuen `wela-api/index.php` deployen, danach in `Konfiguration/Status` den Shop-Zielpfad per Browser waehlen und anschliessend den Upload-Lauf ueber `/document-files/upload` mit realen Dateien ausfuehren.
