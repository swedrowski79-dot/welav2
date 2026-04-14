# AGENTS.md

## Projektkontext
Dieses Repository ist eine Sync-Schnittstelle für:
- **AFS (MSSQL)**
- **Extra (SQLite, Übersetzungen)**
- **Stage (MySQL)**
- **später: Delta + Export Queue**

Die Architektur ist absichtlich in Schritte getrennt:
1. Import (`run_import_all.php`)
2. Merge (`run_merge.php`)
3. Expand (`run_expand.php`)
4. später Delta
5. später Export Queue

XT-Commerce wird **nicht direkt** beschrieben.

## Read first
Bevor Änderungen vorgeschlagen werden, zuerst lesen:
1. `PROJECT_CONTEXT.md`
2. `README.md`
3. `.github/copilot-instructions.md`
4. `database.sql`
5. `config/*.php`
6. betroffene Dateien im jeweiligen Task-Bereich

## Globale Regeln
- Keine direkten XT-DB-Schreibzugriffe
- Keine XT-API-Clients einbauen
- Keine HTTP-Integration für XT bauen
- Pipeline-Reihenfolge respektieren: **import -> merge -> expand -> delta -> export**
- Bestehende Architektur erweitern, nicht unnötig umschreiben
- Kleine, gezielte Änderungen bevorzugen statt Totalumbau
- Monitoring (`sync_runs`, `sync_logs`, `sync_errors`) mitdenken
- Fehler pro Datensatz loggen, Lauf nicht unnötig abbrechen
- Performance beachten: Batch-Verarbeitung, keine unnötigen Selects in Schleifen

## Backend-Konventionen
- Im CLI-/ETL-Bereich möglichst beim bestehenden Stil bleiben
- Nicht automatisch Composer, Frameworks oder neue Infrastruktur einführen
- Bestehende Config-Dateien (`config/*.php`) bevorzugen statt Hardcoding
- Für neue browsbare Tabellen ggf. `config/admin.php` ergänzen

## Frontend-/UI-Konventionen
- Bestehende Admin-Oberfläche erweitern statt komplett neu designen
- UI soll für Nicht-Programmierer verständlich bleiben
- Keine unnötigen zusätzlichen Frontend-Frameworks einführen
- Stil an `public/index.php` und `src/Web/` anlehnen

## Was Agents liefern sollen
Jede Antwort soll möglichst diese Struktur haben:
1. Ziel / Verständnis der Aufgabe
2. Betroffene Dateien
3. Konkrete Änderung oder Patch
4. Validierung / Startbefehle
5. Offene Punkte oder Annahmen

## Startprinzip für Copilot und Codex
Die Agent-Dateien sind **Prompt-Vorlagen**. Sie werden nicht automatisch „gestartet“.
Nutze sie, indem du im Prompt explizit schreibst:
- welche Dateien zuerst gelesen werden sollen
- welche Agent-Datei gelten soll
- welche konkrete Aufgabe umgesetzt werden soll

Siehe dazu auch: `docs/AGENT_START_COMMANDS.md`
