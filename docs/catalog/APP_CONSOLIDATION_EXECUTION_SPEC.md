# App consolidation execution specification

**Status: original consolidation and approved legacy retirement merged into local master; merged-result verification complete. R16 is retired with its sole consumer. Real CSV and representative current-app browser checks passed with human assistance. The dedicated QA site/copy has since been retired; worktree/chat retained. Push prohibited.**

**Prepared:** 25 October 2026, Asia/Jerusalem. The user accepted the sixteen-step repair order, reviewed this specification and explicitly approved implementation in one dedicated consolidation worktree with checkpoint reporting. Integration into master requires a separate later approval; pushing is excluded. Earlier approval-gate language below describes that gate and must not be treated as a request to obtain the same implementation permission again.

**Integration update — 25 October 2026:** the user separately approved merging the reviewed consolidation checkpoint c57c44a into local master. The fast-forward from 0567df0 retained the exact reviewed tree and all unrelated pending work. The complete merged-result PHP, MySQL, JavaScript, safety and build matrix passed. The browser CSV-upload limitation remains documented; a fresh postmerge browser smoke could not connect because the browser tool timed out. See CONSOLIDATION_CHANGELOG.md, CON-18, for evidence, preservation and the current five-workstream handoff. No push is authorized.

**Legacy retirement update — 26 October 2026:** after reviewing the exact boundary and an independent final preflight, the human approved full source retirement in the existing consolidation worktree. Remove the 139 legacy fixture files, seven exclusively legacy test files and app/Casts/JsonRuleCast.php; selectively preserve the six current table datasets and Columns drawer test. Current models, migrations, packages, data, imports and compatibility guards remain unchanged. This supersedes the setter-repair proposal below: R16 is retired with its consumer, not repaired by the earlier counterexample tests. Full PHP, disposable MySQL, JavaScript and safety checks pass on the retirement tree. Real browser CSV upload, mapping, mixed completion/rejection, persisted data and failed-row download now pass with human assistance. Representative Master Values read-back, the current Attribute editor and Preview rendering/selection also pass with human assistance. This is selected smoke coverage, not every Product or combination. No new merge or push is approved. See CON-20.

**Retirement integration and QA closeout — 26 October 2026:** the human separately approved merging the retirement checkpoint and then committing all eight pending documentation/UX files. CON-21 records the local merge and fresh merged-result checks; CON-22 records the exact eight-file documentation commit. The subsequent approved CON-23 retires only the dedicated QA domain/certificate, database copy and copy-only login, retaining this worktree, chat, source database and reusable test database. These later decisions supersede the earlier pending-integration statements above. A new full read-only audit of the app on master is authorized; further application repairs are not.

**Full audit and feature handoff — 26 October 2026:** the requested source audit of master `05ed6a9` is complete. [Its report](MASTER_APP_AUDIT_2026_10_26.md) reconciles the original 32 recommendations, records four new current-app findings and reconfirms the separate security work. Proposed regressions are explicitly unrun; passing consolidation suites do not certify those new paths. The original task personally authored the [catalog/Images handoff](CONSOLIDATION_HANDOFF_FOR_CATALOG_IMAGES_REVIEW.md) after consulting its owner. The owner's read-only plan/structure/architecture/design review and Cross-worktree integration audit consistency check are now complete; their clarifications and combined-P6-first proposal are incorporated. No new repair order or application implementation is approved by either document. See CON-25/26.

**Next repair specification — 26 October 2026:** the human selected CSV A/readiness B and requested the separate [steps 5–7 execution spec](POST_CONSOLIDATION_REPAIRS_EXECUTION_SPEC.md), with centralized policy rules, meaningful regressions/checkpoints and an optional queued-streaming investigation. That new specification is awaiting implementation approval; this document's original execution permission does not authorize its new code. AD-01 is independent of CSV and may be a sequential checkpoint while CSV review continues. Security/package work is deferred until explicitly requested, with no automatic final phase; step 8 remains with Plan catalog configuration and UX.

**Goal:** repair the agreed correctness, reliability and responsibility boundaries in the existing app, preserving established behavior and other tasks' work.

**Architecture:** keep the existing authoritative writers, compiler/loader, ProductFields, relationships and revision collector. Repair shared contracts at their existing owners, migrate their affected callers coherently, and keep UI/native lifecycle adapters separate from business writes. Use one implementation worktree for the entire effort, one writer at a time, with read-only independent reviewers.

**Baseline:** primary checkout /Users/studioycm/Herd/configurator, master at 0567df0af03421f84f751d689e75188821064d0f. Confirmed again while preparing this document. PHP 8.4; Laravel 13.35.0; Filament 5.9.0; Livewire 4.4.7; Pest 5.3.0; Filament Blueprint 2.4.0. Composer direct dependencies and package.json were inspected; Boost application-info agrees on the relevant framework versions. Refresh these in the execution checkout. The .ai/rules directory was absent.

Paths in the tables below are relative to that checkout unless absolute. New filenames are proposed implementation locations, not claims that files already exist. No new base directory or dependency is approved.

## 1. Scope, decisions and exclusions

### Agreed behavior

| ID | Decision |
| --- | --- |
| D01 | Use the sixteen-step order in section 4. A technical dependency discovered later must be documented and reconciled; do not silently reorder or expand the programme. |
| D02 | For ordinary edits of an existing complex Rule, reject the whole stale save and preserve the exact losing draft. Do not automatically merge or partially persist it. |
| D03 | Offer manual reconciliation using the familiar Rule form. Controls start checked: checked means take the displayed latest saved value; unchecked means retain my draft value. Updating the form changes the draft only. A later normal Save validates the entire proposed Rule and checks freshness again. |
| D04 | Interrupted-save recovery is option A: retain input in the open editor, distinguish known rollback from committed-but-refresh-failed and unknown outcomes, and inspect current saved state before deciding what to do. Current state is not proof that a particular request succeeded. |
| D05 | Retry only an operation known to have rolled back and classified as transient, at its real transaction owner, with a finite bound. Never blindly replay an uncertain commit, stale save, permission failure or validation failure. |
| D06 | Preserve established input formats and meaning, including exact numeric-looking text, literal Product keys, tag identities and subtitle states. Present concrete before/after examples for approval before any deliberate new acceptance or meaning rule. No such exception is approved now. |
| D07 | Relevant existing Rules/Images correctness and quality findings remain in scope. Completing Image Manager, catalog browsing redesign or Rules Writing is excluded. External prototype corrections stay with their original feature tasks. |
| D08 | One shared execution history must be read before, and updated after, every change. Record actual evidence and changes to dependencies, not merely a list of edited files. |
| D09 | One dedicated consolidation worktree, sequential code changes, one writer. Parallel agents may research or audit without editing code. |
| D10 | Subagents use gpt-6.1-sol only: medium reasoning for research, max for monitoring, high for audits. Never use Astra. Each delegation states its role, model, reasoning effort, target and write boundary. Do not use an old agent with conflicting model settings; launch a correctly configured one. Implementation is performed by the single writer; there is no delegated implementation-model choice in this specification. |
| D11 | User additionally requested MySQL-copy verification during execution. Use an isolated copy of the local configurator_catalog_dev database for realistic-data/browser checks, with a copy-only account and worktree-local runtime. Keep reset-based suites on configurator_catalog_test; never reset the source or silently point the reset harness at the copy. Source inspection/export is read-only. |
| D12 | For future merges, first make both participating checkouts clean: review and commit intentional work in explicit logical groups under the existing authority, or preserve/isolate unfinished work before proceeding. Do not blindly stage unrelated files, secrets, local settings or generated artifacts merely to clear status. A local documentation/checkpoint commit does not authorize implementation, push or deployment. |

