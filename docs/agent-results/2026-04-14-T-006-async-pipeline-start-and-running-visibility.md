# Task

Implement Ticket `T-006` by hardening async web-triggered pipeline starts and improving running visibility on `/pipeline`.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/tickets/open/T-006-async-pipeline-start-and-running-visibility.md`
- `src/Web/Repository/SyncLauncher.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `docs/agent-results/2026-04-14-import-fix-and-async-pipeline.md`
- `docs/agent-results/2026-04-14-pipeline-status-and-log-reset.md`

# Changed files

- `src/Web/Repository/SyncLauncher.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Controller/PipelineController.php`
- `src/Web/View/pipeline/index.php`
- `docs/tickets/done/T-006-async-pipeline-start-and-running-visibility.md`
- `docs/agent-results/2026-04-14-T-006-async-pipeline-start-and-running-visibility.md`

# Summary

- Hardened async process launch so web-triggered jobs are detached through `nohup` and a valid PID is required.
- Kept the existing status model on `/pipeline` and added clearer progress visibility via the latest log entry of the current/latest run.
- Latest error visibility remains unchanged and continues to use the monitoring tables.

# Open points

- No manual browser test was executed to measure response time during a live long-running job.
- Reachability during long-running runs is supported by the detached launcher approach, but not live-verified in this task step.

# Validation steps

- Executed:
  - `docker compose exec php php -l /app/src/Web/Repository/SyncLauncher.php`
  - `docker compose exec php php -l /app/src/Web/Repository/MonitoringRepository.php`
  - `docker compose exec php php -l /app/src/Web/Controller/PipelineController.php`
  - `docker compose exec php php -l /app/src/Web/View/pipeline/index.php`
- Not executed:
  - live browser start/refresh test with a long-running pipeline job

# Recommended next step

Start a long-running job from `/pipeline`, confirm the redirect returns immediately, then refresh `/pipeline` and verify the progress field changes with the newest log entry while the run stays visible as `running`.
