# Admin and Shared Filament UX Implementation Plan

> **For implementing agents:** Use `superpowers:executing-plans` for inline execution, task by task. This document authorizes planning only; begin implementation when the user selects/authorizes a phase. Do not start subagents, install dependencies, publish, or deploy merely because they appear as alternatives here. Checkboxes describe future work, not completed implementation.

**Goal:** Make catalog administration compact, searchable, actionable, and usable in one workspace, while preserving domain validation, saved definitions, unsaved drafts, and the existing access contract.

**Architecture:** Extend native Filament tables, actions, column management, tabs, and navigation. Share appearance tokens with existing frontend components; use small typed helpers for search, count previews, batch-action presentation, and dialog sizing. Keep persistence in the existing domain actions, especially `SaveConfiguratorDefinition::change()`.

**Tech stack verified 2026-10-06:** PHP 8.4; Laravel 13.35.0; Filament 5.9.0; Livewire 4.4.7; Flux 2.20.1; Tailwind CSS 4; Pest 5.3.0; Filament Blueprint 2.4.0; MySQL. Composer also contains Spatie Laravel Settings 3.9.0 and its Filament plugin, but no application settings classes or settings table were found. They are not an already configured appearance system.

**Spec:** The consolidated requirements and decision register in this document. It incorporates the original request and sketch, all subsequent corrections, the three previous research responses, and the current code. Written user instructions take precedence over the sketch and earlier recommendations. This extends the existing [admin contract](contracts/FILAMENT_ADMIN.md) and [navigation/header work](NAVIGATION_IMPLEMENTATION_PLAN.md); it does not restart the catalog rebuild.

**Status:** Scope-complete plan for review. The required-Attribute runtime decision in G1 remains open. The recommended policies in G2 and the optional work in section 14 are explicit proposals, not silently settled business rules. Non-status phases are independent of G1.

**Later implementation handoff, 2026-10-07:** Before implementing Group forms/batch presentation, global appearance or shared frontend changes, read the [card appearance handoff for “Research Filament 5 admin UX”](CARD_APPEARANCE_ADMIN_UX_HANDOFF.md). It records the locally implemented Group controls, aggregate-save/runtime contracts, phase reconciliation and verification limits added after this plan. It does not mark P0–P12 complete or authorize another phase.

## 1. Global constraints and review focus

- Preserve authenticated catalog access, the `manage-catalog` gate, native panel access, and existing account authorization. “Public catalog” names the customer-facing surface, not guest access.
- Preserve the current dirty checkout. Several relevant resources, themes, account/catalog layouts, dependencies, and tests already have uncommitted changes. Re-read them before editing; do not reset, stash, rewrite, or stage unrelated work.
- The authoritative current user-supplied AGENTS.md replaces earlier versions. `.ai/rules` did not exist at planning time; check again before implementation and read matching rules if it appears.
- Recheck installed versions with Composer/package metadata before execution. Use Boost documentation and schema tools for version-dependent behavior and schema changes.
- Do not change dependencies without approval. A plugin being acceptable in principle is not permission to install an arbitrary version.
- Keep tables' mutation capabilities configured in PHP, per table. Admin Appearance does not become a permissions or bulk-capability editor.
- Keep new files inside existing application directories. No new base directories are necessary for the baseline below.
- Product facts remain imported/read-only. Product lifecycle status is the specifically selected exception; Group and Product deletion remain prohibited.
- No soft deletion or Undo promise is introduced. Removing an inclusion and deleting a shared record are different operations.
- Keep Configurator inclusions and rules owner-scoped; do not create global resources for them or for condition/effect/mapping fragments.
- Use `Filament\Actions\*`, `Filament\Schemas\Components\*`, and `Filament\Schemas\Components\Utilities\Get` / `Set`. Do not use old Forms/Tables action namespaces or a fictional `MultiSelect` component.
- Use `php artisan make:* --no-interaction` when implementing new PHP classes, pages, models, migrations, or tests. Discover the command/options first.
- Test meaningful behavior changes. Styling alone does not require new PHP tests. Run affected checks, `vendor/bin/pint --dirty --format agent` after PHP edits, and the frontend build when assets change.
- This plan does not authorize production migrations, publication, deployments, live data cleanup, dependency purchases, or memory/rule updates.

**Review focus:**

1. A broad search/OR constraint must never escape the drawer's parent scope or return foreign records.
2. Disabling shared records must affect existing Configurators without accidentally shortening generated codes, deleting references, or allowing ordinary rules to override hard inactivity.
3. Selection and previews must survive filtering, pagination, and concurrent changes without applying stale edits or partial writes.
4. Opening a drawer, changing tabs, refreshing badges, or resizing must not erase a staged editor draft.
5. Compact headers, collapsed navigation, wrapped labels, and dialogs must remain usable with keyboard input, touch, dark mode, and Livewire navigation.

## 2. Current surfaces and existing work

The earlier research baseline was Filament 5.8.4. The current checkout is 5.9.0. No package upgrade is part of this plan.

| Surface | Current owner/files relative to the repository root | Required coverage |
| --- | --- | --- |
| Main resource lists | `app/Filament/Resources/{Attributes,Values,Options,Configurators,Groups,Products}/Tables/*Table.php` and each Resource/Pages directory | Shared header/search/columns/density; explicit per-table actions; count drawers; statuses only for selected models |
| Split list/editor pages | `app/Filament/Resources/SplitListRecords.php`, `resources/views/filament/resources/{split-list,record-editor}.blade.php` | Effective row actions, selection highlighting, mounted editor continuity, narrow header configuration |
| Attribute → canonical Options | `app/Filament/Resources/Attributes/RelationManagers/OptionsRelationManager.php` | Same table conventions; shared Option mutations and parent scope |
| Configurator Attributes | `app/Filament/Resources/Configurators/RelationManagers/AttributesRelationManager.php` | Batch inclusion/removal, local presentation, default/Option workspace, status, counts, display/code order |
| Configurator Options | `app/Filament/Resources/Configurators/RelationManagers/OptionsRelationManager.php` | Batch inclusion/removal and local overrides; existing initially hidden/disabled flags; preserve ownership |
| Configurator Rules | `app/Filament/Resources/Configurators/RelationManagers/RulesRelationManager.php` | Enabled state, batch removal, summaries, priority, mapping/advanced editor continuity |
| Configurator Overview/Groups/tabs | `app/Filament/Resources/Configurators/Pages/EditConfigurator.php`, `app/Livewire/Catalog/Configurator{Overview,Groups}.php` and their views | Counts, evaluated deferral, batch Group association, compact native headings and settings |
| Forms and mapping field | Resource `Schemas/*Form.php`, `app/Filament/Forms/Components/MappingSetsField.php`, `resources/views/filament/forms/*` | Small forms stay simple; large forms get purposeful tabs; mapping checkboxes remain inline |
| Context settings | `app/Filament/Pages/ContextSettings.php`, `resources/views/filament/pages/context-settings.blade.php` | Compact independent vocabularies; suggested Territory/Application tabs; existing save boundary |
| Product view | `app/Filament/Resources/Products/Schemas/ProductInfolist.php`, `resources/views/filament/resources/products/facts.blade.php` | Compact read-only facts and useful item previews; no general Product editor |
| Admin navigation/dashboard/auth | `app/Providers/Filament/AdminPanelProvider.php`, native Dashboard/AccountWidget/login/password-reset pages | Existing native state and search; icons/badges; shared density without new dashboard workflows |
| Dialog/action defaults | `app/Providers/AppServiceProvider.php` and application-mounted actions | Reconcile the important global SevenExtraLarge override; per-dialog sizing and header controls |
| Shared shell/theme | `resources/css/shell.css`, `resources/css/filament/admin/theme.css`, `resources/css/app.css`, `resources/js/{app,catalog-shell}.js` | Reuse tokens and existing responsive/navigation behavior |
| Frontend catalog/account | `resources/views/components/layouts/{catalog,app}.blade.php`, `resources/views/components/page-header.blade.php`, `resources/views/livewire/{catalog,settings}/*` | Shared visual rhythm; preserve Flux/plain HTML and account security; apply Filament adapters only where actually mounted |
| Embedded/front Filament | Configurator Overview, Groups, existing standalone Preview schema and `x-filament::*` views | Shared controls/dialogs where present; do not blanket-import the admin stylesheet into Flux pages |

Current source shows native desktop collapse, sidebar search, shared shell variables, transparent branding, and compact header work. The navigation document records local N1–N4 implementation and an incomplete N5 browser matrix. Reuse that work and finish its remaining checks; do not claim this planning turn executed those tests.

Current gaps include individual-search rows, AboveContent filters, absent bulk actions, noninteractive count cells, six missing status fields, `.fi-page-main` padding still driven by a 12px shared token, and the large global modal override. These are code observations, not fresh browser measurements.

Preview & Test remains a placeholder by an earlier explicit decision. This plan improves its surrounding tabs but does not mount the dormant evaluator UI. A working item drawer is not permission to revive Preview & Test.

## 3. Decision and requirements register

