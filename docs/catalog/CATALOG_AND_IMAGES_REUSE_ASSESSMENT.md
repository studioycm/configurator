**Current disposition after accepted K1, 30 October 2026:** source baseline is committed Master62f3eb9, not an old feature tree or pre-K1 snapshot. §19 maps actual K1 producers and the next K2-D1 affected plan. Images implementation remains deferred; closed K1 test resources are not reusable by implication.

# Catalog and Images — selective reuse assessment

**Status:** planning only, 28 October 2026. Read with the [master execution specification](CATALOG_AND_IMAGES_MASTER_EXECUTION_SPEC.md) and [full reconciled audit](CATALOG_AND_IMAGES_PLAN_AUDIT.md). Only these two new planning files were written; no application/prototype/runtime/Git integration was performed. Classifications are implementation recommendations; “reuse” is not a claim of current combined test acceptance.

**Current dependency amendment:** R11, 30 October 2026 — §17 supersedes old current-baseline/reuse descriptions only where Groups One–Two changed actual main; all other disposition remains.

**Document revision at full Catalog review:** R10, 29 October 2026. The master specification §21 is the exact first-package independent-review manifest and proposed command/acceptance/rollback contract. Recommended first package remains M0 + I1–I4, with unresolved native `create(true)` completion feasibility a hard checkpoint, not an event-only notification repair. R3 corrects process environments before bootstrap, preserves guard/cache refusal, and requires one destructive-test owner/window for the shared disposable MySQL DB. No execution authority is implied.

## 1. Baseline and method

Target authority: current primary master `8e1f640219ec8dbd2ee4c739041907aba4c4224d`, including repair merge `5788905a4a0ac95f26cf435cac4d82c09b576dd0`. Seven pending reconciled documentation paths are separate from the application baseline and preserved.

Older implementation reference: `/Users/studioycm/.codex/worktrees/catalog-images-p0-p1/configurator`, detached `244b305d083a90c731cd4bde23d996b4db0f8f3d`, 92 pending paths, including essential untracked code/tests and planning. Catalog prototype: `catalog-review-site` at `b6395c56d4ab845baf2d80a89bbb115058db7573`, one pending repair plan, 53 tracked/pending files. These are protected references, not a baseline or a merge source to overlay wholesale.

The full source/integration findings were reconciled by Cross-worktree integration audit and reviewed by this feature owner. This assessment uses those conclusions plus current source/status/native API checks. No fresh application test, browser, runtime or database verification occurred. Preserved old passes remain standalone historical evidence. Reassess affected rows if master or dependency versions change before execution.

Classifications:

- **Retain current master:** an existing application authority; adapt feature callers around it.
- **Reuse candidate:** an earlier cohesive algorithm/component can be copied selectively with its exact required dependencies and meaningful tests, then accepted on the new base. This is not blanket unchanged-file approval.
- **Adapt:** useful implementation exists but integration, lifecycle or UI contract must change.
- **Replace behavior:** reject the specific stale/unsafe behavior, not delete/archive the preserved reference files.
- **Build:** unfinished capability; plans/prototypes do not constitute implementation.
- **Defer:** outside phase 1 or separately owned work with an explicit trigger.

## 2. Current master authorities to retain

| Capability / current paths | Decision | Reason and required preservation |
| --- | --- | --- |
| `CatalogAvailability`, current Group hierarchy/ancestry, real leaf/Product membership | Retain | New tree/tags/cards cannot redefine access or turn hidden-only physical branches into leaves. Preserve SA-01 on independent child requests. |
| `ProductFields`, `ProductFieldQuery`, `ProductEditorData`, current profile/editor projections | Retain | Exact property keys, Properties/Parts/Others, selected editor fields and both Product fingerprints. Image reference lookup is not limited to displayed fields. |
| `SaveCatalogGroup`, `SaveCatalogProduct`, `SaveGroupSettings`, `CatalogIntegrity`, `CatalogRevisions` | Retain authoritative behavior; narrowly extend | Keep actor, strict validation, lock participant planning/fresh admission, SQL transaction/retry ownership, revision/no-op semantics and full-versus-narrow preservation. Never use a fabricated full form for image-only/batch writes. |
| `CatalogSnapshots`, matcher/reconciler, `CatalogCards`, `CatalogCardDisplay` | Retain | Complete-result matching/differences, all-results threshold behavior, coherent server cards, exact totals/boolean compatibility and 24-card rendering chunks. Extend result projection/version envelope, not the compiler dataset with image payload. |
| `ConfiguratorDefinitionLoader`, compiler/engine, Preview signed Save lifecycle | Retain | Code restoration is new; dashboard public-context/Rules exclusion stays. Image changes conservatively invalidate older tested Product/Group captures. No reuse of Preview operation receipts for image Save. |
| `HasWorkspaceTable`, `TableBrowserProfile`, native search/drawer/picker adapters | Retain explicit contracts | Add a truthful Images host and bounded capabilities. Do not weaken host typing, restore old reflection, or retrofit enhanced image controls into ordinary resources/drawers. |
| WeakSet Alpine registrar; provider CSV/Rules/native/historical export branches; current safety bootstrap | Retain | Union required image additions. Keep scoped lifetimes, idempotence, export publication/readiness and effective test-target checks. |
| Completed sixteen dispositions + SA-01/RL-02/RL-01/AD-01 | Retain / retired legacy remains retired | These are not unfinished prerequisites. The audit carries all aliases and concrete preservation tests; no broad rollback to the old base. |

## 3. Earlier Images implementation: selective disposition

All paths in this table are relative to the protected Images reference. Target paths stay in the corresponding existing app structure unless an explicitly described adaptation changes them.

