# Bedienungsanleitung

## Ziel

Diese Anleitung beschreibt die Bedienung der wichtigsten Laufarten:

- Pipeline-Laeufe
- separater Bild-Upload
- separater Dokument-Upload
- Monitoring und Fehlersuche

## 1. Pipeline starten

Pipeline-Laeufe werden ueber die Pipeline-Oberflaeche oder per CLI gestartet.

Typische Reihenfolge:

1. `Import`
2. `Merge`
3. `Expand + Delta`
4. optional `Export Worker`

Alternative:

1. `Full Pipeline`

Hinweise:

- Diese Runs sind Teil der orchestrierten CLI-Pipeline.
- Sie erscheinen in `Monitoring Laeufe`.
- Detailinformationen sind in `sync_logs` und `sync_errors` sichtbar.

## 2. Bilder hochladen

Der Bild-Upload ist bewusst separat von der Pipeline.

Vorgehen:

1. In `Status` den lokalen Bildpfad und optional den XT-Zielpfad pruefen
2. In `Bild-Dateien` zuerst `Bildpfad scannen` ausfuehren
3. Danach `Offene Bilder hochladen` ausfuehren

Was beim Upload passiert:

- offene Datensaetze aus `images_file` werden geladen
- lokale Dateien werden gelesen
- Dateien werden ueber die API hochgeladen
- fuer diesen Lauf wird ein eigener `sync_run` mit `run_type = image_upload` erzeugt

Ergebnispruefung:

- `Monitoring Laeufe` zeigt den neuen Upload-Lauf
- `Monitoring Logs` zeigt Start, gefundene offene Dateien und erfolgreiche Uploads
- `Monitoring Fehler` zeigt fehlgeschlagene Einzeldateien
- in `images_file` werden `uploaded_at`, `shop_server_path` und `last_error` aktualisiert

CLI-Alternative:

- `php run_image_scan.php`
- `php run_image_upload.php`

## 3. Dokumente hochladen

Der Dokument-Upload ist ebenfalls separat von der Pipeline.

Vorgehen:

1. In `Status` den lokalen Dokumentpfad und optional den XT-Zielpfad pruefen
2. In `Dokument-Dateien` zuerst den Dokumentpfad scannen
3. Danach `Offene Dokument-Dateien hochladen` ausfuehren

Was beim Upload passiert:

- offene Datensaetze aus `documents_file` werden geladen
- lokale Dateien werden gelesen
- Dateien werden ueber die API hochgeladen
- fuer diesen Lauf wird ein eigener `sync_run` mit `run_type = document_upload` erzeugt

Ergebnispruefung:

- `Monitoring Laeufe` zeigt den neuen Upload-Lauf
- `Monitoring Logs` zeigt Start, offene Dateien und erfolgreiche Uploads
- `Monitoring Fehler` zeigt fehlerhafte Dateien
- in `documents_file` werden `uploaded_at`, `shop_server_path` und `last_error` aktualisiert

CLI-Alternative:

- `php run_document_scan.php`
- `php run_document_upload.php`

## 4. Monitoring lesen

### `Monitoring Laeufe`

Hier sieht man:

- Run-Typ
- Status
- Start- und Endzeit
- Dauer
- Fehlermenge
- Laufdetails

Statusbedeutung:

- `running`: Lauf ist aktiv
- `success`: Lauf erfolgreich beendet
- `warning`: Lauf beendet, aber mit Teilfehlern
- `failed`: Lauf als Ganzes fehlgeschlagen

### `Monitoring Logs`

Hier sieht man normale Laufmeldungen, zum Beispiel:

- Start eines Upload-Laufs
- Anzahl offener Dateien
- erfolgreiche Einzeluploads

### `Monitoring Fehler`

Hier stehen fachliche oder technische Fehler, zum Beispiel:

- lokale Datei nicht lesbar
- API-Upload fehlgeschlagen
- erwartete Nachbearbeitung auf Gegenseite nicht bestaetigt

## 5. Typische Bedienreihenfolge fuer Uploads

### Bilder

1. Pfade in `Status` kontrollieren
2. `Bildpfad scannen`
3. `Offene Bilder hochladen`
4. Run in `Monitoring Laeufe` pruefen
5. Fehler in `Monitoring Fehler` pruefen

### Dokumente

1. Pfade in `Status` kontrollieren
2. Dokumentpfad scannen
3. `Offene Dokument-Dateien hochladen`
4. Run in `Monitoring Laeufe` pruefen
5. Fehler in `Monitoring Fehler` pruefen

## 6. Wichtige Abgrenzung

Die Upload-Runs sind absichtlich getrennt von der Pipeline.

Das bedeutet:

- kein Pipeline-Button startet diese Uploads
- keine Einbindung in `full_pipeline`
- trotzdem vollstaendige Monitoring-Historie ueber `sync_runs`, `sync_logs` und `sync_errors`

## 7. Separate CLI-Befehle

Die folgenden Befehle koennen direkt im Projektverzeichnis ausgefuehrt werden:

```bash
php run_image_scan.php
php run_image_upload.php
php run_document_scan.php
php run_document_upload.php
```

Im Docker-Setup typischerweise:

```bash
docker compose exec php php run_image_scan.php
docker compose exec php php run_image_upload.php
docker compose exec php php run_document_scan.php
docker compose exec php php run_document_upload.php
```
