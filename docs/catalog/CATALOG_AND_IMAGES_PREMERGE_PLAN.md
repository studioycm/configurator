# Catalog and Images — consolidated premerge plan

**Updated 28 October 2026; documentation only.** This is the current planning sequence for the existing catalog redesign and Images work. It incorporates the completed final-master source audit and every finding disposition in the [consolidated audit](CATALOG_AND_IMAGES_PLAN_AUDIT.md). It does not authorize application/prototype edits, test/runtime changes, commits, merging or pushing. A future master SHA or incoming chat message does not start implementation.

## 1. Baseline and authoritative plans

| Target | Current state / protection |
| --- | --- |
| Primary | `/Users/studioycm/Herd/configurator`, master `8e1f640219ec8dbd2ee4c739041907aba4c4224d`; application repair merge `5788905a4a0ac95f26cf435cac4d82c09b576dd0`. Clean before this documentation delivery. |
| Images | `/Users/studioycm/.codex/worktrees/catalog-images-p0-p1/configurator`, detached `244b305d083a90c731cd4bde23d996b4db0f8f3d`; 92 pending paths: original 88 + three planning docs + continuation checklist. Preserve all tracked/untracked inputs and ignored runtime. Do not overlay old shared files on master. |
| Prototype | `/Users/studioycm/.codex/visualizations/2026/10/18/01a14fee-762d-7661-acdc-8d5213474de9/catalog-review-site`, `b6395c56d4ab845baf2d80a89bbb115058db7573`; pending repair plan remains separate. Static illustrative data is not app authority. |

Read these synchronized documents together:

- [Catalog journey/settings plan](CATALOG_REDESIGN_UX_AND_SETTINGS_PLAN.md), including CAT-S08-01–06 and D1–D3.
- [Images requirements/source history](IMAGE_ASSIGNMENT_PHASE_1_IMPLEMENTATION_PLAN.md), current §15.
- [Images technical blueprint](IMAGE_ASSIGNMENT_PHASE_1_EXECUTION_BLUEPRINT.md), current §21.
- [Post-consolidation Images amendments](IMAGE_ASSIGNMENT_POST_CONSOLIDATION_PLAN_AMENDMENTS.md).
- [Master audit](MASTER_APP_AUDIT_2026_10_26.md), [handoff](CONSOLIDATION_HANDOFF_FOR_CATALOG_IMAGES_REVIEW.md) and [repair evidence](POST_CONSOLIDATION_REPAIRS_EXECUTION_SPEC.md): preserved application authorities, not unfinished prerequisite work.

The full source review is complete. What remains before implementation is a concrete scoped execution proposal, preservation/isolation and approval; native completion feasibility must be established before its repair is claimed. Re-audit affected contracts when they change rather than repeat the whole review indefinitely. Later human instructions control all documents.

## 2. Ownership and ordering

Plan catalog configuration and UX owns the later execution specification and approved feature implementation/testing. Cross-worktree integration audit owns this documentation consolidation and bounded independent review. Plan bulk creation forms supplies completed baseline/regression context and later approved final integration; it is not assigned to redo completed repairs. Rules Writing, CI and security retain their separately scoped owners.

One writer per shared implementation seam. A selected execution proposal must name its exact source/target, allowlist, dependencies, native API feasibility, verification, preservation and stopping checkpoint. No wholesale reset/stash/rebase of pending work. Integration approval and eventual master merge/push authority are separate.

Recommended first package is **R0–R3: isolated P0–P6 integration, completion/recovery repairs and combined acceptance**. Catalog C0–C3 can progress independently under its own approval. P7 follows reviewed combined P6; P8 follows complete review; P9 combines image data with the presentation actually approved; P10 precedes any rollout claim.

## 3. R0–R3: existing Images before extension

