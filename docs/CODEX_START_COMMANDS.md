# Codex Start Commands

Use these prompts directly in Codex.
Replace the task text and result filename as needed.

---

## 1. Standard backend task (no subagents)

```text
Read first:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- agents/codex/backend-task.md

Work strictly by these rules.
Do not use subagents unless the task clearly needs multi-part analysis.

Task:
[PASTE BACKEND TASK HERE]

Requirements:
- prefer minimal repository-consistent changes
- no direct XT writes
- create or update a result report under docs/agent-results/[FILENAME].md

Return:
1. Files read
2. Plan
3. Changed files
4. Implementation
5. Validation
6. Result report path
```

---

## 2. Standard UI task (no subagents)

```text
Read first:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- agents/codex/ui-task.md

Work strictly by these rules.
Do not redesign the whole admin UI.

Task:
[PASTE UI TASK HERE]

Requirements:
- keep the current PHP admin structure
- avoid unnecessary dependencies
- create or update a result report under docs/agent-results/[FILENAME].md

Return:
1. Files read
2. UI plan
3. Changed files
4. Implementation
5. Validation
6. Result report path
```

---

## 3. Large task with subagents

```text
Read first:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- agents/codex/review-with-subagents.md

Act as the main Codex orchestrator.
Use subagents only if they improve this task.
If you use them, keep them narrow and assign them one clear responsibility each.

Task:
[PASTE LARGE TASK HERE]

Subagent rules:
- backend-analyst for pipeline, DB, config, service logic
- ui-analyst for public/index.php and src/Web/
- result-writer only for docs/agent-results/[FILENAME].md

Requirements:
- integrate findings centrally before editing code
- avoid broad rewrites
- no direct XT writes
- always produce docs/agent-results/[FILENAME].md

Return:
1. Files read by main agent
2. Subagents used and why
3. Consolidated plan
4. Changed files
5. Implementation
6. Validation
7. Result report path
```

---

## 4. Bugfix task

```text
Read first:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- agents/codex/backend-task.md

Task:
Investigate and fix this problem:
[PASTE BUG DESCRIPTION HERE]

Requirements:
- identify likely root cause before changing code
- keep the fix minimal
- document changed files and manual validation
- write docs/agent-results/[FILENAME].md
```

---

## 5. End-to-end feature task

```text
Read first:
- AGENTS.md
- .github/copilot-instructions.md
- PROJECT_CONTEXT.md
- agents/codex/review-with-subagents.md

Task:
Implement this end-to-end feature:
[PASTE FEATURE HERE]

Requirements:
- decide whether subagents are needed
- if yes, use only narrowly scoped subagents
- keep repository structure intact
- write docs/agent-results/[FILENAME].md
```
