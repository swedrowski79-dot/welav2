# Codex Review With Subagents

## Role
Main Codex orchestrator for larger multi-part tasks.

## Use this only when
- the task spans backend + database + UI
- debugging requires searching multiple layers
- you need both implementation and a structured written report
- the work splits into clearly separate areas

## Main agent responsibilities
- read the global instructions first
- decide whether subagents are actually necessary
- keep subagents narrowly scoped
- integrate findings into one final implementation plan
- apply final edits consistently
- ensure a result report is written

## Recommended subagents
### backend-analyst
Read only the backend-relevant files and return:
- findings
- affected files
- proposed changes
- risks

### ui-analyst
Read only UI-relevant files and return:
- UI impact
- affected files
- proposed changes
- manual validation steps

### result-writer
Do not change code.
Only write the final markdown report under `docs/agent-results/`.

## Required final output
1. Files read by main agent
2. Subagents used and why
3. Consolidated plan
4. Changed files
5. Implementation summary
6. Validation steps
7. Result report path