| Package / source IDs | Required work | Checkpoint and discriminating evidence |
| --- | --- | --- |
| R0 — CK-01–03, G1, INT-07, A06 | Preserve full 92-path overlay, untracked sources, prototype and ignored runtime in recoverable form; pin current base; choose isolated checkout/site/DB/storage/mail/queue targets. Review exact scope and installed native completion APIs. Documentation reconciliation is delivered here. | Human approves concrete bounded implementation scope. Prove effective disposable test targets before lifecycle operations; do not migrate/boot main app implicitly. No additional master-code prerequisite identified. |
| R1 — CK-10–13, A01+A04/A02/A03/A07 | Combine truthful Images host + bounded assetSearch/custom-paging profile adapter; preserve master lock participant plan and image fingerprints; union registrar/provider/CSS/tests. Preserve native fields, current search/selection/navigation and source/storage behavior. | Both target profiles, 32-KiB bounds, restore/migration/foreign-state rejection, hidden checks, ordinal preservation; exact membership/keys; Group fresh admission; startup and canonical/Rules/historical export branches survive. |
| R2 — CK-14–16, IMG-R01/IMG-B01/IMG-B02 | Implement settled goal-check continuation and fresh inspected fingerprint consumption; defer inherited native completion until enclosing commit; fence delayed effects to saved owner/context/full raw draft. Resolve native create(true) feasibility using supported adapters. | No-Save keep/remove; own lost response; other writer meeting goal; intervening writer; revoked/deleted/reassigned owner/destination; newer draft; native notifications/redirect/create-another reset before commit/after rollback; new ordinary/image draft survives older callback. |
| R3 — CK-17, I2 | Verify the actual combined source, not independent passing snapshots. Use main safety bootstrap + Images independent-commit test partition, isolated storage and relevant actual-engine contenders. Review browser acceptance and residual limits. | Native file picker→upload→candidate→prepare→Save→reload; cancel/error/ownership, Enter/mod+s, create-another, uncertain outcomes, Results/Checked/hidden checks/custom paging and ordinary-resource parity; scoped light/dark/narrow coverage. Record exact inputs, commands/results, targets and unvisited states. Human/reviewer accepts combined P6 before P7. |

Truthful recovery is mandatory: a known write says **“Image saved”**; an authorized fresh goal match says **“Image matches your choice”** and may complete only the already requested valid continuation. It does not prove request commit/rollback or the writer. Different/unknown retains the draft and stays. Native full Save/Create uncertainty remains `review_required`; no automatic creation replay, generic durable Save framework or global exactly-once promise.

Supported create-another completion is an engineering feasibility gate. Notification hooks alone cannot delay its core form/session reset. Preserve native action parameters/embedded restrictions and do not copy vendor Save/Create internals. Assert custom and inherited effects, not only event labels.

All nine direct overlaps require semantic union: SaveCatalogGroup.php, TableBrowserProfile.php, AppServiceProvider.php, resources/js/app.js, resources/js/admin-profile.js, resources/css/filament/admin/theme.css, tests/Unit/AdminProfile.test.js and both Images governing plan files. Unchanged callers, policies, Preview, current ProductFields and test-safety paths remain in the regression scope.

## 4. Catalog C0–C3: separate approved delivery units

