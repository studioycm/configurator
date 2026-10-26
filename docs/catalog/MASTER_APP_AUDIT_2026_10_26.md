# Master application audit — 26 October 2026

**Review complete; findings and proposed checks only. No application fixes are authorized or implemented by this report. No push.**

## Scope, baseline and evidence

Audited local master `05ed6a93d52eec229270ff336170c16c68f42d9b` in `/Users/studioycm/Herd/configurator`, clean at the source-review boundaries. Its application/test/config/dependency inputs match the reviewed legacy-retirement tree `222cf85`. The later audit/handoff documentation commit does not change those inputs.

The human requested a full current-app review, an additional pending Images/catalog alignment track, and a handoff personally authored by the original task after consulting **Plan catalog configuration and UX**. The owner consultation was completed before that handoff was written. See [the handoff](CONSOLIDATION_HANDOFF_FOR_CATALOG_IMAGES_REVIEW.md) for feature status and dependency contracts.

Inventory: **254 tracked app files, 131 resources, 82 database files, 18 config files, 3 bootstrap files, 2 route files and 145 test files**. The architecture lane enumerated 31 Actions, 36 Services, 15 DTOs and 22 current Models. Reviews traced critical entrypoints and caller chains across persistence/concurrency, security, UI/state, catalog/compiler/runtime, queues/import/export and test quality; this is not a claim that every possible execution path was exercised.

Guidance applied: Laravel development architecture, Laravel Daily structure/Eloquent/permissions/production-readiness/queues/testing audits, Filament security/development, Livewire, Fortify/Horizon where relevant, test best practices and bounded performance/UX guidance. Each lane used `gpt-6.1-sol` high. Native locked source and version-scoped Boost documentation were used where framework lifecycle mattered. PHP 8.4.27; Laravel 13.35.0; Filament 5.9.0; Livewire 4.4.7; Fortify 1.41.0; Horizon 5.50.0; Pest 5.3.0; League CSV 9.28.0. `.ai/rules` was absent.

Dependency-advisory/upgrade work, CI expansion, remote/production operations, feature completion and broad code reorganization remain separate. No live account occupancy, production settings or private application rows were inspected. PHPStorm was reachable but Configurator was not open there, so no Configurator IDE-inspection pass is claimed. Herd runtime-context capture was off; no fresh browser/throughput/heap measurements are claimed.

## Findings at a glance

Four newly identified current-app issues supplement the previously documented application-security work. Passing suites do not close untested entrypoint or file-byte failures.

| ID | Priority | Evidence/status | Finding |
| --- | --- | --- | --- |
| SEC-05 | P1 | Existing source weakness reconfirmed, conditional on enabled Fortify TOTP | Native panel login does not enforce that account's Fortify second factor. |
| SEC-03 / F03 | P1 | Existing signing primitive reconfirmed; cross-owner disclosure impact conditional on private output presence | Import FileUpload metadata can sign client-substituted paths on the shared private disk. |
| SEC-02 / F02 | P2 | Existing source weakness reconfirmed | Retained 2FA component snapshots do not enforce current password confirmation on sensitive actions. |
| SEC-04 / S01 | P2 | Existing authority-design weakness; exploitation depends on a vacant allowlisted address | A changeable email is administration authority. |
| RL-01 | P2 | Implemented CP6; storage/transaction/concurrency regressions pass | Generic canonical exports can commit success accounting when files were not written. |
| RL-02 | P2 | Implemented CP6; real canonical file roundtrips and quoting modes verified | Generic canonical CSV escaping can merge records/corrupt fields; native XLSX reading shares the escape assumption. |
| SA-01 | P2 | Implemented CP5; authentic retained-child HTTP regressions pass | An existing ProductConfigurator child can render fresh facts after Product/Group availability is withdrawn. |
| SEC-06 | P2 | Existing response distinction/missing limits reconfirmed; volume impact unmeasured | Recovery enumeration and direct resend abuse controls remain incomplete. |
| SEC-04 / S02 | P3 | Existing configuration-contract defect; fails closed for multi-address/list configuration | Horizon compares plural allowed-emails configuration to one scalar address. |
| AD-01 | P3 | Implemented CP7; exact-text presentation regressions pass | Valid exact text `"0"` is replaced by fallback labels or an empty marker. |

