# wela-api

Dieses Verzeichnis kann in den Shop unter `wela-api` kopiert werden.

## Deployment

1. `config.php.example` nach `config.php` kopieren
2. in `config.php` die lokalen XT-Shop-DB-Zugangsdaten eintragen
3. den gleichen `api_key` wie in der Sync-App verwenden
4. `xt_commerce_root` auf das Shop-Hauptverzeichnis setzen
5. optional Logging aktivieren
6. danach sollte die API erreichbar sein unter:

```text
http://10.0.1.104/wela-api/?action=health
```

## Auth

Jeder Request braucht diese HTTP-Header:

```text
X-Wela-Key: <dein-api-key>
X-Wela-Timestamp: <unix-timestamp>
X-Wela-Signature: <hmac-sha256 ueber "<timestamp>.<body>">
```

## Aktuell implementierte Aktionen

- `health`
- `lookup_map`
- `fetch_rows`
- `upsert_row`
- `delete_rows`
- `sync_product`
- `browse_server_directories`
- `upload_document_file`
- `refresh_shop_state`

`sync_product` schreibt jetzt optional auch Produkt-SEO-URLs in `xt_seo_url`. Bestehende SEO-Zeilen behalten dabei `url_text` und `url_md5`, waehrend `meta_title`, `meta_description` und `meta_keywords` weiter aktualisiert werden. Fehlende SEO-Zeilen werden weiterhin vollstaendig angelegt.

`refresh_shop_state` steht weiterhin als separate API-Aktion zur Verfuegung, wird aber nicht mehr automatisch von der Sync-Schnittstelle nach einem Export-Worker-Lauf aufgerufen.

`browse_server_directories` liefert den Verzeichnisbaum des Shop-Servers zurueck, damit die Sync-App einen Shop-Zielpfad fuer Dokumente ueber die API auswaehlen kann.

`upload_document_file` schreibt eine per Base64 uebergebene Datei in das Zielverzeichnis im Shop. Ohne expliziten `target_path` wird `document_upload_path` aus `config.php` verwendet; wenn auch dieser leer ist, faellt die API auf `media/files` unter dem Shop-Root zurueck. Wenn der Upload nach `media/images/org` geht, nutzt die API einen globalen xt:Commerce-Bootstrap unter `bootstrap/xtcommerce.php` und regeneriert anschliessend die XT-Bildgroessen ueber `MediaImages::processImage($filename, true)`. Dabei wird die XT-Klasse auf `product` oder `category` gesetzt, je nachdem ob der Dateiname bereits als Kategorienbild im Shop hinterlegt ist. Die erzeugten Bilddateien werden anschliessend ueber `getImageTypes()` verifiziert.

## XT Bootstrap

Die API lädt für die Bildverarbeitung bewusst **nicht** den vollständigen `xtCore/main.php`-Frontend-Bootstrap. Stattdessen initialisiert `index.php` einen minimalen, API-sicheren Bild-Bootstrap:

```text
wela-api/bootstrap/xtcommerce.php
```

Voraussetzung dafuer:

```php
'xt_commerce_root' => 'C:\\xampp\\htdocs',
```

oder alternativ eine bereits gesetzte Umgebungsvariable:

```text
XT_COMMERCE_ROOT
```

Fuer einen direkten CLI-Test steht zusaetzlich bereit:

```bash
php wela-api/bin/test-xt-image.php DATEI [product|category]
```

`fetch_rows` liefert paginierte Read-only-Zeilen aus freigegebenen XT-Tabellen und wird fuer den XT-Mirror-Refresh verwendet.

## Logging

Die API kann ein JSON-Lines-Log schreiben, wenn Logging in `config.php` aktiviert ist.

Einfachste Variante:

```php
'logging' => true,
'log_file' => __DIR__ . '/wela-api.log',
```

Alternativ ist auch diese Struktur moeglich:

```php
'logging' => [
    'enabled' => true,
    'file' => __DIR__ . '/wela-api.log',
],
```

Geloggt werden unter anderem:

- eingehender Request und Action
- Upload-Zielpfad
- Dateischreiben
- XT-Bootstrap
- erkannte Bildklasse
- geladene `MediaImages`-Bildtypen
- Ergebnis von `MediaImages::processImage(...)`
- Verifikation der erzeugten Bilddateien
- Response und Exceptions

`content_base64` wird im Log bewusst nicht im Klartext gespeichert.

Antwortbeispiel:

```json
{
  "ok": true,
  "message": "XT-API und Datenbank erreichbar."
}
```