### Explicitly excluded

- Actual CI coverage/gates, CI datasets and related security dependency work. Local test-target safety remains in scope.
- New durable operation receipts, a reload-persistent draft platform, automatic replay, edit history or recording who changed every historical version. Retain existing in-session receipts where they already protect a workflow.
- The exploratory reload/request-payload or short-lived browser snapshot recovery idea. Preserve it as a future improvement, not an implementation requirement.
- New access/registration policies, dependency upgrades/removals, mass namespace/provider/enum/helper conversions, a generic repository/lock/retry/adapter framework, or broad cleanup unrelated to a demonstrated boundary.
- Feature completion, importing an entire pending feature worktree, editing the external prototypes, production operations, deployment and pushing.

An inspection may end in **retain**, **already addressed**, **superseded** or **deferred**. Those are valid reviewed outcomes, not reasons to manufacture a refactor.

## 2. Execution rules and ownership

### Before the first code change

1. Obtain explicit user approval of this specification. Acknowledging receipt or approving only a proposed order does not satisfy this gate.
2. Refresh master, branch/worktree attachments, dirty files, versions and relevant incoming changes. Create or reuse exactly one suitable isolated consolidation worktree from the accepted current master, using a codex/ branch. Do not create a worktree per step, copy another task's uncommitted work, or reset/stash unrelated files.
3. Read AGENTS.md and every applicable .ai/rules file if the directory exists by then. Read sibling files and relevant skills before choosing implementations.
4. Establish isolated execution resources: checkout-specific cache/storage/assets and disposable test databases. Confirm no other task is using a destructive-test target. Serialize test runs that share a database or use separately verified targets; one writer does not prevent other chats from running tests.
5. Create docs/catalog/CONSOLIDATION_CHANGELOG.md in the consolidation worktree. This is the one authoritative execution journal; the existing DECISIONS.md and old audit answers remain historical inputs.

### Every change

- Read the approved spec, journal and current source, including unchanged callers on which the change relies. Record the baseline commit and concrete file/hunk scope before editing.
- Use Laravel Boost against the actual implementation checkout: application-info, installed-version search-docs for API/lifecycle choices, schema before any proposed data change, read-only database-query/logs when useful, and get-absolute-url before sharing an app URL. A connection aimed at primary master is not evidence for a divergent worktree. Disclose and resolve wrong-context/unavailable tools; do not pretend they ran.
- Apply laravel-development-architecture and laraveldaily-structure-audit as bounded responsibility checks. Use filament-development/livewire-development and planning-filament where relevant; testing-best-practices before test design. Inspect installed native lifecycle code where documentation does not settle ordering. No repeated whole-app audit is required for each small change.
- Identify the operation owner, actor/authorization, state/input contracts, transaction/lock owner, external effects, direct callers and indirect consumers. Prefer extending healthy existing mechanisms over replacing them.
- Add meaningful regression coverage for changed logic and its important failure modes; use existing tests where sufficient. Do not add tests for copy/layout alone or create verification scripts that duplicate working tests.
- Implement one coherent change; run its focused checks; review it; update the journal before moving on. No second code writer operates concurrently. Same-file adjacency does not make two responsibilities one abstraction.
- Run required local formatting/checks for changed paths. Inspect automated fixes for unrelated changes. Never delete existing tests without approval.
- Make local checkpoint commits only after execution authorization, with an explicit reviewed file/hunk allowlist and associated journal entry. Never use blanket staging. Merge/push permissions do not follow from local commits.
- If a solution needs new data/schema policy, dependency, feature completion, uncertain irreversible action or materially different contract, stop that affected work and return with a concrete proposal. Routine implementation choices within the approved contract do not need repeated permission.

### Shared journal entry

Each entry records: step/change ID; status; original finding IDs; baseline/result identity; problem and reason for the chosen approach; changed paths/methods and unchanged dependencies consulted; preserved/changed input, wire, identity, event, storage, lock and lifecycle contracts; Boost target and relevant guidance applied; exact commands, safe target and results; independent review and open limits; downstream adaptation instructions; and the associated commit when known. Use the stable change ID in its commit message rather than trying to embed a commit's own hash in the same committed file.

Statuses: planned, in progress, implemented, verified, integrated, superseded, retained. Approval, verification, integration and deployment are distinct states. A written test scenario is not a passed test. Every reviewer must cite actual paths/contracts and checks applied; opening a skill file alone is not compliance evidence.

### Preserve concurrent work

At specification preparation the eight pre-existing dirty/untracked paths were:

~~~text
.ai/skills/ux-design-thinking/SKILL.md
docs/README.md
docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_IMPLEMENTATION_PLAN.md
.ai/skills/ux-design-thinking/references/implementation-capabilities.md
.ai/skills/ux-design-thinking/references/workflow-review.md
docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_EXECUTION_BLUEPRINT.md
docs/catalog/SECURITY_FIXES_EXECUTION_SPEC.md
docs/catalog/SECURITY_FIXES_IMPLEMENTATION_PLAN.md
~~~

These belong to other ongoing work and are excluded from this task's edits/staging. Refresh the list; this snapshot is not a permanent allowlist. The separate security specification also proposes a test-target guard: coordinate that shared boundary and reuse an equivalent accepted implementation rather than introduce two competing guards. It does not import its security work into this scope.

## 3. Prerequisites and shared code dependencies

There is no proved unconditional chain requiring every whole solution node to finish before the next can start. The approved sequence combines verification safety, coordinated caller migrations and reduced rework:

- Step 1 gates application/database execution that could reach unsafe targets. It does not block inert source review or a pure refusal check.
- Step 2 migrates one meaningful-comparison contract across its selected producers and consumers. Step 4's coarse stale rejection is technically possible independently; the chosen order avoids conflicting comparison semantics in its reconciliation UX.
- Steps 5 and 6 share **design** of real outer transaction ownership before either is implemented. They are not a circular completion dependency. Partial adoption is not proof that the lock risk is eliminated.
- Steps 7 and 8 share an outcome policy, not a new universal recovery service.
- Steps 9 and 11 supply membership/tag contracts to step 12 only where its actual consumers use them. SQL membership, ordered literal keys and image-owner eligibility remain different projections.
- Steps 3, 10, 13, 14 and 15 have bounded independent ownership. Step 16 is evidence-led and does not precede correctness work.