Priorities indicate review urgency, not implementation approval. P1 source weaknesses are not claims that an incident occurred or that the relevant account/disk conditions exist in production.

### Later execution disposition — 26 October 2026

The human approved CSV A/readiness B and the [execution specification](POST_CONSOLIDATION_REPAIRS_EXECUTION_SPEC.md), then directed completion in the main checkout before local integration. Root implemented CP5, independent CP7, then CP6; tests and bounded independent review pass. Current evidence/dispositions are in specification section10 and journal CON-31. XLSX browser saving remains explicitly unverified because the client blocks that download; real HTTP/file tests verify its bytes. Optional queued streaming remains unavailable pending an effective worker/capacity gate. Security remains deferred until requested; step8 belongs to the catalog task.

**Security backlog:** SEC-02–06, the scoped Horizon issue and the separate package/served-asset security track remain open and deferred **until the human explicitly asks for them**. Completing these repairs or the later feature audit does not start security work automatically. Keep the [security plan](SECURITY_FIXES_IMPLEMENTATION_PLAN.md) and [security specification](SECURITY_FIXES_EXECUTION_SPEC.md) as unimplemented proposals; revalidate their baseline/advisories when requested. Existing guards/authorization stay in force. Step 8's Images/catalog plan alignment remains with Plan catalog configuration and UX against the final verified baseline.

## Current-app findings and meaningful verification

### SEC-05 — native panel login and Fortify TOTP

`app/Providers/Filament/AdminPanelProvider.php:64` enables native `login()`/password reset without Filament MFA providers or a Fortify bridge. Installed `vendor/filament/filament/src/Auth/Pages/Login.php:67–163` uses its own provider pipeline and guard `attemptWhen()`. Fortify's separate `AuthenticatedSessionController.php:85–92` inserts its TOTP challenge. Sharing the web guard does not run that second pipeline.

**Impact:** an eligible administrator's password can authenticate through native panel login without their configured Fortify TOTP challenge. Actual user TOTP state was not queried.

**Proposal:** use the existing SEC-05 bridge design; reject retained native Login snapshots as well as route entry. Do not treat a GET redirect as sufficient. **Needed tests:** every login entrypoint, password-only rejection/challenge, TOTP/recovery success and a previously issued native snapshot. No live session attack was performed.

### SEC-03 — private upload metadata signing

Application fields are in `app/Filament/ConfiguratorRuleDataActions.php:36–39` and native import reached by `app/Filament/CatalogDataActions.php:17`. Neither has per-field/global path protection. Installed `BaseFileUpload.php:71,172–215,915–950` defaults protection off and exposes metadata generation for string state. `storeFiles(false)` and `previewable(false)` do not remove that method.

The default private local disk (`config/filesystems.php:31–38`) is shared and serve-enabled. Native exports use `filament_exports/{export_id}`. A signed storage URL uses signature authorization; native export-owner download authorization is a different path. Native `DownloadExport` and `DownloadImportFailureCsv` **do** enforce ownership; they are not the missing check.

**Impact:** source establishes a path-signing primitive. Another owner's private export must exist/be reachable for the proposed disclosure; no such data was queried. This is not traversal beyond the configured disk root.

**Proposal/tests:** existing SEC-03 FileUpload path protection, with only legitimate explicit exceptions if demonstrated. Exercise the actual metadata RPC using another user's generated private export, valid temporary uploads, both import surfaces and normal owner-download denial. Coordinate future global protection with pending Images' stronger actor/host/property/source provenance; do not weaken it with a filename-only exception.

### SEC-02 — recent confirmation on retained sensitive snapshots

Initial settings entry uses `password.confirm` (`routes/web.php:34–43`), but sensitive actions in `Settings/TwoFactor.php:57–67,130–134` and `TwoFactor/RecoveryCodes.php:26–30` call Fortify operations without checking confirmation freshness. Installed Livewire `PersistentMiddleware.php:13–23` omits `RequirePassword`; no application wrapper is registered. The inspected middleware utility also does not turn an arbitrary returned JSON denial into an abort.

