# Catalog and Images — consolidated premerge audit report

**Reviewed 28 October 2026. Documentation only; implementation approval is pending.**

This report consolidates the complete cross-worktree findings, the completed owner source audit against current master, and the 28 October continuation checklist. The active plans have been synchronized in the primary checkout. Original IDs and aliases remain traceable; a repeated alias is not another defect. This documentation refresh does not reproduce runtime failures or certify combined acceptance.

Read the [combined premerge plan](CATALOG_AND_IMAGES_PREMERGE_PLAN.md) for the delivery sequence, the [catalog plan](CATALOG_REDESIGN_UX_AND_SETTINGS_PLAN.md) for the journey/settings contract, and the [Images amendments](IMAGE_ASSIGNMENT_POST_CONSOLIDATION_PLAN_AMENDMENTS.md) plus [source plan](IMAGE_ASSIGNMENT_PHASE_1_IMPLEMENTATION_PLAN.md) / [blueprint](IMAGE_ASSIGNMENT_PHASE_1_EXECUTION_BLUEPRINT.md) for phase-specific requirements.

### Current disposition

| Area | Fixed / retained | Still missing or requiring proof |
| --- | --- | --- |
| Consolidated master | Sixteen dispositions; SA-01, RL-02, RL-01 and AD-01 repaired; R16 legacy consumer/cast retired | Preserve these authorities during integration; historical checks are not combined Images proof |
| Images P0–P6 | Source exists in the preserved older-base worktree; private sources, fenced preparation and failure-journal repair retained | A01/A04 coupled host/profile, A02/A03/A07 semantic union; IMG-R01 and IMG-B01/B02 repairs; combined P6 acceptance |
| Images later phases | Settled limits and foreground workflow retained | P7 matching/review, P8 Apply, A05/P9 display and P10 broader acceptance unfinished |
| Catalog redesign | Existing authenticated journey and actual engine retained; shared defaults/selected overrides and inherited newest-choice policy settled | CAT-S08-01–06 engineering requirements, prototype repairs and D1–D3 product reviews; implementation not delivered |
| Documentation | This consolidated audit and revised plans now available; obsolete wait-for-master/source-audit statements corrected | Exact implementation scope, isolated lane and native adapter feasibility still need the feature owner’s execution proposal and human approval |
| Release limits | Actual HTTP/file CSV/XLSX and current-app browser evidence recorded separately | XLSX browser save blocked by client; automated master/PR CI, deferred security, capacity/rollout proof remain scoped open items |

First proposed implementation package: R0–R3, isolated P0–P6 integration and repairs. Catalog design can proceed independently under its own approval; P7 starts only after reviewed combined P6 acceptance.

## 1. Evidence, precedence and fresh baseline

| Source | Identity and use |
| --- | --- |
| [Full reconciled report](/private/tmp/configurator-full-audit-findings-20261028.md) | Cross-worktree integration audit, turn 01a1826e-eb6c-7751-9ed6-612214b0e4ad; 384 lines; SHA-256 e99518c3010fc7970425ffafbbae8764c9969977515bd2e46cff76c047ee1540. ALL sections 1–9 reviewed. |
| [Full owner source audit](/private/tmp/configurator-full-step8-owner-audit-20261026.md) | Plan catalog configuration and UX, turn 01a178a7-dd64-72a1-9b8b-3e89f3a85614; 135 lines; SHA-256 dae6d65fd7a8426e1b7e755e3467f4c3f0db5f1c295dd20f832630179261c74f. Read in full. |
| Main M | /Users/studioycm/Herd/configurator; read-only baseline: master 8e1f640219ec8dbd2ee4c739041907aba4c4224d, clean before these authorized documentation edits. Application repair merge 5788905a4a0ac95f26cf435cac4d82c09b576dd0. |
| Images I | /Users/studioycm/.codex/worktrees/catalog-images-p0-p1/configurator; detached 244b305d083a90c731cd4bde23d996b4db0f8f3d; 92 pending paths: original 88 plus three planning documents and the 28 October continuation checklist; none edited by this consolidation. |
| Prototype X | /Users/studioycm/.codex/visualizations/2026/10/18/01a14fee-762d-7661-acdc-8d5213474de9/catalog-review-site; b6395c56d4ab845baf2d80a89bbb115058db7573; one pending prototype-repair plan. |
| Current governing plans | Revised M/docs/catalog/CATALOG_AND_IMAGES_PREMERGE_PLAN.md; CATALOG_REDESIGN_UX_AND_SETTINGS_PLAN.md; IMAGE_ASSIGNMENT_POST_CONSOLIDATION_PLAN_AMENDMENTS.md; Images source plan §15 and blueprint §21. These incorporate I’s later §14.14 / §§19–20 without altering its source files. X’s prototype-repair plan remains separately scoped. |
| Earlier planning audit | I/docs/catalog/CATALOG_AND_IMAGES_PLAN_AUDIT.md remains its unchanged 26 October snapshot. This primary report supersedes its readiness statements; the full owner source audit is a distinct source. |
| Continuation reconciliation | I/docs/catalog/CATALOG_IMAGES_CONTINUATION_CHECKLIST_2026_10_28.md; 438 lines; SHA-256 47dfc3ce0336c2e9049f9d44de61fc1fd21e7d5c8f3024aa6ce18bd9150a1b75. Inventory and dispositions incorporated here; no dependency on temporary files to understand required work. |
| Master reconciliation | M/docs/catalog/CONSOLIDATION_HANDOFF_FOR_CATALOG_IMAGES_REVIEW.md; POST_CONSOLIDATION_REPAIRS_EXECUTION_SPEC.md §10; CONSOLIDATION_CHANGELOG.md CON-31–33; MASTER_APP_AUDIT_2026_10_26.md. These later dispositions supersede historical pending/unrun statements in the original architecture register. |

M/I/X prefixes in this report mean these exact roots. Nine-path overlap refers to the audited application overlay; this documentation delivery additionally consolidates the catalog plan, amendments, audit, premerge plan and entry point. Paths identify the affected implementation, not permission to edit it. Temporary report paths are local evidence pointers, not durable release artifacts; the semantic disposition is recorded here with report hashes.

**Precedence:** explicit later human decisions → accepted execution contracts and the synchronized plans/report → final source-audit dispositions → historical findings/planning snapshots. This report corrects stale readiness interpretation: the final baseline and full source audit are available. It does not rewrite old checkpoint evidence or authorize execution. Detailed blueprint changes must be adopted in the reviewed execution scope before their implementation.

**Evidence limits:** today’s new verification is HEAD/status, report reading, plan reconciliation and documentation-preservation checks. Source findings and test descriptions below are qualified evidence from the two full reports, not freshly reproduced runtime failures. Recorded master tests (1466/9241 PHP, 64/481 MySQL, 253 JS, safety 36/74) are 26 October evidence; they are neither today’s passes nor combined Images acceptance. The human authorized these documentation edits only. No app boot, DB/test/migration command, build/formatter, dependency action, browser/service mutation, commit/merge/push or D60 access was performed for this refresh.

## 2. Gate and ownership key

- **B0 — before ANY feature implementation resumes:** exact baseline/preservation, governing-contract reconciliation, selected scope and explicit human execution approval. Already completed report review is not repeated as an indefinite audit.
- **I1 — approved Images integration/repair:** combine existing P0–P6 and resolve coupled contracts; a source-combination gate, not P7 functionality.
- **I2 — before accepting combined P6 / starting P7:** meaningful combined automated and actual-browser evidence, bounded to affected paths.
- **C0/C1/C2/C3 — catalog gates:** approved design/prototype work; settings/tag foundations; navigation/transfer; Product/configuration/return. These can progress independently of P7/P8 where no shared dependency exists.
- **L7/L8/L9/L10 — later Images phases:** P7 review; P8 Apply; A05/P9 display; P10 operational/release acceptance. Put a check at its earliest actual dependency, not automatically before all development.
- **D — deferred/optional/operational:** explicitly recorded owner and trigger; not a blanket local feature blocker.

Owner **F** = Plan catalog configuration and UX and its later approved implementation lane. Cross-worktree integration audit owns this documentation consolidation and bounded independent review. **A** = application maintainer (completed master work formerly Plan bulk creation forms). **Q** = approved isolated verification owner/reviewer. **CI** = project/CI maintainer. **S** = security/dependency maintainer. **W** = Rules Writing owner, Research Rules import and export. **H** = human product/scope approver. These are ownership assignments for planning, not messages or automatic work dispatch.

Unchecked items are obligations, not claims that a repaired master feature is broken. “Settled” below means preserve the accepted behavior; implementation and verification are separately pending.

## 3. B0 — necessary before implementation resumes

