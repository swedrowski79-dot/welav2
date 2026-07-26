## Task

Stage-Browser im Frontend umbauen: Suchfunktion fuer die aktive Tabelle und Inline-Bearbeitung einzelner Datensatzfelder per Doppelklick direkt in der Tabellenansicht, mit Dropdown fuer die Tabellenauswahl.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `docs/CODEX_WORKFLOW.md`
- `config/admin.php`
- `public/index.php`
- `src/Web/Core/Controller.php`
- `src/Web/Core/Request.php`
- `src/Web/Core/Response.php`
- `src/Web/Core/Router.php`
- `src/Web/bootstrap.php`
- `src/Web/Controller/StageBrowserController.php`
- `src/Web/Repository/StageBrowserRepository.php`
- `src/Web/Repository/StageConnection.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/stage-browser/index.php`
- `src/Web/View/stage-browser/show.php`
- `docs/agent-results/2026-04-17-stale-product-cleanup.md`

## Changed files

- `public/index.php`
- `src/Web/Controller/StageBrowserController.php`
- `src/Web/Core/Response.php`
- `src/Web/Repository/StageBrowserRepository.php`
- `src/Web/View/layouts/app.php`
- `src/Web/View/stage-browser/index.php`
- `docs/agent-results/2026-04-19-stage-browser-editing.md`

## Summary

- Der Stage-Browser nutzt ein Dropdown fuer die Tabellenauswahl und fuehrt die Suche klar pro aktiver Tabelle.
- Tabellenzellen koennen per Doppelklick direkt bearbeitet werden; das Speichern erfolgt asynchron ueber einen neuen POST-Endpunkt.
- Die Update-Logik bleibt auf die bestehende Tabellen-Whitelist beschraenkt und blockiert Primary-Key-Aenderungen.
- `NULL`-Werte werden im UI weiterhin erkennbar angezeigt und bei Inline-Edits sauber behandelt.
- Numerische Felder validieren Eingaben jetzt explizit und liefern verstaendlichere Fehlermeldungen.

## Open points

- Eine echte Browser-Interaktionspruefung gegen `http://localhost:8080` wurde in diesem Lauf nicht ausgefuehrt.
- Aktuell sind alle nicht-auto-increment und nicht-Primary-Key-Felder editierbar; falls einzelne Tabellen/Felder spaeter gesperrt werden sollen, sollte dafuer eine dedizierte Konfiguration in `config/admin.php` ergänzt werden.

## Validation steps

- `docker compose exec -T php php -l /app/src/Web/Core/Response.php`
- `docker compose exec -T php php -l /app/src/Web/Repository/StageBrowserRepository.php`
- `docker compose exec -T php php -l /app/src/Web/Controller/StageBrowserController.php`
- `docker compose exec -T php php -l /app/public/index.php`
- `docker compose exec -T php php -l /app/src/Web/View/layouts/app.php`
- `docker compose exec -T php php -l /app/src/Web/View/stage-browser/index.php`

## Recommended next step

Den Stage-Browser einmal manuell im laufenden Admin unter `http://localhost:8080/stage-browser` pruefen, insbesondere Tab-Wechsel, Suche je Tabelle und Inline-Edits fuer kurze sowie lange Textfelder.