**Impact:** a still-authenticated user holding an earlier valid component snapshot can perform sensitive MFA operations after confirmation expires. No guest/arbitrary-user bypass is asserted.

**Proposal/tests:** existing SEC-02 persistent wrapper that throws on denial. Capture a real HTTP component snapshot, expire confirmation, then test ordinary/JSON updates, disable/regenerate operations, guests and fresh-confirmation success. Initial-page redirect tests do not cover this lifecycle.

### SEC-04 / S01 — mutable email as administration authority

`User::canAccessPanel()` (`app/Models/User.php:68–74`) compares email against two fixed addresses. `manage-catalog` delegates to that admission (`AppServiceProvider.php:75`). `Settings/Profile.php:31–54` allows an authenticated user to change their email under uniqueness rules; clearing verification does not affect the panel check.

**Impact:** if an allowlisted address becomes vacant, an existing ordinary account can claim it and obtain administration. Registration is disabled and current account occupancy was not inspected. An administrator changing their own email also loses access.

**Proposal/tests:** the existing protected `is_admin` design and preservation of current administrators; no ordinary mass-assignment/profile route may change it. Assert email changes never grant/revoke administration and submitted role fields cannot elevate a non-admin. This is a distinct implementation decision, not permission to migrate users during this audit.

### RL-01 — generic canonical export publication/accounting

Value, Attribute and Attribute Value actions use native export jobs (`CatalogDataActions.php:24–29`; native `CanExportRecords.php:445–447`). Native `PrepareCsvExport.php:68–69` ignores header-write failure. Native `ExportCsv.php:101–129` increments counters inside a transaction and ignores page `put()` at 128. The local disk has `throw => false`; false storage return can therefore coexist with committed successful counters. `ExportCompletion.php:61–85` derives completion/downloads from those counts; `CsvExportContentGenerator.php:18–29` concatenates existing pages without detecting a missing page. Native `CreateXlsxFile.php:100–105` also ignores the workbook write result.

The checked XLSX binding in `AppServiceProvider.php:36–41` applies only to Rules. The selected consolidation repair protected that separate Rules export pipeline, not every canonical exporter.

**Proposal:** at the app integration boundary, make false header/page/workbook writes explicit failures and align accounting/publication/download readiness. Do not edit vendor or assume SQL rollback restores files.

**Needed tests:** real generic export chain with a fake disk returning false for one page while headers succeed; assert counters, completion and actual downloaded records together. Add header/workbook failure cases and successful multi-page control. `CatalogDataExchangeTest.php:193` checks row arrays; line 271 onward fakes export dispatch, so those tests do not exercise publication failure. No application storage-failure injection ran in this audit.

### RL-02 — generic CSV/XLSX exact-text roundtrip

Native `ExportCsv.php:79–80` creates a League CSV Writer and sets only delimiter. Native workbook reading (`CreateXlsxFile.php:65–67`) also sets only delimiter. Native ImportAction readers at `120,169,199,563` retain the same default escape. `CatalogExporter::textColumn()` protects spreadsheet formulas but does not change trailing backslashes. The repaired Rules writer/readers explicitly use an empty escape.

**Actual isolated reproduction:** using installed League CSV 9.28.0 and these defaults, three in-memory rows including trailing-backslash fields came back as **two**, with field/row boundaries merged. Empty escape on both writer and reader retained all three exactly. A separate properly quoted comma-plus-trailing-backslash input produced **one row instead of two** under the default reader. These are library-byte tests with generated sample strings; no app job, database import, browser export or retained data was executed.

**Proposal/tests:** define and demonstrate the canonical CSV contract across writer, preview/header reading, import, rejected-row output and XLSX consumption. Show before/after examples before changing accepted legacy/nonstandard escape behavior under D06. Changing only the writer is insufficient for quoted input. Test actual CSV bytes, multiline/quote/backslash boundaries, Unicode/formula/apostrophe protection, record counts and actual XLSX cells. Existing generic row-array assertions do not prove this contract. Evidence: `generic-csv-byte-roundtrip.json` in the private audit directory.

### SA-01 — Product child availability revalidation

