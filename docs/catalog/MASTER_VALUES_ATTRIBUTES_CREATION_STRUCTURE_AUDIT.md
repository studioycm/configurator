# Catalog creation flow: structure audit and implementation decisions

**Scope:** Master Value batches, shared Attribute creation/editing, Configurator Add Attributes/Add Values, table defaults, and local Value subtitles. This is the requested audit of the proposed implementation, not a whole-application refactor or permission to implement it.

**Snapshot:** 2026-10-23; `/Users/studioycm/Herd/configurator`, `master`, HEAD `958af03ebcd66fcc5e8c0ecb4e1c420236089197`. PHP 8.4.27, Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7, Blueprint 2.4.0, Pest 5.3.0, FilaCheck Pro 1.2.7. Versions rechecked through installed Composer packages. `.ai/rules` is absent. Concurrent documentation changes include `docs/README.md`, image-assignment and Preview & Test plans, and the third architecture audit; they are outside this edit.

**Method:** Applied the Laravel Daily structure audit adapter and its Laravel architecture references, Filament development/Blueprint guidance, Boost documentation, installed framework source, and the existing architecture findings register. Re-audited on 2026-10-23 after correcting the Filament Examples guidance: retained `v4/` paths identify the shared Filament 4/5 collection and do not make an example unsuitable for Filament 5. Actual source patterns are eligible; custom Livewire 4 behavior and exact extension points are checked separately. No subscription course implementation advice is attributed to Laravel Daily. The interactive prototype was edited and browser-checked during the earlier pass; this re-audit inspected source/documentation and did not execute Laravel runtime behavior.

The final [implementation plan](MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_PLAN.md) incorporates the decisions below. Source line references refer to this snapshot.

## Findings and dispositions

### CA01 — Whole-row selection needs a picker-scoped adapter

**Medium; confirmed source behavior and implementation choice.** `app/Livewire/Catalog/WorkspaceTablePicker.php:13-15` delegates to the native TableSelect component. Installed `vendor/filament/forms/src/Components/TableSelect/Livewire/TableSelectLivewireComponent.php:84-90` enables selection and binds it to `state`. `vendor/filament/tables/resources/views/index.blade.php:2321,2373` renders the row and checkbox handler separately. `selectable()` alone does not provide the requested row click, and there is no verified `selectableOnClick()` API to put in this plan.

**Decision:** Keep native checkbox state, pagination, selection limits, disabled behavior, and `fi-selected` presentation. Attach a small delegated Alpine handler to the existing `WorkspaceTableSelect` wrapper, whose `extraAttributes` are already merged at `app/Filament/Forms/Components/WorkspaceTableSelect.php:25-31`. A noninteractive click within a record row invokes its enabled native checkbox once. Checkbox/label/button/link/input clicks retain their own behavior; text selection, nonprimary clicks, and disabled rows are ignored. No table vendor view, public Livewire toggle endpoint, separate selected-ID store, or global listing-row behavior is needed. Pending Master choices use native CheckboxList labels with full-row targets.

**Verification required:** Actual embedded pickers for Masters, Attributes, and shared Values; row text/space versus direct checkbox versus Space key; search/pagination/remount; disabled/max selections; unrelated links/actions and default cells. The prototype's Master picker and the existing Attribute/Value label rows were checked in Chrome in this audit. Implementation coverage belongs to Tasks 1–3 and 6.

### CA02 — The optional-column contract was not mapped to concrete frontend files

**Medium; confirmed plan gap, now resolved.** The previous Task 1 deferred selection of its UI files. Installed `Repeater::toTableEmbeddedHtml()` at `vendor/filament/forms/src/Components/Repeater.php:1905` supplies native field rendering, item keys, add/delete actions, and state handling. Repeater TableColumn provides no record-table column chooser. Filament visibility can also affect dehydration/validation, contrary to the requirement that hidden Tags and Specification still save.

**Decision:** Retain a native table Repeater with four fixed field columns and no reordering. Add a scoped Columns toolbar view, a small Alpine presentation/focus module, and scoped theme rules. Hide cells using presentation attributes/CSS only; keep all four fields mounted, validated, and dehydrated. Do not copy a vendor repeater view or create a new form-state engine. The plan now names the exact files, owner methods, DOM hooks, error-reveal behavior, preference scope, and Add 5/Add 10 actions.

