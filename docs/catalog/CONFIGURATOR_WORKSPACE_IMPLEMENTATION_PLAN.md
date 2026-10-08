# Configurator workspace: revised implementation plan

Updated 2026-10-07. **Planning only.** This document reconciles the previous Configurator UX proposals with the current implementation and the latest operator corrections. It replaces stale Configurator-specific proposals in the [shared admin plan](ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md); it does not replace unrelated admin, Group, card or catalog work.

Baseline: `76f953e` (`Add catalog lifecycle status and refine Configurator workspace`), plus ongoing uncommitted shared admin corrections. Installed versions confirmed with `composer show --direct`: Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7, Pest 5.3.0; PHP 8.4. Recheck source and the dirty-file inventory before implementation because the **Research Filament 5 admin UX** chat is working in the same tree.

## 1. Goal, authority and current completion status

The operator should be able to find an Attribute or Rule, understand its relationships, change it, and save it in one visible workspace. Search, useful quick filters, tags where they exist, ordering and the selected editor must remain readily accessible. Keep the already successful Group assignment flow.

Carry forward the useful patterns from the previously reviewed admin sketch/HTML: immediate assignment, list-and-detail selection, visible Attribute/Option relationships and side-by-side Mapping membership lists. They guide the interaction and density; current Laravel models, saved IDs and aggregate actions determine persistence.

| Area | Confirmed current state | Remaining work in this plan |
| --- | --- | --- |
| Manage page | Full edit page with Overview / Groups / Attributes / Rules / Preview & Test; no misleading page-wide Save | Retain the tab structure and scoped saves; mount the existing Preview component in the placeholder tab |
| Attributes and Rules | Related table/editor layout, selected-row highlighting, independent retained drafts, schema rebuild on selection/kind changes | Improve compact layout, action placement, visible summaries, quick access and pane resizing |
| Attribute inclusion | Single inclusion automatically stages eligible shared Options; batch inclusion uses native `TableSelect` and an explicit-default review | Compact the review; preserve per-Attribute review state through selection changes; fix append ordering on definitions with gaps |
| Options | Related table inside the selected Attribute, single/batch inclusion, separate Option saves, removal/default/reference protection | Add visible row ordering and a selected Option editor in the workspace |
| Rules | Mapping and Advanced kinds, staged whole-rule saves, source/target checklists, stale-reference warnings | Make When → Then summaries visible; add a per-Mapping-set Disable/Hide choice |
| Row ordering | Attribute/Rule move actions are currently inside row menus; Options have native reorder mode | Put Up/Down directly before Edit, initially visible; replace the exclusive reorder-mode trigger with a visibility toggle in More |
| Sizing | Persistent dialog widths and table-column sizing exist; related panes use a fixed unequal grid | Add a separate stepped table/editor splitter starting at 50/50 |
| Shared admin UX | Compact shell/forms, search scopes, count drawers, columns/filters dialogs, bulk review and status controls exist | Reuse those components with explicit Configurator settings; small quick filters must also be visible outside the Filters dialog |
| Status and lifecycle | Implemented in the current baseline, including engine/loader changes | Preserve the shipped decisions; remove the previous claim that this phase is deferred |
| Dashboard context | Dashboard and saved Preview use a loader that excludes separate Territory/Application context | Preserve that boundary; clearly identify saved future-public-only Rules in authoring and diagnostics |
| Groups and cards | Group tabs, property multi-selection and card appearance changes exist | Preserve the new save boundaries/settings; use Presets as the visible name where the older SubGroups wording remains |

Confirmed operator decisions take priority over older contracts and examples. The [admin contract](contracts/FILAMENT_ADMIN.md), [engine contract](contracts/ENGINE_AND_DATA.md), [Group/card handoff](CARD_APPEARANCE_ADMIN_UX_HANDOFF.md) and current source remain supporting references. Their older statements about first-Option defaults, combined Attribute/Option editing, global context in dashboard Preview, or deferred status work are superseded by the decisions below.

Presentation defaults selected for this plan: main workspace checkbox selection starts off; splitter stops are the three stated ratios; inherited tags mean shared `Value.tags`, not a new local tagging system. These are reversible UI choices, not new business rules.

## 2. Dashboard, future public catalog and lifecycle boundaries

### Dashboard and saved Preview

- The logged-in catalog/configurator is for company agents. Territory and Application are ordinary included Configurator Attributes when the business requires them. Their labels or keys do not grant special context behavior.
- Separate global Territory/Application entities are reserved for a future public catalog. Do not restore the removed dashboard side component, teleport area or separate context selectors in Preview & Test.
- Preserve `ConfiguratorDefinitionLoader::forDashboardProduct()` and `forPreview()` using the dashboard path. It ignores submitted/restored public context, converts ChangeContext to reevaluation, and supplies no global context choices to compilation.
- A Rule with any Territory/Application predicate, including a predicate inside an All/Any group, is omitted **as a whole** from dashboard evaluation. Removing only that predicate could change Boolean meaning. Keep the saved Rule and its references unchanged.
- Preserve the generic `forProduct()` path for future public evaluation. Do not delete public context definitions or automatically convert their predicates into Attribute selections by matching names.
- Authoring should show a compact **Future public only** indicator on Rules containing these predicates. Diagnostics should explain their omission without presenting them as inactive or invalid dashboard Rules. Existing future-public authoring/settings can remain in their explicitly labeled area.