### [ ] CK-01 — preserve the COMPLETE deliverable and choose the isolated execution lane
- **IDs:** G1 / IMG-01; INT-07 / IMG-04 / PF-R02; G2 / IMG-02–03.
- **Feature/files/action:** Both features; inventory all 92 I pending paths, including untracked services, controllers, migrations, routes, config, JS, views and tests, plus X’s pending repair plan and ignored preview runtime. Preserve recoverable bytes/hashes BEFORE any approved combination. A tracked-only patch is incomplete. Keep main app/data and existing planning work intact.
- **Status/evidence:** Pinned HEADs match the full reports; the Images inventory includes the subsequently added checklist. Primary changes in this delivery are the requested documents only. All 92 pending-path hashes plus the prototype inventory were captured before primary documentation edits. No integration or ignored-runtime snapshot has been performed; a later executable preservation receipt must cover ignored runtime before a combination.
- **Owner/dependencies/gate:** F; first B0 item; Q verifies the approved lane/manifest. No upstream master-code repair identified.
- **Acceptance/decision:** Later approved execution demonstrates an exact recoverable inventory, correct isolated site/DB/storage targets and unchanged excluded source/runtime. Choose concrete integration commands/lane in the execution proposal; human implementation approval remains pending. No rebase/reset/stash/commit/push is implied.

### [ ] CK-02 — documentation synchronized; approve the concrete execution contract
- **IDs:** INT-07 / IMG-04 / PF-R02; IMG-A06; all source IDs mapped in §§8–12 below.
- **Feature/files/action:** Both; the primary source plan/blueprint and post-consolidation amendments now incorporate this report and final baseline. The later implementation owner must define the exact executable scope/lane. Explicitly include A07, R01, B01/B02, all nine shared paths, CAT-S08-01–06 and combined evidence gates. Replace obsolete “step 6 still active / waiting for final SHA / full source audit not yet done” readiness interpretation. Preserve dated P0–P6 history and distinguish original planning audit from full source audit.
- **Status/evidence:** This report and the primary plans supply complete disposition/ordering and corrected active requirements. Pending worktree documents remain unchanged. Documentation reconciliation is complete; code repairs, native adapter feasibility, execution approval and runtime acceptance are not complete.
- **Owner/dependencies/gate:** F; CK-01; B0 before code. H reviews the exact execution scope referencing this report and synchronized blueprint.
- **Acceptance/decision:** Each selected finding maps to one owner, implementation boundary and discriminating check; aliases count once; historical numbers remain qualified. Remaining product decisions are only those in §7; no settled quiz/recovery decision is re-asked.

### [ ] CK-03 — approve bounded scope, responsibility boundaries and verification isolation
- **IDs:** G1–G3; R03/R06/R07/R08/R10/R11/R12/R13/R14; O01–O09; V02/V05.
- **Feature/files/action:** Both; propose R0–R3 for existing Images first, and separately approved catalog units. Retain concrete focused Services/Actions, native factories/adapters, current field/compiler/writer authority and one revision lifecycle. No repository/module/interface/DTO/provider split or class move merely to satisfy a pattern.
- **Status/evidence:** Reports support retaining these boundaries; no feature implementation approval exists. Master Tests/CreatesApplication and TestDatabaseSafety protections are already repaired.
- **Owner/dependencies/gate:** F + H; CK-01/02; B0. Q privately validates disposable effective targets before any later test lifecycle; keep image independent-commit test allowlist alongside master bootstrap/worker refusal guards.
- **Acceptance/decision:** Approved scope states included/excluded files, native extension-point feasibility, test-target safety, separate commit/push/runtime authority, rollback preservation and next checkpoint. Package/API recheck is targeted at affected native hooks; no dependency change here. CI/security/optional workers are not prerequisites for approving local R0–R3.

## 4. I1/I2 — integrate and accept existing P0–P6 before P7

### [ ] CK-10 — implement the truthful Images host/profile adapter as ONE coupled change
- **IDs:** IMG-A01 + IMG-A04; G2; R06/R07/R08; N8; P-01/P-02 overlaps.
- **Feature/files/action:** Images; I/app/Filament/Pages/CatalogImages.php:32; M/app/Filament/Resources/HasWorkspaceTable.php and TableBrowserProfile.php:12–45; I/app/Filament/Resources/InteractsWithCatalogAssetBrowsing; M/resources/js/admin-profile.js and both AdminProfile test sets. Implement truthful Product/Group host identities/capabilities; admit only host-supported assetSearch/custom paging without weakening master’s Component&HasWorkspaceTable requirement.
- **Status/evidence:** Open definite combined-source incompatibility: pending host lacks interface; master rejects assetSearch and only accepts native-option perPage. Fixing the interface alone is insufficient.
- **Owner/dependencies/gate:** F; CK-01–03; I1, accepted at I2. Preserve unchanged typed WorkspaceTablePicker, ItemListDrawer and scoped-search/reset adapters.
- **Acceptance/decision:** Mount/restore/target switch; distinct target profiles; migration and failed-restore preservation; 32-KiB bound; invalid/foreign options; exact source keys; no persisted checked IDs, pasted lists, drafts, source tokens or receipts. Compact hybrid search remains approved; do not add competing tableFilters authority.

### [ ] CK-11 — preserve canonical search, explicit selection and semantic navigation
- **IDs:** G2/G3; IA-02/IA-03; R06/R09/R10; N5/N8; P-01.
- **Feature/files/action:** Images; CatalogAssetRecordSearch/Sequence, Targets, browsing trait/Page and pagination/header views. Preserve one eligible query/order across table and Results navigation; Checked navigation retains mandatory membership while ignoring optional search. Inspection and pagination never change checks or silently save.
- **Status/evidence:** Implemented separately with substantive tests; combined behavior unverified. Do not rebuild working search/navigation merely because formerly named native filter classes were deliberately amended.
- **Owner/dependencies/gate:** F + Q; CK-10; I1 preservation/I2 acceptance.
- **Acceptance/decision:** Code-only All/Contains/ignore-case/separators default; number matches any code segment; literal wildcard and exact keys; hidden checks/asymmetric owner membership; select-all rejects >5000 rather than truncating; presets 5/10/15/25/50/100/200 and custom raw integer 1–500, default 10; ordinal/clamping; cross-page neighbors/deleted destination/stale reveal. Keep page-size control inline with pagination and accessible label/tooltip, without removed helper sentence. Search/page/mode changes retain image draft. Settled workflow, no new product decision.

### [ ] CK-12 — combine full/pointer writers, membership, fingerprints and locking without regression
- **IDs:** IMG-A02; INT-02 / BULK-F02 / PRV-INT-01 / R02; R09/R12; N1/N5/N9; P-01.
- **Feature/files/action:** Images; M/app/Actions/SaveCatalogGroup.php:25–32 plus pending image guard; SaveCatalogProduct; SaveGroupSettings; SaveCatalogMainImage; CatalogAssetAssignments/Targets; ItemListMembers/ItemLists; ProductEditorData/ProductFields/ProductFieldQuery; CatalogRevisions. Retain planned old/submitted Configurator locks before Groups and fresh owner admission in native Group saves; retain image-only Groups→Product required subset. Preserve strict ordinary fields, whole-owner and effective editor fingerprints, atomic settings/image Save and coalesced revisions.
- **Status/evidence:** Selected master graph repaired and actual-engine tested; pending Group writer predates that guard. Not a new master defect or proof of universal deadlock freedom.
- **Owner/dependencies/gate:** F; CK-03 + exact current callers; I1/I2. No generic membership registry, synthetic metadata Save, reverse Product→Group acquisition or indiscriminate extra locks.
- **Acceptance/decision:** Reassignment outside planned owner set, competing full/pointer/Preview saves, stale Product classification, exact dotted/zero keys, one leaf Group behind card-properties, actual SQL membership vs property strings, unchanged-pointer no revision, fields/settings/image rollback together. Preserve complete Product/Group Preview fingerprints and explicit initiating actor. Settled contracts.

### [ ] CK-13 — union Alpine, provider, profiles and CSS; preserve ALL nine shared paths
- **IDs:** IMG-A03/IMG-A07; INT-06 / BULK-F05 / R17; N15; G2; Rules R-05; P-02.
- **Feature/files/action:** Both integrations; add two image factories to M/resources/js/app.js’s single idempotent registrar. Preserve globals/all current factories. Merge image CONTENT_AFTER pagination hook into current AppServiceProvider; preserve scoped CatalogCsv/CatalogRevisions/AdminAppearance, canonical dispatcher/version/destination guards, Rules XLSX and historical/native branches. Union CSS and profile-test expectations, not whole old files.
- **Status/evidence:** Open semantic union; master startup/export work repaired. Existing image pagination view is opt-in scoped. Direct overlap map is nine paths (see §8).
- **Owner/dependencies/gate:** F; CK-10/12 and current provider/native dependencies; I1/I2.
- **Acceptance/decision:** Early Alpine, late init, repeat init, complete registration set, no duplicate handlers; all exporter routing branches; image hook absent on ordinary tables; profile migration + asset options; fresh light/dark/narrow/keyboard UI. Preserve additive exports.catalog_artifact_version migration in any approved isolated schema; historical rows stay null. No new provider split, CSV-policy reuse for Images or queued assignment infrastructure.