| Source capability/files | Decision | What travels / what must change | Acceptance package |
| --- | --- | --- | --- |
| Four `app/Models/CatalogAsset*.php`, four factories, `DTO/CatalogAssetChange.php`, five `2026_10_23_155712`–`155716` migrations | Reuse candidates | Keep additive schema, explicit winner, ready metadata, claims/source/batch/item journals and owner FK foundation. Confirm actual target schema/migration history first; preserve retention and restrict-delete behavior. No legacy `product_pic` backfill. | I1 foundation/schema tests |
| `Services/CatalogAssetTargets.php`, `CatalogAssetAssignments.php` | Reuse candidates with caller adaptation | Keep finite Product/Group/main-image policy, current ItemLists mandatory context, ready/winner validation and pointer mutation without save/revision/file work. Compare latest availability/locking callers and exact field registry. | I1/I3 scope/assignment tests |
| `CatalogAssetSourceFiles.php`, `CatalogAssetSources.php`, `CatalogAssetFiles.php` | Reuse candidates | Keep bounded file/frontier traversal, actor-owned reservation/settlement ledger, immutable fragments/manifests, revision replay and all three source recovery-journal fixes. Bind to new isolated disk/context configuration; retain source mutation/symlink/path/byte-count protections. | I1 sources + actual-engine contender proof |
| `CatalogAssetStorage.php`, `CatalogAssetPathGenerator.php`, CatalogAsset conversions/Media behavior | Reuse candidates | Keep attempt-owned paths, import-key idempotence, lease/token fences, explicit winner publication, non-upscaling variants and original fallback. Revalidate installed package hooks and connection guard. No collection cleanup/digest-wide global deduplication or loser deleting winner bytes. | I1/I4 storage/concurrency/failure proof |
| `config/catalog-assets.php`, disk config, Media per-model path mapping, source preview/upload controllers/routes | Adapt | Keep private-source/public-managed split, endpoint-issued upload origin, actor/source/context authorization and signed previews. Future clean lane gets separate runtime roots. Main app/old ignored runtime and real D60 paths are not copied/imported. | M0/I1/I4 |
| `CatalogAssetRecordSearch.php`, `CatalogAssetRecordSequence.php`, search-highlight JS | Reuse candidates | Retain canonical recipe, exact-key query service, fragment/numeric/normalization rules, bounded explicit-ID selection and neighbor semantics. Test current eligible query, scalar sorts/null ties and custom pagination on master. | I2 |
| `InteractsWithCatalogAssetBrowsing.php`, `CatalogImages.php`, search/status/pagination/table-header views | Adapt | Keep dedicated workspace, read-only browsing/inspector and semantic Results/Checked navigation. Implement current HasWorkspaceTable methods and profile capabilities together; do not restore old generic toolbar controls to ordinary resources. Compact pagination remains on one row without helper text. | I2 + A01/A04 |
| Old `TableBrowserProfile.php`, profile JS/tests | Replace incompatible portions; adapt semantic union | Current explicit host contract is the base. Add validated assetSearch/custom-size restoration under 32 KiB and target identity. Preserve current native picker/drawer sessions, column migration and failed restore. No wholesale file copy or type relaxation. | I2 |
| `CatalogMainImageField.php`, field view, `catalog-image-editor.js`, `catalog-asset-upload.js` | Adapt | Keep native FileUpload, authenticated source tickets, operation intent, preflight/final separation and compact current/proposed image field. Preserve native keyboard/create behavior, no added ordinary navigation controls. Test actual native chooser, not just endpoint. | I3/I4 |
| `CatalogMainImageEdits.php` inspection/recovery; all JS/Livewire consumers | Replace specific IMG-R01 behavior; retain remaining receipt/draft design | Goal match is not proof of this request. Return/consume fresh inspected fingerprint; recheck actor/owner/context/full draft/destination; truthful message and no blind replay. Native full Save/Create remains review_required on uncertainty. | I3 focused recovery matrix |
| Deferred `EditProduct`/`EditGroup` callbacks, `CreateGroup`, `SplitCreateRecord` adapters | Replace unsafe completion behavior | IMG-B01 must cover inherited notification/redirect/create-another reset; IMG-B02 must fence entire saved raw draft/context, including newer ordinary changes. Supported native lifecycle feasibility is a checkpoint. Do not copy vendor internals or accept event-only proof. | I3a/I3b/I4 |
| `SaveCatalogMainImage.php` | Reuse candidate with current-lock adaptation | Pointer-only authority remains separate from full writers. Reauthorize fresh actor/context/fingerprint; Group→Product locks, shared ready validator, one real-change revision. | I3 + master lock regressions |
| Old edits to `SaveCatalogGroup.php` / `SaveCatalogProduct.php` | Adapt additions only | Preserve master Configurator-before-Group planning/fresh owner admission and current field fingerprints. Add internal image change and full-owner guard at the authoritative locked write; preparation remains outside. | I3 + A02 |
| Provider, `app.js`, profile JS/theme and `AdminProfile.test.js` overlays | Semantic union, not replacement | A03/A07: keep one registrar and all current export/CSV/scoped/provider/opt-in hook behavior. No broad global UI hook or two competing registrars. | I2–I4 |
| `CatalogAssetDisplay.php`, `components/catalog/main-image.blade.php` | Reuse bounded admin projection/rendering; adapt for catalog | Keep ready metadata/contain/original fallback. Existing raw-input 500 cap/query limit is unsuitable for all-results catalog. Add trusted result-scoped projection at CatalogCards; retain small-admin bounds. | I3 preview then I7/A05 |
| Old `tests/Pest.php` and transaction-scope test changes | Adapt carefully | Retain current effective-target safety/partitions. Add only image-specific independent-commit cases; never copy manual transaction escapes or weaken unrelated suites. | M0/I4/I8 |
| Image-focused PHP/JS test files plus shared profile tests | Reuse candidate assertions, extend missing boundaries | Preserve real test value; update current contracts, add recovery/native/integration cases and run on new base. Historical pass counts cannot substitute for execution or actual-engine/browser proof. | Corresponding package, then I4/I8 |
| Old Image plan/blueprint/three newer planning docs/continuation checklist | Retain as historical evidence | Current primary reconciled plan §15 / blueprint §21 and the new master spec govern execution. No copying stale “complete” labels into combined acceptance. | Planning traceability |

