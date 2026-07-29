# HA-000 Beschreibungspruefung

## Task

Die Beschreibungen des Artikels `HA-000` zwischen Extra-Tabelle, RAW,
Stage, Export-Payload und aktuellem XT-Mirror vergleichen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/merge.php`
- `config/delta.php`
- `config/xt_write.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `docs/agent-results/2026-07-29-product-description-delta-diagnosis.md`

## Changed files

- `docs/agent-results/2026-07-29-ha-000-description-check.md`

Code, Konfiguration, Datenbank, Export-State und Export Queue wurden nicht
veraendert.

## Summary

- `HA-000` ist das Stage-Produkt mit `afs_artikel_id = 51624`.
- In `afs_extras.article_translations` existieren vollstaendige Zeilen fuer
  `de`, `en`, `fr` und `nl`.
- Extra, `raw_extra_article_translations` und
  `stage_product_translations` stimmen bei `description`, `intro_text` und
  `technical_data_html` in allen vier Sprachen exakt ueberein.
- Der aktuelle XT-Mirror wurde am 29.07.2026 von 09:18:55 bis 09:19:16 UTC
  erfolgreich aktualisiert. Er zeigt damit den aktuellen ueber die XT-API
  gelesenen Shopbestand.
- Im XT-Mirror stimmen fuer keine der vier Sprachen die Lang- oder
  Kurzbeschreibung mit dem aktuellen Extra-/Stage-Stand ueberein.
- Die erwartete XT-Langbeschreibung wird aus
  `description + Leerzeile + technical_data_html` gebildet.

| Sprache | Extra-Beschreibung | Extra-Technik | Erwartete XT-Langbeschreibung | XT-Langbeschreibung | Langtext passt | Kurztext passt |
|---|---:|---:|---:|---:|---|---|
| de | 1.269 Zeichen | 1.199 Zeichen | 2.470 Zeichen | 1.276 Zeichen | nein | nein |
| en | 1.164 Zeichen | 1.216 Zeichen | 2.382 Zeichen | 1.168 Zeichen | nein | nein |
| fr | 1.209 Zeichen | 1.212 Zeichen | 2.423 Zeichen | 1.285 Zeichen | nein | nein |
| nl | 1.173 Zeichen | 1.206 Zeichen | 2.381 Zeichen | 1.246 Zeichen | nein | nein |

- Der deutsche aktuelle Extra-Text beschreibt konkret die ECON-Serie mit
  haengender/stehender Ausfuehrung, 2.000/3.000 mm Laenge, NW160, 80 Grad C,
  Drosselklappe und Wandhalter-Anschluss.
- Der deutsche Shoptext ist stattdessen ein aelterer generischer Text
  ("unterstuetzt dich dabei, deine Anlage sauber, sicher und funktional
  aufzubauen").
- Der letzte normale Queue-Eintrag fuer `HA-000` ist `37934` vom 23.07.2026.
  Er enthaelt vier damalige Uebersetzungen und wurde erfolgreich
  verarbeitet.
- Der aktuelle Shoptext entspricht in Inhalt und Laenge diesem alten
  Payload-Stand. Seitdem wurde fuer `HA-000` kein neuer Queue-Eintrag
  erzeugt.
- Trotzdem sind `stage_products.hash` und
  `product_export_state.last_exported_hash` identisch. Der State wurde am
  29.07.2026 um 09:19:16 UTC erneut als aktuell markiert, obwohl die
  Shoptexte abweichen. Damit bestaetigt `HA-000` konkret den zuvor
  diagnostizierten fehlenden Translation-Hash-Abgleich.

## Open points

- `HA-000` benoetigt einen neuen Produkt-Export mit dem aktuellen
  Stage-Payload.
- Vorher muss der Produkt-Delta-Abgleich den `translation_hash`
  beruecksichtigen, damit der noch falsche Shoptext nicht erneut als
  synchron bestaetigt wird.
- Der bereits falsch bestaetigte Export-State von `HA-000` muss fuer den
  Nachzug gezielt korrigiert oder der Artikel kontrolliert neu eingereiht
  werden.

## Validation steps

Ausgefuehrt:

- Extra-, RAW- und Stage-Werte je Sprache anhand von Zeichenlaengen und
  SHA-256-Hashes verglichen.
- Erwartete kombinierte Langbeschreibung je Sprache berechnet.
- Aktuellen XT-Mirror fuer Produkt-ID `745` und Artikel `HA-000` geprüft.
- XT-Lang- und Kurzbeschreibungen je Sprache gegen Stage verglichen.
- Queue-Historie und Payload des letzten Eintrags `37934` geprüft.
- Aktuellen `product_export_state` gegen `stage_products.hash` geprüft.

Nicht ausgefuehrt:

- keine Delta-Konfigurationsaenderung
- keine State-Korrektur
- kein neuer Queue-Eintrag
- kein Export nach XT

## Recommended next step

Den Translation-Hash-Abgleich korrigieren und `HA-000` anschliessend gezielt
neu exportieren. Danach den XT-Mirror erneut aktualisieren und alle vier
Sprachen nochmals gegen Extra/Stage pruefen.
