# Produktbeschreibungen dauerhaft korrigiert

## Task

Den fehlerhaften Produkt-Delta-Abgleich fuer Uebersetzungen korrigieren,
bereits falsch bestaetigte Produkttexte kontrolliert neu exportieren und
`HA-000` sowie den Gesamtbestand gegen den aktuellen XT-Shopbestand
validieren.

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
- `docs/agent-results/2026-07-29-product-description-delta-diagnosis.md`
- `docs/agent-results/2026-07-29-ha-000-description-check.md`
- `config/delta.php`
- `config/merge.php`
- `config/xt_write.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtSnapshotService.php`
- `src/Service/ExportQueueWorker.php`
- `run_delta.php`
- `run_export_queue.php`
- `run_xt_mirror.php`

## Changed files

- `config/delta.php`
- `docs/agent-results/2026-07-29-product-description-delta-fix.md`

Bereits vorhandene, parallele Aenderungen an Kategorie-Uebersetzungen,
Schema, Migrationen und Bootstrap-Code wurden nicht veraendert.

## Summary

- Fuer `product_export_queue` wurde der vorhandene, vom
  `ProductDeltaService` bereits unterstuetzte Mirror-Abgleich wieder
  aktiviert:
  - `mirror_translation_hash_field => translation_hash`
- Vor der Recovery wurden die eingebauten Stage- und Mirror-Hashfunktionen
  direkt auf den aktuellen Bestand angewendet:
  - 5.155 passende Produkte
  - 345 abweichende Produkte
  - 0 Produkte ohne Mirror-Translation
- Nur bei diesen 345 nachgewiesen abweichenden Produkten wurde
  `product_export_state.last_exported_hash` innerhalb einer Transaktion auf
  `NULL` gesetzt. Die Aktualisierung war zusaetzlich darauf beschraenkt,
  dass der bisherige State-Hash dem aktuellen Stage-Hash entsprach.
- Der anschliessende regulaere Delta-Lauf `110` erzeugte exakt:
  - 345 Produkt-Updates
  - 0 weitere Queue-Eintraege fuer Kategorien, Medien oder Dokumente
  - 0 Fehler
- Der regulaere Batch-Export-Worker verarbeitete:
  - 345 von 345 Produkt-Updates erfolgreich
  - 0 Retries
  - 0 permanente Fehler
- `HA-000` (`afs_artikel_id = 51624`) erhielt Queue-Eintrag `50681`.
  Dieser wurde mit einem Versuch erfolgreich verarbeitet.
- Nach dem Export wurde der XT-Mirror mit Lauf `113` erfolgreich neu
  eingelesen.
- Der abschliessende Gesamtvergleich ergab:
  - 5.500 passende Produkt-Translation-Hashes
  - 0 abweichende Translation-Hashes
  - 0 fehlende Translation-Hashes
- Fuer `HA-000` stimmen Lang- und Kurzbeschreibung nun in allen vier
  Sprachen exakt zwischen Stage und XT-Mirror:

| Sprache | Langbeschreibung Stage/XT | Kurzbeschreibung Stage/XT |
|---|---:|---:|
| de | 2.470 / 2.470 Zeichen | 406 / 406 Zeichen |
| en | 2.382 / 2.382 Zeichen | 383 / 383 Zeichen |
| fr | 2.423 / 2.423 Zeichen | 384 / 384 Zeichen |
| nl | 2.381 / 2.381 Zeichen | 387 / 387 Zeichen |

- Ein zweiter Delta-Lauf `114` nach dem Mirror-Refresh erzeugte:
  - 0 Produkt-Updates
  - 0 Queue-Eintraege
  - 0 Fehler

Damit ist sowohl der aktuelle Datenbestand korrigiert als auch die
dauerhafte Erkennung kuenftiger Beschreibungsaenderungen wieder aktiv.

## Open points

- Der Produkt-Mirror meldet bei vielen Produkten weiterhin Abweichungen in
  mindestens einem der separat verglichenen Produkt-Stammdatenfelder. Wegen
  identischer Export-State-Hashes erzeugt dies aktuell keine Queue-Eintraege
  und betrifft den jetzt vollstaendig passenden Translation-Stand nicht.
  Dieser Stammdatenvergleich kann getrennt untersucht werden.
- Es existiert keine formale automatisierte Testsuite im Repository.

## Validation steps

Ausgefuehrt:

- `docker compose exec -T php php -l /app/config/delta.php`
- Stage-Translation-Hash gegen XT-Mirror-Translation-Hash vor der Recovery
- gezielter, transaktionaler State-Reset fuer genau 345 Abweichungen
- `docker compose exec -T php php run_delta.php`
  - Lauf `110`: 345 Produkt-Updates, 0 Fehler
- `docker compose exec -T php php run_export_queue.php`
  - Lauf `111`: 345 Produkt-Updates erfolgreich
- `docker compose exec -T php php run_xt_mirror.php`
  - Lauf `113`: erfolgreich
- Gesamtvergleich nach Mirror-Refresh:
  - 5.500 passend
  - 0 abweichend
  - 0 fehlend
- Bytegenauer Vergleich von Lang- und Kurzbeschreibung fuer `HA-000` in
  `de`, `en`, `fr`, `nl`
- erneuter `docker compose exec -T php php run_delta.php`
  - Lauf `114`: 0 Aenderungen, 0 Fehler

## Recommended next step

Beim naechsten regulaeren Full-Pipeline-Lauf die Produkt-Delta-Zahlen
beobachten. Neue Aenderungen an `description`, `technical_data_html`,
`intro_text` oder Produktnamen sollten nun wieder automatisch als
Translation-Hash-Abweichung erkannt und exportiert werden.
