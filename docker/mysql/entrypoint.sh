#!/bin/bash

set -euo pipefail

PERSISTENT_DATADIR="/var/lib/mysql-persistent"
RAMDISK_DATADIR="/mnt/mysql-ram"
RAMDISK_ENABLED="${MYSQL_RAMDISK_ENABLED:-1}"

mkdir -p "${PERSISTENT_DATADIR}" "${RAMDISK_DATADIR}"
chown -R mysql:mysql "${PERSISTENT_DATADIR}" "${RAMDISK_DATADIR}"

if [[ "${RAMDISK_ENABLED}" == "1" ]]; then
    echo "[mysql-entrypoint] RAM-disk mode enabled; copying persisted data into tmpfs datadir."

    persistent_size_kb="$(du -sk "${PERSISTENT_DATADIR}" | awk '{print $1}')"
    available_size_kb="$(df -Pk "${RAMDISK_DATADIR}" | awk 'NR==2 {print $4}')"

    if [[ -n "${persistent_size_kb}" && -n "${available_size_kb}" && "${persistent_size_kb}" -ge "${available_size_kb}" ]]; then
        echo "[mysql-entrypoint] RAM-disk too small for persisted data (${persistent_size_kb} KB required, ${available_size_kb} KB available)." >&2
        echo "[mysql-entrypoint] Increase MYSQL_RAMDISK_SIZE_BYTES before starting the ramdisk test mode." >&2
        exit 1
    fi

    if [[ -n "$(ls -A "${PERSISTENT_DATADIR}" 2>/dev/null)" && -z "$(ls -A "${RAMDISK_DATADIR}" 2>/dev/null)" ]]; then
        cp -a "${PERSISTENT_DATADIR}/." "${RAMDISK_DATADIR}/"
        chown -R mysql:mysql "${RAMDISK_DATADIR}"
    fi

    exec /usr/local/bin/docker-entrypoint.sh "$@" --datadir="${RAMDISK_DATADIR}"
fi

echo "[mysql-entrypoint] RAM-disk mode disabled; using persisted datadir."
exec /usr/local/bin/docker-entrypoint.sh "$@" --datadir="${PERSISTENT_DATADIR}"
