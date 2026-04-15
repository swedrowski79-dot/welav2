# Task

Improve the admin stage database browser pagination and register the new stage media/document tables in the browser.

# Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `docs/CODEX_WORKFLOW.md`
- `agents/codex/ticket-task.md`
- `docs/agent-results/2026-04-15-T-026-document-and-product-media-stage-model.md`
- `config/admin.php`
- `public/index.php`
- `src/Web/Controller/StageBrowserController.php`
- `src/Web/Repository/StageBrowserRepository.php`
- `src/Web/Core/Controller.php`
- `src/Web/Core/Paginator.php`
- `src/Web/View/stage-browser/index.php`
- `src/Web/View/stage-browser/show.php`
- `src/Web/View/partials/pagination.php`

# Changed files

- `config/admin.php`
- `src/Web/Core/Paginator.php`
- `src/Web/View/stage-browser/index.php`
- `src/Web/View/partials/pagination.php`
- `docs/agent-results/2026-04-15-stage-browser-pagination-and-table-registration.md`

# Summary

- Added `stage_product_documents` and `stage_product_media` to the stage browser whitelist in `config/admin.php`, so both tables are available in the admin DB browser.
- Upgraded the shared paginator helper to clamp out-of-range pages and expose previous/next plus compact page list helpers.
- Reworked the stage browser pagination partial to show:
  - `Erste` / `Zurueck`
  - compact page numbers with ellipses for long page ranges
  - `Weiter` / `Letzte`
- Added the pagination block to the top of the stage browser table view while keeping the existing bottom pagination.
- Added a small page summary (`Zeilen`, current page, total pages) above and below the table for easier navigation context.
- Left the rest of the admin and stage browser behavior unchanged.

# Open points

- The browser still renders full-row search across all columns, which is unchanged by this fix.
- Pagination compaction is intentionally generic and shared through the existing paginator partial, so any future pagination UX changes should keep that reuse in mind.

# Validation steps

- Executed:
  - `docker compose up -d --build`
  - `docker compose exec -T php php -l /app/config/admin.php`
  - `docker compose exec -T php php -l /app/src/Web/Core/Paginator.php`
  - `docker compose exec -T php php -l /app/src/Web/View/partials/pagination.php`
  - `docker compose exec -T php php -l /app/src/Web/View/stage-browser/index.php`
  - `curl -s http://localhost:8080/stage-browser | grep -ao 'Stage Produkt-Dokumente\|Stage Produkt-Medien' | sort | uniq -c`
  - `curl -s 'http://localhost:8080/stage-browser?table=stage_product_documents' | grep -q 'Stage Produkt-Dokumente'`
  - `curl -s 'http://localhost:8080/stage-browser?table=stage_product_media&page=5&per_page=20' | grep -q 'Stage Produkt-Medien'`
  - `curl -s 'http://localhost:8080/stage-browser?table=stage_product_media&page=5&per_page=20' | grep -o 'aria-label="Pagination"' | wc -l`
  - `curl -s 'http://localhost:8080/stage-browser?table=stage_product_media&page=5&per_page=20' | grep -o '&hellip;' | wc -l`
  - `curl -s 'http://localhost:8080/stage-browser?table=stage_product_media&page=999&per_page=20' | grep -o 'Seite [0-9]\+ von [0-9]\+' | head -n 1`
  - `curl -s "http://localhost:8080/stage-browser/show?table=stage_product_documents&id=\${DOC_ID}" | grep -q 'Datensatzdetail'`
  - `curl -s "http://localhost:8080/stage-browser/show?table=stage_product_media&id=\${MEDIA_ID}" | grep -q 'Datensatzdetail'`
- Observed:
  - both new tables appear in the browser table dropdown
  - `/stage-browser?table=stage_product_documents` loads successfully
  - `/stage-browser?table=stage_product_media` loads successfully
  - the paginated media table renders two pagination blocks on the page
  - compact pagination renders ellipses on long page lists
  - requesting page `999` clamps to the last available page (`Seite 267 von 267`)
  - both new tables are reachable in the detail view route

# Recommended next step

If stage browser usage grows further, consider preserving `page`, `per_page`, and `q` when returning from the detail view to the table view.
