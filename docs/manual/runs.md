# Run-Dokumentation

## Zweck

Diese Datei beschreibt alle im Projekt relevanten Run-Typen, ihre Ausloeser, ihre Aufgabe und ihre Sichtbarkeit im Monitoring.

Grundsatz:

- Pipeline-Runs bleiben Teil der CLI-/Pipeline-Struktur
- Upload-Runs fuer Bilder und Dokumente sind bewusst separat
- trotzdem schreiben beide Gruppen nach `sync_runs`, `sync_logs` und `sync_errors`

## Monitoring-Basis

Alle Runs werden ueber folgende Tabellen nachvollziehbar:

- `sync_runs`
- `sync_logs`
- `sync_errors`

Wichtige Felder:

- `sync_runs.run_type`
- `sync_runs.status`
- `sync_runs.started_at`
- `sync_runs.ended_at`
- `sync_runs.error_count`
- `sync_runs.message`
- `sync_runs.context_json`

Moegliche Statuswerte im aktuellen Projekt:

- `running`
- `success`
- `warning`
- `failed`

## Pipeline-Runs

### `import_all`

- Ausloeser: Pipeline-UI oder CLI
- Script: `run_import_all.php`
- Aufgabe: Fuehrt den gesamten Import in die RAW-Tabellen aus

### `import_products`

- Ausloeser: Pipeline-UI oder CLI
- Script: `run_import_products.php`
- Aufgabe: Importiert Produktdaten und Artikel-Uebersetzungen

### `import_categories`

- Ausloeser: Pipeline-UI oder CLI
- Script: `run_import_categories.php`
- Aufgabe: Importiert Kategorien und Kategorie-Uebersetzungen

### `merge`

- Ausloeser: Pipeline-UI oder CLI
- Script: `run_merge.php`
- Aufgabe: Baut die Stage-Grunddaten aus den RAW-Daten auf

### `expand`

- Ausloeser: Pipeline-UI oder CLI
- Script: `run_expand.php`
- Aufgabe: Erzeugt abgeleitete Stage-Daten wie Attribute und Produkt-Medien

Hinweis:

- Der Labeltext im UI lautet `Expand + Delta`
- Der Run-Typ selbst bleibt `expand`

### `delta`

- Ausloeser: Pipeline-UI oder CLI
- Script: `run_delta.php`
- Aufgabe: Berechnet Export-Deltas und befuellt die Queue

### `export_queue_worker`

- Ausloeser: Pipeline-UI oder CLI
- Script: `run_export_queue.php`
- Aufgabe: Verarbeitet die Export-Queue und schreibt nach XT

### `xt_mirror`

- Ausloeser: Pipeline-UI oder CLI
- Script: `run_xt_mirror.php`
- Aufgabe: Holt den XT-Zustand in die lokalen Mirror-Tabellen

### `full_pipeline`

- Ausloeser: Pipeline-UI oder CLI
- Script: `run_full_pipeline.php`
- Aufgabe: Fuehrt die aktive End-to-End-Sequenz aus

## Alias- und Spezial-Run-Typen

### `delta_products`

- Bedeutung im Monitoring: wird als `Delta` gelabelt
- Zweck: historischer oder interner Delta-Bezug

### `xt_snapshot`

- Bedeutung im Monitoring: wird als `XT Mirror Refresh` gelabelt
- Zweck: historischer oder interner Snapshot-/Mirror-Bezug

## Separate Upload-Runs

Diese Runs sind bewusst **nicht** Teil der Pipeline-Sektion und werden **nicht** ueber `run_*.php` gestartet.

Sie werden direkt aus dem Web-Backend gestartet, sobald in der Admin-Oberflaeche Upload-Aktionen ausgefuehrt werden.

### `image_upload`

- Ausloeser: Button `Offene Bilder hochladen`
- Einstieg: `POST /image-files/upload`
- Controller: `App\Web\Controller\ImageFileController::upload()`
- Aufgabe:
  - offene Eintraege aus `images_file` laden
  - lokale Dateien lesen
  - per API hochladen
  - Run, Logs und Fehler protokollieren

Statusverhalten:

- `success`: alle offenen Bilder erfolgreich verarbeitet oder nichts offen
- `warning`: Lauf beendet, aber einzelne Dateien hatten Fehler
- `failed`: der Upload-Lauf selbst konnte nicht sauber gestartet oder abgeschlossen werden

### `image_scan`

- Ausloeser: Button `Bildpfad scannen` oder CLI
- Einstieg: `POST /image-files/scan` oder `php run_image_scan.php`
- Aufgabe:
  - `images_file` aus `stage_product_media` auffuellen
  - lokalen Bildpfad scannen
  - `local_path`, `file_hash`, Metadaten und `upload` aktualisieren

### `document_upload`

- Ausloeser: Button `Offene Dokument-Dateien hochladen`
- Einstieg: `POST /document-files/upload`
- Controller: `App\Web\Controller\DocumentFileController::upload()`
- Aufgabe:
  - offene Eintraege aus `documents_file` laden
  - lokale Dateien lesen
  - per API hochladen
  - Run, Logs und Fehler protokollieren

Statusverhalten:

- `success`: alle offenen Dokumente erfolgreich verarbeitet oder nichts offen
- `warning`: Lauf beendet, aber einzelne Dateien hatten Fehler
- `failed`: der Upload-Lauf selbst konnte nicht sauber gestartet oder abgeschlossen werden

### `document_scan`

- Ausloeser: Button `Dokumentenpfad scannen` oder CLI
- Einstieg: `POST /document-files/scan` oder `php run_document_scan.php`
- Aufgabe:
  - `documents_file` aus `stage_product_documents` auffuellen
  - lokalen Dokumentpfad scannen
  - `local_path`, `file_hash`, Metadaten und `upload` aktualisieren

## Scan-Aktionen

Die Scan-Aktionen fuer Bilder und Dokumente sind derzeit weiterhin separate Web-Aktionen ohne eigenen `sync_run`.

Betroffen:

- `POST /image-files/scan`
- `POST /document-files/scan`

Diese Aktionen pflegen primär:

- `images_file`
- `documents_file`

## Sichtbarkeit im UI

Die Upload-Runs erscheinen in:

- `Monitoring Laeufe`
- `Monitoring Logs`
- `Monitoring Fehler`
- Dashboard-Widgets, die auf `sync_runs` basieren

Sie erscheinen nicht als eigene startbare Pipeline-Jobs, solange sie nicht in `config/pipeline.php` als Jobs definiert werden.

## CLI-Befehle

Folgende separate CLI-Entrypoints stehen zur Verfuegung:

- `php run_image_scan.php`
- `php run_image_upload.php`
- `php run_document_scan.php`
- `php run_document_upload.php`

Diese Skripte sind bewusst nicht Teil der Pipeline und nicht in `full_pipeline` eingebunden.
