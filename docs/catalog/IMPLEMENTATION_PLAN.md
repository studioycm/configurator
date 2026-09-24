# Catalog and configurator implementation plan

> For the new implementation session: execute task by task using the project's normal workflow and `superpowers:executing-plans` when working directly. Maintain the checkboxes and evidence log. This documentation session has not started the rebuild; the new session's instruction establishes execution scope.

**Goal:** deliver a visible catalog from imported D060 Products, then the rebuilt shared configurator engine and Filament management.

**Architecture:** Laravel services/actions own queries, validation, persistence and evaluation. Class-based public Livewire pages and Filament administration consume those operations. Product discovery and configurator evaluation have separate state and behavior. Use the existing app structure and dependencies.

**Spec:** [guidelines](IMPLEMENTATION_GUIDELINES.md), [user decisions](DECISIONS.md), [data/engine contract](contracts/ENGINE_AND_DATA.md), [public contract](contracts/PUBLIC_CATALOG.md), [admin contract](contracts/FILAMENT_ADMIN.md). Read the guidelines once and the relevant contract before each task. Research is supporting evidence, not another plan.

**Reviewed stack, 2026-09-23:** PHP 8.4; Laravel 13.33.0; Filament 5.8.4; Livewire 4.4.6; MySQL 8.0.46; Pest 5.2.1; Boost 2.9.1. Recheck installed versions before new API assumptions.

## 1. Start and authority

Start at **T01**. All implementation tasks below are open. Completed earlier: research, normalized CSV/audit, selected skill imports and local wide-modal/Groups/Rules label polish. No new domain tables, importer, public catalog or rebuilt engine exists yet.

Execute M1–M4 in order unless the new session's user narrows scope. Prepare M5's isolated rehearsal and release evidence. Active/deployment database switching, pushing and deployment follow their corresponding instruction; they are not automatic consequences of completing this plan. Do not restart the research or historical decision questionnaire.

The source workspace was on `upgrade/laravel13-filament5`, HEAD `7f752536e7cddcfdcfaf6f4b27e829d1a64bf886`, with uncommitted work. Preserve prior edits to AppServiceProvider, ConfigEngineDemo, CatalogGroup/OptionRule resources, ProductProfilesTable and TableConfigurationStandardsTest; preserve selected skills/boost.json and these docs. Preserve unrelated `herd.yml`, `.DS_Store` and `public/vendor/` content.

Confirmed user behavior is fixed. The handoff selects these **technical defaults**, without relabelling them as user decisions: CLI-first whole-file atomic import; initially read-only imported Product facts; one discovery URL snapshot/explicit paginator; hidden-preset cleanup; page-size cap 100; saved-definition Preview; unassigned duplicates; inactive-rule cycle checks; base-presentation fallback for priority ties; empty initial policy-override allowlist. Follow them coherently. Record a necessary mechanism change while preserving the contract; ask only for a consequential behavior, retention or access conflict.

## 2. Sequence and source ownership

| Milestone | Tasks | Outcome |
| --- | --- | --- |
| M1 — Catalog foundation | T01–T05 | Visible public shell, imported D060, real Group/Product flow and useful admin lookup |
| M2 — Discovery | T06–T07 | Filters/SubGroups, counts, immediate cards, pagination/Back and Group settings |
| M3 — Models and engine | T08–T09 | Canonical codes/local inclusions and one deterministic evaluation result |
| M4 — Authoring/integration | T10–T12 | Working matrix/rule management and public/Preview parity |
| M5 — Transition rehearsal | T13 | Verified retained data, MySQL behavior, matching release and rollback evidence |

Execute T01→T13 in order. Later improvements must not delay early visible progress. Data/engine §§2–4 owns schema/IDs/operators/DTOs, §§6–7 owns all source columns/import behavior. Public §§3–6 owns Group settings, vocabulary, URL/precedence/counts/queries. Admin §§2–7 owns resources, fields/actions, staged writes and authorization. Plan §7 owns migration partition and release boundaries. The guidelines collect research-derived instructions.

## 3. Execution and review loop

For each task: inspect sibling code/current diff; read its contract; add meaningful tests with independent expected results; establish the behavioral gap with the narrow test; implement; rerun; inspect the relevant UI. Pure copy/layout changes need visual review, not tests that mirror markup. Generate new PHP types through Artisan; rename existing families coherently. Preserve existing tests and user changes.