Healthy upstream owners to retain: SaveConfiguratorDefinition::change and its fresh locked draft; compiler/loader full admission; ProductFields and subtitle normalization; model relationships and CanonicalUsage/InclusionUsage; SaveGroupSettings locking/validation; CatalogRevisions batching; valid scoped service lifetimes. They may support several repairs without needing a rewrite themselves.

## 4. Approved order and execution contracts

For each step below: refresh the cited source; characterize the stated failure or preservation boundary with the named tests; make the smallest selected correction; run focused checks; independently review and journal the result. Candidate new files/tests are not required when existing coverage or an existing owner suffices. A changed shared API must migrate all its actual current callers in the same coherent change, or retain a documented valid bridge with an explicit removal endpoint.

### 1. Safe local test targeting — N16

**Owner/files:** tests/CreatesApplication.php, tests/bootstrap-mysql.php, tests/CatalogMySqlContention.php, phpunit.xml, phpunit.mysql.xml, actual manual/forked bootstrap entries. Proposed shared owner: Tests\TestDatabaseSafety in tests/TestDatabaseSafety.php, with tests/bootstrap.php and a dedicated inert safety configuration if needed. Reconcile the already proposed equivalent names in the separate security spec; there must be one guard.

**Contract:** check the effective driver/URL/database/host/user/port and relevant aliases/read-write overrides, not a connection's display name. Pure checks run before requiring the application; check resolved configuration after environment/config loading and before provider/PDO/migration effects, retaining later defensive checks. Cover alternate named connections, child/worker environments and cached-config overrides actually used by the harness. Default mode permits the intended SQLite in-memory target only; the MySQL mode remains an explicit disposable target. Refuse ambiguous inputs rather than infer safety from APP_ENV or a Unit directory.

The outer php artisan test launcher is an explicit entry point in this contract: artisan boots Laravel before the test runner reaches CreatesApplication. A guard only in tests/bootstrap.php or CreatesApplication is insufficient for that command. Establish a pre-application guarded launch route, or refuse that route and use the guarded direct Pest entry until it is protected. Do not claim the Artisan command safe merely because the later test bootstrap rejects a target. Prove inert refusal for the selected outer launcher before using any Artisan test command below.

The existing dedicated MySQL declaration is 127.0.0.1:3307/configurator_catalog_test with user configurator_catalog_test. This declaration is not proof the live target exists, is disposable, or is unused. Establish that before running it. No development/production database may be reset. If the accepted alias is occupied by another task, establish a separately verified disposable alias and update the guard/config together; never loosen the guard merely to run a command.

**Needed tests:** inert subprocess acceptance/refusal before app/PDO, DB_URL pointing elsewhere despite a sqlite name, process/ENV/SERVER conflicts, forbidden cache/URL overrides, resolved aliases and child entry points. Then establish the safe baseline. Existing home: tests/Unit/MySqlSafetyTest.php; candidate new tests/Unit/TestDatabaseSafetyTest.php plus pure safety configuration. No raw unsafe config may actually connect as a test expectation.

**Done:** all selected test entry points reject unsafe targets before effects; actual isolated PHP/MySQL baseline and any pre-existing failures are recorded. Unit files that explicitly bootstrap the app or migrate are treated as database tests.

### 2. Exact editor comparisons — N2

**Owner/files:** app/Services/ConfiguratorEditorChanges.php; export/acknowledgement paths in ConfiguratorOverview, RulesRelationManager and AttributesRelationManager; ConfiguratorPreview/SaveTestedConfiguratorChanges; resources/js/editor-drafts.js and only affected host projections.

**Interface:** retain normalize(string $type, array $state): array and the existing signed envelope. Add one explicit meaningful-state comparison entry at this owner, proposed sameMeaning(string $type, array $left, array $right): bool, backed by a documented nonvalidating projection. Route selected capture/export/differences/inspect/prepare/rebase/ack decisions through it; use compatible field comparisons for acknowledgements. Pure comparison does not need an actor. Mirror the same meaningful projection in the affected JavaScript guards and prove agreement with shared cases, without altering the signed wire bytes merely for equality.

**Contract:** exact text including 001 versus 1; intentional transport-equivalent IDs/booleans; meaningful list order; literal dotted/Unicode keys; absence versus explicit clear; existing blank/null and none/custom/live-link subtitle meanings. Preserve unfinished/invalid raw Rule drafts without demanding successful full compilation just to detect changes. Do not strip underscore-prefixed user data or replace every host's draft store.

**Needed tests:** tests/Feature/ConfiguratorEditorChangesTest.php, ConfiguratorPreviewReconciliationTest.php and ConfiguratorPreviewSnapshotTest.php; tests/Unit/EditorDrafts.test.js, ConfiguratorPreviewWorkspace.test.js and AttributeEditorDraft.test.js. Trace exact Rule operand through capture, Preview, save and engine; verify later typing survives acknowledgement/rebase, no-op changes stay clean and invalid unfinished writing remains available.

### 3. Explicit actor context in captures — N4

**Owner/files:** ConfiguratorEditorChanges and its real UI/Preview/SaveTestedConfiguratorChanges callers.

**Interface:** add explicit App\Models\User $actor to capture, verifiedRows, inspect, captureHash, prepare and savedRows, and their actor-dependent internal signature/authorization calls. Preserve the remaining existing parameters/returns. Use Gate::forUser($actor), pass the initiating actor from each real caller, and preserve same-actor envelope/signature composition. No actor singleton/scoped mutable property; no optional ambient fallback in the migrated operation API.

**Needed tests:** ConfiguratorEditorChangesTest.php and ConfiguratorPreviewWorkspaceTest.php. Authorized explicit actor with no ambient login works; a different ambient user does not change whose authority/signature is evaluated; wrong actor, revoked permission and foreign scope are rejected without writes. Same actor roundtrips unchanged.

### 4. Stale-edit protection and manual reconciliation — N3

**Owner/files:** app/Filament/Resources/Configurators/RelationManagers/RulesRelationManager.php, ConfiguratorRuleForm/schema factory and its Blade/translation adapters as required; existing SaveConfiguratorDefinition::change remains the write authority. Do not introduce another aggregate writer.

**Write contract:** when opening an existing Rule, retain a trusted server-held opening base for its owned editable definition, including children; updated_at alone misses child-only changes. Re-check the base against the freshly loaded Rule inside the existing locked change callback, before replacement. Preserve fresh priority, unrelated aggregate edits and current kind/ownership restrictions. New Rule creation keeps its current staged-ID semantics. Do not silently extend this policy to every editor, immediate default/reorder command, or source-wins importer.

**Conflict UX:** keep the exact draft and original base; open a native Filament\Actions\Action modal explaining that the Rule changed. Display the latest saved values and a familiar editable draft form built from the existing Rule schema. Use separate full state paths so two live instances never share a field binding. Controls above the form default to checked = use the displayed latest saved value; unchecked = retain my draft. Treat dependent nested structures as coherent groups (Mapping content or Advanced condition/effect structures), rather than mix incompatible children field-by-field. Kind remains subject to the current fixed-kind contract.

