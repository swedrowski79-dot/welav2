# Task

Prüfen, warum der letzte Pipeline-Lauf scheinbar alle Produkte, Kategorien und Dokumente erneut durch die Export Queue schickt.

# Files read

- `AGENTS.md`
- `PROJECT_CONTEXT.md`
- `config/delta.php`
- `src/Service/ProductDeltaService.php`
- `src/Service/XtProductWriter.php`
- `wela-api/src/ProductSyncService.php`

# Changed files

- `docs/agent-results/2026-07-20-full-queue-reenqueue-diagnosis.md`

# Summary

- Der letzte Expand-Lauf hat tatsächlich neue Queue-Einträge erzeugt; es handelt sich nicht nur um historische `done`-Einträge.
- Produkte: `5.455` Updates wurden erzeugt, weil der XT-Mirror bei `5.455` von `5.489` Produkten vom Stage-Attributstand abweicht. Das ist die direkte Folge des zuvor behobenen Attribut-Payload-Fehlers und ist als einmaliger Nachzug erwartbar.
- Kategorien: Es wurden in diesem Lauf keine neuen Einträge erzeugt. `73` bereits vorhandene Einträge waren aktiv bzw. fehlerhaft; Delta hat sie dedupliziert (`queue_created = 0`).
- Dokumente: Alle `2.956` Stage-Dokumente wurden als fehlend eingeordnet und neu eingereiht. Der aktuelle XT-Mirror enthält `0` Dokument-Verknüpfungen vom Typ `files`, während Stage `2.956` Dokumente enthält. Dadurch wird jeder Dokument-Delta-Lauf sie erneut als `mirror_missing` bewerten.

# Open points

- Der Dokument-Mirror muss geprüft und korrigiert werden, damit `xt_mirror_media_link` die XT-Dokumentlinks mit `type = files` enthält. Erst dann endet das wiederholte Einreihen aller Dokumente.
- Nach Abschluss des Attribut-Nachzugs muss ein XT-Mirror-Refresh erfolgen. Ein anschließender Delta-Lauf sollte dann bei Produkten keine oder nur tatsächliche Änderungen erzeugen.

# Validation steps

- Queue-Gruppierung nach Entity-Typ und Status geprüft.
- Kontext des letzten `expand`-Laufs geprüft:
  - Produkte: `mirror_mismatched = 5455`, `queue_created = 5455`.
  - Kategorien: `deduplicated = 73`, `queue_created = 0`.
  - Dokumente: `mirror_missing = 2956`, `queue_created = 2956`.
- Bestandsvergleich geprüft:
  - `stage_product_documents = 2956`
  - gemirrorte XT-Dokumentlinks `files = 0`

# Recommended next step

Den Dokument-Mirror-Pfad gezielt prüfen und reparieren; danach Mirror Refresh und Delta erneut ausführen. Produkte erst nach dem laufenden Attribut-Nachzug erneut spiegeln und prüfen.