| Unit | Requirements / decisions | Acceptance |
| --- | --- | --- |
| C0 — design/prototype | Resolve D1 visual/navigation defaults and D2 if descendant mode selected. CB-P01 optional inherited newest-choice/chronology; CB-P02 “Low Pressure (6 bar)” caption only. | Realistic prototype pairwise visuals plus both precedence orders, compatible older choices and exact clearing feedback. Group limits/AND matching unchanged. Prototype evidence is not Laravel acceptance. |
| C1 — persistence/freshness/taxonomy | CAT-S08-01 full/narrow/reset/revision integration; CAT-S08-03 independent coherent shared display version/ETag/card refresh; CAT-S08-04 small separate exact Group taxonomy. | Branch+leaf full/narrow round trips, deletion versus absence, false/0/empty/ordered values, rollback/no-op, old settings retained; no mass Group updates; browser/cards agree; bounded fresh authorized tag reads, rename/removal/order/assignment semantics. |
| C2 — Groups/navigation/transfer | Approved sidebar/cards/facets over current availability/ancestry; immediate-child vocabulary by default. D2 descendant cards/tags/counts share one eligible set. D3 actual representable mapping inventory and selected field/value/preset mappings. | Exact scope, OR within/AND across categories; hidden/physical branches, no invented modelCode; bounded history/links; transfer on new entry only. Unsupported multivalue/multi-preset choices stay visible context, never arbitrary first value. |
| C3a — Product header and return | CAT-S08-06 bounded branch/criteria/chronology/anchor context through actual and compatibility routes; compact required code/name/image/facts. | Group→Products→Product→return, Back/Forward/BFCache/scroll/focus/deep links and stale responses; independent child availability; Parts/Others and current loader/engine ownership retained. |
| C3b — code restoration | CAT-S08-02 separate engine adapter over current saved definition/canonical two-character identities/applicability. Current code generation is not a defect. | Valid exact code roundtrip, conditional order, malformed/foreign/stale/inactive assignment, failure retaining choices; generated result matches intended code. No positional prototype decoder or unsaved Preview reconstruction. |
| C3/P9 joint display | CAT-S08-05 = IMG-A05 in existing CatalogCards authorized result projection; managed image metadata/fallback rendering. | Complete 501+ results and repeated pointers; full-set differences before chunks; measured query cost; four placements on both card types in light/dark/wide/narrow pairwise matrix. |

Settled choices: shared admin defaults with selected Group overrides; reset removes a new override key; no ancestor inheritance/per-user layout store; inherited optional newest-choice wins; unchanged Group limits; collapsed top criteria/sidebar; one image per owner; normal resource UX except image fields/direct actions; goal-check continuation. Do not re-ask these.

Only product reviews still open are **D1** recommended Precision/Focus Path/immediate-child defaults and finite presentation options, **D2** optional descendant card/facet/count pairing, and **D3** actual supported Group-tag→Product mappings. None blocks unrelated Images backend work. Engineering choices such as native adapters, display-version transport and bounded taxonomy reads remain within these contracts unless a concrete conflict is found.

## 5. Later Images phases

| Phase | Required implementation | Acceptance before next checkpoint |
| --- | --- | --- |
| P7 — CK-20 | Pure filename matching; explicit source/field/recipe; frozen exact owners/order and bounded full review. Exact stem case-insensitive initial mode; Contains suggestions never auto-win. | Every frozen position classified including deleted/ineligible; missing/ambiguous/conflict/unchanged/excluded denominators; changed recipe/source fences; no Apply while incomplete. Separate review checks/pagination. |
| P8 — CK-21 | Freeze ≤50 selected reviewed items, source/recipe/member evidence and expiry. One sequential foreground request per stable server-issued item step. | Repeated completed step returns same outcome, never advances; Stop/teardown schedules no next request, return never auto-starts; pointer/revision/result atomic, preparation outside transaction; partial/unknown outcomes truthful. |
| P9 / A05 — CK-22 | Authorized result-scoped image projection, managed display component and approved card/header integration; keep bounded forIds for small admin reads. | 0/1/200/501+ distinct and >500 repeated pointers; no raw 500-input cap on catalog, per-card SQL/media/filesystem/source read or compiler/discovery image payload. Zero pointers no image query; measure one-extra-query goal. Joint catalog acceptance only for selected presentation. |
| P10 — CK-23 | Relevant isolated MySQL races, failures/recovery and measured capacity/lease/browser/operational evidence. | Safe rollback plan and any restore-dependent rehearsal; actual limits and unvisited states recorded. Source inspection alone cannot establish capacity/deadlock freedom/rollout readiness. |

Preserve phase-1 limits: JPEG/PNG/WebP; **3,145,728 bytes**, **25,000,000 pixels**; retained original + non-upscaled contain-fit 160/480/960; source ≤2000 files / **524,288,000 bytes**; selection/review ≤5000; Apply 1–50; new review acceptance within 24 hours. Private sources copied into managed image storage, originals retained, cleanup operator-controlled. No background assignment/scan/review/conversion workers, real D60 access/import, galleries/PDF/additional entities or shared library/global replacement. Phase 2 remains separate.