Record changed files, exact commands/results and browser scope in §9. A missing test file, zero tests, written test or inspected tutorial is not a pass. Run `vendor/bin/pint --dirty --format agent` after PHP edits. Commit only within the execution session's scope; do not blanket-stage/reset unrelated files.

Five required failure cases: owned blanks/conflicting identities (T04); malformed Back with precedence (T06); exact-case codes/forged local IDs (T08); empty intersections/hidden restoration (T09); aggregate failure/unvisited Settings tabs (T10–T12).

## 4. Task checklist

### T01 — Checkout, private inputs and database roles

**Inspect:** AGENTS.md, rules/skills, Composer/npm versions, phpunit.xml, tests/Pest.php, database/cache config, Git diff and private inputs. **Produces:** a reproducible implementation environment.

- [ ] Read guidelines; resolve installed versions and generator help. Use an isolated checkout for changing the migration baseline. A worktree does not copy uncommitted work: carry the relevant reviewed patch, docs and independent skill files without modifying/resetting the source workspace.
- [ ] Make the normalized CSV/audit available from [Decisions §5](DECISIONS.md#5-product-data-and-normalization). Verify CSV SHA-256 `5a8adbd4439bb1f020ab625d84fa6fde3a62ce67fbd74f94cf2b2e3b19758f72`, 501 data rows and 67 positions. Keep raw inputs private.
- [ ] Resolve the isolated Herd site and distinguish old/current, new development and disposable test databases. Record roles without secrets. Do not switch the active site or reset a source database.

**Exit:** checkout, preserved changes, input verification and database roles recorded. No behavior test is needed for this inventory.

### T02 — Visible public catalog shell

**Create:** `app/Livewire/Catalog/Index.php`, `resources/views/livewire/catalog/index.blade.php`, `resources/views/components/layouts/catalog.blade.php`. **Modify:** `routes/web.php`. **Test:** `tests/Feature/Catalog/PublicCatalogTest.php`. **Contract:** public §§2/7.

- [ ] Add public `/catalog`, named `catalog.index`, using class-based Livewire; preserve home/account routes. Render an honest empty/preparation state using current app assets, without invented Products/counts.
- [ ] Verify public access and intact account routes; inspect desktop/narrow rendering on the actual Herd site.

**Exit:** a visible catalog entrance before the complete engine/editor. Real records connect in T05; the shell is not a completed catalog.

### T03 — Fresh schema, guarded tests and access boundary

**Create:** `app/Models/{Group,Product,Configurator,GroupFilter,SubGroup}.php`, factories/seeders and ordered migrations; `app/Actions/TransferRetainedApplicationData.php` (initially only the reviewed local account transfer); `phpunit.mysql.xml`, `tests/bootstrap-mysql.php`, `tests/Feature/Catalog/MySql/CatalogSchemaTest.php`. **Modify:** `app/Providers/AppServiceProvider.php`, panel registration, test configuration. **Contract:** data/engine §§2/8; admin §§2/7; plan §7/8.

- [ ] Establish the MySQL guard before any refresh/migration test: testing environment, driver and exact disposable database allowlist; unsafe/missing targets fail without fallback. Exclude MySQL-only cases from default SQLite discovery.
- [ ] Apply the 17-retained/19-replaced migration partition on the implementation branch. Create minimal empty Configurator before Group's nullable FK, then Group/Product and empty GroupFilter/SubGroup metadata tables. T06 consumes that metadata; T07 exposes its admin editor. Preserve old code/migrations/database as the archive.
- [ ] Implement tree/leaf constraints, legacy identity, JSON casts and Product's `name` accessor; SQL uses `product_code`. Define `manage-catalog` from existing panel eligibility. Exclude obsolete domain resources/pages from registration before any fresh-schema panel is used.
- [ ] Prove migration order, distinct legacy/internal relationships, correct accessor/SQL behavior, cycle/non-leaf rejection and admitted/non-admitted writes. Make retained local account data available for authenticated development with original IDs/hash/encryption compatibility; read the source without modifying it or fabricating production credentials.

**Exit:** compatible fresh schema/panel on isolated databases, with current database untouched. SQLite is not proof of MySQL constraints.

### T04 — Repeatable, atomic D060 import

**Create:** `app/Services/CatalogImportParser.php`, `app/Actions/ImportCatalogProducts.php`, `app/Console/Commands/ImportCatalogProducts.php`, `tests/Feature/Catalog/CatalogImportTest.php`, `tests/Feature/Catalog/MySql/CatalogImportTest.php`. **Contract:** data/engine §§6–7; decisions §5.

- [ ] Implement the exact positional map and canonical-CSV parser. Preserve strings/blank slots and skip repeated HTML decoding. A raw-source normalization port must match the audited corpus.
- [ ] Expose `catalog:import-products` with dry-run default and explicit apply, source/map hash validation, admitted-actor authorization and optional supplied parent metadata. Record the implemented command signature/help in §9.
- [ ] Preflight every row/identity/Group/code conflict; one lock and one transaction resolve Groups first and apply source-owned merges. Retain app-only keys, stable IDs and absent Products. Produce a bounded private outcome report.
- [ ] Verify 501 first-import/unchanged-reimport records; then independent corrected/blank/absent/conflicting-identity fixtures. Force a late failure and concurrent attempt on MySQL: zero partial writes or wrong-identity overwrite.

**Exit:** D060/2144 and 501 Products with all three JSON fields, reproducible report and proven reimport/failure behavior. Missing parent metadata leaves D060 unattached; no invented legacy parent.

### T05 — Real Group/Product flow and useful management

**Create:** `app/Livewire/Catalog/{GroupShow,ProductShow}.php`, matching views, `tests/Feature/Filament/CatalogAdministrationTest.php`. **Modify:** Index/routes/layout; rename Group/Product resource families and affected factories/tests. **Contract:** public §§2/7; admin §§2/6.

- [ ] Connect exact named Group/Product routes. Branches show actual children; leaves show stable immediate cards/pagination; Product pages show actual `product_name`, code and fixed facts.
- [ ] Keep unfinished configurator/specification/asset areas honest. No unfinished engine call, demo slug or first saved-configuration fallback. Unassigned Products remain useful.
- [ ] Build Group hierarchy/assignment basics and Product lookup by code with escaped read-only facts; numeric Part1…Part28 ordering, no arbitrary JSON editor or dead Import button.
- [ ] Test correct scope/labels, 404s and navigation; browser-check root→leaf→actual Product, narrow layout, keyboard navigation and preserved 7xl dialogs.

**Exit M1:** demonstrable imported public catalog and useful admin lookup. Filters follow in M2; actual configuration follows in M4.

### T06 — Discovery reducer, queries and URL history

**Create:** `app/Services/{CatalogDiscovery,CatalogFilterReconciler}.php`, `app/DTO/{CatalogDiscoveryState,CatalogDiscoveryResult}.php`; `tests/Unit/CatalogFilterReconcilerTest.php`, `tests/Feature/Catalog/CatalogDiscoveryTest.php`, `tests/Feature/Catalog/MySql/CatalogDiscoveryTest.php`. **Modify:** GroupShow/view and useful repeated Blade components. **Contract:** public §§3–8.

- [ ] Implement exact version-1 `discovery` shape/action methods, one URL binding and explicit Eloquent paginator. No WithPagination, async discovery actions or second Table state owner.
- [ ] Initialize D060's seven source filters and canonical labels/value order from statically inspected conf.js via `database/seeders/D060FilterSeeder.php`, scoped by legacy Group identity. Then implement newest-first replay, atomic preset precedence, hidden same-property cleanup, resets, self-excluding counts and bounded Group-scoped queries over the full dataset.
- [ ] Pin the independent fixture: pressure25→Flange→size1 gives B; Flange→pressure25→size1 gives C. Test preset variants, `0`, blank/null/type precision, preset-only properties and all-page counts.
- [ ] Browser-prove one-step Back/forward, valid restored page, malformed/stale address-bar repair, rapid clicks and Back during delayed responses. Try native behavior first; use only the contract's scoped replacement adapter if the observed repair fails.
- [ ] Measure actual MySQL queries/time/payload/EXPLAIN before adding indexes/caches. Prove no per-button queries or full Product/JSON hydration.

**Exit:** controls, counts, cards, notices and URL agree; zero counts remain clickable; both the independent and observed 36-versus-9 sequences preserve precedence. The metadata schema already exists from T03; T07 adds its management UI.

### T07 — Group filters, SubGroups and settings

**Create:** `app/Actions/SaveGroupSettings.php`. **Modify:** the GroupFilter/SubGroup models/factories from T03 where required, Group resource schema/actions, CatalogAdministrationTest and discovery tests. **Contract:** public §§3/5; admin §6.

- [ ] Manage ordered filters/labels/value order and one-property preset sets as a single validated Group-settings transaction, preserving IDs and T06's canonical source setup.
- [ ] Implement shared result-setting keys/defaults/cap and visitor allowlist. Preset-only properties work without visible filters. Do not infer presets from source `sub_group` text.
- [ ] Verify singleton, 10/16, force-hide, newest-conflict, clear/reset, unchanged save and stale metadata. Twenty-three Products at10 produce10/10/3; sizes2/1 preserve access to all records. Admin changes affect the next public evaluation.

**Exit M2:** complete managed discovery independent of configurator rules.

### T08 — Canonical models and reference-safe writes

**Create:** canonical/local/rule models and migrations/factories exactly in data/engine §§2–3; `app/Services/ConfiguratorDefinitionCompiler.php`; definition/rule/condition/effect DTOs in `app/DTO`; `app/Actions/SaveConfiguratorDefinition.php`; `tests/Feature/Catalog/ConfiguratorDefinitionTest.php`, `tests/Feature/Catalog/MySql/ConfiguratorIntegrityTest.php`. **Modify:** Configurator stub and affected old-model coverage.

- [ ] Build canonical rows before local inclusions; add default-membership FK after both inclusion tables, then rule/condition/effect/mapping constraints. Verify real MySQL DDL.
- [ ] Implement the typed definition compiler and complete dependency/cycle validation before the save operation depends on them. Then implement authorized scoped validation, deterministic reference/owner locks, explicit repairs, stable-row diffs and duplicate remapping with canonical reuse/no copied assignments.
- [ ] Preserve stored default through reorders; keep display/code/option order separate. Prove `Aa`, `aa`, `00` coexist while duplicate exact code, spaces, invalid length/non-ASCII and forged owner/Attribute IDs fail.
- [ ] Prove removal denial before writes, correct new staging-default resolution, valid partial mappings/overlapping targets and complete-permutation ordering.

**Exit:** domain writes protect identities/references without depending on a UI. Do not add historical code retirement/versioning.

### T09 — Shared compiled engine

**Create:** `app/Services/ConfiguratorDefinitionLoader.php`, `app/DTO/{ConfiguratorEvaluationInput,ConfiguratorEvaluationResult}.php`, `tests/Unit/Configurator/ConfiguratorEngineTest.php`. **Use:** T08's definition compiler/DTOs. **Replace coherently:** `app/Services/ConfiguratorEngine.php`. **Contract:** data/engine §§4–5; guidelines §5.

- [ ] Load coherent current Product→Group→Configurator definitions and compile typed conditions/effects plus all choice/applicability dependencies. Reject cycles, including inactive stored dependencies.
- [ ] Evaluate database-free to settle the entire DAG, then presentation/completeness/code. No two-pass demo repair or parallel manifest evaluator.
- [ ] Pin A→B→C, missing-negative/OR, product/context-only activation, empty intersection/latest choice, fallback/hidden restoration, outgoing effects, presentation self-reference versus self-hide, ties and independent code/UI/default order; test All/specific contexts.
- [ ] Reconcile current assignment/definition changes and reject stale disabled inputs. Empty/invalid definitions withhold code. Adapt retained legacy capability tests deliberately, without deleting them or preserving superseded semantics.

**Exit M3:** one deterministic result containing legal/hidden/disabled state, selections/memory, presentation, diagnostics, completeness and code.

### T10 — Shared-definition management and workspace

**Create/rename:** exact resource families in admin §2; `app/Filament/Resources/Configurators/Pages/EditConfigurator.php`, `app/Filament/Resources/Configurators/RelationManagers/AttributesRelationManager.php`; `tests/Feature/Filament/{CanonicalDefinitionsTest,ConfiguratorAdministrationTest}.php`. **Contract:** admin §§2–4/7.

- [ ] Build canonical forms/usage views and four tabs: Attributes, Rules, Preview & Test, Custom. Use explicit Settings/Assign Groups/Duplicate/local inclusion/Code order actions over domain writes; no broad Save or Custom behavior.
- [ ] Implement leaf assignment without silent stealing through Configurator's assignment action; duplicate remaps local references and remains unassigned. Preserve shared/local labels and stored defaults.
- [ ] Verify resource and direct custom authorization, shared-local isolation, duplicate integrity and Settings save with Attributes/Rules unvisited. Start with ordinary rendering, not speculative deferred editing schemas.

**Exit:** current shared definitions and local setup are manageable with clear edit scope.

### T11 — Rule editor, matrix and priority

**Create:** `app/Filament/Resources/Configurators/RelationManagers/RulesRelationManager.php`, `app/Filament/Forms/Components/MappingSetsField.php`, `resources/views/filament/forms/components/mapping-sets-field.blade.php`, `tests/Feature/Filament/ConfiguratorRulesTest.php`. **Contract:** admin §§4–5/7.

- [ ] Build typed root-AND/one-level groups, mapping sets and advanced effects. Reject extra/deeper/unknown payloads. Exclusions remain secondary.
- [ ] Stage all cells/sets and validate the complete resulting definition before writes. Preserve surviving IDs and failed draft input; driver/target changes require explicit repairs, never silent clearing.
- [ ] Implement owner-locked authorized complete-permutation drag/keyboard reorder; highest row has highest priority. A native pre-reorder hook is not the transaction boundary.
- [ ] Test partial coverage/overlapping targets, duplicate/empty sets, wrong IDs, late failure rollback, stable reload and cycle errors. Browser-check keyboard labels, scrolling, error focus and narrow-screen usability.

**Exit:** saved rules drive the engine; no independent database-writing matrix cells.

### T12 — Saved Preview and public configuration

**Create:** `app/Livewire/Catalog/ProductConfigurator.php` and view, focused saved-preview adapter, `tests/Feature/Catalog/ConfiguratorIntegrationTest.php`. **Modify:** ProductShow, legacy demo/registration and affected tests. **Contract:** public §7; admin §7; data/engine §5.

- [ ] Resolve trusted Product/current assignment on every interaction. Public and Preview call the same loader/compiler/evaluator; Preview selects an actual Product from assigned Groups and visibly uses saved data.
- [ ] Render single-choice toggle/select inputs, exact hidden/disabled results, fallback notices and separate Product/Configuration codes. Territory/Application each default All and remain single-select.
- [ ] Prove same-input parity, current saved edits affecting next evaluation, hidden restoration, disabled-choice bypass denial and no Preview writes. Unassigned Products stay valid.
- [ ] Remove remaining active POC configuration/parts/specification/attachment dependencies coherently; retain archive and adapt tests. Browser-check the full catalog→Product→configurator flow and saved admin edits.

**Exit M4:** real Product configuration with public/Preview parity; deferred content remains honest placeholders.

### T13 — Isolated transfer and release rehearsal

**Modify:** T03's `app/Actions/TransferRetainedApplicationData.php` for the inspected retained package-data map. **Create:** `app/Console/Commands/TransferRetainedApplicationData.php`, `tests/Feature/Catalog/MySql/RetainedDataTransferTest.php`. **Verify:** fresh migrations and §9 evidence. **Contract:** plan §7; data/engine §8.

- [ ] Inspect intended deployment source read-only and finalize retained table/column/file/job mapping. Local counts do not establish production content.
- [ ] Rehearse clean migration, retained-data transfer, CSV import and approved definition setup in a separately named MySQL database. Verify login/IDs/FKs/auto-increments/counts/checksums/reimports/files.
- [ ] Execute MySQL uniqueness/CHECK/FK/JSON/rollback/concurrency and relevant regression checks; complete the full-suite check under repository guidance and actual browser journey. Record missing/failed evidence honestly.
- [ ] Prepare and rehearse matching code/database activation and reversal, write freeze/delta handling, queue/storage disposition and backups, preserving the current installation. Present results before any active/deployment switch.

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
| T01 | Not started | |
| T02 | Not started | |
| T03 | Not started | |
| T04 | Not started | |
| T05 | Not started | |
| T06 | Not started | |
| T07 | Not started | |
| T08 | Not started | |
| T09 | Not started | |
| T10 | Not started | |
| T11 | Not started | |
| T12 | Not started | |
| T13 | Not started | |

At each stopping point record branch/revision, relevant uncommitted paths, database roles, actual passing/failing commands, browser observations and the next unchecked task with only its blocking input. Update user decisions and owning contracts/guidelines together when behavior changes. Do not let a historical alternative become a second implementation instruction.

Documentation preparation is complete; application completion requires the evidence above.
