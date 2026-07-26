# Task

Serverseitig nach Bild-Uploads die internen XT-Commerce-Bildgroessen neu erzeugen, diesmal mit der korrekten XT-Umgebung `xtFramework/admin/main.php` und `MediaImages`.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `wela-api/index.php`
- `wela-api/README.md`
- `wela-api/config.php.example`
- `src/Service/WelaApiClient.php`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Web/Controller/ImageFileController.php`

# Changed files

- `wela-api/index.php`
- `wela-api/xt_image_helpers.php`
- `wela-api/README.md`

# Summary

Die XT-API ruft jetzt nach einem erfolgreichen Datei-Upload automatisch `xt_regenerate_product_image($filename)` auf, aber nur dann, wenn das Zielverzeichnis dem XT-Bilder-Originalpfad `media/images/org` entspricht. Dokument-Uploads und andere Upload-Ziele bleiben unveraendert.

Die neue Helperfunktion laedt `xtFramework/admin/main.php`, sichert den Dateinamen mit `basename()` ab, instanziiert `MediaImages`, setzt die Klasse auf `product` oder `category` und triggert die XT-interne Bildgenerierung ueber `processImage($filename, true)`, also mit Ueberschreiben vorhandener Bildgroessen.

Die Klassenerkennung laeuft mit sicherem Fallback auf `product`. Wenn der Dateiname bereits in `xt_categories.categories_image` oder `xt_categories.categories_master_image` verwendet wird, setzt der Helper automatisch `category`.

Ein Bild gilt fuer die Sync-App jetzt erst dann als erfolgreich hochgeladen, wenn die XT-Regenerierung durchgelaufen ist und die erwarteten Bildgroessen `mini`, `thumbnail`, `product`, `info` und `popup` auch physisch vorhanden sind. Erst dann liefert die Shop-API eine bestaetigte Erfolgsmeldung zurueck und `images_file.upload` wird auf erledigt gesetzt.

Die Bild-Uploadmarkierungen wurden danach erneut zurueckgesetzt:
- vorher: `385` total, `6` pending, `379` uploaded
- nachher: `385` total, `385` pending, `0` uploaded

# Open points

- Die eigentliche Bildregenerierung laeuft erst in der ausgerollten Shop-API, weil dort `xtFramework/admin/main.php` und `MediaImages` vorhanden sein muessen.
- Falls der Shop einen abweichenden Original-Bildpfad nutzt, muss die Zielpfad-Erkennung entsprechend angepasst werden.

# Validation steps

- `docker compose exec -T php php -l wela-api/index.php`
- `docker compose exec -T php php -l wela-api/xt_image_helpers.php`
- `docker compose exec -T php php -l src/Web/Repository/ImageFileRepository.php`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync -e "SELECT ... FROM images_file; UPDATE images_file SET upload = 1, uploaded_at = NULL, last_error = NULL; SELECT ... FROM images_file;"`

# Recommended next step

Die aktualisierte `wela-api` in den XT-Shop deployen und danach den Bild-Upload ueber die Admin-Oberflaeche erneut anstossen.
