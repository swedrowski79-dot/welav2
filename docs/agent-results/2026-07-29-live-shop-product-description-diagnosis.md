# Artikelbeschreibungen nach Uebertragung der Schnittstelle

## Task

Pruefen, welche Artikelbeschreibungen an XT uebertragen werden und wie nach
der Uebertragung der Wela-API auf einen anderen Server falsche beziehungsweise
unerwartete Artikelbeschreibungen entstehen koennen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `.env` (nur Ziel-URL, keine Zugangsdaten)
- `config/sources.php`
- `config/merge.php`
- `config/delta.php`
- `config/xt_write.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `src/Service/WelaApiClient.php`
- `wela-api/src/ProductSyncService.php`
- `docs/agent-results/2026-07-29-product-description-delta-fix.md`

## Changed files

- `docs/agent-results/2026-07-29-live-shop-product-description-diagnosis.md`

Code, Konfiguration, Export-State, Queue und Shopdaten wurden nicht
veraendert.

## Summary

### Uebertragene Artikeltexte

Fuer jede vorhandene Stage-Uebersetzung in `de`, `en`, `fr` und `nl` schreibt
der Produktexport nach `xt_products_description`:

- `products_name` aus `stage_product_translations.name`
- `products_description` aus
  `description + zwei Zeilenumbrueche + technical_data_html`
- `products_short_description` aus `short_description`, das aus dem
  Extra-Feld `intro_text` stammt
- `products_keywords` und `products_url` als leere Zeichenfolge
- `products_store_id = 1`
- `reload_st = 0`

SEO-Titel und SEO-Beschreibung werden getrennt nach `xt_seo_url` geschrieben.

Im aktuellen Quelldatenbestand sind alle vorhandenen Beschreibungen direkt in
AFS Extras gefuellt. Der konfigurierte AFS-Fallback wird fuer den aktuellen
Bestand deshalb nicht verwendet.

### Zielsystem

- `.env` und der laufende PHP-Container verwenden dieselbe XT-API-URL.
- Der API-Healthcheck war erfolgreich.
- Die Live-API meldet die Runtime-Version
  `2026-07-29-translated-seo-path-fix-2`.
- Der erfolgreiche Mirror-Lauf `179` las 6.883 Produkte aus dem aktuell
  konfigurierten Shop.

### Konkreter Nachweis fuer HA-000

Der aktuelle Stage- und Queue-Payload fuer `HA-000` enthaelt je Sprache zwei
getrennte Langtextfelder. Fuer Deutsch sind das:

- `description`: 1.269 Zeichen
- `technical_data_html`: 1.199 Zeichen
- resultierendes `xt_products_description.products_description`:
  2.470 Zeichen

Die zwei zusaetzlichen Zeichen sind die beiden Zeilenumbrueche zwischen den
Texten. Der aktuelle XT-Mirror enthaelt genau diese zusammengesetzte
Langbeschreibung. Dasselbe Verhalten gilt fuer Englisch, Franzoesisch und
Niederlaendisch.

Wenn im Zielshop ausschliesslich der Inhalt der Extra-Spalte `description`
erwartet wird, ist die automatische Ergaenzung von `technical_data_html` die
direkte Ursache des unerwarteten Ergebnisses.

Die Wela-API auf dem neuen Server waehlt selbst keinen Beschreibungstext aus.
Sie schreibt die bereits im Request enthaltenen `translations[].columns` per
Upsert nach `xt_products_description`. Die Textzusammenstellung erfolgt davor
in `XtProductWriter`.

### Abgrenzung des lokal geprueften Laufs

Der lokale Full-Pipeline-Lauf `176` exportierte keine Produkte. Dieser Lauf
ist deshalb nicht der vom Benutzer beschriebene Massenexport auf dem anderen
Server und darf nicht als dessen Exportnachweis interpretiert werden.

Unabhaengig davon bleibt ein zweites Risiko bestehen: `product_export_state`
ist nicht an eine konkrete XT-Ziel-URL gebunden. Bei weiteren Zielwechseln
koennen bestaetigte States eines anderen Shops zu ausgelassenen Exporten
fuehren.

### Temporaerer Vergleich ueber shop.welafix.de

Die lokale XT-API-URL wurde fuer einen reinen Lesevergleich kurzfristig auf
`https://shop.welafix.de/wela-api` gestellt. Der Healthcheck war erfolgreich
und bestaetigte die Runtime-Version
`2026-07-29-translated-seo-path-fix-2`.

Mirror-Lauf `184` las erfolgreich:

- 6.882 Produkte
- 23.269 Produkt-Sprachzeilen
- 0 Mirror-Fehler

Der bytegenaue Vergleich unmittelbar vor dem vom Benutzer gemeldeten zweiten
Korrekturlauf ergab:

