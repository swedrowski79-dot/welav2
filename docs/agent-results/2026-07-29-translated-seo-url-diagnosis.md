# Übersetzte SEO-URLs für Warengruppen und Artikel

## Task

Prüfen, warum die SEO-URLs der übersetzten Warengruppen und Artikel nicht den übersetzten Namen beziehungsweise der übersetzten Kategoriehierarchie entsprechen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/delta.php`
- `config/languages.php`
- `config/xt_write.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/StageCategoryMap.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/XtProductWriter.php`
- `wela-api/seo_helpers.php`
- `wela-api/src/CategorySyncService.php`
- `wela-api/src/ProductSyncService.php`
- `docs/agent-results/2026-04-16-localized-seo-generation.md`
- `docs/agent-results/2026-04-17-seo-url-md5-fix.md`
- `docs/agent-results/2026-04-21-seo-url-logic.md`
- `docs/agent-results/2026-07-17-category-seo-empty-fallback-fix.md`
- `docs/agent-results/2026-07-17-seo-skip-unchanged-upserts.md`
- `docs/agent-results/2026-07-20-full-product-reexport-seo-fix.md`
- `docs/agent-results/2026-07-20-product-seo-canonical-redirect-fix.md`

## Changed files

- `docs/agent-results/2026-07-29-translated-seo-url-diagnosis.md`

Keine PHP-, Konfigurations-, Datenbank- oder Queue-Daten wurden für diese Diagnose geändert.

## Summary

### Aktueller Datenbefund

Der letzte vor dem geprüften Export vorhandene XT-Mirror-Lauf `126` endete am
2026-07-29 um 09:52:51 erfolgreich.

In `xt_mirror_seo_url` lagen zu diesem Zeitpunkt folgende SEO-Zeilen:

| Typ | de | en | fr | nl |
|---|---:|---:|---:|---:|
| Artikel (`link_type = 1`) | 6.676 | 5.500 | 5.500 | 5.500 |
| Warengruppen (`link_type = 2`) | 280 | 3 | 3 | 3 |

Die vorhandenen Zeilen haben einen Sprachpräfix und einen zum `url_text`
passenden MD5-Wert. Das Hauptproblem ist deshalb nicht die MD5-Bildung, sondern
das fast vollständige Fehlen der fremdsprachigen Warengruppen-SEO-Zeilen.

Für alle 75 Übersetzungssätze je Sprache ist ein übersetzter Warengruppenname
vorhanden. Die fehlenden SEO-Zeilen entstehen daher nicht durch leere Namen in
den Extras.

Beispiele aus dem Mirror:

- Warengruppe `272` hat auf Deutsch
  `de/absaugarme-zubehoer/absaugarme`, aber im geprüften Mirror keine URL für
  `en`, `fr` oder `nl`.
- Die Warengruppen `281` und `288` zeigen das gleiche Muster.
- Bei Artikel `HA-000` sind die fremdsprachigen Produktslugs übersetzt, zum
  Beispiel
  `en/econ-extraction-arm-with-internal-support-structure`.
  Der übersetzte Warengruppenpfad davor fehlt jedoch.
- Auf Deutsch enthält derselbe Artikel den vollständigen Pfad
  `de/absaugarme-zubehoer/absaugarme/...`.

Um 09:53:02 wurde Warengruppe `272` aufgrund einer anderen Änderung erfolgreich
exportiert. Danach gab es noch keinen neuen Mirror-Lauf. Dieser einzelne Export
kann die vier SEO-Zeilen von `272` inzwischen ergänzt haben, behebt aber nicht
die übrigen Warengruppen.

### Ursache im Code und Delta

Die URL-Erzeugung selbst arbeitet sprachabhängig:

- Kategorien werden aus dem übersetzten Kategorienamen erzeugt.
- Unterkategorien übernehmen den vorhandenen SEO-Pfad ihrer Elternkategorie in
  derselben Sprache.
- Produkte werden aus dem übersetzten Produktnamen erzeugt und übernehmen den
  vorhandenen SEO-Pfad ihrer Hauptkategorie in derselben Sprache.
- Bei einer URL-Änderung erzeugt die API einen Redirect von der alten URL.

Dadurch entsteht ein zwingender Exportablauf:

`Elternkategorie -> Unterkategorie -> Artikel`

Das aktuelle Delta bildet diese Abhängigkeit nicht vollständig ab:

1. Der Kategorie-SEO-Vergleich berücksichtigt nur `meta_title` und
   `meta_description`, nicht `url_text` oder das Fehlen einzelner
   Sprachzeilen als eigenen Reparaturgrund.
2. Bei bereits vorhandenem Export-State erzeugt
   `ProductDeltaService::nextAction()` nur dann ein Update, wenn sich der
   Payload-Hash geändert hat. Ein vom Mirror gemeldetes Mismatch allein erzeugt
   keinen Queue-Eintrag.
3. Der Kategorie-Hash enthält die eigene Kategorie und ihre Übersetzungen, aber
   nicht die übersetzten Namen beziehungsweise URLs aller Vorfahren.
4. Der Produkt-Hash enthält Produktdaten und Kategoriezuordnung, aber nicht den
   übersetzten SEO-Pfad der Kategorie.

Der Lauf `127` bestätigt dieses Verhalten direkt:

- 71 von 71 aktiven Kategorien wurden im Mirror als abweichend erkannt.
- Nur eine Kategorie mit geändertem Payload wurde in die Queue geschrieben.
- Die übrigen 70 galten trotz Mirror-Abweichung als unverändert.

Damit bleiben fehlende fremdsprachige Kategorie-SEO-Zeilen sowie davon
abhängige flache oder veraltete Produkt-URLs dauerhaft bestehen.

## Open points

- Nach dem Export von Kategorie `272` ist ein neuer Mirror-Lauf erforderlich,
  um ihren aktuellen Shopstand lokal zu bestätigen.
- Auch bei deutschen Produktpfaden meldet der aktuelle Vergleich viele
  Abweichungen. Bei Varianten muss dafür zuerst die vom Writer aufgelöste
  Master-Kategorie berücksichtigt werden; die rohe Kategorie-ID des Slaves
  reicht für eine belastbare Bewertung nicht aus.
- Ein vollständiger Reparaturlauf schreibt viele URLs im Shop. Er sollte erst
  nach der Codekorrektur und in der unten genannten Reihenfolge ausgeführt
  werden.

## Validation steps

Ausgeführt:

- PHP- und API-Pfad für Kategorie- und Produkt-SEO gelesen.
- Sprachzeilen, URL-Präfixe und `url_md5 = MD5(url_text)` im XT-Mirror geprüft.
- Übersetzte Kategorie- und Produktnamen mit vorhandenen URLs verglichen.
- `HA-000` und mehrere betroffene Warengruppen exemplarisch geprüft.
- Delta- und Export-Queue-Logs der Läufe `126` bis `129` geprüft.
- Keine direkte Verbindung zu einer XT-Datenbank verwendet.

Nicht ausgeführt:

- Kein Code-Fix.
- Kein erzwungener Massen-Reexport.
- Kein XT-Mirror-Refresh nach dem Einzel-Export von Kategorie `272`.

## Recommended next step

Die Reparatur sollte zweistufig umgesetzt werden:

1. Delta-Abhängigkeiten ergänzen:
   - Kategorie-State muss Änderungen des vollständigen übersetzten
     Vorfahrenpfads berücksichtigen.
   - Produkt-State muss Änderungen des aufgelösten übersetzten
     Hauptkategoriepfads berücksichtigen.
   - Fehlende SEO-Sprachzeilen müssen einen kontrollierten Reparatur-Export
     auslösen können.
2. Einmalige kontrollierte Reparatur:
   - alle 71 aktiven Kategorien hierarchisch von Eltern nach Kindern exportieren,
   - XT-Mirror aktualisieren und vier SEO-Zeilen je Kategorie prüfen,
   - danach die 5.499 aktiven Produkte exportieren,
   - Mirror erneut aktualisieren und Produktpfade einschließlich Redirects
     prüfen.

Ein Produkt-Reexport vor dem Kategorie-Reexport würde erneut flache
fremdsprachige Produkt-URLs erzeugen und ist deshalb nicht ausreichend.
