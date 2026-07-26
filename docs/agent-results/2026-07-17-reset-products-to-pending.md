## Task

Alle Artikel in der Export-Queue erneut auf `pending` setzen.

## Files read

- `AGENTS.md`

## Changed files

- `docs/agent-results/2026-07-17-reset-products-to-pending.md`

## Summary

- Alle Queue-Eintraege mit `entity_type='product'` wurden auf `pending` zurueckgesetzt.
- Claim- und Retry-Felder wurden dabei ebenfalls geleert bzw. normalisiert.

## Open points

- Keine.

## Validation steps

- Vorher geprueft: `product done 5412`
- Update ausgefuehrt.
- Nachher geprueft: `product pending 5412`

## Recommended next step

- Den naechsten Exportlauf mit den gewuenschten Workern starten und die reale Durchsatzrate beobachten.