The **nine historical semantic overlaps** remain an explicit comparison map: `SaveCatalogGroup.php`, `TableBrowserProfile.php`, `AppServiceProvider.php`, `resources/js/app.js`, `resources/js/admin-profile.js`, `resources/css/filament/admin/theme.css`, `tests/Unit/AdminProfile.test.js` and both original Images governing plan files. On a clean master-based lane these are adaptation/review points, not instructions to merge an old overlay. Unchanged consumers/native parents remain in the regression scope too.

## 4. Unfinished Images functionality to build

| Capability | Status / selected approach | Owner and gate |
| --- | --- | --- |
| Pure filename matcher, recipe explanation and collision handling | Build P7. Exact initial match, optional normalization/case/pattern controls; Contains is suggestion only. No suffix inference from Product code. | This task I5, after combined foundation acceptance |
| Assign one image / Product folder matching / complete inline review | Build P7. Groups support one-image batch, not invented Product-style property matching. Freeze all positions, bounded build, manual candidate evidence, independent review selection/pagination/inspector. | This task I5 |
| ≤50 acceptance + journaled sequential foreground processing/results/recovery | Build P8. Stable step IDs, no auto resume/next-on-retry; final pointer/revision/result atomic; safe partial/systemic handling. | This task I6, after full review acceptance |
| Real catalog-card/header image wiring | Build P9 after A05 result-scoped projection; previews alone are not catalog delivery. | This task I7, with presentation actually approved |
| Capacity/lease/actual-engine/browser/operations acceptance | Unverified until run in isolated runtime. No queue introduced to avoid foreground limits. | This task I8 before rollout claim |

## 5. Catalog prototype versus real application

| Prototype material / capability | Reuse decision | Real application requirement |
| --- | --- | --- |
| Three visual directions, compact sticky controls, image trays, card positioning, top criteria/sidebar alternatives | Reuse design concepts selectively | One approved visual system and finite shared admin options, not three complete production themes. Required identity and light/dark/contained imagery. |
| Free Tree / Focus Path / tag card and chip arrangements | Adapt interaction model | Fresh authorized hierarchy/tag projection, plus versus select, exact scope, physical branch/leaf semantics, one page scroll and bounded rendering. |
| Group tags/sample application/pressure/capacity values | Illustrative only | Separate stable-ID normalized Group taxonomy, multiple values, OR/AND, exact identities/order/rename/remove, no reuse of configurator attributes/flat ValueTag. |
| Prototype settings and inheritance | Replace persistence authority | Shared presentation singleton/version + selected Group overrides through actual writers, explicit reset deletion and old-settings compatibility. No per-user/ancestor inheritance invented. |
| Newest-choice preview engine / serialized choice order | Repair reference only when approved | CB-P01 chronology/inherited preset tests, then actual Product reconciler/transfer proof. Group limits remain unchanged. |
| Caption Low Pressure | Approved repair scope still unimplemented | CB-P02 is “Low Pressure (6 bar)”, retaining exact 6. It does not rename real product data. |
| Prototype modelCode, demo records/images and mock eligibility | Do not port as authority | Actual Group has name/legacy_id, Product code/fields come from current data, eligibility from availability. No family/demo image claimed as managed owner image. |
| Positional configuration-code decoder | Replace | New saved-definition/actual-engine restoration adapter with exact regenerated-code validation and failure preserving choices. Unsaved Preview not restored. |
| Simulated review/share/comparison controls | Do not port by default | Only requested authenticated catalog journey, image assignment/review/Apply and code load/share. No anonymous publication, purchase/comparison or new sharing invitation. |

## 6. Catalog application work still to build

- **C0:** settle D1 visual/default choices and relevant D2 descendant policy; optional separately approved prototype repairs. None is an Images backend blocker.
- **C1:** concrete presentation singleton + authoritative save/version, all override full/narrow/reset/revision integration, separate Group category/value/pivot and administration/assignment on Catalog Display's selected-Group section. Preserve ordinary resource UX and serialization; no generic attribute/settings framework.
- **C2a:** real three sidebar policies and Group cards/filter vocabulary/counts over current availability. D2 is required only for optional descendant mode.
- **C2b:** D3 real explicit tag-to-Group-filter/preset mapping inventory, then smallest owned persistence/transfer adapter. Unsupported multi-values/multiple presets remain context; no arbitrary first choice or expanded engine behavior.
- **C3a:** Product results/presentation and bounded Group→Products→Product→return state, compatibility redirect query retention, history/scroll/focus/chronology and independent availability.
- **C3b:** actual reversible configuration code. Existing code generation is working; restoration is unfinished, separate from header styling.
- **Joint I7:** managed card/header imagery and coherent display-version/result-scoped projection acceptance. Full-set Product differences and all-results behavior remain intact.

## 7. Deferred and separately owned work

Shared library manager, global linked-image replacement, Asset attachments, PDF/other file types, galleries and other entities are phase 2. Keep CatalogAsset + main_image_id extensible without creating those abstractions/UI now. Actual D60 import is a separate approved operation; synthetic strings may test matching without reading real files.

Security SEC-01–06/Horizon/packages, CI INT-05/V01/V03/V04/S12, optional structural O01–O09, appearance-read/command-boundary suggestions, operational restore-dependent evidence and optional export workers retain the audit's owners/triggers. They are neither silently fixed nor blanket prerequisites. R18 calls for measurement before optimization. Writing conversion/drafts/batch persistence belongs to Research Rules import and export; coordinate any shared writer/lock/revision/loader changes before combining.

The full original inventory/aliases and all sixteen repair dispositions remain in the consolidated audit. R16 is retired code, not unfinished setter work. Later completed SA-01/RL-02/RL-01/AD-01 must be retained. Browser XLSX saving remains distinct from verified HTTP/file delivery. No fresh advisory/CI/browser/production readiness claim is made here.

## 8. Decision and handoff