**Verification required:** Ten populated names plus blank appended rows; hidden fields retain values and fail validation when invalid; error reveals the correct field; column choices survive resizing/remount; native mobile labels and keyboard order. Task 1 owns this extension; the same factory is reused in Tasks 2–3.

**Re-audit refinement:** Bind Add 5/Add 10 to the owning Repeater as hint Actions, not sibling schema actions whose callback has no guaranteed Repeater owner. Match installed `Repeater::getAddAction()` at `vendor/filament/forms/src/Components/Repeater.php:216-243`: use raw UUID-keyed state, fill only the new child schemas and run the normal after-update/partial-render lifecycle. One small private UI helper serves Add row/5/10, with a server-enforced total-row limit. The previous broader getState()/state() wording is superseded by this exact installed lifecycle; no deployed defect is claimed.

### CA03 — Blank-row filtering can misaddress errors and nested reuse can create duplicates

**High; supported implementation risk, not an existing runtime defect.** The proposed writers ignore completely empty rows. `ConfiguratorFormErrors::rethrow()` at `app/Filament/Resources/Configurators/Schemas/ConfiguratorFormErrors.php:36-54` maps list positions against the current raw schema state. Filtering or reindexing rows before that mapping would shift errors after a blank row. A pending Master selected by two Attribute drafts must also be created once across the outer operation.

**Decision:** Submit complete ordered lists, retaining a ref-to-original-index map while normalizing. Return field errors at original submitted positions; let the existing adapter translate those positions into repeater UUID paths. `CreateMasterValues` receives the complete pool and an optional referenced-ref set so nested callers need not pass a filtered/reindexed pool. Create all referenced Masters once before looping over Attributes. Resolve references explicitly to integer IDs; do not let pending refs enter SQL or trust display labels as identity. No generic DTO/repository/error-mapper layer is required.

**Verification required:** Blank first/middle rows followed by a late duplicate; removal/reordering before submission; one pending Master reused under two Attributes; a nonfirst pool row failing canonical validation; rollback without losing UI state. Tasks 1–3 carry these cases.

### CA04 — Nested canonical creation must begin after the aggregate lock prefix

**High; supported lock-composition risk, deadlock not reproduced.** `SaveConfiguratorDefinition::save()` at `app/Actions/SaveConfiguratorDefinition.php:40-43` locks Configurator, then global context, then canonical records. `change()` at `:152-162` invokes its callback after the Configurator lock but before the context lock. Creating associations in that callback would acquire canonical locks too early. `AddAttributeValues::handle()` at `app/Actions/AddAttributeValues.php:19` already locks its Attribute, and canonical writers own their existing constraints and authorization.

**Decision:** Acquire `CatalogContextSettings::current(lock: true)` inside `change()` immediately after locking the Configurator and before evaluating the callback. Retain the authoritative aggregate writer and fresh full-draft patch. New composition creates definitions/associations; it must not rename an existing shared definition inside that callback. Keep one top-level transaction, no retries around only part of the graph, and no notifications before it returns.

**Lineage:** R02 retained and narrowed to this composition; R03 retained. The separate audit's S06 tested-save/import lock inversions remain a separate risk and are not claimed fixed by this change. Its own source explicitly says no deadlock was reproduced. Coordinate if that work lands first; do not silently absorb an importer/status refactor.

**Verification required:** Actual-engine isolated MySQL rollback, Code races, overlapping inclusion/canonical edits, and lock-order traces. SQLite cannot verify these locks. Task 3 establishes the boundary; Task 6 records concurrency evidence and remaining external risks.

### CA05 — A separately saved default must leave the settings save contract

**High; supported implementation risk.** The existing read-only Default icon is at `app/Filament/Resources/Configurators/RelationManagers/OptionsRelationManager.php:81`. `ConfiguratorAttributeForm.php:88` still supplies a default Select, and `AttributesRelationManager::saveInclusion()` at `:571` accepts that field in a broader patch. Turning the icon into a save action while leaving a stale default in form state would let a later settings save undo the change.

**Decision:** Patch the default alone through a fresh aggregate change, validate the clicked local inclusion's membership, and remove that field from the target workspace's general settings payload/hash. Reconcile only default baseline metadata; preserve selected/stashed Attribute and Value drafts. Retain the legacy standalone form's default field where there is no replacement table. Replace `reviewComponents()`' positional `array_pop` coupling with explicit component composition.

**Verification required:** Unsaved local name or Attribute settings → change Default → switch editor rows → save the original draft. The new default and draft must both survive. Task 4 owns this change; it needs no new general editor framework.

