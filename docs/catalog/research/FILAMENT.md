# Configurator admin research — Filament

> Historical research evidence. Execute the [final plan](../IMPLEMENTATION_PLAN.md) and [guidelines](../IMPLEMENTATION_GUIDELINES.md); their contracts supersede earlier alternatives here. Dates, versions, access and test observations are snapshots, not fresh verification.

Research date: 2026-09-23. Research only; no implementation or database changes.

This document supports the [living plan](../DECISIONS.md). It distinguishes verified framework/example behavior from proposed adaptations. It is not an executable Filament Blueprint or approval to rebuild. The current conversation has selected fresh database approach DB11A; this research does not perform that transition. Whole-configuration-code conditions are a legacy capability assessment, not a new requirement or a blocker to specifying the already agreed condition types.

## 1. Verified environment and research access

`composer show --direct` and Laravel Boost `application_info` confirmed PHP 8.4, Laravel 13.33.0, Filament 5.8.4, Livewire 4.4.6, and filament/blueprint 2.4.0. The `.ai/rules` directory was absent at inspection time.

Read project instructions, the living plan, planning-filament, filament-development, Filament forms UX and performance skills, and the relevant Blueprint overview/reactive/import/checklist guidance. Used Boost `search_docs` and the current configuration-table schema summary, the FilamentExamples `search_examples` connector, authenticated browser pages, and installed framework source. No example project was installed or executed. No raw subscriber archive or credentials were saved.

