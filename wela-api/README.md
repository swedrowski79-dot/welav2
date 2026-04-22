# wela-api

Dieses Verzeichnis kann in den Shop unter `wela-api` kopiert werden.

## Deployment

1. `config.php.example` nach `config.php` kopieren
2. in `config.php` die lokalen XT-Shop-DB-Zugangsdaten eintragen
3. den gleichen `api_key` wie in der Sync-App verwenden
4. danach sollte die API erreichbar sein unter:

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

`upload_document_file` schreibt eine per Base64 uebergebene Datei in das Zielverzeichnis im Shop. Ohne expliziten `target_path` wird `document_upload_path` aus `config.php` verwendet; wenn auch dieser leer ist, faellt die API auf `media/files` unter dem Shop-Root zurueck.

`fetch_rows` liefert paginierte Read-only-Zeilen aus freigegebenen XT-Tabellen und wird fuer den XT-Mirror-Refresh verwendet.

Antwortbeispiel:

```json
{
  "ok": true,
  "message": "XT-API und Datenbank erreichbar."
}
```
