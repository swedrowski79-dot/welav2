# Task

Raise the PHP `memory_limit` from `128M` to `1024M` for the current local Docker/PHP runtime and document where the value is configured.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `Dockerfile`
- `docker-compose.yml`

# Changed files

- `Dockerfile`
- `docker/php/conf.d/zz-memory-limit.ini`
- `docs/agent-results/2026-04-15-php-memory-limit-increase.md`

# Summary

- Added a dedicated PHP runtime override file at:
  - `docker/php/conf.d/zz-memory-limit.ini`
- Set:
  - `memory_limit = 1024M`
- Updated `Dockerfile` to copy that file into the active PHP config directory inside the container:
  - `/usr/local/etc/php/conf.d/zz-memory-limit.ini`

This is the repository-relevant location for the current Dockerized local PHP runtime.

# Open points

- None.

# Validation steps

- Executed:
  - `docker compose up -d --build php`
  - `docker compose exec -T php php -i | rg "memory_limit =>|Loaded Configuration File|Scan this dir for additional .ini files|Additional .ini files parsed"`
  - `docker compose exec -T php php -r 'echo ini_get("memory_limit"), PHP_EOL;'`
- Observed:
  - additional config directory: `/usr/local/etc/php/conf.d`
  - effective runtime value: `1024M`

# Recommended next step

Use the rebuilt PHP container normally; the new runtime limit is already active.