### [ ] CK-14 — implement settled goal-state recovery across ALL consumers
- **IDs:** IMG-R01; P-04 superseded; N11/R10 protected recovery boundaries.
- **Feature/files/action:** Images; CatalogMainImageEdits.php:115–134, CatalogImages.php:519–556, catalog-image-editor.js, events/messages/tests. Replace observational committed/not_committed attribution with truthful goal/baseline/conflict/unknown meaning. Known write: “Image saved.” Fresh goal match: “Image matches your choice,” then finish only the already-requested valid destination.
- **Status/evidence:** Decision SETTLED; implementation OPEN. Page currently ignores inspected fingerprint and routes observed goal to committed-success effects.
- **Owner/dependencies/gate:** F; CK-12 and coupled UI consumers; I1 repair/I2 acceptance.
- **Acceptance/decision:** No-Save keep; remove already-empty without Save; own lost-response write; another writer meeting goal; change between inspection/completion; revoked/deleted/reassigned owner/destination; newer draft. Inspect with zero writes; consume fresh fingerprint, actor/context/draft/destination checks; truthful events/notifications/reset/navigation; retain draft on conflict/unknown. Native full-record uncertain Save/Create remains review_required. No blind replay, automatic creation retry or durable generic Save platform.

### [ ] CK-15 — repair inherited native completion and deferred full-draft fencing together
- **IDs:** IMG-B01; IMG-B02 = U01; P-05 strengthened; N2/N11/R08/R10.
- **Feature/files/action:** Images native fields; EditProduct.php:79–95, EditGroup.php:52–68, CreateGroup.php:18–30, SplitCreateRecord, shared field trait/browser consumers; installed Filament EditRecord::save/CreateRecord::create. Defer notification/redirect/create-another reset until enclosing commit; capture saved owner/context/full raw draft identity and suppress obsolete refill/clean effects.
- **Status/evidence:** Open source-established pending-P6 contract gaps, independently corroborated. No everyday browser reproduction claimed. Current unsaved-change alerts are absent: do NOT invent a current savedDataHash-mutation finding.
- **Owner/dependencies/gate:** F; CK-12/14; I1. FIRST settle supported native adapter feasibility, especially create(true): notification hooks alone cannot defer its core reset. Preserve native parameters and create-another/embedded restrictions; no vendor Save/Create copy or silent contract weakening.
- **Acceptance/decision:** Product/Group Save notifications absent before outer commit/after rollback; standalone Create redirect; create(true) draft/session/reset on commit versus rollback; both native hosts Save→new ordinary/image draft or context→outer commit retain newer draft. Assert custom AND inherited effects and no duplicate successful effects for the same verified receipt/current host within the tested lifecycle. Do not claim durable/global exactly-once creation or recovery across process loss. Existing rollback-event/workspace-newer-draft tests do not cover all these cases. Engineering feasibility gate, not a new product choice.

### [ ] CK-16 — retain source, managed storage and upload-origin correctness
- **IDs:** G3 / IMG-06 upload seam; P-03; R08; IA-05/IA-06; LA-01/LA-02; AR-02/AR-03/AR-07/AR-08.
- **Feature/files/action:** Images; Sources/SourceFiles/Files/Storage, source preview and individual upload controllers, routes/disks, Media collections/path generator, MainImageField and trait native overrides. Keep private import inbox and generated physical identities, copied managed originals/public image disk, bounded scans, actor/context tickets, immutable manifests, attempt fences/winner, independent preparation outside owner transactions and retained failed attempts.
- **Status/evidence:** Implemented separately; source failure-journal fix PRESENT for commitUpload/scanNext/finalize. Preserve, do not reopen historical “unfixed” gaps or substitute generic retries/repositories/queues.
- **Owner/dependencies/gate:** F + Q; CK-03/12/13; I1 preservation/I2 acceptance. SEC-03/package changes are separate conditional compatibility triggers, not blanket prerequisites.
- **Acceptance/decision:** Upload/scan/finalization failure-journal DB outage retains committed cursor/counters and reports pause without false durable failure; actual multipart cross-actor/context reference substitution; growing/one-byte-over source; MIME/decode/pixel boundaries; takeover late success/failure/corruption; rollback leaves ready preparation; original fallback on derivative failure; all owner pointers unchanged on preparation failure. JPEG/PNG/WebP, 3 MiB/25 MP, original+non-upscaled 160/480/960 stay settled. No real D60 access/import for these generated-fixture checks.

### [ ] CK-17 — establish a genuinely combined P6 acceptance checkpoint
- **IDs:** G3 / IMG-05–06; G2; V02/V03/V05; BULK-F06 scoped cases; PRV-INT-03; N16.
- **Feature/files/action:** Both preservation + Images acceptance; combine current safety bootstrap with I/tests/Pest.php’s explicit independent-commit file partition. Run only in an approved disposable effective target with isolated storage/mail/queue effects, serial shared-resource races and no manual escape from framework transactions.
- **Status/evidence:** OPEN/UNVERIFIED. Separate master and Images passing counts, test-body inspection, synthetic image preview and a P6 checklist do not prove combined acceptance.
- **Owner/dependencies/gate:** Q + F; CK-10–16; I2, before P7. CI changes are not necessary to execute guarded local checks; no automatic root/main DB migration.
- **Acceptance/decision:** Relevant image/native/search/navigation/profile/picker/startup/export/Group-settings/Preview/availability/exact-text regressions; actual-engine preparation/full/pointer/Preview contention and enclosing commit/rollback; code formatter/FilaCheck/build only under later code authority. Real browser file-picker→upload→candidate→prepare→native Save→reload; cancel/error/ownership, Enter/mod+s, create-another, unknown outcomes, pending transfer, ordinary-resource parity, hidden checks/custom paging, light/dark/narrow. Record unvisited states: endpoint tests do NOT close the earlier failed-before-selection picker check. Acceptance review required; no P7 coding merely because tests are proposed.

## 5. Later Images phases — do NOT block unrelated earlier work

### [ ] CK-20 — finish deterministic matching and complete bounded review
- **IDs:** P7; AR-01; P-06; G3 phase boundary.
- **Feature/files/action:** Images; proposed CatalogAssetMatcher, ReviewCatalogAssets and AssetAssignment/factories/views. Freeze explicit owner IDs/order/source/recipe/version; index filenames once, preserve product_pic exact key/leading zeroes; classify every frozen position, including deleted/ineligible, in bounded idempotent build requests. Source 2000 files/500 MiB; review ≤5000.
- **Status/evidence:** Unfinished feature; batch schema is not behavior. Search is not filename matching; exact stems ignore case initially, collisions ambiguous, Contains suggestions need explicit choice.
- **Owner/dependencies/gate:** F; CK-17; L7 before Apply becomes available.
- **Acceptance/decision:** Case/extension/subfolder ambiguity, exact path-stem extraction, exclusions/wildcards, all-file/no-truncation denominators, missing owner, lost build response and fenced old recipe; Apply disabled until all classified. Separate review pagination/preferences/checks. Settled matching limits/default fill-missing; D60 example can use synthetic names, not actual source access.

### [ ] CK-21 — implement foreground acceptance/Apply, not a queue
- **IDs:** P8; EX-01; AR-04/AR-07; N1/N11 preserved.
- **Feature/files/action:** Images; ApplyCatalogAssets/ProcessNextCatalogAssetBatchItem/AssignCatalogAsset and UI loop. Preissue ≤50 exact reviewed items with frozen acceptance/version and stable server item-step IDs; claim then prepare outside owner locks; atomic pointer/revision/result under fresh scope/fingerprint and claim checks.
- **Status/evidence:** Unfinished feature. No background assignment/scanner/review/conversion worker is approved; foreground journals are not failed_jobs.
- **Owner/dependencies/gate:** F; CK-20 + accepted P6 writer/preparation; L8 before batch Apply acceptance.
- **Acceptance/decision:** Same-step terminal replay returns same row, never next; old intent/step stays read-only after new acceptance; busy/expired claim blocks later rows until explicit recovery; Stop prevents new request but in-flight may commit; close/return does not auto-start; session/systemic/unknown errors pause; exact partial/unchanged/skipped accounting. New review expires after 24h; accepted work uses fresh checks and explicit Continue. No queue:retry/Horizon workaround or generic outbox.

