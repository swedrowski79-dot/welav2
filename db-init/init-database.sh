#!/bin/sh
set -eu

DB_HOST="${STAGE_DB_HOST:-mysql}"
DB_PORT="${STAGE_DB_PORT:-3306}"
DB_NAME="${STAGE_DB_NAME:-stage_sync}"
DB_USER="${MYSQL_ROOT_USER:-root}"
DB_PASSWORD="${MYSQL_ROOT_PASSWORD:-root}"
SCHEMA_FILE="${SCHEMA_FILE:-/schema/database.sql}"
MAX_ATTEMPTS="${DB_INIT_MAX_ATTEMPTS:-60}"

if [ ! -r "$SCHEMA_FILE" ]; then
    echo "[db-init] Schema nicht lesbar: $SCHEMA_FILE" >&2
    exit 1
fi

echo "[db-init] Warte auf MySQL unter ${DB_HOST}:${DB_PORT} ..."
attempt=1
while ! mysqladmin ping \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASSWORD" \
    --silent >/dev/null 2>&1; do
    if [ "$attempt" -ge "$MAX_ATTEMPTS" ]; then
        echo "[db-init] MySQL ist nach ${MAX_ATTEMPTS} Versuchen nicht erreichbar." >&2
        exit 1
    fi
    attempt=$((attempt + 1))
    sleep 2
done

echo "[db-init] Stelle Datenbank ${DB_NAME} sicher ..."
mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASSWORD" \
    --execute="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "[db-init] Spiele ${SCHEMA_FILE} in ${DB_NAME} ein ..."
mysql \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --password="$DB_PASSWORD" \
    --database="$DB_NAME" \
    < "$SCHEMA_FILE"

echo "[db-init] Datenbankschema ist vollständig vorhanden."
