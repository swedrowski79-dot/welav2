## Task

SEO-URL-Generierung so korrigieren, dass `url_md5` immer exakt aus dem geschriebenen `url_text` berechnet wird.

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `config/xt_write.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtCategoryWriter.php`
- `wela-api/index.php`

## Changed files

- `src/Service/XtProductWriter.php`
- `src/Service/XtCategoryWriter.php`
- `docs/agent-results/2026-04-17-seo-url-md5-fix.md`

## Summary

- Die Ursache war die doppelte Berechnung derselben SEO-URL:
  - einmal fuer `url_text`
  - ein zweites Mal fuer `url_md5`
- Da die URL-Reservierung Zustand im Writer veraendert, konnte der zweite Aufruf einen anderen String liefern als der erste.
- Produkt- und Kategorie-Writer cachen die erzeugte SEO-URL jetzt pro Entity und Sprache.
- Zusaetzlich wird `url_md5` unmittelbar vor dem Write explizit aus dem finalen `url_text` gesetzt.

## Open points

- Bereits falsch geschriebene SEO-Zeilen werden dadurch nicht rueckwirkend repariert; der Fix gilt fuer neu erzeugte bzw. neu geschriebene SEO-URLs.

## Validation steps

- `php -l src/Service/XtProductWriter.php`
- `php -l src/Service/XtCategoryWriter.php`
- Codepfad geprueft:
  - `url_text` wird einmal reserviert
  - `url_md5 = md5(url_text)`

## Recommended next step

Die betroffenen SEO-Eintraege erneut exportieren, damit die falschen `url_md5`-Werte im Shop ersetzt werden.