### [ ] CK-22 — resolve result-scoped image projection BEFORE P9 wiring
- **IDs:** IMG-A05 = CAT-S08-05; AR-06; CAT-07/CAT-08.
- **Feature/files/action:** Both display seams; I/app/Services/CatalogAssetDisplay.php:20–50 and minimum main-image component; M/app/Services/CatalogCards.php:23–63 and authorized Group/Product headers. Keep bounded admin forIds; add trusted result-scoped projection inside coherent revision/Product read, one explicit ready winner join, no raw >500 rejection/truncation, per-card query/model hydration or filesystem/source probing.
- **Status/evidence:** Open L9 prerequisite; current raw input bound precedes deduplication and query limit is 500. Existing live catalog supports all results. Editor previews are not catalog integration.
- **Owner/dependencies/gate:** F; accepted P6 + relevant approved presentation; L9. P7/P8 do not wait for this fix. Images P9 can serve existing approved catalog surfaces without requiring the entire redesign; revised layouts additionally depend on their C gates.
- **Acceptance/decision:** 0/1/200/501+ distinct and >500 repeated pointers, invalid/unready/foreign winner and exact owner-result exclusion; no provenance leakage; image-free zero extra query/image-bearing proposed one extra metadata query, same-read races and actual warm budget. All-match property differences before HTML chunking. Contain/reserved dimensions/white-photo-safe dark tray/missing-broken fallback; no family/filename inference or compiler/discovery image payload. Query budget changes require evidence and explicit contract review, not hidden result caps.

### [ ] CK-23 — finish operational evidence and rollback boundaries before release
- **IDs:** P10; PF-L01 when applicable; BULK-F06/PRV-INT-03 scoped cases; V03; G3.
- **Feature/files/action:** Both delivered features; isolated actual-engine/fault/browser/capacity gates. Measure worst allowed decode/conversion/FPM memory, request duration, lease margins/renewal and scan/matching/paging/display SQL/payload costs. No CLI-only production capacity claim.
- **Status/evidence:** Unverified. Historical physical preparation races cover selected scenarios only; full combined writer schedules and workload gates remain open.
- **Owner/dependencies/gate:** Q + F; CK-17 plus delivered phases; L10 before rollout claims. Conditional restore rehearsal in CK-43, not a blanket new migration prerequisite.
- **Acceptance/decision:** Deterministic relevant contention/rollback/late-handler schedules; real UI flow/theme/narrow/accessibility/error states; measured caps and named unverified surfaces. Rollback disables new UI/display while retaining schema, pointers, originals and sources; no destructive down/purge. Real D60 inventory/dry run/import, deployment, main migrations, commit/push each need their own authorized scope. No fresh workload/advisory/production-account inference.

## 6. Catalog delivery — independently ordered gates

### [ ] CK-30 — review design defaults and implement only approved prototype repairs
- **IDs:** CB-P01/CB-P02; CAT-04/CAT-08; CAT-PLAN versus full owner report distinction.
- **Feature/files/action:** Catalog; I/catalog UX/settings plan and X/prototype repair plan/dist app/model/review-state. Review Precision/Focus Path/immediate-child defaults and existing finite settings matrix. Apply newest-choice wins to optional inherited presets with explicit chronology; caption exact six bar as “Low Pressure (6 bar).”
- **Status/evidence:** Product precedence policy/caption correction settled; repairs unimplemented. Visual/navigation defaults remain proposals. Prototype styles/fixtures/decoder are review references, not real adapters.
- **Owner/dependencies/gate:** F + H; CK-02/03; C0 before selected prototype/design execution. Does not block Images R0–R3/P7 backend.
- **Acceptance/decision:** Both action orders, compatible retention, older-choice removal messages, URL/history chronology, deterministic old-link fallback; unchanged Group limits/AND behavior and exact-six set. Pairwise style/theme/placement review plus long/deep/empty states. Product choice D1 in §7 only; no automatic prototype publish/share.

### [ ] CK-31 — define and prove new override keys through every writer
- **IDs:** CAT-03/CAT-04; CAT-S08-01; INT-04 / BULK-F03 / PF-R01 / R01; N7.
- **Feature/files/action:** Catalog; M/app/Filament/Resources/Groups/Schemas/GroupForm.php:48–61,191–205; SaveGroupSettings::handle/patchPresentation; CatalogPolicy; proposed presentation settings/resolver/action. Coordinate hydration/dehydration, full save, raw narrow patch, key deletion/reset and new-key revision semantics. Own Group-card controls work on branches; Product-card overrides belong to leaf listings.
- **Status/evidence:** Planned extension, not reopened master defect. Current full save normalizes known legacy keys; narrow raw patch preserves omitted keys but cannot delete by array_replace alone.
- **Owner/dependencies/gate:** F; approved C0/finite key contract + CK-12 preserved writers; C1 before persistence code/acceptance.
- **Acceptance/decision:** Branch+leaf full/narrow round trips; unknown/new override keys; false/zero/empty/ordered lists; no-op and rollback; explicit reset removes key, not today’s global value; no ancestor inheritance/materialization; existing filters/presets/editor_properties/appearance semantics unchanged. Shared admin defaults+selected overrides SETTLED. UI serializer stays UI-owned; service must not hydrate form to decide data survival.

### [ ] CK-32 — give shared presentation an explicit coherent freshness contract
- **IDs:** CAT-07; CAT-S08-03; R15/N12; S08 distinct candidate.
- **Feature/files/action:** Catalog; proposed CatalogPresentationSettings/SaveCatalogPresentationSettings/CatalogPresentation; current DatasetController ETag, Snapshots, BuildCatalogSnapshot, CatalogCards and catalog-filters.js. Keep display version distinct from Group-only schema/revision; resolve effective values coherently at initial open/explicit refresh and server-card boundary; publish cache only after enclosing commit.
- **Status/evidence:** Unimplemented adapter; existing 304/snapshot/client refresh follows Group revision. Global edit must not mass-increment every Group or silently move active choices mid-gesture.
- **Owner/dependencies/gate:** F; CK-31; C1 before shared-setting delivery. Retain scoped memo rollback pattern, not AdminAppearance as new catalog storage.
- **Acceptance/decision:** Shared edit with no Group mass update; same-instance warm/cold nested rollback; old card generation versus refreshed presentation; choices/chronology preserved; browser/server versions agree; bounded cold/warm reads. No new cache hierarchy or global clears by assumption. This is an engineering contract, not another product questionnaire.

### [ ] CK-33 — implement the separate Group taxonomy and scoped navigation projection
- **IDs:** CAT-01/CAT-02/CAT-05/CAT-07; CAT-S08-04; R05/R09/R12/R18; N5/N6.
- **Feature/files/action:** Catalog; proposed finite category/value/Group associations and save operation; CatalogNavigation over current availability/ancestry/leaf authorities. Around ten categories, multiple values; exact IDs/order/labels/rename/remove/assignment rules. Do not reuse flat Master Value tags or add hidden configurator Attributes.
- **Status/evidence:** Planned; current Group revisions do not automatically invalidate all cached ancestor descendant facets. Prefer bounded fresh reads initially, or explicitly prove ancestor dependency invalidation.
- **Owner/dependencies/gate:** F; CK-31/32 + approved relevant scope; C1/C2. H’s D2 only gates optional descendant pairing; immediate-child path can remain independently scoped.
- **Acceptance/decision:** Exact 0/001/case/Unicode identities; removed/renamed values; parent hiding/reassignment/cycles; only-hidden-child branch stays a physical branch; immediate-child vocabulary before choices; OR within category/AND across. Descendant mode pairs visible leaf Group cards/tags/counts; opening a leaf still returns its exact Products. No arbitrary client scope or property-key/owner-ID conflation.

### [ ] CK-34 — deliver sidebar/cards and bounded navigation state without widening access
- **IDs:** CAT-01/CAT-02/CAT-08/CAT-09; CAT-S08-06 (shared return envelope).
- **Feature/files/action:** Catalog; existing Index/GroupShow/routes + proposed sidebar/card/read adapters. Collapsed top criteria plus sidebar; Free Tree flat expanded rows, Focus Path or sidebar tag cards; distinguish plus-expand from name-select and inspection from filtering. Keep logical Group hierarchy unrestricted by fixed nested scrollboxes, not a new business depth limit.
- **Status/evidence:** Unfinished redesign. Current authenticated root/branch/leaf routing and active ancestry are authorities; no anonymous access authorization.
- **Owner/dependencies/gate:** F; CK-30/33; C2. D1/D2 apply only to their chosen defaults/mode.
- **Acceptance/decision:** Keyboard/focus, deep/long labels, empty/hidden branches, responsive one-page scroll; correct cards/facets/count set; bounded validated URL/history/scroll state, teardown/reinit. New-branch retention applies only to a new branch; Back/Forward and explicit links restore validated prior choices. Required identity cannot be hidden; no invented Group modelCode or eight-row fixture membership.

