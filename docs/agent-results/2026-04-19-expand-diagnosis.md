## Task

Pruefen, ob der `expand`-Schritt haengt oder nur sehr lange laeuft.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `run_expand.php`
- `run_full_pipeline.php`
- `config/pipeline.php`
- `config/delta.php`
- `src/Monitoring/SyncMonitor.php`
- `src/Service/DeltaRunnerService.php`
- `src/Service/ProductDeltaService.php`
- `src/Web/Repository/SyncLauncher.php`

## Changed files

- `docs/agent-results/2026-04-19-expand-diagnosis.md`

## Summary

- Der `expand`-Schritt wirkte im Frontend wie ein Haenger, ist aber nicht im eigentlichen Expand-Teil stecken geblieben.
- Die Run-Logs zeigen: Expand-Definitionen wurden abgeschlossen, danach lief mindestens das Kategorie-Delta.
- Gleichzeitig gibt es keinen sauber beendeten `expand`-/`full_pipeline`-Run, obwohl die Prozesse aus dem Weblauf nicht mehr aktiv waren.
- Die direkte Diagnose in MySQL zeigt einen Datenbank-Blocker:
  - eine lange aktive InnoDB-Transaktion auf `INSERT INTO export_queue ...`
  - Status `waiting for handler commit`
  - ein neuer `expand`-Start blockiert bereits beim `sync_runs`-Update dahinter
- Das Problem sitzt damit derzeit eher bei Commit/Locking rund um die Delta-/Queue-Schreibphase als bei den Expand-Definitionen selbst.

## Open points

- Warum die Transaktion auf `export_queue` in `waiting for handler commit` stehen bleibt, ist noch nicht abschliessend geklaert.
- Zu pruefen ist als Naechstes:
  - Batch-/Transaktionsverhalten beim Queue-Insert
  - moegliche zu grosse Payloads im Produkt-Delta
  - InnoDB-/Container-Ressourcen waehrend des Commit-Pfads

## Validation steps

- `docker compose exec -T mysql mysql -uroot -proot -Nse "SELECT id, run_type, status, started_at, ended_at, message, context_json FROM sync_runs ORDER BY id DESC LIMIT 10;" stage_sync`
- `docker compose exec -T mysql mysql -uroot -proot -Nse "SELECT id, sync_run_id, level, created_at, message FROM sync_logs ORDER BY id DESC LIMIT 50;" stage_sync`
- `docker compose ps`
- `ps -ef | rg "run_expand.php|run_full_pipeline.php|run_delta.php|run_export_queue.php|php /app"`
- `docker compose exec -T php sh -lc 'ls -l /tmp && echo --- && for f in /tmp/full_pipeline.log /tmp/expand.log /tmp/delta.log /tmp/export_queue_worker.log; do if [ -f "$f" ]; then echo FILE:$f; tail -n 80 "$f"; echo ---; fi; done'`
- `docker compose exec -T mysql mysql -uroot -proot -Nse "SELECT trx_id, trx_state, trx_started, trx_mysql_thread_id, trx_query FROM information_schema.innodb_trx ORDER BY trx_started;" stage_sync`
- `docker compose exec -T mysql mysql -uroot -proot -e "SHOW ENGINE INNODB STATUS\G" stage_sync`
- `docker compose exec -T mysql mysql -uroot -proot -Nse "SELECT COUNT(*) FROM export_queue WHERE status='pending'; SELECT COUNT(*) FROM export_queue WHERE status='processing';" stage_sync`

## Recommended next step

Gezielt die Delta-/Queue-Schreibphase analysieren und pruefen, ob die Produkt-Payloads oder der Queue-Insert-Commit den MySQL-Blocker ausloesen.