| ID | Final requirement or disposition | Delivery |
| --- | --- | --- |
| R01 | Compact administration and fewer screens/clicks; written instructions outrank sketch | All phases |
| R02 | Page/main and sidebar brand headers align; logo fits; dark sidebar and transparent logo wrappers stay | P1 |
| R03 | Cell padding 4–8px vertical / 8–12px horizontal; workspace gaps 6–12px | P1, P9 |
| R04 | Zero outer `.fi-page-main` padding; components own internal spacing | P1 |
| R05 | Compact rows, headings, table headings, controls, navigation, and gaps | P1, P2, P10 |
| R06 | One table-heading row: search/filter/column controls precede header actions | P2 |
| R07 | Narrow tables use `Search · Filters · Columns · Actions`; static PHP grouping is preferred | P2 |
| R08 | Record actions are icon buttons; label becomes tooltip and accessible name | P2 |
| R09 | Replace individual search inputs with one scoped search, including All | P2 |
| R10 | Different terms/fields with AND/OR belong in native Filter/QueryBuilder | P2 |
| R11 | Native column manager in a slide-over: show/hide/order/Apply/Reset/persistence | P2 |
| R12 | No dragging headers to reorder columns | P11 |
| R13 | Item counts show bounded multiline hover/focus preview and open working lists | P3 |
| R14 | Counts may represent relations, computed subsets, or arrays; count and list must agree | P3 |
| R15 | Use typed helper initially; retain macro/subclass comparison and reasons | P3 |
| R16 | Delete/removal confirmation shows exact blockers and an action for each blocker type | P4 |
| R17 | Existing records can be selected/associated/included in batches | P5 |
| R18 | Plain catalog properties/values use multiple Select; metadata-bearing rows retain row editing | P5 |
| R19 | Mapping source/target checkboxes stay inline | P5, P7 |
| R20 | Batch edits support unchanged/set/clear and preserve mixed values | P6 |
| R21 | All valid earlier batch proposals are included; table allowlists stay in code | P6 |
| R22 | Search/replace has field selection, literal options, regex, preview, and validation | P6 |
| R23 | Additional batch utilities remain visible as proposed extensions | P6, section 14 |
| R24 | Status scope: Attribute, Option, Configurator, Product, Group, Configurator Attribute, Configurator Rule | P8 |
| R25 | Shared disabling affects existing Configurators; initial deferral/future-use-only approach is superseded | P8, G1 |
| R26 | Status cells use icon actions through domain boundaries | P8 |
| R27 | Resource and suitable Configurator-tab badges; deferral evaluated per component | P7 |
| R28 | Tabs for forms only where they reduce long independent sections | P7 |
| R29 | Native heading/context APIs first; heading hooks only where needed | P1, P7 |
| R30 | Modal header width/maximize controls and slide-over resize | P10 |
| R31 | Column resize: minimal own implementation preferred for evaluation; plugin acceptable | P11 |
| R32 | Admin Appearance shared settings, preview, Save/Reset and cache | P9 |
| R33 | Personal appearance and live sidebar/modal drag ideas are retained separately | Section 14 |
| R34 | Additional UI suggestions remain available for later review; no unrelated new workflows | Section 14 |
| R35 | Authorization, atomicity, dependencies, drafts, responsive/keyboard/resize checks are acceptance criteria | Each owning phase, P12 |
| R36 | Cover any frontend actually using Filament while preserving other framework/component ownership | P1, P9, P10, P12 |

### Open decisions and recommendations

**G1 — required-Attribute runtime consequence, decided 2026-10-07.** The engine currently generates code from applicable Attributes. The user chose configuration unavailable until repaired or re-enabled. The alternatives below remain historical context:

1. **Recommended:** disabling a required shared Attribute or local inclusion makes the configuration unavailable until repaired/re-enabled; do not emit a shortened code.
2. Omit that Attribute and its code segment, explicitly accepting changed code structure.
3. Retain a valid stored default as read-only, defining what happens if its shared Option is also disabled.

G1 is approved. G2 remains intentionally undecided at the user’s request; revisit it when P8 is ready to begin. Other authorized phases continue independently.

**G2 — proposed availability policies to review with the status phase.** These were recommendations, not explicit user decisions: a Disabled Configurator leaves its Product visible but unavailable for configuration; a Disabled Group makes its catalog branch effectively unavailable while retaining descendants' own flags; direct routes follow the same effective availability as discovery; imports retain local statuses. Adopt them only as part of approving the final status contract. Do not silently cascade status writes to children or replace selections/defaults.

Also settle the runtime response to a disabled selected/default shared Option in that status contract. Recommended: retain the saved reference, explain why the current configuration is unavailable, and require a valid active choice; never rewrite the stored default automatically. Existing rule-driven temporary availability and its selection-repair behavior remain a separate contract.

**Technical spike gates:** one-row table composition, state-preserving dialog resizing, and widths-only column resizing need application prototypes. Their success criteria are specified below. These are engineering checks, not invented user permission flows.

## 4. Domain, authorization, and data contract

### Selected and excluded models

| Model/table | Planned lifecycle change | Reason/boundary |
| --- | --- | --- |
| `App\Models\Attribute` / `attributes` | Add `is_active` | Shared availability affects existing inclusions |
| `App\Models\Option` / `options` | Add `is_active` | Shared availability; preserve code and usage references |
| `App\Models\Configurator` / `configurators` | Add `is_active` | Owner-level configuration availability |
| `App\Models\Product` / `products` | Add `is_active` | Status-only admin mutation; imported facts remain read-only |
| `App\Models\Group` / `groups` | Add `is_active` | Catalog availability; G2 defines ancestor effects |
| `App\Models\ConfiguratorAttribute` / `configurator_attributes` | Add `is_active` | User-selected local inclusion status; G1 defines runtime effect |
| `App\Models\ConfiguratorRule` / `configurator_rules` | Reuse existing `is_active` | No duplicate field/migration |
| `App\Models\Value` | No status | Earlier candidate was not included in the final selected models |
| `App\Models\ConfiguratorOption` | No new lifecycle status | Keep `hidden_by_default` / `disabled_by_default` as runtime initial state, clearly labeled |
| `App\Models\SubGroup`, `GroupFilter` | No status | Earlier optional candidates were not selected |
| `App\Models\User` | No status/User resource | Account suspension is a separate authentication capability |
| `MappingSet`, `MappingSetSource`, `MappingSetTarget` | No status | Rule/relationship fragments; preserve mapping semantics |
| `RuleConditionGroup`, `RuleCondition`, `RuleConditionOption` | No status | Partial disabling would change Boolean logic |
| `RuleEffect`, `RuleEffectOption` | No status | Partial disabling would change outcomes |
| `CatalogContextSettings` | No status | Singleton vocabulary settings, not a lifecycle entity |

For each of the six new fields: non-null boolean, default `true`, cast `boolean`, existing rows active; update model fillable/factory defaults and exact domain allowlists. Do not infer indexes from a boolean alone: add only if a measured query shape warrants them. Keep schema changes limited to these current tables, not residual legacy tables exposed by schema tools.

New shared appearance persistence is described in section 11. It is a settings record, not another lifecycle-status target.

### Authority and connected state transitions

| Mutation | Authority/persistence boundary | Enforcement |
| --- | --- | --- |
| Attribute/Value fields | `App\Actions\SaveCanonicalDefinition::handle()` | Existing validators, canonical identity restrictions, owner locks |
| Shared Option identity/code | `App\Actions\SaveCanonicalOption::handle()` | Exact two ASCII alphanumeric characters; globally unique code; unique Attribute/Value pair; existing used-identity restrictions |
| Canonical deletion | `App\Actions\DeleteCanonicalDefinition` + refined `CanonicalUsage` | Exact usage report and locked recheck; no cascade |
| Configurator inclusions/rules/settings | `App\Actions\SaveConfiguratorDefinition::change()` / `SaveConfiguratorSettings` | `manage-catalog`, owner lock, full draft compilation/validation, atomic persistence |
| Group association | `App\Actions\AssignConfiguratorGroups::handle()` | Complete final membership; owned/unassigned leaf Groups; existing lock protocol |
| Group details/presentation | `SaveCatalogGroup` / `SaveGroupSettings` | Preserve unselected settings, property vocabulary, leaf constraints, revision invalidation |
| Configurator deletion | `App\Actions\DeleteConfigurator` | Assigned Groups block deletion; shared records remain |
| Status | New explicit status action, delegating/ extending the relevant boundaries | Actor authorization, usage impact, runtime/cache policy, no default ToggleColumn update |
| Admin Appearance | New scoped settings save action | `manage-catalog`; numeric/enum validation; cache invalidation after successful save |
| Account settings | Existing Profile/Password/TwoFactor/DeleteUser actions | Preserve existing user/password/session/security behavior |

Use `Gate::forUser($actor)->authorize('manage-catalog')` at entry to every catalog mutation and drawer/list lookup. Resource visibility/action hiding is presentation, not sufficient authorization. Reload selected IDs through their resource/owner query before writing; reject deleted, duplicated, foreign, or now-ineligible IDs.

**Batch transaction rule:** preflight the selected set, lock affected owners in the existing deterministic order, then execute the complete action transactionally. For each Configurator, transform one current draft and compile/save it once. An invalid selected record blocks application; show blockers and let the user explicitly change selection. Do not silently “apply the valid subset.” Coalesce `CatalogRevisions::batch()` changes and notify/refresh only after successful commit. Large/background/chunked writes are not a baseline capability.

**Draft rule:** inspection never saves. Authoritative single/batch saves stay at the existing domain boundary. When a drawer action would conflict with an open draft, retain the draft and use the existing unsaved-change flow; do not silently close/refill it. Rebuild cached editor schemas only when required by record/kind changes, and refill from retained state. Preserve the existing `configurator-attribute-updated`, `configurator-updated`, and `catalog-record-saved` flows.

**Concurrency rule:** previews retain the original field values and a server-derived fingerprint of the affected state. Recheck under lock on Apply. A mismatch preserves the user's inputs/selection, reports the changed records, and requires a refreshed preview; no stale write or success notification.

**Hard-status versus structural validation:** references do not by themselves prevent a selected lifecycle record from being disabled. Show its usage impact and preserve structurally valid references, then derive runtime availability using the approved policy. New inclusion of disabled shared records is rejected. Authoring diagnostics must distinguish inactive references from missing/foreign references; do not make every metadata edit impossible merely because an existing dependency is inactive.

