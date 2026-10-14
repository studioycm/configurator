# Attribute Value Naming Implementation Plan

> **For agentic workers:** Implement reviewed tasks using `superpowers:executing-plans`. Use subagents only if the operator selects that execution method. Checkboxes track implementation, not planning. This document authorizes no runtime changes, database mutation, commit, push or deployment.

**Goal:** Replace application-domain Option terminology with Attribute Value, first in the interface and then throughout the active domain, while preserving every existing permission and record relationship.

**Architecture:** The shared association remains a first-class Eloquent model: Attribute + Master Value + shared code/status. Its Configurator inclusion remains a separate model with local ordering and presentation. Navigation visibility is independent from access. Phase A changes presentation only; Phase B coordinates the application, schema and versioned input rename.

**Tech stack:** PHP 8.4.22; Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7, Blueprint 2.4.0. Verify installed versions again before execution. The default test runner uses SQLite; schema acceptance also uses the guarded disposable MySQL runner.

**Spec:** The operator's 2026-10-14 naming/access decisions, recorded below, and [ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md](ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md), section 19. This plan supplements the existing admin workspace plan and does not execute the pending Product-data foundation.

## Confirmed decisions and constraints

- UI wording first, then a complete active-domain rename.
- Hide the standalone resource's navigation link for tidiness. This is **not** an access/permission restriction.
- Keep direct resource pages, global search, related pickers, shared editors, count drawers, local overrides, batch/status actions and domain saves under their existing `manage-catalog` checks.
- Defer SuperAdmin/user-type handling. Do not add a User flag, role, permission package, account allowlist, policy or new access gate.
- Initial navigation default: hide the link for all currently authorized admins. There is no SuperAdmin exception yet; an eventual exception changes navigation visibility only.
- Preserve the new Attributes/Master Values labels, tag-button filter, inline search/counts, quiet breadcrumbs, header layout, count drawers and split editors.
- Keep Master Value distinct from Attribute Value. `Value`/`value_id` remain the existing Master Value model/reference in this scope.
- Keep shared Attribute Value IDs distinct from Configurator Attribute Value inclusion IDs. Defaults, rule references and selections reference **local inclusion IDs**, not shared IDs.
- Preserve codes, uniqueness, status/visibility semantics, defaults, rule results, locks, transactions, draft protection, notification timing and cache invalidation owners.
- Do not convert the association into an Eloquent Pivot/belongsToMany relationship or create a second editable authority.
- Framework/general vocabulary is excluded: Filament `->options()`, HTML `<option>`, `OptionStateCast`, arbitrary Select choices, Territory/Application choices and unrelated Product JSON values remain valid terminology.
- Historical migrations, archived research and legacy source/fixture headings retain their historical names. Active application references use the new vocabulary; explicit legacy decoders/URL redirects are the compatibility boundary.
- Follow AGENTS.md and applicable `.ai/rules` before edits; `.ai/rules` is absent at this checkpoint. No dependencies or base folders are added.
- Preserve the other session's dirty `tests/Feature/Filament/TableConfigurationStandardsTest.php`. Incorporate its explicit label assertions when implementing, without replacing its handoff changes.
- No publication or production migration is part of this planning authorization.

## Review focus

1. Hiding navigation must leave direct pages, global search and contextual shared editing usable. Task A3 owns positive and negative access tests.
2. A shared ID numerically equal to another local inclusion ID must never be substituted into a default, rule or selection. Tasks B1/B3 own asymmetric-ID tests.
3. Existing literal codes, default membership, inactive/hidden records and dependency blockers must survive schema/name changes. Tasks B1/B4 own roundtrip and denial tests.
4. Names derived from enum values, count-list categories or auto labels must not leak old domain wording. Tasks A1/A2 own rendered labels and messages.
5. Renamed component/event/purpose keys must not silently discard unsaved drafts or redirect to unfiltered/wrong records. Tasks B3/B4 own compatibility and deployment checks.

## Current domain and target names

| Current | Target | Contract |
| --- | --- | --- |
| `App\Models\Value` | Unchanged; UI Master Value | Reusable UI Name/Specification/tags |
| `App\Models\Option` | `App\Models\AttributeValue` | One Attribute/Master Value pair, globally unique exact code, active/hidden status |
| `App\Models\ConfiguratorOption` | `App\Models\ConfiguratorAttributeValue` | One shared Attribute Value included in one Configurator Attribute, local order/overrides |
| `App\Models\RuleConditionOption` | `App\Models\RuleConditionAttributeValue` | Condition reference to a Configurator-local inclusion |
| `App\Models\RuleEffectOption` | `App\Models\RuleEffectAttributeValue` | Effect reference to a Configurator-local inclusion |

`Option` currently extends `Model`, has belongsTo Attribute/Value and hasMany inclusions. A unique `(attribute_id, value_id)` pair establishes association identity. The code column reserves three characters so the MySQL CHECK can reject an overlong value before truncation; the valid code contract is exactly two ASCII letters/digits. Preserve that schema detail, case-sensitive comparison and both unique indexes.

## UI vocabulary

