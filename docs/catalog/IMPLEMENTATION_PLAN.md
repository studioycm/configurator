# Catalog and configurator implementation plan

> Updated 2026-09-24: T01–T12 are implemented in the isolated rebuild checkout. T13 local rehearsal is complete; production inspection/content and release authorization remain outstanding. Maintain the checkboxes and evidence log when continuing.

**Goal:** deliver a visible catalog from imported D060 Products, then the rebuilt shared configurator engine and Filament management.

**Architecture:** Laravel services/actions own queries, validation, persistence and evaluation. Class-based public Livewire pages and Filament administration consume those operations. Product discovery and configurator evaluation have separate state and behavior. Use the existing app structure and dependencies.

**Spec:** [guidelines](IMPLEMENTATION_GUIDELINES.md), [user decisions](DECISIONS.md), [data/engine contract](contracts/ENGINE_AND_DATA.md), [public contract](contracts/PUBLIC_CATALOG.md), [admin contract](contracts/FILAMENT_ADMIN.md). Read the guidelines once and the relevant contract before each task. Research is supporting evidence, not another plan.

**Reviewed stack, 2026-09-23:** PHP 8.4; Laravel 13.33.0; Filament 5.8.4; Livewire 4.4.6; MySQL 8.0.46; Pest 5.2.1; Boost 2.9.1. Recheck installed versions before new API assumptions.

## 1. Start and authority

