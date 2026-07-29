# Task

Dokumente im XT-Shop mit einer stabilen External-ID versehen und die
Artikel-Dokument-Verknuepfung so korrigieren, dass:

- aktuelle Dokumente sicher gesetzt und repariert werden
- geloeschte Dokumente einen Delete-Auftrag erzeugen
- historische, nicht mehr erwartete Artikelverknuepfungen entfernt werden
- Mirror und Delta denselben XT-Dokumenttyp verwenden

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/delta.php`
- `config/xt_write.php`
- `config/xt_mirror.php`
- `config/pipeline.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Service/XtSnapshotService.php`
- `run_delta.php`
- `run_export_queue.php`
- `run_full_pipeline.php`
- `run_xt_mirror.php`
- `wela-api/index.php`
- `docs/agent-results/2026-04-22-document-duplicate-links-fix.md`
- `docs/agent-results/2026-07-29-document-link-diagnosis.md`
- aktuelle Stage-, Mirror-, Queue-, State- und Monitoring-Daten

# Changed files

- `config/delta.php`
- `config/xt_write.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Service/XtSnapshotService.php`
- `run_export_queue.php`
- `docs/agent-results/2026-07-29-document-external-id-link-fix.md`

# Summary

## External-ID und Relation

Der bestehende Writer schreibt jetzt im reparierten Gesamtfluss fuer jedes
Dokument:

- `xt_media.external_id = afs_document_id`
- `xt_media.type = files`
- `xt_media_link.type = media`
- `xt_media_link.m_id = xt_media.id`
- `xt_media_link.link_id = xt_products.products_id`
- `xt_media_link.sort_order = stage_product_documents.position`

Alle 2.956 aktuellen Stage-Dokumente wurden kontrolliert neu exportiert.
Der Export-Worker-Lauf `188` endete mit:

- `done = 2956`
- `error = 0`
- `retried = 0`
- `permanent_error = 0`

## Mirror- und Delta-Korrektur

Der Dokument-Mirror liest Dokumente jetzt unabhaengig von einer noch
vorhandenen Stage-Zeile ueber:

- `link.type IN ('media', 'files')`
- `media.type = 'files'`
- `media.external_id`

Dadurch kann ein aus der Stage geloeschtes Dokument weiterhin im Mirror
erkannt und als Entfernung in die Queue geschrieben werden.

Fuer Dokumente sind jetzt sowohl fehlende als auch abweichende
Mirror-Zustaende reparierbar:

- `mirror_repair_missing = true`
- `mirror_repair_mismatched = true`

Der nicht vergleichbare AFS-Wert `document_type` (`NULL`, `1`, `2`) wurde aus
dem Vergleich mit dem technischen XT-Typ `files` entfernt.

Die nachtraegliche Mirror-Loeschsuche gilt jetzt neben Produkten und
Kategorien auch fuer Dokumente.

## Snapshot-Korrektur

`XtSnapshotService` erkennt Dokumente jetzt an der Kombination aus
Dokument-Linktyp und `media.type = files`. Vor dem Reexport wurden die 2.925
Altlinks dadurch korrekt als Dokumente ohne External-ID ausgewiesen, statt
als nicht unterstuetzte Links verworfen zu werden.

Nach dem Reexport meldet Mirror-Lauf `189`:

- `documents = 2956` erkannte Dokumente
- `documents_without_external_id = 0`
- `documents_without_product_mapping = 0`
- `unsupported_media_links = 0`

## Historische Links

Der Dokument-Worker gleicht nach jedem erfolgreichen Dokument-Batch die
Dokumentlinks ueber Artikel-ID und Dateiname gegen
`stage_product_documents` ab.

Beim ersten Reparatur-Batch wurden genau die zuvor diagnostizierten 419
veralteten Links entfernt. Die aktuellen 2.956 Links wurden beibehalten.

Das Loeschen eines Dokumentlinks unterstuetzt sowohl den aktuellen Linktyp
`media` als auch den historischen Linktyp `files`.

## Gezielter Worker

`run_export_queue.php` unterstuetzt jetzt optional:

```bash
php run_export_queue.php 500 --entity=document
```

Der Reparaturlauf wurde zusaetzlich mit `--child` als genau ein Worker
ausgefuehrt. Dadurch wurden ausschliesslich Dokument-Queue-Eintraege
verarbeitet.

# Open points

- Die alten, jetzt unverknuepften `xt_media`-Zeilen ohne External-ID wurden
  bewusst nicht geloescht. Nur ihre Artikelrelationen wurden entfernt. Damit
  bleiben Dateimetadaten und physische Dokumentdateien unangetastet.
- Die historischen Tabellen `xt_products_snapshot`, `xt_categories_snapshot`,
  `xt_media_snapshot` und `xt_documents_snapshot` werden vom aktuellen
  `XtSnapshotService` nicht befuellt und sind alle leer. Der produktive
  Delta-Abgleich verwendet die erfolgreich aktualisierten `xt_mirror_*`-
  Tabellen; der Dokument-Fix ist davon nicht beeintraechtigt.
- Beim Linkabgleich wurden 109 allgemeine Media-Link-Zeilen ohne vollstaendige
  Media- oder Produktzuordnung uebersprungen. Sie gehoeren laut abschliessendem
  Snapshot nicht zu den aktuellen Dokument-Verknuepfungen.
- Ein bereits vor diesem Task vorhandener, ausstehender Media-Queue-Eintrag
  blieb unangetastet.
- Der Live-Delete eines echten Dokuments wurde nicht absichtlich ausgeloest.
  Die Delta-Erzeugung fuer diesen Fall wurde transaktional und mit
  vollstaendigem Rollback validiert.

# Validation steps

Tatsaechlich ausgefuehrt:

- PHP-Syntaxpruefung:
  - `config/delta.php`
  - `config/xt_write.php`
  - `src/Service/ProductDeltaService.php`
  - `src/Service/XtSnapshotService.php`
  - `src/Service/XtMediaDocumentWriter.php`
  - `run_export_queue.php`
- `git diff --check` fuer alle geaenderten Produktionsdateien
- XT-Mirror vor dem Export:
  - 2.925 Dokumentlinks erkannt
  - 2.925 ohne External-ID
  - 0 als unsupported verworfen
- Delta vor dem Export:
  - 2.956 Dokumente verarbeitet
  - 2.956 Mirror-Reparaturen
  - 2.956 eindeutige Dokument-Queue-Eintraege
- isolierter Dokument-Export:
  - Lauf `188`
  - 2.956 erfolgreich
  - 0 Fehler
  - 419 veraltete Links entfernt
- XT-Mirror nach dem Export:
  - Lauf `189`
  - 2.956 Dokumente aus den produktiven `xt_mirror_*`-Tabellen erkannt
  - 2.956 Links mit External-ID
  - 0 Links ohne External-ID
- direkter Stage-/Mirror-Abgleich:
  - `stage_documents = 2956`
  - `exact_links = 2956`
  - `missing_links = 0`
  - `stale_links = 0`
  - keine doppelten External-IDs gefunden
  - keine doppelten Dokumentrelationen gefunden
- gezielter Dokument-Delta nach dem Export:
  - `mirror_matched = 2956`
  - `mirror_missing = 0`
  - `mirror_mismatched = 0`
  - `queue_created = 0`
- transaktionaler Loeschtest mit Dokument `1136`:
  - Stage-Zeile innerhalb einer Transaktion entfernt
  - genau ein Queue-Auftrag mit `deleted = 1` erzeugt
  - Transaktion zurueckgerollt
  - Stage-Zeile danach weiterhin vorhanden
  - kein Test-Queue-Auftrag verblieben

# Recommended next step

Bei der naechsten echten AFS-Dokumentloeschung nach dem regulaeren
Full-Pipeline-Lauf im Shop kontrollieren, dass der Dokumentreiter die
Verknuepfung nicht mehr zeigt. Der Delta- und Writer-Pfad ist dafuer jetzt
vorbereitet und die bestehenden Dokumente besitzen die erforderliche
External-ID.