| Surface/current wording | Target |
| --- | --- |
| Resource singular/plural, page title, breadcrumb, navigation/global-search group | Attribute Value / Attribute Values |
| Create/Edit/Delete Option | Create/Edit/Delete Attribute Value |
| Shared Option section | Shared Attribute Value |
| Options resource `value.label` column | UI Name |
| Options resource `attribute.label` column/filter | Attribute, unchanged |
| Attributes resource `options_count` | Values |
| Master Values resource `options_count` | Attributes; the unique pair means one distinct Attribute per row |
| Configurator Attributes `options_count` | Values |
| Shared Options picker/drawer | Shared Attribute Values |
| Options using this Master Value | Attribute Values using this Master Value |
| Local Option uses | Configurator uses |
| Included Options | Included Values |
| Shared/Canonical Option field | Shared Attribute Value |
| Local option label | Local UI Name |
| Shared Value column | Shared UI Name |
| Default Value column | Default UI Name |
| Include Options / Include option | Include Values / Include value |
| Save/Close/Reload saved option in the local editor | Save/Close/Reload saved value |
| Allowed-option sets | Allowed-value sets |
| Included Options / exact codes | Included values / exact codes |
| Target Options / Selected Options | Target values / Selected values |
| Legal Options, unavailable/removed Option, Options saved | Legal values, unavailable/removed value, Values saved |
| Canonical Options in usage reports | Shared Attribute Values |

Within a selected Attribute, the short word Values has a clear parent context. Use the full Attribute Value term on the standalone resource and wherever Master Value versus association could be ambiguous. Include the Attribute/context in existing drawer/editor headings; do not add duplicate resource/table headings.

## Phase A — UI wording and navigation only

**Delivery:** a useful independent change. All PHP class/relationship names, table/column names, route slugs, input/state keys, event names and enum backing values remain unchanged.

### A1 — resource and contextual table/form labels

**Modify:**

- `app/Filament/Resources/Options/OptionResource.php`, `app/Filament/Resources/Options/Schemas/OptionForm.php`, `app/Filament/Resources/Options/Tables/OptionsTable.php`, `app/Filament/Resources/Options/Pages/EditOption.php`.
- `app/Filament/Resources/Attributes/Tables/AttributesTable.php`, `app/Filament/Resources/Attributes/RelationManagers/OptionsRelationManager.php`.
- `app/Filament/Resources/Values/Tables/ValuesTable.php`.
- `app/Filament/Resources/Configurators/RelationManagers/AttributesRelationManager.php`, `app/Filament/Resources/Configurators/RelationManagers/OptionsRelationManager.php`, `app/Filament/Resources/Configurators/Schemas/ConfiguratorAttributeForm.php`.
- `app/Filament/Resources/OptionSelectionTable.php`, `app/Services/ItemLists.php`.

