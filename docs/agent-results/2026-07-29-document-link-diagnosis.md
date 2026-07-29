# Task

Dokument-Verknuepfungen zwischen Artikeln und XT-Shop pruefen, insbesondere:

- fehlende bzw. nicht gesetzte Verknuepfungen
- nicht entfernte Verknuepfungen, nachdem ein Dokument aus der Quelle geloescht wurde
- Abgleich mit dem funktionierenden Bild-Verknuepfungsweg

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/delta.php`
- `config/xt_write.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Service/XtSnapshotService.php`
- `docs/agent-results/2026-04-22-document-duplicate-links-fix.md`
- Git-Historie zu `config/xt_write.php` und `src/Service/XtMediaDocumentWriter.php`
- aktuelle, lokale Mirror-, Stage-, State-, Queue- und Monitoring-Daten

# Changed files

- `docs/agent-results/2026-07-29-document-link-diagnosis.md`

Produktionscode wurde bei dieser Diagnose nicht geaendert.

# Summary

Der Fehler ist bestaetigt. Setzen, Erkennen und Entfernen der Dokument-Verknuepfungen sind derzeit nicht konsistent.

## 1. Writer und Mirror verwenden unterschiedliche Link-Typen

Der Writer legt Dokument-Verknuepfungen in `xt_media_link` mit
`type = 'media'` an (`config/xt_write.php`).

Beide Mirror-Leser erwarten dagegen `type = 'files'`:

- `ProductDeltaService::fetchDocumentMirrorRows()`
- `XtSnapshotService::buildMediaAndDocumentSnapshots()`

Diese Abweichung entstand mit Commit `36a7045` vom 22.04.2026. Dort wurde der
Writer von `files` auf `media` umgestellt; die lesenden Stellen wurden nicht
mit umgestellt.

Auswirkung:

- Der aktuelle XT-Mirror enthaelt 2.925 reale Dokument-Links mit
  `link.type = 'media'` und `media.type = 'files'`.
- Der Snapshot weist trotzdem `documents = 0` aus.
- 3.034 Links werden beim Snapshot als nicht unterstuetzt gezaehlt.
- Der Dokument-Delta meldet alle 2.956 Stage-Dokumente als
  `mirror_missing`.

## 2. Fehlende Links werden trotz erkanntem Fehlen nicht repariert

Fuer Bilder ist in `config/delta.php` `mirror_repair_missing = true` gesetzt.
Bei Dokumenten fehlt diese Option.

Der Lauf vom 29.07.2026 zeigt deshalb:

- `processed = 2956`
- `mirror_missing = 2956`
- `mirror_repairs = 0`
- `queue_created = 0`

Obwohl der Delta alle Dokumente als fehlend erkennt, erzeugt er bei bereits
passendem State-Hash keinen neuen Exportauftrag.

Der direkte Bestandsabgleich ueber Artikel-ID und Dateiname ergab:

- 2.956 erwartete Dokument-Verknuepfungen in der Stage
- 2.506 davon im aktuellen Shop-Bestand vorhanden
- 450 aktuelle Verknuepfungen fehlen im Shop
- 2.925 Dokument-Links sind im Shop vorhanden
- 419 Shop-Links haben keine aktuelle Entsprechung mehr in der Stage

## 3. Loeschungen werden strukturell uebersprungen

Wenn ein Dokument nicht mehr in `stage_product_documents` vorkommt, prueft der
Delta, ob es noch im Mirror vorhanden ist. Fehlt der Mirror-Eintrag, wird der
State direkt als entfernt markiert und kein Delete-Auftrag erzeugt.

Da `fetchDocumentMirrorRows()`:

- den falschen Link-Typ `files` filtert und
- per `INNER JOIN` zwingend einen noch vorhandenen Stage-Eintrag verlangt,

kann ein geloeschtes Dokument dort prinzipbedingt nicht gefunden werden.
Die Entfernung wird daher als bereits erledigt behandelt, obwohl der
Shop-Link weiter existiert.

Zusaetzlich ist die nachtraegliche Mirror-Loeschsuche im Delta aktuell nur fuer
Produkte und Kategorien aktiviert, nicht fuer Dokumente.

## 4. Der Writer kann historische Links nicht ueber die Dokument-ID loeschen

Der Delete-Writer sucht die zugehoerige `xt_media`-Zeile ueber
`external_id = afs_document_id`.

Im aktuellen Shop-Bestand haben alle 2.925 historischen Dokument-Links jedoch
eine Media-Zeile mit leerer `external_id`. Ohne gefundene Media-ID beendet der
Writer die Loeschung, ohne einen API-Delete auszufuehren.

Fuer einen stabilen kuenftigen Betrieb muessen die aktuellen Dokumente daher
einmal ueber den heutigen Writer auf Media-Zeilen mit `external_id` migriert
und die historischen Links gezielt bereinigt werden.

## 5. Positionswerte sind im Altbestand ebenfalls nicht synchronisiert

`stage_product_documents.position` und `sort_order` sind fuer alle 2.956
aktuellen Zeilen identisch. Im Shop ist `xt_media_link.sort_order` bei allen
2.925 historischen Dokument-Links dagegen `NULL`.

Der aktuelle Writer wuerde die Position setzen, wird wegen des fehlenden
Mirror-Reparaturmodus aber nicht erneut aufgerufen.

# Open points

- Es wurde noch kein Fix implementiert, weil die Anforderung in diesem Schritt
  eine Pruefung war.
- Die 419 veralteten historischen Links koennen nicht allein ueber eine
  `afs_document_id` identifiziert werden, da ihre `external_id` leer ist. Sie
  brauchen eine einmalige, kontrollierte Bereinigung anhand der Stage als
  interner Wahrheit.
- `document_type` aus AFS (`NULL`, `1`, `2`) darf nicht direkt mit dem technischen
  XT-Medientyp `files` verglichen werden. Der Mirror-Vergleich muss diesen Wert
  normalisieren oder aus den Vergleichsfeldern entfernen.
- Ein Zurueckstellen des Writer-Link-Typs auf `files` ist nicht als sicherer Fix
  anzusehen. Der Shop-Bestand und die Aenderungshistorie sprechen dafuer, dass
  XT die Dokument-Verknuepfung als `media` erwartet. Die lesenden Stellen
  sollten dem Writer folgen.

# Validation steps

Tatsaechlich ausgefuehrt:

- PHP-/Konfigurationsfluss fuer Dokument-Insert und Dokument-Delete gelesen
- Bild- und Dokument-Delta-Konfiguration miteinander verglichen
- aktuelle Mirror-Verteilung nach `link.type`, `media.type` und `class` geprueft
- Stage-/Shop-Abgleich ueber Artikel-ID und Dateiname ausgefuehrt
- Media-`external_id` der aktuellen Dokument-Links geprueft
- Stage- und Shop-Sortierwerte verglichen
- aktuelle `sync_logs` fuer XT-Mirror und Dokument-Delta ausgewertet
- Git-Historie der Umstellung von `files` auf `media` geprueft

Nicht ausgefuehrt:

- kein Delta-Lauf, weil dieser Datenbank-State bzw. Queue-Eintraege schreiben
  wuerde
- kein Export-Worker und kein API-Delete
- keine Aenderung am Live-Shop

# Recommended next step

Den Fix als zusammenhaengende, inkrementelle Aenderung umsetzen:

1. Dokument-Mirror auf `link.type = 'media'` plus `media.type = 'files'`
   ausrichten und die Abhaengigkeit von einer aktuellen Stage-Zeile entfernen.
2. `XtSnapshotService` dieselbe Dokument-Erkennung beibringen.
3. Fuer Dokumente `mirror_repair_missing` aktivieren und die Mirror-Vergleichsfelder
   auf die tatsaechlich geschriebenen XT-Werte normalisieren.
4. Einen kontrollierten Reexport der 2.956 aktuellen Dokumente ausfuehren, damit
   Media-`external_id`, Link und Position konsistent werden.
5. Danach die 419 historischen, nicht mehr in der Stage vorhandenen Links
   gezielt und protokolliert entfernen.
6. Mirror erneut laden und pruefen:
   - erwartete Dokumente = vorhandene Dokumente
   - keine ungewollten veralteten Links
   - geloeschtes Testdokument erzeugt genau einen Delete-Auftrag und der
     Artikel-Link verschwindet im Shop
