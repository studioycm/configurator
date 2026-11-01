**Current accepted implementation input, 30 October 2026:** Master62f3eb9 now contains the independently accepted K1 backend. §11 records the bounded downstream/source reassessment for K2-D1; original CR2 audit evidence/closure remains historical and valid for its scope.

# Catalog rebuild — complete scoped source and planning audit

**Review status:** Complete independent CR1 review and CR2 correction closure received; see §9. Implementation remains unapproved.

**Revision CR2, 29 October 2026.** Authoritative scope is the human's complete Catalog audit/replan request and settled clarifications. Read with [one coherent implementation/execution specification](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md), including its CAT01–CAT24 acceptance matrix, and [reuse assessment](CATALOG_AND_IMAGES_REUSE_ASSESSMENT.md). This is a full Catalog-scope source/plan review, not a new whole-application security/compiler/Images audit or runtime acceptance.

## 1. Target, method and evidence limits

Primary master `8e1f640219ec8dbd2ee4c739041907aba4c4224d`; pending owner/coordination/audit planning documents remain separate from the application baseline. Current composer direct dependencies were read: Laravel13.35.0, Filament5.9.0, Livewire4.4.7, Blueprint2.4.0, Pest5.3.0; PHP8.4 project contract. `.ai/rules` absent in inspected checkout; supplied AGENTS applies. No framework/CLI app boot, database inventory, test, browser interaction, migration, feature code, dependency/Git/runtime/site change ran for this audit. Composer metadata/documentation/source reads are not runtime proof.

