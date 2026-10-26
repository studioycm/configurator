# Consolidation handoff for catalog and Images review

Prepared by the original consolidation task, 26 October 2026. **Read-only review authority; no feature implementation, merge, push or deployment is granted by this document.**

The human requested this handoff be authored here, after asking **Plan catalog configuration and UX** what it needs. That consultation is complete. The owner requested the contract/dependency map below, rather than duplicated logs or an unqualified request to rebase.

## 1. Exact sources and authority

| Source | Verified state and use |
| --- | --- |
| Consolidated application | `/Users/studioycm/Herd/configurator`, local master `05ed6a93d52eec229270ff336170c16c68f42d9b`, clean before these audit documents. Application/test inputs equal reviewed retirement `222cf85`; later commits concern records and existing planning documents. |
| Pending Images implementation | `/Users/studioycm/.codex/worktrees/catalog-images-p0-p1/configurator`, detached `244b305d083a90c731cd4bde23d996b4db0f8f3d`, **22 modified + 66 untracked files**. All 88 file hashes were rechecked unchanged. This overlay remains independent and unmerged. |
| Authoritative later Images amendments | The **worktree's** implementation plan §14.14 and execution blueprint §§19–20 contain later P6 contracts/evidence. Master committed older planning snapshots; do not replace the later amendments wholesale. |
| Catalog browsing prototype | `/Users/studioycm/.codex/visualizations/2026/10/18/01a14fee-762d-7661-acdc-8d5213474de9/catalog-review-site`, clean `main` at `b6395c56d4ab845baf2d80a89bbb115058db7573`. Static design/reference code, not established Laravel integration. |
| Retained consolidation checkout | `/Users/studioycm/.codex/worktrees/app-consolidation/configurator`, clean at `222cf85`. Kept with this chat; its dedicated website/database/login have been retired. |

Installed versions: PHP 8.4.27, Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7, Blueprint 2.4.0, Pest 5.3.0. Read the actual checkout's AGENTS and applicable path rules; `.ai/rules` was absent. Required reviewers use `gpt-6.1-sol`: high for audits, medium for research, max for monitoring; never Astra. One implementation writer after approval; parallel reviews stay read-only.

## 2. Features and remaining delivery

- **Existing live catalog:** Group navigation, leaf-Group Product filters/cards and Product Configurator are already on master. They are distinct from the proposed redesign.
- **Images P0–P6:** source inventory supports foundations, intake/scanning, managed preparation, browsing, Results/Checked navigation and native/image-only editing integration. These are uncommitted worktree artifacts, not certification against current master.
- **P7:** matching, review construction and its UI remain unfinished. Batch tables are foundations, not matching behavior.
- **P8:** bounded foreground Apply/next-item processing remain unfinished. Preserve stable server-issued step identity, frozen acceptance and the approved maximum of 50 assignments per Apply.
- **P9:** public catalog image display remains unfinished. P6 has a minimum projection/component; public Index/GroupShow/ProductShow/ProductConfigurator integration is absent.
- **P10:** broader concurrency, capacity, browser and release verification remain outstanding.
- **Browsing redesign:** still a separate prototype. Design direction, sidebar navigation, direct-child versus descendant browsing and real app adapters remain unsettled. These decisions need not block unrelated image backend work.
- **Later scope:** shared library manager, reusable attachments/PDFs/galleries and additional entities remain separate.

## 3. Dispositions of the sixteen consolidation steps

These are integrated dispositions, not sixteen newly reopened feature requirements. Detailed commands and individual evidence remain in [the execution spec](APP_CONSOLIDATION_EXECUTION_SPEC.md) and [journal](CONSOLIDATION_CHANGELOG.md).

