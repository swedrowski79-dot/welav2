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

`sync_product` schreibt jetzt optional auch Produkt-SEO-URLs in `xt_seo_url`, aber nur wenn fuer das XT-Produkt noch keine SEO-Zeilen existieren. Bestehende SEO-URLs werden dabei nicht ueberschrieben.

`fetch_rows` liefert paginierte Read-only-Zeilen aus freigegebenen XT-Tabellen und wird fuer den XT-Snapshot-Import verwendet.

Antwortbeispiel:

```json
{
  "ok": true,
  "message": "XT-API und Datenbank erreichbar."
}
```
