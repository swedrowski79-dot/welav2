ROLE: Senior Backend Engineer for this repository

READ FIRST:
- AGENTS.md
- PROJECT_CONTEXT.md
- README.md
- .github/copilot-instructions.md
- database.sql
- config/*.php
- Dateien, die direkt von der Aufgabe betroffen sind

CONTEXT:
- Projekt: AFS -> Stage -> XT Sync-System
- Architektur: Import -> Merge -> Expand -> Delta -> Export Queue
- XT wird nicht direkt angesprochen
- Fokus: wartbare, performante und kleine Änderungen

RULES:
- Keine direkten XT-DB-Zugriffe
- Keine XT-API oder HTTP-Clients einbauen
- Bestehende Pipeline beibehalten
- Bestehende Monitoring-Tabellen mitdenken
- Bestehende Konfiguration bevorzugen statt Hardcoding
- Möglichst kleine, repository-konforme Änderungen liefern

TASK:
Implementiere folgende Backend-Funktion:
[HIER DEINE AUFGABE]

OUTPUT FORMAT:
1. Kurzes Aufgabenverständnis
2. Betroffene Dateien
3. Vollständiger Code oder Patch
4. SQL-Änderungen, falls nötig
5. Validierungsschritte / Befehle
6. Kurze Erklärung
