# Diagnose Bild-Upload und XT-Bildgrößen

## Task

Prüfen, warum der Artikelbild-Upload scheinbar nicht mehr funktioniert, und
den Fehler zwischen lokalem Dateiscan, API-Upload, Medienverknüpfung und
XT-Bildgrößenerzeugung eingrenzen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `run_image_upload.php`
- `config/expand.php`
- `config/delta.php`
- `config/xt_write.php`
- `config/xt_mirror.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Web/Repository/ImageFileRepository.php`
- `wela-api/index.php`
- `wela-api/src/FileTransferService.php`
- `wela-api/xt_image_helpers.php`
- `docs/agent-results/2026-07-29-product-multiple-images-interface-check.md`
- aktuelle `sync_runs`, `sync_logs`, `sync_errors`, `images_file`,
  `export_queue` und XT-Mirror-Tabellen
- aktive XT-Tabellen `xt_media`, `xt_media_link` und `xt_image_type` read-only
  über die Wela-API

## Changed files

- `docs/agent-results/2026-07-29-image-upload-regeneration-diagnosis.md`

Produktionscode, Export-State, Uploadstatus und Shopdaten wurden bei dieser
Diagnose nicht verändert.

## Summary

### Der Datei-Upload funktioniert

Der aktuelle Bild-Upload-Lauf `183` ist erfolgreich abgeschlossen:

- Start: `2026-07-29 16:57:13`
- Ende: `2026-07-29 16:58:37`
- vorgesehene Dateien: 425
- erfolgreich hochgeladen: 425
- API-Fehler: 0
- Status: `success`

Aktueller Stand in `images_file`:

- 427 Bilddateinamen insgesamt
- 425 erfolgreich hochgeladen
- 0 wartende Uploads
- 2 lokal fehlende Dateien

Die beiden fehlenden Quelldateien sind:

- `Potenzialausgleich.jpg`
- `STW84V_Anschlussplan.png`

Ein vollständiger HTTP-Abgleich aller 427 referenzierten Originaldateien gegen
`/media/images/org/` ergab:

- 426 Dateien vorhanden und per HTTP 200 erreichbar
- 1 Datei nicht vorhanden
- einzige fehlende Serverdatei: `STW84V_Anschlussplan.png`

`Potenzialausgleich.jpg` fehlt zwar aktuell im lokalen Quellordner, liegt aber
noch aus einem früheren Upload im Server-`org`-Ordner.

Der in der Admin-Oberfläche verwendete Server-Verzeichnisbrowser zeigt
absichtlich nur Unterordner. Dateien werden in
`FileTransferService::browseServerDirectories()` mit `is_dir()` gefiltert.
Deshalb wird für den physisch mit Dateien gefüllten Ordner
`C:\xampp\htdocs\media\images\org` eine leere `directories`-Liste angezeigt.
Diese Anzeige bedeutet nicht, dass der Ordner keine Dateien enthält.

Der Zielordner liegt außerdem neben der API:

- korrekt: `C:\xampp\htdocs\media\images\org`
- nicht: `C:\xampp\htdocs\wela-api\media\images\org`

Originaldateien des letzten Laufs sind per HTTP erreichbar, beispielsweise:

- `/media/images/org/Zyklon.jpg` → HTTP 200
- `/media/images/org/Anschlussplan_stw1k_24_270.jpg` → HTTP 200

### Die Medienverknüpfung wurde nachgezogen

Die neue Media-Reparaturregel wurde zwischenzeitlich ausgeführt:

- Exportläufe `174` und `175` verarbeiteten zusammen 5.478 Medien erfolgreich.
- Mirror-Lauf `179` enthält 5.487 von 5.488 Stage-Bildverknüpfungen.
- Alle zehn Artikel mit einem zweiten Bild besitzen im Mirror zwei
  `xt_media_link(type=images)`-Zeilen mit `sort_order` 1 und 2.

Eine einzelne Media-Zeile ist noch als verzögerter Retry offen:

- Artikel: `AD-250-verz.`
- Media-ID: `afs-article-655-image_1`
- Datei: `AD_1.jpg`
- Ursache: temporäre Windows-Socket-Erschöpfung während des parallelen
  Massennachzugs
- Fehlermeldung:
  `Normalerweise darf jede Socketadresse ... nur jeweils einmal verwendet werden`

Dieser einzelne Retry erklärt nicht das allgemeine Anzeigeproblem der neu
hochgeladenen Bilder.

### Fehler in der XT-Bildgrößenerzeugung

