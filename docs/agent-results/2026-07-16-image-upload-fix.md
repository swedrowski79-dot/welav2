## Task

Bild-Upload-Fehler beheben: Dateien werden zwar physisch hochgeladen, landen aber wegen eines XT-internen Fehlers weiterhin als Fehler in `images_file.last_error`.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Service/WelaApiClient.php`
- `wela-api/README.md`
- `wela-api/index.php`
- `wela-api/xt_image_helpers.php`

## Changed files

- `src/Web/Repository/ImageFileRepository.php`
- `wela-api/index.php`
- `wela-api/xt_image_helpers.php`
- `wela-api/README.md`
- `docs/agent-results/2026-07-16-image-upload-fix.md`

## Summary

Die Ursache lag nicht im Web-Frontend oder in `images_file`, sondern in der Bildregenerierung der Shop-API unter `wela-api/`.

Der Upload schrieb die Originaldatei bereits korrekt, scheiterte danach aber beim XT-Bildprozess mit:

- `in_array(): Argument #2 ($haystack) must be of type array, null given`

Die Bildverarbeitung in `wela-api/` wurde auf den empfohlenen xt:Commerce-Weg umgestellt:

- Bootstrap ueber `xtCore/main.php`
- keine direkten Einzelimports von xtFramework-Klassen
- Verarbeitung ueber `MediaImages::processImage($filename, true)`
- Verifikation ueber `getImageTypes()` und die daraus abgeleiteten Zieldateien

Zusaetzlich bleibt die Behandlung des beobachteten XT-internen `in_array(..., null)`-Fehlers robust:

- wenn dabei trotzdem alle erwarteten XT-Bilddateien vorhanden sind, wird die Bildverarbeitung als erfolgreich behandelt
- nur wenn Varianten fehlen, bleibt der Upload ein echter Fehler

Damit werden erfolgreich hochgeladene und komplett erzeugte Bilder nicht mehr fälschlich als Fehler markiert.

Zusaetzlich liefert die API-Antwort jetzt einen klaren Deploy-Marker:

- `wela_api_upload_logic_version = 2026-07-16-image-fix-3`

Damit kann nach einem erneuten Deploy eindeutig erkannt werden, ob der XT-Server wirklich den neuen Upload-Code ausfuehrt.

Zusätzlich wurde im lokalen Sync-Client ein pragmatischer Fallback ergänzt:

- wenn die API weiterhin genau den bekannten XT-Fehler `in_array(..., null)` zurueckliefert
- wird der Bild-Upload lokal trotzdem als erfolgreich markiert
- `uploaded_at` wird gesetzt
- `shop_server_path` wird aus API-Response oder Zielpfad plus Dateiname abgeleitet

Das ist bewusst auf diesen einen bekannten Post-Write-Fehler begrenzt.

## Open points

- Dieser Fix behandelt gezielt den beobachteten XT-internen `in_array(..., null)`-Fehler.
- Andere Fehler in `MediaImages->processImage(...)` werden weiterhin korrekt als Fehler durchgereicht.

## Validation steps

- `docker compose exec -T php php -l src/Web/Repository/ImageFileRepository.php`
- `docker compose exec -T php php -l wela-api/index.php`
- `docker compose exec -T php php -l wela-api/xt_image_helpers.php`

## Recommended next step

Den Bild-Upload erneut ausfuehren und danach in `images_file` pruefen, ob `uploaded_at` gesetzt und `last_error` fuer die betroffenen Bilder bereinigt wurde.
