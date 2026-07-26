## Task

`stage_sync` komplett leeren (ohne `afs_extras`), danach die gesamte Pipeline erneut laufen lassen und pruefen, ob die Shop-Uebertragung sauber durchgeht.

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
- `run_export_queue.php`
- `run_xt_mirror.php`
- `src/Service/ExportQueueWorker.php`
- `src/Service/WelaApiClient.php`
- `wela-api/index.php`
- relevante fruehere Reports unter `docs/agent-results/`

## Changed files

- `docs/agent-results/2026-04-20-stage-reset-and-full-pipeline-recheck.md`

## Summary

- `stage_sync` wurde vollstaendig geleert:
  - RAW
  - STAGE
  - Export Queue / Export State
  - XT Mirror / Snapshots
  - Monitoring (`sync_runs`, `sync_logs`, `sync_errors`)
- `afs_extras` blieb unberuehrt.
- Danach lief `run_full_pipeline.php` von null erfolgreich durch.
- Anschliessend wurde `run_xt_mirror.php` noch einmal ausgefuehrt, damit der lokale Mirror auch den nach dem Export erreichten Live-Zustand zeigt.

Verifizierter Endzustand:

| Bereich | Ergebnis |
| --- | --- |
| Pipeline-Runs | `full_pipeline`, `import_all`, `merge`, `xt_mirror`, `expand`, `export_queue_worker` alle `success` |
| Export Queue | nur `done`, keine `pending`, keine `processing`, keine `error` |
| Sync Errors | `0` |
| Stage vs XT Mirror | `0` fehlende Online-Produkte, `0` fehlende Medien-Referenzen, `0` fehlende Dokument-Referenzen |
| Shop Cache Refresh | erfolgreich ueber `refresh_shop_state` |

Queue-Endstand:

| entity_type | status | count |
| --- | --- | ---: |
| category | done | 220 |
| document | done | 2957 |
| media | done | 55 |
| product | done | 3122 |

## Open points

- Keine fuer diesen Lauf.
- Ein API-Update ist fuer den aktuellen erfolgreichen Endzustand **nicht erforderlich**.

## Validation steps

- `docker compose up -d --build`
- `docker compose exec -T mysql mysql -uroot -proot stage_sync ... TRUNCATE ...`
- `docker compose exec -T php php /app/run_full_pipeline.php`
- `docker compose exec -T php php /app/run_xt_mirror.php`
- Queue-Pruefung ueber `export_queue`
- Fehlerpruefung ueber `sync_errors`
- Shop-Abgleich:
  - `stage_products` vs `xt_mirror_products`
  - `stage_product_media` vs `xt_mirror_products`
  - `stage_product_documents` vs `xt_mirror_products`
- `refresh_shop_state` ueber die XT-API

## Recommended next step

- Keiner unmittelbar noetig; bei kuenftigen End-to-End-Pruefungen den XT-Mirror nach dem Export erneut laufen lassen, wenn der Live-Zustand mit dem lokalen Mirror verglichen werden soll.
