## Task

Schnittstelle komplett pruefen, die Pipeline laufen lassen, Exportfehler beheben und den Laufzustand fuer die Uebergabe in den Shop dokumentieren.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docker-compose.yml`
- `config/pipeline.php`
- `config/delta.php`
- `config/sources.php`
- `config/xt_write.php`
- `run_full_pipeline.php`
- `run_delta.php`
- `run_export_queue.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/XtProductWriter.php`
- `src/Service/XtMediaDocumentWriter.php`
- `src/Service/XtCategoryWriter.php`
- `src/Service/WelaApiClient.php`
- `wela-api/index.php`
- `wela-api/README.md`
- relevante fruehere Reports unter `docs/agent-results/`

## Changed files

- `config/xt_write.php`
- `src/Service/ExportQueueWorker.php`
- `wela-api/index.php`
- `docs/agent-results/2026-04-20-schnittstelle-vollpruefung-und-export-fix.md`

## Summary

- Die Vollpipeline lief erfolgreich durch:
  - `import_all`
  - `merge`
  - `xt_mirror`
  - `expand`
  - `export_queue_worker`
- Danach wurden Delta und Export-Worker mehrfach erneut ausgefuehrt, um offene Exportreste nachzuziehen.
- Zwei Repo-seitige Ursachen wurden behoben:
  - `config/xt_write.php`: `products_master_slave_order` wird nicht mehr vom Sync-Client in jeden Produktpayload geschrieben, weil die aktuell angebundene XT-API dieses Feld bei normalen Produktupdates ablehnt.
  - `src/Service/ExportQueueWorker.php`: stale Claims werden jetzt nach dem Retry-Fenster statt mit einem starren 10-Minuten-Mindesttimeout wieder freigegeben.
- Fuer die mitgelieferte `wela-api` wurde der eigentliche Insert-Fix abgesichert:
  - `wela-api/index.php` setzt `products_master_slave_order` bei echten Produkt-Neuanlagen serverseitig auf `0`, wenn der Client das Feld nicht mitsendet.

## Open points

- Der aktuelle Shop-Endpunkt unter `XT_API_URL` ist noch nicht auf dem Stand des hier gepatchten `wela-api/index.php`.
- Dadurch bleibt ein externer Widerspruch bestehen:
  1. ohne `products_master_slave_order` schlagen neue Produkte mit `SQLSTATE[HY000]: General error: 1364 Field 'products_master_slave_order' doesn't have a default value` fehl
  2. mit `products_master_slave_order` antwortet die aktive API mit `Unzulaessige XT-Feldbelegung.`
- Deshalb ist der Lauf technisch weitgehend bereinigt, aber noch nicht komplett shop-sauber abgeschlossen. Letzter beobachteter Queue-Stand:

| entity_type | status | count |
| --- | --- | ---: |
| category | done | 249 |
| document | done | 7847 |
| document | error | 24 |
| media | done | 522 |
| media | error | 110 |
| product | done | 5388 |
| product | error | 100 |
| product | pending | 50 |

- Die verbleibenden `media`-/`document`-Fehler haengen direkt an den 50 noch nicht insertbaren Produkten, weil deren XT-Referenzen fehlen.

## Validation steps

- `docker compose up -d --build`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql`
- `docker compose exec -T php php -v`
- `docker compose exec -T php php -m | grep -E 'pdo_mysql|pdo_sqlite|pdo_sqlsrv|sqlsrv'`
- `docker compose exec -T php php /app/run_full_pipeline.php`
- `docker compose exec -T php php /app/run_export_queue.php`
- `docker compose exec -T php php /app/run_delta.php`
- gezielte Produkt-Reproduktion gegen die aktive XT-API:
  - bestaetigt: Standardprodukt-Update funktioniert nach Client-Fix
  - bestaetigt: Produkt-Neuanlage scheitert an der aktuell deployten Shop-API-Inkonsistenz
- `refresh_shop_state` wurde erfolgreich gegen den Shop-Endpunkt ausgefuehrt

## Recommended next step

- Die gepatchte `wela-api/index.php` auf dem aktiven Shop-Endpunkt deployen und danach `run_export_queue.php` erneut laufen lassen, bis die restlichen `50` Produkt- sowie die davon abhaengigen Medien-/Dokument-Eintraege abgearbeitet sind.