## 5. Shared layout, density, and navigation

Use the existing `resources/css/shell.css` tokens. Keep currently implemented 48px header minimum, 28px logo, 216px expanded sidebar, and 58px collapsed rail as the initial baseline; these supersede the earlier 56px sizing suggestion and are editable within the validated Appearance ranges. Long content can expand a header.

| Token/control | Initial target | Contract |
| --- | --- | --- |
| Cell padding | 6px block / 10px inline | Allowed 4–8px / 8–12px |
| Workspace/control gaps | 8px | Allowed 6–12px |
| Sidebar brand/page header minimum | 48px | Equal across matching desktop surfaces |
| Logo | 28px high, proportional width | Fit inside header; transparent wrappers; existing dark-logo asset |
| Desktop controls/navigation | 32px minimum target | Compact padding; wrapped text expands naturally |
| Touch navigation/controls | Existing 44px targets | Preserve touch and mobile drawer usability |
| Filament outer page-main inset | 0 | Separate token from editor/card internal padding |
| Internal editor/card padding | 10px starting point | Required local breathing room; do not zero every component |
| Table rows | Content-driven, approximately 32–40px for a single line | No forced clipping of wrapped labels or validation messages |

Distinguish the native outer page header from nested editor headings; compact both without giving every nested heading the outer minimum height. Keep the admin's `topbar(false)` choice; “aligned header” refers to the visible page header and sidebar brand, not a newly added topbar. Arrange title/breadcrumbs adjacent when space permits, retaining their real hierarchy and normal narrow-screen fallback.

Scope styles to application tables/workspaces/dialogs. Do not broadly change `.fi-btn` or ToggleButtons in ways that remove danger states, disabled meaning, checked state, focus outlines, or public configuration choice readability. Keep the sidebar dark in both content themes and preserve native collapse/global-search state and group flyouts.

Navigation icons: retain the current dark-sidebar/group-flyout design; use `Filament\Support\Icons\Heroicon` with distinct resource icons: Attributes `OutlinedAdjustmentsHorizontal`, Options `OutlinedListBullet`, Master Values `OutlinedCircleStack`, Configurators `OutlinedWrenchScrewdriver`, Groups `OutlinedRectangleGroup`, Products `OutlinedCube`, Context Settings `OutlinedGlobeAlt`, Appearance `OutlinedCog6Tooth`. Dashboard/open-catalog retain Home/external-link icons. These final suggestions replace the first research response's alternate icon list; visual review can adjust them without changing domain behavior.

