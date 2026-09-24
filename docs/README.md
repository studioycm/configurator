# Catalog rebuild: start here

This is the handoff for a new implementation session. Updated 2026-09-23. The catalog rebuild, import and fresh-database rehearsal have **not** been implemented. Earlier wide-modal and admin-label polish already exists locally.

## Read in this order

1. The repository's [AGENTS.md](../AGENTS.md), relevant installed skills, and `.ai/rules/index.md` if that directory now exists.
2. [Implementation plan](catalog/IMPLEMENTATION_PLAN.md): execution order, exact task boundaries, verification and progress.
3. [Implementation guidelines](catalog/IMPLEMENTATION_GUIDELINES.md): settled behavior and research-derived constraints to follow throughout.
4. Only the contract required by the current task: [data and engine](catalog/contracts/ENGINE_AND_DATA.md), [public catalog](catalog/contracts/PUBLIC_CATALOG.md), or [Filament administration](catalog/contracts/FILAMENT_ADMIN.md).

[Decisions](catalog/DECISIONS.md) retains the full discussion, corrections and E00–E16/O identifiers. Consult it for provenance or a conflict; do not restart its historical decision questionnaire. [Research](catalog/research/README.md) is supporting evidence, not another task list. Reading every tutorial or research report again is unnecessary.

## Authority and working state

Later user instructions override these documents. Confirmed business decisions control the implementation. The plan and contracts select technical defaults to make execution concrete; those defaults are identified separately from user decisions. Change a technical mechanism when installed behavior requires it, preserving the contract and recording the reason. Escalate an actual behavior, retention or access conflict rather than silently inventing a requirement.

The source workspace is `/Users/studioycm/Herd/configurator`. It has uncommitted application polish, selected skills, these documents and unrelated user files. Inspect the diff before editing. A worktree created from HEAD will not contain uncommitted or Git-ignored inputs: carry the relevant reviewed changes/documents/skills into the implementation checkout and make the private source CSV available explicitly. Do not reset or stash the entire user's workspace to make it clean. See plan task T01.

The normalized CSV and audit live under Git-ignored `storage/app/imports/ari/`; source paths and hashes are in [Decisions §5](catalog/DECISIONS.md#5-product-data-and-normalization). They are inputs, not already imported Products. Keep the original workbook and raw client files unchanged.

Only this handoff and `docs/catalog/` are newly eligible for version control. No commit, push, deployment or active database switch was performed by the documentation task. Forge deploy-on-push is disabled.

## Suggested opening instruction for the new session

> Implement the catalog/configurator rebuild using `docs/README.md`, `docs/catalog/IMPLEMENTATION_PLAN.md` and `docs/catalog/IMPLEMENTATION_GUIDELINES.md`. Start with T01, preserve current workspace changes, and execute the tasks in order, prioritizing the visible public catalog. Read each task's detailed contract before editing. Use Laravel Boost and the installed Laravel, Filament, Livewire and testing skills. Keep the task checklist and evidence log current. Continue independent work when client content is missing; do not fabricate parent metadata or production configurator codes. Use isolated development/test databases, preserve the current database, and prepare the rehearsal and release evidence. Do not deploy, push or switch the active/deployment database without the corresponding instruction. Do not reopen settled decisions or repeat completed research unless concrete new evidence requires it.

This session prepared the handoff only. The new session's instruction establishes its execution scope.

Handoff verification: all 16 original documents reviewed and relocated; 18 final Markdown files, 207 local links and 13 internal anchors checked; all 67 import positions covered once; all handoff files eligible for version control. Application tests were not rerun for this documentation-only change.