### Already implemented status policy

- Configurator disabling already offers the three reviewed outcomes for assigned Groups/Products: remain visible without configuration, hide while disabled, or unassign while keeping Products visible without configuration. Preserve the existing impact review and restoration semantics.
- Disabled shared Options and Hidden shared Options both prevent selection. Shared Hidden is a lifecycle state; it is different from a temporary Mapping-set Hide result or local initial presentation flags.
- An inactive required Attribute, or an inactive/hidden **shared stored default**, makes the configuration unavailable until repaired or re-enabled. Local initial flags and runtime Rules still use the existing temporary selection fallback without changing the stored default.
- The status migration is implemented; the shared admin chat reports that it was locally applied. This audit does not establish production deployment state; implementation must verify the release target rather than repeat or remove this phase.

Current verification: `php artisan test --compact tests/Feature/Catalog/ConfiguratorIntegrationTest.php --filter='context|territory|application'` passed **3 tests / 29 assertions** on SQLite `:memory:`. These cases distinguish dashboard/Preview from public evaluation, exercise nested context Rules and stale state, and preserve actual Attribute behavior. This revision pass used source inspection and focused tests; it does not claim a fresh browser acceptance pass or the other chat's larger test results.

## 3. Data change: per-Mapping-set disallowed-target behavior

This is the one new persisted domain setting in this revision. It requires an additive migration and coordinated form, draft, compiler, persistence, DTO and engine changes.

| Element | Exact change |
| --- | --- |
| Enum | Add `App\MappingTargetBehavior` alongside existing root-namespace enums, with string-backed cases `Disable = 'Disable'` and `Hide = 'Hide'` |
| Table | Add `mapping_sets.disallowed_target_behavior`, non-null string length 16, default `Disable`; retain existing IDs, FKs, source/target memberships and set order |
| Model | Add the field to `App\Models\MappingSet` fillable attributes and cast it to `App\MappingTargetBehavior` |
| Factory | Default `Database\Factories\MappingSetFactory` to Disable; add/use explicit Hide fixture data where needed |
| Serialized definition | Add `disallowed_target_behavior` to each Mapping set; both modes survive load → edit → validation → save → reload and Configurator duplication |
| DTO | Add `disallowedTargetBehavior` to `App\DTO\ConfiguratorMappingSetDTO`, typed as the enum, with Disable as the compatibility default |
| Validation | Extend strict set-key allowlists in `ConfiguratorRuleDraft` and `ConfiguratorDefinitionCompiler`; missing legacy field means Disable, but an explicitly invalid/null/empty value is rejected at its set field |
| Normalization | The compiler's normalized `ConfiguratorDefinition::data` must contain the canonical string value. The save path already persists normalized data: include this field in the `MappingSet` persistence allowlist |

Commands for later implementation, only if the corresponding files are still absent:

```bash
php artisan make:enum MappingTargetBehavior --string --no-interaction
php artisan make:migration add_disallowed_target_behavior_to_mapping_sets_table --table=mapping_sets --no-interaction
```

Existing resources, pages, relation managers and schemas are updates; do not scaffold replacements. Existing test files cover the affected areas, so extend those instead of creating redundant test suites. Do not add packages. Reinspect the database schema through Boost before writing the migration. Exercise the migration on disposable test databases; no active-database migration is part of planning.

### Canonical runtime rule

For the currently matched Mapping set, calculate disallowed targets from the target Attribute's complete local Option membership minus the set's allowed targets.

1. Both Disable and Hide intersect the same current legal Option list with the allowed targets.
2. **Disable:** add disallowed targets to runtime disabled state. Otherwise-visible targets remain displayed but cannot be chosen.
3. **Hide:** add disallowed targets to runtime hidden state. They are omitted from the choices and cannot be chosen.
4. Recompute runtime presentation from the definition on each evaluation. Switching from a Hide set to a Disable set or an unmapped source removes the previous set's temporary hidden result, subject to all other restrictions.
5. Preserve source uniqueness within a Rule, target overlap across sets, and the existing behavior that an unmapped source adds no restriction. Preserve intersections across matching Rules and priority-based presentation semantics. A Hide restriction controls visibility when another Rule also disables the same Option; neither mode re-enables lifecycle-blocked or otherwise excluded Options.
6. Preserve runtime fallback, downstream settlement, strict intent rejection and configuration-code generation. Do not write any shared Option flags, local initial flags, stored defaults or saved selections during evaluation.

