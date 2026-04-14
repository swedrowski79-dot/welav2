# Ticket: T-006

## Status
done

## Title
Harden async web pipeline start and running visibility

## Problem
The web-triggered pipeline should return immediately and remain observable.
The basic fix exists, but it still needs validation and possibly hardening.

## Goal
Ensure pipeline actions triggered from the web UI do not block the browser and remain visible through status reporting.

## Scope
- validate async launch behavior for merge / expand / delta / full pipeline
- ensure `/pipeline` remains responsive
- improve current running step visibility where needed

## Acceptance Criteria
- [x] browser request returns immediately after start
- [x] `/pipeline` remains reachable during long-running tasks
- [x] current running state is visible
- [x] latest error state remains visible

## Files / Areas
- `src/Web/Repository/SyncLauncher.php`
- pipeline controller / monitoring repository / pipeline view

## Notes
Keep the architecture incremental.

## Implementation Notes
- Hardened `SyncLauncher` to start jobs through `nohup /bin/sh -lc ... & echo $!`, which detaches web-triggered runs more robustly and verifies a PID was created.
- Kept `/pipeline` on the existing monitoring tables and added a dedicated progress field based on the latest log entry of the active or latest run.
- Existing running/idle, timestamps, and latest error visibility remain in place and are now complemented by clearer progress feedback.