**Recommend reuse, with deliberate adaptation.** The old Images implementation contains valuable bounded source/storage/search/navigation/field work; a rewrite from nothing would discard tested design. A wholesale overlay would reintroduce stale master contracts and leave real native/recovery defects unresolved. Select cohesive pieces into a clean then-current-master lane, prove the foundation, then finish P7/P8 and catalog work as independently reviewable packages.

First execution request: **M0 + I1–I4** from the master execution spec. This task owns it. Cross-worktree integration audit supplies bounded independent review; Plan bulk creation forms supplies baseline/integration coordination. D1–D3 are the only remaining product choices and block only their catalog portions. No implementation, checkout creation, merge, push or runtime mutation is authorized by publishing this assessment.


## 9. Complete pending Images path disposition — R4

Snapshot: 92 paths on detached `244b305d083a90c731cd4bde23d996b4db0f8f3d`, re-enumerated read-only on 29 October 2026. Paths below are relative to that protected worktree. Reuse means selectively carry a candidate plus meaningful tests to current master; Adapt means preserve current master and reconcile the feature addition; Discard means reject the named stale behavior/copy, never remove the source; Defer means preserve evidence outside application copying. Package and detailed conditions remain in §3/master spec. No single blanket P0–P6 reuse approval.

| Exact pending path | Disposition |
| --- | --- |
| `app/Actions/SaveCatalogGroup.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Actions/SaveCatalogProduct.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Filament/Resources/Groups/Pages/CreateGroup.php` | Adapt; discard unsafe attribution/completion behavior; I3a proof and I3 repair required. |
| `app/Filament/Resources/Groups/Pages/EditGroup.php` | Adapt; discard unsafe attribution/completion behavior; I3a proof and I3 repair required. |
| `app/Filament/Resources/Groups/Schemas/GroupForm.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Filament/Resources/Products/Pages/EditProduct.php` | Adapt; discard unsafe attribution/completion behavior; I3a proof and I3 repair required. |
| `app/Filament/Resources/Products/Schemas/ProductForm.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Filament/Resources/SplitCreateRecord.php` | Adapt; discard unsafe attribution/completion behavior; I3a proof and I3 repair required. |
| `app/Filament/Resources/TableBrowserProfile.php` | Adapt; discard old incompatible host/profile behavior, retain current master authority (I2). |
| `app/Models/Group.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Models/Product.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Providers/AppServiceProvider.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Providers/Filament/AdminPanelProvider.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `config/filesystems.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `config/media-library.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_IMPLEMENTATION_PLAN.md` | Defer — preserve historical plans/checklist/evidence; current primary contracts govern. |
| `resources/css/filament/admin/theme.css` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/js/admin-profile.js` | Adapt; discard old incompatible host/profile behavior, retain current master authority (I2). |
| `resources/js/app.js` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `routes/web.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `tests/Pest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Unit/AdminProfile.test.js` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `app/Actions/SaveCatalogMainImage.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/DTO/CatalogAssetChange.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `app/Filament/Pages/CatalogImages.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Filament/Resources/CatalogMainImageField.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Filament/Resources/InteractsWithCatalogAssetBrowsing.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Filament/Resources/InteractsWithCatalogMainImage.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Http/Controllers/CatalogAssetSourcePreviewController.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Http/Controllers/CatalogMainImageUploadController.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Models/CatalogAsset.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Models/CatalogAssetBatch.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Models/CatalogAssetBatchItem.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Models/CatalogAssetImportSource.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Services/CatalogAssetAssignments.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Services/CatalogAssetDisplay.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Services/CatalogAssetFiles.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `app/Services/CatalogAssetPathGenerator.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `app/Services/CatalogAssetRecordSearch.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Services/CatalogAssetRecordSequence.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Services/CatalogAssetSourceFiles.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `app/Services/CatalogAssetSources.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `app/Services/CatalogAssetStorage.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `app/Services/CatalogAssetTargets.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `app/Services/CatalogMainImageEdits.php` | Adapt; discard unsafe attribution/completion behavior; I3a proof and I3 repair required. |
| `config/catalog-assets.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `database/factories/CatalogAssetBatchFactory.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `database/factories/CatalogAssetBatchItemFactory.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `database/factories/CatalogAssetFactory.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `database/factories/CatalogAssetImportSourceFactory.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `database/migrations/2026_10_23_155712_create_catalog_assets_table.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `database/migrations/2026_10_23_155713_create_catalog_asset_import_sources_table.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `database/migrations/2026_10_23_155714_create_catalog_asset_batches_table.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `database/migrations/2026_10_23_155715_create_catalog_asset_batch_items_table.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `database/migrations/2026_10_23_155716_add_main_image_id_to_catalog_records.php` | Reuse candidate — retain bounded schema/algorithm/claim/retention contracts, verify on new base (I1). |
| `docs/catalog/CATALOG_AND_IMAGES_PLAN_AUDIT.md` | Defer — preserve historical plans/checklist/evidence; current primary contracts govern. |
| `docs/catalog/CATALOG_IMAGES_CONTINUATION_CHECKLIST_2026_10_28.md` | Defer — preserve historical plans/checklist/evidence; current primary contracts govern. |
| `docs/catalog/CATALOG_REDESIGN_UX_AND_SETTINGS_PLAN.md` | Defer — preserve historical plans/checklist/evidence; current primary contracts govern. |
| `docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_EXECUTION_BLUEPRINT.md` | Defer — preserve historical plans/checklist/evidence; current primary contracts govern. |
| `docs/catalog/IMAGE_ASSIGNMENT_POST_CONSOLIDATION_PLAN_AMENDMENTS.md` | Defer — preserve historical plans/checklist/evidence; current primary contracts govern. |
| `lang/en/catalog-assets.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/js/catalog-asset-search-highlight.js` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/js/catalog-asset-upload.js` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/js/catalog-image-editor.js` | Adapt; discard unsafe attribution/completion behavior; I3a proof and I3 repair required. |
| `resources/views/components/catalog/main-image.blade.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/views/filament/forms/catalog-main-image.blade.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/views/filament/pages/catalog-images-table-header.blade.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/views/filament/pages/catalog-images.blade.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/views/filament/resources/catalog-asset-pagination.blade.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/views/filament/resources/catalog-asset-search-status.blade.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `resources/views/filament/resources/catalog-asset-search.blade.php` | Adapt — selective feature component/addition; preserve current master and relevant §3 conditions (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetAssignmentTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetDisplayTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetFoundationTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetRecordSearchTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetRecordSequenceTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetSourceFilesTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetSourcePreviewTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetSourcesTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetStorageTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogAssetTargetsTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/CatalogMainImageEditsTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Catalog/MySql/CatalogAssetConcurrencyTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/CatalogAssetTransactionScopeTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Filament/CatalogAssetBrowsingTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Filament/CatalogImagesEditingTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Filament/CatalogImagesNavigationTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Feature/Filament/CatalogMainImageFieldTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Unit/CatalogAssetChangeTest.php` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Unit/CatalogAssetSearchHighlight.test.js` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Unit/CatalogAssetUpload.test.js` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |
| `tests/Unit/CatalogImageEditor.test.js` | Adapt — preserve meaningful assertions; refresh contracts, safety partition and missing proofs (I1–I4). |


