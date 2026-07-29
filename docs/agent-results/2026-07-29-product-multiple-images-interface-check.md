# Prüfung Artikel-Mehrfachbilder

## Task

Prüfen, ob die bestehende Stage-/Export-/Wela-API-Schnittstelle mehrere Bilder
pro Artikel unterstützt und ob diese Funktion auf dem aktiven Shopstand
tatsächlich arbeitet.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/normalize.php`
- `config/expand.php`
- `config/delta.php`
- `config/xt_write.php`
- `config/xt_mirror.php`
- `run_expand.php`
- `run_export_queue.php`
- `run_image_upload.php`
- `src/Service/ExpandService.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/WelaApiClient.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Web/Repository/ImageFileRepository.php`
- `wela-api/index.php`
- `wela-api/src/FileTransferService.php`
- `wela-api/xt_image_helpers.php`
- relevante frühere Medien-/Bild-Ergebnisberichte unter
  `docs/agent-results/`

## Changed files

- `config/delta.php`
- `src/Service/ProductDeltaService.php`
- `docs/agent-results/2026-07-29-product-multiple-images-interface-check.md`

Export-State und Shopdaten wurden bei der Prüfung und Implementierung nicht
verändert.

## Summary

### Technische Fähigkeit

Die Schnittstelle ist grundsätzlich für Mehrfachbilder ausgelegt:

- AFS-Felder `Bild1` bis `Bild10` werden als `image_1` bis `image_10`
  normalisiert.
- `ExpandService` erzeugt pro belegtem Bildslot eine eigene Zeile in
  `stage_product_media`.
- Jede Zeile erhält eine stabile `media_external_id`, beispielsweise
  `afs-article-64471-image_2`.
- Der Medien-Writer legt pro Bild eine eigene `xt_media`-Zeile an.
- Über `xt_media_link` wird jedes Bild mit `type = images` und eigener
  `sort_order` mit dem Produkt verbunden.
- Das erste Bild wird zusätzlich korrekt nach `xt_products.products_image`
  geschrieben.
- Der Datei-Upload berücksichtigt alle Dateinamen aus `stage_product_media`,
  nicht nur das Hauptbild, und lässt durch XT die benötigten Bildgrößen
  erzeugen.

Mehrere Bilder desselben Artikels sind damit vom Datenmodell und von der
Wela-API her möglich. Unterschiedliche `m_id`-Werte erlauben mehrere
`xt_media_link`-Zeilen für dasselbe Produkt.

### Aktueller Stage-Bestand

- 5.488 Bildzeilen für 5.478 Artikel.
- 5.478 Zeilen in Slot `image_1`.
- 10 Zeilen in Slot `image_2`.
- 10 Artikel besitzen aktuell mehr als ein Bild.
- Kein Artikel besitzt derzeit mehr als zwei Bilder.
- 397 unterschiedliche Produktbild-Dateien werden referenziert.

Die zehn Artikel mit einem zweiten Bild sind:

- `STW1K230V`
- `STW1K24V`
- `STW81V`
- `STW84V`
- `STWA1`
- `VENTURIE-000`
- `VENTURIE-ALU-44-50`
- `VENTURIE-ALU-55-63`
- `VENTURIE-SS-44-50`
- `VENTURIE-SS-55-63`

### Datei-Upload

Neun der zehn zweiten Bilder wurden bereits nach
`media/images/org` hochgeladen und durch die Shop-API bestätigt.

Die Datei `STW84V_Anschlussplan.png` für Artikel `STW84V` fehlt im lokalen
Bildpfad und wurde deshalb nicht hochgeladen.

### Aktueller Shopstand

Die aktive API meldet
`runtime_version = 2026-07-29-translated-seo-path-fix-2`.

Read-only über die Wela-API und den XT-Mirror geprüft:

- `xt_media`: 741 Zeilen, davon 11 mit `external_id`.
- `xt_media_link`: 3.043 Zeilen.
- Davon nur 9 Links mit `type = images`.
- Die übrigen 3.034 Links besitzen `type = media`.
- Nur 9 von 5.488 aktuellen Stage-Bildzeilen sind über ihre
  `media_external_id` als `xt_media` vorhanden und korrekt per
  `xt_media_link(type=images)` verbunden.
- Keiner der zehn Artikel mit zwei Bildern besitzt aktuell einen
  `type=images`-Media-Link.

Die Hauptbilder funktionieren unabhängig davon:

- 5.478 von 5.478 Hauptbildern stimmen zwischen
  `stage_product_media(position=1)` und `xt_products.products_image` überein.

Damit lautet der aktuelle Befund:

- **Hauptbilder funktionieren.**
- **Mehrfachbilder sind technisch unterstützt, werden auf dem aktuellen
  Shopstand aber noch nicht als zusätzliche Produktbilder angezeigt.**

### Ursache

`product_media_export_state` enthält 5.490 bestätigte Zustände:

- 9 Stage-Medien sind tatsächlich im Mirror vorhanden.
- 5.479 Stage-Medien fehlen im Mirror, gelten lokal aber trotzdem als
  exportbestätigt.
- 2 Zustände gehören zu inzwischen entfernten Stage-Zeilen.

Der aktuelle Delta-Code priorisiert einen unveränderten bestätigten Hash vor
einem fehlenden Mirror-Datensatz. Deshalb melden die letzten Delta-Läufe zwar:

- `mirror_matched = 9`
- `mirror_missing = 5479`

sie erzeugen aber:

- `changed = 0`
- `queue_created = 0`

Die vorhandene Wela-API- und Writer-Logik wird für diese Altbestände daher
nicht erneut aufgerufen. Die 9 vorhandenen Links belegen, dass der technische
Schreibpfad grundsätzlich funktioniert.

### Pipeline-Korrektur

Die Pipeline berücksichtigt fehlende Shop-Medien jetzt über die neue,
konfigurierbare Delta-Option:

```php
'mirror_repair_missing' => true,
```

Die Option ist ausschließlich für `media_export_queue` aktiviert.

Wenn eine aktuelle `stage_product_media`-Zeile im erfolgreichen XT-Mirror
fehlt, setzt `mirrorDecision()` nun:

- `repair = true`
- `repair_reason = missing`

`nextAction()` liefert dadurch auch bei einem bereits bestätigten,
unveränderten Export-Hash wieder `update`. Der nächste Delta-Lauf wird die
fehlenden Medien daher erneut in die Export-Queue stellen.

Produkte, Kategorien und Dokumente behalten ihr bisheriges Verhalten. Für das
Monitoring wird die Anzahl generischer Reparaturen zusätzlich als
`mirror_repairs` ausgegeben. Die bestehende Kennzahl `mirror_seo_repairs`
bleibt auf SEO-Reparaturen begrenzt.

## Open points

- Der erste reale Delta-Lauf mit der neuen Regel wird nach aktuellem
  Mirror-Stand ungefähr 5.479 Media-Updates einreihen. Dieser Lauf wurde in
  dieser Aufgabe noch nicht gestartet.
- Für `STW84V` muss zuerst `STW84V_Anschlussplan.png` im konfigurierten
  Bildpfad bereitgestellt und hochgeladen werden.
- Auch `Potenzialausgleich.jpg` fehlt im lokalen Bildpfad. Datenbanklinks
  können erst nach vorhandener Bilddatei vollständig sichtbar funktionieren.
- Nach erfolgreichem Export muss ein XT-Mirror-Refresh erfolgen. Ohne
  aktualisierten Mirror würde ein späterer Delta-Lauf dieselben Medien
  weiterhin als fehlend bewerten.

## Validation steps

Erfolgreich read-only ausgeführt:

- Datenfluss `Bild1..Bild10 -> stage_product_media -> media queue ->
  XtMediaDocumentWriter -> xt_media/xt_media_link` vollständig verfolgt.
- Stage-Verteilung nach Bildslot und Artikel geprüft.
- Uploadtracking in `images_file` für alle zweiten Bilder geprüft.
- `xt_products.products_image` mit allen Stage-Hauptbildern verglichen.
- `xt_media` und `xt_media_link` direkt über die aktive Wela-API gelesen.
- XT-Mirror zusätzlich über `external_id` sowie Artikel + Dateiname geprüft.
- Medien-Export-State, Queue-Status und aktuelle Delta-Kontexte geprüft.
- Keine direkte Verbindung zur XT-Datenbank verwendet.
- Keine Exportbestätigung zurückgesetzt und keine Shopdaten geschrieben.
- `php -l config/delta.php`
- `php -l src/Service/ProductDeltaService.php`
- `git diff --check` für die geänderten PHP-Dateien
- isolierter Reflection-Test der Delta-Entscheidung:
  - fehlendes Medium + gleicher State-Hash ergibt `repair = true`
  - Reparaturgrund ist `missing`
  - nächste Aktion ist `update`
  - ein fehlendes Produkt ohne aktivierte Option bleibt unverändert und
    erzeugt keine Aktion

## Recommended next step

Vor dem ersten Nachzug einen aktuellen XT-Mirror erstellen. Danach Delta und
Export bewusst mit dem erwarteten einmaligen Bestand von ungefähr 5.479
Medien ausführen, anschließend den Mirror erneut aktualisieren und kontrollieren:

- `mirror_missing = 0`
- keine offenen Media-Queue-Fehler
- für `STW1K230V` zwei `type=images`-Links mit `sort_order` 1 und 2
- beide Bilder im Shop sichtbar