Applied the local LaravelDaily structure audit and Laravel architecture responsibility/transaction/actor/serialization guidance; UX-design-thinking contract/authority/uncertainty review; reviewing-filament-plans requirement/entry-point/denial/evidence separation; Filament planning and testing-best-practices. Boost version-scoped docs checked relationship existence and after-transaction effects; installed source/current native forms validate used APIs. Sources: [Laravel13 relationship existence](https://laravel.com/docs/13.x/eloquent-relationships#querying-relationship-existence), [events after transactions](https://laravel.com/docs/13.x/events#dispatching-events-after-database-transactions), [Filament5 custom Pages](https://filamentphp.com/docs/5.x/navigation/custom-pages), [forms](https://filamentphp.com/docs/5.x/forms/overview). No new framework/plugin dependency or implementation syntax is inferred from an incompatible example.

Reviewed the whole requested journey and dependency graph: model/schema roles and supported writes/imports; visibility/availability; root/branch/leaf/Product route/Livewire entry points; Product snapshots/query/filter reconciliation; raw Product/Parts/public/editor ownership; loader/compiler/engine/code identity boundary; URL/history/transport lifecycle; existing Group settings serializers/full/narrow writers and scoped registration; relevant tests and UI templates; pending Images read/display interface; all active Catalog plan/spec/acceptance families and prototype behavior assumptions. Relevant methods/clauses were traced, not every unrelated Rule/compiler/export implementation line. Supporting source inventories/hashes are appended for refreshability, not an assertion that hashing means code review. Truncated broad command outputs were supplemented by targeted reads of decisive methods; no runtime conclusions derive from them.

The completed Cross-worktree ledger/reconciliation, sixteen repaired/retired dispositions and later SA/RL/AD fixes remain preserved evidence. No open merge-caused regression is inferred from new requirements or an old base. Shared writing/provider/lock/field/safety authorities are consumed, not reimplemented. Actual current-master runtime tests, where separately approved, belong to the audit-led bounded regression lane; consume its evidence, do not duplicate it or use retained web DBs for resets.

## 2. Source contract graph and decisions justified by source

| Area | Inspected authority / relevant path | Audit conclusion and required plan treatment |
| --- | --- | --- |
| Group roles/membership | Models Group/Product/GroupFilter/SubGroup; Group/Product migrations; CatalogIntegrity; ImportCatalogProducts parent-resolution validation; AssignConfiguratorGroups; SaveGroupSettings | parent_id hierarchy ≠ SubGroup preset. Product one direct Group FK. Supported writes require end Groups for Products/Configurator/filter definitions; no modelCode or role enum established. Physical leaf alone isn't product-bearing; add eligible direct Product EXISTS. No role/membership schema migration justified. |
| Visibility | CatalogAvailability::visibleGroups/productIsVisible; independent ProductConfigurator render/loader | Active ancestors and Configurator hide policy matter; inactive-only leaf has no card. Disabled-visible/unassigned configurator can still show Products/facts. Authenticated read ≠ manage-catalog admin write. Direct Product visibility does not itself prove leaf ownership on legacy inconsistent rows. |
| Existing root/branch | Index::render; GroupShow::render; group-tree/index/group-show views | Current app intentionally renders root/child categories. New model-only root behavior is a redesign, not an already implemented feature. Replace parent-card route progression while retaining availability/named routes and honest empty leaf direct links. |
| Product filtering | BuildCatalogSnapshot, CatalogSnapshotMatcher, CatalogFilterReconciler; JS engine/transport | Exact literal keys/strings, single direct value, one atomic OR preset, newest-first chronology, boolean compatibility/exact total, unsupported input denial already exist. Do not invent tree/tag→Product matching or treat filter value_order as a new mandatory suitability rule. |
| Product query/cards | CatalogCards::get + CatalogCardDisplay::compare | Group-scoped active Products, exact matched IDs, threshold/all and complete-set differences before24 chunks. Keep separate server cards and outside-transaction rendering. Current small-image projection cannot be substituted for all-results query scope. |
| Freshness/state | DatasetController ETag; CatalogSnapshots; catalog-filters.js generation/readDiscovery/discoveryUrl/pagehide/pageshow/destroy; catalog-shell.js | Existing ETag observes Group/schema/revision, not new global presentation. New displayVersion needs coherent extension; use existing Product codec and request identity/BFCache semantics, no competing URL owner. Product compatibility redirect currently drops query; new return/code state needs adapter. |
| Facts/configurator | ProductEditorData, public product-facts view, ProductConfigurator/DefinitionLoader, compiler code identity and engine completion | Public Properties follow Group filters; admin editor_properties separate; profile_fields/parts authoritative. Dashboard strips public context conditions, not all Rules. Flat configuration fields already fit real Product workflow. Preserve real snapshots/diagnostics/Parts1–28 and unknown/inactive configuration truthfulness. |
| Code restoration | Compiler validates global unique two-character codes; engine sorts applicable by codeOrder and emits separator-joined code | Generation exists; reversible restoration is new. Decode against real saved definition/applicability and exact regenerated code, not prototype positions. One code at another Product isn't automatically valid; no unsaved Preview reproduction. |
| Shared settings | AdminAppearance/Page/Settings/Save action; GroupForm settingsState/data serializers; SaveGroupSettings full and patchPresentation; CatalogPolicy/Revisions | Existing shell settings ≠ proposed Catalog display. Full normalization can drop unknown keys while narrow patch retains raw omissions; array_replace cannot express deleting an override. Plan needs coordinated serializers/full+narrow/reset/revision tests and one explicit Save, no admin live preview. |
| Comparison | Prototype app.mjs::productCard/compareProfiles only Technical/demo facts | Wanted shared comparison is new actual-data function. No comparison implementation/table in current catalog traced. Exact scope still needs confirmation; default recommendation same-leaf saved facts. Keep it independently gated, not a renderer/theme condition. |
| Images boundary | Pending CatalogAssetDisplay + managed image components; source blueprint/Images amendments; current CatalogCards | Catalog consumes only accepted ready owner association; no product_pic URL inference/D60 backfill/source probing. Preserve A05/CAT-S08-05 result-scoped projection gate and source/storage/native-save isolation; no restart of complete Images audit. |

## 3. Findings, obsolete instructions and missing requirements

These findings assess new-plan conformance/compatibility, not an assertion all are current application defects.

| ID | Classification and evidence | Reconciled outcome / owning package |
| --- | --- | --- |
| CR-A01 | Obsolete scope: prototype/old UX plan starts category/child cards; human requires product-bearing models | K1/K3 E/B/R projection over supported Group/Product roles; parent navigation only. Old parent-card PublicCatalogTest must change deliberately; other exclusion tests retained. |
| CR-A02 | Unsupported shortcut: leaf==model ignores direct active ownership; legacy mixed ownership not ruled out by DB FK | K1 source-derived predicate includes visibility+physical leaf+eligible direct Product. Supported writer/import constraints justify leaf check; actual anomaly inventory is outstanding. Conditional inconsistency stops affected activation, not assumed data migration/new role schema. |
| CR-A03 | Cross-layer facet/count mismatch in old optional descendants/immediate-child concepts | K1/K3 one model set for both modes; B-scoped vocabulary/model counts, R card count; remove former D2/policy control. Pagination/disclosure cannot change set scope. |
| CR-A04 | Actual prototype coupling: stateForGroup calls transferCriteria, populates Product filters/constraints/context | Discard from new behavior, preserve source. C2b/D3/mapping inventory and CK-35 transfer acceptance superseded; K3/K4 negative isolation tests. Genuine Product-side presets/links remain. |
| CR-A05 | Obsolete styles/sections: three mock styles and expandable parameter sections | Precision only, bounded layout controls, flat actual configuration list; no theme picker/three-template or three-style production acceptance. Do not silently delete old prototype styles. |
| CR-A06 | Obsolete administration requirement: unsaved/live preview in older C1 | K2 settings+explicit Save only. Remove preview-only queries/state; retain Images field/current/proposed previews. |
| CR-A07 | Supported writer compatibility risk: new override keys can be dropped or silently materialized by full/narrow paths | K2 all serializer/validation/normalization/raw patch/deletion/fingerprint/revision handling as one package. Unknown/false/zero/empty/order and no-op/rollback tests; no UI hydration inside writer. |
| CR-A08 | New freshness obligation: global display change not represented by existing Group revision/ETag | K2 coherent displayVersion/cache publication/client+server checks; no mass Group revision update or stale request memo. |
| CR-A09 | Partial new journey behavior: current Product compat redirect drops query; existing Product URL codec is already an authority | K4 bounded return state plus validated route query preservation; do not duplicate d codec, widen return routes or allow return tags as filters. Back/BFCache/lifecycle proofs. |
| CR-A10 | Unimplemented feature: prototype positional code decoder is not saved engine restoration | K5 real identity/applicability/roundtrip validation with failure retaining choices; dashboard context/Preview boundaries retained. |
| CR-A11 | Real remaining product ambiguity: comparison same-leaf facts vs cross-Group/configured variants | K6 bounded options/recommendation supplied; only this package waits. Never report all decisions resolved. Labels/fields/JSON type handling need explicit stable alignment after scope choice. |
| CR-A12 | Result projection incompatibility: pending small-admin helper raw pointer cap500 vs existing all-results | K7 preserve small-admin bound, add trusted scoped projection and0/501/repeated-pointer query tests; no per-card Media/file I/O, source/compilation payload. |
| CR-A13 | Facts authority confusion: old fixed/read-only proposal versus current profile_fields and public/admin classification distinction | Preserve ProductEditorData and current Product facts/Parts; no old allowlist/writer replacement. K4 tests exact0/001/whitespace and public/admin independence. |
| CR-A14 | Acceptance gap: repeated R1–R9 supersessions leave contradictory active text | One CR1 spec becomes current Catalog plan/execution/reuse/matrix; full independent package review required. Existing Images instructions preserved. Old records linked as historical, not silently erased or passed as current. |
| CR-A16 | Discovery/lifetime obligation: AdminPanelProvider lists Pages explicitly, no Page discovery; current Group/Product resource delete flags are false | K2 explicitly registers CatalogDisplay and scoped CatalogPresentation without replacing provider unions; preserve ordinary resource capabilities. Strict new writes/tolerant bounded presentation reads avoid copying the known conditional appearance-reader weakness. This is planned compatibility/resilience, not an implemented fix to app-wide S08. |
| CR-A15 | Evidence gap: no new runtime/schema/data/benchmark run | Do not label future UI/runtime accepted or source rules as actual data cleanup proof. Audit-led existing-code lane can establish baseline; new packages need isolated implemented tests/browser/read-back. |

## 4. Every planning source reconciled, not blindly ported

| Existing source family | Retain | Supersede / exclude from current execution |
| --- | --- | --- |
| CATALOG_REDESIGN_UX_AND_SETTINGS_PLAN.md | Intended three stages, separate Group taxonomy, bounded controls, selected overrides, source-backed engines, CAT-S08 boundaries | Optional child/descendant card policy, transfer inventory, three-style decision, live settings preview. CR1 governs complete current behavior. |
| CATALOG_AND_IMAGES_MASTER_EXECUTION_SPEC.md R1–R9 | Images M0/I1–I4 and later Image phase contracts, preservation, guarded commands, latest human clarification provenance | All historical Catalog C0–C3/transfer/preview/default-choice/code assumptions replaced by complete CR1. No amendment scavenger hunt required to implement Catalog. |
| CATALOG_AND_IMAGES_REUSE_ASSESSMENT.md | Full92-path Images preservation and selective implementation map, private runtime/evidence limits | Prototype navigation/tag-transfer/theme-code are inspiration only; updated complete Catalog reuse table points to CR1. |
| CATALOG_AND_IMAGES_PLAN_AUDIT.md / PREMERGE_PLAN / CROSS_WORKTREE ledgers | Repaired master contracts; A01–A07/IMG recovery/lifecycle obligations; CAT-S08-01/02/03/04/05/06 preservation; review/evidence limits | CK-35 positive transfer superseded; descendant/immediate-card/defaultD1/D2/D3 questionnaire obsolete. Review entire new Catalog package, not carry forward delta verdict. |
| IMAGE_ASSIGNMENT_PHASE_1_IMPLEMENTATION_PLAN / EXECUTION_BLUEPRINT / POST_CONSOLIDATION amendments | Sources/private storage/ready assets, one main image, individual/bulk workflow, bounds/native lifecycle recovery; image-only/full writer separation | Not authorities for model eligibility, Catalog tag transfer, production styles or catalog Product pagination. P9 consumes CR1 presentation and coherent result set. |
| contracts/PUBLIC_CATALOG.md + CARD_APPEARANCE_IMPLEMENTATION_PLAN.md | Actual filter/chronology/URL/request/freshness/query/differences/threshold/all/24chunk budgets and exact-string behavior | Parent-card navigation description/test expectations intentionally change. Image addition extends measured query budget, doesn't imply old unmodified cost guarantee. |
| contracts/FILAMENT_ADMIN.md / PRODUCT_DATA_IMPLEMENTATION_PLAN.md | Canonical fields/actual current writers/actors/Parts/public details; current file locations and field registry | Older fixed imported/read-only/admin-scope proposals are not runtime authority. No new security/permission model or Preview public-context expansion. |
| catalog-review-site complete behavior families (domain/model/state/app/styles/tagicons/images/options/tests) | Precision visual vocabulary; card/tray options; independent controls; long/narrow/dark/error cases; pure reconciliation/test ideas | Parent intermediary cards, auto transfer, mock modelCode/fixtures, positional decoder, expandable configurator, three themes/live settings preview; Technical-only demo comparison cannot certify shared real function. |
| Separate image-assignment prototype | Operator source/match/review interaction ideas, explicit simulated boundary | Native runtime lifecycle, authenticated source receipt, actual persistent query/asset ownership can't be inferred from it. No production catalog semantics derived from image workflow controls. |
| Retained catalog-rebuild checkout/site and old Images checkout/site | Source/evidence/private data held intact; no automatic archive | Neither is a current-master implementation base or disposable test-reset target; clean Git status doesn't preserve ignored DB/media. |

## 5. Whole-journey consistency and complete requirement coverage

CR1 CAT01–CAT24 table is the full current requirement→source→planned-code/package→test/checkpoint ledger. All requested areas map there: models/roles/membership CAT02/03; active/auth CAT01/02; tree/tags/options/counts CAT04/05; no transfer CAT06; independent Product engine/membership CAT07/08; stale/lifecycle CAT09; route/direct/back CAT10; Precision/admin/override CAT11–14; flat real config/facts/code CAT15–17; shared comparison CAT18; images CAT19; responsive/accessibility/performance CAT20/21; shared compatibility/preservation/evidence CAT22–24. No code is declared conformant simply because it is listed in that table.

Connected path checks specified: settings hydrate→draft→validate→Save→reload/fresh display; taxonomy assign/rename/delete→eligible set→facets/card/route; tree/tag→Group Products without transfer→Product/return; Product choice→chronology→query→stale response; code parse→real engine→exact roundtrip→atomic state; comparison select→current two-owner read→close/return with no mutations; ready image projection→current card set→client fallback. Initial/empty/error/stale/denied/noop/rollback/context-change stages are explicit. No screenshot/notification substitutes for saved read-back or exact queried IDs.

Installed framework guidance supports native components and scoped services. New UI factories aren't business writers; operations own validation, actor, transaction/lock/revision, supported retries and after-commit effects. No new per-service interfaces/repository wrappers, generic claim/editor engine, global Alpine registration, copied native save body or automatic relationship persistence introduced. Existing image lifecycle unresolved I3a remains owned by Images and is not accidentally "fixed" by this Catalog source audit.

## 6. Readiness, uncertainty and next decision

The full requested Catalog source/planning scope has been reconciled into CR1; this does not establish runtime correctness, actual data population, measured capacity or production readiness. Source-frontier limits: actual legacy mixed-role rows, unbuilt new taxonomy/display/projection/return/code/comparison classes, actual MySQL concurrent new writes, browser lifecycle/layout/accessibility, private runtime backup completeness and resolved future master drift. These require the specified approved implementation/preflight/acceptance, not guessed assertions. Existing-code verification remains coordinated with its approved owner.

**Genuine product decision:** confirm comparison same-leaf saved facts versus broader cross-Group/configured variants. Recommendation same-leaf actual configured card facts first; K6 only gated. Tree/tag default remains configurable and nonblocking. No repeated D1/D2/D3/flat-list/no-preview/transfer questionnaire. Conditional mixed-role data requires a decision only if evidenced. Native extension feasibility, exact breakpoint/token values, query plans and safe test harness are engineering work, not additional product approvals.

Full CR1 independent review is required before claiming approval-ready complete plan. K1 (orK1+K2) can be selected for a bounded first production implementation approval after that review; C0 optional prototype alignment is not a universal prerequisite. No Catalog coding/migration/dependency/security/merge/push is authorized now. This report's source inspection and test-assertion review are not executed tests; existing historical runtime reports stay separately dated.

## 7. Refreshable evidence inventory

The source and planning path/hash tables below record the actual inspected baseline material for comparison. Method-specific findings above distinguish directly traced behavior from supporting/preserved sources. Secrets, environment values, private DB/media and unrelated task data are excluded. Hashes only establish identity; they do not prove runtime conformance or full line-by-line review of every supporting file.


| Source/support path | SHA256 |
| --- | --- |
| `app/Models/Group.php` | `7f18ee6365f6015bf68efa68d9edd58ebb15c7f28aea21053b6e74a04d02124c` |
| `app/Models/Product.php` | `7836247289a751b00254fb88c882dbf1e760a38cc38777e347b69447cc17b320` |
| `app/Models/GroupFilter.php` | `eee97c82c0e4d7106bc610a40895ec0e85a361c23d9ea91591e92254a14d31a5` |
| `app/Models/SubGroup.php` | `e9fc7eeb8f1d88ec1dd9f230263bff1f742ff8c75e650386fb511d39d89adaaa` |
| `app/Models/Configurator.php` | `dec59cae118d78cc7d9652f763b8113168b0435b5058afe0f79c95ed608c02fd` |
| `app/Actions/SaveCatalogGroup.php` | `0fcfe99d839f45877895426f40e47b5b9d6dcf1c9aee09d523d8a30b4eea5d93` |
| `app/Actions/SaveCatalogProduct.php` | `0c2e980a81da95cc3a343392760ef646d6af63906ec66e01cc57b8fc42ec6fd0` |
| `app/Actions/SaveGroupSettings.php` | `9968e4b8c1b63dedb4ef3617f672d5f8f05a78d60ae67261b1b7b2bc24c91e63` |
| `app/Actions/AssignConfiguratorGroups.php` | `31413b1b5104ac8b3c7fce9d757a51e9659e81a44ba6c3570ddb346b5cd7b3e0` |
| `app/Actions/ImportCatalogProducts.php` | `a82542b483bf826efd2e5de53b281642ca41391fc4c96acbcc822de3e18d2df9` |
| `app/Services/CatalogAvailability.php` | `0011c118e2069ade605ac793b32996f7aa8a63b38664a52178fae4a7730ef5aa` |
| `app/Services/CatalogIntegrity.php` | `3e746007eb48ba2007c467ec07e183902ecc8c752364a72db55c6c82290b96d4` |
| `app/Services/BuildCatalogSnapshot.php` | `67ad60970128244e2da8dcbbc9c64c76e73493cfc768db4c9e4e878c87c6d762` |
| `app/Services/CatalogSnapshots.php` | `b993eeeb846eeec761daf53948330fef21ed6ea47cc97c0b8da13ca761b961b8` |
| `app/Services/CatalogSnapshotMatcher.php` | `7edbf377dc33b2cc739f7bf3425dd916ec44e13c242bbd908a3649e3266eab1b` |
| `app/Services/CatalogFilterReconciler.php` | `443062ee17d6b8428583ab1d1ac26e20904b1159d03d9b22feb981accba95a80` |
| `app/Services/CatalogCards.php` | `835a3d93779a3e14d977745d9f6bf6995dbc950d06d3579967347854b6f9ef96` |
| `app/Services/CatalogCardDisplay.php` | `2e14be35833578d596f7a99b4db41d4577bec3f5a9272088c036794a4960b948` |
| `app/Services/CatalogPolicy.php` | `edc40e4cb240a9e4be9d4960b310d13076570c3306c689ccd7cbf27bad62ff3d` |
| `app/Services/CatalogRevisions.php` | `61145b9e62e67779525cf7b6a61101569bd8e36c34827c0327104e5a67863b5e` |
| `app/Services/ProductFields.php` | `e5828fb515e2078b1c0829a80eeac0286f3631863ef8a910285f896a463b414f` |
| `app/Services/ProductFieldQuery.php` | `00f4f8c0956fa20a8ac110476556fc63187fdbd6d421c52e592068a10b78942a` |
| `app/Services/ProductEditorData.php` | `06932140290930922abccaf43ffa2bbc56857af82d0345a536886160fab80402` |
| `app/Services/ConfiguratorDefinitionLoader.php` | `85fa06f7c10007502f40fe19af4f70e21401bf96f79c47e1704559253d7aead6` |
| `app/Services/ConfiguratorDefinitionCompiler.php` | `fa46f8e62880ef969468c9bf3ee1f341bcaf46047bf72344d1933090e2b500d4` |
| `app/Services/ConfiguratorEngine.php` | `27c579afead8c7cca049404d0d7a4d46a910368f460af0a51795ee6bd1e74406` |
| `app/Services/ConfiguratorPolicy.php` | `d586f78bf012b2f7905233b01ac320113fd38391f2e1bd35f0f66fa06dc4ce52` |
| `app/DTO/ConfiguratorEvaluationInput.php` | `0ae199d13a0b26f21c4b8ef4f41df67de947addc5a6a283ee73b76d0ec49dded` |
| `app/DTO/ConfiguratorEvaluationResult.php` | `be531d8994164d5ad5e76452a2872caa73bc3d8200c339bc0a06648b5f89b440` |
| `app/Http/Controllers/CatalogDatasetController.php` | `52072f9f7e1b0d433e869f5e77bd737325261de2eb42099bb745d373526892e8` |
| `app/Livewire/Catalog/Index.php` | `6eb87b3a79b06a4f705bce003f2f0cdee3da98f12611c5bdfe61b4047c3f947d` |
| `app/Livewire/Catalog/GroupShow.php` | `10aacaf668e58615bd13865cb292255ac6d4e42815699bccd6435e0f23681df5` |
| `app/Livewire/Catalog/ProductShow.php` | `5be46033840116b6b8bdaa4c43d341aa35047aabf7e85ab81b7f210e81169259` |
| `app/Livewire/Catalog/ProductConfigurator.php` | `7196f6965745e4a9d67d2a28d1b18b6b50ef4130fec8b3357e0cb1b62ea4c837` |
| `app/Filament/Resources/Groups/Schemas/GroupForm.php` | `0dc92b846d11649c2fb49564f4b134d702b378c5f90b5dc6b677ae1500a39322` |
| `app/Filament/Pages/AdminAppearance.php` | `3c328e53be3ee160344be4ef1a198f4b0840b5cd496343992db36cd4c89758cf` |
| `app/Providers/AppServiceProvider.php` | `b9290184a9b7fdb03d774560576c9a700f04da2138b15a2c0e0c41557cdaa339` |
| `resources/js/catalog-filter-engine.js` | `2f961f24c8a5c73c9f13bc9b1d4806edb37190be7610cae0360b2a742334a274` |
| `resources/js/catalog-filters.js` | `6cc7a4b17f8fc0478768336cf9bf9752e4d4a549ca824431f80b36ca6e050ea2` |
| `resources/js/catalog-shell.js` | `52feba6816e9c0e2b6acbe9e3b02e13195da459cd03a770fc4de0ececc529981` |
| `resources/js/app.js` | `8104c6753714c33a5fa5224ba1a95c460e0f7f47a54ab13a9dd42ee8c0230be9` |
| `routes/web.php` | `3459a8c18eb6f86264803c38756a6cc44a0b53cda0c0dd00fafbc6240d576e30` |
| `tests/TestDatabaseSafety.php` | `6614f73e6b3ae950a6d8108cf814f74f5573cc0cd47390d91dfc7aa717bc0421` |
| `phpunit.xml` | `97daac6089f7a496cf9d78012e4dc984286ce26f0de1c77523f5877c8917afe6` |
| `phpunit.mysql.xml` | `7d0594389943a1df1b63867a8fa58d2cae4b773df79bc7617052cfbb478dfeb9` |
| `composer.lock` | `fdb7008e770a6fa8f0805d9a981b1f5ecdefb1b09c1d1babcff7fc8013ea8820` |
| `package-lock.json` | `b3ae967a3026a8f7843a67c7e2c1e8e4683b775c0cd7803bcb56766edce9bc77` |
| `resources/views/livewire/catalog/group-show.blade.php` | `0a37722e500b9a9b027b58dde04dcc39060c6e205a095f5a40e3d7b2547ee2c2` |
| `resources/views/livewire/catalog/index.blade.php` | `d24655078a4090955da17689ba31c768f2a4e8b40f50ea8393fbe809e55ccde0` |
| `resources/views/livewire/catalog/product-configurator.blade.php` | `f7758d93fe86d19ebb1084de458504f34cfe4e80cb021a4dbd935f9390426dcd` |
| `resources/views/livewire/catalog/product-show.blade.php` | `eab795e2e7697a2edf61d127ee654c3a606f0ee605b6b9d956cbaea847e0d8cb` |
| `resources/views/components/catalog/product-facts.blade.php` | `5f8728aba13398431d7fda29f4d3e0405503f435fcb33742f5cd6694e9a7c058` |
| `resources/views/components/catalog/group-tree.blade.php` | `095f20754cced1fe3c5afaeb184be4ef01df441e94dd143c9e256dab13853506` |
| `resources/views/components/catalog/breadcrumbs.blade.php` | `a021a006502dab0a292df6baf3f36523dc19552492cb6de340ca0b1db40cfa67` |
| `resources/views/components/catalog/configuration-fields.blade.php` | `e88113142f235c1f4799bb1d596d305ab739dd261fc5fbacf6d257d7b5278185` |
| `resources/views/components/catalog/configuration-result.blade.php` | `2b322411e970c10fe3692006158868ec9c65d8cc52bea6959a7f557cdcfb9f68` |
| `resources/views/components/catalog/card-chunk.blade.php` | `f7b1c39c92d6b0c62407c8c635102f485aafd474bf7edfcf34007233a7d74b4c` |
| `resources/views/components/catalog/product-card.blade.php` | `530e60246fee69ec01d8f2ee87a72a08d6b25c47d36c5dc656a8c8630c1edb6d` |
| `tests/Feature/Catalog/PublicCatalogTest.php` | `c4f36a41704481929e7678e62956e6c17461d1691e144e412fb71498e0ead5f7` |
| `tests/Feature/Catalog/GroupSettingsTest.php` | `e2a3ac7260e9909dd305e6ae1a1088d5ef6858efa33c7853eee06db11bce51ca` |
| `tests/Feature/Catalog/CatalogSnapshotTest.php` | `fe1239281edb1fbf49802acb54bacbd305635c63202a127da877cbfbb36c4162` |
| `tests/Feature/Catalog/ProductConfiguratorAvailabilityTest.php` | `7142c766fb9de3538c74dec666c238577b0ac17672b1dbe43071e0357e86a93e` |
| `tests/Feature/Catalog/ConfiguratorRuntimeTest.php` | `510e89f86d7cb799d680456234536bd48806255f97cbc2fd1cefb1e204238a48` |
| `tests/Feature/Catalog/CatalogImportTest.php` | `a985c4ddd80fa8cfeab424e998a6a09bef927eb23f7d7e30da567030b2c99b24` |
| `tests/Unit/CatalogFilterEngine.test.js` | `c3493d65beb5b0819e2fc484356cb51c2e9071747e8706d7501d7cd62c957da6` |
| `tests/Unit/CatalogFilterTransport.test.js` | `f48e7a714194fa74a14cfe19dae4dd7bcd9bce285c7eb661f00b30662bfcd301` |
| `tests/Unit/CatalogFilterRecovery.test.js` | `ffbf75cb082e94b9d08a457fbe209144e7e868b0d106e2dc71227cd6e3ff6da9` |
| `tests/Unit/CatalogShell.test.js` | `fb7fa03f9cfcde4c20316316f2ccb98b61f6d51109036425d970696574de49b3` |

| Historical planning/contract input | SHA256 |
| --- | --- |
| `docs/catalog/CATALOG_REDESIGN_UX_AND_SETTINGS_PLAN.md` | `9c66dcbf1120ad72fd89de7fb9be85e2c51134ebb0f02124649f04d5f90a5855` |
| `docs/catalog/CATALOG_AND_IMAGES_PREMERGE_PLAN.md` | `773506acc26208b8c7d0b972e504817839bfc08daaa6b940742128f645f65d3a` |
| `docs/catalog/CATALOG_AND_IMAGES_PLAN_AUDIT.md` | `149e34765bb5b9f358b354d3462426ea99add001139f48fc994c632ac308434c` |
| `docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_IMPLEMENTATION_PLAN.md` | `1a17540928213ab0c8bd14fe7fb4e44f9665541d78dc87d7979b118b448c38ca` |
| `docs/catalog/IMAGE_ASSIGNMENT_PHASE_1_EXECUTION_BLUEPRINT.md` | `ab7174e56a85cbaddad186ee589fd8b5b0b3d62bdee176a4924a951a50c4c340` |
| `docs/catalog/IMAGE_ASSIGNMENT_POST_CONSOLIDATION_PLAN_AMENDMENTS.md` | `d612d850655f4d084edf240d8e15e63c559967b5f8258513b31f012ac2c2650c` |
| `docs/catalog/PRODUCT_DATA_IMPLEMENTATION_PLAN.md` | `4548b6b9cc591e89072526861db0c5e511361a3ee4fecdb2273a54fc86493586` |
| `docs/catalog/CARD_APPEARANCE_IMPLEMENTATION_PLAN.md` | `af19a25508bef0aca79b16c949b66f15cf1b2ae4ead29bb392ca983bc5fb2022` |
| `docs/catalog/contracts/PUBLIC_CATALOG.md` | `e400a8385a4b08653b521d25175c37a512a27c9b6c3f0343ceb66a7c3af56d14` |
| `docs/catalog/contracts/FILAMENT_ADMIN.md` | `1580b585733280b4bb753c441fab0c4a3bc65b41764db49a10d6286625c90f1e` |
| `docs/catalog/CROSS_WORKTREE_RECONCILIATION_2026_10_29.md` | `68a624f83bac1a5036b3eac40ce7fb2103cccea2d04cb4185d262de2623ad62e` |

Additional decisive source: `app/Providers/Filament/AdminPanelProvider.php` SHA256 `8a023cb875618d973fbd8de9f3a77f2468dfb54abc5c0fd175639d8fe1bf1dfa` (explicit Page registration / ordinary capability preservation).

Additional decisive source: `app/Filament/Resources/Groups/GroupResource.php` SHA256 `c34611e4a3f6394b65e33377daa9b3a82e36c797b92ee1f0211b59fd58d45b48` (explicit Page registration / ordinary capability preservation).

Additional decisive source: `app/Filament/Resources/Products/ProductResource.php` SHA256 `fedfe479f0622490bd8464a688ee8216d16f2e6499b97366f8c23f524827acfb` (explicit Page registration / ordinary capability preservation).

Additional taxonomy boundary: `app/Models/Value.php` SHA256 `bbdc94890380291c17e6cf25338f6663f7e6c1ca99815d0b91db7b28df5c4d8d` (flat Master Value JSON tags, not the new Group taxonomy).

Additional taxonomy boundary: `app/Filament/Pages/ManageTags.php` SHA256 `6444814da4ebde2c710a1ca57f9ee107f0f62387161e9ee097240711dae19824` (flat Master Value JSON tags, not the new Group taxonomy).


## 8. Full independent CR1 review and CR2 corrections

Independent task Cross-worktree integration audit reviewed the complete package, all24 requirements and84 source/support hashes, not only the latest semantic delta. Four planning gaps were returned: standalone K1 deletion depended on later K2 storage; tree expansion needed a separate bounded request/stale contract; some finite control defaults were unspecified; code restoration needed an explicit remembered/pending-choice policy. CR2 specification §13 records exact corrections/tests. K1 no deletion, K2 complete reference protection, named tag fingerprint, explicit children request/version/50-row contract, full criteria/tag defaults and reset-on-success/no-reset-on-failure code memory are now specified. These are engineering clarifications, not reopening C1–C3. No app defect/implementation/runtime pass is inferred; corrected final package needs bounded closure review against the completed whole-package audit.


CR2 closure refinement: the review confirmed the four corrections and requested two exact details within CR-R03/04. Product page defaults are now literal compact_header/medium. K5 explicitly consumes URL code once on fresh entry/intentional history/explicit Load, never on ordinary render/choice/reevaluation; later choices push actual code or remove unusable code, refresh uses replace, rejected restoration preserves full prior runtime, and a scoped URL adapter/test is included in K5's allowlist. No Product-tag coupling or state/definition historical fidelity promise is introduced. Final correction hashes are returned for bounded closure review; no source audit or runtime suite is restarted.


## 9. Independent closure receipt — metadata only


Independent reviewer **Cross-worktree integration audit** returned final CR2 closure on 29 October 2026 after its complete CR1/CAT01–CAT24 review and bounded correction recheck. CR-R01–CR-R04 are CLOSED at source/planning-review level: standalone K1/K2 deletion dependency/fingerprint; separate bounded/versioned tree requests; exact control defaults; canonical hidden-memory/intent and one-time URL consumption. No further substantive planning correction was found. Reviewer reported 671 inventoried source files unchanged and whitespace checks passing. This receipt changes metadata only and does not restart review.

Exact reviewed versions before this receipt append:

| Artifact | Reviewed SHA256 |
| --- | --- |
| Complete plan/execution CR2 | fe5e9cf91aa24d0fd7bfa156b1c474120de6d2b69be92ac5d1a4f957005ecb0d |
| Complete source audit CR2 | 4444475eb3b4f736ca21c81ad9cc9b4e2cdab177082d3e80f522f1560b5ceedb |
| Combined authority banner R10 | c1283a1e125a63df453b454fc89dfba484865f6257f55a63c90304660fbf2bb7 |
| Reuse assessment R10 | 9fb3f1ea83312d46da9a40c6be2702e82d644b7061c1a7daee3683ea82bf2f75 |

**Current handoff:** K1 alone orK1+K2 is ready to present for explicit implementation approval, with its isolation/test/engineering gates. K6 comparison boundary is the only actual product decision, gating K6 alone. Neither this verdict nor the receipt authorizes Catalog code, migrations, dependency changes, runtime tests, merges/pushes or security work. Audit-led approved existing-code verification stays separate; future new-code/browser/MySQL/capacity acceptance remains unrun. Images source/contracts and retained sites/worktrees/data stay protected.


## 10. Bounded actual-main dependency refresh — 30 October 2026

Groups One–Two repairs now exist on actual main as a verified **uncommitted 13-path overlay** atop 8e1f640, not as a new commit. See [combined specification §28](CATALOG_AND_IMAGES_MASTER_EXECUTION_SPEC.md#28-actual-main-groups-one–two-baseline-and-dependent-plan-refresh--30-october-2026), complete Catalog plan §15 and reuse §17. Their exact source/build manifest SHA256 is `4e17be16866da04181cbbdca5421232b6f82a199fd84ecfe09bcf1c2c9b5aad6`; this owner freshly matched all 771 actual files, including four built assets. Package versions were reread; current AGENTS applies and .ai/rules is absent. Original §§1–9 remain source/planning history at their recorded baseline and CR-R01–04 remain closed.

Inspected six changed application responsibilities and current test guard/XML plus unchanged Catalog owner/revision callers. No new structural defect or class extraction is justified: retain the existing inclusion-default capability, authoritative graph/owner writers and selective appearance reader; adapt only future provider registration by union; keep CatalogPresentation distinct. Preserve the current named test-profile union. Existing repair regressions are preservation requirements, not a substitute for unbuilt Catalog/Images tests.

This bounded refresh runs no app tests, browser, DB or runtime operations. Accepted Groups evidence/limits are attributed to the coordinator in §28, not independently rerun here. Changed clauses require independent delta review; old closure is not automatic approval of this amendment. Clean feature execution is still blocked until an authorized committed baseline or separately approved verified overlay materialization exists and implementation is released. No feature restart, Git/source/provider/test/site/media/security changes follow.


## 11. Accepted-K1 impact-to-plan source re-audit — K2-D1

Read final independent execution/downstream audit `/private/tmp/audit-k1-master-final-20261030.json` (SHA256 `3376c89bd00bee9076adf8a6039a67de2c829c134ace9baffb26bfd188dfe979`) and local commit receipt; freshly matched all16 actual source blobs on Master62f3eb97b5b80dc9575276fef16441286bc2bd68. Both feature owners here acknowledge the report; coordinator owns Writing relay. No source/DB/runtime/test execution ran for this re-audit.

Scoped traces checked actual CatalogNavigation/SaveGroupTags models/schema and exact fingerprint/lock/validation; GroupForm::settingsState→SaveGroupSettings full/narrow raw serialization; pure CatalogSnapshots cache/get boundary; CatalogDatasetController ETag→GroupShow SSR/loadCards→catalog-filters.js freshness; existing native Page/HasSchemas and provider bindings/hooks; Pest partition. This is the relevant producer/consumer boundary, not a whole project/style/security audit.

Three concrete K2 corrections are incorporated: actual CatalogNavigation must join real displayVersion wiring and current tests; current Group writer projection must preserve fresh raw overrides/unknowns under optional new fingerprint semantics; revision-only dataset/client refresh must recognize presentation-only changes without changing Product engine choices. Complete plan §17 names exact34 affected paths/tests and unchanged protected dependencies; combined§30 maps all nine downstream rows and five independently closed finding IDs; reuse§19 records retain/adapt/build/defer from actual K1 rather than copied reference source.

Activation is explicitly gated: main has not run K1 taxonomy migrations; K2 adds settings table. Only named approved additive pending K1 three + K2 one migration may precede live new-schema consumer/provider/client activation, after actual target/history/schema check; no fake probing/feature switches, no inferred main authority from K1 synthetic reset. K2 TEST lease/capability/lifecycle and native synthetic QA are distinct future resources; none is opened by this plan. K1 RR proof is retained as tested-engine evidence, not arbitrary-isolation/main-data verification. No open material K1 finding or new K1 UX question. K6 scope and real K7 image producer dependency retain their stage-specific gates.

K2-D1 subsequently passed bounded independent plan review; accepted passive S4 backend and its consumer delta are recorded below. Neither prior plan review nor the safe checkpoint is full K2 runtime acceptance. After actual stage release, preserve direct-Master/no-worktree/one-writer/conditional-local-commit protocol and existing pending work. No feature restart, main migration, test reset/QA/site/secret/env/provider/source edit or Git operation occurred here.


### Accepted S4 source/consumer delta after §11

Read final independent closure `/private/tmp/audit-k2-safe-backend-final-closure-20261030.json` SHA256 `4e0af0d1628fca1c678c88696558139f4a0b019f3260063a450b183f5ce9cc52`; freshly matched accepted source8. Required NOOP/PURE/RECOVERY findings are closed. The observer reviewed retained actual SQLite83/576 logs and RED/GREEN source manifests, not a new engine/browser rerun. Master HEAD remains62f3eb9; passive K2 source is uncommitted/unwired and Main migrations are unrun.

Scoped source trace confirms `CatalogCards` constructs a name/id Group stub without raw result_settings. It must not be passed as the actual own-override Group; future card composition needs a fresh authorized Group/raw settings read coherent with snapshot revision/shared presentation. CatalogDisplay likewise uses actual selected Group and one server category context. `effective(Group,array,?array=null)` is pure and requires context for nonempty references; `resolveCategoryIds(shared,authorized materialized batch)` resolves one bounded union. SSR/dataset retain snapshot waits outside transactions and public envelope/ETag; Navigation's version-only producer does not need fake Group presentation. Provider/writers later retain actual scoped identity and fresh locked references rather than cache/context write authority. Complete Catalog §17.7 and combined§30 map each of these four consumer rows to discriminating future checks.

The actual cache distinguishes typed same-V payload corruption while ignoring top-level object order; only structural normalization is cached and reference validity is freshly resolved. No-op comparison ignores only top-level JSON object order, preserves nested list order/types, and causes zero SQL/publication. These accepted discoveries are retained as invariants, not new current-app incidents. Only source preparation of current-backend MySQL cases + exact Pest exception is next; all five production files and other accepted tests stay unchanged. Actual engine/deletion/reference/full consumer/native proofs and all Main/QA/resource gates remain distinct and open; no whole audit or UX restart.