## 10. Private runtime, sites and evidence — retain separately

Keep both existing Herd links and old worktrees under the latest human decision. The catalog-rebuild checkout was observed clean at `62e7bb42ea8458e0769434fe93451393cf49a546`; cleanliness does not account for its ignored runtime/import/rehearsal material. Its ownership and private runtime need separate inventory before cleanup. Neither old web site is the planned new implementation baseline.

Images storage contains unique synthetic P6 managed originals/conversions, private generated upload/import sources and runtime/login configuration, plus source and journal artifacts. Historical preview assignments include Product 5 and Group 5. Preserve these privately with the preview database and relevant browser/audit evidence. Do not copy credentials/private runtime into source, this document or publication. Read-only file inventory found no SQLite file under either checkout's database directory; the actual preview DB locations and complete backups remain **unverified** and must be securely established before deletion. Earlier SQLite isolation statements are historical evidence, not a current location verification.

Catalog-rebuild storage has import/rehearsal artifacts and logs that Git status does not cover. Preserve actual D60/source imports without reading/importing them in this feature. Standalone catalog-review-site remains its own protected prototype (53 inventoried tracked/pending files and a pending repair plan); other private assets also require inventory before removal. Discarding an obsolete adapter or mock decoder from the new design is not permission to destroy these sources.

**Review status:** R4 retains the R3 safe-command/native feasibility contracts and adds explicit current-master/shared-Rule-writer coordination, a separate C0 first slice and the full path manifest. Request bounded audit review of this delta; no implementation, test, site/runtime mutation or cleanup is approved.


## 11. Runtime evidence classification — R5

The master execution specification §23 now gives separate Catalog and Images acceptance-proof proposals. Current-master existing-behavior checks establish a baseline only; protected old source/prototype/site observations remain exploratory/historical; new implementation requires its own automated, actual-engine and browser save/failure/read-back proofs on isolated targets. C0 can certify repaired prototype interactions only, never unbuilt Laravel catalog behavior. First Images acceptance cannot certify unfinished matching/Apply/display. Every case records source/diff, dependency/build/fixture and safe effective-environment/storage fingerprints; PASS/FAIL/BLOCKED/NOT RUN are explicit.

R4 was independently reviewed without new blocking planning findings. R5 delta review is requested separately; no runtime acceptance is claimed. Keep R3's safe pre-bootstrap commands, R4's92-path disposition/Writing ownership and both retained sites/data. I3a's inherited native Create-another/form-reset/outer rollback/newer-draft proof remains a hard stop. Media/DB inventory and recoverability precede any affected destructive action; no cleanup authority follows from a discard classification. See §23 for exact case matrices, deliverables, private evidence handling, single-owner MySQL window and human approval gates.


## 12. Flat configurator and shared comparison preference — R6

Latest human decision: flat configurator list in all styles; discard expandable-section behavior from future production variants while retaining original prototype evidence. Three admin styles are still exploratory. Prototype Technical has selected-material content and Technical-only two-Product fact comparison; Field Guide adds descriptions/header imagery and different defaults. Do not classify them as identical skins or imply those extras exist in every style.

Reuse Technical comparison as interaction inspiration only: its modal reads demonstration PRODUCT_FILTERS/facts, not authorized real Products. Human prefers a shared comparison feature across styles; a later scoped plan should cover actual-data alignment, eligibility, two selections, third replacement, missing/exact values, read-back of current data, focus/return/responsiveness and no filter/configuration mutation. Recommend same-leaf saved Product facts first; cross-Group or configured-variant comparison remains an explicit larger tradeoff. This preference supersedes the blanket exclusion from later feature planning, not the existing Images/C0 execution boundaries. No source deletion or coding authority follows. Master spec §24 records the exact decision, proposal, tests and remaining scope choice.


## 13. Precision settled and bounded central controls — R7

Human selected Precision only. D1 is settled; older three-style exploration is historical and must not be presented as an outstanding approval. Keep original Field Guide/Technical sources as evidence, selectively borrow suitable tokens/content ideas, and omit a production theme picker/three-template system. Flat configurator list stays settled; shared Product comparison is separately scoped rather than style-exclusive.

Master spec §25 is the authoritative bounded-control amendment: Group cards (four image placements, finite scale/density/grid, permitted content); Product cards (four placements/scale plus reuse of current Group-owned grid/padding/facts); Product page (compact header or narrow image column, finite scale, one flat configurator). Preserve existing Group ownership and legacy/custom Product settings; no ancestor overrides, duplicate global Product-card policy or theme builder. New image-scale overrides are own-Group/leaf respectively; page layout shared only. Responsive/accessibility and semantic-invariance/save/read-back cases are specified there.

