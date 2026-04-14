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

Antwortbeispiel:

```json
{
  "ok": true,
  "message": "XT-API und Datenbank erreichbar."
}
```
