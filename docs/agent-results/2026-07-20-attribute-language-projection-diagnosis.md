# Task

Aktuellen Attributfehler nach dem Neuaufbau der lokalen Datenbanken entlang Dictionary, RAW und Stage untersuchen.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `config/expand.php`
- `config/merge.php`
- `src/Service/ImportWorkflow.php`
- `src/Service/AttributeTranslationProjectionService.php`
- `src/Service/ExpandService.php`

# Changed files

- `docs/agent-results/2026-07-20-attribute-language-projection-diagnosis.md`

# Summary

- Das Dictionary `afs_extras.attribute_translations` enthält 496 aktive Begriffe und fast alle Werte für `en`, `fr` und `nl`.
- Die aktuelle Dictionary-Abdeckung reicht für alle 10.127 Attributzeilen aus den AFS-Artikelattributen.
- Die bestehende Projektionsausgabe `stage_sync.raw_extra_attribute_translations` ist jedoch nur für Deutsch vollständig:
  - `de`: 10.127 vollständige Zeilen
  - `en`, `fr`, `nl`: jeweils 10.127 Zeilen, aber 0 vollständige Zeilen
- Beim Expand werden diese leeren Fremdsprachenzeilen übersprungen. Deshalb enthält `stage_sync.stage_attribute_translations` aktuell nur `de` mit 10.127 Zeilen.
- Ursache ist damit nicht der XT-Export, sondern eine veraltete/fehlerhafte RAW-Projektion vor dem Expand-Schritt.

# Open points

- Es muss geklärt werden, warum die Dictionary-Werte bei der letzten RAW-Projektion leer gelesen wurden, obwohl sie aktuell vorhanden und vollständig auflösbar sind.

# Validation steps

- Aktuelle Tabellenstände geprüft:
  - `raw_afs_articles = 5.489`
  - `raw_extra_attribute_translations = 40.508`
  - `stage_attribute_translations = 10.127`
- Sprachvergleich der RAW- und Stage-Attributzeilen durchgeführt.
- Schreibgeschützte Join-Prüfung bestätigt: Das aktuelle Dictionary löst alle 10.127 AFS-Attributzeilen auf.

# Recommended next step

Die Attributprojektion gezielt neu aufbauen und anschließend Merge sowie Expand erneut ausführen. Vor dem erneuten Export prüfen, dass `stage_attribute_translations` für `de`, `en`, `fr` und `nl` jeweils vollständige Zeilen enthält.