**Actions:** Update form changes only selected draft fields and sets the expected base to the exact latest snapshot displayed for that review; it does not save. Cancel/keep editing leaves the original draft recoverable. The later normal Save recompiles/validates and checks that reviewed base again under locks. If another edit intervened, reject and show a fresh conflict; never silently rebase to an unseen version. Checking nothing still requires this explicit review/update action before a deliberate later save. No history screen, automatic merge or partial persistence.

**Component/authority:** Filament\Actions\Action, existing Rule field components and validators, Filament\Forms\Components\CheckboxList or equivalent native checkboxes for refresh choices; one-column modal with full-width nested Rule sections. Action is visible only for an existing Rule conflict, authorizes manage-catalog and revalidates owner membership on execution. Existing validation/authorization and error locations remain authoritative. Read-only latest display and choices never themselves mutate persistence. Restore focus to the Rule editor after updating/cancelling; checkbox labels state which saved values they select.

**Needed tests:** tests/Feature/Filament/ConfiguratorRulesTest.php plus ConfiguratorEditorChangesTest.php. Two ordinary sessions; ordinary versus Preview/import; child-only intervening edits; deleted/foreign Rule; no partial write; unrelated Rule and priority preserved; checked/unchecked/group choices; exact losing input and cancel preserved; second edit after modal review rejected. For both Mapping and Advanced, edit the actual reconciliation modal's fields, select coherent nested groups, use Update form, then later normal Save. Assert isolated form state paths, exact draft transfer, zero persistence during Update form and exact final persisted values; ordinary-form or checkbox-only tests cannot prove the new modal lifecycle. Browser-check the real two-tab workflow, keyboard/focus and narrow viewport. Existing full-form compile remains the final admission rule.

### 5. Consistent lock acquisition — N1

**Owners/files:** existing Configurator/canonical/status/Group/Product/assignment/batch writers and real outer callers, ConfiguratorEditorChanges::fingerprint, Rules import, CatalogIntegrity/CanonicalUsage and the native chunk boundary in step 6. Include create/delete paths that compete for the same rows; pending Images gets a migration obligation, not edits here.

**Design gate, shared with step 6:** enumerate actual selected outer roots, lock sets and observed order, including implicit FK/index effects relevant to the selected schedules. Choose and document a deterministic complete lock-set acquisition/revalidation protocol before changing participants. Discover required owners before lower-row acquisition, lock same-kind sets in stable order, and revalidate membership/freshness while held. An inner sorted query cannot repair an earlier reverse acquisition. Do not add a universal lock manager, serialize the entire catalog, alter chunk size or change throughput policy as an unreviewed shortcut. If full-set acquisition cannot be made correct within these contracts, return with the bounded serialization alternative and its tradeoff before adopting it.

**Implementation boundary:** migrate all current competing roots selected by that graph, including outer chunk selections, not only the ordinary/Preview pair. Retain existing authorization, complete compiler admission, revisions and atomicity. Nested calls follow the outer protocol. Retry only known rolled-back transient outcomes at the outer owner, with a documented finite bound; never retry stale/validation/permission failures or external effects blindly.

**Needed tests:** dedicated actual MySQL ConfiguratorPreviewSaveConcurrencyTest.php, CatalogCreationConcurrencyTest.php, CatalogStatusConcurrencyTest.php and CatalogImportTest.php under tests/Feature/Catalog/MySql, using tests/CatalogMySqlContention.php. Add a deterministic two-Configurator shared-Attribute/context schedule, Group/Configurator and Group/Product schedules implicated by the graph, multirow chunks, retry exhaustion and no partial state/revision. Existing same-owner tests are insufficient. SQLite or source-order assertions alone cannot certify this result.

**Done:** the selected participant graph and actual-engine schedules support the claimed invariant. State residual database contention honestly; an eliminated identified cycle does not mean all possible deadlocks are impossible.

### 6. Native import transaction failures — N9

**Owners/files:** app/Filament/CatalogDataActions.php, affected CatalogImporter subclasses and proposed app/Filament/Imports/Jobs/ImportCatalogCsv.php. Preserve vendor files. Installed Filament 5.9.0 provides ImportAction::job(?string $job): static and an overridable native ImportCsv::handle(): void. The dispatcher constructs the selected job with import, rows, columnMap and options; inherit that constructor, middleware, backoff, tags and encoding behavior.

**Contract:** a native chunk remains one outer transaction. Legitimate RowImportFailedException/ValidationException row failures retain native continuation and failed-row records; a transaction-invalidating infrastructure error escapes to the outer transaction owner. Rollback leaves no earlier chunk row writes, processed/success counters or failure rows committed. Reset all attempt-local counters, failedRows and importer/model state before any supported replay. Preserve mapped/unmapped fields, source-wins update_existing, separate imports and native notifications' factual counts. Do not convert this into per-row commits or one-row chunks.

**Verified limitation:** the native handle catches Throwable around each row and exposes no row-error classification hook. A wrapper around parent::handle() cannot fix swallowed errors. A narrowly documented application subclass may replace this bounded orchestration, with installed-version parity tests; no broad vendor fork.

**Outcome bound:** permit finite automatic retries only for driver outcomes whose whole-chunk rollback is established. A disconnect/commit uncertainty is not that proof. Native refresh/event work after commit can fail, and dispatcher finally() marks completion even after job failures. Do not automatically replay a known-committed chunk because notification/refresh failed. Reconcile current import counters/status and report infrastructure/unknown outcomes honestly; do not claim exact-once delivery or produce a fabricated row-error CSV. A job subclass alone does not solve terminal batch completion. At the shared design gate, prove the bounded completion treatment through the native dispatcher/job path; if a dispatcher extension or new durable identity is required beyond these limits, present that concrete proposal rather than silently add it. New durable receipts remain excluded.

**Needed tests:** tests/Feature/CatalogDataExchangeTest.php and Catalog/MySql/CatalogImportTest.php; candidate tests/Feature/CatalogNativeImportTransactionTest.php. Drive the actual native job: success plus legitimate rejected row; earlier successful row then invalidating error; rollback/retry with accurate counters and no duplicate failed rows; retry exhaustion; postcommit event failure without row replay; and terminal/unknown status. Application importer unit tests alone do not prove native orchestration.

### 7. Save and export recovery — N11

**Owners/files:** ConfiguratorPreview, resources/js/configurator-preview-workspace.js, affected open-editor adapters; app/Filament/Exports/Jobs/ExportConfiguratorRulesCsv.php and its actual preparation/download chain. Keep each operation's effects with that owner.