### [ ] CK-35 — add only explicit representable Group-to-Product transfer
- **IDs:** CAT-06/CAT-09; CB-P01; CAT-S08-06.
- **Feature/files/action:** Catalog; current Group filters/presets, ProductFields and snapshot/local matcher/reconciler. Use explicit current field/value or owned-preset mappings; current Product state has one value per direct field and one preset. Unsupported multi-value/multi-preset input is visible context, never arbitrary first-value selection.
- **Status/evidence:** Unimplemented adapter. Inherited optional presets participate in newest-choice wins; mandatory Group limits remain unchanged. Current singleton/multivalue preset semantics must survive.
- **Owner/dependencies/gate:** F + H; CK-33/34 and D3 supported mappings; C2 before transfer delivery, not before Images backend.
- **Acceptance/decision:** Represented single value/atomic preset, unsupported set, foreign/stale mapping, unavailable value; both precedence orders; singleton hiding/compatible multivalue retention; upstream Group context unchanged. Transfer only on new catalog entry; validated deep links/history take precedence. D3 pending; no broader Product filter-engine expansion approved.

### [ ] CK-36 — split real configuration-code restoration from Product presentation
- **IDs:** CAT-05; CAT-S08-02; SA-01/AD-01 preserved; Rules R-03/R-06 related identities only.
- **Feature/files/action:** Catalog; ProductShow/ProductConfigurator, ConfiguratorDefinitionLoader/compiler/engine/intent adapter and generated-code view. Current page initializes and displays code; add bounded restore/share using current saved definition, canonical two-character identities, applicable attributes and actual engine validation, rather than positional prototype decoding.
- **Status/evidence:** New feature, not broken existing generation. Dashboard loader intentionally excludes public context/rules; unsaved Preview reconstruction is excluded.
- **Owner/dependencies/gate:** F; approved C3 contract + CK-34/35 where journey overlaps; C3 before code-restoration delivery. Product header can ship independently under its own approved scope.
- **Acceptance/decision:** Exact valid round trip; conditional applicability/order; invalid/foreign/stale/inactive assignment; failed restore retains current choices; generated result validated against intended code; Product/saved-definition binding and dashboard-context exclusion. No new compiler/field registry or unsaved-editor reproduction. No new human decision unless a concrete contract conflict is demonstrated.

### [ ] CK-37 — deliver Product header/return state and joint visual acceptance
- **IDs:** CAT-S08-06; CAT-04/CAT-08/CAT-09; CAT-S08-05 = IMG-A05.
- **Feature/files/action:** Catalog; product-card links, ProductShow, configurator header, named/compatibility routes, local discovery/history and display component. Compact image+Product identity/facts; preserve Parts/Others/editor authority. Carry bounded originating Group/branch/discovery/precedence/anchor and preserve unrelated valid route parameters through compatibility redirects.
- **Status/evidence:** Unfinished adapters; existing cards link bare Product routes and redirects can drop query context. Fresh outer AND retained child availability must remain.
- **Owner/dependencies/gate:** F + Q; CK-34/35 for journey, CK-22 for actual images, CK-36 only for code-load control; C3/L9.
- **Acceptance/decision:** Group→Products→Product→return, direct links, Back/Forward/BFCache, scroll/focus and stale responses; exact Product membership/code/title 0/001/whitespace; four image placements/card types/light-dark wide-narrow pairwise plus broken/missing/deep/long cases; contain/no upscaling, native whole-card link/no nested controls. Preserve local matching/counts, complete-set differences before chunks and independent server-card delivery. No canceled numeric per-option Product counts, compare/purchase feature or user-specific catalog layout store.


### Catalog planning findings retained individually

These are plan/adapter obligations, not nine new failures in current master.

| ID | Current disposition and gate |
| --- | --- |
| CAT-01 | Presentation must not widen access or alter matching. Preserve current availability, ancestry and membership; adversarial navigation/filter proof at C1/C2. |
| CAT-02 | Immediate-child semantics retained; optional descendant cards/facets/counts must share the same visible leaf set. D2 before that mode. |
| CAT-03 | New-key shared inheritance only; full/narrow/reset round trips and false/zero/empty proof at C1. Existing saved appearance remains authoritative. |
| CAT-04 | Separate own Group-card overrides from Product-card overrides within a leaf; shared navigation remains stable. C1/C3. |
| CAT-05 | Prototype modelCode/decoder/sample membership are not production authorities. Use actual routes, fields, members and saved-definition engine. C2/C3. |
| CAT-06 | Explicit representable Group-to-Product mappings only. Unsupported multi-value/multi-preset criteria remain visible context; D3/C2. |
| CAT-07 | Independent coherent display freshness, after-commit publication and bounded measured reads required; no mass Group updates. C1/L9. |
| CAT-08 | Pairwise four-placement/card-type/theme/viewport matrix plus critical permission/history/empty/broken cases. C0/C3/L9. |
| CAT-09 | New-branch retention and new-entry transfer must preserve validated links/history/chronology. C2/C3. |
| CB-P01 | Prototype inherited optional preset/newest-choice chronology repair planned; current Group limits and AND matching unchanged. C0. |
| CB-P02 | Prototype caption becomes “Low Pressure (6 bar)”; exact 6 remains included, no threshold/data change. C0. |

## 7. Settled decisions versus actual remaining decisions

**Preserve without re-asking:** dedicated Images workspace; normal Product/Group UX except image fields/actions; one CatalogAsset + main_image_id per owner; private inbox → copied managed public image storage; preserved originals/operator cleanup; accepted image/size/conversion/source/review/Apply limits; code-fragment search rules; pagination and Results/Checked; fill missing; foreground execution; 24-hour review expiry; admin shared defaults + selected Group overrides; optional inherited newest-choice wins; unchanged Group limits; check-image-and-continue on matching goal, native full Save/Create unknown review_required; light/dark; collapsed top criteria and sidebar.

| Decision | Owner and earliest gate | Recommended choice / effect |
| --- | --- | --- |
| **D1** | H + F, C0/C2 defaults | Review A.R.I Precision, Focus Path and immediate children; review finite admin setting choices/placements already in the catalog plan. Alternatives remain settings, not three production themes. |
| **D2** | H + F, before optional descendant mode | Pair descendant leaf Group cards, available tags and counts over exactly the same visible set; default immediate-child rule unchanged. |
| **D3** | H + F, before transfer delivery | Approve actual explicitly representable Group-tag→field/preset mappings. Keep unsupported combinations informational; broader engine behavior is separate. |
| **Execution approval** | H, B0 then each selected later checkpoint | Approve R0–R3 integration/repair first if desired. Catalog/prototype code, P7/P8/P9/P10, runtime mutations, commits/push and D60 actions do not follow automatically. |

Native Create another feasibility, display-version transport and taxonomy read strategy are engineering decisions inside accepted contracts. They are not extra product questions unless investigation proves a conflict requiring a changed contract.

## 8. Exact integration footprint and concrete upstream dependencies

The nine directly shared paths in the audited application overlay are:
1. app/Actions/SaveCatalogGroup.php
2. app/Filament/Resources/TableBrowserProfile.php
3. app/Providers/AppServiceProvider.php
4. resources/js/app.js
5. resources/js/admin-profile.js
6. resources/css/filament/admin/theme.css
7. tests/Unit/AdminProfile.test.js
8. docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_IMPLEMENTATION_PLAN.md
9. docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_EXECUTION_BLUEPRINT.md

Changed-to-unchanged callers also matter: ItemListMembers/ItemLists, WorkspaceTablePicker/ItemListDrawer/native search adapters, Product fields/editor fingerprints, native Filament parents, Preview signed state and revisions, availability/card snapshots, safety bootstrap, canonical/Rules export bindings. Nine Git overlaps are not the entire audit scope.

**Root/master-owned dependency now:** no additional master-code repair prerequisite identified in either full report, and fresh HEAD/status shows no baseline drift. SA-01/RL-01/RL-02/AD-01 and sixteen consolidation dispositions are preserved implemented/retained contracts. A is a regression owner, not assigned to redo them.

**Conditional upstream work, NOT current prerequisites:** CI policy (CK-40), approved package/security changes (CK-41), operational restore evidence (CK-43), and concurrent shared-contract changes by W/A. If another lane changes a touched authority, refresh the affected comparison/acceptance before combining; do not silently reset this checklist or claim compatibility from the old SHA.

## 9. Relevant nonblocking, deferred and optional work