| Step | Final disposition | Contract relevant to incoming work |
| --- | --- | --- |
| 1. Safe local tests | Repaired effective-target guard | `tests/TestDatabaseSafety.php`, bootstraps and `CreatesApplication` guard URL/cache/driver overrides before migration traits. Preserve Images' explicit independent-commit cases without bypassing that guard. |
| 2. Exact editor comparisons | Repaired shared comparison projections | `ConfiguratorEditorChanges` and editor JS preserve literal text, map keys, missing/clear and list order. Identity/boolean transport normalization is deliberate. Do not use the Configurator projection as a whole Product/Group image fingerprint. |
| 3. Capture actors | Repaired explicit actor propagation | Capture/verify/inspect/prepare/signatures receive `User`; signed bytes and scope remain protected. Keep backend calls independent of ambient panel state. |
| 4. Stale Rule edits | Repaired whole-save rejection/manual reconciliation | Retain the losing draft; checked fields take latest values, unchecked retain draft; a later normal Save revalidates freshness. No automatic partial persistence. |
| 5. Lock acquisition | Repaired selected participant graph | Complete owner/canonical/context/Group/Product sets and current-read revalidation; no claim that every physical deadlock is impossible. |
| 6. Native CSV transactions | Repaired chunk boundary/rollback | `ImportCatalogCsv` owns outer chunk work, whole participant plans and truthful row versus infrastructure outcomes. Preserve native lifecycle and after-commit dispatch. |
| 7. Save/export recovery | Repaired selected Preview and Rules export boundaries | Distinguish known rollback, committed-but-refresh-failed and unknown. Never replay an uncertain write automatically. Current state is not request-attribution proof. |
| 8. Rules-import recovery | Repaired spent authority/outcome handling | Actor/scope/expiry-bound approval stays consumed after uncertain outcomes; inspect before another attempt. |
| 9. Membership/order | Repaired finite `ItemListMembers` authority | Ordering does not construct drawer UI. SQL membership, literal card-property keys and the exact leaf-Group editor context remain distinct. |
| 10. Group presentation | Repaired narrow `patchPresentation` | Patch supplied presentation fields into raw saved settings; preserve unknown/absent values, unrelated filters/presets, effective defaults and revision coalescing. |
| 11. Tags | Repaired exact identity/shared field | Preserve `"0"`, `"001"`, case/spacing and removed-choice fallback; no numeric-key coercion. |
| 12. Tables/adapters | Repaired explicit finite hosts; necessary native adapters retained | `HasWorkspaceTable`, bounded profile restore, owner/variant identity, deferred filters, native picker/reset/state paths. Do not loosen the shared contract to admit Images. |
| 13. Startup/wiring | Repaired idempotent Alpine union; native integration preserved | One registrar handles early Alpine and `alpine:init`, once per instance. Preserve all master components/exports and nonpanel Rules XLSX wiring. |
| 14. Appearance memo | Repaired same-instance nested rollback behavior | Scoped `AdminAppearance` avoids promoting uncommitted values; preserve after-commit cache publication. |
| 15. Legacy JSON | **Retired, not repaired** | Original R16 attribution was corrected. Full obsolete fixture/engine/cast removal deletes 147 files, selectively retains 7 current table cases and preserves all 22 current models. Never restore the legacy stack or implement its superseded setter proposal. |
| 16. Presentation/loading | Retained justified distinct boundaries | Eloquent Rule summaries and capture-array descriptions have different inputs. No universal presenter or mass namespace conversion was required. |

### Locks, writers and revisions: preserve both sides

Master `SaveCatalogGroup::handle()` plans the old/submitted Configurator owners, locks them in ID order **before Groups**, then rejects a freshly discovered owner outside the plan. Pending image changes add an internal fingerprint/change guard. Combine those inside the current writer; taking the older pending file wholesale would remove the master guard.

Product/full-form saves, Group settings and batches retain their authoritative writers and complete participant sets. Native Group editing can enclose fields/settings/image work in one transaction; success effects must wait for the **enclosing connection's** commit. Image preparation/file work remains outside owner transactions. `SaveCatalogMainImage` changes only a pointer under its required Groups→Product subset; do not route it through a hidden full form or add unrelated Configurator locks indiscriminately. Preserve one save/revision lifecycle and fresh-record admission.

### Membership and owner identity

`ItemLists::definition()` now delegates authorized membership to `ItemListMembers` while preserving its public query/definition contract. Existing pending `CatalogAssetTargets` therefore has a compatible public entry point. Distinguish:

- actual Product SQL membership from its image-owner ID;
- Configurator-assigned Group membership from public catalog ancestry;
- the exact leaf Group behind `group-card-properties` from property-key rows;
- global authorized Images scope from a drawer's scoped records.

Never turn a property string into an owner ID or substitute a broad descendant query for the approved membership contract.

### Drafts and outcome meaning