**Contract:** classify validation/stale/permission rejection, established rollback, committed operation whose refresh failed, and unknown result. Preserve the open draft and existing in-session acknowledgement protections. Read current state for reconciliation; do not claim it identifies the exact request. Bounded retries apply only to known rolled-back transient operations. Keep later typing while accepting acknowledgements for earlier content. No new operation ledger or reload-draft guarantee.

For exports, files do not roll back with SQL. Separate staging/publication/metadata outcomes in the existing job; a broad catch must not delete a valid committed final file because a later step failed. Preserve export identity, authorization, counters, CSV/XLSX contents and native retry/download contracts. Use a stable job-owned file identity; uncertain commit/publication requires reconciliation before cleanup/replay.

**Needed tests:** ConfiguratorPreviewSnapshotTest.php, ConfiguratorPreviewReconciliationTest.php, ConfiguratorRuleExporterTest.php and ConfiguratorPreviewWorkspace.test.js. Inject failure before write, after commit/before UI refresh, lost confirmation and later typing. For exports exercise staged failure, successful publication, later callback failure, retry and an authorized real download; assert file/content/counter state together.

### 8. Rules-import recovery — N10

**Owners/files:** app/Services/ConfiguratorRuleImport.php and app/Filament/InteractsWithConfiguratorRuleImport.php.

**Contract:** preserve the cache claim's single-use, actor/scope/expiry and fingerprint checks. When a known failed/rolled-back application has consumed its token, retain file/options/source, invalidate that displayed preview and offer a fresh preview and confirmation. An unknown result first needs saved-state reconciliation; never restore a consumed token or automatically apply it again. A fresh preview obtains current authority and data, not a reused old approval.

**Needed tests:** ConfiguratorRuleImportTest.php and ConfiguratorRuleImportActionTest.php. Known rejection/rollback with retained input, spent displayed preview, fresh token/current scope, old-token replay denial, ambiguous outcome with no blind replay, whole-file atomicity and unchanged unrelated Rules.

### 9. Shared membership queries and ordering — N5

**Owners/files:** app/Services/ItemLists.php, ItemListOrdering.php, related drawer/assignment callers and existing model/usage relationships. A focused ItemListMembers service under existing app/Services is permitted only if a native relation/existing owner cannot express the shared finite projection clearly; it is not a registry/repository framework.

**Contract:** separate finite authorized owner/member selection from headings, translated columns, resource URLs and eager-loading presentation. ItemLists may keep decorating the result; ordering must not depend on constructing a UI definition. Preserve unknown/foreign/deleted-owner denial, exact local/canonical IDs, full reorder versus selected subset, descending Rule priorities and literal ordered Group card-property strings. SQL queries and literal-key arrays need distinct representations. An image target's owner leaf Group is not the same thing as its card-property members.

**Needed tests:** AttributeValueAssignmentTest.php, AttributeValueBatchTest.php, Catalog/ConfiguratorDefinitionTest.php, Filament/ItemListOrderingTest.php and RowOrderingTest.php. Use asymmetric owners and exact identities, stale/duplicate/missing members, complete reorder validation and no partial writes. Record the pending CatalogAssetTargets adaptation contract without editing or merging its feature.

### 10. Narrow Group presentation updates — N7

**Owner/interface:** add patchPresentation(User $actor, Group $group, array $changes): Group on app/Actions/SaveGroupSettings.php; retain handle() for the full editor. Migrate actual presentation-only callers, currently demonstrated in app/Actions/BatchCatalogChanges.php. Remove their use of GroupForm::settingsState as persistence authority; do not merely relocate that serializer.

**Contract:** authorize manage-catalog, use the existing Group lock and revisions, validate only supplied allowed presentation keys, and merge those into raw persisted result settings. Preserve absent keys, false, zero and explicit empty arrays, literal Product keys and current leaf policy. Do not reconstruct vocabulary, materialize unrelated defaults, delete/recreate filter/preset rows, change their IDs/labels/value order, or touch unrelated settings. Keep no-op revision behavior and one logical revision under existing outer batching.

**Needed tests:** Filament/BatchCatalogActionsTest.php, Catalog/GroupSettingsTest.php and ItemListOrderingTest.php. Seed a retained value absent from current vocabulary, custom labels/order/IDs and unrelated raw settings; patch one presentation key and assert all remain exact. Cover empty/false/zero/no-op, invalid key rollback and batch revision behavior. A narrow architecture guard against this specific application-writer-to-UI-serializer dependency is acceptable; no blanket folder-purity project.

### 11. Consistent tag identity — N6

**Owners/files:** app/Models/Value.php, ManageValueTags/SaveValueTags/canonical/create/batch writers, ValueImporter, ManageTags, ValueTagFilter and the shared tag field currently exposed by ValuesTable. Keep accepted-input validation and UI feedback at their appropriate boundaries.

**Contract:** one exact string-identity rule through acceptance, writes, vocabulary, counts and filtering. Preserve case and numeric-looking strings (0, 001, 1), exact existing source tags including legacy whitespace, existing permitted new-destination trimming, selected removed-tag fallback, option order and one usage count per Value. Avoid numeric-key coercion in options/suggestions. Strict distinct/read corrections must agree across real create/import/row/batch paths. Shared field receives explicit options/context instead of depending on a concrete resource. Do not rewrite existing data, add a tag table or add persistent caching without a separate demonstrated need.

**Needed tests:** ManageValueTagsTest.php, TagWorkspaceTest.php, Filament/ScopedTableSearchTest.php, CatalogDataExchangeTest.php and BatchCatalogActionsTest.php; add a dedicated MySQL identity case only for selected engine-dependent JSON/string semantics. Verify exact included identities, rename/merge/remove, repeated tag per Value, case/numeric/whitespace variants, retained selected options and AND/OR filters. Deliberate new input meanings still require D06 review.

### 12. Shared tables and framework adapters — N8

**Owners/files:** app/Filament/Resources/TablePresentation.php, InteractsWithScopedTableSearch.php, TableBrowserProfile.php, affected table factories/hosts, WorkspaceTableSelect/WorkspaceTablePicker, ValueForm::appendRows and their existing JS/Blade adapters.

**Contract:** make the finite host capabilities and search/profile/tag inputs explicit; migrate only affected consumers. Preserve SQL and array tables, pending versus applied filters, hidden-field search scope, authorization, translated headers, profile identity/reset/width/order state and native selection. Reuse steps 9/11 only where their contracts actually apply. No second selection/profile state store or universal capability manager.

Keep three native protocols separate: picker model/record/relationship/bound-state/selection; Repeater UUID/raw-state/child-fill/partial-render append; and future image upload owner/context/operation provenance. Retain justified native subclasses/overrides where no equivalent extension exists. Pending upload work is an adaptation note, not feature completion.

**Needed tests:** Filament/ScopedTableSearchTest.php, TableConfigurationStandardsTest.php and ItemListOrderingTest.php; AdminProfile.test.js and WorkspaceTableSelection.test.js. Browser-check representative SQL and array hosts, native picker select/deselect/reopen, nested append/focus and profile restore/reset. Reuse existing creation coverage for native append; no source-text assertion alone establishes lifecycle correctness.

