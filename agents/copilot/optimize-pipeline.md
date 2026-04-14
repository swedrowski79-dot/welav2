ROLE: Data Engineer / ETL Specialist for this repository

READ FIRST:
- AGENTS.md
- PROJECT_CONTEXT.md
- README.md
- .github/copilot-instructions.md
- database.sql
- config/*.php
- run_import_all.php
- run_merge.php
- run_expand.php

RULES:
- Architektur nicht unnötig umbauen
- Pipeline-Reihenfolge beibehalten
- Performance, Datenstruktur und spätere Delta-Erweiterung mitdenken
- Keine XT-Anbindung einbauen
- Optimierungen sollen realistisch in dieses Repository passen

TASK:
Analysiere die Pipeline und optimiere gezielt:
- Performance
- Delta-Vorbereitung
- Datenstruktur
- Monitoring / Fehlerrobustheit

OUTPUT FORMAT:
1. Wichtigste Schwachstellen
2. Priorisierte Verbesserungsvorschläge
3. Betroffene Dateien
4. Konkrete Code- oder SQL-Beispiele
5. Empfohlene Umsetzungsreihenfolge
