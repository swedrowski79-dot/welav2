# Zielordner `wela-api-xt` nicht verfügbar

## Task

Die verknüpfte API unter `wela-api-xt` für die eindeutige Child-Attributidentität prüfen und anpassen.

## Files read

- `wela-api/src/ProductSyncService.php`
- `wela-api/index.php`
- relevante frühere Ergebnisberichte unter `docs/agent-results/`

## Changed files

- `docs/agent-results/2026-07-20-wela-api-xt-target-missing.md`

## Summary

Der Ordner `wela-api-xt` ist auf dem Host und im PHP-Container leer. Es gibt daher keine Ziel-Dateien wie `wela-api-xt/src/ProductSyncService.php` oder `wela-api-xt/index.php`, die angepasst werden könnten.

Die vorhandene lokale API-Implementierung liegt in `wela-api/`. Dort verwendet die Child-Attributlogik derzeit noch `attributes_model` allein als Identität. Für die gewünschte Regel muss sie auf die Kombination aus `attributes_model` und `attributes_parent` umgestellt werden.

## Open points

- Den Inhalt oder die Einbindung von `wela-api-xt` wiederherstellen.
- Danach den API-Upsert und das Prefetching für Child-Attribute auf den zusammengesetzten Schlüssel umstellen.

## Validation steps

- Host geprüft: `wela-api-xt` enthält keine Dateien.
- Container geprüft: `/app/wela-api-xt` enthält ebenfalls keine Dateien.

## Recommended next step

Den korrekten API-Quellordner wieder einbinden oder bestätigen, dass stattdessen `wela-api/` das zu ändernde und auszurollende Ziel ist.

## Nachprüfung

Der API-Ordner ist inzwischen wieder eingebunden und enthält die erwarteten Dateien. Er ist jedoch als `nobody:nogroup` eingebunden und nicht beschreibbar; ein Schreibberechtigungsversuch für `src/ProductSyncService.php` wird mit `Operation not permitted` abgelehnt. Der zusammengesetzte Child-Schlüssel kann nach Freigabe der Schreibrechte umgesetzt werden.