`ProductShow::render()` (`app/Livewire/Catalog/ProductShow.php:23–28`) calls `CatalogAvailability::assertProduct()`; that requires an active Product and visible Group ancestry (`CatalogAvailability.php:52–59`). The independently updated child has a locked Product ID, but `ProductConfigurator::render()` (`:75–84`, query at 77) fetches fresh Product facts without the same check. Loader `productInput()` returns an unavailable diagnostic/null definition; the child view still renders properties, parts and description (`product-configurator.blade.php:37–47`).

**Trigger/impact:** an authenticated viewer who loaded a visible Product can retain its child snapshot. After deactivation or Group hiding, a child-only refresh can expose newly saved facts even though reopening the parent page is denied. This is not arbitrary-ID tampering, a guest/tenant bypass or an unauthorized write. Confidentiality severity depends on whether hide means withdraw access; the parent already enforces denial.

**Proposal/tests:** use the existing availability assertion after the child's fresh query, preserving administrator Preview's separate renderer. Test visible success, deactivate/ancestor-hide plus changed facts, independent HTTP child refresh denial, and admin Preview continuity. No runtime snapshot reproduction was attempted.

### SEC-06 — recovery responses and modest abuse controls

Fortify reset routes (`vendor/laravel/fortify/routes/routes.php:64–70`) lack application request throttles; `PasswordResetLinkController.php:41–47` selects distinguishable existing/missing-account responses. `Settings/Profile.php:62–74` directly sends verification notifications without the separate Fortify resend endpoint's throttle.

Existing login/TOTP limits and password-broker cooldown remain healthy but do not cover all these entrypoints. Volume, mail-provider behavior and incidents were not measured.

**Proposal/tests:** existing SEC-06 generic recovery response and modest temporary request/resend limits, preserving cooldown and real recovery success. Assert equal observable responses for existing/missing accounts and direct Livewire resend thresholds. No actual mail was sent.

### SEC-04 / S02 — Horizon's plural email contract

`HorizonServiceProvider.php:31–41` compares one email with `config('horizon.allowed_emails')`; `config/horizon.php:9` currently supplies a scalar environment default. **One exact scalar works.** Multiple addresses or an array fail closed. This is not a universal outage or authorization bypass. Installed Horizon intentionally permits local-environment admission after route authentication; its source default here is disabled. Effective deployment/exposure was not inspected.

**Proposal/tests:** align with the chosen protected admin authority. Cover enabled/disabled, guest/admin/non-admin and distinguish vendor local-environment behavior. Do not infer production access from local defaults.

### AD-01 — exact `"0"` text in review/presentation

The compiler accepts `"0"` and uses null-aware overrides (`ConfiguratorDefinitionCompiler.php:67,87,101,112,142,446–453`). Several presentation branches instead use PHP truthiness:

- `ConfiguratorEditorChanges.php:230,531,534,616,683`: new Rule headings, Attribute/Option descriptions and compiler-error/change labels.
- `ConfiguratorPreviewInspection.php:49`: Mapping set labels in explanation causes.
- `ProductShow.php:28`: Product page title.
- `resources/views/livewire/catalog/tag-values.blade.php:51`: a sole exact tag `['0']` appears as the empty marker in before/after assignment review.

Stored values, compiler and tag identity are preserved; the operator can see inconsistent labels while reviewing the same data. Existing numeric-text coverage uses `"001"`, which misses PHP's special falsey string `"0"`.

**Proposal/tests:** bounded explicit null/blank or empty-list fallback at the actual presentation sites. No compiler, DTO or presenter-framework rewrite. Assert inspected and prepared labels/reference/error descriptions, Mapping set causes, actual Product title and rendered tag-review before/after values retain `"0"`. Existing null/blank fallback must remain. No application regression was run in this read-only audit.

## Historical recommendation reconciliation

The original **32 entries are recommendations/candidates**, not 32 unresolved defects. Preserve original evidence and the execution-spec disposition map.

