# Implementierungshinweise

## 1. Stage-Datenbank

Empfohlene Kernbereiche:

- `raw_afs_articles`
- `raw_afs_categories`
- `raw_afs_documents`
- `raw_extra_articles`
- `raw_extra_categories`
- `stage_products`
- `stage_categories`
- `stage_product_attributes`
- `stage_product_media`
- `stage_product_documents`
- `xt_mirror_*`

## 2. Identity-Auflösung

Beim Start des XT-Writers immer zuerst laden:

- `xt_products.external_id -> products_id`
- `xt_categories.external_id -> categories_id`
- `xt_media.external_id -> id`

Diese Maps im Speicher halten.

## 3. Delta-Logik

Zuerst Stage-Hash bilden.
Dann gegen XT-Mirror vergleichen.
Dann nur `insert` oder `update_if_changed` ausführen.

## 4. SEO-Logik

### Bestehender SEO-Datensatz gefunden
- `url_text` unverändert lassen
- `url_md5` unverändert lassen
- nur `meta_title`, `meta_description`, `meta_keywords` aktualisieren

### Kein SEO-Datensatz gefunden
- Kategoriepfad rekursiv ermitteln
- Slug erzeugen
- Kollision prüfen
- URL speichern

## 5. Produktarten

Ableitung aus AFS `Zusatzfeld07`:

- leer -> `standard`
- `Master` -> `master`
- sonst -> `slave`, Wert = Master-Artikelnummer

## 6. Replace-Strategie für Relationen

Für folgende Tabellen ist oft `replace_by` sauberer als diff-basierte Einzelupdates:

- `xt_products_to_categories`
- `xt_media_link`
- `xt_plg_products_to_attributes`

## 7. SEO-Linktypen

Annahme für XT basierend auf Beispieldaten:

- `link_type = 1` -> Produkt
- `link_type = 2` -> Kategorie

Im Writer trotzdem als Konstante konfigurierbar halten.


## Update: Translation-Modell (Variante B)

Die Zusatzdaten werden jetzt als zeilenbasierte Übersetzungstabellen behandelt:
- `extra.article_translations`
- `extra.category_translations`

Deutsch (`de`) läuft dabei über dieselbe Übersetzungslogik wie `en`, `fr` und `nl`.
AFS bleibt die sprachneutrale Stammquelle und dient nur noch als Fallback, wenn in den Extra-Daten ein Sprachdatensatz unvollständig ist.


Update v3:
- manufacturer entfernt, da nicht in den Übersetzungsquellen vorhanden.
- seo_slug entfernt, da nicht in den Übersetzungsquellen vorhanden.
- SEO-URLs werden ausschließlich generiert, wenn in XT noch keine URL existiert; bestehende url_text/url_md5 bleiben unverändert.