Example acceptance oracle: source A0 allows target B0 with Disable; source A1 allows B1 with Hide. A0 shows B1 disabled and selects B0; A1 omits B0 and selects B1; returning to A0 shows B1 again, disabled. The saved default and membership IDs stay unchanged. Repeat through both dashboard and saved Preview.

## 4. Workspace and action contract

### Visible rows and More actions

| Surface | Direct actions | More actions | Initial state |
| --- | --- | --- | --- |
| Attributes header | **+ Include Attributes**, visible search/scope, useful quick filters, Filters, Columns | Include attribute, Code order, Reorder rows, Show/Hide selection, available bulk actions | Ordering visible; main-table checkbox selection off |
| Attribute row | **Up, Down, Edit**, then remaining direct single action such as Remove | A row menu only if more than one remaining visible action needs it | Arrows precede Edit |
| Options header | **+ Include Options**, visible search/scope and inherited-tag filter, Filters, Columns | Include option, Reorder rows, Show/Hide selection, available bulk actions | Ordering visible; main-table checkbox selection off |
| Option row | **Up, Down, Edit**, then Remove with existing dependency protection | A row menu only if needed for multiple other actions | Explicit Option Save in the selected editor |
| Rules header | **+ Add rule**; its choices are Mapping and Advanced; visible search/scope and quick kind/activation filters | Reorder rows, Show/Hide selection, available bulk actions | Ordering visible; main-table checkbox selection off |
| Rule row | **Up, Down, Edit**, then Remove | A row menu only if needed for multiple other actions | Summary visible, without opening the editor |

Every add/create/include trigger in the Configurator scope has a plus icon, including Add rule's **group trigger**, Add mapping, Add advanced rule, Add set, Add condition/group/effect, inclusion flows and any creation shortcut. Save, Apply, Remove and visibility toggles retain their appropriate icons. Explicitly override the Add rule group icon; its default ellipsis icon is non-null, so a null-coalescing fallback will not fix it.

Flatten a menu with one remaining **visible and authorized** ordinary action into that action's button; omit an empty menu. Preserve its label, tooltip, disabled reason, confirmation and action context. **Explicit exceptions:** Reorder rows and Show/Hide selection remain in More as requested. Single Include attribute can also remain there. Evaluate actual visibility, not just the configured action count or selected-record count; More must remain available with zero selected rows.

Use icon buttons and tooltips where labels crowd the table. Keep accessible labels and visible disabled boundary controls; the tooltip must explain a boundary or ordering-sort restriction. Clicks on arrows, selection checkboxes and menus must not also trigger the row's Edit action. Keep actions reachable when the table scrolls horizontally.

The shared presentation helper must preserve an action's configured dynamic tooltip when supplying generic defaults. Evaluate menu visibility in the actual row/action context, retaining registered action identities so grouping changes do not break mounted actions.

### Ordering state and persistence

- Introduce a presentation flag `showOrderControls`, initially true, in each scoped related table. **Reorder rows** toggles this flag only, with an appropriate Show/Hide label/state. It does not turn on Filament's exclusive reordering mode, clear search/filters, conceal normal actions, refill the editor or write data.
- Keep the current aggregate save boundary. Attribute arrows change `display_order` only; Code order remains a separate action changing `code_order` only. Option arrows change local `display_order` only. Rule arrows change `priority` in the currently established descending order.
- Attribute and Rule move methods already exist; reuse their current fresh-definition merge. Add Option moves using the same scoped aggregate pattern. Re-resolve the owner, chosen row and full ordered neighbor list server-side inside `SaveConfiguratorDefinition::change()`; reject foreign/deleted rows before persistence.
- An Up/Down move swaps with the adjacent member of the **full owner order**, even with filtering. Its tooltip identifies the neighboring row when filtering could hide it. Disable movement if the table is explicitly sorted on a different column or direction, with guidance to restore the owner order. Do not silently alter a user-selected sort.
- Normalize the changed axis through the existing ordering contract; preserve the other ordering axes, stable IDs, defaults and Rule references. At the first/last row, retain disabled arrows instead of changing the action-cell layout.
- Preserve unsaved content while applying row moves. Attribute content saves already omit owned Option/order state; Rule saves already copy fresh saved priority instead of replaying a hidden draft priority. Retain those protections, and give the new Option editor the same protection against replaying stale order/default metadata.
- Flush relevant table records and refresh Preview freshness after a successful mutation. Preserve editor selection, search, applied/deferred filters, width preferences, unrelated drafts and existing stale-batch protection. Ordering preference toggles themselves are not definition mutations.

### Main-table selection