This material presentation delta needs independent review before affected Catalog implementation; current Images foundation and C0 repair scope are not expanded. No old source/style/site/data deletion or feature/runtime change is approved. D2, D3 and comparison's actual scope remain the only listed product/behavior questions; implementation feasibility and numeric layout tokens remain engineering work.


## 14. Settled model grid and no settings preview — R8

Master spec §26 supersedes historical immediate-child-card/optional-descendant and live admin-preview requirements. Reuse prototype Precision tokens/card components and independent tree/tag presentation selectively; **discard intermediary parent-card traversal and mock eligibility as production requirements**, while preserving original source/evidence. Root cards must be actual authorized visible active-Product-bearing end/model Groups. Tree parent selection and tags narrow the same eligible model set; derive scoped facets/counts from that model projection. Childcount==0 by itself is insufficient. Current availability/integrity/physical-leaf/snapshot authorities remain; parent-with-Products data would require an evidenced contradiction report, not automatic migration. No actual stored-row inventory was performed.

Discard the proposed admin live preview surface/read path; settings plus explicit Save only. This does not remove Images candidate/current/proposed previews. Remove optional immediate-child/descendant policy controls and formerD2 question; retain tree/tags alternative modes and agreed filter/context semantics. Current master lacks the new projection/UI: old prototype observations cannot runtime-accept it. Existing C0 repair does not silently rewrite the mockup's navigation; larger prototype alignment requires its own allowlist.

R8 changes C1/C2 production specification, reuse and acceptance cases only; no feature/runtime code or retained-site/data changes. Precision/flat list/shared comparison intent remain settled; only actual transfer mappings/comparison boundary and any evidenced legacy ownership contradiction require further behavior decisions. Review this semantic delta independently before affected implementation approval.


## 15. Remove first-page tag transfer — R9

Human settled tags as first-page model-card filters ONLY. Former D3/C3 mapping question and C2b/CatalogGroupCriteriaTransfer/mapping-store/toggle/positive-transfer tests are superseded, not deferred or awaiting mappings. Master spec §27 governs conflicting historical plan/audit references. Product filters independently filter the selected Group's Products; Configurator state is independent. Back-return state is not filter authority, and no new context banner is required.

**Discard from new behavior, preserve source:** prototype `stateForGroup` really calls `transferCriteria(state.criteria)` and sets Product filters/constraints/context. This is prototype-only coupling to remove in a separately reviewed/approved C0 alignment; never port it to Laravel. Bounded production Index/GroupShow/catalog-filters inspection found no matching Group-tag transfer adapter; no production defect or full-app absence proof is claimed. Keep genuine Product-side presets/newest-choice behavior and independently validated Product deep links/history. Negative tests must prove same Group entry yields same Product/configuration state regardless of Group tags, reverse independence, return restoration and no cross-Group leakage or forged-tag authority.

All old files/worktrees/sites remain retained. This semantic planning delta requires independent bounded review; no coding/runtime authority follows. No Group tag-to-Product mapping inventory remains on the decisions list. Comparison boundary remains separately scoped; mode/default discussion must not reintroduce transfer.


## 16. COMPLETE Catalog replan reuse disposition — CR2/R10

The complete current Catalog specification is [CR2](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md), with [source audit](CATALOG_REBUILD_COMPLETE_SOURCE_AUDIT.md) and CAT01–CAT24 acceptance ledger. This replaces older Catalog clauses in §§5–6/11–15 and combined R1–R9; those records remain historical. Images-only92-path disposition remains intact. Full independent package review replaces reliance on successive delta verdicts.

| Complete Catalog capability/source | Reuse/adapt/discard/defer | Reason / target |
| --- | --- | --- |
| Current Group/Product/GroupFilter/SubGroup schema/relationships | Retain; add only new taxonomy/settings schema | Actual hierarchy/direct membership/preset roles proven; no Group.type/modelCode/new Product membership table. Legacy mixed-role data unknown, not automatically migrated. |
| Availability/Integrity/import admission/Group and Product writers | Retain/narrowly adapt only needed settings integration | Preserve actors, active ancestry, physical branch ownership/lock/revision and exact Product fields. End-group card predicate adds actual eligible direct Product EXISTS. |
| Current Index/GroupShow branch view and parent-card tests | Adapt intentional new flow | Replace intermediary/root-category cards with all eligible model cards, parent tree organization and first-page tag query; update obsolete navigation expectation while retaining exclusions. |
| Current snapshot builder/cache/matcher/reconciler/JS engine+transport | Retain semantics; adapt displayVersion/journey | Product field literals, preset chronology, zero filter-state requests, latest requests/BFCache, exact totals/all thresholds/full-result differences24chunks. No new Group tag coupling or Product pagination. |
| Current field registry/facts/Parts/editor classification | Retain actual current authority | Public Group-filter Properties differs admin editor_properties; keep profile_fields exact and Parts1–28. No old fixed allowlist/read-only proposal. |
| Current real compiler/loader/engine/flat field renderer | Retain; add separate code restore adapter | Saved canonical identity/applicability, dashboard public-context exclusion, no unsaved Preview restore. No shared Rule writer edit; recompare approved Writing contract changes. |
| Current full/narrow Group settings/serializer/policy | Adapt as one package | New allowlist/raw preservation/deletion/noop/aftercommit and selected override stale checks; don't silently materialize defaults or use UI to decide writes. |
| AdminAppearance existing shell singleton | Retain separately; reuse architectural pattern only | New CatalogDisplay/settings own model/action/version, explicitSave, no live preview/preview-only reads. |
| New Group tag models/pivot/navigation | Build K1/K3 | Separate stable vocabulary/direct model-assigned tags, E/B/R and consistent scoped counts. ValueTag/Attribute never reused as hidden Group attributes. |
| Prototype Precision tokens/cards/trays/sidebar/tag icons/control concepts | Reuse design selectively; adapt to actual fields | One Precision renderer/finite settings; use actual ID/property/managed-image projection. Don't port all review knobs or add custom styling engine. |
| Prototype parent-card/modelCode/tree eligibility/auto-transfer/mock code decoder | Discard behavior from new app; preserve source | Human contract overrides; planned source-backed query, no transfer negative tests and real restoration required. No source/site deletion authorized. |
| Prototype expandable config/three production styles/admin live preview | Discard from current execution; preserve historical evidence | Flat list, Precision-only, explicitSave. Optional C0 alignment isn't required before production K1. |
| Technical demo comparison | Reuse interaction inspiration; build actual shared K6 after scope choice | Two actual eligible owners/data projection/fields, denial/missing/stale/focus/return; demo source isn't authorization or runtime proof. |
| Pending Images Asset/display component/projection | Adapt read interface only after accepted Images foundation | Ready winner/owner metadata, trusted result query0/501/repeated pointers, no raw500 catalog cap/no source/filesystem per render. No whole Images rewrite. |
| Existing Catalog tests | Retain relevant assertions; adapt only intended old parent-flow test | New exactID/tag isolation/version/reset/code/comparison/browser proofs; old tests are not passed just by being listed. |
| Old worktrees/sites/private database/media/prototype docs | Preserve/defer cleanup | Actual runtime location/backup completeness unknown; no test-reset targets, archive/delete or actual D60 import. |