| Source | Access actually inspected | Useful mechanism and boundary |
| --- | --- | --- |
| [Children Meals: Multiple Many-to-Many Checkbox Lists](https://filamentexamples.com/project/filament-v4-many-to-many-checkbox-list-in-fieldset) | Full authenticated tutorial plus MCP source for `v4/forms/multiple-checkboxes-for-manytomany-relationship/app/Filament/Resources/Groups/Tables/GroupsTable.php` | Dynamically creates a row/checkbox schema, hydrates matching keyed state with `fillForm()`, and explicitly synchronizes relationships on submit. Demonstrates a matrix-like editor, not configurator mapping semantics. |
| [Parent-child Dependent Dropdowns](https://filamentexamples.com/project/filament-v4-parent-child-dependent-dropdowns) | Full authenticated tutorial plus MCP `ShopForm.php` and resource source | A live parent changes child options; the example resets child state and restores state for editing. Useful lookup mechanics; its reset policy is not the project's explicit-repair policy. |
| [Three-level Dependent Dropdowns](https://filamentexamples.com/project/filament-v4-three-level-dependent-dropdowns) | MCP `CompanyForm.php` source; public page verified through search | Shows a chain of dependent selectors and hydration. Does not provide graph evaluation or restoration semantics for the configurator. |
| [Asset Stock Management](https://filamentexamples.com/project/filament-v4-asset-stock-management) | MCP `ItemImporter.php`, resource actions and related files; public page verified | Concrete `ImportAction`/`Importer` integration. It always creates new items and resolves categories by name; neither is this project's legacy-identity reimport contract. |
| [Filament Repeater: Set Values Manually or From Other Fields](https://laraveldaily.com/post/filament-repeater-set-values-manually-other-fields) | Full browser article, dated 2024-04-19 | Separates initial values, reactive changes and `form->fill()` in mount; uses JSON with an array cast. Its older namespaces and automatic reseeding must not be copied into the chosen default/reference policy. |
| [Complex Product Form: Tabs and v5.8 Defer Schema](https://filamentexamples.com/project/filament-complex-product-form-with-tabs-defer-schema) | Full authenticated tutorial and linked private GitHub test file | A current 5.8 rendering pattern, with tests for deferred markup and validation reveal. Successful association saves are not covered by those example tests. |

Additional MCP source inspected: the mutually exclusive checkbox groups and dynamic checkbox table-columns examples, plus the invoice form's ordered relationship repeaters. These supplied comparison mechanisms, not permission to add their behavior. Search for `defer` returned an unrelated `deferFilters()` example; the current deferred-schema source was reached directly through the authenticated browser instead.

## 2. Requirements the admin must preserve

These come from the conversation/living plan, not from an example application:

- Canonical Attribute + shared Value determines Option identity and its globally unique two-character code. Configurator inclusion, local display overrides, option/default settings and ordering belong to that configurator.
- Codes are manually entered ASCII `A–Z`, `a–z`, `0–9`, exactly two characters, case-sensitive globally. `Aa`, `aa`, and `00` are distinct valid examples. Do not uppercase, numerically cast or automatically allocate them.
- Store the initial default when the first option is included. Later option reordering never changes that default. Code order is configurator-local and independent of UI order.
- Drag rules into priority order, highest first. Priority resolves scalar outcomes; allowed sets still intersect, and advanced exclusions still subtract.
- Conditions are an ordinary AND list with optional one-level AND/OR groups. Stages/items are deferred. The placeholder tab is exactly **Custom**, with no implemented custom behavior.
- Mapping sets are driver/source option sets → allowed target option sets. Partial source coverage is legal; every saved set has at least one source and one target, and a source appears in at most one set per rule. Exclusion remains a separate, secondary authoring path.
- Several leaf Groups can share one Configurator. Assignment is explicit, with no ancestor inheritance. Unassigned products remain visible without configuration.
- Removal must show default/mapping references and require explicit repair. Shared definitions cannot be deleted while referenced. Valid rules may still produce an empty runtime intersection; this is diagnostic/incomplete behavior, not necessarily invalid authoring.
- Catalog discovery filters have their own newest-selection reconciliation. Disabled configurator options cannot be clicked to clear upstream choices. Hiding a configurator attribute makes it inapplicable to selection, completion and code.
- Product administration uses `product_code` for labels and SQL search/sort. `name` is an accessor, not a physical column; `product_name` is for public display. Preserve core columns plus `properties`, `parts`, and `extra_data` JSON.

## 3. Shared definitions, local setup and assignments

Recommended adaptation: expose canonical definitions and local configuration as visibly different editing contexts. A shared Option label should show its Attribute, Value and code so that, for example, two different meanings of “316” cannot be confused. Editing a Configurator should select canonical options and edit its local overrides/defaults, without creating duplicate code records. Show assigned Groups when describing the impact of a shared Configurator edit.

The existing [ConfigAttributeForm](../../../app/Filament/Resources/ConfigAttributes/Schemas/ConfigAttributeForm.php) owns an attribute through `config_profile_id`; [ConfigOptionForm](../../../app/Filament/Resources/ConfigOptions/Schemas/ConfigOptionForm.php) keeps `is_default` and `sort_order` on that option. Those are current POC structures to redesign, not evidence that shared canonical identity/local default separation exists already.

Framework fit:

| Admin concern | Filament 5 component/API | Adaptation required |
| --- | --- | --- |
| Canonical code entry | `Filament\Forms\Components\TextInput`; required, fixed two-character limits and an exact ASCII validation rule | The authoritative case-sensitive global unique constraint belongs in storage as well as form validation. Do not silently change case. Code retirement/edit history remains deferred. |
| Select shared definitions | `Filament\Forms\Components\Select`; constrained search and selected-label resolution | Search and label lookup must use the same allowed scope. Existing [OptionRuleForm](../../../app/Filament/Resources/OptionRules/Schemas/OptionRuleForm.php) has capped searches and scoped label lookup worth retaining conceptually. |
| Local included options/default | Explicit option selector and stored default selector | A single chosen default ID expresses the policy more clearly than unrelated canonical `is_default` toggles. Validate that it remains included. |
| Local association rows | Relation manager, or `Filament\Forms\Components\Repeater` over a suitable `HasMany` association model | A repeater over `BelongsToMany` writes related models, not arbitrary pivot fields. If using a pivot model as rows, it needs its own key and appropriate relationship. |
| Leaf Group assignment | A scoped relation manager using `Filament\Actions\AssociateAction` / `DissociateAction` where the final relationships fit | Add actual application authorization and leaf checks. These actions do not gain model-policy checks merely by appearing in a relation manager. |

Sources: [Select fields](https://filamentphp.com/docs/5.x/forms/select), [Repeater relationships](https://filamentphp.com/docs/5.x/forms/repeater#integrating-with-a-belongstomany-eloquent-relationship), [Managing relationships](https://filamentphp.com/docs/5.x/resources/managing-relationships).

No application `*Policy.php` classes were found in the current `app` tree. Preserve the existing [panel-admission intent](../../../app/Models/User.php:68) and explicitly specify authorization for each write path in the implementation plan. No new role hierarchy, publish step, code immutability rule, or dependency package follows from these recommendations.

## 4. Driver → target allowed-option matrix

The authenticated checkbox example establishes that a matrix can be built from existing Filament components without an added frontend framework. Its data shape is a set of independent relationship booleans. Our editor must preserve named mapping sets and their membership; flattening everything into independent cells would lose that distinction.

Recommended first design to specify:

1. Choose driver Attribute and target Attribute from the current Configurator.
2. Present mapping sets with a source-options selection and target-options selection. Show code alongside each option label.
3. Offer the sketch-style matrix as the same state's editing/overview surface. Make unmapped sources visibly different from an intentionally empty target set, which cannot be saved.
4. Validate nonempty sets, known/included option IDs, correct Attribute ownership and no duplicate source membership across sets. Keep target overlap legal unless a separate approved rule forbids it.
5. Submit the complete mapping edit through one domain validation/save boundary; a checkbox click should not immediately delete or create individual relationships.
6. Use the shared evaluator for Preview & Test, including the actual intersection with other active restrictions and explanatory diagnostics.

`Filament\Forms\Components\CheckboxList`, `Repeater`, `Filament\Schemas\Components\Grid` and `Fieldset` are sufficient primitives for a bounded first editor. If the exact sketch interaction is awkward, a custom `Filament\Forms\Components\Field` with a Blade view can bind the same array via `$getStatePath()` and `$applyStateBindingModifiers()`. That is a supported extension path, not evidence that a ready-made configurator matrix exists. [Custom fields](https://filamentphp.com/docs/5.x/forms/custom-fields)

Filament's repeater `distinct()` and `disableOptionsWhenSelectedInSiblingRepeaterItems()` can help prevent duplicate source use. `fixIndistinctState()` automatically removes other selections; do not use that to silently move an option between mappings. The domain validator must still reject forged/stale state and surface actionable references. [Repeater distinct validation](https://filamentphp.com/docs/5.x/forms/repeater#distinct-state-validation)

The example does not settle maximum matrix dimensions, keyboard behavior, overflow, efficient selection of many options or editing a source/target Attribute after sets exist. Test these with realistic option counts. No benchmark or production-scale matrix claim has been made.

## 5. Rule priority and a shallow condition editor

**Final handoff resolution:** the admin contract requires an authorized owner-locked `reorderTable` operation over a complete permutation. Native `beforeReordering()` is outside the internal write transaction and cannot supply that lock boundary.

Filament 5 directly supports `Table::reorderable('priority', direction: 'desc')`, plus `beforeReordering()` and `afterReordering()`. The installed [CanReorderRecords](../../../vendor/filament/tables/src/Concerns/CanReorderRecords.php:18) writes descending rank values in a database transaction. Use a query scoped to the current Configurator; specify how reorder mode treats filters so a partial visible subset cannot accidentally acquire conflicting priorities. Keep priority distinct from Attribute UI order, Option UI order and code order. [Table reordering](https://filamentphp.com/docs/5.x/tables/overview#reordering-records)

For conditions, a constrained form/repeater with typed sources, compatible operators and value selectors is a better direct fit than treating the table QueryBuilder as the engine's condition representation. Allow one grouping level in the UI and validate the same depth/shape on save. Source choices must include the already agreed selection/product/context capabilities. The parallel read-only legacy assessment found no stored whole-code rule usage; assess that legacy capability separately without adding it as a new requirement or delaying the typed contract for agreed conditions.

Verified caution: Filament's table QueryBuilder supports `maxRules()` and `maxNestingDepth()`, but an oversized submitted tree is ignored and applies no table constraints. That is documented table-filter behavior, not appropriate validation for a persisted configurator rule. The installed [QueryBuilder](../../../vendor/filament/tables/src/Filters/QueryBuilder.php:59) confirms this boundary. Do not serialize arbitrary table constraints directly into engine rules. [Query-builder limits](https://filamentphp.com/docs/5.x/tables/filters/query-builder#limiting-the-size-of-the-rule-tree)

## 6. Reactive state and reference-safe saves

Use `Filament\Schemas\Components\Utilities\Get` and `Set` with `live()` where a selection changes the form. In 5.8.4, setting another field with `$set()` does not invoke that field's `afterStateUpdated()` hook unless `shouldCallUpdatedHooks: true` is requested. Do not rely on a chain of UI hooks as the engine or as the save validator. [Field lifecycle](https://filamentphp.com/docs/5.x/forms/overview#field-updates)

The dependent-select example's automatic reset is appropriate for its location form. Here, changing included options or mapping ownership can invalidate references. Show the exact affected default/mapping and require repair; changing the editor's visible options must not erase the evidence needed to repair it.

Installed lifecycle evidence identifies an important enforcement point:

- [EditRecord::save()](../../../vendor/filament/filament/src/Resources/Pages/EditRecord.php:159) authorizes access, calls `getState()`, then `mutateFormDataBeforeSave()` and the record update.
- [Schema::getState()](../../../vendor/filament/schemas/src/Concerns/HasState.php:450) validates, calls the supplied `afterValidate` callback containing page `afterValidate`/`beforeSave`, then saves relationships before returning.
- [Repeater::saveToRelationship()](../../../vendor/filament/forms/src/Components/Repeater.php:1002) can delete related rows missing from submitted state.

Consequently, a reference check only in `mutateFormDataBeforeSave()` is too late to prevent relationship mutations. Specify reference/inclusion/default validation before those writes, ideally through the same transactional domain operation used by every entry point. Recheck current references at mutation time; visibility or a disabled Delete button alone is not enforcement. Whether the final implementation uses bound relationship fields or explicit domain persistence must be deliberate. This is a lifecycle finding, not a claim that the current application has failed this future requirement.

## 7. Deferred tabs: useful rendering option, separate semantics

The authenticated [5.8 example](https://filamentexamples.com/project/filament-complex-product-form-with-tabs-defer-schema) wraps heavy child schemas with `Schema::make()->components(...)->deferLoading()`, leaves its frequently used tab eager, and gives deferred schemas unique keys. It also defers repeater item bodies. The example reports smaller initial HTML; those are the publisher's measurements, not measurements of this application.

The exact [ProductDeferredSchemaTest.php](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/main/v4/forms/products-tabs-complex-form/tests/Feature/ProductDeferredSchemaTest.php) was read in authenticated GitHub. It checks eager/deferred page rendering, absent deferred markup, validation of a never-loaded required field and error-driven schema reveal. It does **not** test a successful save preserving or updating associations in unopened tabs.

Installed 5.8.4 source adds the following evidence:

| Question | Verified behavior |
| --- | --- |
| What is deferred? | [Schema::renderEmbeddedHtml()](../../../vendor/filament/schemas/src/Schema.php:169) returns a loading placeholder until loaded. This is not a blanket guarantee that all schema construction, hydration, state or queries are skipped. |
| Does an unvisited field validate? | [CanBeValidated](../../../vendor/filament/schemas/src/Concerns/CanBeValidated.php:75) traverses child schemas without a deferred-status exclusion. This agrees with the example test. |
| Can validation reveal it? | [InteractsWithSchemas](../../../vendor/filament/schemas/src/Concerns/InteractsWithSchemas.php:219) matches error state paths and marks schemas loaded. Layout components with a shared broad state path can reveal more than one schema. |
| Are relationships ignored while unvisited? | [BelongsToModel](../../../vendor/filament/schemas/src/Concerns/BelongsToModel.php:35) traverses relationships without checking deferred render status. Hidden/disabled relationship-save settings are separate. |

Recommendation: keep the complete schema and hydrated state available, and consider rendering deferral only for demonstrated heavy editors. Do not conditionally omit unvisited tabs from the schema, substitute empty arrays for unloaded associations, or equate an inactive tab with a domain-hidden Attribute. The latter has the much stronger E04 runtime meaning.

Before adopting it, verify: unchanged save without opening association tabs; changing only the eager tab preserves associations/defaults; invalid unvisited values reject save and reveal errors; opening/editing/closing a deferred tab saves correctly; failed reference validation causes no relationship writes. Measure initial and aggregate requests/bytes, not initial HTML alone. [Official deferred schema documentation](https://filamentphp.com/docs/5.x/schemas/overview#deferring-the-loading-of-a-child-schema)

## 8. Product management and import integration

A Product resource can show core fields and structured views/editors for the three JSON collections. Use known field shapes for known facts; do not treat a generic editable KeyValue field as validation for every imported value. Parts retain numbered positions. Unknown extras remain preserved and escaped when displayed. The accessor does not make `name` a valid SQL search/sort column.

The stock-management example proves the ordinary `ImportAction` wiring, not the catalog migration contract. Our importer must resolve Groups before Products using legacy IDs; reuse internal IDs on reimport; fill all three JSON collections; retain application-owned fields/keys; apply intentional source blanks; and report missing products without deleting them. The exact map is now complete in the data/engine contract. The final handoff chooses CLI-first full-file atomic apply; the ordinary Importer API details below are adaptation evidence, not the selected persistence pipeline.

Important version-verified integration facts:

- `Filament\Actions\Imports\Importer` supports `resolveRecord()`, per-column `fillRecordUsing()` and lifecycle hooks. Its installed [row pipeline](../../../vendor/filament/actions/src/Imports/Importer.php:66) is remap → cast → resolve record → validate → fill → save. Normalizing an identity only in `beforeValidate()` would occur after record resolution; use one normalization contract consistently before resolution and validation.
- The stock example's always-new-record resolver is unsuitable for reimport. Its relationship-by-name resolution is unsuitable for legacy Group identity.
- Do not use `ignoreBlankState()` for source-owned fields whose intentional blanks must win.
- Import does not automatically perform per-record policy checks. Put actual authorization in the importer/domain save path, and make failures reportable; merely showing the Import button does not enforce individual mutations.

Source: [Filament 5 import documentation](https://filamentphp.com/docs/5.x/actions/import). These are framework integration findings, not approval to choose an import job architecture or modify dependencies.

## 9. Focused verification targets and remaining gaps

The research supports the proposed components and highlights lifecycle traps. It does not replace implementation verification.

| Area | Discriminating check |
| --- | --- |
| Canonical codes | Save `Aa`, `aa`, `00`; reject duplicate exact `Aa`, wrong length and non-ASCII. Verify database enforcement, not form behavior alone. |
| Local setup | Two Configurators share canonical Options; changing one local label/default/order leaves the other unchanged. UI reorder preserves default and generated code order. |
| Matrix | Partial source coverage adds no restriction; empty source/target and duplicate source membership reject save; overlapping targets are not arbitrarily rejected. |
| References | Remove an option used as a default and in a mapping; show both references, require repair, and verify no partial relationship mutation on failure. |
| Priority and conditions | Drag changes scalar priority; intersections remain independent of order. Reject forged over-depth/unknown condition shapes instead of treating them as no restriction. |
| Assignment | Shared leaf assignments work; parent assignment and foreign/out-of-scope IDs reject where the final authorization/structure requires it. Unassigned public products remain visible. |
| Deferred editors | Successful unopened-tab save preserves associations; hidden/disabled semantics stay separate; validation reveal and failed-save rollback are exercised. |
| Import | Reimport reuses internal identities; intentional blanks win; app-only JSON keys survive; imports with invalid rows do not silently alter unrelated groups/records. |
| Preview | Admin/public evaluation agrees for the same Product, context and selections, including hidden attributes and empty allowed intersections. |

Remaining design work is bounded: exact association schema and editor layout, realistic matrix size/keyboard checks, typed sources/operators for agreed conditions, the separate legacy whole-code capability assessment, explicitly specified write authorization preserving current panel-admission intent, and successful save tests for deferred relationship editors. No reviewed example supplies those domain decisions automatically.
