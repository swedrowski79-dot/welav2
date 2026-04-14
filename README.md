# AFS + Extra(SQLite) → Stage Projekt (v2)

Dieses Paket ist an den echten SQLite-Aufbau angepasst:

## SQLite Tabellen
- `articles`
- `warengruppen`

## Besonderheiten
- Produkt- und Kategorietexte sind zeilenbasiert pro Sprache
- Attribute liegen aktuell direkt in `articles`
- Attribute werden später per `expand.php` in eigene Stage-Zeilen zerlegt

## Ablauf
1. `database.sql` in deiner MySQL-Stage ausführen
2. Zugangsdaten in `config/sources.php` anpassen
3. `php run_import_all.php`
4. `php run_merge.php`
5. `php run_expand.php`

## Ergebnis
Danach sollten diese Tabellen gefüllt sein:
- `raw_afs_articles`
- `raw_afs_categories`
- `raw_extra_article_translations`
- `raw_extra_category_translations`
- `stage_products`
- `stage_product_translations`
- `stage_categories`
- `stage_category_translations`

## Hinweis
`stage_attribute_translations` wird per `run_expand.php` aus
`stage_product_translations` befüllt.

## Docker

Das Projekt kann lokal mit einem PHP-8.2-Container und MySQL 8 gestartet werden.
AFS (MSSQL) bleibt extern und wird per ENV konfiguriert.

### Starten

```bash
docker compose up -d --build
```

Die Admin-Oberflaeche ist danach lokal erreichbar unter:

```text
http://localhost:8080
```

### Wichtige ENV-Variablen

- `AFS_DB_HOST`
- `AFS_DB_NAME`
- `AFS_DB_USER`
- `AFS_DB_PASS`
- `STAGE_DB_HOST`
- `STAGE_DB_PORT`
- `STAGE_DB_NAME`
- `STAGE_DB_USER`
- `STAGE_DB_PASS`
- `EXTRA_SQLITE_PATH`

Ohne Overrides nutzt das Setup Docker-taugliche Defaults:

- Stage MySQL unter Host `mysql`
- Datenbank `stage_sync`
- Benutzer `stage`
- Passwort `stage`
- SQLite-Datei unter `/app/data/extra.sqlite`

`config/sources.php` liest diese Werte automatisch aus der Umgebung und fällt
für die SQLite-Datei zusätzlich auf vorhandene Projektpfade zurück.

### Schema importieren

```bash
docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql
```

### Schema importieren

```bash
docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql
```

Das Schema enthaelt jetzt auch die Monitoring-Tabellen:

- `sync_runs`
- `sync_logs`
- `sync_errors`

### Pipeline im PHP-Container ausfuehren

```bash
docker compose exec php php run_import_all.php
docker compose exec php php run_merge.php
docker compose exec php php run_expand.php
```

Nach den Laeufen erscheinen Monitoring-Daten direkt im Dashboard.

### Container prüfen

```bash
docker compose exec php php -v
docker compose exec php php -m | grep -E 'pdo_mysql|pdo_sqlite|pdo_sqlsrv|sqlsrv'
```

### Optional per Makefile

```bash
make up
make schema-import
make import-all
make merge
make expand
```

## Admin-Dashboard

Die Weboberflaeche besteht aus:

- Dashboard
- Sync-Laeufe
- Logs
- Fehler
- Stage-DB-Browser
- Konfiguration/Status

Der Stage-Browser arbeitet nur mit einer festen Whitelist erlaubter Tabellen und liest Spalten dynamisch aus dem Schema.
