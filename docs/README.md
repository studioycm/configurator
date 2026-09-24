# Catalog rebuild: start here

Updated 2026-09-24. **T01–T12 are implemented; the local fresh-database and rollback rehearsal passed.** The user authorized promotion on 2026-09-24: `master` is the primary development branch in `/Users/studioycm/Herd/configurator`, with `ari.data4.work` as its remote development site. The preserved old application is on `codex/legacy-pre-rebuild`; its database and private files remain separate. See [release evidence](catalog/RELEASE_REHEARSAL.md) for the actual cutover status and workflow.

Manage uses Overview / Groups / Attributes / Rules / Preview & Test. Preview & Test is placeholder-only. D060 remains unassigned after QA by user instruction; real Attributes, Options and codes are required before connecting its production configuration. The latest user instruction authorizes committing, pushing and switching this development site to the rebuilt code and a new database. This does not claim that missing client configuration content is production-ready.

## Read in this order

1. The repository's [AGENTS.md](../AGENTS.md), relevant installed skills, and `.ai/rules/index.md` if that directory now exists.
2. [Implementation plan](catalog/IMPLEMENTATION_PLAN.md): execution order, exact task boundaries, verification and progress.
3. [Implementation guidelines](catalog/IMPLEMENTATION_GUIDELINES.md): settled behavior and research-derived constraints to follow throughout.
4. Only the contract required by the current task: [data and engine](catalog/contracts/ENGINE_AND_DATA.md), [public catalog](catalog/contracts/PUBLIC_CATALOG.md), or [Filament administration](catalog/contracts/FILAMENT_ADMIN.md).

[Decisions](catalog/DECISIONS.md) retains the full discussion, corrections and E00–E16/O identifiers. Consult it for provenance or a conflict; do not restart its historical decision questionnaire. [Research](catalog/research/README.md) is supporting evidence, not another task list. Reading every tutorial or research report again is unnecessary.

## Authority and working state

Later user instructions override these documents. Confirmed business decisions control the implementation. The plan and contracts select technical defaults to make execution concrete; those defaults are identified separately from user decisions. Change a technical mechanism when installed behavior requires it, preserving the contract and recording the reason. Escalate an actual behavior, retention or access conflict rather than silently inventing a requirement.

The primary workspace is `/Users/studioycm/Herd/configurator`, checked out on `master`, using local database `configurator_catalog_dev`. [Local catalog](https://configurator.test/catalog) and [remote catalog](https://ari.data4.work/catalog) now run the rebuild. Reviewed original polish and skills are preserved on `codex/legacy-pre-rebuild` (`1b046ae`); unrelated `herd.yml` and local files remain untouched. The earlier `codex/catalog-rebuild` worktree is retained as implementation history, not the primary editing location. Never reset or stash the entire user's workspace to make it clean.

The normalized CSV and audit live under Git-ignored `storage/app/imports/ari/`; source paths and hashes are in [Decisions §5](catalog/DECISIONS.md#5-product-data-and-normalization). These unchanged inputs have now been imported into the isolated development/rehearsal databases: D060 contains 501 Products. Keep the original workbook and raw client files unchanged.

The implementation and eligible catalog documentation have been committed and pushed under the later promotion instruction. Private CSVs, database dumps, environment files, keys and unrelated local documentation remain outside Git. Forge deploy-on-push remains disabled: work in the primary folder, test/build, commit and push `master`, check the Deployment check workflow, then use Forge **Deploy Now** for `ari.data4.work`. Normal deployment uses only `ari_configurator_rebuild`; it does not repeat the import or overwrite data. See the [deployment and rollback workflow](catalog/RELEASE_REHEARSAL.md#primary-development-workflow).

## Original implementation-session instruction

> Implement the catalog/configurator rebuild using `docs/README.md`, `docs/catalog/IMPLEMENTATION_PLAN.md` and `docs/catalog/IMPLEMENTATION_GUIDELINES.md`. Start with T01, preserve current workspace changes, and execute the tasks in order, prioritizing the visible public catalog. Read each task's detailed contract before editing. Use Laravel Boost and the installed Laravel, Filament, Livewire and testing skills. Keep the task checklist and evidence log current. Continue independent work when client content is missing; do not fabricate parent metadata or production configurator codes. Use isolated development/test databases, preserve the current database, and prepare the rehearsal and release evidence. Do not deploy, push or switch the active/deployment database without the corresponding instruction. Do not reopen settled decisions or repeat completed research unless concrete new evidence requires it.

The original 2026-09-23 session prepared documentation only. Subsequent implementation and verification are recorded in the plan; do not restart completed tasks.

Handoff verification: all 16 original documents reviewed and relocated; 18 final Markdown files, 207 local links and 13 internal anchors checked; all 67 import positions covered once; all handoff files eligible for version control. Application tests were not rerun for this documentation-only change.
