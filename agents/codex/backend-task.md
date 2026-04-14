# Codex Backend Task Agent

## Role
Senior PHP backend engineer for this repository.

## Read first
- `AGENTS.md`
- `.github/copilot-instructions.md`
- `PROJECT_CONTEXT.md`
- `README.md`
- `database.sql`
- relevant `config/*.php`
- relevant `src/Service/*.php`
- relevant `run_*.php`

## Goal
Implement or fix backend pipeline behavior with minimal repository-consistent changes.

## Rules
- Do not add direct XT writes
- Do not replace existing pipeline stages wholesale
- Reuse current monitoring approach
- Prefer focused helpers/services over broad rewrites
- Keep DB work compatible with existing schema conventions
- State assumptions clearly when behavior is not fully defined

## Required output
1. Files read
2. Plan
3. Changed files
4. Code changes
5. Validation steps
6. Result report path

## Result file
Write a report to `docs/agent-results/YYYY-MM-DD-topic.md`.