### 13. Startup registration and service wiring — N15

**Owners/files:** resources/js/app.js and catalog-filters.js as implicated; app/Providers/AppServiceProvider.php and bootstrap/providers.php are preserved unless a concrete selected wiring correction needs them.

**Contract:** use one idempotent registration routine for the event-first and already-present Alpine paths; include catalogShell and the existing complete current registration set. Include separately registered filter callbacks in the startup review. Do not double-register listeners or lose current creation/picker/Preview controls. Preserve nonpanel Rules XLSX binding, scoped AdminAppearance/CatalogRevisions identity and late-evaluated actor/panel callbacks. A provider split is not required.

**Needed tests:** CatalogShell.test.js plus candidate AlpineRegistration.test.js executing both actual startup branches/callbacks and once-only behavior; ConfiguratorRuleExporterTest.php for nonpanel binding. Build and browser-check normal load and client navigation/reinitialization. Pending Images must later take the semantic union with this baseline rather than overwrite app.js with its older copy.

### 14. Appearance cache consistency — N12

**Owners/files:** app/Services/AdminAppearance.php and app/Actions/SaveAdminAppearanceSettings.php. Retain scoped lifetime.

**Contract:** same-instance warm/cold reads, transactional writes and outer rollback must agree. Do not publish uncommitted settings to shared cache or retain rolled-back values in a scoped memo. A rollback callback alone is insufficient if warm memo reads remain stale or transactional cold reads are promoted incorrectly. Preserve normal committed reads and existing callbacks; no provider-lifetime redesign.

**Needed tests:** Filament/AdminAppearanceTest.php with the same resolved instance before/inside/after inner save and outer rollback, caught nested rollback, repeated save and committed path. Assert both memo-visible values and shared-cache publication timing; a fresh-request rollback test alone is insufficient.

### 15. Legacy JSON persistence — N13 / original R16; retired by approved legacy removal

**Current disposition:** on 26 October 2026 the human approved full legacy retirement after source/coverage audits and a final preflight. JsonRuleCast and its sole ConfigProfile consumer are removed together in the dedicated worktree; automated checks pass. R16 is retired in that tree and now on local master through the separately approved CON-21 merge. The corrected 25 October reopening and unimplemented setter proposal below remain historical research; do not implement that now-obsolete proposal or call the cast repaired.

**Actual owner/consumer:** app/Casts/JsonRuleCast.php::set(), used by tests/Fixtures/Legacy/Models/ConfigProfile.php::extra_rules_json. The legacy factory supplies [], its legacy textarea supplies JSON text, and its legacy migration defines a nullable JSON column. No current production model registration was located. Original FINDINGS_REGISTER R16 and the earlier pattern-domain-boundaries F6/R16 analysis already identify this cast correctly.

**Lineage correction:** tests/Fixtures/Legacy/Casts/NormalizedIntArrayCast.php on OptionRule is a different, already-correct cast. CON-15's six persistence characterizations and PHPDoc correction remain valid but do not close JsonRuleCast/R16. The earlier source analysis explicitly used that cast as a healthy counterexample. At the CON-19 reopening, no decision had separately retired the original finding; the later approved retirement is recorded above and in CON-20.

**Historical research evidence — 25 October 2026:** installed Laravel 13.35.0 HasAttributes::setClassCastableAttribute()/normalizeCastClassResponse() merges an array cast result into raw model attributes. JsonRuleCast::set() currently returns the payload array. A no-application-bootstrap, no-DB in-memory assignment on the actual ConfigProfile model reproduced: a `name` payload key overwrites model name while old extra_rules_json remains; list payloads add numeric attribute keys; [] and JSON text `[]` do not clear the old value. Malformed JSON text currently sets null. This proves the assignment defect; a new database persistence regression has NOT run and no production corruption event is claimed.

**Superseded setter-fix proposal, not implemented:** retain the class/location and get() behavior; make set() return ?string. Preserve null/empty string -> null, PHP arrays as accepted payloads, and existing lenient json_decode(..., true) for other inputs; malformed/scalar decoded values remain null. Encode accepted arrays with JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION. Do not use JSON_NUMERIC_CHECK. Empty arrays/JSON text `[]` write JSON `[]`, clearing old rules. Non-encodable arrays raise JsonException instead of silently storing false or clearing data. Existing decoded-array normalization remains; this is not a new lossless JSON text codec or schema/validation policy.

**Alternatives rejected:** a native array cast changes JSON-text input, scalar and lenient-read behavior. An explicitly keyed encoded attribute map is valid but unnecessary for this single-column cast. Moving legacy support, changing NormalizedIntArrayCast again, migrations, model/factory changes, and current Product JSON refactors are outside this fix.

**Superseded setter-test proposal:** proposed tests/Feature/LegacyJsonRuleCastTest.php using the real legacy ConfigProfile factory, its extra_rules_json column and existing LoadsLegacyFixtures isolated SQLite-memory setup. Prove nonempty map/list persistence from PHP arrays and JSON text; payload keys such as name cannot change unrelated model attributes or create stray columns; nested strings `001`, Unicode, false/null and float 1.0 roundtrip strictly; empty array/JSON empty array clears existing rules; null/empty/malformed/scalar inputs retain null-write behavior; malformed/scalar/null raw reads remain []; encoding failure leaves model attributes/persisted data unchanged. Preserve existing legacy tests and the valid NormalizedIntArrayCast coverage.

**Superseded setter-verification plan:** demonstrate the actual consumer regression red, implement the bounded setter change, rerun its focused file and tests/Feature/ModelRelationshipsTest.php plus tests/Feature/Filament/ConfigEngineDemoInteractionTest.php, run Pint for changed PHP, and run the complete guarded default PHP suite before integration. Legacy fixtures are restricted to SQLite memory; no MySQL-copy schema changes/resets, JS/build/browser expansion or deployment is needed for this pure cast correction. Record results honestly; current earlier suite counts do not establish this missing persistence contract.

### 16. Presentation responsibilities and data loading — N14

**Owners/files:** app/Models/ConfiguratorRule.php::workspaceSummary/presentationSummaries and its real Rules table consumer; separately ConfiguratorEditorChanges::describeChanges/errorLocation and Preview consumers.

**Contract:** these are two distinct inputs: loaded Eloquent Rule relationships and typed capture/canonical arrays. Preserve exact summaries, translations, fallback labels, order and error locations. The actual Rules table already eager-loads its graph; a query regression has not been measured. Keep existing small presentation methods when clear. Centralize an explicit required graph or extract a focused representation only for demonstrated benefit; no universal presenter or mandatory model split.

**Needed tests:** tests/Unit/OptionRulePresentationTest.php (despite its directory it uses database migrations), ConfiguratorEditorChangesTest.php, Filament/ConfiguratorRulesTest.php and AdminTabLoadingTest.php where applicable. Assert real consumer output and controlled loading/query behavior for the selected change. Record retain if no useful change remains; optional extraction is not a prerequisite to completion.