| Item / IDs | Feature/path; necessary action and reason | Status/evidence | Owner; prerequisite; earliest gate | Acceptance and decision boundary |
| --- | --- | --- | --- | --- |
| **CK-40 — CI**; INT-05 / BULK-F04 / V01/V03/V04/S12 | Both release evidence; M/.github/workflows/tests.yml/lint.yml/deployment-check.yml. Define actual master/PR triggers, approved private Composer/fixture access, isolated MySQL provisioning and formatting-failure behavior. Deployment checks are not PHP test execution. | Open/deferred. Fresh read confirms tests/lint main/develop triggers and fixing Pint; no workflow executed. Manual actual-engine coverage exists, not an automated job. | CI + A; separate CI/credentials/fixture approval; D, before claiming automated master/PR CI coverage or a release gate that requires it. NOT before local R0–R3/P7. | Real workflow on intended branches, refused unsafe DB targets, protected secret access, actual MySQL and submitted-format-drift failure. Local passing tests do not close CI; changing pipeline/credentials requires approval. |
| **CK-41 — packages/security**; O08/R08 + SEC-01–06/Horizon/S-OLD | Native upload/auth/Gate/served assets may later intersect both features. Retain required MediaLibrary dependency; revalidate package ownership/advisories/installed parents when a separately approved change is resumed, then run affected upload/native/export regressions. | Deferred; no fresh advisory scan/upgrade/IDE/deployment inspection. Historical version floors are not current recommendations. | S + A, with F for touched feature seams; explicit separate approval; D/conditional future integration. | Full security-ID dispositions in §12; truthful package+published served-asset evidence and legitimate upload compatibility. NO dependency removal/upgrade, authority migration, worker/security work or filename-only exemption here. |
| **CK-42 — measured performance**; R18/CAT-07/AR-01/AR-06 | New taxonomy/vocabulary and Images source/review/display: measure queries, decoded rows, aggregation, response/DOM/payload and interaction separately. Reuse within request only when beneficial; no speculative index/cache/storage rewrite. | R18 open measurement lead, not confirmed slowness. New phase-specific costs unmeasured. | F + Q; actual phase implementation/workload; C1/L7/L9/L10 as affected. Baseline measurement does not block unrelated integration. | Representative cold/warm scoped data, exact semantics and time/memory/query evidence. Cache/index/schema changes justified individually, with approval where required. |
| **CK-43 — operational restore**; PF-L01 | Product/profile schema or rollout operation that depends on recovery: rehearse restore/fault boundaries using disposable MySQL, exact schema/value hashes and retained provenance. An SQL transaction does not make DDL atomic or recreate old buckets. | Operational limitation retained, no new restore rehearsal. No destructive operation is planned in this checklist. | A/Q + H approval; identified recovery-dependent operation; before THAT operation, not all additive image work or design. | Restore actually recovers schema/data/provenance at selected fault points. No automatic down migration, source-data reset, copy of real credentials or unsupported rollback claim. |
| **CK-44 — Writing coordination**; G4, Rules R-01–R-08/RW IDs | Preserve complete aggregate loader/writer, exact ProductFields/identities, stale protection and Rules XLSX in shared paths. Writing backend/codec/shared drafts remain W’s separate feature; refresh only actual shared-contract changes. | Runtime semantic IDs/Data Exchange/Rules exchange landed; Writing app not implemented. Prototype repair/evidence is not backend acceptance. | W; own approved plan plus shared contract handoff if changed; D, conditional overlap review. NOT a dependency on Writing delivery before Catalog/Images. | Mixed selected/unselected aggregate parity, current subtitle/default/inclusion identities and exact keys, owned revision/atomic Save, unsupported syntax retention belong to W. Feature regression union covers retained shared authorities. No auto-dispatch to W. |
| **CK-45 — optional quality/robustness**; R03/R11/R14/O01–O07/O09/V05/S08/S10 | Retain focused boundaries and current namespaces/factories. Assess corrupt appearance read fallback or non-HTTP error mapping only on demonstrated affected paths. Avoid universal presenters/editor/claim frameworks or test relocation as “cleanup.” | Healthy/optional; S08 resilience and S10 error-boundary candidates remain unresolved, not repaired by memo fix and not proven new failures here. | A/F as actual owner; separately justified scope; D or narrowly affected implementation. | Observable behavior/isolation/discovery/import/hash compatibility and scoped failure tests; do not impose structural assertions that mirror implementation or delete tests. No blanket refactor prerequisite. |
| **CK-46 — unverified operating/UI cases**; BULK-F06/PRV-INT-03/V03 | Rerun changed native max/loading/disabled/IME/device cases and relevant browser/physical contention scenarios, not an unlimited unrelated matrix. | Selected previous evidence exists; exhaustive hardware/zoom/viewport/heap/listener/capacity coverage absent. | Q + relevant F/A owner; affected change; I2/C3/L10 as justified. | Name each actual device/workload/state checked and remaining gaps. No blanket pass or automatic new dependency/tooling. |
| **CK-47 — optional export worker/client/tooling** | Optional queued single-stream export needs an effective worker/capacity gate; XLSX browser save needs usable client download path; preventive manifests/skill accountability and PHPStorm inspection remain separately scoped. | Worker unavailable/unimplemented; browser XLSX blocked by client although real HTTP/file proof exists; no new IDE pass/tool installation. | A/Q/CI as appropriate; separate explicit need/approval; D. | Operational worker measurements/client-save observation/actual tool output before those claims. None blocks this review, local feature integration or foreground Images by itself. |

## 10. Every original INT finding, gate and lane alias reconciled

| Source ID / aliases | Disposition here; action group |
| --- | --- |
| INT-01 = BULK-F01 / V02 / CA08 / S07 / SEC-00 | Master repaired; preserve effective-target guard in CK-03/17. No duplicate harness. |
| INT-02 = BULK-F02 / PRV-INT-01 / R02 | Selected graph repaired; new Images merge/contenders CK-12/17, not replay of old defect. |
| INT-03 = ordinary-form portion of Rules R-02 | Master stale Rule Save repaired; preserve via CK-44. Writing draft work separate. |
| INT-04 = BULK-F03 / PF-R01 / R01 | Narrow presentation writer repaired; new override integration CK-31. |
| INT-05 = BULK-F04 / V01 / V03 / V04 / S12 | Open/deferred CI; CK-40, not hidden local implementation blocker. |
| INT-06 = BULK-F05 / R17 | Registrar repaired; union CK-13. |
| INT-07 = IMG-04 / PF-R02 | Partly reconciled; full checklist supplied here; execution-contract synchronization CK-02. |
| G1 / IMG-01 | Open complete-overlay/preservation/approval gate CK-01/03. |
| G2 / IMG-02–03 | Open semantic union + combined proof CK-10–17. |
| G3 / IMG-05–06 | Open scope/browser/acceptance CK-16/17, later CK-20–23. |
| G4 | Writing backend unfinished, not Catalog/Images prerequisite; CK-44. |
| BULK-F06 | Scoped unverified UI/capacity CK-46; use affected cases only. |
| PRV-INT-02 | Explicit-principal concern resolved; retain initiating-actor checks CK-12/16/44. |
| PRV-INT-03 | Earlier baseline-evidence frontier substantially superseded; current combined feature proof still CK-17/46. |
| PF-L01 | Recovery limitation CK-43, only before an operation relying on it. |
| P-01 | Alias/history: R09/member adaptation → CK-11/12. |
| P-02 | Registrar/provider union → CK-13. |
| P-03 | Native upload seam retained → CK-16/17. |
| P-04 | Older interpretation superseded by settled IMG-R01 → CK-14. |
| P-05 | Deferred-completion frontier strengthened to IMG-B01/B02 → CK-15, count once. |
| P-06 | Unfinished later feature scope → CK-20–23, not another defect. |

## 11. All sixteen repair dispositions and all 32 R/O/V recommendations

These mappings deliberately retain repaired or healthy contracts. They do not create 48 open refactors.

### Sixteen dispositions

| Step / node | Report disposition and feature-relevant treatment |
| --- | --- |
| 1 / N16 | Safe test targeting implemented; CK-03/17 preserves before destructive lifecycle, no test run today. |
| 2 / N2 | Exact comparison implemented; CK-12/14/15/37 preserves leading zeroes, literal keys, missing/clear, list order and newer typing; signed raw bytes remain distinct. |
| 3 / N4 | Explicit actors implemented; CK-12/16/44 preserves capture/verify/prepare/signature inputs with no ambient-auth assumption. |
| 4 / N3 | Stale Rule editor implemented; CK-44 preserves whole rejection/manual draft refresh, not partial persistence. |
| 5 / N1 | Selected participant graph implemented; CK-12/21 plus actual-engine acceptance for new writers. |
| 6 / N9 | Native chunk rollback/row versus infrastructure handling implemented; CK-13/17/21 preserves distinction and bounded known-rollback retries. |
| 7 / N11 | Save/Rules export recovery implemented; CK-14/15/44 preserves unknown versus rollback/committed-refresh meaning and no blind replay. |
| 8 / N10 / ARC-01 | Rules import spent approval implemented; CK-44 preserves consumed authority after uncertainty and fresh preview before another attempt. |
| 9 / N5 | Finite membership implemented; CK-11/12/33 distinguishes SQL members/owner IDs/property keys. |
| 10 / N7 | Narrow raw presentation patch implemented; CK-31 extends without undoing it. |
| 11 / N6 | Exact shared tags implemented; CK-33 preserves existing Master Value tags separately from NEW Group taxonomy. No rewrite/blanket trimming. |
| 12 / N8 | Explicit hosts/native adapters implemented/retained; CK-10/11/16 preserves lifecycle/state paths. |
| 13 / N15 | Startup/wiring implemented; CK-13 preserves current union/scoped services/export branches. |
| 14 / N12 | Same-instance appearance memo rollback repaired; CK-32 retains after-commit publication pattern, CK-45 treats S08 as distinct. |
| 15 / N13 / R16 | Legacy JSON consumer/cast RETIRED, not setter-repaired. CK-03/12/44 must not restore 147 retired files or obsolete consumer; preserve current models/seven retained current tests. |
| 16 / N14 | Distinct presentation/loading DELIBERATELY RETAINED; CK-03/45. No universal presenter or extra refactor gate. |

