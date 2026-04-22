# AFS + Extra(SQLite) → Stage Projekt (v3)

Dieses Projekt synchronisiert Daten aus:

- AFS (MSSQL)
- Extra-Datenbank `afs_extras` (MySQL, mehrsprachige Inhalte)

in eine Stage-Datenbank (MySQL).

---

## Architektur

Die Pipeline ist bewusst in mehrere Schritte getrennt:

1. **Import (RAW)**
   - AFS → `raw_afs_*`
   - Extra → `raw_extra_*`

2. **Merge (STAGE)**
   - Zusammenführung in:
     - `stage_products`
     - `stage_product_translations`
     - `stage_categories`
     - `stage_category_translations`

3. **Expand (Attribute)**
   - Attribute werden aus:
     - `attribute_name1..4`
     - `attribute_value1..4`
   - in:
     - `stage_attribute_translations`
   - zerlegt

---

## Wichtige Regeln (NEU)

### AFS Artikel Filter

Es werden **nur folgende Artikel geladen**:

```sql
Internet = 1
AND Art < 255
AND Mandant = 1
```

Diese Regeln sind in `config/sources.php` definiert (`where`).

---

### SELECT-Optimierung

Importer verwenden:

- `columns` → bestimmt SELECT-Spalten
- `where` → bestimmt Filter

Beispiel:

```php
'articles' => [
    'table' => 'Artikel',
    'columns' => [...],
    'where' => [
        'Internet = 1',
        'Art < 255',
        'Mandant = 1',
    ],
]
```

➡️ Kein `SELECT *` mehr  
➡️ Bessere Performance  

---

## AFS Extras

Quelltabellen:

- `articles`
- `warengruppen`

Merkmale:

- Inhalte sind **zeilenbasiert pro Sprache**
- Sprache wird normalisiert auf:
  - `de`, `en`, `fr`, `nl`

Die Befuellung von `afs_extras` aus der alten SQLite-Datei erfolgt separat ueber:

```bash
docker compose exec php php run_sync_afs_extras.php
```

---

## Ablauf

```bash
docker compose exec php php run_import_all.php
docker compose exec php php run_merge.php
docker compose exec php php run_expand.php
```

---

## Ergebnis Tabellen

### RAW
- `raw_afs_articles`
- `raw_afs_categories`
- `raw_extra_article_translations`
- `raw_extra_category_translations`

### STAGE
- `stage_products`
- `stage_product_translations`
- `stage_categories`
- `stage_category_translations`
- `stage_attribute_translations`

---

## Monitoring

Neue Tabellen:

- `sync_runs`
- `sync_logs`
- `sync_errors`

➡️ Werden im Dashboard angezeigt  
➡️ Jeder Lauf wird protokolliert  

---

## Docker

Start:

```bash
docker compose up -d --build
```

Standardstart mit MySQL auf RAM-Disk:

```bash
docker compose up -d --build
```

oder:

```bash
make up-ramdisk
```

Optional mit eigener Groesse:

```bash
MYSQL_RAMDISK_SIZE_BYTES=10737418240 docker compose up -d --build
```

Hinweis:

- Der Container kopiert beim Start die persistente MySQL-Datenbank aus `mysql_data` nach `/mnt/mysql-ram` auf `tmpfs`.
- Die RAM-Disk wird unter `/mnt/mysql-ram` eingehängt.
- Die Default-Groesse ist `8589934592` Bytes (8 GiB) und muss groesser als der aktuelle Inhalt von `mysql_data` sein.
- MySQL läuft in diesem Modus vollständig auf der RAM-Disk.
- Dieser Modus ist weiterhin nur fuer Tests gedacht; Aenderungen waehrend des Laufs werden nicht automatisch zurueck auf die persistente Datenablage synchronisiert.
- Zur Rueckkehr in den Normalmodus den MySQL-Container explizit mit `MYSQL_RAMDISK_ENABLED=0` neu starten oder `make up-persistent` verwenden.

Dashboard:

```text
http://localhost:8080
```

---

## ENV Variablen

- `AFS_DB_HOST`
- `AFS_DB_NAME`
- `AFS_DB_USER`
- `AFS_DB_PASS`
- `STAGE_DB_HOST`
- `STAGE_DB_PORT`
- `STAGE_DB_NAME`
- `STAGE_DB_USER`
- `STAGE_DB_PASS`
- `EXTRA_DB_HOST`
- `EXTRA_DB_PORT`
- `EXTRA_DB_NAME`
- `EXTRA_DB_USER`
- `EXTRA_DB_PASS`
- `EXTRA_SQLITE_PATH` (nur fuer Bootstrap aus Alt-SQLite)

---

## Schema importieren

Bei einem frischen MySQL-Datadir - insbesondere im Standardbetrieb mit RAM-Disk/tmpfs - wird `database.sql` beim ersten Containerstart automatisch importiert.

Manueller Import ist nur noch noetig, wenn ein bereits initialisierter MySQL-Datadir ohne Anwendungsschema nachtraeglich korrigiert werden soll.

```bash
docker compose down
docker compose up -d --build
```

oder explizit:

```bash
docker compose exec -T mysql mysql -uroot -proot stage_sync < database.sql
```

---

## Wichtiger Hinweis

Dieses Projekt:

- ❌ schreibt NICHT direkt nach XT-Commerce
- ❌ macht KEINE API Calls

➡️ Es bereitet nur Daten in der Stage vor  
➡️ XT-Anbindung erfolgt separat (Codex)

---

## Admin-Dashboard

Enthält:

- Dashboard
- Sync-Läufe
- Logs
- Fehler
- Stage-Browser
- Statusprüfung

Der Stage-Browser ist abgesichert über eine Whitelist.