- 5.499 aktive deutsche Stage-Produktzeilen
- 2.502 bereits passende deutsche Langbeschreibungen
- 2.997 noch abweichende deutsche Langbeschreibungen
- dieselben 2.997 Abweichungen bei den Kurzbeschreibungen
- auch in `en`, `fr` und `nl` jeweils 2.997 abweichende Lang- und Kurztexte

`HA-000` enthielt zu diesem Zeitpunkt im Domain-Shop noch die alten,
kuerzeren Texte und nicht die aktuelle Kombination aus Extra-Beschreibung
und technischen Daten.

Die Aufteilung von ungefaehr 2.500 erfolgreichen und 2.997 noch alten
Produkten spricht fuer einen teilweise erfolgreichen parallelen
Export-Worker-Lauf. Der Worker kann temporaere API- oder Socketfehler als
Retry behandeln und die betroffenen Queue-Eintraege fuer standardmaessig
300 Sekunden zurueckstellen. Solche Retries erhoehen `issue` und `retried`,
aber nicht den terminalen `error`-Zaehler. Der Lauf kann deshalb als
`success` enden, obwohl noch zeitverzoegerte Pending-Eintraege existieren.

Ein zweiter Worker-Lauf nach Ablauf der Retry-Zeit verarbeitet diese
Eintraege erneut. Das erklaert, warum der zweite Durchlauf die
Beschreibungen korrigieren konnte.

Nach dem Lesevergleich wurde `XT_API_URL` wieder auf
`http://10.0.1.49/wela-api` zurueckgestellt. Mirror-Lauf `185` aktualisierte
den lokalen Spiegel anschliessend erfolgreich wieder vom urspruenglichen
Ziel. In keinen Shop wurde durch diese Pruefung geschrieben.

## Open points

- Es muss fachlich entschieden werden, ob
  `technical_data_html` weiterhin an die XT-Langbeschreibung angehaengt werden
  soll oder ob `products_description` ausschliesslich aus
  `stage_product_translations.description` bestehen soll.
- Vor einer Live-Korrektur sollten weitere konkret betroffene Artikel per SKU
  kontrolliert werden.
- Fuer grosse Live-Reparaturen sollte voruebergehend nur ein Export-Worker
  verwendet und nach jedem Lauf nicht nur der Laufstatus, sondern auch
  `pending`, `pending_delayed`, `retried` und `issue` kontrolliert werden.
- Die Full Pipeline sollte einen Lauf mit zeitverzoegerten Retries nicht als
  vollstaendig abgeschlossen darstellen.
- Dauerhaft sollte der Export-State an ein Zielsystem-Fingerprint gebunden
  oder bei einem bewussten Zielwechsel kontrolliert invalidiert werden.
- Ein ungepruefter Reset aller Produkt-States wuerde bis zu 5.499 komplette
  Produkt-Payloads inklusive Stammdaten, Kategorien, Attribute, SEO und
  Beschreibungen neu nach Live schreiben.

## Validation steps

Read-only erfolgreich ausgefuehrt:

- XT-URL aus `.env` mit der Umgebung des laufenden PHP-Containers verglichen
- API-Healthcheck gegen das aktuell konfigurierte Ziel
- letzte Pipeline-, Mirror-, Delta- und Worker-Laeufe ausgewertet
- Produkt-, Mirror-, Export-State- und Queue-Mengen geprueft
- Quellen und konkrete XT-Feldzuordnung der Produkttexte verfolgt
- Fuellstand der Extra-Beschreibungen je Sprache geprueft
- die gespeicherten Queue-Payloads fuer `HA-000` je Sprache ausgewertet
- Stage- und XT-Mirror-Laengen fuer `HA-000` verglichen
- Domain-API per Healthcheck geprueft
- Domain-Mirror mit Lauf `184` eingelesen
- alle vorhandenen Stage-/Domain-Produkttexte bytegenau verglichen
- urspruengliche API-URL wiederhergestellt
- lokalen Mirror mit Lauf `185` wieder auf das urspruengliche Ziel ausgerichtet

Nicht ausgefuehrt:

- kein Import, Merge, Delta oder Export
- kein Reset von Export-States oder Queue
- keine Aenderung an Shopdaten

## Recommended next step

Festlegen, ob die technischen Daten in der normalen XT-Langbeschreibung
enthalten sein sollen. Falls nicht, die Berechnung in `XtProductWriter` und
den korrespondierenden Translation-Hash in `ProductDeltaService` gemeinsam
auf die reine Extra-Beschreibung umstellen. Danach die nachgewiesen betroffenen
Artikel kontrolliert neu einreihen und gegen den Zielshop verifizieren.