Preserve P6's native **full-record** draft hash, source/actor/context/property binding, upload provenance, prepared-winner fencing and after-commit newer-draft checks. An image change must not erase other Product/Group edits. Keep `editor_properties` independent of filters, exact Product keys/types, `profile_fields.product_pic` as reference data and ordered fingerprints. Complete owner attributes remain part of tested Preview fingerprints, so image changes must still invalidate older captures.

**Owner-requested review question (IMG-R01):** `CatalogMainImageEdits::reconcile()` checks current desired state and never replays. It labels a matching pointer `committed` and an unchanged original fingerprint with a different desired pointer `not_committed`. These observations establish current goal/baseline state, not durable attribution of this particular request's commit or rollback. Review both classifications and explicit retry language. `CatalogImages::reconcileImageSave()` also passes the matching-state result to `completeImageSave()`, which resets the draft, emits a committed event, notifies “Main image saved” and continues navigation. Agree whether and how goal-state convergence permits those effects, including another writer setting the same pointer. Preserve owner/target/draft/destination fencing. Do not silently introduce a durable receipt platform. Native image-enabled full-form recovery already returns `review_required` and must remain separate.

## 4. Eight direct overlaps and integration obligations

| Path | Preserve/reconcile |
| --- | --- |
| `app/Actions/SaveCatalogGroup.php` | Master's Configurator-before-Groups planning/current owner guard plus pending internal image fingerprint/change. |
| `app/Filament/Resources/TableBrowserProfile.php` | Master's `Component&HasWorkspaceTable` contract and validation plus legitimate asset-search/custom-pagination support. |
| `resources/js/app.js` | Master's single idempotent registrar/component/export union plus the two image factories. |
| `resources/js/admin-profile.js` | Column-order revision migration and failed-restore preservation plus compact `assetSearch` preferences. |
| `resources/css/filament/admin/theme.css` | Both current creation/editor/table rules and image-specific layouts; verify actual combined UI. |
| `tests/Unit/AdminProfile.test.js` | Preserve and run both expectation sets, not one side's tests. |
| `docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_IMPLEMENTATION_PLAN.md` | Preserve later worktree evidence/amendments; reconcile status against code. |
| `docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_EXECUTION_BLUEPRINT.md` | Master's older tracked snapshot and the worktree's later untracked blueprint are separate evidence; preserve the entire later file. |

Changed-to-unchanged dependencies also matter. No direct overlap count certifies compatibility.

**IMG-A01:** pending `CatalogImages` implements `HasTable` but not `HasWorkspaceTable`; its browsing mount/header/restore calls reach newly typed master profile methods. Add truthful target-specific identity, supported controls/search and restore behavior to Images. Preserve separate Product/Group preferences, ordinal pagination, single search authority and the 32-KiB profile bound; do not relax master types.

**IMG-A02–04:** Group writer, startup and profile merges above are semantic obligations even where Git can merge text. Preserve native picker parameter forwarding, hidden selections, Results/Checked navigation and width/profile/session encodings. Never persist checked IDs, pasted-code sets, drafts, source tokens or receipts as compact preferences.

**IMG-A05:** future public display must reconcile `CatalogAssetDisplay::forIds()`'s 500-ID/query ceiling with current all-results catalog behavior, including the 501-card case. The input-length check occurs **before deduplication**, so more than 500 repeated pointers can also be rejected even with fewer distinct images; the query later deduplicates IDs and limits returned rows to 500. Naïve direct calls can reject a legitimate result; naïve chunking changes the promised one-extra-query budget. Decide that contract before P9; do not add a new catalog result cap incidentally. It is not a current P6 defect.

**IMG-A06:** master planning status/README are older than the P6 worktree amendments. Resolve chronology explicitly; a committed plan is neither proof of current completion nor P7–P10 execution authority.

## 5. Separate catalog design review

Keep **CB-P01** (preset/direct-choice reconciliation plus preserved chronology) and **CB-P02** (below-six caption versus exact 6-bar mapping) open. Use one bounded action reconciliation model and an explicit serialized precedence list where the design requires newest optional choice to win. Retain compatible older choices and explain cleared choices. Never clear Group membership, availability or future mandatory suitability constraints to make results appear.

Master's current PHP/JS filter reconciliation is a behavioral reference; it does not authorize copying production snapshot/identity contracts into a simulated prototype or transplanting the prototype into Laravel. Settle chosen visual direction, navigation and hierarchy semantics explicitly. Do not make these decisions prerequisites for unrelated backend source preparation.

