## Task

Produkte, die in AFS nicht mehr online sind, im Shop automatisch offline nehmen und die beiden XT-Shop-Fehler eingrenzen bzw. beheben:

- Hauptkategorien erscheinen erst nach manuellem Kategorie-Eingriff im Backend
- Sprach-SEO-URLs von Produkten liefern 404, bis ein Produkt im Backend erneut gespeichert wird

## Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/WelaApiClient.php`
- `run_export_queue.php`
- `config/xt_write.php`
- `wela-api/index.php`
- `wela-api/README.md`
- `database.sql`

## Changed files

- `src/Service/ProductDeltaService.php`
- `src/Service/WelaApiClient.php`
- `run_export_queue.php`
- `wela-api/index.php`
- `wela-api/README.md`
- `docs/agent-results/2026-04-17-shop-refresh-and-offline-cleanup.md`

## Summary

- Die Live-Entfernung fuer fehlende Mirror-Zustaende wurde von Spezialfaellen auf den vom Nutzer gewuenschten Normalfall erweitert:
  - Produkte, die nicht mehr in der aktuellen AFS-Stage vorhanden sind, werden jetzt generell als Offline-Update in die Queue gestellt.
  - Das gleiche gilt jetzt auch fuer Kategorien, damit veraltete Live-Kategorien nach einem Reset nicht dauerhaft online bleiben.
- Der Delta-Lauf hat damit direkt `1286` Produkt-Entfernungen erkannt und in die Queue gestellt (`mirror_live_removed = 1286`).
- Fuer den Shop-Zustand wurde eine neue API-Aktion `refresh_shop_state` vorbereitet:
  - sie leert `cache/` und `templates_c/`
  - `run_export_queue.php` ruft diesen Refresh nach erfolgreichen Produkt-/Kategorie-Exports automatisch auf
  - falls die Ziel-API noch nicht auf dem neuen Stand ist, blockiert der Worker nicht, sondern loggt nur eine Warnung
- Die 404-Sprachfehler wurden auf Datenebene weiter eingegrenzt:
  - `xt_seo_url`, `xt_products_description` und die Kategoriepfade fuer `W02-000` sind vorhanden
  - das Verhalten passt deshalb stark zu fehlender XT-Nacharbeit nach API-Schreibvorgaengen (Cache/SEO-Rebuild), nicht zu fehlenden Exportdaten
- Die Hauptkategorie-Probleme passen ebenfalls zu diesem Bild; zusaetzlich gibt es aktuell noch `14` veraltete Live-Kategorien, die durch die neue Delta-Regel kuenftig ebenfalls offline gehen

## Open points

- Fuer den automatischen Shop-Refresh ist ein erneutes Deployment von `wela-api/` auf dem Zielshop noetig.
- Ein nachgelagerter Worker-Lauf konnte nicht voll weitergeprueft werden, weil MySQL derzeit mit `The table 'export_queue' is full` abbricht.
- Aktueller Datenstand nach dem letzten Delta:
  - `stale_live_products = 124`
  - `stale_live_categories = 14`
  - diese sind noch nicht alle abgearbeitet, weil die Queue vorher vollgelaufen ist

## Validation steps

- `docker compose exec -T php php -l /app/src/Service/ProductDeltaService.php`
- `docker compose exec -T php php -l /app/src/Service/WelaApiClient.php`
- `docker compose exec -T php php -l /app/run_export_queue.php`
- `docker compose exec -T php php -l /app/wela-api/index.php`
- `docker compose exec -T php php /app/run_delta.php`
- Live-Pruefung von `xt_seo_url`, `xt_products_description`, `xt_products_to_categories`, `xt_categories`, `xt_categories_description` fuer `W02-000`
- Frontend-Pruefung:
  - deutsche `W02-000` URL liefert `200`
  - englische/franzoesische/niederlaendische SEO-Produktseiten liefern weiterhin `404`, obwohl die SEO-Zeilen vorhanden sind

## Recommended next step

Zuerst `wela-api/` auf dem Zielshop neu deployen, dann den Export weiterlaufen lassen, bis die neue Queue fuer stale Produkte/Kategorien abgearbeitet ist. Falls die Queue erneut stoppt, muss zusaetzlich der MySQL-Blocker `export_queue is full` beseitigt werden.
