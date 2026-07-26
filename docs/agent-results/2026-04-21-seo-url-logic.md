# Task

SEO-URL-Generierung fuer Produkte und Kategorien exakt an die xtFramework-Logik aus `class.seo_modRewrite.php` angleichen und bei URL-Aenderungen Redirects in `xt_seo_url_redirect` anlegen.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `config/xt_write.php`
- `config/languages.php`
- `src/Service/AbstractXtWriter.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/WelaApiClient.php`
- `wela-api/index.php`
- `wela-api/README.md`
- `/root/.copilot/session-state/a99ae03d-ddea-4e0d-9c98-8bbfa7afa770/plan.md`

# Changed files

- `wela-api/index.php`
- `wela-api/seo_helpers.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtCategoryWriter.php`
- `docs/agent-results/2026-04-21-seo-url-logic.md`

# Summary

- Auto-generierte SEO-URLs werden jetzt serverseitig in `wela-api` erzeugt statt mit der bisherigen clientseitigen Slug-Logik.
- Die neue Logik liest live aus XT:
  - `xt_seo_stop_words`
  - `xt_config`
  - `xt_products_to_categories`
  - `xt_seo_url`
- `filterAutoUrlText()` wurde entlang der vorgegebenen XT-Schritte umgesetzt:
  - `trim()`
  - `/` nach `-`
  - Worttrennung per `preg_split("/[\\s,.]+/")`
  - Stopwords/Replace-Regeln aus `xt_seo_stop_words`
  - Stopword-Entfernung nur bei mehr als einem Wort
  - Zusammenbau mit `-`
  - Replace-Regeln per `preg_replace`
  - Entfernen nicht erlaubter Zeichen mit `/[^a-zA-Z0-9\\-\\/\\.\\_]/u`
  - Mehrfach-`-` reduzieren
  - abschliessendes `-` entfernen
  - Fallback `{class}-{id}-empty`
- Produkt-URLs nutzen bei aktivem `_SYSTEM_SEO_PRODUCTS_CATEGORIES=true` jetzt die Master-Kategorie aus `xt_products_to_categories`.
- Kategorie-URLs bauen ihren Parent-Pfad aus dem bereits vorhandenen Parent-SEO-Eintrag in `xt_seo_url`.
- Dubletten werden XT-konform als `slug1`, `slug2`, `slug3` behandelt, nicht als `slug-1`.
- Die Writer markieren Produkt- und Kategorie-SEO jetzt nur noch als `auto_generate`; `url_text` und `url_md5` setzt die API selbst.
- Wenn fuer dieselbe aktive Entitaet bereits ein anderer `xt_seo_url` existiert, legt die API jetzt zusaetzlich einen Redirect in `xt_seo_url_redirect` an:
  - alte URL nach `url_text`
  - neue URL nach `url_text_redirect`
  - beide MD5-Werte gesetzt
  - `is_deleted=0`, Zaehler auf `0`, `last_access` auf aktuellen Timestamp
- Fuer `xt_seo_url_redirect.master_key` wird bei Bedarf automatisch der naechste Schluessel ermittelt, falls die Spalte nicht auto-increment ist.

# Open points

- Bestehende Produkt- und Kategorie-SEO-Zeilen im Shop werden erst beim naechsten Export auf die neue Logik umgestellt.
- Wenn Parent-Kategorien im Shop noch alte SEO-Pfade tragen, verwenden neue Child-URLs zunaechst ebenfalls diesen Parent-Pfad, bis die Kategorien selbst neu exportiert wurden.
- Fuer Content- oder Hersteller-SEO ist die gemeinsame Auto-Generate-Logik vorbereitet, im aktuellen Repo aber noch kein eigener Schreiber daran angeschlossen.
- Die Zielinstanz verwendet die Redirect-Tabelle `xt_seo_url_redirect`; darauf ist die Implementierung jetzt fest verdrahtet.

# Validation steps

- `docker compose exec -T php php -l wela-api/seo_helpers.php`
- `docker compose exec -T php php -l wela-api/index.php`
- `docker compose exec -T php php -l src/Service/XtProductWriter.php`
- `docker compose exec -T php php -l src/Service/XtCategoryWriter.php`
- XT-Config-Spotcheck via API:
  - `_SYSTEM_SEO_URL_LANG_BASED=true`
  - `_SYSTEM_SEO_PRODUCTS_CATEGORIES=true`
- XT-Stopword-Spotcheck via API:
  - `xt_seo_stop_words` liefert `ALL`-Replace-Regeln und sprachspezifische Stopwords
- In-Memory-Simulation der XT-Tabellen mit Beispiel-Ergebnissen:
- MySQL-Simulation der Redirect-Logik mit bestehendem aktivem SEO-Eintrag:
  - aktiver `xt_seo_url` wurde von `de/alte-url` auf `de/neue-url` aktualisiert
  - `xt_seo_url_redirect` erhielt einen neuen Redirect-Eintrag fuer `de/alte-url -> de/neue-url`
- In-Memory-Simulation der XT-Tabellen mit Beispiel-Ergebnissen:
  - `de/absaugarme-zubehoer/absaugarme-edelstahl/absaugarm-typ-lpe-3000-160-k`
  - `de/absaugarme-zubehoer/absaugarme-lackiert/absaugarm-75-1-0-m-lackiert-haengend`
  - `de/absaugarme-zubehoer/absaugarme-lackiert/absaugarm-zubehoer1`
  - `de/absaugarme-zubehoer/absaugarme-aus-edelstahl`

# Recommended next step

Die betroffenen Kategorien und Produkte erneut exportieren und anschliessend den XT-Mirror neu ziehen, damit die LPE-Slave-URLs mit der neuen XT-Logik im Shop und im Mirror sichtbar werden.