### CA06 — Subtitle intent crosses more boundaries than its field label

**High; confirmed source dependency and compatibility requirement.** `ConfiguratorDefinitionCompiler.php:73,85` has strict keys and the present Master-name fallback. `ConfiguratorDefinitionLoader.php:150`, `SaveConfiguratorOption.php:14`, `ConfiguratorEditorChanges.php:28,150`, and `BatchCatalogChanges.php:58,247` independently transport or compare local text. The runtime template also treats null and equality differently across input types. Adding only a field/column would lose mode on some saves or render empty second lines.

**Decision:** One additive `subtitle_mode` column and one finite enum with None/Custom/Master. Normalize at the authoritative compiler/writer boundary, carry it through all serializers, partial patches, signed captures, duplication and batch edits, and keep the existing display-value wire/Rules contracts. Existing and new blank values become None, per the user's explicit choice. Master mode resolves fresh canonical names at evaluation time; do not copy names or invent cache invalidation. Final-label comparison happens after Rules.

**Lineage:** R04's signed-capture boundary is protected; this feature does not split that service or claim to resolve S04's separate ambient-actor concern. Task 5 enumerates every required transport and regression case.

### CA07 — Keep the existing UI factories and writer ownership

**Medium; retained design boundary, not a discovered defect.** `ValueForm::configure()`, `AttributeForm`, the three selection-table factories, and `SuggestedIdentifier::fields()` are legitimate static UI factories. `SaveCanonicalDefinition`, `SaveCanonicalOption`, `AddAttributeValues::saveDefinition()` and `SaveConfiguratorDefinition` already provide reusable authorized writes. Native action validation/schema relationship persistence can precede the action callback (`vendor/filament/actions/src/Concerns/InteractsWithActions.php:335`), so pending relationship fields cannot safely be used as staging.

**Decision:** Keep resource-local UI composition, scalar drafts, and four focused creation/addition operations that call existing writers. No application operation imports a Filament schema. No `relationship()` or immediate `createOptionUsing()` writes in staged batches. Concrete injection needs no provider/interfaces/repositories. Use the existing identifier algorithm with pending reservations and explicit creation context; scope flash events by absolute state path. Add an early authorization hook before database-backed action validation, then reauthorize/recheck owner and eligible IDs in operations.

**Lineage:** R03 retained; R08 narrowed to small supported extensions; R10 retained without a universal editor trait. R05–R07's broader tag/search/presentation reorganization is deferred. The plan preserves current discovery, signed captures and serializer names.

### CA08 — Two existing audit risks affect implementation instructions

**Medium; refreshed prerequisites, not feature scope expansion.** S11 remains visible in `SaveCanonicalDefinition.php:39-40`: canonical tags are validated without the UI's trimming. New Master creation must normalize input before calling that writer, so direct operation callers agree with the batch UI. Use the existing trim/distinct field rules; report a duplicate after trimming instead of silently deduplicating. A global import/tag-writer refactor remains separate.

S07 remains visible in `tests/CreatesApplication.php:16`: the default test guard checks the connection name, so inherited connection URLs can bypass its intended isolation. `tests/bootstrap-mysql.php` does enforce its literal disposable target. Before running feature tests, explicitly clear URL/cache overrides, select SQLite `:memory:`, and ensure there is no cached application configuration; use only the guarded disposable MySQL configuration for MySQL checks. Do not clear the developer's cache or edit shared test infrastructure implicitly.

**Verification required:** Boundary tag normalization tests and a clean, explicit test environment. The plan specifies these prerequisites; no tests or safety fixes were executed during this audit. The separate S01–S12 program is not absorbed into catalog creation.

## Coverage, limits, and final disposition

Inspected the creation schemas/pages, embedded selector/component, selection queries, canonical and aggregate write paths, error mapping, local default form/payload boundaries, subtitle compiler/loader/editor/batch/runtime transports, relevant JS/browser-profile/dialog owners, package source and existing audit/test setup. Consulted the existing focused tests as planning evidence; no generators, migrations, Laravel feature tests, DB writes, dependency installs, or app changes ran here. No claims of performance improvement, race safety, or end-to-end app persistence are made.

The audit changes the plan's specificity and boundaries rather than its approved UX. Implementation proceeds Master Values → shared Attributes → Configurator additions → independent default control → subtitle intent → connected acceptance. All eight findings have a decision, owning task, and verification requirement in the final plan. Whole-row selection has been added to the prototype; Laravel implementation remains pending separate authorization.