| IDs | Current reviewed disposition |
| --- | --- |
| R01 / R02 | Narrow Group patch and selected lock graph repaired; retain writer/participant invariants and bounded MySQL claims. No claim that deadlocks or process loss are impossible. |
| R03 | Narrowed/retained: focused creation/add/order operations exist; remaining UI draft adaptation runs inside the authoritative locked writer. No unsafe competing writer was substantiated. |
| R04 | Explicit actors/comparisons repaired; distinct representation retained. AD-01 is a narrow new display error, not a signing/conflict bypass. |
| R05 / R06–07 | Shared tag field and explicit finite table-host contracts repaired/healthy. No additional universal trait or helper split justified. |
| R08 | Necessary native picker/search/reset adapters retained; upgrade characterization remains useful, with no demonstrated current defect. |
| R09 | Finite authorized membership separated from drawer UI; exact property keys and SQL IDs remain different projections. |
| R10 | Common mechanics reused where contracts align; distinct Rule/inclusion/tag/full-record lifecycles remain justified. |
| R11 / R12 / R13 / R14 | Retain healthy status orchestration, ProductFields authority, live vocabulary/distinct prepare tests and eagerly loaded Rule summaries. No class deletion or universal presenter is justified. |
| R15 / R16 / R17 | Appearance rollback memo repaired; legacy cast/consumer retired; complete idempotent Alpine registration repaired. |
| R18 | Source supports measuring duplicated tag vocabulary/usage aggregation, not a current-slowness claim. Measure query counts, decoded volume, aggregation, payload and interaction separately before request-level reuse. No index/cache change is proposed without evidence. |
| O01–O07 / O09 | Optional organization/type/test-placement ideas. Existing folders, static factories, concrete Services, root enums and scoped wiring are valid. No mass reorganization recommended. |
| O08 / V01 / V03 / V04 | Package ownership/advisories and CI expansion remain separate/excluded work. No new audit or implementation claim. |
| V02 / V05 | Effective-target guard repaired; existing focused boundary tests are valuable. Add only approved, meaningful architecture guards; do not manufacture filename tests. |

## Pending Images/catalog alignment track

These are distinct from current-master defects. Exact pending status is P0–P6 source present; P7–P10 unfinished; browsing redesign remains a separate static prototype.

| ID | Status and obligation |
| --- | --- |
| IMG-A01 | Definite combined-source incompatibility: Images lacks `HasWorkspaceTable`, while master profile methods require it. Adopt the contract; do not loosen master types. No combined runtime test ran. |
| IMG-A02 | Semantic merge obligation: keep Configurator-before-Groups planning/current owner guard alongside pending image fingerprint logic. No pending-worktree race was reproduced. |
| IMG-A03 | Merge image factories into master's once-per-Alpine registrar/export union, preserving both startup timings. |
| IMG-A04 | Deliver with A01: combine asset-search/custom pagination with column migration, failed restore preservation and compact profile limits. Validation remains host-specific. |
| IMG-A05 | Future P9 contract gap: 500-entry input bound before deduplication, plus the query limit, versus all-results catalog/501-card behavior and query budget. Repeated pointers also need coverage. Not a P6 regression. |
| IMG-A06 | Planning chronology drift: older committed master snapshots and later P6 worktree amendments must be reconciled without discarding either history. |
| IMG-R01 | Owner-requested recovery semantics review: current intended/baseline state does not prove this request committed/rolled back. Review both outcome labels and the resulting draft reset, success event/notification, retry language and navigation. Keep native full-form `review_required` distinct; do not mandate a new durable receipt platform. |
| CB-P01 / CB-P02 | Open prototype choice/preset/chronology reconciliation and below-six caption mismatch. No live-master defect inferred. |

All 88 pending file hashes and 52 prototype file hashes were rechecked unchanged. Eight direct overlaps include two planning files; a clean textual merge alone is insufficient. [The handoff](CONSOLIDATION_HANDOFF_FOR_CATALOG_IMAGES_REVIEW.md) gives the actual contract map and acceptance agenda.

The feature owner subsequently reviewed that handoff and confirmed these obligations, with the recovery-result/consumer and input-count clarifications incorporated. It recommends a verified combined P6 checkpoint before P7/P8, then P9/P10. Prototype precedence and any changed automatic recovery continuation require product decisions. This consultation did not execute tests or independently recertify all current-master findings. The Cross-worktree integration audit found no factual conflict with the newer Git/closeout record.

