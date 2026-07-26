## Task

Separate Bild- und Dokument-Uploads mit eigenen `sync_runs` ausstatten, ohne sie in die Pipeline einzubauen, und eine Dokumentation zu allen Runs plus Bedienungsanleitung unter `docs/manual/` anlegen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `src/Web/bootstrap.php`
- `src/Monitoring/SyncMonitor.php`
- `config/pipeline.php`
- `src/Service/PipelineConfig.php`
- `src/Web/Controller/ImageFileController.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `src/Web/Repository/MonitoringRepository.php`
- `src/Web/Controller/SyncRunController.php`

## Changed files

- `src/Web/bootstrap.php`
- `config/pipeline.php`
- `src/Web/Controller/ImageFileController.php`
- `src/Web/Controller/DocumentFileController.php`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Web/Repository/DocumentFileRepository.php`
- `docs/manual/runs.md`
- `docs/manual/bedienungsanleitung.md`
- `docs/agent-results/2026-07-16-upload-runs-and-manual.md`

## Summary

Bild- und Dokument-Upload erzeugen jetzt jeweils einen eigenen Monitoring-Lauf:

- `image_upload`
- `document_upload`

Diese Runs werden direkt im Web-Uploadpfad gestartet, loggen Start, offene Dateien, erfolgreiche Einzeluploads und Einzeldateifehler und werden am Ende mit `success`, `warning` oder `failed` abgeschlossen.

Die Runs wurden bewusst nicht als Pipeline-Jobs angelegt und nicht in `full_pipeline` integriert. Sie sind nur im Monitoring sichtbar.

Zusätzlich wurde `docs/manual/` angelegt mit:

- `runs.md` fuer die Uebersicht aller Run-Typen
- `bedienungsanleitung.md` fuer die praktische Bedienung

## Open points

- Scan-Aktionen fuer Bilder und Dokumente haben weiterhin keinen eigenen `sync_run`.
- Die Uploads laufen weiterhin synchron im Web-Request und nicht asynchron ueber einen Hintergrundprozess.

## Validation steps

- Noch nicht ausgefuehrt:
  - `docker compose exec -T php php -l src/Web/bootstrap.php`
  - `docker compose exec -T php php -l src/Web/Controller/ImageFileController.php`
  - `docker compose exec -T php php -l src/Web/Controller/DocumentFileController.php`
  - `docker compose exec -T php php -l src/Web/Repository/ImageFileRepository.php`
  - `docker compose exec -T php php -l src/Web/Repository/DocumentFileRepository.php`
  - manueller Test ueber Admin-UI fuer Bild- und Dokument-Upload

## Recommended next step

Die beiden Upload-Aktionen einmal im lokalen Admin ausfuehren und direkt in `Monitoring Laeufe`, `Monitoring Logs` und `Monitoring Fehler` pruefen, ob die neuen Run-Typen und Statuswerte wie erwartet erscheinen.