## 6. Preserve completed master work and qualify evidence

Sixteen consolidation dispositions are complete/retained/retired; the audit maps every node and R/O/V alias. R16’s cast and sole legacy ConfigProfile consumer were retired, not setter-fixed; do not restore retired code. Preserve exact comparisons/actors, selected lock graph, stale Rule rejection, strict full and narrow raw writers, scoped memo rollback, native adapters and idempotent startup.

- **SA-01:** preserve fresh nested-child Product/ancestor/Configurator availability admission.
- **RL-02:** Standard CSV default/output; explicit legacy-preserve/legacy-convert enclosure-only/custom modes; active rules and whole-file validation before dispatch; exact keys/codes/backslashes and every current entry point.
- **RL-01:** sealed immutable export plans/captures, checked storage writes, after-commit and same Export-row publication locking; independent format readiness; readable historical null versions and managed fail-closed behavior.
- **AD-01:** preserve literal `0`, `001`, whitespace, null/empty distinctions and actual rendered fallback, without widening accepted compiler inputs.

Recorded 26 October merged-master evidence: PHP 1466 tests / 9241 assertions, MySQL 64/481, JS 253, safety 36/74, formatting/FilaCheck and scoped current-app browser checks. These are historical baseline results, not tests run for this documentation delivery or combined Images acceptance. HTTP/file XLSX proof passed; actual browser saving remained `ERR_BLOCKED_BY_CLIENT`. Do not call that save verified or make it an unrelated Images prerequisite.

## 7. Premerge review and stopping conditions

Before any eventual master merge of selected feature code, return a concrete review packet with:

1. Exact source/target SHAs and reviewed allowlist/diff; complete recoverable overlay/runtime preservation and excluded work unchanged. Refresh affected authority comparisons if master or another writer changed them.
2. Finding→implementation→test/evidence traceability using this audit’s IDs, with no duplicate aliases or falsely closed gates. Name the selected stage: combined P6, catalog unit, or later phase; unfinished later phases may remain separate if not exposed/claimed.
3. Actual guarded disposable test targets; affected tests and relevant actual-engine commit/rollback/contender checks; required formatting/FilaCheck/build for changed implementation files. No manual transaction escape or unsafe target override.
4. Actual changed-page/browser evidence, including earlier native picker gap when touching upload, and exact remaining permission/sign-in/client limits. Request the human’s help when that interaction is actually required; do not substitute endpoint evidence.
5. Scope-specific query/payload/capacity measurements and schema/storage/operational changes; distinguish proposed tests, inspected source and observed runtime evidence.
6. Independent bounded review, resolved regressions and explicit human merge approval. A passed test count or this documentation request is not merge or push authority. Pushing remains separate and prohibited now.

Unproven native completion support, obsolete-draft overwrite, dishonest recovery, lost master contracts, unsafe test targets or failed selected-stage acceptance stop that package. D1–D3 stop only their affected catalog units. Review the exact limitation before expanding scope.

CI master/PR provisioning/triggers/private fixtures/format-drift enforcement, deferred SEC-01–06/Horizon/packages, operational restore, R18 performance measurement, optional structural moves, Writing backend, optional queued export worker and client-specific XLSX save retain owners/triggers in audit §§9–12. They are not silently closed or blanket prerequisites for local R0–R3. An actual touched security/shared authority requires targeted review, not automatic unauthorized remediation.

## 8. Documentation delivery verification

This revision consolidates the complete original inventory and owner source audit, promotes later P6 plan history into primary, corrects active baseline/readiness requirements, and adds CAT-S08-01–06 plus R0–R3 and later gates. Pending Images/prototype sources remain the protected originals. Local links/anchors, finding coverage, exact HEADs and protected-source hashes were checked; the audit’s documentation-verification section records the results. Application tests were not run for these documentation-only edits. The primary checkout is expected to be dirty only in the explicitly requested documentation paths after this delivery.