## Re-audit after correcting Filament Examples guidance

**Current checkout:** HEAD remains the snapshot above. Concurrent Product Fields application/test/migration edits appeared during the re-audit and were left untouched. The overlapping ConfiguratorDefinitionCompiler and BatchCatalogChanges diffs were inspected: they adjust Product-property key validation/choices, while the quoted local Value fields and writer contracts remain present. This re-audit itself changes skills and planning documents only; it does not claim that the shared checkout is otherwise free of application changes.

**Disposition:** The earlier sentence treating returned `v4/` source as version-4-only evidence was too restrictive and is superseded. [Filament's own v5 announcement](https://filamentphp.com/insights/danharrin-filament-v5-blueprint) explains that the major-version distinction introduces Livewire 4 support while Filament functionality is shared. The [Filament Examples catalog](https://filamentexamples.com/projects) groups its examples as 4/5 and includes an explicitly [v5.8 deferred-schema example](https://filamentexamples.com/project/filament-complex-product-form-with-tabs-defer-schema). No separate v5 folder or inspected Composer manifest is required to reuse ordinary shared patterns.

The revised research pass inspected actual MCP source for the reusable invoice wizard/field factories, advanced repeaters, embedded quote-product field/picker, and the larger LMS example. Section 1 of the implementation plan records exact example paths, public links, access limits, and each adopted pattern/adaptation. One TableSelect search returned a generic public table; that result was not used to infer picker behavior. The deferred-schema example was inspected through its public description/ProductForm excerpt; its full repository and runtime were not inspected. No downloaded example or dependency installation was needed.

| Prior finding | Re-audit outcome and reason |
| --- | --- |
| CA01 | Retained. The installed picker still delegates selection to native checkboxes. Folder classification does not create a missing row-click API; keep the small scoped adapter and its interaction tests. |
| CA02 | Narrowed/refined. Native repeaters remain the right foundation. Specify bound hint Actions and the installed raw-state/default lifecycle for row appends. Optional column hiding remains presentation-only because it must preserve hidden data and validation. |
| CA03 | Retained. Original error positions and one pending-Master pool are application identity/state contracts, independent of example framework labels. |
| CA04 | Retained as a supported risk. Context-lock ordering and whole-operation rollback are still necessary before composing canonical creation inside the aggregate callback. No deadlock was reproduced in either pass. |
| CA05 | Retained. The default column's independent save still requires removing stale default intent from the settings save; example reuse does not change local-ID ownership. |
| CA06 | Retained. Explicit subtitle intent still crosses loader/compiler/writer/signed-capture/batch/runtime boundaries, with blank meaning None. |
| CA07 | Strengthened by accepted examples. Invoice factories show the intended reuse; quote Modelable/table components show valid embedded composition. Keep our existing selector rather than duplicating it. Do not copy the quote's delete/recreate relationship strategy into an aggregate with stable inclusion IDs. |
| CA08 | Retained and scoped. Tag normalization at the new shared creation boundary and isolated test invocation are still prerequisites; neither requires a broad unrelated refactor. |

**Focused Livewire 4 review:** Installed TableSelect exposes one `#[Modelable] state`, which the existing wrapper binds through `Livewire::mount`; Livewire 4's documented Modelable child binding supports that shape. Do not add a second selection store or an indiscriminate `.deep` modifier. Installed Livewire 4.4.7 contains `interceptMessage`, `onMorphed`, and interceptor cleanup; reuse the existing component-filtered browser-profile pattern. Plan acceptance now explicitly covers parent/child morphs, remounts, sequential blur/add requests, stable keys, and listener cleanup. These are inspected compatibility choices, not passed app-browser tests.

**Guidance repair:** Corrected and synchronized research, Laravel architecture, and Laravel Daily structure-audit guidance in project/global copies, plus the related global Filament plugin guidance: 12 files across four skills. The rule now evaluates actual custom Livewire/plugin/API behavior while keeping shared 4/5 patterns eligible. Metadata and local-reference validation passed through the installed Symfony YAML parser; the Python validator could not run because PyYAML is absent, and no dependency was installed to run it.

**Final result:** Retain all three flows and their reusable component/writer boundaries. Amend source classification, record concrete accepted examples, tighten native append/action ownership, and add focused Livewire 4 acceptance instructions. No new package, generic form engine, class/table rename, application implementation, DB change, commit, or push was introduced by this re-audit.