## 5. Checkpoints and acceptance gates

Checkpoints are technical review/evidence boundaries. After execution approval, passing one permits the next sequential work within scope; they are not repeated permission prompts. A scope/meaning change still returns to the user. No implementation has begun at any checkpoint in this document.

| Checkpoint | Included work | Required evidence before advancing |
| --- | --- | --- |
| CP0 — approved entry | User approval, one worktree, resource ownership, journal, rules/versions refresh | Recorded clean separation from other work; approved baseline and actual target; safe initial guard workflow. |
| CP1 — safe baseline | Step 1 | Inert guard refusal/acceptance, safe effective application targets, isolated baseline results and known pre-existing failures. |
| CP2 — coherent editor route | Steps 2–4, each reviewed separately | Exact capture/ack/rebase behavior, explicit actor, real stale Rule rejection/reconciliation and two-tab browser evidence; unchanged compiler/writer invariants. |
| CP3 — transaction integrity | Joint design and steps 5–6 | Outer-root lock graph and viable import completion boundary recorded before coding those changes; actual MySQL two-owner/chunk schedules, rollback/counter/failure-row outcomes. No unsupported exactly-once claim. |
| CP4 — truthful recovery | Steps 7–8 | Known/committed/unknown distinctions, retained open draft/input, no blind replay, export file/metadata consistency, single-use import recovery. |
| CP5 — shared boundaries | Steps 9–12 | Membership/identity/presentation preservation; no UI serializer in narrow Group writes; affected consumers migrated; native state/profile/picker behavior. |
| CP6 — lifecycle and disposition | Steps 13–16 | Startup parity/idempotence, same-instance rollback/cache evidence, cast disposition corrected, presentation retained or improved with evidence. |
| CP7 — final reviewed checkpoint | Full selected implementation | Combined-state checks below, independent architecture/integrity audit, every finding disposition and dependency handoff, reviewed allowlisted commits. |
| CP8 — integrate later | Separate explicit user merge approval | Reconcile newer master, verify exact combined tree and run affected/full checks as warranted. Report local master result; do not push. |

If CP3 cannot prove a viable lock/import boundary within the selected constraints, hold those affected implementations and present the specific unresolved alternative. Do not claim that adding inner retries or a subclass alone closes the findings. Research/design prerequisites may be completed read-only ahead of their implementation step without changing the approved code order.

## 6. Verification commands and stopping rule

**This preparation ran no application test suite, browser walkthrough, build, formatter, migration or database query.** The commands below are future execution instructions. Historical counts from other checkpoints are not acceptance evidence for this result.

### Safe execution first

- Prove the inert guard entry before framework-backed checks. Even a test under Unit can boot/migrate; inspect its actual traits/bootstrap.
- The current composer test script clears configuration before running tests, and composer setup installs/migrates/builds. Neither is the initial safe guard command. Do not clear another checkout's caches or use setup as a test shortcut.
- Use the approved guarded test entry. The existing dedicated MySQL configuration excludes no hazard merely by its filename. Verify effective target and exclusive resource use before running it; workers must repeat the check after purging inherited connections.
- The outer Artisan launcher also needs the step 1 guard. If that route is not proven protected, use the guarded direct Pest equivalent for the default suite and focused paths; do not run an unguarded Artisan process to discover whether the test bootstrap catches it.
- Use existing factories and minimal asymmetric data. No production snapshot fetch, package installation or network test-data dependency is part of this spec.

### Focused checks during implementation

~~~sh
php artisan test --compact tests/Feature/ConfiguratorEditorChangesTest.php
vendor/bin/pest tests/Feature/Filament/ConfiguratorRulesTest.php
node --test tests/Unit/EditorDrafts.test.js tests/Unit/ConfiguratorPreviewWorkspace.test.js
vendor/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Catalog/MySql/ConfiguratorPreviewSaveConcurrencyTest.php
~~~

Choose the exact named files from the owning step; these examples do not require rerunning unrelated suites after every small edit. The inert safety suite's final command must be recorded when step 1 establishes its bootstrap/config; do not launch an invented unguarded command in advance.

After modifying PHP, run vendor/bin/pint --dirty --format agent. After modifying app/Filament files, run vendor/bin/filacheck --fix --dirty and resolve relevant remaining violations; review every auto-fix and rerun affected tests if behavior changed. Lint/diff checks must cover new files too; git diff --check alone does not inspect untracked content.

### Final implementation checkpoint

After focused checks pass, the writer runs the complete selected suites in the safe isolated environment rather than handing the full run back to the user:

~~~sh
php artisan test --compact
vendor/bin/pest --configuration=phpunit.mysql.xml
npm test
npm run build
git diff --check
~~~

Also complete the targeted real-browser checks for changed editors/recovery/table/native/startup behavior, review recent browser logs, and confirm the built assets/runtime are from the exact tested checkout. Use actual MySQL for concurrency and any selected engine-dependent identity claim. No new browser testing dependency is required; use available browser tooling. Hardware IME and exhaustive interaction coverage are not automatic gates: add a targeted check only if a concrete changed composition/native-input path or failure justifies it.

Record commands, exit codes, test counts/failures, target identity, checkout commit plus uncommitted overlay, assets and gaps. The final source state must correspond to the checks. Repeat affected checks after any later code/formatting/integration change; repeat a full matrix only when new changes, failures or unresolved concerns warrant it. Stop expanding testing once agreed acceptance and required checks pass. Unrun/blocked required checks remain visible and block a passing claim.

Independent audit at CP7 checks the approved behavior, all current affected callers, writer/lock/actor/effect boundaries, native lifecycle, unchanged-data preservation, journal completeness and new-master compatibility. Use gpt-6.1-sol high; reviewers remain read-only. Monitoring, if needed, uses gpt-6.1-sol max and reports only meaningful progress/blockers. Do not create an automation unless separately requested.

## 7. Other tasks and later integration

- The audited Image Manager implementation was uncommitted in /Users/studioycm/.codex/worktrees/catalog-images-p0-p1/configurator at detached 244b305, not the full manager on this master baseline. Its catalog browsing redesign is a different feature from the same chat. Refresh actual status before later integration; do not infer status from a chat title.
- Rules Writing and catalog browsing prototype corrections are open work with Research Rules import and export and Plan catalog configuration and UX. Previously corroborated candidates: Writing capture provenance/stale retained-name behavior; catalog preset/direct-choice reconciliation and the below-six caption mismatch. They are prototype source findings, not reproduced current-app failures. No edits in those external sources are authorized here.
- Preserve changed-contract notes for pending consumers: capture/equality/actor and stale-save contracts; ItemLists membership versus image owner Group; Group presentation patch; ProductFields and fingerprints; native upload provenance; app.js registration union; nonpanel XLSX binding. Later feature work must compare its actual source to the repaired baseline before building on or merging affected behavior.
- Existing human authorization permits substantive read-only consultation with those two named chats about reasoning/correctness and repair information. It does not authorize fixes, pauses, tests, deployment or messages to unrelated chats. Agent replies are evidence, not human approval.
- At CP7 compare incoming master commits and dirty state to the approved base. Preserve other tasks' hunks, investigate semantic overlaps as well as textual conflicts, and prepare the exact reviewed checkpoint for the user's later merge decision. No automatic merge, archive, branch deletion, force operation or push.

