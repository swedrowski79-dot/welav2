# Task

Die komplette Pipeline erneut laufen lassen, danach eine Stichprobe mit 30 zufaelligen Produkten pruefen und die Ergebnisse protokollieren.

# Files read

- `.env`
- `config/sources.php`
- `docs/agent-results/2026-04-21-product-description-techdata-combine.md`

# Changed files

- `docs/agent-results/2026-04-21-pipeline-rerun-and-shop-sample.md`

# Summary

- Die komplette Pipeline lief erfolgreich bis zum Ende:
  - `import_all`
  - `merge`
  - `xt_mirror`
  - `expand`
  - `export_queue_worker`
  - `full_pipeline`
- Danach wurde `run_xt_mirror.php` noch einmal erfolgreich nachgezogen.
- Die Export-Queue ist am Ende vollstaendig geleert:
  - `category done = 331`
  - `document done = 14785`
  - `media done = 5455`
  - `product done = 22065`
  - keine `pending`, `processing` oder `error`-Eintraege mehr
- Mirror-Basis nach dem Lauf:
  - `xt_mirror_products = 6841`
  - `xt_mirror_products_description = 23206`
  - `xt_mirror_seo_url (Produkte, de, store 1) = 6634`
- Fuer 30 zufaellig gezogene Online-Produkte wurde die Datenlage im Mirror geprueft:
  - `30/30` mit deutschem Produktnamen
  - `30/30` mit gefuellter deutscher Produktbeschreibung
  - `30/30` mit gefuellter deutscher Short Description
  - `30/30` mit deutscher SEO-URL
- Die echte Frontend-Pruefung im Shop war jedoch blockiert:
  - `30/30` getestete Produkt-URLs antworteten mit `HTTP 503`
  - auch der Shop-Root antwortete mit `HTTP 503`
  - Rueckgabe aus dem Shop:
    - `Domain in Licensefile is not Matching the installed Domain, please contact helpdesk@xt-commerce.com`

# Open points

- Die Sync-Daten selbst sehen fuer die 30er-Stichprobe korrekt im Mirror ankommend aus.
- Die eigentliche Shop-Frontend-Pruefung ist aktuell nicht belastbar moeglich, solange der XT-Shop wegen Lizenz-/Domainproblem global `503` liefert.

# Validation steps

- `docker compose exec -T php php run_full_pipeline.php`
- `docker compose exec -T php php run_xt_mirror.php`
- `docker compose exec -T mysql mysql -ustage -pstage -e "SELECT entity_type, status, COUNT(*) AS item_count FROM stage_sync.export_queue GROUP BY entity_type, status ORDER BY entity_type, status;"`
- `docker compose exec -T mysql mysql -ustage -pstage -e "SELECT id, run_type, status, started_at, ended_at, imported_records, merged_records, error_count, LEFT(message,200) AS message FROM stage_sync.sync_runs ORDER BY id DESC LIMIT 8;"`
- zufaellige 30er-Stichprobe aus Stage/Mirror mit SEO-URLs gezogen
- HTTP-Pruefung der 30 Produkt-URLs gegen `http://10.0.1.104/...`
- Shop-Root-Check gegen `http://10.0.1.104/`

# Recommended next step

Zuerst das XT-Lizenz-/Domainproblem im Shop beheben. Danach dieselbe 30er-Stichprobe erneut gegen das Frontend pruefen, damit neben den Mirror-Daten auch die echte Auslieferung im Onlineshop bestaetigt ist.