### R01–R18 (architecture register namespace; R01 is NOT IMG-R01)

| ID | Current disposition | Applicable selection / exclusion and proof |
| --- | --- | --- |
| R01 | Implemented N7 | CK-31; exact unrelated rows/settings, no-op/revision tests for new keys. |
| R02 | Selected graph implemented N1/N9 | CK-12/21; actual-engine new-participant contention, not old simplified lock recipe. |
| R03 | Narrowed/retained | CK-03/45; no callback-to-Action extraction absent real reuse/correctness need. |
| R04 | Actor/comparison repaired; representations retained | CK-12/44; HMAC/raw capture, tolerant inspection versus strict preparation parity. No universal capture presenter. |
| R05 | Shared resource-independent tag field implemented | CK-33/17 preserve exact existing consumers/removed-choice fallback; Group taxonomy is distinct. |
| R06 | Explicit host contract implemented | CK-10/11; preserve applied/pending filters/hooks/profile state, no broad trait split by size. |
| R07 | Finite capabilities repaired/healthy | CK-10; Images must declare truthfully, not invent generic capability registry. |
| R08 | Necessary native adapters retained; upgrade watchpoint | CK-15/16/41; compare actual installed parents when touched/upgraded and test native picker/reset/search/Repeater/upload. No defect merely from overrides. |
| R09 | Membership projection implemented | CK-11/12/33; asymmetric membership/full reorder atomicity, literal keys not IDs. |
| R10 | Shared mechanics where compatible; distinct lifecycles retained | CK-14/15/44; full native/image-only/Rule/tag/Preview semantics remain separate. |
| R11 | Healthy status factory; extraction optional | CK-03/45; preserve valid direct importer writes, no universal interactive-token requirement. |
| R12 | Older importer/field proposal superseded | CK-12/20/35/44; current ProductFields/profile JSON/Parts exact authority; no parallel registry or ERP expansion. |
| R13 | Discovery retained | CK-34/35/37; preserve distinct vocabulary/reconcile/prepare and live local snapshot/card path. No service/test deletion approved. |
| R14 | Rule summary/loading healthy | CK-45/44; preserve exact translated/eager-loaded output; extraction only demonstrated benefit. |
| R15 | Memo rollback implemented N12 | CK-32; same-instance nested rollback/publication, not S08 fallback repair. |
| R16 | Retired | CK-03; do not revive cast/setter/legacy model; preserve executable-reference closure. |
| R17 | Registrar implemented N15 | CK-13; early/late/repeated initialization with complete factory set. |
| R18 | Open measurement lead, not demonstrated slowness | CK-42; phase-specific query/decoded volume/payload/browser evidence before caching/index changes. |

### O01–O09

| ID | Disposition / reason not a prerequisite | Owner, trigger and acceptance |
| --- | --- | --- |
| O01 | Optional factory organization, CK-45 | A/F, separately justified ownership change; preserve native discovery/callers. |
| O02 | Optional grouping Actions/Services/DTOs, CK-45 | A/F; responsibility first, retain App/DTO convention and recognizable names. |
| O03 | Optional root enum relocation, CK-45 | A; root namespace valid; verify real inventory/discovery before any approved move. |
| O04 | Optional enum-cast BEHAVIOR change, CK-45 | A + H scope approval; exact backing values, drafts/import/export/hash/serialization parity. |
| O05 | Optional policy naming, CK-45 | A; CatalogPolicy/ConfiguratorPolicy are domain policies, not misplaced authorization merely by name. |
| O06 | Optional provider split, CK-13/45 | A; preserve lifetimes/callbacks/global and nonpanel exports; union does not require split. |
| O07 | Optional stable DI cleanup, CK-03/45 | A/F only where inputs clearer; autowiring/facades/contextual app() valid. No binding/interface per class. |
| O08 | Open/deferred package ownership, CK-41 | S/A, separate approval/runtime checks; MediaLibrary’s pending Images use forbids “unused” removal inference. |
| O09 | Optional DB/framework Unit-test relocation, CK-45 | A/Q; approval and unchanged lifecycle/isolation/coverage; not the safety fix. |

### V01–V05

| ID | Disposition and exact gate |
| --- | --- |
| V01 | Open/deferred CI triggers/private access, CK-40; real workflow needed, not local test count. |
| V02 | Implemented effective-target guard; CK-03/17 preserves across supported entrypoints. |
| V03 | Manual actual-engine evidence exists; CI provisioning still open/deferred CK-40; fresh combined feature races CK-17/23 remain mandatory. |
| V04 | Open/deferred CI format-drift failure, CK-40; later local Pint fixing stays a separate project obligation. |
| V05 | Selected focused boundary tests retained; CK-03/17; broader architectural guards optional and behavior-led, not filename/count mirrors. |

## 12. Later master repairs, deferred security and other-owner exclusions

### Four later findings: repaired, preserve rather than reimplement

| ID | Current evidence/disposition | Feature action and ownership |
| --- | --- | --- |
| SA-01 | Implemented CP5; authentic independent nested-child availability tests recorded | F preserves fresh Product/ancestor/Configurator admission in CK-22/36/37; A owns existing authority. Admin Preview stays separate. |
| RL-02 | Implemented CP6; canonical Values/Attributes/Attribute Values real CSV/XLSX roundtrip proof recorded | CK-13/17 preserve Standard default/output, explicit legacy-preserve/legacy-convert enclosure-only/custom choices, active rules, whole-file predispatch validation, exact keys/codes/memberships/unmapped data and backslashes. No CSV framework reuse for images. |
| RL-01 | Implemented CP6; checked false-write, enclosing commit/rollback/storage failure/MySQL publication evidence | CK-13/17 preserve sealed plans/immutable captures/same Export row publication lock, honest row versus infrastructure accounting, independent format readiness, historical null marker handling and scoped adapters. Not a durable generic Save receipt. |
| AD-01 | Implemented CP7; actual prepared/inspected/rendered exact-value cases recorded | CK-31/33/36/37 preserve literal 0/001/whitespace, exact fallback behavior and unchanged compiler acceptance. No new “fix all falsey values” sweep. |

Closed CP6 review subfindings (historical page gaps, unrequested-format notices, Attribute-family file proof and publication race) are included in RL-01/RL-02, not additional open tasks. XLSX client-download limitation stays CK-47.

### Security inventory: EVERY section reviewed, separate approval still required

Common owner S/A; gate D; evidence is historical report/spec plus current feature seam, not a new advisory scan, exploit reproduction or production inspection.

| ID / aliases | Disposition and trigger; why not a blanket continuation prerequisite |
| --- | --- |
| SEC-00 / S07 / V02 | Safety repaired; preserve CK-03/17. Do not rebuild it from an old pending checkbox. |
| SEC-01 / F01 / E1 | Package/served-asset remediation deferred CK-41. On resumption verify current official advisories, installed dependencies and actual published RichEditor/Livewire assets; lockfile/build alone insufficient. No historical version-floor recommendation here. |
| SEC-02 / F02 | Retained sensitive 2FA snapshot fresh-confirmation gap deferred. Separate genuine old-snapshot/direct-action denial and success tests; unrelated feature work does not authorize auth repair. |
| SEC-03 / F03 / B1 | Private FileUpload metadata path signing concern deferred. If native path protection changes, coordinate actual RPC/foreign-output and legitimate CSV/XLSX/image-temporary flows with CK-16. No filename-only exemption or automatic prerequisite patch. |
| SEC-04 / S01 | Email-based admin authority risk deferred. Proposed protected is_admin/backfill/import exclusions need separate migration/account approval and forged-input/email-change tests. |
| SEC-04 / S02 — Horizon | Fail-closed scalar-versus-list configuration issue deferred. Separate chosen authority/enabled flag, gate + real local/production callback tests. Not worker tuning or evidence of exposure. |
| SEC-05 | Native login/TOTP pipeline bridge deferred; old Login snapshots, challenge/recovery and compatibility tests belong to approved auth work. |
| SEC-06 | Recovery-response/throttle gaps deferred; generic responses/direct resend/broker cooldown tests under approved auth scope; no measured abuse claim. |
| S03 | Historical owner download after admin revocation is explicit retention policy; excluded as contrary automatic new Export/Import policy. Preserve current canonical job Gate checks. |
| S04 | Explicit actor concern repaired N4; CK-12/16/44. |
| S05 | Healthy status orchestration retained; CK-45 optional extraction only. |
| S06 | Old lock advice superseded N1/N9; CK-12, not new lock framework. |
| S08 | Appearance-read resilience candidate CK-45; distinct from repaired memo, conditional on affected path/new stricter validation, not universal block. |
| S09 | Memo rollback repaired N12; CK-32. |
| S10 | Non-HTTP error-boundary candidate CK-45; trace real command/import callers before narrow mapping, no generic exception platform. |
| S11 | Exact tag identity/writer repaired N6; CK-33, no blanket trimming of legacy strings. |
| S12 | CI gap retained CK-40; not “security completed” or local feature blocker. |

