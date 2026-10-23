# Configurator-specific guidance for the next audit

Keep this context out of shared generic Laravel skills. Read current repository instructions and user decisions first; this document preserves audit constraints and points to existing authorities rather than establishing another behavior specification.

## Authorities and conventions

- `AGENTS.md`, applicable `.ai/rules` if they exist, and the latest user instruction. `.ai/rules` was absent at this packaging checkpoint; recheck next run.
- [Decisions](../../DECISIONS.md), [implementation guidelines](../../IMPLEMENTATION_GUIDELINES.md), [Product-data plan](../../PRODUCT_DATA_IMPLEMENTATION_PLAN.md), [engine/data contract](../../contracts/ENGINE_AND_DATA.md), [public catalog contract](../../contracts/PUBLIC_CATALOG.md), [Filament admin contract](../../contracts/FILAMENT_ADMIN.md) and relevant later workspace plans.
- Existing conventions include `app/Actions`, `app/Services`, singular `app/DTO`, class-based `app/Livewire`, and split resource Pages/Schemas/Tables. Verify siblings before proposing paths; no module/repository framework or new dependencies is implied.
- Use installed-version Boost docs and tools as instructed. Artisan generates supported types with `--no-interaction`; generic classes use `make:class`. Follow meaningful test coverage, Pint and FilaCheck requirements for actual PHP/Filament changes.

## Fresh document clarification

The current Product-data plan records **approved policies dated 2026-10-13**: retain saved filter/preset values no longer observed on Products, allow manual future keys, and accept new values only under its defined observed/retained rules. It also defines conflict behavior for duplicated non-Part keys. The old audits described overlapping implementation as pending; that does **not** mean these domain policy questions remain unanswered. The plan itself distinguishes approved policy from permission to execute concrete implementation choices.

The third scan must establish current implementation status from code and latest authorization. Do not ask the user to decide an already recorded policy again, and do not implement it merely because this handoff references it. Read exact key/ownership/row-lock semantics in the plan instead of generalizing “retain stale values” globally.

Some older guideline paragraphs defer Preview while newer workspace code/plans contain Preview/Test. Resolve such chronology using later decisions and active contracts. Do not remove a newer feature to match a stale introductory paragraph.

## Protected architecture and identities

Preserve the canonical Attribute/Value/Option versus Configurator-local inclusion distinction, exact option codes/identifiers, literal Product keys, source fidelity and JSON shape. A structural cleanup must not change storage authority, source-map hashes, compiler inputs, order/default semantics, signed payloads, revision/coalescing behavior, snapshots or serialized output accidentally.

The aggregate writer, compiler/engine/loader and runtime DTOs are useful existing boundaries. UI operation extraction should call or compose them. Preserve current Product editor and specialized string-transformer semantics; do not replace them with generic helpers by name.

Public discovery intentionally uses snapshot/card boundaries and client computation; the former `prepare()` pipeline is not the current browser path. `vocabulary()` and reconciler responsibilities remain separately live according to the historical audits. Revalidate actual callers before any retirement recommendation.

Respect UI state contracts, including applied versus pending filters, draft retention, `tableFilters` state paths, owner-scoped selection, drawer membership/order, global action behavior, browser-local display preferences and legitimate preview inheritance. A native extension point can replace a compatibility override only if these behaviors survive.

## Safety of the later verification

Read test bootstrap/guard/config before running database-resetting tests. Use explicitly disposable local targets and actual MySQL for lock/constraint-sensitive checks; preserve production isolation. No production DB or remote operations are needed for a structure audit. Do not generate test data from production.

Preserve dirty/concurrent files and worktrees. Snapshot counts/versions/branch/HEAD in historical answers are not current facts. The untracked demo-image directory observed during this packaging run is unrelated to this work.

Keep findings, organization preferences, unresolved overlap and validation needs separate. The user wants a refreshed review first, then review/approval before an exact phased implementation plan. Shared skill installation does not authorize application refactoring.
