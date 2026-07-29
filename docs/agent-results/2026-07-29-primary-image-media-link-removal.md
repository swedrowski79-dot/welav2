# Bild1 aus Artikel-Media-Verknüpfungen entfernen

## Task

Sicherstellen, dass das erste Artikelbild ausschließlich über
`xt_products.products_image` verwendet wird und nicht zusätzlich als
`xt_media_link(type=images)` mit dem Artikel verknüpft wird. Nur `Bild2` bis
`Bild10` sollen als zusätzliche Artikelbilder exportiert werden.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `config/delta.php`
- `config/xt_write.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Web/Repository/ImageFileRepository.php`
- `docs/agent-results/2026-07-29-product-multiple-images-interface-check.md`
- `docs/agent-results/2026-07-29-image-upload-regeneration-diagnosis.md`
- aktueller Stage-, Media-State-, Queue- und XT-Mirror-Bestand

## Changed files

- `config/delta.php`
- `config/xt_write.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtMediaDocumentWriter.php`
- `docs/agent-results/2026-07-29-primary-image-media-link-removal.md`

## Summary

### Gewünschte Trennung

Der Bildfluss ist jetzt eindeutig getrennt:

- `Bild1`
  - bleibt in `stage_product_media`
  - bleibt Bestandteil des Datei-Uploads
  - wird weiterhin durch `XtProductWriter` nach
    `xt_products.products_image` geschrieben
  - wird nicht mehr als `xt_media_link(type=images)` exportiert
- `Bild2` bis `Bild10`
  - bleiben eigenständige Media-Entitäten
  - werden weiterhin per `xt_media` und `xt_media_link(type=images)`
    mit ihrer Position verknüpft

### Delta-Änderung

Für `media_export_queue` wurde konfigurationsgesteuert aktiviert:

```php
'exclude_primary_image' => true,
```

`ProductDeltaService::fetchEntities()` schließt damit bei Medien nur Zeilen
aus, die:

- `position = 1` oder
- `source_slot = image_1`

besitzen.

Die Primärbildzeilen bleiben physisch in Stage vorhanden. Weil sie nicht mehr
zur Media-Exportmenge gehören, behandelt Delta ihre bisherigen bestätigten
Media-States kontrolliert als entfernte Verknüpfungen. Dadurch kann ein
normaler Pipeline-Lauf die vorhandenen Doppelverknüpfungen abbauen.

### Writer-Absicherung

Die Media-Link-Definition besitzt ebenfalls:

```php
'exclude_primary_image' => true,
```

`XtMediaDocumentWriter` prüft diese Regel vor jedem normalen Media-Upsert.
Erkennt er `Bild1`, wird kein neues `xt_media`/`xt_media_link` geschrieben.
Eine gegebenenfalls vorhandene Verknüpfung dieses konkreten Mediums wird
stattdessen gelöscht.

Diese zweite Absicherung ist wichtig für alte oder bereits wartende
Queue-Einträge, deren Payload noch vor der Delta-Korrektur erzeugt wurde.

### Erwarteter einmaliger Nachzug

Ein vollständig zurückgerollter Dry-Run gegen den aktuellen Bestand ergab:

- exportierbare zusätzliche Bilder: 10
- davon im Mirror korrekt vorhanden: 10
- vorhandene Bild1-Verknüpfungen zur Entfernung: 5.477
- bereits wartender älterer Bild1-Queue-Eintrag: 1
- erwartete offene Media-Arbeit beim echten Lauf: 5.478
- Dry-Run-Fehler: 0

Nach dem echten Export sollen im Shop nur noch die zehn derzeit vorhandenen
zusätzlichen Bildverknüpfungen verbleiben. Die 5.478 Hauptbilder bleiben
weiterhin über `xt_products.products_image` verfügbar.

Die zugehörigen `xt_media`-Entitäten werden nicht physisch gelöscht. Entfernt
wird ausschließlich die doppelte Produktverknüpfung. Damit bleibt der Eingriff
auf die gewünschte Darstellungslogik begrenzt.

## Open points

- Der reale Delta-/Exportlauf wurde noch nicht gestartet.
- Wegen der bereits beobachteten Windows-Socket-Erschöpfung beim parallelen
  Medien-Massennachzug sollte die einmalige Bereinigung mit nur einem
  Export-Worker beziehungsweise kontrollierten Batches laufen.
- Nach Abschluss ist ein XT-Mirror-Refresh erforderlich.
- Die separate Korrektur der unvollständigen XT-Bildgrößenerzeugung aus
  `default` plus `product` bleibt weiterhin notwendig.

## Validation steps

Erfolgreich ausgeführt:

- `php -l config/delta.php`
- `php -l config/xt_write.php`
- `php -l src/Service/ProductDeltaService.php`
- `php -l src/Service/XtMediaDocumentWriter.php`
- `git diff --check` für alle geänderten PHP-Dateien
- isolierter SQLite-Test der Delta-Auswahl:
  - `image_1`/Position 1 ausgeschlossen
  - `image_2` und `image_10` enthalten
- isolierter Reflection-Test der Writer-Erkennung:
  - `image_1@1` = Primärbild
  - `image_2@2` und `image_10@10` = Zusatzbilder
  - widersprüchliches `image_1@2` wird sicherheitshalber als Primärbild
    behandelt
- transaktionaler Dry-Run gegen den aktuellen Stage-/Mirror-Bestand
- Queue-Anzahl vor und nach Rollback identisch
- keine Shop-API-Schreibaktion ausgeführt
- keine direkte XT-Datenbankverbindung verwendet

## Recommended next step

Einen echten Delta-Lauf starten, danach die 5.478 Media-Entfernungen mit einem
einzelnen Worker kontrolliert abarbeiten und den XT-Mirror aktualisieren.
Anschließend prüfen:

- 5.478 von 5.478 Hauptbildern stimmen weiterhin mit
  `xt_products.products_image` überein
- kein `image_1` besitzt noch einen `xt_media_link(type=images)`
- alle zehn `image_2`-Verknüpfungen bleiben mit `sort_order = 2` erhalten
- keine Media-Einträge stehen in `pending`, `processing` oder `error`
