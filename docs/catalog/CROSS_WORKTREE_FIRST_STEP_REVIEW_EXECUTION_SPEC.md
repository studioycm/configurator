# Cross-worktree first-step review execution specification

> **Historical record — publication note, 1 November 2026.** The original status, commands, approval gates and worktree assumptions below describe their dated October baseline; they are retained evidence, not current execution instructions. Groups One and Two were delivered on Master in `07701045e4dda35213e24b078eba19675d697fc6`. Use the [current delivery and history index](../README.md#current-delivery-and-historical-records), [stage protocol](STAGE_AUDIT_AND_DOWNSTREAM_REPLAN_PROTOCOL.md) and [current Catalog/Images execution plan](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for present scope and remaining verification. Host-local paths/hashes are historical evidence references, not bundled or fresh runtime proof.

**Status:** bounded review execution contract, 29 October 2026. The completed reconciliation is input, not permission to code. Plan bulk creation forms has independently checked and accepted the complete four-artifact packet for human-decision handoff at the receipt hashes recorded in the evidence manifest. Its W1-scope clarification is incorporated. Independent review gate fulfilled at this stated scope; human execution approval remains pending.

**Outcome:** complete accessible reconciliation plus first-package technical verdicts for Catalog/Images and Writing, with immediate corrections/ownership/gates ready for the human's implementation decision.

**Plan:** [task-by-task review plan](CROSS_WORKTREE_FIRST_STEP_REVIEW_PLAN.md). [Full reconciliation](CROSS_WORKTREE_RECONCILIATION_2026_10_29.md). [Evidence manifest](CROSS_WORKTREE_RECONCILIATION_EVIDENCE_2026_10_29.json).

## 1. Baseline, authority and exact file ownership

Application root: `/Users/studioycm/Herd/configurator`; master `8e1f640219ec8dbd2ee4c739041907aba4c4224d`; repaired by local merge `5788905a4a0ac95f26cf435cac4d82c09b576dd0`. Before this package, nine pending documentation paths existed. All nine hashes are in `protected_documents_before` in the evidence manifest. They are read-only inputs to this package.

The Catalog owner subsequently revised its two files through R2 to reviewed R3, and the coordinator added `COORDINATION_REVIEW_PACKAGE.md`. Those coordinated changes are separately attributed in the manifest; they are not permission for this audit to edit them or evidence of unauthorized app drift.

**Only these new files may be authored here:**

1. `docs/catalog/CROSS_WORKTREE_RECONCILIATION_2026_10_29.md`
2. `docs/catalog/CROSS_WORKTREE_RECONCILIATION_EVIDENCE_2026_10_29.json`
3. `docs/catalog/CROSS_WORKTREE_FIRST_STEP_REVIEW_PLAN.md`
4. `docs/catalog/CROSS_WORKTREE_FIRST_STEP_REVIEW_EXECUTION_SPEC.md`

No README/other-owner edits, application/prototype changes, dependency/install actions, tests, build/formatters, application bootstrap, DB queries/migrations, browser/session/service changes, checkout creation, Git writes, cleanup, messages or new tasks are authorized by this specification. Any future code/runtime/check command below is an acceptance requirement for a separately approved feature package, not a command to run now. Existing authorization/model restrictions persist.

## 2. Fix-now decision

**No immediate master repair package is justified by this evidence.**

- Product original `fcac0ad` is patch-identical to retained `92032c0`, with all 62 changed blobs equal; the 10-path editor amendment `4e82c22` is integrated.
- Preview `244b305`, Bulk `0567df0` and later repair merge `5788905` are ancestors of current master. None of the original 62/10/44/98 changed paths is absent. This is supplemented by connected source/assertion review, not treated as ancestry-only proof.
- Retained app-consolidation `06b5835` has unique commit identity, but its actual availability implementation/test equal master. Its eight dirty fragments are equal to master or superseded by master null/empty handling and stronger tests; they do not establish lost production fixes.
- SA-01, RL-01, RL-02 and AD-01 are repaired; R16's actual cast and sole legacy ConfigProfile consumer were retired. Do not replay old proposals or restore legacy code.
- S08 is conditional invalid-stored-appearance resilience; S10 is narrow console error presentation, with its old generic native-import claim superseded by the current chunk job; R18 is an unmeasured vocabulary-read lead. None establishes an urgent upstream requirement for these feature packages.
- PF-R02 remains partly open as a stale Product plan. Correct historical authority in a separately scoped owner document update; do not reverse current `profile_fields` behavior. Catalog/Images A06 documentation has been refreshed.

The useful first step is the review package in sections 3–8. A new confirmed defect must have a source/caller/trigger and meaningful failed-boundary evidence before replacing this no-fix-now decision.

## 3. Review input/output interfaces

Input artifact tuple: `{owner, absolute_path, sha256, baseline_head, package_ids, supplied_at, approval_state}`. Input source tuple: `{absolute_path, sha256, relevant_methods_or_sections, evidence_level, inspected_date}`. These are review-record fields, not new application DTO classes.

Output finding tuple: `{id, aliases, artifact_version, location, affected_callers, trigger, consequence, confidence, recommended_correction, alternative_if_useful, owner, dependencies, acceptance, blocking_scope, disposition}`. Use existing IDs when the cause is unchanged. Prefix new bounded technical-review findings `XWR-01` onward; do not turn a missing historical log into an application defect.

Package verdicts:

- `ready_for_human_decision`: no unresolved immediate source/contract omission found, and future verification obligations are explicit. Not implementation approval or runtime acceptance.
- `changes_required`: a concrete omission/collision must be corrected before delegating that scope.
- `proof_or_decision_pending`: a named engineering feasibility or exact input revision remains unresolved; identify only the affected slice.

Record `observed`, `source_supported`, `assertion_inspected`, `historical_execution`, `proposed` and `unavailable` separately. Hashes prove bytes, not correctness or runtime behavior.

## 4. Dependency order and checkpoints

1. **A — packet freeze:** current HEAD/status and all protected hashes; complete report/ID coverage; availability manifest. If baseline changes, compare affected paths rather than restart the entire audit.
2. **B1 — Catalog first package:** read current owner artifacts at coordinator-supplied hashes; inspect M0 + I1–I4 contracts and actual installed native parents. Produce immediate finding table and test-gap requirements.
3. **B2 — Writing first package:** the coordinator has supplied exact refreshed paths/hashes, recorded in the reconciliation. Review W1-0 through W1-4 and its real current writer/loader/compiler/actor/revision dependencies; no later shared-draft/UI feature is included.
4. **B3 — shared integration:** compare both packages' exact file ownership and changed contract signatures. Return a serial handoff/semantic-union matrix; no simultaneous editing implied.
5. **C — independent check, fulfilled 29 October:** coordinator separately reviewed the audit-owned plan/spec, complete reconciliation and evidence manifest at the recorded receipt hashes. No additional immediate planning correction was found. Author self-check does not fulfill C; this receipt comes from a different task and grants no implementation/runtime/release approval.
6. **D — correction/decision handoff:** read owners' bounded corrections, retain unresolved feasibility/gaps, and return proposed implementation delegation scope for explicit human approval.

## 5. Catalog/Images first-group solutions to assess

These are concrete source-backed approaches for the first priority group only. Detailed later Catalog/Images/Writing solutions remain feature-owned.

| Cause | Recommended approach and considered alternative | Exact owner/files and dependency | Required discriminating acceptance after separate implementation approval |
| --- | --- | --- | --- |
| IMG-A01 + A04 | Adapt `CatalogImages` to current finite `HasWorkspaceTable` and extend validation/restoration only for truthful image-host capabilities. Alternative: a focused image-specific restore adapter behind that same host contract if the shared helper would otherwise admit unsupported native states. Never relax master types or add a generic capability framework. | Catalog owner; `app/Filament/Pages/CatalogImages.php`, `app/Filament/Resources/HasWorkspaceTable.php` (retain contract), `TableBrowserProfile.php`, image browsing concern, `resources/js/admin-profile.js`, profile tests. Deliver together in I2. | Product/Group identities distinct; valid/foreign profile, failed restore, 32 KiB bound, image search/custom size 1–500, ordinal preservation; checks/drafts/tickets absent from preferences; native picker/drawer parity. |
| IMG-A02 | Base on current full Group/Product writers; add only image guard/assignment under existing fresh ownership and lock graph. No old-writer wholesale replacement. | Catalog owner; `app/Actions/SaveCatalogGroup.php`, `SaveCatalogProduct.php`, `SaveCatalogMainImage.php`, `CatalogAssetAssignments`, `ProductEditorData`, revisions. I3 follows retained I1 storage and I2 hosts. | Current Configurator owners planned before Groups; reject late reassignment; Product's two fingerprints retained; full fields/settings/image rollback together; pointer-only write preserves other fields; actual-engine competing writers and no-op revision. |
| IMG-A03 + A07 | Add image factories to current single registrar and scoped image pagination hook to current provider. Preserve current canonical/Rules/historical exporter branches and scoped services. A second provider/registrar is unnecessary for this change. | Catalog owner; `resources/js/app.js`, `app/Providers/AppServiceProvider.php`, profile JS/theme/tests and opt-in pagination view. I2–I4. | Early/late/repeated Alpine startup; no duplicate handlers; all existing factories; Rules XLSX + managed canonical + historical/native routes; image hook absent on ordinary tables. |
| IMG-R01 | Use truthful goal/baseline/conflict/unknown outcomes. Carry the inspected owner fingerprint through fresh actor/context/full-draft/destination checks before completion. Keep native uncertain full-record Save/Create `review_required`. A durable global Save ledger is not required or proposed. | Catalog owner; `CatalogMainImageEdits`, `CatalogImages`, image JS/trait/messages/tests; I3. | No-Save keep/remove, own lost response, another writer meeting goal, intervening write after inspection, revoked/deleted/reassigned destination, newer draft; inspect performs zero writes; no blind replay or false attribution. |
| IMG-B01 + B02 | First prove supported native completion adaptation: preserve parent parameters, defer inherited/custom effects until real enclosing commit, capture saved owner/context/creation session/full raw draft and suppress obsolete resets/refills. `afterCommit` plus notification hooks alone cannot defer `create(true)`'s direct reset. A second possible route is explicit refusal of ambient transactions before any write, but that changes the accepted contract and is not selected or authorized. | Catalog owner; native `EditProduct`, `EditGroup`, `CreateGroup`, narrow `SplitCreateRecord`/field adapters and installed parents. I3a feasibility checkpoint before claiming repair. | Both native hosts Save→new ordinary/image draft→outer commit; no custom/inherited success before commit or after rollback; standalone create redirect; create-another model/session/form reset on commit versus rollback; embedded restriction; original native signatures/parameters. If supported seams cannot meet the contract without vendor-body copies, return a concrete alternative for human decision. |

Research basis: installed Filament 5.9 `EditRecord::save` / `CreateRecord::create`, Laravel 13 connection transaction callbacks, current finite host/profile code, current writer/registrar/provider implementation, pending receipt/completion consumers, and inspected assertion bodies. Versioned official documentation used in the completed source review: [create lifecycle](https://github.com/filamentphp/filament/blob/5.x/docs/03-resources/03-creating-records.md), [edit lifecycle](https://github.com/filamentphp/filament/blob/5.x/docs/03-resources/04-editing-records.md), [database transactions](https://github.com/laravel/docs/blob/13.x/database.md). Reconfirm disputed installed APIs at the package's actual baseline; these approaches are not runtime-proven repairs.

## 6. Writing and shared-path review checklist

Review the owner's first-slice design; do not prescribe a second Writing implementation here. **W1 handles exactly one Rule using a trusted in-process opening baseline.** Shared drafts, batch orchestration, selected-section source refresh, UI/routes/JS and Preview changes are excluded. Their later requirements below are compatibility checks only, not W1 implementation/approval prerequisites.

- The one proposed Rule merges into the fresh complete aggregate; omitted unselected Rules, Attributes/Options/defaults/settings/subtitle modes survive. Current `SaveConfiguratorDefinition` deletes omitted aggregate members, so partial payloads are unsafe.
- W1 authorizes explicitly, compares its trusted opening Rule against the current owned target, preserves fresh Enabled/priority and rejects stale/deleted/foreign data. Empty/invalid source is an in-memory parser/codec value; durable source retention, shared draft CAS and two-admin draft autosave belong to later slices.
- Canonical identities, local inclusion IDs, semantic IDs, Option codes and exact quoted Product keys remain distinct. Missing/blank/null, `0`, `001`, whitespace and effect order survive.
- Unsupported native syntax declines without approximate conversion or persistence. W1 retains raw accepted source in its operation value and labels DB-derived source generated; it makes no durable reopening promise. Checked-section refresh is later work. Enabled remains outside Writing ownership.
- W1 adds no route, UI, draft collector or Preview acknowledgment/event; existing Preview callers remain unchanged. Later mounted-Preview staleness and shared draft exclusion remain compatibility contracts, not new W1 event work.
- Compare actual changes to `SaveConfiguratorDefinition`, `ConfiguratorDefinitionLoader`, compiler/draft services, Rules relation manager, `ConfiguratorEditorChanges`, revisions/context/canonical usage, provider/registrar and tests. A shared dependency is not automatically a textual collision; identify changed assumptions/signatures.

## 7. Verification design and isolated-target contract

**Now:** source/assertion/doc inspection and local metadata/link/hash checks only. Do not run the following application checks under review authority.

**Later approved feature execution:** use a clean master-based lane with independently verified disposable effective DB/storage/session/mail/queue targets. Current `TestDatabaseSafety` must reject URL/cache/base-path/late connection overrides before destructive lifecycle; preserve image-specific independent-commit test partitions. No manual escape from framework transactions, main-app schema changes, credentials in reports, actual D60 files or arbitrary target overrides.

| Changed boundary | Meaningful existing/future coverage owner |
| --- | --- |
| Image host/search/navigation/profile | Existing image browsing/search/sequence/navigation and `AdminProfile` assertions, actual native mount/restore and hidden-check/custom pagination behavior. |
| Recovery/native completion | Extend existing `CatalogMainImageFieldTest`, `CatalogImagesEditingTest` and receipt tests. Existing rollback assertions cover custom events; workspace newer-draft test does not prove both native full-record callbacks or inherited effects. Add the exact B01/B02 cases in section 5. |
| Locks/atomic owner writes | Existing guarded MySQL contender harness, selected Preview/canonical/status/creation regressions plus actual full/pointer/new image competitors with independent connections and observed barriers. SQLite is not lock proof. |
| CSV/export/availability/exact text | Preserve `ProductConfiguratorAvailabilityTest`, `CatalogExportStorageFailureTest`, `CatalogExportTransactionTest`, MySQL publication tests and actor/exact-label/editor-change regressions when touching their dependencies. Test actual entrypoint bytes/read-back, not only row arrays. |
| Writing | W1 parser/codec round trips, atomic one-target/unselected aggregate preservation, allocated IDs, raw no-op rows/order/join/timestamps, explicit actor/trusted opening baseline, unsupported decline and unchanged Preview caller regressions. No shared-draft revision or browser UI tests are required by W1. Prototype counts cannot substitute. |
| Browser | Real native file chooser→ticket→candidate→prepare→Save→reload; cancellation/error/foreign origin, keyboard/create-another/newer-draft; affected current catalog/Preview/resource parity. Mark permission/sign-in/download blocks explicitly. XLSX HTTP/file proof remains distinct from browser saving blocked by client. |

Run the narrowest affected approved tests, then required Pint/FilaCheck/build for actual changed files. Record exact tree/overlay, command, effective target, result and served assets. Repeat/broaden only on changed dependencies, failure or unresolved evidence. Full suite/actual-engine release checks belong to the approved implementation scope and project instructions; none ran for this packet.

## 8. Acceptance, stopping conditions and protected work

Acceptance of this review package requires: all four original stream dispositions and leftovers accessible; every original finding family accounted for with aliases; first-priority remedies and native feasibility limits explicit; exact owner artifacts pinned; both feature verdicts and shared-ownership matrix delivered; unavailable historical evidence disclosed; independent review of this audit package recorded; human implementation decision still pending.

Both exact owner packages have been reviewed; Catalog's bounded correction is closed and the coordinator's independent audit-package check is fulfilled at its recorded scope. Human implementation approval remains a separate gate. A genuine new source omission would be `changes_required`; absence of runtime execution here is an explicit future gate, not a fabricated test failure. Historical missing report URLs may remain unavailable with honest provenance; current report accessibility does not require recreating them.

Rollback for this documentation package is a scoped correction of its four owned files. Do not reset/revert unrelated dirt or delete retained worktrees. Compare all nine protected-document hashes and the preservation receipt's source hashes/HEADs after writing. If concurrent owner edits are detected, attribute the drift explicitly and ask the coordinator for the correct review version; do not overwrite it.

Security SEC-01–06/Horizon/packages, CI INT-05, R18 optimization, optional R/O/V restructuring, Product restore rehearsal, queued export workers and client-download remediation remain separately owned/deferred. They become a dependency only when a concrete selected package changes that boundary or a separately approved operating requirement needs the proof.

## 9. Handoff and approval gate

Return a short status plus absolute paths, final SHA-256 values and unresolved gate/limitation list. The coordinator's independent receipt is recorded in the reconciliation/manifest; it will verify this receipt-only update for the human handoff. No outbound task message is implied by receiving this request.

The next human approval should name the feature owner, exact artifact versions, bounded implementation package, clean master-based lane and permitted verification/runtime operations. Merge/push/security authorization remains separate. No application fix is authorized or claimed by acceptance of this technical review.