- Add a Show/Hide selection action inside More for Attributes, Options and Rules. Use `Filament\Tables\Table::selectable(fn (): bool => $this->showSelection)` with a scoped presentation flag.
- Show selected count and bulk actions while selection is enabled. Preserve explicit selected IDs across search/filter changes, subject to the existing scoped server validation and bulk impact review.
- Turning selection off clears selected rows and hides selection-only controls. If an open bulk action has unsaved draft state, retain the existing discard protection before clearing it. Keep all unrelated editor drafts.
- This flag does **not** affect Attribute/Option `TableSelect` pickers, Mapping-set source/target checkboxes or Group assignment controls. Those remain immediately usable without enabling main-table selection.

### Stepped table/editor splitter

- Use the gap as a visible, draggable separator on Configurator table/editor layouts. Default/reset ratio is **50/50**. Allowed ratios are **1/3–2/3, 1/2–1/2 and 2/3–1/3**; neither pane may become smaller than one third of usable width.
- Snapping uses available container width excluding the divider. Pointer movement previews the nearest step; release stores the selected step. Keyboard Left/Right moves one step, Home/End reaches the bounds, and double-click or Reset restores the center. Account for RTL positioning and prevent accidental text selection while dragging.
- Give the divider `role="separator"`, vertical orientation, a focus target and announced current/min/max values. The hit area should be larger than its visual line; show a grab affordance on hover/focus.
- Store the ratio separately from dialog widths and individual table-column widths, keyed by authenticated user, Configurator and workspace (Attributes, Rules, or Options plus the owning Attribute). Apply it locally without sending a Livewire request on every pointer event. Storage failure falls back to 50/50.
- Preserve the ratio across rerenders and row switches; initialize and clean up listeners/observers once per keyed workspace. Avoid replacing or ignoring the entire Livewire editor DOM to preserve the divider.
- At container widths below **980 px**, stack the panes and hide the divider. This accommodates two 320 px minimum panes at the one-third stop plus the divider. Preserve the chosen desktop step when returning to a wide container.
- Make field grids, Mapping lists, action bars and summaries respond to **pane width**, not just viewport width. Options can reuse the same component when their containing workspace is wide enough; otherwise their selected-row editor stacks in the Attribute pane. Do not force a second narrow nested split.
- Reuse a matching splitter if the concurrent shared admin work introduces one before implementation; the Configurator ratios and visibility contract still apply. Do not change unrelated resource layouts globally as a side effect.

## 5. Forms, inclusion and visible relationships

### Attributes and Options

- Keep the Attributes table alongside the selected Attribute's compact local settings and included Options. Make shared label/key, effective local label, stored default and Option count understandable without opening a count drawer. Drawers remain for secondary inspection.
- Use **Save attribute** for local Attribute settings/default, and **Save option** for the selected Option's fields. No per-cell autosaves and no apparent combined Save covering unrelated drafts. New inclusion still stages and saves the complete new inclusion atomically.
- Replace routine Option-edit modals with a selected-row editor in this workspace. Preserve the selected Option draft through list refresh and save of the parent Attribute. Switching records/kinds rebuilds the schema before fill; unchanged visits must not become dirty drafts.
- Cache Option drafts by owning Attribute and Option before switching the parent Attribute; A → B → A must restore them rather than lose them when the child component remounts. Close/discard and batch-stale protection must cover these drafts too.
- Clear local label/display/hint overrides to null so the inherited shared value appears again. Show the inherited value as context; do not copy it into a local override merely on hydration.
- Show stored default separately from a Preview's current runtime choice/fallback. Moving Options does not choose a default. Remove the remaining legacy first-Option auto-default hook in new inclusion forms: the default is an explicit reviewed choice, including one-Option Attributes.
- Saving a new parent default is an explicit action before removing the previously saved default Option. A pending replacement in the Attribute editor is insufficient for a separate Option removal. Give direct guidance without silently applying the parent draft.

### Single and batch inclusion

- Keep both flows. **+ Include Attributes** is direct; single **+ Include attribute** may stay in More. The single picker automatically stages all currently eligible shared Options through `ConfiguratorInclusionDrafts::attribute()`; do not make the operator select them one by one.
- Retain the native checkbox/filter table picker. Eligible new Attributes are active and not already included; eligible new Options are active, visible, belong to the chosen shared Attribute and are not already included. Current source already validates new inactive/hidden membership under the aggregate lock: preserve it rather than relying on picker filtering.
- Review each selected Attribute with a compact summary (label/key, Option count and explicit default), expandable local settings and Options. Expand invalid rows automatically and provide a top-level error summary linking to fields. This compact review replaces the current repeated full-size forms without changing the save boundary.
- Retain review state keyed by canonical Attribute ID when temporarily deselecting/reselecting or filtering the picker. Apply only the explicitly selected reviewed rows. Do not submit cached deselected rows or regenerate surviving local IDs/defaults because the selected list changed.
- Reuse one initializer for both flows. State assigned with `Set` must include dependent Options/default choices explicitly; do not rely on another field's updated hook firing after programmatic assignment.
- Append Attributes at **max existing display_order + 1** and independently **max existing code_order + 1**, advancing each counter for each new row; an empty axis starts at 0. The current count-based append is unsafe when orders contain gaps. Append Options after the existing owner order and preserve its saved default.
- On submit, re-resolve selected identities/eligibility and review consistency against the current locked definition. Reject stale, duplicate, foreign, empty or mismatched reviews atomically, with the selected IDs and edited review intact. No partial batch success or fabricated default/code.

