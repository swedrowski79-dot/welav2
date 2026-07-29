# Produktbeschreibungen weichen von Extra-Daten ab

## Task

Pruefen, warum Produktbeschreibungen im Onlineshop nicht den in
`afs_extras.article_translations` hinterlegten Beschreibungen entsprechen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `docs/agent-results/2026-04-21-product-description-techdata-combine.md`
- `docs/agent-results/2026-04-21-short-description-from-intro-text.md`
- `docs/agent-results/2026-07-23-delta-state-priority-fix.md`
- `docs/agent-results/2026-07-23-product-translation-text-export.md`
- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `config/delta.php`
- `config/xt_write.php`
- `src/Service/ImportWorkflow.php`
- `src/Service/MergeService.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `src/Service/ExportQueueWorker.php`
- `run_import_all.php`
- `run_merge.php`
- `run_expand.php`
- `run_delta.php`
- `run_export_queue.php`

## Changed files

- `docs/agent-results/2026-07-29-product-description-delta-diagnosis.md`

Code, Konfiguration, Datenbank, Export-State und Export Queue wurden nicht
veraendert.

## Summary

- Die Beschreibung wird aus der Extra-Tabelle korrekt nach RAW und Stage
  uebernommen:
  - `afs_extras.article_translations`: 21.984 Zeilen
  - keine fehlende Quellzeile in `raw_extra_article_translations`
  - keine Abweichung der Spalte `description` zwischen Extra und RAW
  - keine Abweichung der Spalte `description` zwischen RAW und Stage
  - keine doppelten Artikel-/Sprachkombinationen in RAW oder Stage
- Der Export bildet erwartungsgemaess
  `description + Leerzeile + technical_data_html` fuer
  `xt_products_description.products_description`.
- Im vor dem heutigen Export aktualisierten XT-Mirror weichen 351 Produkte
  je Sprache vom aktuellen Stage-Text ab.
- Von diesen 351 Produkten wurden im Lauf vom 29.07.2026 nur 6 neu
  eingereiht und exportiert. 345 abweichende Produkte wurden nicht
  eingereiht.
- Bei allen 351 Produkten entspricht
  `product_export_state.last_exported_hash` bereits dem aktuellen
  `stage_products.hash`. Die 345 uebersprungenen Produkte gelten dadurch bei
  weiteren Delta-Laeufen faelschlich als bereits exportiert.
- Ursache ist die Produktkonfiguration in `config/delta.php`: Der
  Mirror-Vergleich prueft nur Produkt-Stammdaten, aber nicht den bereits vom
  Service unterstuetzten `translation_hash`.
- Wenn die Stammdaten passen, liefert `mirrorDecision()` deshalb
  `matched = true`, obwohl der Shop-Text abweicht. Anschliessend schreibt
  `ProductDeltaService::run()` den aktuellen, inklusive Uebersetzungen
  gebildeten Payload-Hash in den Export-State. Damit wird der noch nicht
  exportierte Text als bestaetigt markiert.

## Open points

- Die Delta-Konfiguration muss mindestens den Produkt-
  `mirror_translation_hash_field` aktivieren. Die vorhandene Service-Logik
  kann diesen Vergleich bereits ausfuehren.
- Die 345 bereits falsch bestaetigten Produkt-States muessen gezielt
  repariert und mit den aktuellen Stage-Payloads erneut exportiert werden.
  Eine reine Konfigurationsaenderung reicht fuer diese bereits vergifteten
  States nicht aus.
- Nach dem Nachzug muss ein neuer XT-Mirror-Refresh erfolgen. Erst damit kann
  der tatsaechliche Shop-Endstand nach dem Export vollstaendig gegen Stage
  geprueft werden.
- Vier Stage-Produkte fehlen weiterhin vollstaendig im XT-Mirror; fuer je
  neun EN-/FR-/NL-Zeilen fehlt die Shop-Beschreibung. Das ist vom hier
  nachgewiesenen Delta-State-Fehler getrennt zu behandeln.

## Validation steps

Ausgefuehrte Pruefungen:

- Docker-Stack mit `docker compose ps` geprueft; MySQL und PHP laufen.
- Letzte Full Pipeline geprueft:
  - Import, Merge, XT-Mirror, Expand/Delta und Export Worker am 29.07.2026
    erfolgreich
  - Produkt-Queue: 469 neue Eintraege, alle Status `done`
- Extra, RAW und Stage per SQL auf Zeilenzahl, exakte Beschreibungen und
  doppelte Artikel-/Sprachschluessel verglichen.
- Erwarteten kombinierten Stage-Text per SQL gegen
  `xt_mirror_products_description.products_description` verglichen.
- Die abweichenden Produkte mit den am 29.07.2026 erzeugten Queue-Eintraegen
  und `product_export_state` abgeglichen:
  - 351 abweichend
  - 6 heute exportiert
  - 345 heute nicht exportiert
  - 351 bereits mit aktuellem Stage-Hash im Export-State
- Beispiel-Payloads aus der erledigten Queue geprueft; eingereihte Produkte
  enthalten die aktuellen Beschreibungen und technischen Daten aus Stage.

Nicht ausgefuehrt:

- kein Code- oder Konfigurationsfix
- kein State-Reset
- kein erneuter Delta-Lauf
- kein erneuter Produkt-Export
- kein XT-Mirror-Refresh nach dem heutigen Export

## Recommended next step

`mirror_translation_hash_field => translation_hash` fuer Produkte
aktivieren, die 345 falsch bestaetigten States gezielt wieder exportierbar
machen, Delta und Export Worker ausfuehren und danach den XT-Mirror erneut
gegen Extra/Stage pruefen.