**Current state:** T01–T12 complete in `/Users/studioycm/.codex/worktrees/catalog-rebuild/configurator`, branch `codex/catalog-rebuild`. The imported public catalog, Group filters, Product runtime and shared management are implemented. [Open the rebuild catalog](http://configurator-catalog-rebuild.test/catalog); `configurator.test` still serves the preserved original installation. Continue at the remaining T13 release boundaries, not T01. D060 remains unassigned after QA by user instruction; production configuration content has not been supplied.

Execute M1–M4 in order unless the new session's user narrows scope. Prepare M5's isolated rehearsal and release evidence. Active/deployment database switching, pushing and deployment follow their corresponding instruction; they are not automatic consequences of completing this plan. Do not restart the research or historical decision questionnaire.

The source workspace was on `upgrade/laravel13-filament5`, HEAD `7f752536e7cddcfdcfaf6f4b27e829d1a64bf886`, with uncommitted work. Preserve prior edits to AppServiceProvider, ConfigEngineDemo, CatalogGroup/OptionRule resources, ProductProfilesTable and TableConfigurationStandardsTest; preserve selected skills/boost.json and these docs. Preserve unrelated `herd.yml`, `.DS_Store` and `public/vendor/` content.

Confirmed user behavior is fixed. The handoff selects these **technical defaults**, without relabelling them as user decisions: CLI-first whole-file atomic import; initially read-only imported Product facts; one discovery URL snapshot/explicit paginator; hidden-preset cleanup; page-size cap 100; Preview & Test placeholder under the later user amendment; unassigned duplicates; inactive-rule cycle checks; base-presentation fallback for priority ties; empty initial policy-override allowlist. Follow them coherently. Record a necessary mechanism change while preserving the contract; ask only for a consequential behavior, retention or access conflict.

## 2. Sequence and source ownership

| Milestone | Tasks | Outcome |
| --- | --- | --- |
| M1 — Catalog foundation | T01–T05 | Visible public shell, imported D060, real Group/Product flow and useful admin lookup |
| M2 — Discovery | T06–T07 | Filters/SubGroups, counts, immediate cards, pagination/Back and Group settings |
| M3 — Models and engine | T08–T09 | Canonical codes/local inclusions and one deterministic evaluation result |
| M4 — Authoring/integration | T10–T12 | Working matrix/rule management and public configuration; interactive Preview deferred |
| M5 — Transition rehearsal | T13 | Verified retained data, MySQL behavior, matching release and rollback evidence |

Execute T01→T13 in order. Later improvements must not delay early visible progress. Data/engine §§2–4 owns schema/IDs/operators/DTOs, §§6–7 owns all source columns/import behavior. Public §§3–6 owns Group settings, vocabulary, URL/precedence/counts/queries. Admin §§2–7 owns resources, fields/actions, staged writes and authorization. Plan §7 owns migration partition and release boundaries. The guidelines collect research-derived instructions.

## 3. Execution and review loop

For each task: inspect sibling code/current diff; read its contract; add meaningful tests with independent expected results; establish the behavioral gap with the narrow test; implement; rerun; inspect the relevant UI. Pure copy/layout changes need visual review, not tests that mirror markup. Generate new PHP types through Artisan; rename existing families coherently. Preserve existing tests and user changes.

Record changed files, exact commands/results and browser scope in §9. A missing test file, zero tests, written test or inspected tutorial is not a pass. Run `vendor/bin/pint --dirty --format agent` after PHP edits. Commit only within the execution session's scope; do not blanket-stage/reset unrelated files.

Five required failure cases: owned blanks/conflicting identities (T04); malformed Back with precedence (T06); exact-case codes/forged local IDs (T08); empty intersections/hidden restoration (T09); aggregate failure/unvisited Settings tabs (T10–T12).

## 4. Task checklist

### T01 — Checkout, private inputs and database roles

**Inspect:** AGENTS.md, rules/skills, Composer/npm versions, phpunit.xml, tests/Pest.php, database/cache config, Git diff and private inputs. **Produces:** a reproducible implementation environment.

- [x] Read guidelines; resolve installed versions and generator help. Use an isolated checkout for changing the migration baseline. A worktree does not copy uncommitted work: carry the relevant reviewed patch, docs and independent skill files without modifying/resetting the source workspace.
- [x] Make the normalized CSV/audit available from [Decisions §5](DECISIONS.md#5-product-data-and-normalization). Verify CSV SHA-256 `5a8adbd4439bb1f020ab625d84fa6fde3a62ce67fbd74f94cf2b2e3b19758f72`, 501 data rows and 67 positions. Keep raw inputs private.
- [x] Resolve the isolated Herd site and distinguish old/current, new development and disposable test databases. Record roles without secrets. Do not switch the active site or reset a source database.

**Exit:** checkout, preserved changes, input verification and database roles recorded. No behavior test is needed for this inventory.

### T02 — Visible public catalog shell

**Create:** `app/Livewire/Catalog/Index.php`, `resources/views/livewire/catalog/index.blade.php`, `resources/views/components/layouts/catalog.blade.php`. **Modify:** `routes/web.php`. **Test:** `tests/Feature/Catalog/PublicCatalogTest.php`. **Contract:** public §§2/7.

- [x] Add public `/catalog`, named `catalog.index`, using class-based Livewire; preserve home/account routes. Render an honest empty/preparation state using current app assets, without invented Products/counts.
- [x] Verify public access and intact account routes; inspect desktop/narrow rendering on the actual Herd site.

**Exit:** a visible catalog entrance before the complete engine/editor. Real records connect in T05; the shell is not a completed catalog.

### T03 — Fresh schema, guarded tests and access boundary

**Create:** `app/Models/{Group,Product,Configurator,GroupFilter,SubGroup}.php`, factories/seeders and ordered migrations; `app/Actions/TransferRetainedApplicationData.php` (initially only the reviewed local account transfer); `phpunit.mysql.xml`, `tests/bootstrap-mysql.php`, `tests/Feature/Catalog/MySql/CatalogSchemaTest.php`. **Modify:** `app/Providers/AppServiceProvider.php`, panel registration, test configuration. **Contract:** data/engine §§2/8; admin §§2/7; plan §7/8.

- [x] Establish the MySQL guard before any refresh/migration test: testing environment, driver and exact disposable database allowlist; unsafe/missing targets fail without fallback. Exclude MySQL-only cases from default SQLite discovery.
- [x] Apply the 17-retained/19-replaced migration partition on the implementation branch. Create minimal empty Configurator before Group's nullable FK, then Group/Product and empty GroupFilter/SubGroup metadata tables. T06 consumes that metadata; T07 exposes its admin editor. Preserve old code/migrations/database as the archive.
- [x] Implement tree/leaf constraints, legacy identity, JSON casts and Product's `name` accessor; SQL uses `product_code`. Define `manage-catalog` from existing panel eligibility. Exclude obsolete domain resources/pages from registration before any fresh-schema panel is used.
- [x] Prove migration order, distinct legacy/internal relationships, correct accessor/SQL behavior, cycle/non-leaf rejection and admitted/non-admitted writes. Make retained local account data available for authenticated development with original IDs/hash/encryption compatibility; read the source without modifying it or fabricating production credentials.

**Exit:** compatible fresh schema/panel on isolated databases, with current database untouched. SQLite is not proof of MySQL constraints.

### T04 — Repeatable, atomic D060 import

**Create:** `app/Services/CatalogImportParser.php`, `app/Actions/ImportCatalogProducts.php`, `app/Console/Commands/ImportCatalogProducts.php`, `tests/Feature/Catalog/CatalogImportTest.php`, `tests/Feature/Catalog/MySql/CatalogImportTest.php`. **Contract:** data/engine §§6–7; decisions §5.

- [x] Implement the exact positional map and canonical-CSV parser. Preserve strings/blank slots and skip repeated HTML decoding. A raw-source normalization port must match the audited corpus.
- [x] Expose `catalog:import-products` with dry-run default and explicit apply, source/map/parent-metadata hash validation, admitted-actor authorization and optional supplied parent metadata. Record the implemented command signature/help in §9.
- [x] Preflight every row/identity/Group/code conflict; one lock and one transaction resolve Groups first and apply source-owned merges. Retain app-only keys, stable IDs and absent Products. Produce a bounded private outcome report.
- [x] Verify 501 first-import/unchanged-reimport records; then independent corrected/blank/absent/conflicting-identity fixtures. Force a late failure and concurrent attempt on MySQL: zero partial writes or wrong-identity overwrite.

**Exit:** D060/2144 and 501 Products with all three JSON fields, reproducible report and proven reimport/failure behavior. Missing parent metadata leaves D060 unattached; no invented legacy parent.

### T05 — Real Group/Product flow and useful management

**Create:** `app/Livewire/Catalog/{GroupShow,ProductShow}.php`, matching views, `tests/Feature/Filament/CatalogAdministrationTest.php`. **Modify:** Index/routes/layout; rename Group/Product resource families and affected factories/tests. **Contract:** public §§2/7; admin §§2/6.

- [x] Connect exact named Group/Product routes. Branches show actual children; leaves show stable immediate cards/pagination; Product pages show actual `product_name`, code and fixed facts.
- [x] Keep unfinished configurator/specification/asset areas honest. No unfinished engine call, demo slug or first saved-configuration fallback. Unassigned Products remain useful.
- [x] Build Group hierarchy/assignment basics and Product lookup by code with escaped read-only facts; numeric Part1…Part28 ordering, no arbitrary JSON editor or dead Import button.
- [x] Test correct scope/labels, 404s and navigation; browser-check root→leaf→actual Product, narrow layout, keyboard navigation and preserved 7xl dialogs.

**Exit M1:** demonstrable imported public catalog and useful admin lookup. Filters follow in M2; actual configuration follows in M4.

### T06 — Discovery reducer, queries and URL history

**Create:** `app/Services/{CatalogDiscovery,CatalogFilterReconciler}.php`, `app/DTO/{CatalogDiscoveryState,CatalogDiscoveryResult}.php`; `tests/Unit/CatalogFilterReconcilerTest.php`, `tests/Feature/Catalog/CatalogDiscoveryTest.php`, `tests/Feature/Catalog/MySql/CatalogDiscoveryTest.php`. **Modify:** GroupShow/view and useful repeated Blade components. **Contract:** public §§3–8.

- [x] Implement exact version-1 `discovery` shape/action methods, one URL binding and explicit Eloquent paginator. No WithPagination, async discovery actions or second Table state owner.
- [x] Initialize D060's seven source filters and canonical labels/value order from statically inspected conf.js via `database/seeders/D060FilterSeeder.php`, scoped by legacy Group identity. Then implement newest-first replay, atomic preset precedence, hidden same-property cleanup, resets, self-excluding counts and bounded Group-scoped queries over the full dataset.
- [x] Pin the independent fixture: pressure25→Flange→size1 gives B; Flange→pressure25→size1 gives C. Test preset variants, `0`, blank/null/type precision, preset-only properties and all-page counts.
- [x] Browser-prove one-step Back/forward, valid restored page, malformed/stale address-bar repair, rapid clicks and Back during delayed responses. Try native behavior first; use only the contract's scoped replacement adapter if the observed repair fails.
- [x] Measure actual MySQL queries/time/payload/EXPLAIN before adding indexes/caches. Prove no per-button queries or full Product/JSON hydration.

**Exit:** controls, counts, cards, notices and URL agree; zero counts remain clickable; both the independent and observed 36-versus-9 sequences preserve precedence. The metadata schema already exists from T03; T07 adds its management UI.

### T07 — Group filters, SubGroups and settings

**Create:** `app/Actions/SaveGroupSettings.php`. **Modify:** the GroupFilter/SubGroup models/factories from T03 where required, Group resource schema/actions, CatalogAdministrationTest and discovery tests. **Contract:** public §§3/5; admin §6.

- [x] Manage ordered filters/labels/value order and one-property preset sets as a single validated Group-settings transaction, preserving IDs and T06's canonical source setup.
- [x] Implement shared result-setting keys/defaults/cap and visitor allowlist. Preset-only properties work without visible filters. Do not infer presets from source `sub_group` text.
- [x] Verify singleton, 10/16, force-hide, newest-conflict, clear/reset, unchanged save and stale metadata. Twenty-three Products at10 produce10/10/3; sizes2/1 preserve access to all records. Admin changes affect the next public evaluation.

**Exit M2:** complete managed discovery independent of configurator rules.

### T08 — Canonical models and reference-safe writes

**Create:** canonical/local/rule models and migrations/factories exactly in data/engine §§2–3; `app/Services/ConfiguratorDefinitionCompiler.php`; definition/rule/condition/effect DTOs in `app/DTO`; `app/Actions/SaveConfiguratorDefinition.php`; `tests/Feature/Catalog/ConfiguratorDefinitionTest.php`, `tests/Feature/Catalog/MySql/ConfiguratorIntegrityTest.php`. **Modify:** Configurator stub and affected old-model coverage.

- [x] Build canonical rows before local inclusions; add default-membership FK after both inclusion tables, then rule/condition/effect/mapping constraints. Verify real MySQL DDL.
- [x] Implement the typed definition compiler and complete dependency/cycle validation before the save operation depends on them. Then implement authorized scoped validation, deterministic reference/owner locks, explicit repairs, stable-row diffs and duplicate remapping with canonical reuse/no copied assignments.
- [x] Preserve stored default through reorders; keep display/code/option order separate. Prove `Aa`, `aa`, `00` coexist while duplicate exact code, spaces, invalid length/non-ASCII and forged owner/Attribute IDs fail.
- [x] Prove removal denial before writes, correct new staging-default resolution, valid partial mappings/overlapping targets and complete-permutation ordering.

**Exit:** domain writes protect identities/references without depending on a UI. Do not add historical code retirement/versioning.

### T09 — Shared compiled engine

**Create:** `app/Services/ConfiguratorDefinitionLoader.php`, `app/DTO/{ConfiguratorEvaluationInput,ConfiguratorEvaluationResult}.php`, `tests/Unit/Configurator/ConfiguratorEngineTest.php`. **Use:** T08's definition compiler/DTOs. **Replace coherently:** `app/Services/ConfiguratorEngine.php`. **Contract:** data/engine §§4–5; guidelines §5.

- [x] Load coherent current Product→Group→Configurator definitions and compile typed conditions/effects plus all choice/applicability dependencies. Reject cycles, including inactive stored dependencies.
- [x] Evaluate database-free to settle the entire DAG, then presentation/completeness/code. No two-pass demo repair or parallel manifest evaluator.
- [x] Pin A→B→C, missing-negative/OR, product/context-only activation, empty intersection/latest choice, fallback/hidden restoration, outgoing effects, presentation self-reference versus self-hide, ties and independent code/UI/default order; test All/specific contexts.
- [x] Reconcile current assignment/definition changes and reject stale disabled inputs. Empty/invalid definitions withhold code. Adapt retained legacy capability tests deliberately, without deleting them or preserving superseded semantics.

**Exit M3:** one deterministic result containing legal/hidden/disabled state, selections/memory, presentation, diagnostics, completeness and code.

### T10 — Shared-definition management and workspace

**Create/rename:** exact resource families in admin §2; `app/Filament/Resources/Configurators/Pages/EditConfigurator.php`, `app/Filament/Resources/Configurators/RelationManagers/AttributesRelationManager.php`; `tests/Feature/Filament/{CanonicalDefinitionsTest,ConfiguratorAdministrationTest}.php`. **Contract:** admin §§2–4/7.

- [x] Build canonical forms/usage views and five tabs: Overview, Groups, Attributes, Rules, Preview & Test (user amendment, 2026-09-24). Overview has a scoped inline form; Groups displays assigned/available leaves, Product counts, links and direct Assign/Unassign. Keep Duplicate/local inclusion/Code order actions over domain writes; no broad Save or Custom tab.
- [x] Implement leaf assignment without silent stealing through Configurator's assignment action; duplicate remaps local references and remains unassigned. Preserve shared/local labels and stored defaults.
- [x] Verify resource and direct custom authorization, shared-local isolation, duplicate integrity and Overview save with Attributes/Rules unvisited. Start with ordinary rendering, not speculative deferred editing schemas.

**Exit:** current shared definitions and local setup are manageable with clear edit scope.

### T11 — Rule editor, matrix and priority

**Create:** `app/Filament/Resources/Configurators/RelationManagers/RulesRelationManager.php`, `app/Filament/Forms/Components/MappingSetsField.php`, `resources/views/filament/forms/components/mapping-sets-field.blade.php`, `tests/Feature/Filament/ConfiguratorRulesTest.php`. **Contract:** admin §§4–5/7.

- [x] Build typed root-AND/one-level groups, mapping sets and advanced effects. Reject extra/deeper/unknown payloads. Exclusions remain secondary.
- [x] Stage all cells/sets and validate the complete resulting definition before writes. Preserve surviving IDs and failed draft input; driver/target changes require explicit repairs, never silent clearing.
- [x] Implement owner-locked authorized complete-permutation drag/keyboard reorder; highest row has highest priority. A native pre-reorder hook is not the transaction boundary.
- [x] Test partial coverage/overlapping targets, duplicate/empty sets, wrong IDs, late failure rollback, stable reload and cycle errors. Browser-check keyboard labels, scrolling, error focus and narrow-screen usability.

**Exit:** saved rules drive the engine; no independent database-writing matrix cells.

### T12 — Public configuration; Preview & Test deferred

**Create:** `app/Livewire/Catalog/ProductConfigurator.php` and view, `tests/Feature/Catalog/ConfiguratorIntegrationTest.php`. The previously implemented saved-preview adapter remains unmounted; interactive Preview is deferred by the later user amendment. **Modify:** ProductShow, legacy demo/registration and affected tests. **Contract:** public §7; admin §7; data/engine §5.

- [x] Resolve trusted Product/current assignment on every public interaction through the shared loader/compiler/evaluator. Keep Preview & Test as a static placeholder only; Product selection, evaluation and testing controls are deferred by the later user amendment.
- [x] Render single-choice toggle/select inputs, exact hidden/disabled results, fallback notices and separate Product/Configuration codes. Territory/Application each default All and remain single-select.
- [x] Prove current saved edits affecting the next public evaluation, hidden restoration and disabled-choice bypass denial. Unassigned Products stay valid. Preserve the earlier adapter tests as future implementation coverage; the Manage page must not mount the live Preview component.
- [x] Remove remaining active POC configuration/parts/specification/attachment dependencies coherently; retain archive and adapt tests. Browser-check the full catalog→Product→configurator flow and saved admin edits.

**Exit M4:** real Product configuration; Preview & Test remains an honest placeholder until its functionality is resumed.

### T13 — Isolated transfer and release rehearsal

**Modify:** T03's `app/Actions/TransferRetainedApplicationData.php` for the inspected retained package-data map. **Create:** `app/Console/Commands/TransferRetainedApplicationData.php`, `tests/Feature/Catalog/MySql/RetainedDataTransferTest.php`. **Verify:** fresh migrations and §9 evidence. **Contract:** plan §7; data/engine §8.

- [ ] Inspect intended deployment source read-only and finalize retained table/column/file/job mapping. Local counts do not establish production content.
- [x] Rehearse clean migration, retained-data transfer and CSV import in a separately named local MySQL database; verify retained administrator eligibility, IDs/FKs/auto-increments/counts/checksums/reimports/files.
- [ ] Supply approved production definitions and verify the actual deployment login/content. The local rehearsal intentionally has no assigned Configurator or invented production codes.
- [x] Execute MySQL uniqueness/CHECK/FK/JSON/rollback/concurrency and relevant regression checks; complete the full-suite check under repository guidance and actual browser journey. Record missing/failed evidence honestly.
- [x] Prepare and rehearse matching local code/database activation and reversal through process-only overrides, archive queue/storage data and restore-test backups while preserving the current installation.
- [ ] Finalize production write freeze/delta handling, queue/storage disposition and matching release revision after production inspection; present evidence before any active/deployment switch.

**Exit M5:** release and rollback evidence, not automatic deployment. Forge deploy-on-push is disabled.

## 5. Generators and cross-task interfaces

Use these verified forms, rechecking help after version changes. Omit a generator when the type already exists; never use generated seeds as canonical client content.

```sh
php artisan make:model Group --migration --factory --seed --no-interaction
php artisan make:livewire Catalog/Index --class --no-interaction
php artisan make:class Services/CatalogDiscovery --no-interaction
php artisan make:command ImportCatalogProducts --command=catalog:import-products --no-interaction
php artisan make:filament-resource Value --panel=admin --record-title-attribute=label --no-interaction
php artisan make:filament-form-field MappingSetsField --no-interaction
php artisan make:test --pest Catalog/CatalogImportTest --no-interaction
php artisan make:test --pest CatalogFilterReconcilerTest --unit --no-interaction
```

Cross-task interfaces use the contract DTO names and exact state shapes. GroupShow exposes `selectFilter(string $propertyKey, string $value)`, `selectSubGroup(?int $subGroupId)`, `clearFilters()`, `resetAll()`, `goToPage(int $page)`, `changePageSize(int $perPage)`. Local ConfiguratorOption IDs are not canonical Option IDs. Finalize service method signatures with their owning task and record them in §9 before adapters depend on them.

The evaluator takes compiled definition plus typed evaluation input and returns ConfiguratorEvaluationResult without queries/writes. Loader owns persistence reads; compiler owns structural validity/dependency order; actions own writes; adapters own validated interaction/presentation. A settings action merges its allowed changes into the current definition under lock; unvisited sections are never treated as submitted empty arrays. Do not introduce repository wrappers to avoid defining a small explicit API.

## 6. Inputs and deferrals

| Input | Needed for | Continue meanwhile |
| --- | --- | --- |
| Real starter parent label/identity/placement | Complete client parent→D060 hierarchy | Import/browse D060 without invented parent; test generic tree using labelled fixtures |
| Initial SubGroups | Client-specific presets | Build/test presets and expose seven known ordinary filters |
| Canonical meanings/manual codes/rules and Territory/Application choices | Populated client configurator | Implement engine/editor with valid synthetic test fixtures; keep public Product unassigned until reviewed content exists |
| Deployment retention/files/jobs/activation details | Real release/cutover | Complete local code and isolated rehearsal; preserve production |

No missing content authorizes production guesses or treating POC duplicates as canon. Defer parts/specification redesign, assets mapper, RFQ/saved configurations, definition/code history, session persistence, stages and Custom functionality. These do not block the current infrastructure.

## 7. Fresh database: exact release boundaries

The selected fresh-database strategy simplifies the new domain baseline; it does not remove the need for transfer/rehearsal.

**Proposed migration-file layout:** keep the ordinary `database/migrations/` path for the new release. On its implementation branch, replace the obsolete domain migration family with the new domain migrations; preserve the old files in the archived matching code revision. Do not leave a second runnable copy nested under the new migration path, invent ledger entries, or expect this new baseline to upgrade the old database. The old installation stays on its old revision/database until the deliberate switch.

Read-only file inventory on 2026-09-23 found **36 existing migration files**, partitioned as follows. This table classifies schema files; it does not authorize transferring every row from their tables.

| File family in `database/migrations/` | Count | Proposed treatment |
| --- | --- | --- |
| `0001_01_01_*` users, cache and jobs | 3 | Retain infrastructure schema; transfer durable account data only under the retention map |
| `2025_04_22_*` workflows | 3 | Retain package schema; inspect model-class references before copying deployment rows |
| `2025_09_22_145432_add_two_factor_columns_to_users_table.php` | 1 | Retain account schema and verify encrypted-data/key compatibility |
| `2025_11_29_133515_*` through `2025_11_29_133522_*` | 8 | Retain the Bolt schema and its constraint migration in original order |
| `2025_12_14_011829_create_notifications_table.php`, `2025_12_16_192934_create_media_table.php` | 2 | Retain generic package schema; notifications/morph references and any files need explicit data classification |
| `2025_12_04_113203_*` through `2025_12_04_113213_*` | 11 | Archive old domain creates, including saved configurations/parts/specifications and FileAttachment |
| `2025_12_14_000001_add_attachable_columns_to_file_attachments_table.php`, `2026_03_26_112723_add_config_profile_to_catalog_groups_table.php`, all six `2026_03_28_*` | 8 | Archive old-domain follow-up alterations; they must not run against the new baseline |

This leaves **17 retained schema files and 19 replaced old-domain files**. New migration ordering follows the core annex: empty Configurator before Group FK, Group before Product/filter/preset records, canonical definitions before local inclusions, default-membership FK after both association tables, then rule/condition/effect/mapping references. Generate migration files sequentially, verify their sorted dependency order, and inspect the complete dry-run migration list before running the disposable rehearsal.

- Retain users/auth and required infrastructure/package schemas. Transfer durable users with original IDs/password hashes and required account fields. Check application-key compatibility for encrypted data without exposing secrets.
- Retain required non-domain Bolt/workflow/notification data in dependency order. Their current local empty/populated counts do not authorize dropping deployment data.
- Build new catalog/configurator tables from reviewed fresh migrations. The import supplies Groups/Products; approved definition setup supplies actual Attributes/Options/Rules. POC seeds are not the new truth.
- Archive the old domain DB, files, matching revision and required uncommitted local changes. If a POC asset is deliberately retained later, map morph types and IDs and verify its physical file/conversions; never copy its old owner ID blindly.
- Reconcile transient jobs before switching. Sessions/cache/reset tokens are not durable business transfer data by default. Preserve any required operational recovery information in the old archive.
- Never rewrite an existing installation's migration ledger to look fresh. Never run `migrate:fresh` on the current/deployment connection. The separately named new/test database has its own ledger.
- A production write freeze or reviewed delta transfer, verified backup, matching application revision/database configuration, and explicit rollback are required before the real switch. None has been executed by this task.

## 8. Verification configuration and commands

Run the narrow task file while developing. These groups apply once their files exist:

```sh
php artisan test --compact tests/Feature/Catalog
php artisan test --compact tests/Feature/Filament
php artisan test --compact tests/Unit/CatalogFilterReconcilerTest.php
php artisan test --compact tests/Unit/Configurator
vendor/bin/pest --configuration phpunit.mysql.xml tests/Feature/Catalog/MySql
vendor/bin/pint --dirty --format agent
```

**MySQL harness is new work in T03.** Exclude `tests/Feature/Catalog/MySql` from default SQLite suite discovery. Dedicated bootstrap must reject missing/unsafe configuration before Laravel/Pest RefreshDatabase runs: testing environment, MySQL driver, exact allowlisted disposable database and no cached configuration resolving another connection. Use database-restricted credentials where available. Add a second early MySQL test-base guard against accidental direct invocation. Do not infer safety from a database name merely containing `test`.

After affected cases pass, complete repository full-regression guidance (`php artisan test --compact`, user-run where AGENTS directs) and actual browser checks. During renames, adapt old-model tests for agreed behavior and report remaining failures; do not delete/skip coverage to get green. Existing historical 71-test results cover earlier polish, not this rebuild. A PHP component test cannot prove browser history/timing/focus.

## 9. Progress and evidence log

| Task | Status | Files / commands/results / browser scope / next input |
| --- | --- | --- |
| T01 | Complete | Isolated managed worktree `/Users/studioycm/.codex/worktrees/catalog-rebuild/configurator`, branch `codex/catalog-rebuild`, base `7f752536e7cddcfdcfaf6f4b27e829d1a64bf886`. Reviewed 8-file patch, eligible docs and independent skills copied; source `herd.yml`, unrelated files and current database preserved. CSV hash verified, 501 rows / 67 positions; audit hash `18293e15a79f099f3cda40cff26f9fd8cb88755d48c0cf82092a1dbc9e200c7d`. Current: `configurator_local`; new empty development: `configurator_catalog_dev`; disposable: `configurator_catalog_test`, MySQL 8.0.46 on local port 3307 with separate database-restricted accounts. SQLite default remains `:memory:`. Private credentials stay ignored. Baseline `php artisan test --compact`: 71 passed / 555 assertions. PHP CLI 8.4.23; package versions match reviewed stack. Isolated Herd link and Boost-resolved `/catalog` URL verified. |
| T02 | Complete | Added `Catalog/Index`, catalog layout/view, named route and public-access test. Red: public route returned 404. Green: `php artisan test --compact tests/Feature/Catalog/PublicCatalogTest.php tests/Feature/HomePageTest.php tests/Feature/DashboardTest.php tests/Feature/Settings`: 15 passed / 40 assertions; Pint and `npm run build` passed. Actual Herd browser shell checked at 1440x900 and 390x844, including keyboard skip link; no invented Product/count. |
| T03 | Complete | Replaced 19 domain migrations (archive in original revision/workspace and private scratch), retained 17 package/infrastructure migrations; 5 new ordered foundations. Added models/factories/seeders, `SaveCatalogGroup::handle(User, ?Group, array): Group`, `CatalogIntegrity`, central result defaults, `manage-catalog`, explicit panel registration, MySQL bootstrap and pre-RefreshDatabase application guard. Final foundation tests: 20 passed / 56 assertions (SQLite), 8 passed / 27 assertions (MySQL); Pint fixed formatting. Unsafe/missing MySQL target rejected before boot. New development migration run: 22 migrations applied. `TransferRetainedApplicationData::handle(string, string): array` copied 2 reviewed local users, repeat unchanged=2, all IDs/hashes/encrypted fields matched and source rows unchanged. No original database mutation. PhpStorm inspection unavailable because only the source project is open; executable checks cover the isolated checkout. |
| T04 | Complete | Exact 67-position canonical parser, hashed dry-run/apply CLI, atomic identity merge and shared import lock. SQLite 11 tests / 96 assertions and MySQL 13 / 104 passed, including audited corpus, late rollback and cross-process contention. Development apply created D060 and 501 Products; identical repeat created/updated=0, unchanged=501. Private JSON reports in ignored storage; original source/current database untouched. Pint passed. |
| T05 | Complete | Real root/branch/leaf/Product pages, ordered 10-card pages, locked identities, actual breadcrumbs; authorized renamed Group/Product resources with explicit domain save and read-only facts. 8 focused tests / 48 assertions passed; table-standards adaptation included in final 9-test / 77-assertion selected run. Browser: imported D060, 501 results, 10 cards, actual Product 124; 390px and 1440px layouts had no overflow; keyboard skip link worked; Product dialog inherited fi-width-7xl (1280px). Pint/build passed. FilamentExamples search returned full v4 example source, used only for structural context; installed v5 source/docs govern APIs. |
| T06 | Complete | Typed snapshot/result, newest-first reducer, bound scalar JSON predicates, exact seven-filter seed, preset reconciliation/counts/pagination and request-local preparation. Final SQLite 17 tests / 75 assertions; MySQL 12 / 83. Actual source outcomes 36 and 9. Browser proved Back/forward, restored page2, stale/scalar URL repair, rapid choices, and Back after a delayed POST was sent; documented scoped fixes below. MySQL REPEATABLE-READ: initial 13 queries / 4.76ms SQL; zero-compatibility and page2 22 / about 8.5ms; seven-filter sample 27 / 9.73ms, selected card JSON 145–1471 bytes, initial HTML 37082 bytes. EXPLAIN uses group/code/id index. No additional indexes/cache. |
| T07 | Complete | SaveGroupSettings validates complete staged metadata before writing, preserves IDs/order/labels and result settings, and rejects foreign/stale input. Group editor saves details and metadata in one transaction; failed drafts remain visible and successful saves reload new IDs. SQLite and MySQL each 16 tests / 106 assertions, including unchanged timestamps and property swaps. Browser verified unchanged save, temporary size2 gave 2 cards / 251 pages / 501 results, then restored10; public snapshot only groupId/discovery, 441 bytes, mobile width390 without overflow. No client presets invented. |
| T08 | Complete | Canonical/local/rule schema and immutable compiler; authorized owner-locked complete saves, stable relational IDs, independent orders/defaults and remapped duplicates. SQLite: 29 tests / 77 assertions. Guarded MySQL: 23 tests / 70 assertions. Isolated development migrations applied; no client Configurator seeded. |
| T09 | Complete | Typed, database-free DAG evaluator, fixed policy resolver and coherent fresh Product loader. 52 selected SQLite/unit tests / 129 assertions; MySQL runtime: 3 tests / 14 assertions. All 14 retained engine/manifest/schema/presentation cases preserved and adapted. |
| T10 | Complete | Canonical library forms and owner-scoped workspace; later user amendment implemented Overview / Groups / Attributes / Rules / Preview & Test. See evidence below. |
| T11 | Complete | Typed rule editor, staged matrix, priority ordering, rollback and real browser checks. |
| T12 | Complete | Public runtime/editor verified. Preview & Test is placeholder-only. Approved legacy archive applied; every historical test retained. Final full SQLite 245 / 1370 and guarded MySQL 94 / 595 passed; whole-branch review fixes complete. See final evidence below. |
| T13 | Local rehearsal complete; release incomplete | Fresh restricted rehearsal and rollback databases, retained data, 501 imports/reimports, hashes/files/code pairing verified. See [release evidence](RELEASE_REHEARSAL.md). Production inspection/content, temporary QA cleanup and matching release revision remain; full regression and whole-branch review are complete. |

At each stopping point record branch/revision, relevant uncommitted paths, database roles, actual passing/failing commands, browser observations and the next unchecked task with only its blocking input. Update user decisions and owning contracts/guidelines together when behavior changes. Do not let a historical alternative become a second implementation instruction.

Implementation is available on the isolated development site. Release readiness remains separate from completed application work.

### Execution rulings and environment evidence (2026-09-23)

- Ruling: use this plan's T01–T13 checklist and evidence rows as the durable task ledger; the skill extractor only understands headings named `Task N`. Scratch evidence lives in `.superpowers/sdd/IMPLEMENTATION_PLAN/`; no automatic commits or pushes.
- Preflight: T03 produces metadata tables consumed by T06/T07 and the Configurator stub extended by T08; T04 import feeds T05 real routes; T06 state DTO feeds T07 settings; T08 compiler/typed definitions feed T09 evaluator; T10/T11 author through T08 actions; T12 uses T09 loader/evaluator; T13 completes T03 retained-data transfer. No conflicting interface found in the initial scan; detailed signatures belong to their task.
- Source database account denied CREATE DATABASE as expected. Herd CLI incorrectly reported Pro unavailable while the Herd MCP connector confirmed running MySQL 8.0.46. Used that local service's setup credentials only to create new empty databases and separate restricted accounts. No source rows/schema/grants changed.
- Herd link automatically refreshed skill links; restored the reviewed independent skill files so that the rebuild does not include unrelated skill-format changes.

- T06 ruling: native Livewire restored valid filters/precedence/page2 with single Back, but a stale initial URL retained its stale address while rendering normalized results. Added a catalog-only completion-time replacement using documented component/morphed hooks, preserving other history state and URL parameters. Native Url binding remains the sole ordinary history owner.
- T06 browser regression: with a delayed filter response, native Back allowed an earlier pending discovery action to overwrite the restored snapshot. The scoped adapter now cancels only this component's unfinished read-only actions through the documented action interceptor before native popstate restoration. No separate URL state or global navigation handler is introduced.

- T07 MySQL finding: JSON object key order differs from PHP insertion order. Compare unordered maps with strict values before assigning them, retaining list order and types; the unchanged-save timestamp regression now passes on both databases.

- T08 ruling: MySQL 8.0.46 accepted `AA ` into `VARCHAR(2)` by truncating the trailing space before CHECK evaluation. The storage capacity is now 3 solely to let the length/ASCII CHECK reject that input; valid codes remain exactly two case-sensitive ASCII alphanumerics. The default-membership composite FK passed its cross-inclusion rejection test.

- T08 ruling: the definition loader was introduced with T08 because complete edits, duplication and owner checks require a current relational draft. T09 extends that same loader with the Product assignment boundary. Full surviving rule/condition/effect/mapping IDs and timestamps are tested across repeat saves; a cloned relationship query prevents deletion predicates leaking into subsequent reference upserts.

- T09 retained coverage: original engine test names remain, with default/legal-set/pruning/fallback/completeness/code/context/priority behavior asserted through the canonical evaluator. The two manifest cases now use the single compiler/evaluator; the three cross-Attribute presentation summaries remain asserted on typed rules. Automatic review rejected a bulk rewrite; smaller assertion-mapped patches preserved every case and passed. The migration rollback test exposed SQLite foreign-key removal by name, now handled by its supported column-list API. Hidden Attributes retain a formerly accepted choice even while temporary restrictions exclude it; restoration still requires current legality.

### T10 — shared management verification

Canonical resources and the four-tab workspace now use gated domain actions. SQLite and guarded MySQL administration tests each passed 16 tests / 115 assertions; three retained table-contract datasets passed 71 assertions. Pint and the Vite build passed. Browser: created an explicitly temporary unassigned synthetic definition, inspected the full-width Settings modal and saved without visiting other tabs; confirmed success and unchanged empty inclusions. Temporary fixture remains isolated for T11/T12 and will be removed before release evidence. Rules and Preview tab contents are delivered by T11/T12.

### T11 — rule editor verification

Typed Builder predicates and one-level All/Any groups, advanced effects and the whole-rule matrix now save through the owner-locked aggregate. Highest row receives highest priority; reorder mode clears search and rejects partial permutations. SQLite editor/administration: 23 tests / 154 assertions. Guarded MySQL rules: 13 tests / 79 assertions. Pint and build passed. Real browser: keyboard Space toggled source/target, empty target failed without losing draft, repair summary received focus, saved matrix reopened with checked memberships, target change retained a visibly stale reference, scrolling preserved code labels, 390px layout had no document overflow. HTTP preview lacked crypto.randomUUID; staging keys now use a server-generated prefix and local counter, with no persistence until Save.

### User amendment — manage page, 2026-09-24

The user supplied `d60-filter/configurator-admin.html` and explicitly replaced the old four-tab layout. Browser inspection of Overview and Product Groups informed the implementation: Overview → Groups → Attributes → Rules → Preview & Test. Overview is an inline scoped form; all Group assignment content and actions live in Groups; Custom and the old Settings/Assign Groups/View Groups header actions are removed. Configurator list action and breadcrumb say Manage. The sketch is a UX reference, not authority for new status workflows or synthetic production data. Administration/integration regressions: 18 tests / 128 assertions; Pint/build passed. Browser confirmed the new tabs and visible assignment lists/actions. Final responsive check at the normal 872px editor width shows Assigned and Available Groups side by side with the Assign action visible. The Filament theme now scans the catalog Livewire views so their responsive utilities are included; the rebuilt assets and final browser view were verified.


### Later user amendment — Preview & Test deferred (2026-09-24)

Keep the Preview & Test tab with temporary placeholder text only. Its actual content and logic are deferred. The Manage page no longer mounts `ConfiguratorPreview`; previously implemented adapter code and its tests remain available for later work. Earlier Preview evidence below is historical and does not mean the UI is currently enabled. The updated Manage-page regression first failed while the component was mounted, then passed with the static placeholder. Focused administration/integration tests: **21 passed / 154 assertions**; Pint passed. Actual browser selection showed only “Preview and testing tools will be added here later.”

### T12 — runtime, manage amendment and earlier regression evidence (2026-09-24)

Public and saved Preview use the same trusted loader/compiler/evaluator. Browser verified catalog → D060 → imported Product, initial `Q0-R1`, valid `Q1-R9`, single Territory choice, hidden RE / disabled RF, and return to Q0 with the visible fallback to R1. Saved Preview produced the same progression. Advanced-rule browser authoring saved and reopened Territory = Synthetic QA region with a whole-Attribute hint. All of these codes belong solely to the clearly labelled temporary QA definition.

Browser-found corrections: initialize the Preview form before entanglement; construct schemas without overwriting an incoming form update; display escaped option values/hints in Preview; tolerate malformed prior selections; preserve literal `"0"` on Product cards. The retained native Group list action could mutate directly despite its URL; it now uses ordinary URL actions to the dedicated domain-save pages. Unexpected admin write errors preserve the draft and show safe feedback. Each defect has focused regression coverage. Pint, Vite build and `git diff --check` passed.

Earlier verification before approved archival cleanup (superseded below): public/integration 15 tests / 76 assertions; canonical/configurator/rules administration 31 / 225; Group administration/settings 17 / 109; guarded MySQL combined schema/import/discovery/constraints/concurrency/retained-transfer/runtime/admin 67 / 397. Complete SQLite suite: **225 passed, 11 failed, 1077 assertions**. The remaining failures are two FileAttachment unit cases, ConfigEngineDemo interaction, ConfigurationPart form, five historical table datasets, FileAttachment images and old model relationships. No test was removed or skipped.

The initial automatic approval review rejected the proposed broad retired-POC archival move; no files were moved at that point. [Exact hashed inventory and proposed test boundary](LEGACY_ARCHIVE_REVIEW.md) covers 82 existing files to move and 36 historical supporting copies; approval was requested then. The later user approval and completed operation are recorded below. The local rehearsal proceeded during that earlier hold.

Per the user's response, **D060 was restored to unassigned after public/Preview QA**. Browser and worktree-scoped Boost verified `configurator_id = null` and 501 Products. The temporary unassigned QA Configurator and its canonical rows remain for review; remove them before declaring release readiness. Its second rule is `Synthetic QA context hint`, created only for editor verification. No parent metadata, client SubGroups or production codes were invented.

### T13 — isolated local rehearsal (2026-09-24)

`TransferRetainedApplicationData::transferAll()` adds a reviewed local retention profile with read-only source snapshot, encryption-key compatibility, hash-gated apply, complete target conflict preflight and atomic inserts. The CLI defaults to dry-run and reads source credentials privately for its process only. Unclassified populated tables, field types, extensions, assets and old-domain references fail closed. All original account-transfer tests remain; transfer/rollback/rejection coverage passed on SQLite and MySQL.

New restricted databases: `configurator_catalog_rehearsal` and `configurator_catalog_rollback`, MySQL 8.0.46 at 127.0.0.1:3307. Clean migration, transfer of two users plus one Bolt form / one section / three fields, exact repeat, CSV import of 501 Products, unchanged repeat and seven reviewed filters passed. No Configurator is assigned or seeded there. Public routes rendered 200 with zero orphan Products and the retained administrator admitted.

Private archive holds the matching original Git revision and application patch, original environment, 32-table dump, and ten verified media files (seven originals / three conversions). Restore to the second isolated database matched every table count and SHA-256; matching old code rendered its demo with HTTP 200. Activation/reversal used only process-local environment overrides. Subsequent read-only verification found **zero changed original tables**, all ten original file hashes matched, and original revision/patch/environment remained unchanged. No queued jobs ran, and no deployment/active database switch occurred. Production remains uninspected; [release evidence and outstanding gates](RELEASE_REHEARSAL.md) are authoritative.


### T12 completion — approved archive, discoverability and final review (2026-09-24)

The user approved the exact [legacy inventory](LEGACY_ARCHIVE_REVIEW.md). Verified hashes before applying 82 moves and 36 copies into `tests/Fixtures/Legacy/`; restored the matching 19 domain migrations and old evaluator only into that archive. Seven retained historical test files now opt into isolated legacy fixtures. The fixture loader refuses any environment except testing with SQLite `:memory:`. No historical test was removed or skipped. Runtime scans and public-route tests confirm that retired resources, demo and schemas are absent from the rebuilt app.

Added named-route links: rebuild home → Browse product catalog; admin sidebar → Open public catalog; Group list/editor and Product list → Open catalog page. The original site and database remain unchanged. Actual browser verification: 501 D060 Products; pressure filter → 85; adding Flange → 63; choosing zero-count 1″ clears older pressure with visible feedback → 36. Next and Back preserve active filters and restore page 1; an imported Product opens with its actual facts and explicit unassigned state. Home/admin links are visible.

One final independent whole-working-tree review completed. Its import finding was Important (parent metadata could change after approval); its context-editor finding was promoted from Minor to Important because a normal operator change hid the field required to repair the draft. Both were fixed in one pass, with failing-then-passing regressions. Parent metadata is now validated, normalized, included in the report and bound to a third SHA-256, including empty metadata. The context editor keeps the previous scalar/list choice visible with clearing guidance until explicitly removed; a failed save leaves the complete aggregate unchanged. No second review cycle was requested. Deferred Preview, client definitions, production retention/cutover and intentionally retired POC behavior remain outside this completion claim.

Final checks: `vendor/bin/pint --dirty --format agent` passed; `php artisan test --compact` **245 passed / 1370 assertions**; guarded `vendor/bin/pest --configuration phpunit.mysql.xml --compact` over MySQL catalog tests plus importer, integration, canonical/configurator/rules/Group administration, home and public routes **94 passed / 595 assertions**. Logs: `.superpowers/sdd/IMPLEMENTATION_PLAN/T12-final-{sqlite,mysql}.log`. The preceding Vite build passed. Branch remains `codex/catalog-rebuild`, base/HEAD `7f752536e7cddcfdcfaf6f4b27e829d1a64bf886`, all implementation changes uncommitted; no push, deployment or active database switch.

Current import command (dry-run by default):

```sh
php artisan catalog:import-products SOURCE --actor=ADMIN_ID [--parents=PARENTS_JSON] --no-interaction
php artisan catalog:import-products SOURCE --actor=ADMIN_ID [--parents=PARENTS_JSON] --apply --source-hash=REVIEWED_SOURCE_SHA256 --map-hash=REVIEWED_MAP_SHA256 --parents-hash=REVIEWED_PARENTS_SHA256 --no-interaction
```

Use every hash printed by the same dry-run. Adding, changing or removing parent metadata requires a new review. The original T13 rehearsal predates this extra hash; the final SQLite/MySQL importer checks cover the revised command, exact 501-row corpus, repeat, parent drift, rollback and concurrent apply.


### User amendment — public layout and development workflow (2026-09-24)

Public Home, catalog, Group and Product pages now share the catalog header: centered Home/Product catalog navigation, active-link state and a light/dark toggle using the existing Flux appearance preference. The content container spans the viewport with responsive padding. Group filters use 12px text, compact buttons and a responsive 2/3/4/8-column grid (eight columns from the xl breakpoint). D060's seven fields fit in one row at a 1440px CSS viewport; 390px uses two columns with no horizontal overflow. Counts, selection and precedence are unchanged.

Verification: `npm run build` passed; home/public/discovery regression **18 passed / 92 assertions**. Browser verified light/dark switching, keyboard Space, persistence through Home/catalog navigation and reload, 1440px and 390px geometry, centered navigation and no console errors. Temporary viewport override reset. No new tests mirror layout or Flux internals.

Workflow inspection: the rebuild is still the uncommitted `codex/catalog-rebuild` worktree; the original folder remains `upgrade/laravel13-filament5`. Both use the same GitHub repository. Live Forge inspection confirms `ari.data4.work` on `general-dev` still deploys `upgrade/laravel13-filament5` with quick deploy off. Asked whether to promote the rebuild into the original project/site or retain separate permanent locations; target selection is pending. No folder move, Git checkpoint/push, server change or database switch was performed in this layout pass.


### User-authorized primary promotion (2026-09-24)

The user selected the rebuild as the primary application, including deployment at `ari.data4.work` with a new database, and requested a preserved legacy side branch/database copy. This supersedes the earlier no-push/no-deployment/no-switch boundary for this operation. Legacy local polish is checkpointed as `codex/legacy-pre-rebuild` (`1b046ae`); unrelated local files and private inputs stay outside Git. The rebuilt application is being promoted to the existing default `master` branch. Full SQLite regression before promotion: **245 passed / 1370 assertions**; Vite build passed. Deployment-check CI now includes `master`. Exact deployment and backup evidence is recorded in `RELEASE_REHEARSAL.md`.
