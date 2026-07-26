# Diagnose: Benennung von `attributes_model`

## Task

Prüfen, wie die `attributes_model`-Werte aktuell erzeugt werden und welche Entscheidung für deutsche Modellbezeichnungen erforderlich ist.

## Files read

- `src/Service/XtProductWriter.php`
- `config/xt_write.php`

## Changed files

- `docs/agent-results/2026-07-20-attribute-model-naming-diagnosis.md`

## Summary

Die aktuellen `attributes_model`-Werte werden technisch und eindeutig aus der deutschen Ausgangsbezeichnung erzeugt, beispielsweise mit Präfix, Slug und Hash. Sie sind daher keine lesbaren deutschen Modellbezeichnungen.

Die Attribute werden als Parent/Child-Struktur geführt. Beide Ebenen benötigen unterschiedliche Modellwerte, da `attributes_model` die Identität für Upserts bildet.

## Open points

- Festlegen, welche deutschen Werte die zwei Ebenen verwenden sollen.

## Validation steps

- Modellbildung in `attributeParentModel()` und `attributeValueModel()` geprüft.
- Konfiguration geprüft: `attributes_model` ist das eindeutige Identitätsfeld der XT-Attributtabelle.

## Recommended next step

Die gewünschte deutsche Benennung für Parent und Child bestätigen, danach Modellbildung, Delta und Re-Export gezielt umstellen.
