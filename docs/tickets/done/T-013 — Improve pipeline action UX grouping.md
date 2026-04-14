# Ticket: T-013

## Title
Improve pipeline action grouping and clarity in admin UI

## Status
done

## Problem
The pipeline action buttons are currently unstructured and confusing.
It is not clear which step belongs to which part of the pipeline.

## Goal
Group pipeline actions logically and improve clarity.

## Requirements
- Group buttons into sections:
  1. Import (AFS → RAW)
  2. Processing (Merge / Expand)
  3. Delta
  4. Full Pipeline
- Add simple descriptions for each button
- Keep existing functionality unchanged
- Improve spacing and layout for readability

## Acceptance Criteria
- [x] Buttons are grouped visually
- [x] Each section has a clear label
- [x] Users understand the flow without prior knowledge
- [x] No functionality is broken

## Notes
This is a UX improvement only.

## Implementation Notes
- Grouped the `/pipeline` action area into four visible sections: Import, Processing, Delta, and Full Pipeline.
- Added short section descriptions plus one short explanatory line per button.
- Kept all existing jobs, endpoints, and functionality unchanged; only the presentation and spacing were improved.