### Rules Writing and related IDs: other-owner work, preserve shared contracts only

Common owner W; CK-44/conditional shared-path handoff. Coding authority and app acceptance are absent for Writing; no task is silently assigned here.

| ID | Review disposition / feature relevance |
| --- | --- |
| G4 / RW-F01 | Missing Writing backend/translation/shared drafts is unfinished OTHER feature, excluded as prerequisite. |
| Rules R-01 | Future selected-rule atomic merge into fresh full aggregate belongs to W; preserve current aggregate writer. |
| Rules R-02 | Ordinary stale editor repaired (INT-03); Writing draft revisions unfinished, not a second open stale master defect. |
| Rules R-03 | Current ProductFields/literal keys exact authority preserved CK-12/20/35/36. |
| Rules R-04 | Current None/Custom/Master subtitle/default/inclusion fields must survive current complete loader roundtrips; preserve, not old prototype omission. |
| Rules R-05 | Rules XLSX specialization/helpers/private paths/native job lifecycle preserved CK-13/17. |
| Rules R-06 | Missing semantic ID versus explicit null/blank, canonical IDs/inclusion IDs/Option codes distinct; preserve loader/capture contracts. |
| Rules R-07 | Prototype Node/browser tests do not certify Laravel persistence/authorization; CK-17/36 likewise separates evidence. |
| Rules R-08 | Lossless unsupported syntax/effect retention codec remains W’s feature; excluded from current implementation scope. |
| RW-P01 / RW-R01 | Prototype capture/stale-form concerns repaired in prototype, not app Writing implementation; do not claim backend delivered. |
| RW-G01 | Selected-section refresh adaptation specified, not app-implemented; other-owner future work. |
| RW-M01 | Alias of repaired stale/comparison/actor/recovery families; do not double count. |
| RW-G02 | Browser-storage readback useful prototype evidence; shared app outcome acceptance unverified, not Images backend prerequisite. |

## 13. Complete report-section coverage and explicit exclusions

| Full report section | Coverage / no silent omission |
| --- | --- |
| Baseline/evidence | §1 pins both full reports, hashes, exact targets and historical versus current evidence. |
| 1 — INT findings/gates/lanes | §10 maps all INT-01–07, G1–G4, BULK-F06, PRV-INT-02/03, PF-L01 and P-01–06. |
| 2 — sixteen dispositions | §11 maps every step/node; retains repaired/healthy boundaries and legacy retirement. |
| 3 — all R/O/V | §11 enumerates 18 R + 9 O + 5 V = 32 recommendations individually; no inflated open-defect count. |
| 4 — later master findings | §12 maps all four implemented repairs and their closed subfindings; CK-13/17/37 preservation. |
| 5 — security/Horizon/packages/operations | §9/12 explicitly retain/defer each SEC/S/F/B/E alias, CI, restore, optional worker/client/IDE gaps. No unauthorized remediation. |
| 6 — Images | CK-01–23 and §§8/10 cover all A01–A07/R01/B01/B02/upload seam, P0–P10 and retained strengths. |
| 7 — Catalog | CK-30–37 cover CAT-01–09, CAT-S08-01–06, CB-P01/P02 and all three actual product reviews. |
| 8 — Writing | §12 maps all Rules R-01–08/RW aliases and explains nondependency while preserving shared authorities. |
| 9 — ordering/other work/evidence | §§2–9/14 give owner/dependency/gate ordering, optional quality/performance/CI/operations, no premature feature or runtime claims. |

**Explicitly excluded from “must fix before continuation”:** repaired master defects; duplicate aliases; deliberate healthy-retain decisions; optional structural moves/interfaces/provider splits/casts/test relocation; separate Writing backend; broad deferred security/package work; actual D60 reads/import; phase-2 library/attachments/PDF/gallery/additional entities; optional export worker; client-specific XLSX save and unrelated exhaustive device/IME/zoom/heap matrices; unperformed PHPStorm inspections; preventive tooling installation. These remain recorded with owner/trigger above, not silently closed.

**Retained engineering strengths:** authoritative aggregate/full/pointer writers; current compiler/loader/engine/DTOs and signed captures; exact identities/JSON keys; finite membership and revision/snapshot ownership; native framework lifecycle plus necessary adapters; scoped lifetimes/after-commit publication; private sources, independently fenced preparation/winner and truthful failure journals. New capabilities extend these rather than replace them with generic frameworks.

## 14. Ordered discussion/execution checklist

1. **B0/R0:** approve the scope referencing this full checklist; preserve the complete overlay/runtime; synchronize execution contracts and isolation/verification targets (CK-01–03). No additional root/master repair is currently required.
2. **I1/R1:** approved P0–P6 combination, coupled host/profile/search/pagination + finite membership/locking + registrar/provider/CSS/test union (CK-10–13).
3. **I1/R2:** approved R01 and native B01/B02 completion/fencing repairs, with native create-another feasibility and discriminating checks (CK-14–16).
4. **I2/R3:** fresh guarded combined tests/actual-engine relevant contention and actual browser upload/native/Images/ordinary-resource acceptance; reviewed P6 checkpoint BEFORE P7 (CK-17).
5. **Independent catalog C lane:** selected design/prototype repair approval; settings/tag/writer/display-version contracts; authorized navigation/facets and bounded history; approved representable transfer; Product header/return and separately tested code restoration (CK-30–37). Do not wait for P7/P8 unless an actual shared dependency exists.
6. **L7 → L8:** complete frozen matching/review, then ≤50 stable-step foreground Apply with separate acceptance checkpoints (CK-20/21).
7. **L9:** resolve A05 once and wire image display in coherent authorized catalog reads; jointly accept whichever existing/redesigned presentation is actually approved (CK-22 and catalog prerequisites for redesigned layouts).
8. **L10/rollout:** record capacity, relevant fault/race/browser proof and safe operational rollback; trigger restore/CI/security/dependency work only under its defined conditional scope/approval (CK-23/40–47).

This document records review readiness and required work. It does not mark implementation, combined acceptance, CI, security or release ready. No action above starts automatically from a SHA, checkbox, incoming task message or this documentation delivery. The documentation edits themselves are authorized; application/prototype implementation, verification runtimes, commits, merging and pushing remain outside this delivery.

## 15. Documentation reconciliation verification — 28 October 2026

The authorized delivery comprises seven primary documentation paths: this audit, the combined premerge plan, catalog journey/settings plan, Images amendments, both Images governing documents and docs/README.md. Newer P6 plan history was incorporated from the protected worktree; active baseline/recovery/lifecycle requirements were corrected and dated evidence qualified. This is the A06 documentation disposition, not a P6 code repair or merge-readiness pass.

Local checks found 63 resolvable file/anchor links, all 23 source-checklist CK headings and every checked finding-ID token from the complete cross-worktree report. The seven documents have no trailing-whitespace issues; tracked diff whitespace checks pass. Preserved pre-edit hashes match all 92 pending Images paths, 53 prototype files and all primary files outside the documentation allowlist. Images/prototype pending status and all three HEADs remain unchanged. Ignored runtime was not operated on or snapshotted; a later approved integration must still preserve it explicitly.

No application/prototype code, tests, DB, services, browser state, dependencies, actual D60 inputs, commit, merge or push changed during this documentation delivery. Proposed checks remain proposed, and historical observed results retain their dates.

**Independent owner consistency review:** Plan catalog configuration and UX reviewed all seven drafts against the complete continuation checklist and full source audit on 28 October. It found no material omission or contract error and requested no correction. This accepts documentation consistency only; native adapter design, the concrete execution specification, human execution approval and combined runtime/merge acceptance remain pending. This review receipt was appended after the reviewed content was frozen.