### Rules and Mapping sets

- Keep persisted Rule kinds Mapping and Advanced. Optional Hide/Disable/presentation creation shortcuts initialize an Advanced draft; they do not create new engine kinds or permit destructive kind conversion of a saved Rule.
- Show a concise When → Then summary in the list by default. For Mapping include Driver → Target, set count and disallowed-target modes; for Advanced include the condition/effect meaning. Include future-public-only scope and activation distinctly. Long summaries can wrap with a full-text tooltip.
- Keep the condition builder, Mapping sets and Advanced effects in one selected editor with compact domain labels. Avoid unnecessary nested card/section chrome or extra tabs that conceal essential fields.
- Each Mapping set shows label, Driver Options, Allowed targets and **Disallowed targets: Disable / Hide** together. Use an immediately visible two-choice control next to the set label, defaulting to Disable. Set membership checkboxes remain visible; add local search/counts within long lists without discarding selected or stale IDs.
- Retain stable set/condition/effect keys. Changing Driver/Target keeps incompatible references visible for explicit repair; do not silently clear them. Keep duplicate-source and stale-reference warnings next to the corresponding list. An invalid hidden Builder block must reach validation.
- Add set/condition/group/effect changes stay in the Rule draft until Save rule. Cancel causes no persistence. The per-set mode follows the same whole-rule save and is copied on Configurator duplication.

## 6. Search, quick filters, tags and component specifications

Reuse the shared search toolbar and scope selector. Search remains visible with ordering controls enabled, and responds through the existing debounced scoped-search path. Reuse only legitimate searchable columns; submitted search scope is validated against the table's allowlist.

Small frequently used filters are visible in the workspace toolbar: Attributes use activation/input type; Options use initial availability and inherited Value tags; Rules use activation/kind and dashboard/future-public scope. Advanced combined constraints stay in Filters. Active filter chips show directly below the toolbar and can be removed there. On narrow panes these controls wrap instead of moving into an extra menu.

Quick filters apply only their own state and synchronize the corresponding applied/deferred key. Changing a quick filter or removing one chip must not apply or discard an unrelated, unfinished advanced filter draft. Preserve this separation through sorting, row moves and splitter changes.

Tags currently belong to shared `App\Models\Value`. Show read-only inherited tags on Options and their picker, with a visible tag filter. Attributes/Rules do not gain invented local tags; their active-filter chips are still visible. Tag edits continue through the shared Value resource and existing reviewed batch tools.

Specifications below cover changed elements; unchanged field validation and columns retain the current source contract.

