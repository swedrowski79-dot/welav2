## Task

Fachliche Klarstellung direkt im Dokument-Scan-Code hinterlegen: `title` ist der relevante Dateiname fuer den Dokumentlauf, `file_name` stammt aus einer Fremdsoftware und ist nicht die lokale Pfadquelle.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `src/Web/Repository/DocumentFileRepository.php`

## Changed files

- `src/Web/Repository/DocumentFileRepository.php`
- `docs/agent-results/2026-07-16-document-title-note.md`

## Summary

Im Dokument-Repository wurde am Einstieg von `syncTitlesFromStage()` ein fachlicher Hinweis als Kommentar ergänzt:

- `title` ist im Projekt der relevante Dokument-Dateiname fuer Scan und Upload
- `stage_product_documents.file_name` stammt aus einer Fremdsoftware
- `file_name` darf daher nicht als lokale Pfad-/Dateiname-Quelle fuer den Dokumentlauf interpretiert werden

Es wurde keine Logik geändert.

## Open points

- Die noch fehlenden `local_path`-/`file_hash`-Werte muessen weiterhin separat ueber die reale Dateiverfuegbarkeit bzw. Scanbedingungen analysiert werden.

## Validation steps

- Keine Laufzeitvalidierung ausgefuehrt
- Nur Kommentar-/Dokumentationsaenderung

## Recommended next step

Die 26 aktuell nicht gefundenen Dokumente gezielt gegen den eingestellten Dokumentenpfad pruefen, um zu klaeren, ob sie lokal fehlen, anders benannt sind oder ausserhalb des Scanpfads liegen.
