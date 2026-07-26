# Produkt-SEO: kanonische URL mit Weiterleitung

## Task

Bei einem Produkt-SEO-Update soll die bisherige URL als Weiterleitung auf die neue URL erhalten bleiben, jedoch nicht mehr als aktive kanonische SEO-URL in `xt_seo_url` bestehen.

## Files read

- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- `wela-api/src/ProductSyncService.php`
- `wela-api/seo_helpers.php`
- `wela-api/index.php`
- `docs/agent-results/2026-07-20-shop2-shop-norm-ha-000-seo-comparison.md`

## Changed files

- `wela-api/src/ProductSyncService.php`
- `docs/agent-results/2026-07-20-product-seo-canonical-redirect-fix.md`

## Summary

Der Produkt-Sync liest nun alle aktiven SEO-Zeilen einer logischen SEO-Identität (`link_type`, `link_id`, Sprache, Store). Wenn mehr als eine Zeile existiert oder die vorhandene URL von der neu erzeugten kanonischen URL abweicht, passiert innerhalb derselben Transaktion:

1. Jede abweichende bisherige URL wird als Redirect auf die neue URL vorgemerkt.
2. Die alten aktiven SEO-Zeilen werden entfernt.
3. Die neue kanonische SEO-Zeile wird geschrieben.
4. Die Redirects werden in `xt_seo_url_redirect` gespeichert.

Damit existiert nach dem Export pro Produkt, Sprache und Store nur noch eine aktive kanonische URL. Eine Sicherheitsabfrage verhindert das Entfernen bestehender URLs, wenn keine neue URL erzeugt wurde.

## Open points

- Die Änderung liegt lokal in `wela-api/` und muss vor dem Test in die aktive Instanz `wela-api-xt/` übernommen werden.
- Bestehende Dubletten werden erst bei einem erneuten Export des jeweiligen Produkts bereinigt.

## Validation steps

- PHP-Syntaxprüfung für `wela-api/src/ProductSyncService.php`.
- Vorhandene Redirect-Pufferung in `wela-api/seo_helpers.php` geprüft: Redirect und SEO-Bereinigung laufen in einer Datenbanktransaktion.
- `wela-api-xt/src/ProductSyncService.php` mit der geänderten lokalen API-Datei abgeglichen.
- Produkt-Export erneut gestartet: 4.909 Produkte erfolgreich exportiert; 6 Produkte sind wegen fehlender XT-Kategorien `379` beziehungsweise `380` fachlich blockiert. 574 Einträge bleiben wartend, darunter Einträge mit nicht auflösbaren Attributwerten.
- Live in `shop2` geprüft: HA-1620, HA-1620-P, HA-1630 und HA-1630-P haben jeweils genau eine aktive deutsche SEO-URL. Die frühere deutsche URL liegt jeweils als aktiver Redirect auf die neue URL in `xt_seo_url_redirect` vor.

## Recommended next step

`wela-api/src/ProductSyncService.php` nach `wela-api-xt/src/ProductSyncService.php` übernehmen und zunächst HA-1620 exportieren. Anschließend in XT prüfen, dass nur die neue deutsche SEO-Zeile aktiv ist und die alte URL in `xt_seo_url_redirect` auf sie verweist.
