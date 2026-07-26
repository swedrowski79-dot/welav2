# Vergleich shop2 / shop_norm: Zugriff blockiert

## Task

Die XT-Datenbanken `shop2` und `shop_norm` auf `10.0.1.151:3307` für HA-000 und seine Slaves vergleichen.

## Files read

- keine Projektdateien; direkter, vom Nutzer freigegebener Read-only-Verbindungsversuch

## Changed files

- `docs/agent-results/2026-07-20-shop2-shop-norm-compare-access-blocked.md`

## Summary

Der MySQL-Server ist erreichbar, lehnt den angegebenen Login jedoch ab:

```text
Access denied for user 'root'@'10.0.1.151' (using password: NO)
```

Auch der Zugriff aus dem Docker-Netz wurde abgewiesen. Es konnten daher keine Tabellen oder Produktdaten gelesen werden.

## Open points

- Einen für Remotezugriff freigegebenen Read-only-MySQL-Benutzer oder die korrekten Zugangsdaten bereitstellen.

## Validation steps

- Verbindungsversuch aus dem MySQL-Container ausgeführt.
- Verbindungsversuch aus einem Host-Netzwerk-Container ausgeführt.
- Beide erreichten den Server, wurden aber authentifizierungsseitig abgewiesen.

## Recommended next step

Einen Benutzer mit `SELECT` auf `shop2` und `shop_norm` für den zugreifenden Host freigeben. Danach werden Schema, HA-000, seine Slaves und die drei Attributtabellen direkt verglichen.
