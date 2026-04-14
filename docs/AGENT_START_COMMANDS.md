# Agent Start Commands

Diese Datei enthält fertige Startprompts für **Copilot** und **Codex**.
Die Agent-Dateien werden nicht automatisch ausgeführt, sondern per Prompt aktiviert.

## 1) Copilot starten – Backend Feature bauen

```text
Lies zuerst diese Dateien:
- AGENTS.md
- PROJECT_CONTEXT.md
- .github/copilot-instructions.md
- agents/copilot/build-backend-feature.md
- die von der Aufgabe betroffenen Dateien

Arbeite nach den Regeln aus diesen Dateien.

Aufgabe:
[HIER DEINE KONKRETE BACKEND-AUFGABE]

Wichtig:
- keine XT-API
- keine direkten XT-DB-Zugriffe
- kleine, gezielte Änderungen

Gib mir bitte:
1. kurzes Verständnis der Aufgabe
2. betroffene Dateien
3. finalen Patch / Code
4. Validierungsschritte
```

## 2) Copilot starten – Backend Debugging

```text
Lies zuerst diese Dateien:
- AGENTS.md
- PROJECT_CONTEXT.md
- .github/copilot-instructions.md
- agents/copilot/debug-backend.md
- die betroffenen PHP-Dateien

Arbeite nach den Regeln aus diesen Dateien.

Debug-Aufgabe:
[HIER FEHLERBESCHREIBUNG, DATEI ODER CODE EINFÜGEN]

Gib mir bitte:
1. Fehlerursache
2. betroffene Dateien
3. minimalen Fix
4. finalen Patch
5. Validierungsschritte
```

## 3) Copilot starten – Pipeline optimieren

```text
Lies zuerst diese Dateien:
- AGENTS.md
- PROJECT_CONTEXT.md
- .github/copilot-instructions.md
- agents/copilot/optimize-pipeline.md
- run_import_all.php
- run_merge.php
- run_expand.php
- config/*.php
- database.sql

Arbeite nach den Regeln aus diesen Dateien.

Aufgabe:
Analysiere die Pipeline und optimiere sie für Performance, Delta-Vorbereitung und Datenstruktur.

Gib mir bitte:
1. wichtigste Schwachstellen
2. priorisierte Verbesserungen
3. betroffene Dateien
4. konkrete Patches oder Codebeispiele
```

## 4) Codex starten – UI-Komponente bauen

```text
Read these files first:
- AGENTS.md
- PROJECT_CONTEXT.md
- agents/codex/build-ui-component.md
- public/index.php
- src/Web/
- the files affected by the task

Use the repository rules from these files.

Task:
[INSERT THE EXACT UI FEATURE HERE]

Important:
- extend the existing admin UI
- do not redesign the whole app
- avoid unnecessary dependencies

Return:
1. short UI concept
2. changed files
3. final patch/code
4. UX notes
5. validation steps
```

## 5) Codex starten – Dashboard verbessern

```text
Read these files first:
- AGENTS.md
- PROJECT_CONTEXT.md
- agents/codex/design-dashboard.md
- public/index.php
- src/Web/

Use the repository rules from these files.

Task:
Improve the admin dashboard for:
- pipeline status
- sync runs
- logs
- errors
- export queue

Return:
1. layout idea
2. changed files
3. final patch/code if needed
4. UX reasoning
```

## 6) Codex starten – UX verbessern

```text
Read these files first:
- AGENTS.md
- PROJECT_CONTEXT.md
- agents/codex/improve-ux.md
- public/index.php
- src/Web/
- the affected UI files

Use the repository rules from these files.

Task:
Improve this workflow / screen:
[INSERT FILE, FLOW OR DESCRIPTION HERE]

Return:
1. UX problems
2. proposed improvements
3. changed files
4. final patch/code
5. user benefit
```