| Element | Component / Docs | Validation and Config |
| --- | --- | --- |
| Manage tabs | `Filament\Schemas\Components\Tabs` and `Filament\Schemas\Components\Tabs\Tab`; [tabs](https://filamentphp.com/docs/5.x/schemas/tabs) | Keep one outer column, full span, `->persistTabInQueryString('tab')`; stable owner-scoped Livewire keys; no new page-level Save |
| Preview mount | `Filament\Schemas\Components\Livewire`; [Livewire in schemas](https://filamentphp.com/docs/5.x/schemas/custom-components) | `Livewire::make(App\Livewire\Catalog\ConfiguratorPreview::class, owner arguments)->key(owner key)->lazy()` in the existing Preview & Test tab |
| Batch picker | `Filament\Forms\Components\TableSelect`; [installed component source](https://github.com/filamentphp/filament/blob/5.x/packages/forms/src/Components/TableSelect.php) | Required list of distinct positive IDs; `->multiple()->required()->minItems(1)->tableConfiguration(existing selection table)->tableArguments(owner arguments)`; reactive Attribute review uses `->live()` |
| Stored default | `Filament\Forms\Components\Select`; [select](https://filamentphp.com/docs/5.x/forms/select) | Required local membership/staging key; `->required()->live()->options(scoped reviewed options)`; no implicit first-Option selection |
| Mapping sets/mode | `App\Filament\Forms\Components\MappingSetsField`, extending `Filament\Forms\Components\Field`; [custom fields](https://filamentphp.com/docs/5.x/forms/custom-fields) | At least one set with nonempty valid source/target lists; mode only Disable/Hide; `->required()->default([])->columnSpanFull()`; add `disallowed_target_behavior` to the entangled set object and render a labeled radio/button group in its existing Blade field |
| Visible Rule summary | `Filament\Tables\Columns\TextColumn`; [text columns](https://filamentphp.com/docs/5.x/tables/columns/text) | Existing `summary` becomes visible by default: `->wrap()->toggleable(isToggledHiddenByDefault: false)`; derive from eager-loaded saved Rule data, not per-row queries |
| Future-public indicator | `Filament\Tables\Columns\TextColumn`; same docs | Derived scope badge, `->badge()`; inspect all saved condition groups using the same context-detection rule as the dashboard loader |
| Quick finite choices | `Filament\Forms\Components\ToggleButtons` and `Filament\Tables\Filters\SelectFilter`; [toggle buttons](https://filamentphp.com/docs/5.x/forms/toggle-buttons), [select filters](https://filamentphp.com/docs/5.x/tables/filters/select) | Validated allowlisted choices, `->inline()->live()`; bind quick controls to their own applied filter key, preserving unrelated deferred keys |
| Inherited tags | `Filament\Tables\Columns\TextColumn` plus `Filament\Tables\Filters\SelectFilter`; same column/filter docs | Eager-load `option.value`; show `Value.tags` as badges; `->multiple()->searchable()` with the existing tag vocabulary/query convention; live quick control, no local tag persistence |

Reactive schema imports:

```php
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
```

Actions all use `Filament\Actions\Action` (and `Filament\Actions\ActionGroup` for explicitly grouped triggers). Docs: [actions](https://filamentphp.com/docs/5.x/actions/overview), [grouping](https://filamentphp.com/docs/5.x/actions/grouping-actions), [table actions](https://filamentphp.com/docs/5.x/tables/actions).

| Action | Location / Visibility | Authorization / Behavior / Config |
| --- | --- | --- |
| moveUp / moveDown | Row, before Edit; visible when `showOrderControls`; disabled at boundaries/alternate sort | `->authorize('manage-catalog')->iconButton()->icon(Heroicon::OutlinedArrowUp/OutlinedArrowDown)->tooltip(...)`; scoped fresh owner-order swap through aggregate save; preserve drafts |
| Reorder rows | More, always reachable independently of selection | Existing manage-catalog access; toggle presentation flag only; stateful Show/Hide ordering label; no native exclusive reorder mode |
| Show/Hide selection | More, on the three main related tables | Existing manage-catalog access; toggle selectable flag and clear selection safely on hide; preserve mounted-draft protection |
| Include Attributes / Options | Direct header plus button; single include remains in More | `->authorize('manage-catalog')->slideOver()`; native picker and reviewed atomic save; `->icon(Heroicon::OutlinedPlus)` with accessible label/tooltip |
| Add rule | Direct header plus group; Mapping/Advanced choices | Group `->iconButton()->icon(Heroicon::OutlinedPlus)->tooltip('Add rule')`; each child `->authorize('manage-catalog')`; open retained staged editor, no immediate write |
| Edit | Row, after ordering controls | `->authorize('manage-catalog')`; owner-scoped selection, rebuild schema before filling retained draft; icon/tooltip supplied by shared presentation |
| Save attribute / option / rule | Selected editor; labeled buttons remain clear | Reauthorize `manage-catalog` in every request; validate form and complete affected definition, merge only owned fields under lock, then refresh affected records/diagnostics |
| Remove | Direct if it is the sole remaining row action; otherwise row More | Existing `->authorize('manage-catalog')->requiresConfirmation()` and dependency/impact checks; preserve atomic rejection and no cascade through unrelated/shared records |

## 7. Preview & Test

- Replace the EditConfigurator placeholder with the existing `App\Livewire\Catalog\ConfiguratorPreview`. Use the saved-definition flow and currently assigned leaf-Group Product picker; do not build a second evaluator or invent Product facts.
- Show **Saved definition** and explain that unsaved Attribute/Option/Rule drafts are not included. Add an obvious refresh/test action and a stale indicator after saved mutations. Refresh must preserve the selected real Product and revalidate current assignment.
- Show configuration code, current choices, hidden/disabled/illegal Options, no-legal-Option diagnostics and unavailable status reasons. Include the per-set Disable/Hide result in the same engine-derived output.
- Extend the current basic Rule diagnostics with an optional authorized trace: matched Rules/sets, reasons for exclusion, and effective fallback. Instrument the existing engine/definition path; do not reconstruct Rule decisions independently in Blade or change the visitor result format.
- A trace must not change legal choices, settlement order or code. Identify saved future-public-only Rules as skipped on dashboard Preview from the saved authoring definition; do not reintroduce their predicates into evaluation.
- No separate global Territory/Application fields in this tab. Actual included Attributes use normal controls. Preview/Test/refresh must not write definitions, defaults, Product properties or assignments.

## 8. Files, authorization and shared-work boundaries

Existing Resource location: `/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/ConfiguratorResource.php`. Existing edit Page location: `/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Pages/EditConfigurator.php`. [Resource docs](https://filamentphp.com/docs/5.x/resources/overview). No resource/page generation command is required.

| Work | Owning paths under `/Users/studioycm/Herd/configurator/` |
| --- | --- |
| Related-table/editor behavior | `app/Filament/Resources/Configurators/RelationManagers/{Attributes,Options,Rules}RelationManager.php`; `resources/views/filament/resources/configurators/{attributes,rules}.blade.php` |
| Field/review layout | `app/Filament/Resources/Configurators/Schemas/{ConfiguratorAttributeForm,ConfiguratorRuleForm}.php`; `app/Services/ConfiguratorInclusionDrafts.php` |
| Mapping field | `app/Filament/Forms/Components/MappingSetsField.php`; `resources/views/filament/forms/components/mapping-sets-field.blade.php` |
| Mapping persistence/evaluation | `app/MappingTargetBehavior.php`; additive migration; `app/Models/MappingSet.php`; `database/factories/MappingSetFactory.php`; `app/DTO/ConfiguratorMappingSetDTO.php`; `app/Services/{ConfiguratorRuleDraft,ConfiguratorDefinitionCompiler,ConfiguratorDefinitionLoader,ConfiguratorEngine}.php`; `app/Actions/SaveConfiguratorDefinition.php` |
| Splitter/presentation | `resources/views/components/catalog/related-editor.blade.php`; existing shared workspace assets/JS registration; narrowly scoped additions to `resources/css/filament/admin/theme.css` and `app/Filament/Resources/TablePresentation.php` |
| Preview | `EditConfigurator.php`; `app/Livewire/Catalog/ConfiguratorPreview.php`; `resources/views/livewire/catalog/configurator-preview*.blade.php`; opt-in engine trace DTO/state only if required |

Use the existing `manage-catalog` Gate and panel admission; this plan does not introduce new roles or grant agents administration access. Gate checks must run on later Livewire requests, not just mount. Resolve all inclusion, Option, Rule, set and preview-Product IDs under the current owner. Invalid/unauthorized state produces validation/403/404 before writes, redirects or success notifications.

All definition mutations use `SaveConfiguratorDefinition::change()` / existing `reorder()` APIs, owner locking, complete-draft compilation and atomic persistence. Preserve stable memberships, mapped local references, retained invalid drafts, current canonical eligibility checks, no-op behavior and existing stale-batch review checks. Visual filters, disabled inputs and hidden UI never serve as the server boundary.

The shared admin chat currently has edits in shared resource creation/listing, providers, split-list views and theme CSS. Recheck those files and reuse its final components before editing them. Preserve unrelated dirty files; do not reset, stash or format all concurrent PHP files. The filter-performance/catalog work owns discovery filters, snapshots, cards and Group result settings. This plan does not move filter state back to the server or reconstruct that page.

Keep `SaveGroupSettings` semantics: only inner `result_settings` supports partial merge, preserve complete filter/preset lists and unrelated card settings, and coalesce one aggregate revision when a real Group change occurs. The Group assignment workflow remains as implemented.

Record a reviewed rollback baseline only after the operator finishes the small tasks/push they requested. `76f953e` is an inspected reference, not a newly created checkpoint. Do not commit/tag/push, copy the active database, or deploy as part of this planning task.

## 9. Implementation order and acceptance checks

### Phase A — reconcile shared admin and expose workspace controls

Reinspect concurrent changes first. Apply scoped plus icons, menu flattening with explicit More exceptions, visible row arrows, selection toggle, always-visible search/quick filters/chips and default-visible Rule summaries. Keep the existing successful editor/draft and priority protections.

Acceptance: initial Attributes/Options/Rules show Up/Down before Edit; More → Reorder rows hides/restores them without changing search, selection, editor or saved order. Main selection toggle does not affect picker/Mapping checkboxes. A one-action row menu is direct. All add triggers, including grouped Add rule, show plus. A quick filter does not apply an unfinished advanced filter draft.

### Phase B — resizable workspaces and compact Option/review forms

Introduce/reuse the stepped splitter; adapt form grids to pane width. Add the selected Option editor and compact inclusion review, remove implicit default selection and fix independent append axes.

Acceptance: new workspace opens 50/50; pointer and keyboard reach exactly the three stops; neither pane becomes smaller than a third; rerenders retain the step and dirty form state. Narrow containers stack cleanly. Separate Option Save preserves pending Attribute fields; parent Save and parent A → B → A selection preserve Option drafts. A missing default blocks all new inclusion writes. Deselect/reselect restores reviewed values. Gapped display/code orders append independently without collisions.

### Phase C — per-set Disable/Hide through the full save and runtime path

Apply the additive data change, strict normalization/validation, field state, duplication and engine behavior together. Preserve legacy Disable behavior and existing Mapping cardinality/reference rules.

Acceptance: different sets of one Rule can use different modes. The A0 → A1 → A0 example produces the stated visibility/legal choices and identical saved defaults/IDs. Invalid mode, duplicate source, stale/foreign references or a late persistence failure rejects the entire Rule save. Hide never changes shared flags or revives another restriction. Multiple matching Rules still intersect, including an empty resulting legal set.

### Phase D — mount and clarify saved Preview & Test

Mount the existing component, show saved/stale status and engine-derived explanation, and exercise Mapping modes and ordinary Territory/Application Attributes through it. Preserve the shipped lifecycle and context boundary.

Acceptance: dashboard and saved Preview produce the same selections/code/availability for the same Product and saved definition. Unsaved drafts do not affect Preview. Global context predicates remain saved but are skipped as complete Rules. Real Attributes named Territory/Application respond normally. Unauthorized or unassigned Products cannot be previewed. Preview refresh and diagnostics cause no writes.

### Meaningful regression coverage

Extend existing suites rather than mirroring visual configuration declarations:

- `tests/Feature/Filament/ConfiguratorWorkspaceTest.php`: rendered action visibility/placement where observable; toggles preserve drafts/search; real scoped moves; boundaries, foreign/deleted IDs, filtered full-order moves, content save after reorder; separate Option editor; batch-review roundtrip and gapped independent axes.
- `tests/Feature/Filament/ConfiguratorRulesTest.php`: mode through mounted Rule action/editor, A → B → A and Mapping → Advanced → Mapping draft retention, error field location and unchanged persistence after failed/canceled Save. Test mounted table actions with `Filament\Actions\Testing\TestAction::make('edit')->table($record)` or rendered state where closure visibility needs a mounted record.
- `tests/Unit/ConfiguratorCompilerTest.php`: both mode values, missing legacy value, invalid explicit values, normalized data; retain source-uniqueness/overlapping-target checks.
- `tests/Unit/Configurator/ConfiguratorEngineTest.php`: independent expected visibility/legal/code outcomes, transition back to earlier set, unmapped source, multiple restrictions, default fallback and lifecycle restrictions. Use asymmetric allowed target sets so tests can detect swapped or ignored modes.
- `tests/Feature/Catalog/ConfiguratorDefinitionTest.php`: save/load/duplicate roundtrip for mode with surviving IDs, reference remapping, no-op save and transactional failure; reorder never changes defaults or the unrelated axis.
- `tests/Feature/Catalog/ConfiguratorIntegrationTest.php`: dashboard/Preview agreement for both modes, forged hidden/disabled selections, no writes and retained public-context boundary.
- `tests/Feature/Catalog/CatalogStatusTest.php`: run existing affected lifecycle cases; add coverage only if an actual status interaction changes.
- `tests/Unit/WorkspaceSizing.test.js`: extend the existing Node test setup only for new splitter step/clamp/persistence/lifecycle logic. Do not introduce a browser package for this task.

Run the smallest affected suite after each behavior change using `php artisan test --compact <file>` or `--filter`; run the existing Node sizing test with its established command. If shared PHP files are dirty from another session, agree their edit/formatter boundary before `vendor/bin/pint --dirty --format agent`; format only this task's named PHP files until concurrent edits are coordinated. Build changed frontend assets with `npm run build`. At completion ask the operator to run the complete PHP suite with `php artisan test --compact`, per project convention.

Browser acceptance is required for completed UI work: authenticated desktop at both bounds and center, narrow/mobile and RTL where supported; use a disposable reviewed Configurator for write workflows. Verify visible search/tags/quick filters, plus and arrow tooltips, one-action flattening, picker selection without an extra toggle, separate saves, retained dirty drafts, splitter keyboard/pointer behavior, loading state, no horizontal page overflow, and Preview transitions. Read recent Boost browser logs. Report browser/network behavior separately from database/server checks; no speed claim based only on SQL tests or historical measurements.

## 10. Research basis and limits

Version-specific Boost documentation and installed Filament 5.9/Livewire 4.4 sources informed the plan. Native `Table::selectable()` accepts a closure; `ActionGroup` supports its own explicit icon/tooltip; native reorder mode is a distinct interaction that currently suppresses normal header actions/search in this application. The requested visibility toggle therefore needs independent presentation state.

Official references: [Filament table actions](https://filamentphp.com/docs/5.x/tables/actions), [grouped action triggers](https://filamentphp.com/docs/5.x/actions/grouping-actions), [custom fields](https://filamentphp.com/docs/5.x/forms/custom-fields), [Livewire 4 Alpine integration](https://livewire.laravel.com/docs/4.x/alpine). The native TableSelect API was verified in installed source; the attempted standalone TableSelect documentation URL was unavailable, so use the component source linked above rather than a third-party plugin API.

Filament Examples was searched again for checkbox/table pickers, move actions and resizable table/form layouts. The retrieved examples were Filament 4 complex-filter implementations; they support general filter-layout ideas but do not verify a Filament 5 splitter or these domain-specific save semantics. No verified ready-made splitter example was found in those returned results. Reuse the existing project components and current official/installed APIs; adding an external plugin is not part of this plan.