Docs: [styling](https://filamentphp.com/docs/5.x/styling/overview), [navigation](https://filamentphp.com/docs/5.x/navigation/overview), [global search](https://filamentphp.com/docs/5.x/resources/global-search), [render hooks](https://filamentphp.com/docs/5.x/advanced/render-hooks).

## 6. Table presentation, search, filters, and columns

### Native API specification

| Element | Component, documentation, and exact configuration |
| --- | --- |
| Table | `Filament\Tables\Table`; [tables](https://filamentphp.com/docs/5.x/tables/overview); shared helper adds scoped class/key; individual table owns heading, fields, counts, actions and wide/narrow preset |
| Record action | `Filament\Actions\Action`; [actions](https://filamentphp.com/docs/5.x/actions/overview); `->iconButton()->icon(...)->tooltip(label)`; retain `->label(...)`; no unlabelled icon |
| Grouped actions | `Filament\Actions\ActionGroup`, `BulkActionGroup`; [actions](https://filamentphp.com/docs/5.x/actions/overview); secondary actions grouped statically by table preset; bulk actions through `toolbarActions()` |
| Columns | `Filament\Tables\Columns\TextColumn`; [columns](https://filamentphp.com/docs/5.x/tables/columns/overview); preserve current sort/copy/wrap semantics; replace `searchable(isIndividual: true, isGlobal: false)` with scoped global searching |
| Lifecycle cell | `Filament\Tables\Columns\IconColumn`; [icon columns](https://filamentphp.com/docs/5.x/tables/columns/icon); `->boolean()->label('Active')->action(status Action)`; current-state/intended-change tooltip; `manage-catalog`; P8 domain save with impact confirmation rather than an automatic ToggleColumn update |
| Lifecycle form field | `Filament\Forms\Components\Toggle`; [toggle](https://filamentphp.com/docs/5.x/forms/toggle); `Toggle::make('is_active')->label('Active')->default(true)`; boolean validation in the owning save action; existing Rule field reused |
| Column manager | `Filament\Tables\Enums\ColumnManagerLayout::Modal`; `->columnManagerLayout(...)->reorderableColumns()->columnManagerTriggerAction(fn (Action $action): Action => $action->label('Columns')->slideOver())`; Apply/Reset/session persistence retained |
| Filter trigger | `Filament\Tables\Enums\FiltersLayout::Modal`; `->filtersLayout(...)->filtersTriggerAction(fn (Action $action): Action => $action->label('Filters')->slideOver())`; replace permanent AboveContent filters; Apply/Reset and active indicators retained |
| Combined constraints | `Filament\Tables\Filters\QueryBuilder`, `Filament\QueryBuilder\Constraints\TextConstraint`, `NumberConstraint`, `BooleanConstraint`; [QueryBuilder](https://filamentphp.com/docs/5.x/tables/filters/query-builder); only listed fields/relationships; AND/OR groups |
| Relationship/status filters | `Filament\Tables\Filters\SelectFilter`; [select filters](https://filamentphp.com/docs/5.x/tables/filters/select); retain existing relationship filters; `is_active` options `1 => Active`, `0 => Disabled`, empty => All |
| Search field selection | `Filament\Forms\Components\Select`; [Select](https://filamentphp.com/docs/5.x/forms/select); `->multiple()->searchable()->options(allowed fields)`; empty selection means All, visibly labeled; validate list/distinct/allowed keys |

Default heading order: `Heading / selected count | Search · scope · Filters · Columns | header actions`. Inherently narrow tables always use one Actions group, rather than changing mounted action ownership at runtime. Search remains an input; Filters/Columns/Actions can use short text/icon controls with tooltips. Selection actions replace/occupy the configured Actions area, retaining selection count/Clear/selected-only.

Lifecycle columns/filters show the record's own stored flag. Explain effective restrictions from a shared record or ancestor separately in the tooltip/diagnostics; an active local inclusion can still be unavailable because its shared Attribute is disabled. Do not collapse those independent facts into a misleading single editable flag.

Use a scoped CSS composition of the native header/toolbar first. Filament 5.9 still renders heading and toolbar separately; merely changing `headerActions()` does not meet the requirement. P2 must demonstrate one visual row, correct keyboard order, native action/modal mounting exactly once, and no empty toolbar border/height. If CSS composition cannot meet those criteria, use one narrowly scoped application header adapter through `Table::header()` while retaining native control state and dialogs. Do not publish/replace the entire vendor table view or clone action DOM. Active-filter indicators may occupy a conditional line; individual-search rows must be gone.

### Search contract and per-table scope

- Keep one native search term and a visible field-scope control. All means the table's explicitly allowed searchable fields, including permitted hidden columns; hiding a column does not change scope.
- Restrict native global-search fields without changing its established multiword matching behavior. `applyGlobalSearchToTableQuery(Builder $query): Builder` is the installed extension point; an application trait can own scoped behavior. Do not replace owner query construction.
- Different terms/fields use QueryBuilder: `Code contains A AND Value contains Flange`. Main search, existing filters, and constraints combine inside the authoritative parent scope.
- Client field paths/operators are not trusted SQL. Whitelist keys and map them to configured fields/relationship predicates. Numeric constraints use numeric operators; string comparisons use bound parameters.
- Scope/constraint changes reset pagination while retaining selected-record semantics. Show scope and active constraints without reopening the controls.
- Add status fields/constraints only in P8 after their columns exist; P2 does not query the absent `is_active` fields.

| Table key | All/field-search options | Existing filters to retain | Combined constraint fields |
| --- | --- | --- | --- |
| `attributes` | ID, key, label | Add selected status filter in P8 | ID, key, label, status |
| `values` | ID, label, description | Existing tag filters | ID, label, description, tags using existing tag semantics |
| `options` | ID, code, Attribute label, Value label | Attribute; status in P8 | Code, Attribute label, Value label, status |
| `configurators` | ID, name, description | Status in P8 | Name, description, status |
| `groups` | ID, name, legacy ID, Parent name, Configurator name | Parent; status in P8 | Name, description, Parent/Configurator, status |
| `products` | ID, product code/name, Group name, legacy ID | Group; status in P8 | Product code/name, Group, status; imported facts stay read-only |
| `attribute-options` | Code, Value label | Parent Attribute enforced; status in P8 | Code, Value label, status |
| `configurator-attributes` | Effective label, canonical label/key, help text | Owner enforced; status in P8 | Local/shared label and input type/status |
| `configurator-options` | Code, effective/shared label, display value, hint | Owner inclusion enforced | Local text and existing initial hidden/disabled flags |
| `configurator-rules` | Label, kind; structured summary only if searchable query is explicitly supplied | Owner enforced; Enabled/Disabled | Label, kind, enabled state; priority numeric |
| `configurator-groups` / selection/drawer tables | Provider-specific title/code/context fields | Provider/owner enforced | Only provider-declared fields |

Use constrained relationship searches without turning unbounded related datasets into preloaded Select options. Keep cheap IDs/dates/legacy columns hidden by default where they distract from names/codes; user column-manager choices take precedence after initialization.

## 7. Working item lists and dependency blockers

### Typed reusable helper contract

Create a typed factory returning native `TextColumn`, not a global macro. Proposed interfaces:

```php
App\Filament\Resources\ItemCountColumn::make(
    string $name,
    string $label,
    string $listKey,
): Filament\Tables\Columns\TextColumn;

App\Services\ItemLists::definition(
    App\Models\User $actor,
    string $key,
    int $parentId,
): App\DTO\ItemListDefinition;
```

`ItemListDefinition` is a server-only typed value: title, meaning/count label, authoritative query or array provider, columns, actions, and optional full-list URL. Do not serialize closures into public Livewire properties. The drawer receives only whitelisted list key/parent ID, reauthorizes and resolves the definition on every request, then paginates/searches the provider's scoped dataset.

Pros/cons: helper factories remain discoverable and native; macros require global boot registration, can collide with future methods, and weaken static analysis; subclasses become worthwhile only for genuinely different rendering. Query loading, not helper-versus-macro syntax, determines performance. Do not introduce a registry of arbitrary client-supplied model classes or SQL callbacks.

### Count and preview rules

- Display the exact cardinality and meaning, not a different related total. Preserve aggregate queries/aliases already loaded by resources.
- Hover/focus tooltip: up to five escaped title/name lines with one useful secondary field (usually code), plus the remaining count. Empty means “No items.” The tooltip is noninteractive; actions belong in the opened table.
- `TextColumn::tooltip()` accepts `Htmlable`; build trusted Blade/escaped text, never concatenate raw user HTML. No unbounded relation loads or per-row tooltip count/query closures.
- For simple relation summaries, batch-load bounded previews for the visible page. For expensive computed providers, fetch on explicit hover/focus demand with memoization and a loading state; do not query every row just to prepare a tooltip.
- Click action: `Filament\Actions\Action::make('openItems')->slideOver()->stickyModalHeader()->stickyModalFooter()`; visible to authorized catalog managers; read-only open, no writes. Table supports relevant View/Edit/remove actions, pagination/search, and an Open full list action.
- Maintain original table selection, draft, scroll and return context. Avoid growing a stack of dialogs; a blocker list can replace the inspection content or use one managed child with a clear return path.
- View/edit links use resource/named routes with equivalent filtering. For owner-only Rules/Attributes, link to the Configurator/tab and a validated selection parameter or open its existing side editor. Add a selection parameter only with server ownership validation and the unsaved-draft contract.

| List key / visible count | Exact opened items |
| --- | --- |
| Attribute Options | Canonical Options for that Attribute |
| Attribute Configurators/inclusions | Local inclusions with Configurator context; show distinct owners only if the count is distinct |
| Master Value Options | Canonical Options using that Value |
| Option local uses | `ConfiguratorOption` inclusions with Configurator/Attribute context |
| Configurator Groups | Assigned Groups; Product counts can open that Group's matching Products |
| Configurator Attributes / Rules | Only the owner's local inclusions/rules |
| Configurator Attribute Options | Local included Options, not every shared Option |
| Rule sources/targets or other computed counts | Exact mapping/condition/effect subset; keep inline mapping selection intact |
| Suitable array-backed item totals | Exact array rows via custom-data provider; supply search/pagination behavior |

This applies to counts of actual item sets wherever useful, including summary sections—not to measurements, quantities, or numeric statistics with no meaningful item list.

### Blocked deletion/removal

Refine `App\Services\CanonicalUsage` into the common source for exact dependency categories, true counts, bounded summaries, and paginated queries. Current Option rule reporting uses associated Attribute IDs and can report unrelated rules; inspect actual Option references in conditions/effects/mapping sets/defaults instead. Do not use its current 100-row display cap as a total.

The confirmation displays selected/blocked counts and one action per actual blocker type, for example:

```text
Cannot remove these Attributes.
Rules (3)                 [View rules]
Shared Options (8)        [View options]
Configurator inclusions (4) [View inclusions]
Stored defaults (2)       [View defaults]
```

Use `Filament\Actions\Action` / `BulkAction`, `->requiresConfirmation()`, danger styling, exact scope/reason, and disabled confirmation while blocked. Authorization remains `manage-catalog` plus existing deletion capability. A matching locked check runs again on confirmation; newly appearing dependencies refresh this same report while retaining selection. Never silently cascade, unassign, clear defaults, or remove rules to make deletion pass.

Configurator deletion exposes assigned Groups as blockers. Local Attribute/Option removal exposes defaults and exact driver/target/condition/effect/mapping references. Group/Product deletion is not enabled. A deletion dialog may explain their existing prohibition but must not manufacture a permitted Delete action.

Docs: [column actions](https://filamentphp.com/docs/5.x/tables/columns/overview), [custom-data tables](https://filamentphp.com/docs/5.x/tables/custom-data), [modals](https://filamentphp.com/docs/5.x/actions/modals).

## 8. Batch selection, inclusion, association, and list fields

For actual records use `Filament\Forms\Components\TableSelect::make('selected')->multiple()` in a direct `Filament\Actions\Action::slideOver()` with a dedicated table configuration. `ModalTableSelect` is available for ordinary relationship fields, but avoid a select modal opening another unnecessary selection modal. Do not bind `relationship()` automatic persistence to Configurator aggregate fields.

Visibility/authorization: `manage-catalog`; eligible owner/canonical scope; already included rows clearly marked. Submitted IDs: required array/list, integer/distinct/existing in the permitted set, with server reauthorization after selection. Search/filter/pagination preserve selection; display selected count, Clear, selected-only, current-page select-all, and an explicit all-matching choice where supported. Do not silently turn page selection into all matching rows.

| Job | Final interaction and persistence |
| --- | --- |
| Include Attributes | Select several; review required Options/defaults in the same staged workspace; Include N once; save one valid owner draft |
| Include Options | Select eligible shared Options for the current Attribute; preserve already included rows/local overrides; review default consequences |
| Assign/unassign Groups | Select available or assigned leaf Groups; compute complete final membership; call `AssignConfiguratorGroups::handle()` once |
| Catalog filter properties | Adjacent searchable `Select::multiple()` over `CatalogImportParser::propertyKeys()`; add missing repeater rows without replacing row IDs/labels/value order; enforce current max 18 filters |
| Allowed property/source values | `Select::multiple()` over the current Group vocabulary; preserve existing valid settings; report stale choices using `SaveGroupSettings` |
| Mapping sources/targets | Keep simple inline checkboxes and existing mapping-set structure |
| Conditions/effects/context choices | Keep structured repeaters/builders where entries contain logic or new text; improve labels/summary/collapse and use batch pickers only for choosing existing items |

Reuse one explicit draft initializer for single and multiple inclusion. Programmatic `Set` does not run field update hooks. New Attribute selections follow the existing single flow's current-Options initialization, but batch review must choose a valid stored default; do not invent one silently. Reopen/hydration/errors must not overwrite a draft, and later shared Options must not automatically expand previously saved inclusions.

Preserve mapping contract: each source belongs to at most one set; target overlap is allowed; unmapped driver options add no restriction; save mapping edits atomically.

Cancel is zero writes. A failed batch retains staged rows/defaults/selection and field errors. Success preserves existing IDs/order/local overrides and refreshes dependent tables/badges without resetting unrelated drafts.

Docs: [table selection](https://filamentphp.com/docs/5.x/forms/select#selecting-options-from-a-table-in-a-modal), [repeaters](https://filamentphp.com/docs/5.x/forms/repeater).

## 9. Batch action and field allowlists

Use `Filament\Actions\BulkAction` and `BulkActionGroup` for selected table rows; association/inclusion selection is a header `Action`. All custom catalog actions are authorized through `manage-catalog` and the domain action; fields/operations are configured by each table, not a universal mass-assignment endpoint.

| Table | Planned actions | Editable fields / restrictions |
| --- | --- | --- |
| Master Values | Edit; add/remove/replace tags; find/replace; eligible delete | `label`, `description`, `tags`; labels max 255, descriptions max 5000, existing tags max 30/max 80 per item and distinct/trimmed; no status |
| Attributes | Edit label; find/replace; selected status; eligible delete | `label` max 255; key changes only through existing unused-identity rules, separately shown and blocked for in-use keys |
| Shared Options / Attribute Options | Selected status; eligible delete; dedicated replace-code operation | No blanket Attribute/Value reassignment; code exactly two ASCII alphanumerics and globally unique; usage impact preview |
| Configurators | Description/name editing; find/replace; status; eligible delete | `name` max 255, `description` max 5000; duplicate remains existing scoped action; assigned Groups block deletion |
| Groups | Description/presentation edit; find/replace; status | Existing `result_settings` fields; preserve filters/presets/unselected fields; no bulk parent/Configurator reassignment or deletion by default |
| Products | Status only | No imported identity/fact/text editing or deletion |
| Configurator Attributes | Include/remove; presentation edit/reset; find/replace; status | `label_override` max 255, `help_text`, permitted `input_type`; retain existing validation/default rules; display/code ordering are separate controls |
| Configurator Options | Include/remove; presentation edit/reset; find/replace; initial hidden/disabled | `label_override`, `display_value_override` max 255; `hint`; booleans `hidden_by_default`, `disabled_by_default`; no new lifecycle flag |
| Configurator Rules | Existing enable/disable; removal; label edit/find/replace | `label` max 255 and existing `is_active`; no blanket driver/target/kind/logic replacement |
| Configurator Groups | Assign/unassign selected | Existing membership rules; no stealing another Configurator's assignment |

Retain existing validators for help/hint, input types, and presentation fields; do not impose arbitrary new limits in a UI helper. Add explicit changed-field validation to the owning action, not just the modal.

### Edit and replace behavior

Field controls use `Filament\Forms\Components\Select`, `TextInput`, `Textarea`, `Toggle`, or `TagsInput` according to the field. Every editable field has **Leave unchanged / Set / Clear**; Clear only for nullable/resettable fields. Mixed values are displayed as mixed, not replaced by the first row's value. Reset local overrides means restore canonical fallback, not copy today's shared text into a persistent override.

Find/replace field specification:

| Field | Component / validation / configuration |
| --- | --- |
| Target field | `Filament\Forms\Components\Select`; required whitelisted key; one target string field |
| Match mode | `Select`; required `literal` or `regex`; default literal |
| Search/pattern | `TextInput` or `Textarea`; required nonempty string; literal matching never interprets regex metacharacters |
| Replacement | `TextInput` or `Textarea`; present string, empty allowed only if destination validation permits; regex captures `$1` / `${1}` documented |
| Case-sensitive | `Toggle`; boolean; literal default true; regex maps to documented flags |
| Occurrences | `Select`; `first` / `all`; default all |
| Advanced regex flags | Explicit allowed flags only; no executable replacements; invalid/limit errors block apply |
| Preview | Read-only table showing record/context, before/after, changed/unchanged/blocker state; no writes |

Null remains null and unmatched values remain unchanged. Validate the entire resulting set, including uniqueness against unselected rows and within the selection under the existing database collation/validation rules. Do not promise identity/code swaps that the existing uniqueness/write protocol cannot execute; detect and report transient conflicts rather than failing halfway. Display effects on Configurators for code changes and warn that imported Group names can be replaced on reimport.

On Apply, recompute from locked current values and compare the preview fingerprint. Use the domain action, not raw SQL REPLACE or arbitrary field-path updates. Preserve selection/inputs on validation or regex failure.

Proposed useful extensions, retained for phase review: prefix/suffix; trim/collapse whitespace; case normalization; fill blanks only; copy selected presentation fields from a source record; reset overrides; duplicate selected rules as disabled copies; move selected blocks while preserving internal order. Do not add all these operators through an unbounded “any field/any operation” engine.

Docs: [bulk/actions](https://filamentphp.com/docs/5.x/actions/overview), [forms](https://filamentphp.com/docs/5.x/forms/overview), [PHP regex replacement](https://www.php.net/manual/en/function.preg-replace.php).

## 10. Form tabs, badges, deferral, and native features

| Form | Target layout | Loading/state contract |
| --- | --- | --- |
| Attribute / Option / Master Value | Compact Sections, one schema column | Do not add tabs for two-to-four fields |
| Configurator | Existing Overview / Groups / Attributes / Rules / Preview & Test | Add Group/Attribute/Rule counts; preserve query-string tab persistence and placeholder Preview |
| Configurator Overview | Details / Context choices if context lists warrant it | Preserve Save overview and same state paths; do not force tab changes for ordinary name/description work |
| Group | Details / Filters / Presets / Presentation | Root `$schema->columns(1)`; tabs full width; one authoritative existing Group save; leaf-only settings stay conditional |
| Configurator Attribute | Core settings/default visible with included Options workspace | Do not separate coupled default/Option work into different tabs |
| Mapping rule | Driver/target/mapping sets together | Existing inline checkbox field unchanged |
| Long advanced rule | Conditions / Effects, with visible summary | Errors reveal the relevant tab; short forms can stay sections |
| Context Settings | Territories / Applications | Independent vocabularies, shared existing save boundary |
| Product infolist | Compact fact sections; optional long-data tabs after review | Read-only; no edit-form conversion |
| Admin Appearance | Density / Navigation / Dialogs with persistent preview | Saved defaults and unsaved preview clearly distinguished |

Layout components: `Filament\Schemas\Components\Tabs`, `Tabs\Tab`, `Section`, `Grid`, `View`, `Livewire`; [tabs](https://filamentphp.com/docs/5.x/schemas/tabs), [schemas](https://filamentphp.com/docs/5.x/schemas/overview). Use `->columnSpanFull()` for tab/section containers; do not multiply nested two-column layouts into quarter-width fields.

Badges: navigation total-record counts for Attributes, Master Values, Options, Configurators, Groups, Products, subject to authorization. Count all administrative records, including disabled; label the meaning rather than implying active totals. Cache/invalidate on create/delete/import; refreshing sidebar uses native `refresh-sidebar`. Resource badges do not have the tab `deferBadge()` API.

Configurator badges show Groups/Attributes/Rules totals; an enabled/total rule tooltip is optional. Use callbacks and keyed `Tabs`; cheap counts already loaded need no new asynchronous query. Native `Tab::badge(fn ...)->deferBadge()` is for expensive badges, not eager calls masquerading as deferral.

Deferred schemas use `Schema::make()->components([...])->deferLoading()` under uniquely keyed tabs/sections. Evaluate each embedded component: defer expensive inactive Groups/Attributes/Rules only after confirming mount/validation/events/drafts behavior. Once loaded, keep it mounted; never key by active tab index. Constructing schema callbacks can still query before rendering, so move expensive work behind the real loading boundary. Keep badge totals independent from mounting the full editor.

Filament 5.9's Builder `->searchable()` can help longer rule block pickers; filters Reset can use `filtersResetAction()` for a consistent Clear filters label. Preserve existing breadcrumbs; hierarchical mode is an optional presentation choice, not a route rewrite. Table-content/page-heading hooks add missing contextual material only when native placement cannot do it. Rendering improvements are already in the installed package; do not invent an enable switch.

## 11. Admin Appearance, dialogs, and resizing

### Shared settings

Add Admin Appearance under Settings, authorized by `manage-catalog`. Reuse the current singleton-model/action convention rather than installing another settings mechanism. Proposed new files in existing directories: `app/Models/AdminAppearanceSettings.php`, `app/Actions/SaveAdminAppearanceSettings.php`, `app/Services/AdminAppearance.php`, `app/Filament/Pages/AdminAppearance.php`, `resources/views/filament/pages/admin-appearance.blade.php`, matching factory/migration/test.

Persistence: `admin_appearance_settings` singleton ID 1; `settings` JSON; `version` unsigned integer default 1; timestamps. Create the row with defaults in its migration; model array/integer casts. Save action locks singleton, validates the complete allowlisted document, increments version on actual change, and invalidates the singleton cache after commit. Do not mix visual preferences into `CatalogContextSettings::choices` or serialize CSS supplied by users.

This is an implementation recommendation within the approved shared-settings requirement. The installed-but-unconfigured Spatie package remains an alternative if the project chooses to standardize all settings; adopting it requires a coherent repository/configuration/migration plan rather than assuming it is ready.

| Setting key | Initial value | Validation/configuration |
| --- | --- | --- |
| `cell_padding_block` | 6 | integer 4..8 |
| `cell_padding_inline` | 10 | integer 8..12 |
| `workspace_gap` | 8 | integer 6..12 |
| `header_height` | 48 | integer 40..64; controls/logo must still fit |
| `logo_height` | 28 | integer 20..32; no larger than header minus vertical padding |
| `sidebar_width` | 216 | integer 192..320; native panel width in px/rem |
| `collapsed_sidebar_width` | 58 | integer 58..80; never clip native flyouts/search/account controls |
| `navigation_padding_block` | 6 | integer 4..8; preserve desktop/touch target minima |
| `control_height` | 32 | integer 30..36 for desktop; touch minimum remains separate |
| `modal_width` | `medium` | enum small/medium/large; presets 640/960/1280px, viewport clamped |
| `slide_over_width` | `medium` | same enum/presets, capped by viewport |

Fields: `Filament\Forms\Components\TextInput::numeric()->integer()->minValue(...)->maxValue(...)` and `Select::options(...)->required()`; validation also in the save action. Reactive previews use `->live(onBlur: true)` for numeric text and Get/Set where necessary. Root form one column; Save applies shared defaults, Reset stages defaults for preview and requires Save to persist.

Preview renders a representative header, sidebar, narrow/wide table and dialog sample using unsaved form values. Other users see saved shared defaults only. Build CSS once; numeric/enum settings update a small escaped CSS-variable set without asset rebuilds. Frontend consumers receive shared shell variables, not the entire admin stylesheet or admin actions. Existing account Appearance remains light/dark/system unless personal overrides are separately selected.

### Modal and slide-over behavior

- Remove/reconcile `Action::configureUsing(... modalWidth(SevenExtraLarge), isImportant: true)` so specific actions can choose appropriate initial widths.
- Ordinary destructive confirmations remain appropriately small; selection/edit drawers can start larger. Shared default width does not force every tiny confirmation to 1280px.
- Use native `modalWidth()`, `slideOver()`, `slideOverPosition(Filament\Support\Enums\SlideOverPosition::End)`, `stickyModalHeader()` and `stickyModalFooter()` where applicable. Verify enum namespace in installed source before implementation.
- Modal header controls: Small / Medium / Large and Maximize/Restore, with accessible names. Keep temporary maximization local to the mounted dialog; restore its previous width.
- Slide-over: same presets plus bounded edge drag. Resize only the window presentation; keep the same Livewire component, form state, selection, validation errors and draft.
- Add keyboard-operable width controls. Clamp persisted widths to the viewport; mobile uses available width and disables desktop-only handles.
- Use a small scoped application dialog adapter and `extraModalWindowAttributes(..., merge: true)` plus custom heading content/native hooks where sufficient. Do not replace Filament's whole modal lifecycle or override an existing Alpine owner.
- Save drag width on completion, not each pointer movement. Initial browser persistence is namespaced by user/panel/dialog purpose; reset and malformed-storage handling are required.
- No corner-drag modal or live sidebar-drag baseline is implied. Those remain optional ideas in section 14.

### Column widths

Prototype a small widths-only Alpine module in existing `resources/js/`, registered once through the application asset path. Use stable application table/column keys, native `extraHeaderAttributes(..., merge: true)` / `extraCellAttributes(..., merge: true)`, and scoped CSS widths. Do not change sorting, owner queries, or action state.

Contract: edge pointer drag with capture; stop resize clicks from sorting; minimum 64px/maximum 640px per data column as adjustable technical defaults; fixed selection/action columns; keyboard/preset alternative; save on release; restore after rerender, hide/show and native manager ordering; horizontal scroll where width genuinely exceeds the container. Columns offers Reset widths for widths alone, and its full Reset columns also restores default visibility/order and clears saved widths; neither resets filters. Native column ordering remains in the manager; header drag reordering/pinning are not included.

Use `[user, panel, table, column]` browser-storage keys, cleanup listeners through Alpine init/destroy, and handle cancellation/unavailable storage. CSS table layout must be tested: width attributes alone may be suggestions under automatic layout. Prototype failure means evaluate the plugin, not quietly replace all vendor table views.

Plugin alternative: `asmit/resized-column` supports Filament 5 and persistence. Before installation, verify its resolved version/license/assets, trait lifecycle hooks and merging with our attributes. Its inspected PHP integration sets header/cell attributes without requesting merge; that is a concrete compatibility concern. Keep optional reordering/pinning off. The modal-resizing plugin considered earlier skips slide-overs and is not a complete solution for this scope.

## 12. File map and delivery tasks

All paths below are repository-relative to `/Users/studioycm/Herd/configurator`. New filenames are proposed implementation files, not files created by this planning turn. Every task reads current siblings/AGENTS/rules first and preserves unrelated changes. Estimates are bounded hands-on ranges, not calendar promises; failed technical prototypes change their phase estimate.

| Phase | User-visible outcome | Dependencies / estimated work |
| --- | --- | --- |
| P0 | Current baseline and selected execution scope recorded | 0.5–1h |
| P1 | Consistent compact surfaces using existing shell | P0; 3–5h |
| P2 | One table header, one scoped search, native Filters/Columns | P1; 7–11h |
| P3 | Item counts open useful working lists | P2; 7–11h |
| P4 | Blocked removal explains dependencies and opens them | P3; 4–7h |
| P5 | Batch include/associate; plain lists and mapping retain simple flows | P2/P4; 7–11h |
| P6 | Safe batch editing/deletion and search/replace across valid tables | P4/P5; 10–16h; optional utilities separate |
| P7 | Purposeful tabs, useful badges, evaluated deferral | P2/P5; 4–7h |
| P8 | Selected status behavior agrees across admin/runtime/catalog | G1/G2 approval and P4/P6; 10–16h |
| P9 | Shared Admin Appearance with live preview/cache | P1; 5–8h |
| P10 | Dialog header width controls and slide-over resizing | P3/P9; 5–9h |
| P11 | Persisted column resizing, no header reordering | P2/P9; 4–7h prototype + integration |
| P12 | Cross-surface acceptance and reviewable delivery | Owning phase checks already pass; 3–5h |

Each phase leaves useful behavior if later phases are removed. Styling/search do not wait for status decisions. No automatic worktree/commit/push is part of writing this plan; execution follows the user's selected method and repository guidance.

### P0 — Baseline and implementation setup

Files/read: `AGENTS.md`, current `.ai/rules` if present, `composer.lock`, `package.json`, existing navigation/admin/public contracts, modified files in the phase, nearby tests.

- [ ] Confirm selected phase and authorization; snapshot `git status --short`, identify an exact file allowlist, and reuse a suitable attached worktree if isolation is needed.
- [ ] Run `composer show filament/filament`, `composer show livewire/livewire`, and inspect JS versions. Read relevant skills/rules and use Boost docs/schema; do not rely on historical versions.
- [ ] Determine current test prerequisites from `tests/Pest.php`, `phpunit.xml`, and guarded MySQL bootstrap. SQLite feature tests are available; MySQL checks must use only `configurator_catalog_test`, never the active development DB.
- [ ] Reconcile any current implementation changes with this plan rather than reverting them. Record code-observed versus browser/test-observed behavior.

### P1 — Finish density and shared presentation

Modify: `resources/css/{shell,app}.css`, `resources/css/filament/admin/theme.css`, `app/Providers/Filament/AdminPanelProvider.php`, existing page-header/layout/related-editor views only as needed. Preserve `resources/js/catalog-shell.js` behavior.

Consumes existing shell tokens/native navigation; produces distinct outer-page and internal-card spacing plus application table/dialog classes.

- [ ] Split outer page inset from card/editor padding; set `.fi-page-main` to zero across responsive overrides.
- [ ] Apply 6/10px cells and 8px gaps; align 48px matching headers/logo; compact navigation/headings/controls without fixed row clipping.
- [ ] Apply/review final resource icons while retaining group flyouts, dark sidebar, transparent branding, native search and persisted collapse.
- [ ] Build with `npm run build`; visually inspect wide/narrow resource and nested-editor pages plus existing catalog/account shell in light/dark.
- [ ] Reuse existing Dashboard/PublicCatalog/Settings tests only if layout changes affect their contracts; no new CSS-mirroring tests.

Acceptance: no duplicate page heading or toolbar-sized empty gap, no clipped logo/controls, readable wrapped content, and no lost shell/context state.

### P2 — Shared table presentation, scoped search and constraints

Create: `app/Filament/Resources/TablePresentation.php`, `app/Filament/Resources/InteractsWithScopedTableSearch.php`; scoped header view only if the prototype needs one. Modify all six `Tables/*Table.php`, four relation managers, `SplitListRecords.php`, related view/theme files. Test: new `tests/Feature/Filament/ScopedTableSearchTest.php`; update current-resource portion of `TableConfigurationStandardsTest.php` while preserving legacy fixture coverage.

Interfaces: `TablePresentation::configure(Table $table, string $key, bool $narrow = false): Table`; search trait defines `protected function scopedSearchColumns(): array` with server-owned keys/labels/column predicates and overrides the installed global-search extension point. Tables configure their own allowed fields and groups.

- [ ] Write behavioral tests: Code A + Value Flange combination, one-field versus All search, hidden-column scope, owner confinement and zero foreign results; confirm failure on old individual-search layout/behavior.
- [ ] Prototype native one-row composition on Options and the narrow Configurator Attribute table; prove filters/column dialogs mount once and controls work by keyboard.
- [ ] Implement scoped search and QueryBuilder from section 6; move AboveContent filters into the native slide-over, retaining tag/relationship semantics.
- [ ] Configure Columns slide-over/ordering/reset/persistence and icon record actions, including the effective `SplitListRecords` action replacement.
- [ ] Roll the same verified helper onto remaining main/relation/selection tables; choose narrow grouping statically in PHP.
- [ ] Run `php artisan test --compact tests/Feature/Filament/ScopedTableSearchTest.php tests/Feature/Filament/TableConfigurationStandardsTest.php`; run affected resource tests if their action hooks change.

Acceptance: one input, visible scope, working AND/OR filters, no individual-search row, no permanent filter row, one heading/control row on supported widths, selection/actions retained, no scope escape.

### P3 — Typed count columns and working-list drawer

Create: `app/DTO/ItemListDefinition.php`, `app/Services/ItemLists.php`, `app/Filament/Resources/ItemCountColumn.php`, `app/Livewire/Catalog/ItemListDrawer.php`, `resources/views/livewire/catalog/item-list-drawer.blade.php`, bounded tooltip view in `resources/views/filament/resources/`, `tests/Feature/Filament/ItemListDrawerTest.php`. Modify count-bearing tables/resources and Group summary views as needed.

Consumes section 7's typed interfaces; produces authoritative list providers reused by P4. Eloquent providers use `Filament\Tables\Contracts\HasTable` / `InteractsWithTable`; array providers use verified custom-data table APIs and explicit pagination/search.

- [ ] Test a parent with multiple related records plus an unrelated parent; count/preview/opened table agree and foreign parent IDs are rejected.
- [ ] Implement whitelisted providers, bounded escaped tooltip summaries and native drawer actions; show context for local uses/defaults/rules.
- [ ] Add relevant View/Edit/remove actions through existing domain boundaries, with draft/conflict protection and an equivalent full-list/workspace route.
- [ ] Test filtering/pagination and malicious tooltip text; opening/cancelling does not write or change the original draft/selection.
- [ ] Run `php artisan test --compact tests/Feature/Filament/ItemListDrawerTest.php tests/Feature/Filament/ConfiguratorWorkspaceTest.php`; visually inspect hover/focus/list transitions.

Acceptance: actual working lists, accurate semantics, bounded loads, safe text, useful return context, no forced relation-manager abstraction.

### P4 — Exact blockers and linked confirmation

Modify: `app/Services/CanonicalUsage.php`, `app/Actions/{DeleteCanonicalDefinition,DeleteConfigurator}.php`, existing canonical usage view, deletion/removal actions in resource pages/relation managers. Test: extend `CanonicalDefinitionsTest.php`, `ConfiguratorAdministrationTest.php`, `ConfiguratorWorkspaceTest.php`.

- [ ] Add the discriminating regression: two Options share an Attribute, but a rule references only one; the other's blocker report must not claim that rule.
- [ ] Implement exact grouped queries/counts including defaults, inclusions, rules, assigned Groups; use the same predicates in report and locked rejection.
- [ ] Add one View blocking records action per category through P3; disable destructive confirmation while blocked and keep the current selection.
- [ ] Test a dependency added between preflight/confirm, >100 blockers, local-inclusion removal, and unrelated records; no writes/success notice on rejection.
- [ ] Run the three affected feature files; review the dialog/list return path in the real workspace.

Acceptance: actionable reasons and exact blockers; no broad false-positive rule list, silent cascade, or misleading total.

### P5 — Batch selection and list editing

Modify: Attribute/Option relation managers, `ConfiguratorGroups.php` and view, `AssignConfiguratorGroups.php` only if a batch wrapper is needed, `GroupForm.php`, corresponding validation helpers. Create reusable table-selection configuration classes in existing `app/Filament/Resources/` if duplication warrants them. Test: extend `ConfiguratorWorkspaceTest.php`, `ConfiguratorAdministrationTest.php`, `GroupSettingsTest.php`.

- [ ] Add tests for equivalent single/batch inclusion, retained defaults/IDs/overrides, duplicate/foreign/stale IDs, and cancel/no partial writes.
- [ ] Implement direct Action/TableSelect selection and one shared explicit draft initializer; show required defaults in the same staged review.
- [ ] Batch Group membership by computing the complete final set and calling the existing action once; preserve other assignments and leaf restrictions.
- [ ] Add adjacent multiple Select for plain properties/values; insert missing metadata rows without replacing current row settings; keep mapping checkboxes unchanged.
- [ ] Test selection across pages/search, reopen/error roundtrip, A→B→A selection safety, newly added shared Options, and existing mapping invariants.
- [ ] Run the three affected feature files and check keyboard/multiselection flow in a narrow workspace.

Acceptance: one staged batch save, explicit default choices, no auto-expanded saved definitions, and fewer repeated selection dialogs.

### P6 — Per-table batch actions and text transformation

Create: UI factory `app/Filament/Resources/BatchEditActions.php`, pure `app/Services/StringFieldTransformer.php`, focused batch coordinator `app/Actions/BatchCatalogChanges.php`, `tests/Feature/Filament/BatchCatalogActionsTest.php`, `tests/Unit/StringFieldTransformerTest.php`. Modify table action configurations and existing save actions only to support agreed allowlists/transaction batching.

Interfaces: UI factory builds field/intent schema and previews but cannot persist; coordinator accepts a whitelisted table/action key, selected IDs and validated intent, reloads scoped rows and delegates to the existing boundaries. No arbitrary model/field dispatch. `StringFieldTransformer::replace(?string $value, array $options): ?string` supports the exact section 9 options; invalid regex throws `InvalidArgumentException`, converted into an action field error.

- [ ] Add failing tests for unchanged/set/clear/mixed values; all-or-none rollback; canonical code collisions; used key restrictions; Group setting preservation; unauthorized IDs.
- [ ] Add pure transformation tests with literal metacharacters, Unicode text, null/empty, captures, first/all, invalid patterns and replacement collisions. Example expected result: literal `Flange A` with search `Flange`, replacement `Seal`, case-sensitive/all becomes exactly `Seal A`; regex `(A)(1)` with replacement `$2$1` becomes `1A`.
- [ ] Implement section 9's baseline table allowlists and before/after preview; reuse real validators and locked stale-preview checks.
- [ ] Add removal/deletion confirmations through P4; preserve status actions until P8 supplies their domain contract.
- [ ] Run `php artisan test --compact tests/Feature/Filament/BatchCatalogActionsTest.php tests/Unit/StringFieldTransformerTest.php` plus the touched domain feature file.
- [ ] Review proposed extra utilities separately; implement only selected operators with their own resulting-value and transaction coverage.

Acceptance: no first-row mixed-value overwrite, arbitrary identity mass edit, raw SQL transformation, stale apply, or partial valid-subset save.

### P7 — Tabs, badges, deferral, and editor continuity

Modify: `EditConfigurator.php`, Overview/Group/Rule/Attribute forms where selected, `ContextSettings.php`, six Resources' navigation badges, optional `app/Services/AdminNavigationCounts.php`. Test: existing Workspace/ContextSettings/GroupSettings tests and new `tests/Feature/Filament/AdminBadgesTest.php` when cache/count behavior is introduced.

- [ ] Implement the purposeful form layouts in section 10; keep short forms and mapping checkboxes simple; retain Preview placeholder.
- [ ] Add authorized cached resource totals and owner-tab counts with exact meanings; refresh on appropriate existing events/create/delete/import.
- [ ] Measure initial query/render cost for each embedded tab and prototype deferral on the expensive ones only; keep loaded components alive.
- [ ] Test draft preservation, schema refresh on record/kind switch, hidden-tab validation visibility, callback badge updates and cache invalidation.
- [ ] Run the affected feature files; inspect tab switching, event refresh and Builder search without mounting dormant Preview.

Acceptance: fewer long independent sections, useful counts, no blanket deferral or new request/query multiplication disguised as optimization.

### P8 — Selected status schema and runtime

Prerequisite: record G1's answer and review G2 in this document before implementation. Modify the six Models/factories, Rule presentation, schema/forms/tables, relevant save/import actions, `ConfiguratorDefinition{Loader,Compiler}.php`, Configurator DTOs/engine, `Catalog{Discovery,Cards,Snapshots,Revisions}.php`/snapshot builder as required, and frontend route/state handling. Create status migration and `app/Actions/ChangeCatalogRecordStatus.php`; test `tests/Feature/Filament/CatalogStatusesTest.php` plus existing Runtime/Integration/Import/PublicCatalog suites.

- [ ] Derive one availability truth table from the agreed G1/G2 policy: shared active × local active × owner active × default Option active × ordinary rule state. State exact generated-code/diagnostic outcomes.
- [ ] Add independently derived tests before schema/runtime changes: disabling affects existing definitions; re-enabling restores availability; a rule cannot re-enable hard-disabled shared data; disabled defaults have the agreed outcome; imports retain flags.
- [ ] Generate migration for six true-default flags; update model casts/allowlists/draft serialization/compile/persist/duplicate paths. Keep existing Rule `is_active`.
- [ ] Implement one hard-availability source reused by loader/runtime/admin/customer-facing evaluation. Keep stored references and local initial-disabled semantics distinct; expose diagnostics/repair lists.
- [ ] Add icon status actions, All/Active/Disabled filters, bulk status and impact confirmation. For aggregate records, never use a default per-cell relationship save.
- [ ] Invalidate affected catalog revisions/snapshots/count caches and protect discovery/direct-route consistency. Do not use active-only queries that hide references from authoring diagnostics.
- [ ] Run status + affected Runtime/Integration/Import/PublicCatalog tests and guarded MySQL cases when lock/import behavior warrants them.

Acceptance: the approved truth table holds on save, reload, duplication, preview evaluator and catalog use; no accidental code shortening, reference loss, or imported-status reset.

### P9 — Admin Appearance settings and preview

Create section 11's model/action/service/page/view/factory/migration; test `tests/Feature/Filament/AdminAppearanceTest.php`. Modify panel registration and token rendering in admin/shared layouts.

Interface: `AdminAppearance::defaults(): array`, `AdminAppearance::current(): array`, `SaveAdminAppearanceSettings::handle(User $actor, array $settings): AdminAppearanceSettings`. UI consumes validated settings; save action owns normalization, lock, version and cache invalidation.

- [ ] Test defaults, boundaries/cross-field logo fit, unauthorized mutation, reload persistence, no-change save, and cache invalidation after actual save.
- [ ] Generate singleton/table/page/classes through available Artisan commands; no need to generate new resource navigation for a singleton.
- [ ] Implement Density/Navigation/Dialogs tabs and a persistent unsaved preview; Reset stages defaults, Save persists.
- [ ] Drive escaped numeric/enum CSS variables/native sidebar widths, preserving zero Filament page inset and frontend component ownership.
- [ ] Run `php artisan test --compact tests/Feature/Filament/AdminAppearanceTest.php`; visually compare preview and actual wide/narrow pages.

Acceptance: durable shared defaults with working cache refresh, no stylesheet rebuild per edit, no change to account authentication or existing theme selection.

### P10 — Dialog presets/maximize and slide-over drag

Create `resources/js/workspace-dialogs.js`, application dialog-heading/width-control view in existing `resources/views/filament/resources/`; modify `resources/js/app.js`, theme, AppServiceProvider and application action configuration. No new PHP test merely to mirror CSS; extend Workspace tests if action/state hooks change.

- [ ] Prototype header controls using native action/modal extension points; preserve action ownership/one Alpine lifecycle and the native focus trap.
- [ ] Reconcile the important global width override; apply suitable per-purpose presets and Maximize/Restore.
- [ ] Add bounded slide-over handle and keyboard presets; persist completed widths, clamp on viewport changes, clean up listeners on teardown/navigation.
- [ ] Verify typed unsaved text, selection, validation errors and scroll survive resize/maximize/restore and closing/returning from a managed child list.
- [ ] Build assets; use real-browser pointer and keyboard checks plus current Boost browser logs. Run affected action tests only if hooks/state changed.

Acceptance: width changes do not remount/refill/save forms; mobile/focus/Escape/Cancel behavior remains native and usable.

### P11 — Column-width prototype and persistence

Create `resources/js/table-column-widths.js`; modify table helper, app.js/theme and Columns controls; plugin dependency only if separately approved after prototype review.

- [ ] Prototype on Options and a nested Configurator table using merged attributes and stable keys; no header reordering.
- [ ] Verify sorting versus resize hit areas, wrapping/truncation, action/selection columns, pointer cancellation, keyboard width choice, horizontal overflow and zero per-move requests.
- [ ] Add per-user/table completed-width storage, safe parse/fallback, reapply on Livewire changes/column manager hide-show-order, Reset widths, and width clearing when the full native column reset runs.
- [ ] If native extension points cannot meet acceptance without broad vendor replacement, document the failed seam and assess the plugin's trait/attribute/assets integration before changing dependencies.
- [ ] Build and test the same scenarios with two tables mounted simultaneously, different user sessions, and responsive sizes.

Acceptance: stable widths, no action/selection/draft loss, no sort-on-drag, no competing ordering interface or leaked cross-user preference.

### P12 — Integrated acceptance and delivery

Consumes each phase's passing targeted checks. Existing test files are retained, including legacy fixtures; do not rewrite historical tests into the new application scope.

- [ ] Execute section 13's browser matrix on the actual authenticated application, not only mockups; finish relevant N5 navigation checks.
- [ ] Run narrow feature suites for changed behavior and the frontend build. For MySQL, verify the guarded disposable connection before executing its configured suite.
- [ ] Run `vendor/bin/pint --dirty --format agent` for PHP edits and inspect the allowlisted diff; review formatting changes against unrelated dirty files.
- [ ] Run `git diff --check`; record test/browser results and any unverified scenarios. Ask the user to run the complete `php artisan test --compact` after feature tests pass, as required by AGENTS.md.
- [ ] Present exact changed-file allowlist, remaining decisions/limitations and screenshots where useful. Commit/push only within the separately authorized delivery scope; never `git add -A`.

## 13. Verification specification

No application tests were run to write this plan. These are future checks, not claims of passing implementation.

| Contract | Discriminating evidence | Owning phase |
| --- | --- | --- |
| Authorization/scope | Authorized actor versus ordinary user; valid owner versus foreign submitted parent/IDs; no mutation/notification on rejection | P2–P9 |
| Scoped/combined search | Same text in allowed and foreign records; Code A + Value Flange; All versus one field; hidden columns; filter reset | P2/P3 |
| Count/list equality | Zero, five, >five, >100 items; direct relation/computed/array providers; exact meaning and pagination | P3/P4 |
| Safe previews | Escaped HTML/script-like names; bounded rows/queries; hover and keyboard focus | P3 |
| Dependencies | Referenced Option and unrelated sibling; defaults/rules/mappings/Groups; concurrent dependency addition | P4 |
| Batch selection | Search/page change, page/all-matching distinction, cancel, duplicates/foreign/stale rows, existing overrides | P5/P6 |
| Atomicity | Later selected row fails validation after earlier rows would pass; all records/drafts/revisions unchanged | P5/P6/P8 |
| Transformation | Literal metacharacters, captures, null/empty, case/first/all, PCRE error, uniqueness and stale preview | P6 |
| Editor roundtrip | A→B→A; rule-kind change; hidden-tab validation; badge event and drawer refresh retain unrelated draft | P5/P7 |
| Status consistency | Approved truth table; shared/local/owner/default combinations; restore; import; direct route/discovery parity | P8 |
| Appearance cache | Save/reload/new request/Reset/no change; malformed/out-of-range inputs; panel versus ordinary user | P9 |
| Resize | Typed text/errors/selection remain; cancel/maximize/restore; hide/show/order/rerender; listener cleanup | P10/P11 |

Browser matrix: wide resource table; two-column/narrow Configurator workspace; stacked layout around its existing 1200px transition; 390px mobile; light/dark content; collapsed/expanded sidebars and native group flyouts; search/filter/columns/action keyboard flow; long labels/breadcrumbs; nested count/blocker lists; account `wire:navigate`; reload/history/back; open mobile navigation while a dialog exists; unsaved editor while another table refreshes; two independent tables' resize state.

Use purpose-built browser tools and recent Boost browser logs. Do not install Pest Browser/Playwright dependencies just for this plan: the project does not currently include the Pest browser plugin. Separate browser/network/DOM/payload findings from SQL/query/render findings; fewer controls/requests are not proof of faster queries. Baseline and compare the same data/workflow when making a performance claim.

Useful execution commands, only when the relevant files exist:

```sh
php artisan list --no-interaction
php artisan make:class --help
php artisan make:model --help
php artisan make:filament-page --help
php artisan make:test --help
php artisan test --compact tests/Feature/Filament/ConfiguratorWorkspaceTest.php
php artisan test --compact tests/Feature/Filament/CanonicalDefinitionsTest.php
php artisan test --compact tests/Feature/Catalog/GroupSettingsTest.php
npm run build
vendor/bin/pint --dirty --format agent
git diff --check
```

Generators at execution: `make:class` for planned helpers/services/actions; `make:model AdminAppearanceSettings --factory --no-interaction` with a separate creation migration if required by the installed generator; `make:migration add_active_status_to_catalog_models --no-interaction`; new Pest tests with `make:test --pest Name --no-interaction` (use `--unit` for the pure transformer). Inspect help to choose valid Filament page/resource options; existing resources need no regeneration. Migrations are reviewed and tested before any requested database application.

## 14. Retained ideas, alternatives, and explicit exclusions

The user requested that all thoughts remain visible while later additional improvements can be reviewed after core fixes. These are recorded without converting every idea into an automatic implementation requirement.

| Idea/alternative | Disposition and reason |
| --- | --- |
| Visible condition → effect and related-rule context | Strong follow-up; improve existing summary/related-list visibility without a separate rule workbench |
| Copyable codes | Keep existing copyable Option code; extend to relevant code fields after core table work |
| Better initial column choices | Include sensible hidden ID/date/legacy defaults; honor saved manager choices |
| Explicit All/Active/Disabled filtering | Included with selected status models |
| Usage before deletion | Included as actionable dependency lists |
| Stable selected rows/drafts | Required acceptance contract, not optional polish |
| Personal density/default-width preferences with cache | Later option; define shared-default → personal override precedence and user-only save scope before adding user storage |
| Cross-device column order/visibility/width | Later option; native session visibility/order and browser widths are baseline; no DB preference migration silently added |
| Live sidebar drag | Later option; native collapse/configured widths already solve baseline; if selected, use bounded drag, keyboard settings and existing native state |
| Modal corner drag | Later option; header presets/maximize first; slide-over drag is selected scope |
| Column pinning/header drag reordering | Pinning not selected; header reordering explicitly excluded |
| Official paid Compact Theme | Alternative comparison only; no purchase/install, and it would not solve bespoke table/header controls |
| `asmit/resized-column` | Acceptable evaluated fallback; dependency/version approval and integration test required |
| Resizable modal plugin | Incomplete alternative because it skips slide-overs; do not adopt as the complete solution |
| Global column macro | Retained comparison; typed helper preferred to avoid global naming/analysis risks |
| Generic lifecycle flags on every model | Superseded by the explicit selected-model list |
| Future-use-only shared disabling | Superseded by disabling affecting existing Configurators |
| Table selection for mapping checkboxes/plain property keys | Superseded by inline mapping and multiple Select decisions |
| Runtime breakpoint regrouping | Avoid initially; configure narrow action grouping on the PHP table |
| Preview & Test evaluator/scenario workspace | Existing deferred proposal; retain placeholder until explicitly selected |
| New dashboard analytics, user suspension, audit-log system, background bulk jobs | Not part of these requirements; do not introduce through a generic UX cleanup |

## 15. Research register and limits

Primary implementation authority is the installed source plus version-scoped Boost documentation. Public examples/videos inform interaction choices; their version labels do not prove API compatibility.

| Source | Use and access limit |
| --- | --- |
| [Filament 5.3](https://github.com/filamentphp/filament/releases/tag/v5.3.0), [5.6](https://github.com/filamentphp/filament/releases/tag/v5.6.0), [5.7](https://github.com/filamentphp/filament/releases/tag/v5.7.0), [5.8](https://github.com/filamentphp/filament/releases/tag/v5.8.0) | Earlier research established column-manager modal, slide-over/badge/heading, rendering/sidebar, and deferred-schema additions; current installed APIs rechecked |
| [Filament 5.9 release](https://github.com/filamentphp/filament/releases/tag/v5.9.0) | Current installed release; fresh release-note check: Builder search, filter-reset customization, breadcrumbs and table-content hooks |
| [FilamentExamples bulk-edit modal](https://filamentexamples.com/project/filament-v4-table-bulk-edit-modal) | Interaction reference; actual mutations must use this application's domain rules |
| [FilamentExamples sidebar resize](https://filamentexamples.com/project/drag-to-resize-sidebar-for-filament) | MCP/source reference for Alpine/CSS/persistence; not a table-width implementation |
| [FilamentExamples custom table column](https://filamentexamples.com/project/custom-table-design-viewcolumn) | Rendering ideas; prefer native typed helper unless custom rendering is necessary |
| [FilamentDaily actionable tables](https://www.youtube.com/watch?v=0sipJqwC_YY) and [column resize](https://www.youtube.com/watch?v=STj8HYjezWE) | Earlier descriptions/chapters and matching plugin identified; no claim of a complete transcript/code review |
| [FilamentDaily modal resize](https://www.youtube.com/watch?v=G8xwZld0RIQ) and [sidebar drag](https://www.youtube.com/watch?v=-Yutwkt0JP4) | Related references, not verified complete solutions for both requested dialog types |
| [Official demo](https://demo.filamentphp.com/) | Earlier native interaction reference; not proof that custom behavior works in this application |
| [Resized-column repository](https://github.com/asmitnepali/resized-column) | Filament 5 support/persistence; PHP integration inspected earlier, current repository rechecked; full frontend integration remains untested |
| [Pointer capture](https://developer.mozilla.org/en-US/docs/Web/API/Element/setPointerCapture), [Alpine lifecycle](https://alpinejs.dev/globals/alpine-data) | Resize primitives/listener cleanup; native form/navigation state must remain intact |
| [Existing navigation plan](NAVIGATION_IMPLEMENTATION_PLAN.md) | More recent shell decisions and separately reported local verification; preserve its implemented boundaries and finish remaining browser checks |

Earlier measured header/row/toolbar heights were historical observations. Do not reuse them as current benchmark results. No exact FilamentExamples implementation was found for the proposed generic count helper, combined scoped-search control, or bulk find/replace; these are small application designs based on native APIs, not copied examples.

This plan is the only repository artifact created by this planning task. No application implementation, schema migration, database mutation, dependency change, test execution, commit, push, or deployment is represented as completed here.
