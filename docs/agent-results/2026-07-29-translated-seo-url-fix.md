# Korrektur übersetzter SEO-URLs

## Task

Die SEO-URLs der übersetzten Warengruppen und Artikel dauerhaft korrigieren,
fehlende Sprachzeilen reparieren und die betroffenen Shopdaten in der richtigen
Reihenfolge neu exportieren.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/delta.php`
- `config/languages.php`
- `config/sources.php`
- `config/xt_write.php`
- `run_delta.php`
- `run_export_queue.php`
- `run_xt_mirror.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/StageCategoryMap.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/XtProductWriter.php`
- `wela-api/index.php`
- `wela-api/seo_helpers.php`
- `wela-api/src/CategorySyncService.php`
- `wela-api/src/ProductSyncService.php`
- die direkt relevanten SEO-Ergebnisberichte unter `docs/agent-results/`

## Changed files

- `config/delta.php`
- `src/Service/ProductDeltaService.php`
- `wela-api/index.php`
- `wela-api/src/CategorySyncService.php`
- `docs/agent-results/2026-07-29-translated-seo-url-fix.md`

Die parallel vorhandenen Änderungen an Import, Merge, Kategorieübersetzungen,
Schema und Weboberfläche wurden nicht verändert.

## Summary

### Dauerhafte Delta-Korrektur

Für Kategorien und Produkte ist jetzt eine konfigurierbare
`seo_path_dependency` aktiv.

Der Delta-Hash enthält dadurch:

- bei Kategorien den vollständigen übersetzten Pfad einschließlich aller
  Vorfahren,
- bei Produkten die vom Writer tatsächlich aufgelöste Master-Kategorie und
  deren vollständigen übersetzten Pfad.

Ändert sich künftig ein übersetzter Kategoriename oder die Hierarchie, werden
dadurch automatisch auch abhängige Unterkategorien und Produkte neu exportiert.

Zusätzlich prüft das Delta die vier erforderlichen SEO-Sprachzeilen `de`, `en`,
`fr` und `nl`. Fehlende oder leere Zeilen lösen jetzt einen kontrollierten
Reparatur-Export aus. Das Monitoring weist diese Fälle separat als
`mirror_seo_repairs` aus.

Eine bestehende Mirror-Adoption wurde ebenfalls abgesichert: Ein neuer
SEO-Pfad-State darf nicht allein aufgrund übereinstimmender Stammdaten als
exportbestätigt übernommen werden.

### Validierung der Kategorieauflösung

Read-only gegen die aktuelle Stage geprüft:

- 71 von 71 Kategorien besitzen einen Pfad-Abhängigkeitshash.
- 5.499 von 5.499 Produkten besitzen einen Pfad-Abhängigkeitshash.
- Die aufgelöste Kategorie des neuen Delta-Codes wurde für alle 5.499 Produkte
  mit `XtProductWriter::resolvedCategoryId()` verglichen.
- Abweichungen: `0`.

### Ausgeführter Reparaturexport

Die Exportbestätigung wurde gezielt für eine Kategorie und 370 Produkte
zurückgesetzt, die im ersten Delta-Lauf noch fälschlich als bereits bestätigt
übernommen worden waren. Der Reset ist durch den anschließenden erfolgreichen
Export vollständig wieder bestätigt worden.

Der eigentliche Shopexport lief bewusst mit einem einzelnen Worker:

- Lauf `137`: 5.199 Reparatur-Updates erzeugt.
- Lauf `138`: weitere 371 Reparatur-Updates erzeugt.
- Lauf `139`: 71 Kategorien und 5.499 Produkte exportiert.
- Ergebnis Lauf `139`: 5.570 erfolgreich, 0 Fehler, 0 Retries.
- Lauf `140`: XT-Mirror erfolgreich aktualisiert.

Danach existieren für alle 71 aktiven Kategorien vier nicht leere SEO-Zeilen:

| Sprache | aktive Kategorien | fehlende SEO-Zeilen |
|---|---:|---:|
| de | 71 | 0 |
| en | 71 | 0 |
| fr | 71 | 0 |
| nl | 71 | 0 |

Alle vorhandenen Kategorie- und Produkt-SEO-Zeilen haben einen passenden
`url_md5 = MD5(url_text)`-Wert und eindeutige `url_text`-Werte innerhalb der
geprüften Sprachmenge.

`HA-000` besitzt nach dem Produkt-Reexport übersetzte Produktpfade, zum Beispiel:

- `en/extraction-arms-accessories/extraction-arm/econ-extraction-arm-with-internal-support-structure`
- `fr/bras-daspiration-accessoires/bras-daspiration/bras-daspiration-econ-avec-structure-porteuse-interne`

### Bestätigte Ursache der alten Kategorie-URLs

Der API-Stand `2026-07-29-translated-seo-path-fix-1` wurde anschließend
serverseitig eingespielt und sowohl über die Health-Antwort als auch durch
bytegleichen Dateivergleich mit der lokalen Vorlage bestätigt.

Ein erneuter Einzeltest für Kategorie `272` erzeugte die korrekten neuen Pfade.
Die direkte Leseabfrage über die Wela-API zeigte jedoch acht aktive
Kategorie-SEO-Zeilen statt vier: pro Sprache standen alte und neue URL
gleichzeitig in `xt_seo_url`. Der Mirror übernahm dadurch teilweise weiterhin
die alte Zeile. Beispielsweise existierten gleichzeitig:

- alt: `en/extraction-arm`
- neu: `en/extraction-arms-accessories/extraction-arm`

Ursache ist die physische Identität von `xt_seo_url`: Das generische Upsert der
Kategorie-Synchronisation kann eine vorhandene logische
`link_type/link_id/language_code/store_id`-Zeile nicht zuverlässig ersetzen,
wenn sich URL und `url_md5` ändern. Die Produktsynchronisation enthielt dafür
bereits eine gezielte Bereinigung.

`CategorySyncService` verwendet jetzt dieselbe Schutzlogik:

- alle vorhandenen Zeilen derselben logischen SEO-Identität werden gelesen,
- alte URLs werden vor dem Ersetzen als Redirect auf die neue kanonische URL
  vorgemerkt,
- doppelte beziehungsweise veraltete aktive Zeilen werden entfernt,
- anschließend wird genau die neue kanonische Zeile geschrieben.

Die lokale Laufzeitversion wurde für diese Ergänzung auf
`2026-07-29-translated-seo-path-fix-2` angehoben.

### Deployment und Reparaturläufe

Die beiden geänderten API-Dateien wurden serverseitig übernommen.

- Die Health-Antwort bestätigt
  `runtime_version = 2026-07-29-translated-seo-path-fix-2`.
- `wela-api/index.php` ist bytegleich mit der aktiven Datei.
- `wela-api/src/CategorySyncService.php` ist bytegleich mit der aktiven Datei.

Der anschließende Einzeltest für Kategorie `272` war erfolgreich. Im Shop
verbleiben genau vier aktive SEO-Zeilen:

- `de/absaugarme-zubehoer/absaugarm`
- `en/extraction-arms-accessories/extraction-arm`
- `fr/bras-daspiration-accessoires/bras-daspiration`
- `nl/afzuigarmen-toebehoren/afzuigarm`

Danach wurden zunächst die zwölf noch sichtbar falschen Kategoriepfade und
anschließend alle 71 aktiven Kategorien einmal hierarchisch neu exportiert.
Damit wurden auch 15 ältere deutsche Doppelzeilen bereinigt.

- Lauf `155`: 71 von 71 Kategorien erfolgreich, 0 Fehler.
- Lauf `156`: XT-Mirror erfolgreich aktualisiert.
- Ergebnis: 0 Kategoriepfade mit falschem Elternpräfix.
- Ergebnis: exakt 284 aktive SEO-Zeilen für 71 Kategorien × 4 Sprachen.
- Ergebnis: 0 fehlende, doppelte oder MD5-fehlerhafte Kategorie-SEO-Zeilen.

Auf Basis der korrigierten Kategoriepfade wurden 1.213 tatsächlich betroffene
Artikel ermittelt und gezielt neu exportiert.

- Lauf `158`: 1.213 von 1.213 Artikeln erfolgreich, 0 Fehler, 0 Retries.
- Lauf `159`: XT-Mirror erfolgreich aktualisiert.
- 5.499 aktive Artikel besitzen exakt 21.996 SEO-Zeilen.
- Pro Artikel existiert genau eine Zeile je Sprache `de`, `en`, `fr`, `nl`.
- Es gibt 0 doppelte Produkt-URLs und 0 falsche MD5-Werte.
- Bei allen vorhandenen Kategorie-SEO-Zeilen gibt es in allen vier Sprachen
  0 falsche Produktpräfixe.

`HA-000` besitzt jetzt folgende vier vollständige Pfade:

- `de/absaugarme-zubehoer/absaugarm/absaugarm-econ-innenliegender-stuetzkonstruktion`
- `en/extraction-arms-accessories/extraction-arm/econ-extraction-arm-with-internal-support-structure`
- `fr/bras-daspiration-accessoires/bras-daspiration/bras-daspiration-econ-avec-structure-porteuse-interne`
- `nl/afzuigarmen-toebehoren/afzuigarm/econ-afzuigarm-met-inwendige-draagconstructie`

Der abschließende Delta-Lauf `160` meldete:

- `changed = 0`
- `errors = 0`
- keine Queue-Einträge in `pending`, `processing` oder `error`

## Open points

- 76 aktive Artikel verweisen auf 13 XT-Kategorien mit den externen IDs
  `6`, `12`, `14`, `19`, `34`, `54`, `58`, `65`, `79`, `168`, `194`, `230`
  und `334`, die nicht in `stage_categories` vorhanden sind.
- Diese externen Kategorien besitzen deutsche SEO-Zeilen, aber keine
  Kategorie-SEO-Zeilen für `en`, `fr` und `nl`. Ohne Quelldatensatz können ihre
  übersetzten Namen und Hierarchien nicht durch den Sync erzeugt werden.
- Sieben Artikel haben zusätzlich zu ihrer gültigen Kategorie `278` noch eine
  alte Relation zur Kategorie-ID `0`. Die Produkt-SEO-Zeilen selbst sind
  vollständig und eindeutig; die ungültige Zusatzrelation ist ein separater
  Bereinigungspunkt.

## Validation steps

Erfolgreich ausgeführt:

- `php -l config/delta.php`
- `php -l src/Service/ProductDeltaService.php`
- `php -l wela-api/index.php`
- `php -l wela-api/src/CategorySyncService.php`
- `git diff --check` für alle geänderten PHP-/Konfigurationsdateien
- vollständiger Dependency-Vergleich für 71 Kategorien und 5.499 Produkte
- kontrollierter Ein-Worker-Export, Kategorien vor Produkten
- Einzeltest der Kategorie `272` einschließlich direkter Wela-API-Leseprüfung
- XT-Mirror-Refresh nach jedem Reparaturblock
- Prüfung aller SEO-Sprachzeilen, URL-Präfixe, Pfade, Eindeutigkeit und MD5-Werte
- direkter Shopabgleich: 284 Kategorie- und 21.996 Produkt-SEO-Zeilen
- abschließender Delta-Lauf `160`: `changed = 0`, `errors = 0`
- keine direkte XT-Datenbankverbindung verwendet

## Recommended next step

Die 13 fehlenden Kategorien beziehungsweise die Zuordnung der 76 betroffenen
Artikel in der AFS-/Extras-Quelle klären. Erst nach einer eindeutigen
Quellzuordnung sollten diese Kategorien importiert und die abhängigen Artikel
erneut exportiert werden. Die sieben zusätzlichen Kategorie-ID-0-Relationen
separat mit einem fokussierten Produktrelations-Fix bereinigen.