## Healthy boundaries and verification ledger

- Central `SaveConfiguratorDefinition`/compiler/loader/engine ownership, canonical versus inclusion IDs, raw Product JSON types/exact keys and signed capture/fingerprint envelopes remain coherent.
- Owner/canonical/context/Group/Product lock planning, complete selections/chunks, fresh-owner revalidation and revision batching have substantive source/test coverage.
- Native import distinguishes row validation from infrastructure failures, retries known rollback only and does not replay unknown commits. Rules approvals remain spent after uncertainty.
- Rules export page identity, checksums, storage reconciliation, after-commit publication and failure-aware workbook generation are materially stronger than the generic path in RL-01/02; keep them.
- Custom table profiles scope by account/component/owner/variant; deferred filters, hidden-field search, native picker forwarding and column-width reset behavior are protected.
- Lazy Configurator tabs, request/generation/revision checks, aborted stale catalog fetches and event/observer teardown are present. All 20 application JS modules were source-reviewed.
- Route auth, disabled registration, current authoritative write Gates, owner-membership checks, bounded Rules parsing and escaped Blade remain useful protections. No additional supported SQL-injection or source-XSS path was established.

Actual merged-result verification ran **before** this read-only source audit: PHP **1346 / 8814** (105.64s), specialized MySQL **62 / 460** (11.82s), JS **253**, inert safety **24 / 49**, routes **30**; all passed. Subsequent changes were documentation only. These counts do not claim that the default SQLite feature suite ran on MySQL. Physical transaction tests explicitly exercise level-zero commits; a savepoint-only test is not substituted for that evidence.

The accepted real browser check used human native file selection/Import/download because automation failed. It verified the simple two-row CSV case and representative Master Values/Attribute/Preview behavior. It does not cover RL-02 special CSV bytes, every Product/combination, real retained HTTP MFA snapshots, all fault-injection paths or a new combined Images runtime.

Process-loss redelivery/at-least-once limits and unknown commit outcomes remain explicitly bounded. No durable generic operation/chunk receipt platform, exhaustive physical gap/index schedules, production delivery/load, mobile/RTL/BFCache/memory matrix or fresh dependency advisory scan was executed.

Evidence: `/private/tmp/configurator-full-master-audit-20261026` contains baseline/code hashes, Images/prototype preservation manifests and the CSV byte reproduction. Prior merged/QA evidence remains under `/private/tmp/app-consolidation-evidence/legacy-retirement-20261026`. The dedicated QA runtime is retired; its source worktree and verification artifacts remain.

## Decision and repair grouping

Before coding, review these coherent groups:

1. **Administration and sensitive account/file boundaries:** reconcile the existing SEC-02–06 plan, including its native entrypoints and retained snapshots. Resolve admin authority once; downstream features keep using `manage-catalog` rather than copying identity policy.
2. **Canonical Data Exchange reliability and exact bytes:** RL-01/02 share the native export/import adapters. Agree accepted-format examples before changing escape behavior; tests must read actual files/cells and completion outcomes.
3. **Catalog availability after child updates:** SA-01 needs a precise hide/deactivate access rule and a real child-update regression; preserve administrator Preview inspection.
4. **Exact-text presentation:** AD-01 is an independent bounded fix with focused rendered/prepared-output assertions.
5. **Images compatibility before combined acceptance:** IMG-A01–04 and plan chronology, then phase-specific P7/P8 delivery and the P9 bound/query decision. Prototype navigation/visual decisions remain their own review.

These groups express prerequisites and shared code dependencies, not a newly approved implementation order. The source audit does not justify a broad structural rewrite. Further fixes, feature work, integration and pushing need their respective human approval.


### Post-audit repair disposition

SA-01, RL-01, RL-02 and AD-01 have approved implementations and verification recorded in [the follow-up specification](POST_CONSOLIDATION_REPAIRS_EXECUTION_SPEC.md#10-execution-evidence--steps-57) and journal CON-31. The sections above retain the original audit evidence/proposals; unrun statements there describe the original audit snapshot. They do not supersede the later implementation results. Original security findings and Images/catalog integration obligations remain separate deferred work. No complete new architecture or security remediation is implied.
