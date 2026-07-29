# Kategorie-Beschreibungen aus AFS Extras geprueft

## Task

Pruefen, ob Warengruppen-/Kategorie-Beschreibungen aus
`afs_extras.category_translations.description` ueber RAW und Stage bis in
den XT-Shop gelangen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/agent-results/2026-07-29-category-translation-description.md`
- `config/sources.php`
- `config/normalize.php`
- `config/merge.php`
- `config/delta.php`
- `config/xt_write.php`
- `src/Service/AfsExtrasBootstrapService.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtCategoryWriter.php`
- `src/Web/Repository/MigrationRepository.php`
- `scripts/setup-database.php`
- `migrations/022_add_category_translation_description.sql`

## Changed files

- `docs/agent-results/2026-07-29-category-description-flow-diagnosis.md`

Die bereits vorhandenen, parallelen Code-, Schema- und
Migrationsaenderungen fuer Kategorie-Beschreibungen wurden nicht
veraendert.

## Summary

### Implementierter Datenpfad

Der aktuell uncommittete Arbeitsstand hat den grundsaetzlichen Datenpfad
bereits vorbereitet:

- `config/sources.php` liest `category_translations.description`.
- `config/normalize.php` schreibt das Feld nach
  `raw_extra_category_translations.description`.
- `config/merge.php` bevorzugt die Extra-Beschreibung.
- `config/xt_write.php` schreibt `translation.description` nach
  `xt_categories_description.categories_description`.

Der Merge verwendet derzeit allerdings weiterhin
`raw_afs_categories.description` als Fallback. Damit stammen
Kategorie-Beschreibungen nicht ausschliesslich aus AFS Extras.

### Aktueller Datenbestand

In `afs_extras.category_translations` befinden sich 75 Zeilen je Sprache:

| Sprache | Zeilen | Beschreibung gefuellt | Beschreibung leer |
|---|---:|---:|---:|
| de | 75 | 15 | 60 |
| en | 75 | 1 | 74 |
| fr | 75 | 1 | 74 |
| nl | 75 | 1 | 74 |

RAW enthaelt:

| Sprache | Beschreibung gefuellt |
|---|---:|
| de | 15 |
| en | 0 |
| fr | 0 |
| nl | 0 |

Die drei fremdsprachigen Extra-Beschreibungen gehoeren zur Warengruppe
`272` (`Absaugarm`). Sie wurden nach dem letzten Import gepflegt und sind
deshalb noch nicht in RAW angekommen.

Stage enthaelt trotzdem 15 Beschreibungen je Sprache. Ursache ist der
AFS-Fallback:

- DE: 15 Extra-Beschreibungen
- EN: 15 deutsche AFS-Fallbacktexte
- FR: 15 deutsche AFS-Fallbacktexte
- NL: 15 deutsche AFS-Fallbacktexte

Damit stehen aktuell insgesamt 45 deutsche AFS-Texte in den
fremdsprachigen Stage-Zeilen.

### Aktueller XT-Shopbestand

Der XT-Mirror wurde fuer diese Pruefung mit Lauf `122` am 29.07.2026 von
09:42:44 bis 09:43:04 UTC erfolgreich aktualisiert.

Vergleich der 71 aktiven Stage-Kategorien:

| Sprache | Stage-Zeilen | Passend zum Shop | Abweichend |
|---|---:|---:|---:|
| de | 71 | 70 | 1 |
| en | 71 | 58 | 13 |
| fr | 71 | 58 | 13 |
| nl | 71 | 58 | 13 |

Konkrete relevante Abweichungen:

- Warengruppe `272`:
  - Die aktuellen Extra-Beschreibungen fuer EN, FR und NL sind gefuellt.
  - RAW enthaelt sie noch nicht.
  - Stage enthaelt stattdessen den deutschen AFS-Fallback.
  - Im Shop sind die drei Beschreibungen aktuell leer.
- Warengruppe `288` (`Rohrverbinder`):
  - Extra, RAW und Stage enthalten den aktuellen deutschen Text mit
    12.360 Zeichen.
  - Der Shop enthaelt noch einen aelteren Text mit 6.418 Zeichen.

Fuer `272` und `288` entspricht der gespeicherte
`category_export_state.last_exported_hash` trotzdem bereits dem aktuellen
Stage-Hash. Ein normaler Delta-Lauf wird diese Abweichungen deshalb derzeit
nicht automatisch nachziehen.

## Open points

- Wenn Kategorie-Beschreibungen gemaess Anforderung ausschliesslich aus
  Extras kommen sollen, muss der AFS-Fallback fuer
  `stage_category_translations.description` entfernt werden.
- Danach muessen Import und Merge erneut ausgefuehrt werden, damit die drei
  aktuellen Uebersetzungen von Warengruppe `272` in RAW und Stage ankommen.
- Bereits falsch bestaetigte Kategorie-States muessen gezielt anhand des
  Stage-/Mirror-Translation-Hashvergleichs fuer einen neuen Export
  vorbereitet werden.
- Nach Delta und Export muss der XT-Mirror erneut aktualisiert und gegen
  Extras verglichen werden.
- 60 der 75 Extra-Warengruppen besitzen derzeit auch auf Deutsch keine
  Beschreibung. Bei einer reinen Extra-Quelle bleiben diese Beschreibungen
  bewusst leer.
- Vier Extra-Warengruppen haben aktuell keine aktive
  `stage_categories`-Zeile und werden deshalb nicht als Shop-Kategorie
  exportiert.

## Validation steps

Ausgefuehrt:

- Vorhandene uncommittete Kategorie-Aenderungen gelesen und abgegrenzt.
- Spalten in `afs_extras`, RAW und Stage per `information_schema` geprueft.
- Extra-, RAW- und Stage-Werte je Sprache gezaehlt.
- Extra gegen RAW und gefuellte Extra-Beschreibungen gegen Stage
  verglichen.
- Aktuellen XT-Mirror mit `run_xt_mirror.php` eingelesen.
- Alle aktiven Stage-Kategorien je Sprache gegen
  `xt_mirror_categories_description` verglichen.
- Warengruppen `272` und `288` einzeln bis zum Export-State geprueft.

Nicht ausgefuehrt:

- keine Aenderung des Merge-Fallbacks
- kein neuer Import oder Merge
- keine Korrektur von Kategorie-Export-States
- kein Kategorie-Export

## Recommended next step

Den AFS-Fallback aus der Kategorie-Translation entfernen, Import und Merge
ausfuehren, nur die danach vom XT-Mirror abweichenden Kategorie-States
gezielt neu einreihen und den Shop nach dem Export erneut vollstaendig
gegen `afs_extras.category_translations.description` pruefen.
