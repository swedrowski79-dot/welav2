## Task

Separate CLI-Befehle fuer Bild- und Dokument-Scan/Upload anlegen, ohne diese in die Pipeline einzubauen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `run_import_all.php`
- `run_expand.php`
- `run_export_queue.php`
- `config/pipeline.php`
- `src/Web/bootstrap.php`
- `src/Web/Repository/EnvFileRepository.php`
- `src/Web/Repository/StageConnection.php`
- `src/Web/Repository/ImageFileRepository.php`
- `src/Web/Repository/DocumentFileRepository.php`

## Changed files

- `config/pipeline.php`
- `run_image_scan.php`
- `run_image_upload.php`
- `run_document_scan.php`
- `run_document_upload.php`
- `docs/manual/runs.md`
- `docs/manual/bedienungsanleitung.md`
- `docs/agent-results/2026-07-16-separate-cli-media-commands.md`

## Summary

Es wurden vier neue separate CLI-Entrypoints angelegt:

- `run_image_scan.php`
- `run_image_upload.php`
- `run_document_scan.php`
- `run_document_upload.php`

Die Skripte:

- verwenden die bestehende Web-/Repo-Logik
- schreiben eigene `sync_runs`
- bleiben ausserhalb der Pipeline
- erscheinen nur im Monitoring, nicht als Pipeline-Jobs

Zusätzlich wurden neue Run-Type-Labels dokumentiert:

- `image_scan`
- `image_upload`
- `document_scan`
- `document_upload`

Die Manual-Doku wurde um die neuen CLI-Befehle erweitert.

## Open points

- Die neuen Skripte wurden nicht als Pipeline-Jobs konfiguriert und erscheinen daher nicht als startbare Buttons in der Pipeline-Oberflaeche.

## Validation steps

- Syntaxpruefung fuer:
  - `run_image_scan.php`
  - `run_image_upload.php`
  - `run_document_scan.php`
  - `run_document_upload.php`

## Recommended next step

Die vier Befehle einmal ueber den PHP-Container starten und anschliessend in `Monitoring Laeufe` pruefen, ob die neuen separaten Run-Typen sauber protokolliert werden.