K1 orK1+K2 is proposed first production slice; C0 standalone repairs/alignment optional. K6 comparison scope is the genuine remaining product decision, not D1/D2/D3. Future implementation/native/runtime/capacity proofs remain explicit engineering gates. No Catalog implementation is authorized.


## 17. Groups One–Two actual-main reuse refresh — 30 October 2026

Use [combined specification §28](CATALOG_AND_IMAGES_MASTER_EXECUTION_SPEC.md#28-actual-main-groups-one–two-baseline-and-dependent-plan-refresh--30-october-2026) for the exact accepted **uncommitted** source/build identity, evidence limits and execution hold. Source inspection matched all 771 entries. Old §1 committed-only/documentation-only identity is historical, not permission to start from an incomplete checkout.

| Existing disposition | Updated decision for BOTH features |
| --- | --- |
| Current inclusion/default/graph authority | Retain repaired six local defaults from ConfiguratorInclusionDrafts for ordinary and batch inclusion; preserve identities/order/overrides/old drafts. No Catalog/Images replacement or second default factory. |
| AdminAppearance singleton pattern as architectural reference | Retain current selective read-only recovery, strict writes and transactional/cache behavior. Supersede any description of the current reader as throwing on recoverable stored settings. CatalogPresentation stays separate; Images consumes current shell only. No service/helper/trait extraction justified by this refresh. |
| Provider/app registration overlays | Adapt by semantic union with current PAGE_START recovery notice, STYLES_AFTER/BODY_END and registrations. Keep current-before-recovery evaluation, accessible generic message and manage-catalog repair-link permission. No wholesale retained provider/translation copy. |
| Old image Pest/independent-commit/safety adaptations | Retain current exact catalog/rules/reconciliation profile union; adapt image-specific cases without replacing bootstrap, loosening guard or admitting QA/main/retained targets. Restricted reconciliation XML is not a feature-wide test recipe. |
| Existing snapshot/owner/revision preservation | Retain; Groups repair did not modify production snapshot command/Group/Product writers. Preserve new regression assertions. No rewrite or new snapshot invalidation strategy follows. |
| Retained 92 Images and 53 prototype paths | Preserve; all recorded hashes/HEADs and expanded pending-file status matched on 30 October. No source deletion/import/site reuse follows. Native lifecycle/source/storage/500-result projection gaps retain their existing IDs and gates. |

No change to K1 scope, normalized taxonomy, settings choices, image source/storage/matching/Apply semantics or shared-library deferral. No full re-audit or runtime pass for Catalog/Images is claimed. Review only this delta independently. Final implementation preparation needs the authorized reproducible actual-main baseline, then affected consumer checks; both feature implementations remain held.


## 18. Committed baseline and bounded release packets — 30 October 2026

Coordinator committed the exact13 Groups One–Two repairs locally as `07701045e4dda35213e24b078eba19675d697fc6`; fresh Git read confirms that HEAD. §17 retains the reviewed pre-commit source history; its missing-committed-baseline blocker is resolved, not a feature release. Actual accepted771 source/build identity is unchanged. No push or Writing/Catalog/Images integration is implied.

Release-readiness **K1-RP4** is [complete Catalog plan §16](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#16-catalog-k1-release-readiness-packet--k1-rp4-30-october-2026), exact16 paths/backend stop. Separate **IMG-RP4** is [combined spec §29](CATALOG_AND_IMAGES_MASTER_EXECUTION_SPEC.md#29-images-m0–i1-release-readiness-packet--img-rp4-30-october-2026), exact39 paths/foundation stop. These bounded packets govern the next release decision over the older broader M0+I1–I4 proposal; plans are still awaiting independent packet review and human release.

Only new selective-disposition refinement: native upload POST/controller depends on I3's editor-ticket issuer and travels with that later workflow, not the I1-only source/storage checkpoint. I1 retains upload-source reservation/copy/settlement at the service boundary and signed source preview GET; no chooser/owner-save claim. Current models/config/routes/Pest shared seams adapt by union on then-current source, including any accepted K1 work; no automatic integration to manufacture that baseline. All other §3/§17 reuse decisions, retained references/media/sites, current lifecycle gates and actual D60/library exclusions remain.


**Historical RP2 setup clarification (superseded for human-approved K1 only by RP4 below):** both packets' launches were NON-RUNNABLE until their shared decision in combined §29.1 records exact source/dependencies, target/profile, explicit destructive lifecycle permission, private capability and new lease. Historical catalog45-table target is protected; guard/credentials/lease do not authorize erasure. Reconciliation TEST-only reuse is a proposed separately authorized option, not a claimed available launcher or inherited G1/G2 permission; no QA/rules/main substitution. Same source allowlists; no runtime/resource/credential action. Preserve Livewire actual tmp-for-tests root isolation and inspect retained contender helper child exits/joins on future implementation.


**RP4 latest-human workflow:** supersedes RP3 mandatory trial. Plan and implement directly on unchanged actual Master, one writer; optional fresh worktree only for a demonstrated isolated uncertainty, no Git merge/promotion. K1 implementation/guarded SQLite and specific reconciliation TEST reset lifecycle approved, still require bounded RP4 review/unchanged baseline then private MySQL capability/ownership/launcher/new lease review before DB use. Main/historical Catalog45/QA/Rules excluded. After checks, independent actual-Master audit and required fixes, scoped local stage commit allowed; refresh affected later plans and continue Catalog sequentially, THEN Images, THEN Writing. Do not start Images after just K1 or inherit K1 resource permissions. Same16/39 path scopes, reuse decisions, guarded no-ambient/native POST deferral/I3a/settled UX; no feature code/test/resource action in this documentation amendment.


## 19. Accepted-K1 actual-source reuse delta — K2-D1

BOTH Catalog/Images owner read final actual-execution/downstream audit SHA256 `3376c89bd00bee9076adf8a6039a67de2c829c134ace9baffb26bfd188dfe979` and verified actual16 committed sources on Master62f3eb97b5b80dc9575276fef16441286bc2bd68. Complete Catalog §17 now supplies K2-D1 exact34-path/test/resource/activation packet; combined §30 maps every downstream audit row. Existing earlier reuse rows remain unless affected below. No old source deletion or new implementation authority follows from this assessment.

| Prior prospective/reference item | Actual disposition and required adaptation |
| --- | --- |
| New normalized taxonomy/navigation to build in K1 | **K1 implemented/accepted on Master. Retain**, do not recopy/recreate models/migrations/factories/relation. Stable ordered ID/pivot fingerprint, strict labels100/150, canonical input/no-op/stale/rollback and ordered Group/category/value locking are actual contracts. |
| K2 display Page/settings/version | **Retain accepted passive S4 backend; build remaining consumers only after their resource/activation gates.** Exact scope now includes CatalogNavigation.php real displayVersion and default-version tests, dataset/card/SSR/client pair freshness. Keep pure snapshot schema2/cache/engine authority; image metadata remains null until actual producer. |
| K2 category/value removal | **Extend actual SaveGroupTags with storage/reference writers atomically**, not delete assigned/reference data silently. Preserve all K1 existing operations/limits/fingerprint; fresh locked references, no singleton-first→Group inversion, absent singleton first-Save races. |
| Group full/narrow serializer/current policy | **Adapt fresh raw metadata preservation**, no ordinary UI redesign/hidden stale JSON override. Present/omitted/new-equal/explicit-known-key-delete differ; unknown raw/legacyfalsezero survives unless explicitly changed. New presentation fingerprint distinct from K1 tag fingerprint. Existing core SaveCatalogGroup/SaveCatalogProduct stay authoritative and unchanged. |
| Retained Images Group/Pest overlays | **Adapt by union against current Master**, preserving tagValues and exactly existing K1 independent MySQL test partition/ordinary RefreshDatabase. Do not restore stale pre-K1 Group/Pest or inherit K1 DB window. Later native I3a/media/owner gates stay open. |
| Current appearance/provider/caching pattern | **Retain G12 and add narrowly.** Separate presentation domain; current-before-recovery PAGE_START + styles/body hooks, exports and registration intact. Missing settings row default is not a missing-table probing fallback. |
| Canonical verification tooling | **Private reviewed evidence, not app-source reuse.** K1 evidence mandatory env/proof retention and scoped-permission diagnosis inform later launchers; never replay used release/nonce/closed window, expose secrets, change grants/guard or call a network refusal an app defect. |
| K5/Writing APIs | **No direct K1 codec/definition producer change**, do not redo those architectures. Writing must refresh protected test/source hashes and its own then-current fixtures/transaction partition through coordinator. |
| Actual image display/joint stage | **Deferred accepted dependency.** Null mainImage truthful placeholder remains. Real K7 requires accepted Images ready-winner projection, no schema guessing/product_pic URL/file read. Flag joint sequence decision at actual frontier, not a blocker invented for K2. |

K1 findings STRICT-INPUT/TEST-001/VERIFY-001/LAUNCH-001/RUNTIME-001 are independently closed, with source-specific evidence limits recorded in combined§30. New K2 checks retain them as invariants, not defects to rediscover. Main taxonomy migrations have not been activated; before any K2 runtime consumer/bundle, exact main additive migration authority/history/schema readiness is required. New K2 reset/browser authority is separate. Retained92 Images/53 prototype files/sites/media/data remain protected. No application/runtime/resource/test changes occurred in this reuse refresh.


### Accepted S4 reuse correction to §19

Independent closure SHA256 `4e0af0d1628fca1c678c88696558139f4a0b019f3260063a450b183f5ce9cc52` accepts the exact passive backend83/576 SQLite checkpoint and closes NOOP/PURE/RECOVERY. **Retain** its model/factory/migration/service/writer/two tests; no further production backend edit is part of source-only engine preparation. **Adapt** future Catalog Display selected-Group calls and CatalogCards to actual authorized raw result_settings plus one trusted server-resolved context batch; current cardGroup id/name stub is insufficient. **Adapt** SSR/dataset coherence and Navigation's version-only producer without inventing own projections or moving snapshot waits into transactions. **Retain** strict top-level object-map no-op with nested order/types, observed-V+typed-payload private cache identity, structural/reference split, own invalid-reference fallback and after-enclosing-commit publication. **Build later** scoped provider/full Group/removal/native/version transport with fresh locked write authority, never cached context as permission. Complete Catalog §17.7 maps all four consumer rows/proofs.

The new MySQL file/exact Pest exception are source preparation only; preserve existing guard/XML/helper/approved profile union and both prior independent-commit exceptions. No MySQL execution/lifecycle is inferred, no old resource window reused. Full K2 remains uncommitted/unaccepted; Images/shared library/D60 still deferred. This bounded plan refresh changes no Product/Group UX or settings/limits and requires no new product choice.