Für ein neu hochgeladenes Zweitbild wurde physisch geprüft:

- Original `org/Anschlussplan_stw1k_24_270.jpg` → HTTP 200
- `thumb/Anschlussplan_stw1k_24_270.jpg` → HTTP 200
- `info/...` → HTTP 404
- `icon/...` → HTTP 404
- `ewevelationsinfo/...` → HTTP 404
- `ewevelationsthumb/...` → HTTP 404

Die aktive Tabelle `xt_image_type` erklärt dieses Verhalten:

- Für Klasse `product` existiert nur:
  - `thumb`, 200 × 200
- Die übrigen produktrelevanten Größen liegen unter Klasse `default`:
  - `info`
  - `icon`
  - `ewevelationsthumb`
  - `ewevelationsinfo`
  - `ewevelationsicon`
  - `ewevelationssidebar`

`wela-api/xt_image_helpers.php` lädt aktuell zuerst ausschließlich:

```sql
SELECT * FROM xt_image_type WHERE class = 'product'
```

Die `default`-Typen werden nur geladen, wenn für `product` überhaupt keine
Zeile vorhanden ist. Weil die einzelne Product-Zeile `thumb` existiert, werden
die Default-Größen nicht verarbeitet.

Dadurch entsteht der irreführende Zustand:

- Originaldatei wurde übertragen.
- `thumb` wurde erzeugt.
- API meldet `image_generation_verified = true`.
- Die vom Artikeldetail beziehungsweise Theme benötigten Default-Größen
  fehlen dennoch.

Der Upload selbst ist daher nicht defekt. Defekt beziehungsweise unvollständig
ist die Auswahl der XT-Bildtypen bei der Nachbearbeitung.

### Zusätzlicher Laufzeitbefund

Der geprüfte Shop-Frontendpfad antwortete während der Diagnose mit HTTP 503
und `Retry-After: 300`. Deshalb war eine vollständige visuelle Kontrolle der
Produktdetailseite in diesem Moment nicht möglich. Originaldateien und
erzeugte Bildgrößen konnten unabhängig davon direkt geprüft werden.

## Open points

- Der API-Helper muss für Produktbilder die Bildtypen aus `default` und
  `product` gemeinsam verarbeiten.
- Bei identischem Zielordner sollte die produktspezifische Definition Vorrang
  vor `default` haben.
- Die Erfolgsmeldung darf erst erfolgen, wenn alle zusammengeführten
  Zielgrößen physisch existieren.
- Nach Deployment des API-Fixes müssen die bereits als erfolgreich markierten
  425 Dateien erneut zur Bildgrößenerzeugung vorgemerkt werden.
- `Potenzialausgleich.jpg` und `STW84V_Anschlussplan.png` müssen zunächst im
  lokalen Bildpfad bereitgestellt werden, bevor eine vollständige
  Neugenerierung aller Dateien durchgeführt wird. Aktuell fehlt nur
  `STW84V_Anschlussplan.png` auch serverseitig.
- Der einzelne Media-Retry `afs-article-655-image_1` kann nach Ende der
  Retry-Verzögerung normal erneut verarbeitet werden.

## Validation steps

Erfolgreich read-only ausgeführt:

- letzte `image_upload`-Läufe und ihre Kontexte geprüft
- sämtliche Logs und Fehler des Laufs `183` geprüft
- aktuellen `images_file`-Status geprüft
- alle 427 Originaldateien parallel per HTTP gegen `/media/images/org/`
  abgeglichen
- Original- und Größen-URLs per HTTP abgefragt
- aktive Server-Bildordner über die Wela-API gelesen
- `xt_image_type` über die Wela-API gelesen und nach Klassen verglichen
- aktuellen Media-Queue-Retry geprüft
- Stage-/Mirror-Abdeckung und alle zehn Mehrfachbild-Artikel geprüft
- keine direkte XT-Datenbankverbindung verwendet
- keinen Upload und keinen Export ausgelöst

## Recommended next step

`wela-api/xt_image_helpers.php` so korrigieren, dass Produktbilder die
zusammengeführte Menge aus `default`- und `product`-Bildtypen erhalten.
Anschließend API deployen, die 425 vorhandenen Bilder erneut vormerken und den
Upload-/Regenerierungslauf wiederholen. Danach für ein neues Zweitbild
mindestens `org`, `thumb`, `info`, `icon` sowie die verwendeten
`ewevelations*`-Größen per HTTP und auf der Produktdetailseite prüfen.