## 8. Evidence lineage and dispositions

The original architecture handoff remains docs/catalog/research/laravel-architecture/README.md with FINDINGS_REGISTER.md, both original audit answers and ARCHIVE_PROVENANCE.md. The private archive /Users/studioycm/.codex/research/laravel-architecture/2026-10-21/MANIFEST.json records 171 evidence entries, not 171 findings; recorded truncation/encryption gaps remain. Do not copy raw private evidence or claim unavailable material was read. The cross-worktree audit is a separate source; no integration-caused feature loss established in inspected paths was never an all-clear for degradation or runtime correctness.

Planning inputs: /private/tmp/ari-solution-research/CURRENT_SCOPE.md, EXECUTION_RULES_FOR_REVIEW.md, PRELIMINARY_REPAIR_PREREQUISITES.md, PRELIMINARY_HEALTHY_UPSTREAM.md, PRELIMINARY_ORDER_EVALUATION.md, UPSTREAM_DEPENDENCY_MAP.md and UPSTREAM_DEPENDENCY_CHALLENGE.md; /private/tmp/ari-priority-review/CONSOLIDATED_THREE_LEVEL_REVIEW.md; /private/tmp/ari-integration-audit/code-quality-addendum.md. Temporary files are supporting provenance; the contracts, decisions and acceptance requirements needed for execution are copied into this durable specification. If an evidence claim cannot be refreshed, record that limit rather than treat an old report as current proof.

| Original finding family | Disposition in this specification |
| --- | --- |
| R01 / INT-04 | Step 10 narrow writer; the old blanket Group stale-vocabulary rejection claim is superseded. |
| R02 / INT-02 / PW-01,03 | Steps 5–6, supported locking/transaction risks requiring actual-engine verification; no historical incident asserted. |
| R03 / INT-03 / PW-02,06 | Step 4 owned Rule stale protection; already good create/add/default/reorder boundaries retained. Step 9 handles ordering's membership dependency. |
| R04 / R10 / PW-07 | Steps 2–4 and 7; optional representation in 16. Capture comparison, actor, presentation and completion remain distinct responsibilities. |
| R05 / R18 | Step 11, exact tags plus shared field, not an automatic cache/data migration. |
| R06–08 | Step 12 finite capabilities/native adapters; relevant startup/wiring in 13; justified overrides retained. |
| R09 | Step 9 distinct authorized membership projections and downstream adaptation notes. |
| R11 | Retain valid StatusActions review orchestration; its lock participation is covered in 5. |
| R12 | Superseded importer-as-field-authority advice; retain current ProductFields and literal-key authority. |
| R13 | Retain live CatalogDiscovery vocabulary and distinct prepare callers; no inferred deletion. |
| R14 / R15 / R17 | Steps 16 / 14 / 13 respectively; retain the presentation boundary, repair scoped memo consistency and startup registration. |
| R16 | Retired on local master through CON-21: full legacy removal includes JsonRuleCast and its sole ConfigProfile consumer. The earlier NormalizedIntArrayCast characterization did not fix this defect. CON-20 records source/coverage closure; CON-21 records the approved merge and fresh merged checks. |
| O01–05 | Retain valid factories, existing Actions/Services/enums/policy naming and serialization. No mass reorganization. |
| O06–08 | Preserve wiring/lifetimes and actual package consumers in 13; explicit actor in 3. No package purge or compulsory provider split. |
| O09 / V02 / INT-01 | Step 1; optional test reclassification must preserve its real lifecycle and is not the safety fix. |
| V05 | Narrow behavior/boundary guards with actual repaired code, particularly step 10; no blanket architecture enforcement project. |
| V01,03,04 / INT-05 | CI/security-adjacent expansion remains outside this repair programme. |
| ARC-01 / PW-04,05 / INT-06 | Rules recovery 8; owner-specific completion 7 and memo 14; startup 13. |
| G1–4 / INT-07 | Pending feature compatibility and honest selected-state evidence in section 7; feature delivery is not silently included. |

### Version-matched references consulted

- Laravel database transactions/deadlock attempts: https://laravel.com/docs/13.x/database#handling-deadlocks
- Laravel database configuration: https://laravel.com/docs/13.x/database#configuration
- Laravel custom casts: https://laravel.com/docs/13.x/eloquent-mutators#custom-casts
- Filament native actions/modals: https://filamentphp.com/docs/5.x/actions/modals
- Filament forms validation: https://filamentphp.com/docs/5.x/forms/validation
- Filament testing: https://filamentphp.com/docs/5.x/testing/overview
- Livewire lifecycle: https://livewire.laravel.com/docs/4.x/lifecycle-hooks

Boost search-docs was used with the installed package context; native ImportAction/ImportCsv/ImportDispatcher and current app source were inspected for the transaction boundary not supplied by a generic documentation example. Source inspection is not a runtime test.

## 9. Approval record

- [x] User accepted the revised sixteen-step order.
- [x] Agreed scope, stale-edit UX, recovery A, input preservation, one-writer rules and subagent model restrictions recorded.
- [x] User approves this concrete execution specification and authorizes implementation (25 October 2026).
- [x] Original approved CP0–CP7 implementation, automated checks, journal and independent review completed, with the browser CSV-upload gap retained. Original R16 was reopened after that checkpoint; the subsequently approved CON-20 retirement removes its sole consumer in this worktree. Prior step 15 evidence was not its closure.
- [x] User separately approves merging the exact verified checkpoint into master (25 October 2026); local merge and merged-result checks completed.
- [x] User approves the exact full legacy retirement in the existing worktree (26 October 2026), superseding the unimplemented JsonRuleCast setter-fix proposal.
- [x] Approved source retirement, preserved current table coverage and automatic verification completed; R16 retired with its consumer in this worktree.
- [x] Actual native browser CSV upload, mapping, completion/rejection feedback, persisted data and failed-row download verified with human assistance (26 October 2026).
- [x] Representative fresh browser acceptance completed with human assistance: Master Values read-back, current Attribute editor and Preview rendering/selection; no exhaustive all-Product or combination claim.
- [x] User separately approves integration of the verified retirement checkpoint into master; CON-21 local merge and fresh checks completed (26 October 2026).
- [x] User authorizes committing all eight pending documentation/UX files on master; CON-22 completed with exact contents preserved.
- [x] User approves retiring the dedicated Consolidation QA setup while retaining the worktree/chat; CON-23 completed.

The recorded human instructions authorize implementation and verification already underway. The original programme and the separately approved retirement integration are complete locally. Pushing remains a separate decision. The newly requested full-app master audit is read-only and does not authorize further application changes.
