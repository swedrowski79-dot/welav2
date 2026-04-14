# Projekt: AFS → Stage → XT Sync-System

## Überblick
Dieses Projekt synchronisiert Produktdaten aus:
- AFS (MSSQL)
- Extra-Datenbank (SQLite, mehrsprachig)

Ziel:
- Daten in Stage (MySQL) überführen
- Aufbereiten (Merge, Attribute, Übersetzungen)
- Delta & Export vorbereiten

Wichtig:
XT wird NICHT direkt angesprochen (separate API).

---

## Pipeline
1. Import (RAW)
2. Merge (STAGE)
3. Expand (Attribute)
4. (geplant) Delta
5. (geplant) Export Queue

---

## Datenbank

### RAW
- raw_afs_articles
- raw_afs_categories
- raw_extra_article_translations
- raw_extra_category_translations

### STAGE
- stage_products
- stage_product_translations
- stage_categories
- stage_category_translations
- stage_attribute_translations

### Monitoring
- sync_runs
- sync_logs
- sync_errors

---

## AFS Filter
Nur Artikel mit:
- Internet = 1
- Art < 255
- Mandant = 1

---

## SQLite
Tabellen:
- articles
- warengruppen

Besonderheit:
- Zeilenbasiert pro Sprache

---

## Sprache
- de, en, fr, nl
- lowercase
- fallback: de

---

## Config

### sources.php
- DB Verbindungen
- Tabellen
- columns (SELECT)
- where (Filter)

### normalize.php
- Feldmapping
- berechnete Felder

### merge.php
- Zusammenführung

### expand.php
- Attribute 1–4 → Zeilen

---

## Attribute
Quelle:
- attribute_name1..4
- attribute_value1..4

Ziel:
- stage_attribute_translations

---

## Docker

Start:
docker compose up -d --build

Pipeline:
docker compose exec php php run_import_all.php
docker compose exec php php run_merge.php
docker compose exec php php run_expand.php

---

## Dashboard
- Sync Läufe
- Logs
- Fehler
- DB Browser

---

## Regeln

NICHT:
- XT DB Zugriff
- API Calls

SONDERN:
- Daten vorbereiten
- Delta erzeugen
- Export vorbereiten

---

## Nächste Schritte
- Delta System
- Export Queue

---

## Performance
- kein SELECT *
- nur columns nutzen
- where nutzen
- keine SELECTs in Schleifen

---

## Fehlerhandling
- Fehler loggen
- nicht abbrechen
- sync_runs pflegen