## 6. Fresh master audit: watchlist, not feature-task fix instructions

See [the full master audit](MASTER_APP_AUDIT_2026_10_26.md). New findings are exact-text `"0"` presentation (AD-01), child visibility revalidation (SA-01), generic canonical export storage outcomes (RL-01), and generic CSV escape/roundtrip handling (RL-02). Existing source-security work remains: SEC-02–06 and the scoped Horizon list issue. None was silently repaired by this audit.

Coordinate particularly native FileUpload path protection with legitimate P6 upload provenance, future admin authority with the existing `manage-catalog` Gate, and public image display with fresh availability checks. Do not independently fork master fixes or remove owner checks. General structural refactors are not prerequisites merely because they appeared in an old audit.

## 7. Evidence and future combined-QA ledger

Fresh merged result: PHP **1346 tests / 8814 assertions**, dedicated MySQL **62 / 460**, JS **253**, inert safety **24 / 49**, routes **30**; all passed. Actual native two-row CSV upload/import produced one saved Value, one required-name rejection and zero unconfirmed; DB/UI read-back and the actual failed-row download matched. Attribute editor and Preview render/selection passed with human assistance. Evidence is under `/private/tmp/app-consolidation-evidence/legacy-retirement-20261026/master-merge`; the browser receipt is one directory above it. New source-audit/overlay manifests and the pure CSV reproduction are under `/private/tmp/configurator-full-master-audit-20261026`.

These are **master/retirement** results. Old P6 counts are **old base plus overlay** results. There is no combined-master certification, no new full browser/performance matrix and no claim of exactly-once process-loss delivery.

The Consolidation QA domain, certificate, database copy and login are gone. Its worktree remains with an inert SQLite-memory runtime. Images' own preview/site/SQLite/build/storage were preserved. Root's reusable `configurator_catalog_test` remains, but reset suites must be coordinated; never reset the source `configurator_catalog_dev` or an Images preview database.

Before future execution, verify installed versions, current HEAD/dirty state, effective DB/URL/cache/base/storage variables and credentials privately. Existing safe recipe for ordinary tests:

~~~sh
env -u DB_URL -u DATABASE_URL -u APP_CONFIG_CACHE -u APP_BASE_PATH -u LARAVEL_STORAGE_PATH -u DB_SOCKET APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/pest --compact
~~~

The MySQL suite uses the same removed overrides, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_DATABASE=configurator_catalog_test`, `DB_USERNAME=configurator_catalog_test` and `vendor/bin/pest --configuration=phpunit.mysql.xml --compact`. Supply its restricted password through the approved private test setup, never through documentation or inherited source credentials. No command here authorizes running it against a different target.

Later combined acceptance must cover:

- Complete 88-file overlay plus current master, preserving source/ignored runtime files before any approved integration; one suitable isolated execution lane and clean participating checkouts.
- Current and Images SQLite/JS suites; guarded MySQL preparation takeover, stale owner reassignment, full-form/pointer/Preview races, rollback and enclosing after-commit behavior.
- Native full Save/Create, image-only Save, keep/set/remove, foreign upload reference, later typing, create-another, unknown response and explicit retry behavior.
- Table mount/restore/target switches, custom page sizes, hidden checks, exact memberships, profile migration and both Alpine startup orders.
- Browser keyboard/Save/Enter/native picker, light/dark/narrow layouts, Results/Checked and scan stop/resume.
- P7/P8 acceptance separately proves review version/expiry/frozen membership, ≤50 Apply, same-step retries, Stop/Continue and partial outcomes.
- P9 proves 0/1/200/>500 distinct pointers, duplicates/invalid winners, image-free warm reads and actual request/query budgets.

## 8. Requested owner response and next gate

Review the worktree's plans and feature design against this map. Return: (1) accepted/corrected contract deltas, (2) plan amendments needed, (3) engineering prerequisites versus product decisions, (4) a proposed bounded integration/delivery order and meaningful acceptance checks, and (5) evidence gaps. Keep prototype and Laravel findings separate.

Preserve useful source/file-preparation/assignment/UI/display boundaries; no generic repository, lock/retry framework, competing writer or new state platform is implied. Do not edit plans/code/prototype/tests, merge, push, run migrations or change sites under this handoff. A reviewed plan and separate human implementation approval come next.
