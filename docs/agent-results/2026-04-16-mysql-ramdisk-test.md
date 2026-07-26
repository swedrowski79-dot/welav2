# Task

Add a temporary Docker test mode that starts MySQL from a RAM disk by copying the persisted database into tmpfs at container startup.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `README.md`
- `docker-compose.yml`
- `Dockerfile`
- `Makefile`

# Changed files

- `docker-compose.yml`
- `docker/mysql/entrypoint.sh`
- `Makefile`
- `README.md`
- `docs/agent-results/2026-04-16-mysql-ramdisk-test.md`

# Summary

- Added an optional RAM-disk mode for the `mysql` service.
- The MySQL container now starts through `docker/mysql/entrypoint.sh`.
- In normal mode, MySQL runs from the persistent datadir at `/var/lib/mysql-persistent`.
- In RAM-disk mode (`MYSQL_RAMDISK_ENABLED=1`), the entrypoint:
  - checks available tmpfs capacity
  - copies the persisted datadir into `/mnt/mysql-ram`
  - starts MySQL with `--datadir=/mnt/mysql-ram`
- Added a `make up-ramdisk` helper.
- Documented the mode in `README.md`.

# Open points

- This is explicitly a test mode only. Runtime changes are not synced back from RAM to the persistent Docker volume.
- The current persistent MySQL volume is about `6.8G`, so the RAM disk must be sized above that before startup.
- The current test run was started with `MYSQL_RAMDISK_SIZE_BYTES=10737418240` (10 GiB).

# Validation steps

- Ran:
  - `bash -n docker/mysql/entrypoint.sh`
  - `docker compose config`
  - `MYSQL_RAMDISK_ENABLED=1 MYSQL_RAMDISK_SIZE_BYTES=10737418240 docker compose up -d mysql`
- Verified live container state:
  - `mysqladmin ping` returned `mysqld is alive`
  - `SELECT @@datadir;` returned `/mnt/mysql-ram/`
  - `df -h /mnt/mysql-ram` showed `tmpfs`
  - `SHOW DATABASES;` returned `stage_sync` and MySQL system schemas

# Recommended next step

Run your timing/import/export test against the currently active RAM-disk-backed MySQL container and compare it against a normal restart without `MYSQL_RAMDISK_ENABLED=1`.
