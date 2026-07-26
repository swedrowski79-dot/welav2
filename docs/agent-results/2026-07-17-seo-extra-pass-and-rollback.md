## Task

Noch einen weiteren SEO-Optimierungsversuch fuer den verbleibenden Restblock pruefen und nur behalten, falls er messbar hilft.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `wela-api/seo_helpers.php`
- `wela-api/index.php`
- `wela-api-xt/seo_helpers.php`
- `wela-api-xt/index.php`
- `scripts/benchmark_wela_api_products.php`

## Changed files

- `docs/agent-results/2026-07-17-seo-extra-pass-and-rollback.md`

## Summary

- Es wurde ein zusaetzlicher Versuch gebaut, Kategorie-SEO-URLs fuer den Batch vorab zu laden.
- Dieser Versuch wurde direkt mit dem bestehenden Benchmark gegen den Live-Stand am Freitag, 17. Juli 2026 gemessen.
- Ergebnis:
  - kein stabiler Vorteil
  - teils sogar schlechtere Werte
- Deshalb wurde diese zusaetzliche Aenderung wieder komplett entfernt.
- Aktiv bleibt damit der beste nachgewiesene SEO-Optimierungsstand:
  - SEO-Candidate-Bulk-Prefetch
  - Wiederverwendung passender bestehender URLs
  - Skip unveraenderter `xt_seo_url`-Upserts

## Measured results

Bester bestaetigter Stand nach dem Rollback:

- `size=2 normal`: `3.8635s`
- `size=10 normal`: `7.2607s`
- `size=2 no_seo`: `0.1148s`
- `size=10 no_seo`: `0.1956s`

Das zeigt:

- Der Hauptgewinn aus den SEO-Optimierungen bleibt erhalten.
- Der zuletzt getestete Extra-Schritt war nicht sinnvoll und wurde daher nicht behalten.

## Open points

- Der verbleibende Rest sitzt weiterhin im echten SEO-Aenderungspfad.
- Wenn noch mehr herausgeholt werden soll, muss gezielt der Kollisions-/Redirect-Pfad fuer geaenderte URLs angegangen werden.

## Validation steps

- `docker compose exec -T php php -l /app/wela-api/seo_helpers.php`
- `docker compose exec -T php php -l /app/wela-api/index.php`
- `docker compose exec -T php php /app/scripts/benchmark_wela_api_products.php 2 10`

## Recommended next step

- Den jetzigen Stand verwenden und nur dann weiter in den SEO-Pfad eingreifen, wenn du speziell die echten URL-Aenderungsfaelle noch weiter beschleunigen willst.