**Resource:** existing `App\Filament\Resources\Options\OptionResource`; no scaffold. [Resource labels](https://filamentphp.com/docs/5.x/resources/overview#customizing-the-plural-model-label).

```php
protected static ?string $modelLabel = 'Attribute Value';
protected static ?string $pluralModelLabel = 'Attribute Values';
protected static ?string $slug = 'options'; // Phase A keeps existing URLs.
```

**Columns:** `Filament\Tables\Columns\TextColumn` and existing `App\Filament\Resources\ItemCountColumn`; [text columns](https://filamentphp.com/docs/5.x/tables/columns/text). Change only explicit labels according to the vocabulary table. Keep `value.label`, `options_count`, `configurator_options_count` and drawer keys unchanged. Search scope, constraint labels and Columns manager derive display labels from those same column objects.

**Fields:** existing `Filament\Forms\Components\Select`/`TextInput`, existing `Filament\Schemas\Components\Section` and `App\Filament\Resources\FormOrderControls`; [Select](https://filamentphp.com/docs/5.x/forms/select), [schemas](https://filamentphp.com/docs/5.x/schemas/overview). Only labels/headings/hints change. All required/integer/length/regex/ownership validation, query limits, state paths and dehydration remain unchanged.

**Actions:** existing `Filament\Actions\Action`; [actions](https://filamentphp.com/docs/5.x/actions/overview). Location stays in the current table/header/editor/footer. Visibility and authorization retain existing rules; labels/tooltips use the vocabulary table. Save/include/remove behavior and notifications remain at existing domain boundaries.

**Filters:** existing `Filament\Tables\Filters\SelectFilter` and shared QueryBuilder; [select filters](https://filamentphp.com/docs/5.x/tables/filters/select). Attribute/Status/Visibility labels remain appropriate; derived column constraints reflect the renamed display labels. No filter-state/key/query change.

- [x] Add/update explicit expected labels in the handoff test, preserving its separate existence/label assertions and all unrelated checks.
- [x] Verify resource title/breadcrumb/global-search group, shared/contextual headings, action tooltip labels and count-drawer titles independently; do not derive expected wording from the implementation.
- [x] Apply the copy map; preserve the compact layout and existing widths/search/filter semantics.
- [x] Run `php artisan test --compact tests/Feature/Filament/TableConfigurationStandardsTest.php tests/Feature/Filament/ItemListDrawerTest.php tests/Feature/Filament/ItemDrawerEditorsTest.php tests/Feature/Filament/CanonicalDefinitionsTest.php`.

### A2 — rule choices, usage/blockers, notifications and diagnostics

**Modify:** `app/ConditionSource.php`, `app/RuleEffectKind.php`, `app/RuleTargetScope.php`, `app/Filament/Resources/Configurators/Schemas/ConfiguratorRuleForm.php`, `app/Filament/Resources/DependencyActions.php`, `app/Actions/SaveCanonicalOption.php`, `app/Actions/SaveCanonicalDefinition.php`, `app/Actions/SaveConfiguratorOption.php`, `app/Actions/SaveConfiguratorDefinition.php`, `app/Services/ConfiguratorDefinitionCompiler.php`, `app/Services/ConfiguratorEngine.php`; views `resources/views/filament/resources/canonical-usage.blade.php`, `resources/views/filament/resources/configurators/attributes.blade.php`, `resources/views/livewire/catalog/configurator-preview.blade.php`, `resources/views/filament/forms/components/mapping-sets-field.blade.php` and `resources/views/filament/forms/validation-summary.blade.php` when their own text names these records. Preserve the generic validation-summary renderer.

**Enum interface:** implement `Filament\Support\Contracts\HasLabel`, `public function getLabel(): string`, while retaining every existing case/backing value. [Enum labels](https://filamentphp.com/docs/5.x/advanced/enums#using-the-enum-label-with-form-field-options).

| Persisted value kept in Phase A | Human label |
| --- | --- |
| `SelectionOption` | Selected value |
| `SelectionCode` | Selected code |
| `ProductProperty` | Product property; leave this separate Product-data work untouched |
| `Territory`, `Application` | Territory, Application |
| `AllowOptions`, `ExcludeOptions`, `HideOptions`, `DisableOptions` | Allow values, Exclude values, Hide values, Disable values |
| `HideAttribute`, `SetLabel`, `SetDisplayValue`, `SetHint` | Hide Attribute, Set label, Set display value, Set hint |
| Rule target `Attribute`, `Options` | Whole Attribute, Selected values |

Retain the existing source-choice grouping between Agent dashboard and Future public catalog. Replace domain enum `Str::headline($case->value)`/raw-value display with `getLabel()`; do not change parsing, predicates, defaults or effect dispatch. Render effect names in diagnostic prose using their human labels. Keep machine diagnostic codes/trace payload keys unchanged.

Dependency buttons currently concatenate raw report categories such as `View options`. Use an explicit display map for options → Shared Attribute Values, inclusions → Configurator uses, defaults → Stored defaults, rules → Blocking rules. Keep report keys and list routing unchanged.

- [x] Check rendered rule choice labels and diagnostic text while asserting the backing values/compiled effects are unchanged.
- [x] Update shared/local save, rejection and deletion-blocker wording without changing validation error keys or action ordering.
- [x] Test current code/default/selection outcomes, local draft preservation, dependency links and batch review/apply semantics.
- [x] Run `php artisan test --compact tests/Unit/OptionRulePresentationTest.php tests/Unit/ConfiguratorCompilerTest.php tests/Unit/ConfiguratorEngineTest.php tests/Feature/Filament/ConfiguratorRulesTest.php tests/Feature/Filament/BatchCatalogActionsTest.php tests/Feature/Catalog/ConfiguratorRuntimeTest.php`.

### A3 — hide the standalone navigation link, preserve access

**Modify:** existing `OptionResource.php` only for navigation visibility. Leave `User`, panel admission, every `can*()` override, actions/services and global-search authorization unchanged.

```php
public static function shouldRegisterNavigation(): bool
{
    return false;
}
```

[Navigation visibility](https://filamentphp.com/docs/5.x/navigation/overview#disabling-resource-or-page-navigation-items) only hides the sidebar link. Do not change `canViewAny()`, `canEdit()`, `canCreate()`, `canDelete()`, `canGloballySearch()` or `manage-catalog` to accomplish this UI cleanup. Keep resource registration in `AdminPanelProvider`.

**Test:** create `tests/Feature/Filament/AttributeValueNavigationTest.php` using `php artisan make:test --pest Filament/AttributeValueNavigationTest --no-interaction`. Follow existing factories/authorization fixtures; no new User role state.

```php
test('hiding the Attribute Values link keeps existing administrative access', function () {
    $this->actingAs(User::factory()->create(['email' => 'ycm@data4.work']));
    $value = Option::factory()->create();

    expect(OptionResource::shouldRegisterNavigation())->toBeFalse();
    expect(OptionResource::canViewAny())->toBeTrue();
    expect(OptionResource::canCreate())->toBeTrue();
    expect(OptionResource::canEdit($value))->toBeTrue();
    Livewire::test(ListOptions::class)->assertSuccessful()->assertSee('Attribute Values');
    Livewire::test(EditOption::class, ['record' => $value->id])->assertSuccessful();
});
```

Import the current Resource/Pages, Option/User and Livewire classes. Add the second existing permitted admin account to the same access scenario. Verify actual navigation output omits this link while direct index/create/edit routes, global search eligibility, Attribute/Master Value count drawers and a permitted shared save work. A user already denied `manage-catalog` remains denied with no writes. Do not add restrictions to selectors or local override editors.

- [x] Run this test before/after the visibility change, alongside `ItemDrawerEditorsTest.php`, `CanonicalDefinitionsTest.php` and `CatalogAdministrationTest.php`.
- [x] Visually review the sidebar, direct standalone page, resource header, shared drawer and Configurator Values table with an authenticated admin. Do not submit production data as review fixtures.

## Phase B — complete active-domain rename

**Delivery:** one coordinated application/schema release after Phase A acceptance. This phase changes names/contracts, not domain behavior. It requires its own execution selection and migration/deployment review; selecting the naming direction does not authorize production DDL.

### B1 — models, relationships, tables and constraints

**Rename files/classes:**

- `app/Models/Option.php` → `AttributeValue.php`; `ConfiguratorOption.php` → `ConfiguratorAttributeValue.php`.
- `app/Models/RuleConditionOption.php` → `RuleConditionAttributeValue.php`; `RuleEffectOption.php` → `RuleEffectAttributeValue.php`.
- The corresponding four `database/factories/*OptionFactory.php` files/classes → `*AttributeValueFactory.php`; update their model bindings and HasFactory annotations.
- `app/DTO/ConfiguratorOptionDTO.php` → `ConfiguratorAttributeValueDTO.php`.
- `app/Actions/SaveCanonicalOption.php` → `SaveCanonicalAttributeValue.php`; `SaveConfiguratorOption.php` → `SaveConfiguratorAttributeValue.php`.

**Modify:** `Attribute`, `Value`, `ConfiguratorAttribute`, `RuleCondition`, `RuleEffect`, `MappingSetSource`, `MappingSetTarget`, `ConfiguratorAttributeDTO`, `ConfiguratorConditionDTO`, `ConfiguratorEffectDTO`, `ConfiguratorInteraction`, `app/Providers/AppServiceProvider.php` (model-observer registrations), the canonical/inclusion usage, integrity, save/delete/status/batch services and legacy canonical-library importer. Use PhpStorm symbol refactoring plus literal-key inventory; inspect its entire diff before continuing.

| Stored name | New stored name |
| --- | --- |
| `options` | `attribute_values` |
| `configurator_options` | `configurator_attribute_values` |
| `rule_condition_options` | `rule_condition_attribute_values` |
| `rule_effect_options` | `rule_effect_attribute_values` |
| Local inclusion `option_id` | `attribute_value_id` |
| Rule/mapping `configurator_option_id` | `configurator_attribute_value_id` |
| Configurator Attribute `default_configurator_option_id` | `default_configurator_attribute_value_id` |

Keep IDs, `attribute_id`, `value_id`, `configurator_attribute_id`, all other data columns/types/defaults/nullability and timestamps unchanged. `Attribute::options()`/`Value::options()`/`ConfiguratorAttribute::options()` become `attributeValues()`; a local inclusion's `option()` becomes `attributeValue()`. `defaultOption()` becomes `defaultAttributeValue()` and continues to return a **local inclusion**. Rule/mapping `configuratorOption()` becomes `configuratorAttributeValue()`, and `optionReferences()` becomes `attributeValueReferences()`.

**Migration:** generate `php artisan make:migration rename_options_to_attribute_values --no-interaction`. Historical create migrations remain intact; fresh installations create the original schema then apply this forward migration. Use `Schema::rename()`, Blueprint `renameColumn()`/`renameIndex()` with explicit constraint names, not table recreation/data reimport. [Renaming tables and FKs](https://github.com/laravel/docs/blob/13.x/migrations.md#renaming-tables-with-foreign-keys), [renaming columns](https://github.com/laravel/docs/blob/13.x/migrations.md#renaming-columns).

The migration must preserve these relationships:

- Shared Attribute Value → Attribute/Master Value: restrict delete; globally unique exact code and unique pair; CHECK still enforces two ASCII characters.
- Local inclusion → Configurator Attribute/shared Attribute Value: restrict delete; unique `(configurator_attribute_id, attribute_value_id)` and membership `(configurator_attribute_id, id)`.
- Default composite `(configurator_attributes.id, default_configurator_attribute_value_id)` → local `(configurator_attribute_id, id)`: restrict delete.
- Condition/effect reference → its owning condition/effect: cascade delete; → local inclusion: restrict delete; preserve pair uniqueness.
- Mapping source/target → local inclusion: restrict delete; preserve mapping ownership composites and existing uniqueness/ordering.

Name renamed constraints/indexes explicitly using short `av_`, `cav_`, `rcav_`, `reav_` prefixes; retain ownership constraint names whose meaning did not change. Inventory the actual old names through Boost schema before execution. Drop/rebuild only affected FKs around table/column changes, preserving the complete graph above. Do not globally disable integrity or edit existing rows to make constraints pass. MySQL DDL is not promised to be transactionally reversible: preflight old/target names, make operations resumable by inspecting table/column/constraint state, and report a mismatched partial state rather than dropping data. `down()` reverses names and enum conversions with the same row IDs and integrity.

- [ ] Create existing-schema fixtures with shared/local IDs deliberately different, actual defaults, mapping sets and condition/effect references; include hidden/inactive records and literal codes such as `01`/`aB`.
- [ ] Test upgrade, fresh install and rollback on SQLite and the guarded MySQL connection. Assert row/ID equality, unique/CHECK/FK behavior, default ownership and actual definition evaluation before/after, not only table-name existence.
- [ ] Update factories/relationships/queries/validators together so no active writer targets old storage. Keep ordinary Eloquent Model behavior and the existing lock order.

### B2 — resource, contextual component and purpose-key rename

**Rename:** `app/Filament/Resources/Options/` → `AttributeValues/`; `OptionResource` → `AttributeValueResource`; `ListOptions` → `ListAttributeValues`; `CreateOption`/`EditOption` → `CreateAttributeValue`/`EditAttributeValue`; `OptionForm`/`OptionsTable` → `AttributeValueForm`/`AttributeValuesTable`.

The two scoped `OptionsRelationManager` classes become `AttributeValuesRelationManager`. `OptionSelectionTable` becomes `AttributeValueSelectionTable`. Rename existing files/classes, rather than scaffolding duplicate Resources/models. Keep Phase A's labels and all presentation settings.

**Modify:** `AdminPanelProvider`, `SplitListRecords`, `ItemLists`, `ItemListDrawer`, `InteractsWithItemDrawerEditor`, `TablePresentation`, `StatusActions`, `BatchEditActions`, `BatchCatalogChanges`, usage/dependency handlers, Configurator workspace pages/views and relevant table-width/client selector consumers. Actual JS files include `resources/js/table-column-widths.js`, `item-count-preview.js`, `workspace-split.js` and views under `resources/views/filament/resources/`.

Replace application-domain table/list keys: `options` → `attribute-values`, `attribute-options` → `attribute-values-for-attribute`, `configurator-options` → `configurator-attribute-values`, `value-options` → `master-value-attribute-values`, `option-inclusions` → `attribute-value-inclusions`, `inclusion-options` → `inclusion-attribute-values`, `value-option-inclusions` → `master-value-inclusions`, `local-option-defaults` → `local-attribute-value-defaults`, `local-option-blocking-rules` → `local-attribute-value-blocking-rules`, `select-option` → `select-attribute-value`. Canonical usage record type `option` becomes `attribute-value`; its `options` category becomes `attribute-values`. Report/count arrays use `attribute_values` rather than `options`. Update the exact parser, dispatch keys and owning context in every consumer together; avoid a global replacement of generic options arrays. Rename Eloquent count aliases to `attribute_values_count`/`configurator_attribute_values_count`; update their search/sort/constraint paths and all consumers together.

**New slug:** `attribute-values`. Keep authenticated GET redirects for `/admin/options`, `/admin/options/create` and `/admin/options/{record}/edit`, preserving record and relevant query context. Existing admission/`manage-catalog` checks apply; navigation hiding adds no restriction. Record path values must win over client query values. Do not leave duplicate editable legacy Resources/pages registered.

- [ ] Test renamed right-pane creation/editing, both count-drawer parent contexts, filtered full-list links, search scopes, Columns labels, remembered widths and default/card counts.
- [ ] Test old URL redirects reach the same record/filtered list and unauthorized callers remain denied under existing rules.
- [ ] Keep navigation hidden and resource/direct/global-search access unchanged after the class rename.

### B3 — definition, rule, interaction and event contracts

| Current contract token | New active token |
| --- | --- |
| `SelectionOption` | `SelectionAttributeValue` |
| Effect `AllowOptions`/`ExcludeOptions`/`HideOptions`/`DisableOptions` | `AllowAttributeValues`/`ExcludeAttributeValues`/`HideAttributeValues`/`DisableAttributeValues` |
| Rule target `Options` | `AttributeValues` |
| Interaction `SelectOption` | `SelectAttributeValue` |
| Shared inclusion field `option_id` | `attribute_value_id` |
| Rule/effect `option_ids` | `configurator_attribute_value_ids` |
| Mapping `source_option_ids`/`target_option_ids` | `source_configurator_attribute_value_ids`/`target_configurator_attribute_value_ids` |
| Definition Attribute `options` / presentation map `options` | `attribute_values` |
| Interaction `option_id` / DTO `optionId` | `configurator_attribute_value_id` / `configuratorAttributeValueId` |
| Diagnostic code `no_legal_options` | `no_legal_attribute_values` |

Convert persisted `rule_conditions.source_kind`, `rule_effects.kind` and `rule_effects.target_scope` using the exact enum table, within the reviewed schema release. No ProductProperty/context/operator changes. Every renamed enum keeps Phase A's human `getLabel()`.

`ConfiguratorAttributeDTO::$options` becomes `$attributeValues`, typed as a local-ID-keyed `array<string, ConfiguratorAttributeValueDTO>`. `$defaultOptionId` becomes `$defaultConfiguratorAttributeValueId`. Condition/Effect `$optionIds` becomes `$configuratorAttributeValueIds`. The option DTO's `id` remains local and `canonicalId` remains shared. Refactor the compiler/loader/policy/engine/tests as a single connected contract.

Set new definition `schema_version`, interaction `version` and runtime `version` to integer 2. Existing definitions/interactions without a version are v1; a v2 input must not contain legacy aliases. The trusted runtime's `selections`/`remembered` ID maps retain their existing shape/meaning. Add `App\Services\LegacyAttributeValueInput` with `public static function definition(array $input): array` and `public static function interaction(array $input): array`: pure boundary conversion of v1 keys/tokens to v2, retaining IDs and unknown fields so normal strict validation can reject them. Reject mixed legacy/new aliases and ambiguous duplicate fields; never prefer one silently. Definition errors use `ValidationException` with normal form-mapped errors/draft retention. Interaction normalization throws `InvalidArgumentException` on malformed/mixed/unsupported input; `ConfiguratorInteraction::fromUntrusted()` catches it and returns the existing malformed Reevaluate interaction, so the engine refreshes prior choices and emits its normal rejection diagnostic instead of an uncaught error. Core validators/compiler/engine accept normalized v2 only. New serializers/forms emit v2 only. Accept a valid v1 runtime for the same Configurator without resetting its choices; future/unknown versions remain rejected by existing safe-state rules.

Generate the adapter with `php artisan make:class Services/LegacyAttributeValueInput --no-interaction`. Legacy export headings and fixture sources are adapter inputs, not new active model names. Compatibility wrappers do not become a second writer or bypass authorization/ownership validation.

Normalize every renamed contract location deliberately: the Attribute's value rows and default field, nested condition/effect local-ID lists, mapping source/target local-ID lists, source/effect/scope enum tokens and selection intent. Do not rename arbitrary string values, Product property keys, Master Value text, context choices or generic presentation fields. A v1 input with both `options` and `attribute_values` is rejected even if those arrays are equal. A v2 selection requires `configurator_attribute_value_id`, not a canonical shared `attribute_value_id`.

Rename local event names/payloads/handlers consistently: `configurator-options-updated` → `configurator-attribute-values-updated`, `configurator-option-selected` → `configurator-attribute-value-selected`, and event `optionId` → `configuratorAttributeValueId`. Update editor-selected IDs/draft maps, method names, DOM hooks and every `#[On]`/dispatch/Blade/client consumer together. Retain independent shared/local save semantics and the current draft-staleness checks.

- [ ] Test unchanged codes/rule matching, mapping behavior, status availability, presentation/defaults, server-owned facts and query-free engine evaluation with v2.
- [ ] Test accepted v1 definitions/interactions, preserved v1 choices, rejected mixed aliases/foreign IDs/unknown fields, and identical authorization before writes.
- [ ] Test Attributes/Values editor switching and batch-event refresh without losing current drafts. Phase B release requires saved/explicitly closed open drafts before component-class replacement; never force a reload that silently discards them.

### B4 — acceptance and release boundary

**Tests:** existing `CanonicalDefinitionsTest`, `ItemListDrawerTest`, `ItemDrawerEditorsTest`, `ItemListOrderingTest`, `BatchCatalogActionsTest`, `ConfiguratorRulesTest`, `CatalogSchemaTest` and MySQL wrappers; `ConfiguratorDefinitionTest`, `ConfiguratorIntegrationTest`, `ConfiguratorRuntimeTest`, compiler/engine/presentation tests, factories and `tests/ConfiguratorFixtures.php`. Create a focused `tests/Feature/Catalog/AttributeValueRenameTest.php` plus sibling MySQL wrapper using existing Artisan/Pest conventions. Keep historical legacy fixtures intact.

- [ ] Identify cache entries/review tokens keyed by old model/table/purpose names; version only affected namespaces and require stale batch/status reviews to be rebuilt before Apply. Do not flush unrelated cached Product discovery or change its warm query contract.
- [ ] Check queued/serialized references to renamed classes before deployment; drain affected jobs and coordinate the component reload boundary after drafts are saved/closed. Normal route redirects and v1 ingress compatibility remain explicit; do not depend on undeclared permanent class aliases.
- [ ] Run task-owned tests after final changes, Pint/FilaCheck with diff review, the full default suite, guarded MySQL schema/contract tests and asset build if client code changed. Record actual fresh results and browser coverage.
- [ ] Review direct resources, Attribute/Master Value count drawers, Configurator inclusion/rule/Preview workflows, links, keyboard interaction and narrow/wide layouts in the actual authenticated application.
- [ ] Update active plan file paths/names, including the still-unimplemented Product-data foundation and admin/research references. Preserve their domain decisions and do not implement that separate phase.
- [ ] Prepare the explicit file/hunk publication list and a reviewed migration/deployment procedure. No production DDL, data conversion, commit or push without fresh authorization. No reset/stash/broad staging.

## Execution and verification notes

Activate `filament-development`, `laravel-best-practices`, `livewire-development` and `testing-best-practices` for implementation; use the forms UX skill for affected schemas. Before API-dependent edits, use Boost `search-docs`; before schema operations, use Boost schema metadata. Search Filament Examples for relevant resource-label/navigation patterns before writing Filament code; no plugin/dependency is required for these native features.

Use `php artisan make:test --pest ... --no-interaction` for new tests and `make:migration`/`make:class` commands named in their tasks; do not scaffold replacement editable models/resources beside the existing ones. Run PHP tests only with the isolated testing configuration. MySQL runs use `phpunit.mysql.xml` and its dedicated `configurator_catalog_test` bootstrap guards, not the development/production connection.

Run `vendor/bin/pint --dirty --format agent`, `vendor/bin/filacheck --fix --dirty` and `git diff --check` after runtime changes. Inspect their edits; preserve `tableFilters` state paths and other sessions' changes. A passing old handoff test is not acceptance of either unimplemented phase.

## Original planning checkpoint

- Naming sequence and navigation-only intent are confirmed; SuperAdmin handling is explicitly deferred, not an unresolved permission dependency.
- Source, installed Filament authorization/label lifecycle, Boost documentation and local schema metadata support the model distinction and proposed API use.
- Phase A is the first implementation target after plan review. Phase B has an explicit storage/contract map and separate coordinated-release boundary.
- This checkpoint changes documentation only. No runtime code, user permissions, schema/data, tests, commits, push or deployment were changed/run.


## Phase A implementation checkpoint — 2026-10-14

Phase A is implemented locally. Shared records use **Attribute Value / Attribute Values**; local Configurator inclusions use **Included Values**. Resource/create/delete headings, shared/local form and table labels, search/Columns choices, count drawers, usage/blocker links, rule choices/summaries, notifications and runtime diagnostic prose use the approved wording. Master Values count **Attributes**; Attributes and Configurator Attributes count **Values**. Generic select choices, future-public Territory/Application choices and historical legacy-source terminology remain distinct.

The standalone navigation link is hidden for both existing authorized admins. The resource is still registered, `/admin/options` and its existing create/edit URLs remain unchanged, and direct pages, global search, contextual creation/shared drawer editing, local editing and deletion retain existing authorization. No schema, data migration, role, permission, storage key, relationship, event or enum backing value was renamed. Phase B and the separate Product-data foundation remain pending.

Verification:

- Navigation regression ran RED on the visible link, then GREEN after hiding it. It exercises both permitted accounts, direct creation/editing, contextual shared save, global search and denial for an already unauthorized user.
- Independent final review identified enum state casting from `options(RuleTargetScope::class)`. A new Advanced-effect test reproduced the failure. Explicit backing-value-to-label options preserve the original string state; the regression verifies visibility before selecting values, successful save and unchanged persisted effect/scope/reference IDs.
- Final full default suite, after the review fix and formatting: **546 passed / 3,365 assertions**, 29.21 seconds, isolated SQLite/testing configuration. No development or production DB was mutated by tests. This is not a MySQL migration result; Phase A has no schema/SQL changes.
- `vendor/bin/pint --dirty --format agent`, `vendor/bin/filacheck --fix --dirty` (zero issues) and `git diff --check` passed. PhpStorm WARNING inspections covered modified API-bearing files/new regression tests; one pre-existing return-type inference warning remains at the existing DependencyActions record resolver.
- Authenticated Chrome desktop review covered the hidden sidebar link, direct Attribute Values list/right editor, Attributes/Values labels, Master Value count drawer/editor, Configurator Included Values table and Advanced-rule labels/controls. No catalog records were saved during browser review. Boost browser logs only contained old September entries and were not treated as fresh evidence. No new responsive layout behavior was introduced or claimed.
- The pre-existing table-test handoff was preserved, including explicit expected labels, Master Values coverage and separate column-existence/label assertions. No files were staged, committed, pushed or deployed.

Lint rulings:

- Keep manual batch selection clearing after successful Apply. FilaCheck's added native auto-deselection broke Preview/failed-validation retention (two existing tests failed); it was reverted. Disable `bulk-action-missing-deselect` with a documented rationale and retain the regression checks. Cost: this static rule no longer flags future batch actions; tests/manual review must cover their selection lifecycle.
- Set `flat-form-overload.max_fields` to nine because the existing compact replacement form has seven visible controls plus a Hidden operation and preview View. Cost: the global lint threshold permits nine counted components; runtime layout and validation remain unchanged.
- Add the missing Tags active indicator to the shared-value selector using the existing pattern. This is a small presentation addition required by the lint check; tag queries, storage and filtering state are unchanged.

Final review: one Important issue fixed RED → GREEN; no remaining Critical/Important findings or deferred runtime minors. The reviewer did not assess browser layout, production/MySQL, Phase B, historical fixtures or publication. Desktop browser coverage is recorded above; the other items remain intentionally outside this local Phase A delivery.


## Terminology and behavior-test follow-up — 2026-10-14

Phase A was published as `957013f52f8e36adb245f64b3aab5114d336cfe2` on `origin/master`, after the 546-test / 3,365-assertion default suite and reviewed explicit-file publication. The follow-up below was implemented and verified locally; commit and push were authorized on 2026-10-15. Phase B remains deferred.

### Application-owned language structure

Use contextual PHP language keys rather than a second expected-string registry in tests:

| File | Responsibility |
| --- | --- |
| `lang/en/ui.php` | Reused controls, common fields/statuses, navigation and accessibility vocabulary |
| `lang/en/catalog.php` | Master Values, shared Attributes/Attribute Values, Groups, Products, presentation/filter settings and catalog copy |
| `lang/en/configurator.php` | Configurator identity, local inclusions, rules/mapping, Preview, context, diagnostics and validation |
| `lang/en/admin.php` | Shared admin workspace, batch/status/order controls, table counts, appearance and dialog controls |

Resource labels are dynamic native getters; contextual forms/tables/actions and application-owned validation/messages resolve the same language files. Framework-provided generic wording stays in Laravel/Filament/Livewire translations. Starter-kit authentication/account forms, branding, user-entered record names, imported Product headings/values and historical source adapters are outside this terminology cleanup. No language switcher, locale route, plugin or dependency is added.

Keep machine identifiers independent from copy: columns/filter names, form state paths, actions, list/drawer keys, route slugs, event names and enum backing values are unchanged. Explicit Section/Tabs/Tab keys preserve the existing native English-slug/state-path identities; changing a heading must not alter a saved link or mounted schema key. Rule option arrays retain string backing-value keys and use translated display labels; do not replace them with native enum options that change the state type. Display summaries may translate operators without changing evaluation.

Browser labels are safely encoded as page/component configuration. Catalog filter copy is not added to cached snapshots, discovery URLs or engine facts. Initial loading and refreshed snapshots pass the same dictionary into the in-memory filter engine. Mapping icon-button bindings use `Js::from` echoes rather than an uncompiled directive inside a Blade component attribute.

### Existing tests

This follow-up supersedes literal copy expectations in the Phase A table standards. Assert stable column/filter/action identifiers, query/search/filter capabilities, visibility, counts, authorization, saved IDs and outcomes. Where rendered text is relevant, read the component/resource/action configuration or domain language key. Do not compare a configured label to itself as a supposed wording regression. The existing navigation test changes translations during the test to verify that rendering follows configuration while routes/access remain stable.

Retain the Advanced-effect enum-state regression: string `Options` scope, visible Target values before a selection, successful save and unchanged persisted effect/scope/reference IDs. The existing Mapping persistence test also verifies that rendered controls contain no literal `@js(` directive; this reproduced the browser binding failure before the fix. Deprecated helpers in the touched Configurator/context tests now use Filament 5 `TestAction`. No separate copy-only test suite was introduced.

### Research basis

[Laravel Daily's PHP-versus-JSON translation guidance](https://laraveldaily.com/post/laravel-multi-language-key-json-string-php-array) recommends contextual PHP keys where the same word has different meanings. Its linked [Laravel Daily video](https://www.youtube.com/watch?v=9cfelgca3z0) was discovered; no full video transcript was reviewed. Installed-version [Laravel localization](https://laravel.com/docs/13.x/localization) and [Filament resource labels](https://filamentphp.com/docs/5.x/resources/overview) were verified through Boost. Filament Examples MCP supplied actual `v4/full-projects/multi-language-with-switcher` resource/form/table/provider examples: native resource label getters and translated field/table labels are useful; the switcher dependency is unnecessary here. No directly relevant FilamentDaily localization video was found; other existing research remains interaction inspiration, not a claim about localization APIs.


### Follow-up verification

- Final full isolated SQLite/testing suite: **546 passed / 3,277 assertions**, 30.03 seconds, after the Mapping binding fix. Existing enum-state and persistence/authorization tests are included.
- JavaScript filter engine: **9 passed**. Asset build passed. Pint and FilaCheck passed with zero remaining issues; PHP lint checked 116 changed PHP/language files with zero failures. Static inspection resolved 840 literal language references with zero missing keys; dynamic label families were reviewed separately. Diff checks passed.
- PhpStorm WARNING-level inspections passed for OptionResource, ConfiguratorRuleForm, ConfiguratorRule, catalog Index and TableConfigurationStandardsTest. Independent read-only review found the refreshed-snapshot label omission, corrected; duplicate identical browser label configuration was removed.
- Authenticated local browser review covered Master Values tag buttons/count drawer/shared editor, direct Attribute Values access with no sidebar link, Group editor tabs, Configurator Rules/Mapping editor and catalog filter initialization. Browser review discovered the uncompiled icon-button bindings; an existing Mapping test failed before the fix and passed afterward. Fresh Mapping browser controls show numbered labels and no new console errors. No records were saved during review; user-owned tabs/drafts were preserved. This is desktop coverage, not a new mobile/MySQL/production claim.
- At this verification checkpoint, no DB migration, domain rename, permissions change, locale switcher, commit/push of this follow-up or production deployment occurred. Publication was subsequently authorized on 2026-10-15; deployment remains separate. Starter-kit authentication text remains outside this application-domain cleanup.
