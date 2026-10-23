# Master Values, Attributes, and Configurator creation implementation plan

> **For agentic workers:** After implementation is separately authorized, use the executing-plans skill to work through the checkboxes. This document authorizes no implementation, migration, commit, or deployment.

**Status:** Final implementation handoff, re-audited 2026-10-23 using the corrected Filament Examples guidance and [scoped Laravel Daily structure audit](MASTER_VALUES_ATTRIBUTES_CREATION_STRUCTURE_AUDIT.md). Ready for review and separate implementation authorization; application implementation has not started.

**Goal:** Create several Master Values or Attributes in one workspace, reuse those creation and selection mechanisms inside the Configurator, and retain the Configurator's specialized editing and default controls.

**Architecture:** Compose existing Filament schema factories and the embedded table selector. Add focused application operations around the existing canonical and Configurator writers; keep all writes out of schema callbacks. Share field definitions and staging mechanisms while allowing each workspace to arrange them for its own job.

**Tech stack:** PHP 8.4, Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7, Pest 5.3.0. Verified from installed packages; no new dependency is proposed.

**Spec:** The operator decisions captured in section 2, informed by the [interactive prototype](/Users/studioycm/.codex/visualizations/2026/10/19/01a154ae-2ef4-7163-a3f7-c484195c8f7e/catalog-create-workspace.html). The prototype demonstrates interactions; this plan specifies actual Filament persistence and compatibility.

## Global constraints

- Planning only until the user authorizes implementation. Approving a prototype or this plan is not authorization to deploy.
- Preserve existing table search, filters, column controls, ordering, selection, row actions, editor panes, and scoped saves.
- Single-record Master Value editors keep Tags and Specification visible. In the batch editor, the operator may hide/show those columns explicitly; never collapse them automatically, clear their data, or change visibility merely because the slide-over was resized. This incorporates the later operator refinement below.
- Use the existing manage-catalog ability and the existing application writers. New operations must enforce authorization independently of action visibility.
- IDs, codes, and local inclusion IDs have different meanings. Never interchange them or change a code's case.
- Groups and other resources remain outside this implementation's scope.
- Preserve concurrent changes. No broad refactor, model/table rename, new application base directory, provider, package, or universal CRUD framework.
- Preserve drafts in the current workspace and across validation failures. Durable record-draft recovery after a browser reload is not included: the current app provides a navigation guard, not durable draft storage. The prototype's reload recovery is not an application promise.

## Review focus

1. A user-edited identifier, including one deliberately cleared, must survive a name change and failed submission. Task 1 owns row-specific suggestion and reservation tests.
2. One pending Master Value reused in two Attributes must be inserted once, with two distinct Attribute Value codes. Task 2 owns identity and rollback tests.
3. Add Values and default changes must not overwrite another row's unsaved local edits or revert a newer saved default. Tasks 3 and 4 own these interactions.
4. Blank historical subtitles must become None; linked subtitles must follow a Master Value rename without changing the local UI name. Task 5 owns migration, roundtrip, and runtime tests.
5. Client-supplied parent IDs and stale selector results must not attach a Value to the wrong Attribute or Configurator. Tasks 3 and 4 own ownership, status, and forged-request tests.

## 1. Current implementation and evidence

Snapshot: checkout /Users/studioycm/Herd/configurator, branch master, HEAD 958af03ebcd66fcc5e8c0ecb4e1c420236089197. A concurrent documentation/architecture commit arrived during review; its diff contains no application changes. At the final planning snapshot, unrelated changes include docs/README.md, the image-assignment and Preview & Test plans, and the third architecture audit report. Recheck the working tree before implementation. The .ai/rules directory is absent at this snapshot. Installed versions were refreshed during the structure audit: PHP 8.4.27 and the package versions shown above, plus Blueprint 2.4.0 and FilaCheck Pro 1.2.7.

Re-audit checkout note: concurrent Product Fields implementation edits subsequently appeared, including changes in ConfiguratorDefinitionCompiler and BatchCatalogChanges. Their inspected diffs concern Product-property key validation/choices and do not change the subtitle/default contracts cited here. These are other work, not implementation of this plan; preserve them and refresh the final diff before later feature edits. This task changed skill guidance and planning documents only.

| Confirmed current behavior | Existing owner and implication |
| --- | --- |
| Both Master Values and Attributes already put the readable name first and suggest an identifier once on blur. Manual changes are preserved; regeneration is explicit. | [SuggestedIdentifier](/Users/studioycm/Herd/configurator/app/Filament/Resources/SuggestedIdentifier.php), [ValueForm](/Users/studioycm/Herd/configurator/app/Filament/Resources/Values/Schemas/ValueForm.php), [AttributeForm](/Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Schemas/AttributeForm.php). Extend these; do not introduce a second slug algorithm. |
| The two resource lists currently use the generic adjacent single-record create pane and a plus icon. | [SplitListRecords](/Users/studioycm/Herd/configurator/app/Filament/Resources/SplitListRecords.php), ListValues, ListAttributes. Change only these two list entry points. Existing edit panes and direct create routes remain usable. |
| Shared Attributes already select multiple Master Values in a table and stage per-association Codes. Saving the definition and pending Values is atomic. | [AttributeForm](/Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Schemas/AttributeForm.php), [AddAttributeValues](/Users/studioycm/Herd/configurator/app/Actions/AddAttributeValues.php). Currently the selector is inside another action modal; move selection into the surrounding workspace. |
| The selector already has an embedded implementation. | [WorkspaceTableSelect](/Users/studioycm/Herd/configurator/app/Filament/Forms/Components/WorkspaceTableSelect.php), [WorkspaceTablePicker](/Users/studioycm/Herd/configurator/app/Livewire/Catalog/WorkspaceTablePicker.php), ValueSelectionTable, AttributeSelectionTable, OptionSelectionTable. Retain their queries, pagination, filters, and selection behavior. |
| An Attribute Value is an Option row joining one Attribute and one Master Value. Its Code is exactly two ASCII letters/digits, case-sensitive, and globally unique. The Attribute/Master Value pair is unique. | [SaveCanonicalOption](/Users/studioycm/Herd/configurator/app/Actions/SaveCanonicalOption.php), existing MySQL constraints and AttributeValueBatchTest. A Code is not a generated semantic ID. |
| A Configurator includes shared definitions and stores local names, hints, flags, order, and default membership separately. Its editor already stashes drafts per selected Attribute/Value. | [AttributesRelationManager](/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/RelationManagers/AttributesRelationManager.php), [OptionsRelationManager](/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/RelationManagers/OptionsRelationManager.php). Preserve these state boundaries. |
| The Configurator default is currently a select field; its table has a read-only Default icon column. | The same relation managers and [ConfiguratorAttributeForm](/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Schemas/ConfiguratorAttributeForm.php). Make the column interactive in this workspace. |
| Local display value currently falls back to the Master Value's name, independently of the main local label. | [ConfiguratorDefinitionCompiler](/Users/studioycm/Herd/configurator/app/Services/ConfiguratorDefinitionCompiler.php). No shared subtitle field exists to inherit. A new explicit mode is required. |

Read-only database inspection found 34 local included Values, all with blank display_value_override; none had a local name override combined with a blank display value. The user explicitly chose: **blank means no subtitle for existing and new Values**. This decision does not depend on the small local sample.

Filament 5 APIs were checked with Boost search-docs and installed source. In 5.9, WorkspaceTableSelect extends Filament\Forms\Components\TableSelect; documentation also describes ModalTableSelect, so do not substitute the modal class for the existing embedded component. Installed InteractsWithActions validates/dehydrates the action schema and can save schema relationships before invoking the action callback. Therefore pending repeaters/selectors must not use relationship persistence or createOptionUsing callbacks.

Filament Examples' retained `v4/` paths are part of its shared Filament 4/5 collection and do not make those examples v4-only or unsuitable for this application. The earlier folder-based downgrade is superseded. [Filament's release announcement](https://filamentphp.com/insights/danharrin-filament-v5-blueprint) explains the shared framework behavior and the Livewire 4 compatibility boundary. The re-audit accepts the actual example patterns below, checks this application's custom Livewire behavior separately, and uses installed source for exact extension points. A separate v5 directory or manifest is not required to reuse ordinary Filament patterns; no claim is made that every example's complete dependency graph was inspected.

### Filament Examples re-audit: patterns adopted and boundaries retained

Access on 2026-10-23: source snippets returned by Filament Examples MCP, with public project descriptions/excerpts cross-checked where available. No project was installed or executed, and no full paid repository/lockfile inspection is claimed.

| Example and inspected source | Decision for this implementation |
| --- | --- |
| [Reusable Filament Wizard Steps for Invoice Creation](https://filamentexamples.com/project/reusable-filament-wizard-steps-for-invoice-creation): MCP `v4/forms/wizard-invoice-form/.../InvoiceForm.php` and `InvoiceWizardSteps.php`; shared field/repeater factories and an explicit relationship-binding argument. | Direct support for ValueForm/AttributeForm factories reused across resource pages and outer actions. Return fresh native components and let each workspace arrange them. Keep staged creation unbound to relationships because this app's writers must commit the complete graph atomically; that is an application persistence requirement, not a version objection. |
| [Filament Repeater: Five Advanced Use-Cases](https://filamentexamples.com/project/filament-repeater-five-advanced-use-cases): MCP `v4/forms/repeater-five-advanced-use-cases/.../Products/Schemas/ProductForm.php`; native repeaters, Get/Set, and on-blur updates. | Reuse native scalar repeater state and reactive utilities. Do not copy the example's clearing of fields when a product option changes into our column chooser: hiding Tags/Specification must preserve their values. Add 5/Add 10 use the installed native raw-state add lifecycle below. |
| [Quote Form with Custom Table Field and Product Picker Modal](https://filamentexamples.com/project/quote-form-with-custom-table-field-and-product-picker-modal): MCP `QuoteProductsField.php`, `ListQuoteProducts.php`, `ProductPickerTable.php`, and field Blade binding under `v4/forms/quote-form-with-custom-table-field-and-product-picker-modal/`. | The modelable embedded table and reusable table configuration are valid patterns for Filament 5. Retain our existing WorkspaceTableSelect/WorkspaceTablePicker instead of adding another custom field/two child components. Keep the requested inline picker, not the example's extra picker modal. Do not copy its delete-and-recreate relationship save into a Configurator whose local inclusion IDs and defaults must survive. |
| [LMS: Learning Management System](https://filamentexamples.com/project/filament-v4-lms-learning-management-system): MCP CourseForm, ManageCourseLessons, and CompleteButton under `v4/full-projects/lms/`. | Larger-project corroboration for resource-local schemas and reusable Actions. Ordinary relationship CRUD in that app does not replace this app's existing aggregate writer. No LMS deployment/dependency compatibility claim is required to use its ordinary UI organization. |
| [Complex Product Form: Tabs and v5.8 Defer Schema](https://filamentexamples.com/project/filament-complex-product-form-with-tabs-defer-schema): public description and ProductForm excerpt; full tutorial not inspected. | The shared 4/5 catalog demonstrably includes explicitly v5.8 behavior. Native deferLoading() exists in installed 5.9; it is an available tool, not a reason to redesign the always-visible entry grid. Keep the already specified active-picker-only mounting rather than adding another deferred-state mechanism. Any future deferral must preserve this plan's validation/draft contracts and be justified by measurement. |

The MCP search for TableSelect returned a generic public table example, which was inspected but does not establish picker-specific behavior. The invoice, repeater, quote, and LMS decisions above rely on their actual returned source. No example is rejected because its path starts with `v4/`; selection limits, ownership, atomicity and exact Livewire integration remain checks of the code actually used.

## 2. Agreed interaction contract

| Entry point | Workspace and result |
| --- | --- |
| Master Values → Create | One resizable slide-over with spreadsheet-style entry rows: UI Name, editable ID Name, optional Tags and Specification columns. Start with one row; add one, five, or ten more rows. A Columns control changes presentation without dropping data. One Create action saves the completed batch. |
| Shared Attributes → Create | One native slide-over with repeatable Attributes. Label first, editable Key second. Each Attribute can choose existing Master Values, create new Master Values inline, and enter a Code per association. A shared Attribute may be saved without Values. |
| Shared Attribute → edit | Retain the adjacent editor and its existing Values table. Reuse the same inline selection/creation workspace when staging additional Values; save the definition and additions together as today. |
| Configurator → Add Attributes | One native slide-over with Existing Attributes and Create Attributes sections/tabs. Both feed one staged review and one Save and add action. For each new local inclusion, choose at least one eligible Value and an explicit default in its Values table. New shared definitions are created and locally included in the same transaction. |
| Configurator → edit included Attribute | Retain the actual editable local pane and included Values table. Edit existing local Values there; the Add Attributes slide-over does not replace editing. |
| Included Values → Add Values | Extend the current inclusion action. Select existing shared Attribute Values, add associations using existing Master Values plus Codes, or create Master Values inline. Save and include commits the mixed batch. Keep the action available even when all existing shared Values are already included. Preserve the saved default. |
| Included Values → Default column | A named, keyboard-accessible icon action saves only the default change. Clicking the current default is a no-op. New-Attribute review uses the same visible column pattern but only stages the choice until the outer save. |

The two creation tabs in the Configurator are additive: switching tabs preserves work, and final submission includes selected existing Attributes and completed new Attribute entries. Deselecting an existing Attribute retains its review draft for reselection but does not submit it.

Reuse is at the field, selector, staging, validation, and writer boundaries. The Configurator keeps local settings, a Default column, and its own arrangement. Shared Attributes have no stored-default concept.

### Selection rows — one shared interaction contract

In every batch picker, clicking an eligible row's noninteractive area toggles its checkbox. This applies to existing Master Values, pending Masters in “New in this batch,” existing shared Attributes, and shared Attribute Values in Add Values. Names, identifiers, Codes, and row whitespace are selection targets. The existing checkbox remains the keyboard control and exposes the checked state; do not add another row tab stop.

Direct checkbox and wrapping-label clicks toggle once. Links, buttons, editable inputs, selects, textareas, and other interactive controls retain their own actions. Ignore nonprimary clicks, text-selection gestures, and disabled/loading rows; honor native maximum-selection limits. Search and pagination preserve the same selected IDs/refs. Ordinary resource listing rows, existing editor rows, default actions, and reorder handles do not acquire this behavior. Selection does not itself create, include, save, or authorize a record.

Use the picker-scoped adapter specified in section 4, rather than a global table override or a second selection store. Filament 5.9's native `selectable()` enables checkboxes; it does not alone implement this row-click contract.

### Master Value batch refinement — rapid entry and optional columns

The operator's later clarification replaces the always-visible requirement specifically for batch creation. Use the existing slide-over resize behavior from /Users/studioycm/Herd/configurator/resources/js/workspace-dialogs.js. A wider workspace presents UI Name, ID Name, Tags, and Specification as aligned columns.

- UI Name and ID Name remain visible. Columns offers Tags and Specification checkboxes, initially checked, plus Reset columns. Hide either optional column, enter names, then show it again to edit that field across all the same unsaved rows.
- Column changes preserve row refs, names, manually edited IDs, tags, specifications, errors, and focus context. Hiding means presentation only: hidden data is still submitted and validated. If an error belongs to a hidden column, reveal that column and focus the affected field.
- Provide Add row, Add 5 rows, and Add 10 rows. Append stable empty rows without changing existing entries or the chosen columns; respect the existing batch limit and focus the first newly added UI Name.
- Enter in UI Name commits/blurs that name, triggers its normal identifier suggestion, and focuses the next row's UI Name instead of submitting the form. At the last row, add and focus one new row within the limit. Shift+Enter moves to the previous UI Name. Tab/Shift+Tab retain normal field navigation so ID Name remains easy to edit. Do not intercept Enter inside Tags, Specification, suggestion popovers, or IME composition.
- Resizing never changes the chosen columns or their data. At narrow widths, present the selected fields as stacked labeled rows; do not hide fields or force tiny inputs. Remember only column preferences per user/browser and creation context using the existing browser-profile mechanism; do not persist record drafts there.
- Reuse this Master Value batch editor inside both Attribute creation flows, with the same optional-column and keyboard behavior. Existing single-record edit panes keep their present visible fields.

Filament 5.9 supplies table repeaters and the native field components. Its Repeater TableColumn is not the record table's toggleable column manager. The Columns control, responsive presentation, and keyboard focus behavior require a scoped UI extension; do not promise they work by applying toggleable() to a repeater column. Keep scalar staging and the existing writers unchanged. Do not rely on hidden() alone, which can omit field state from dehydration/validation.

### Value text and subtitle behavior

The local Value editor order is:

1. Local value UI name.
2. Local value subtitle, with link/unlink suffix action.
3. One live Value preview showing the effective main name and subtitle.
4. Local hint, followed by the existing local visibility/disabled settings.

The preview is adjacent to the two text fields and updates on blur or an explicit link change; it does not run the complete Rules engine on every keystroke. Explain that Rules can override the saved presentation and direct users to Preview & Test for that outcome.

| Subtitle state | Visible editor | Persisted intent | Runtime base text |
| --- | --- | --- | --- |
| None | Empty editable field | mode none, custom text null | Empty; no subtitle element |
| Custom | Editable text | mode custom, custom text | That text |
| Master name | Current Master Value UI Name, read-only, Linked indicator | mode master, custom text null | Resolve the current Master Value name each evaluation |

Typing text selects Custom; clearing it selects None. Linking remembers any unfinished custom text in transient UI state. Unlinking restores that custom draft if present, otherwise None. The link is a live reference, not a copy.

Main name resolution remains local UI name → Master Value UI Name. Render a subtitle only when nonblank and different from the effective main name, after presentation Rules have run. SetLabel, SetDisplayValue, and SetHint keep separate effects and their existing priority behavior. Use “subtitle” in Value-specific UI copy; keep existing machine keys and enum backing values.

## 3. Data contracts and responsibility boundaries

### Persisted changes

Only the subtitle feature requires a schema change:

- Add configurator_options.subtitle_mode: string length 16, non-null, default none; no new index or relationship.
- Add App\ConfiguratorSubtitleMode at /Users/studioycm/Herd/configurator/app/ConfiguratorSubtitleMode.php, alongside existing root enums: None = none, Custom = custom, Master = master. Use TitleCase case names; implement HasLabel for UI labels. Its pure resolve(custom, masterName) method returns a string, including an empty string for None.
- Keep display_value_override as nullable varchar(255), storing text only for Custom. Keep ConfiguratorOptionDTO.displayValue typed string and the runtime display_value key; no nullable DTO/wire rename is necessary.
- Add the mode to ConfiguratorOption fillable and its unsaved-model default. Retain the current raw-string model convention rather than introducing an enum cast into array consumers.
- Existing blank/null/whitespace-only display_value_override → None and null text. Existing nonblank text → Custom and retained text. Use the same blank-text check in migration, UI normalization, and writers: null or a string whose trim is empty is blank; the string "0" is not blank. Do not otherwise trim or rewrite existing custom text. **Do not backfill blank rows to Master.**
- Legacy incoming drafts with no mode infer Custom from nonblank custom text, otherwise None. Explicit invalid/null/empty mode is a validation error. Explicit Custom requires nonblank text ≤255; None/Master normalize stored custom text to null.
- Normalize into the compiler's returned definition data as well as the DTO, so reload and unchanged saves preserve intent.

The additive migration must perform its small deterministic backfill without application model events. Deploy migration and compatible code together. A code rollback restores the old blank fallback, and dropping the mode loses live-link intent; document this as a semantic rollback limitation and prefer a forward fix.

All existing canonical tables, constraints, model names, route slugs, IDs, and exact Code semantics remain intact.

### Staged batch shape

Use validated PHPDoc array shapes and stable UUID refs, matching existing new:UUID local-draft IDs. No new DTO layer or persisted draft table:

    master_values: list<{ref, label, semantic_id, tags, description}>
    attributes: list<{ref, label, key, values: list<{ref, value_id?|value_ref?, code}>}>
    existing_attribute_ids: list<int>                         // Configurator only
    reviews: list<{ref, attribute_id?|attribute_ref?, settings,
                   values: list<{ref, option_id?|option_ref?, local_fields}>,
                   default_ref}>

A Value association has exactly one persisted value_id or pending value_ref. The same pending Master Value ref may occur in several Attribute drafts; each association has its own row ref and Code. A review Value has exactly one existing shared option_id or newly staged association option_ref. Its own ref becomes the new local inclusion ID; default_ref must reference that local review row. Initially stage all active, nonhidden Values for a selected existing Attribute, as today, while retaining review edits on deselect/reselect. IDs shown in read-only names are display data, never authoritative input.

AddConfiguratorAttributes accepts the four root fields shown above. AddConfiguratorValues accepts existing_option_ids, new_values (the association-row shape), and master_values; the trusted owner and inclusionId are separate method arguments.

Keep one pending Master Value pool per outer workspace. A nested “Create and use” interaction validates/stages UI data only, adds references to that pool, and adds association rows to its Attribute. It does not insert records. Other Attributes can select those pending Values from “New in this batch.” Unfinished nested creation must be completed/staged or explicitly discarded before final submission; do not silently drop a nonempty composer.

Standalone Master Value creation saves all completed entries. Nested creation saves only referenced staged Master Values, once per ref. Unreferenced staged Values remain visible with an explanation that they will not be created. Removing a referenced pending Master requires removing its associations first; show the affected Attribute names.

Use Repeater item UUIDs plus an explicit ref; preserve them on validation failure, deletion, and reordering. Submit full ordered lists, including empty appended rows, to the application operation. Normalization retains each original submitted index/ref even when it skips a blank row or an unreferenced Master. Errors use those original positions, relative to master_values.<index> or attributes.<index>.values.<index>; strip an inner writer's definition prefix when wrapping its error. A blank first row followed by an invalid third row must show the error on that third row, never the second. Reuse the existing [ConfiguratorFormErrors](/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Schemas/ConfiguratorFormErrors.php) adapter to map the original list indices to repeater UUIDs and the mounted schema's actual state path; it already has non-Configurator callers. Never hardcode mountedActions.0, create a second error-mapping framework, filter/reindex the pool in UI callbacks, or rebuild the batch after an error.

### Application operations

All paths below are proposed additions inside existing directories. Concrete constructor injection suffices; no container binding or interface is needed.

| Operation / full path | Contract and reuse |
| --- | --- |
| App\Actions\CreateMasterValues — /Users/studioycm/Herd/configurator/app/Actions/CreateMasterValues.php | handle(User actor, array rows, ?array referencedRefs = null): array returns ref → persisted Value. Null means standalone: create every populated row. A ref list means nested: create only those referenced rows from the complete pool, once per ref, preserving original pool indices in errors. Authorize, validate shape/limits/refs, normalize tags, then call SaveCanonicalDefinition for each new Value in one transaction. Preserve field validation and unique-race messages; never generate a missing/cleared submitted ID during save. |
| App\Actions\CreateSharedAttributes — /Users/studioycm/Herd/configurator/app/Actions/CreateSharedAttributes.php | handle(User actor, array attributes, array masterValues = []): array returns ref → persisted Attribute. Validate cross-row refs/Codes, create referenced Master Values once, then call SaveCanonicalDefinition and AddAttributeValues for each new Attribute. No default/local settings. |
| App\Actions\AddConfiguratorAttributes — /Users/studioycm/Herd/configurator/app/Actions/AddConfiguratorAttributes.php | handle(User actor, Configurator owner, array payload): Configurator. Under the owner writer's lock, create any shared drafts, resolve IDs, validate selected existing records again, require explicit per-inclusion defaults, and append to a fresh full definition through SaveConfiguratorDefinition::change. |
| App\Actions\AddConfiguratorValues — /Users/studioycm/Herd/configurator/app/Actions/AddConfiguratorValues.php | handle(User actor, Configurator owner, int inclusionId, array payload): Configurator. Scope inclusion to owner; resolve/create new Master Values, call AddAttributeValues for new shared associations, then include selected/new shared Values through a fresh locked aggregate patch. Never replace the current default. |

Retain existing writer responsibilities:

- SaveCanonicalDefinition owns canonical field validation and writes. Value::suggestSemanticId retains its model-level algorithm.
- AddAttributeValues::handle retains resolved persisted value_id/code input and its void return. Query the created Options once by the resolved value IDs when a caller needs their IDs.
- Extend AddAttributeValues::saveDefinition with an optional fourth masterValues array for the shared Attribute editor. Resolve its referenced Master Values through CreateMasterValues inside the same existing transaction before passing resolved rows to handle. Existing three-argument callers remain compatible.
- SaveConfiguratorDefinition remains the sole aggregate writer. SaveConfiguratorOption remains the narrow local-Value patch writer.
- BatchCatalogChanges continues to handle its current batch edits/removals; do not turn it into a generic bulk-create engine.

The new operations accept only the documented domain keys, using strict nested array allowlists. UI helpers such as _suggested_*, active picker, column choices, resolved labels, and subtitle proxies never enter writers. Use PHPDoc list/array shapes and deliberate integer normalization after validation. Error fields not tied to one entry remain at the relevant collection/form root.

`CreateMasterValues` trims each string tag before canonical validation, preserving the current UI's behavior. Validate the resulting nonblank/distinct strings; do not silently deduplicate, change case, or normalize identifiers/Codes as a side effect. All nested creation calls this operation. Broader tag-import normalization remains separate work.

Constructor dependency direction is fixed: CreateMasterValues → SaveCanonicalDefinition; CreateSharedAttributes → CreateMasterValues + SaveCanonicalDefinition + AddAttributeValues; AddConfiguratorAttributes → SaveConfiguratorDefinition + CreateSharedAttributes + existing inclusion-draft builder; AddConfiguratorValues → SaveConfiguratorDefinition + CreateMasterValues + AddAttributeValues. AddAttributeValues::saveDefinition may resolve/inject CreateMasterValues for the optional pool; it never calls CreateSharedAttributes. UI factories call these operations through action callbacks; operations never call UI factories. Concrete classes resolve transiently without provider registration or interfaces. Outputs are models/ref maps, not notifications or Filament components.

### Atomicity, lock order, and failure

1. Authorize before selector data access, draft mutation, or database work. Retain the selectors' existing Gate checks; new action schemas must check access before loading choices/tag vocabulary. On submitted batch actions, use beforeFormValidated with Gate::authorize('manage-catalog') as well as action authorization and operation-level authorization, so permission revocation cannot leave database-backed validation ahead of the explicit check. Validate shape, limits, UUID uniqueness, referenced draft membership, and explicit default intent.
2. Each top-level operation owns one DB transaction. Nested existing writer transactions stay on the same connection; no partial saves or notifications escape.
3. For Configurator operations, modify SaveConfiguratorDefinition::change to acquire CatalogContextSettings::current(lock: true) immediately after locking the Configurator and before invoking the patch callback. This gives nested shared writes the same Configurator → global context → canonical Attribute/Option lock prefix used by save. Reuse ascending-ID canonical locking; do not add independent mutexes.
4. The fresh locked definition is the patch base. Append new Attributes at the existing independent display/code-order ends; append new Values at the local display-order end. Keep unrelated settings, rules, mappings, inclusions, defaults, and their IDs.
5. Existing shared definition edits already lock consuming Configurators before canonical rows. The new composition must not call an existing-definition rename/edit from inside its creation callback. It creates definitions/associations and includes them.
6. New inclusion boundaries require active Attributes and active, nonhidden shared Values. Recheck on submit; picker filtering is not enforcement. Existing inactive references continue under the existing repair rules.
7. Keep database unique constraints authoritative. Concurrent identifier/Code conflicts abort the whole operation and return a field error; do not silently rename, reuse another record, or partially succeed.
8. Notify and refresh tables only after the top-level transaction returns. Preserve Eloquent writes and current navigation-count invalidation. Runtime definitions already load canonical labels fresh; do not invent an observer, cached definition, or revision system for the live subtitle. If a caller already uses CatalogRevisions::batch, keep it inside its transaction.

The lock-order concern is a supported composition risk identified in source, not a reproduced deadlock. MySQL acceptance must cover uniqueness/rollback and concurrent inclusion/edit attempts; SQLite results alone cannot establish these guarantees.

## 4. Filament component specification

These are modifications to existing resources; no new resource scaffolding. Each outer schema uses columns(1); inner name/identifier grids use columns(['default' => 1, 'md' => 2]) and columnSpanFull(). No quarter-width nested grids.

Reactive imports are Filament\Schemas\Components\Utilities\Get and Filament\Schemas\Components\Utilities\Set. Use live(onBlur: true) for text-derived suggestions/previews, live() only for selector/mode/default changes. Local-only focus/disclosure behavior may use the app's existing Alpine integration.

### Reusable schema owners

| Full path | Change / responsibility |
| --- | --- |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Values/Schemas/ValueForm.php | Extract public fields(creationContext, reservedIdentifiers, tagOptions) and batch(name, creationContext, reservedIdentifiers, tagOptions) factories. configure delegates to fields. Factories return fresh components; existing create/edit routes keep current behavior. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Schemas/AttributeForm.php | Extract definitionFields(creationContext, reservedKeys); compose a shared inline AttributeValueWorkspace in valuesSection. Keep canonical name/Key and staged additions in the same existing save. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/AttributeValueWorkspace.php — new | Static UI factory for paginated persisted Master selection, pending-Master selection, inline Master creation, and the association Code table. Takes explicit state access and stable owner ref; changes only staging state. Expose associationTable(extraColumns = [], extraFields = []) so the Configurator supplies its default cell without a context-switching universal layout. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Schemas/AttributeBatchForm.php — new | Repeatable Attribute definitions, one root Master pool, and per-Attribute staged associations. Uses existing ValueForm/AttributeForm factories; supports an explicit schema extension for Configurator review. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Schemas/ConfiguratorAttributeCreationForm.php — new | Existing-Attribute selection, shared creation composition, local inclusion settings, and staged default column. Does not duplicate shared field definitions. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Schemas/ConfiguratorValueTextFields.php — new | Local UI name/subtitle/link controls and preview, reused by the selected Value editor and ConfiguratorAttributeForm's nested local-Value form. |

Use existing schema factories as legitimate UI owners. The application operations must not import these classes, query a resource for validation rules, or depend on a mounted Livewire component.

### Scoped frontend extensions and exact file map

Keep native Filament fields, Actions, table selection, and repeater item lifecycle. These additions handle presentation and input targeting only; they do not duplicate record data in a JavaScript store.

| Full path | Exact responsibility |
| --- | --- |
| /Users/studioycm/Herd/configurator/resources/js/master-value-batch.js — new | Export masterValueBatch(config), an Alpine presentation/focus component. Own only optional-column booleans, preference subscription, and an ephemeral next-focus target. Use browserProfile from admin-profile.js with user + catalog-create:<context>:master-columns; allowlist tags/specification booleans. No labels, identifiers, record drafts, or selection IDs in browser preferences. |
| /Users/studioycm/Herd/configurator/resources/views/filament/resources/master-value-batch-columns.blade.php — new | The Columns dropdown using native Filament dropdown/button/checkbox Blade components, with Tags, Specification, and Reset columns. It shares the surrounding Alpine presentation component. No separate Livewire child or query. |
| /Users/studioycm/Herd/configurator/resources/js/workspace-table-selection.js — new | Export workspaceTableSelection(), with one delegated toggle(event) method. Locate only a native record row (.fi-ta-row or .fi-ta-record) inside the owning selector wrapper, then invoke its enabled .fi-ta-record-checkbox via click(). Skip nested interactive targets/labels, disabled controls, text selection, nonprimary/repeated click events, and nested unrelated tables. Let native selection state and fi-selected styling update; do not mutate IDs or checkbox.checked directly. |
| /Users/studioycm/Herd/configurator/app/Filament/Forms/Components/WorkspaceTableSelect.php — modify | Add a chainable selectRowsByClick(bool condition = true): static UI option, default false. Opt in the scoped creation/addition pickers with ->selectRowsByClick(). Merge data-workspace-row-selection, x-data workspaceTableSelection(), and x-on:click toggle($event) only into the existing wrapper when enabled; retain existing bindings/attributes and installed mount properties. The handler also checks the native checkbox's effective disabled state. |
| /Users/studioycm/Herd/configurator/resources/js/app.js — modify | Import/register both Alpine components through the existing alpine:init and already-initialized branches. Preserve other registrations. |
| /Users/studioycm/Herd/configurator/resources/css/filament/admin/theme.css — modify | Only scoped Master batch geometry, column visibility, and container-width stacking. Reuse native field/action colors and states. No global table/repeater rules or change to ordinary single-record editors. |
| /Users/studioycm/Herd/configurator/tests/Unit/MasterValueBatch.test.js — new | Test preference allowlisting, keyboard event exclusions, and focus-target decisions. Use the existing Node test conventions. |
| /Users/studioycm/Herd/configurator/tests/Unit/WorkspaceTableSelection.test.js — new | Test event targeting and exclusions, exactly one invocation of the native checkbox, and no second selection state. Browser acceptance separately proves native Alpine/Livewire integration. |

`ValueForm::batch()` returns one full-span Filament\Schemas\Components\Group containing the Columns View and a native Filament\Forms\Components\Repeater. Attach Add 5/Add 10 as native Filament\Actions\Action objects through that repeater's hintActions(), explicitly bound with schemaComponent($repeater); their callback receives the owning repeater as its named `component` parameter. Do not rely on a sibling Actions component to inject an unrelated Repeater. Use table() with four TableColumns in the fixed order UI Name, ID Name, Tags, Specification; compact(), defaultItems(1), maxItems(500), reorderable(false), cloneable(false), and the normal Add row/delete actions. Keep helper Hidden fields before visible fields, so the installed renderer does not omit trailing helpers after its four-column counter is exhausted. The Column controls do not use hidden()/visible()/disabled() on those four fields.

Mark the Group data-master-value-batch and the field wrappers data-batch-field=label|semantic_id|tags|description; bind data-show-tags and data-show-specification from Alpine. Scoped CSS hides header/cells 3 or 4 together when deselected; all fields remain mounted. Because reordering is disabled and there are exactly four visible components, these cell positions are stable. In a narrow container, stack selected cells with their native labels; keep headers accessible and prevent overflow. Add a rendered-markup/browser regression case for this small dependency on the installed native repeater structure. Do not publish or copy its vendor template.

Add row/Add 5/Add 10 stage native repeater state only. Use one private appendRows(Repeater component, int count): void helper in ValueForm for counts 1, 5, and 10, called from the configured addAction() and the two bound hint Actions. Authorize before changing state; reject another count or a request exceeding 500 total rows, and disable Add 5/Add 10 when fewer than that number remain. Do not silently append a partial quantity. Match installed Repeater::getAddAction(): obtain getRawState(), append keys from generateUuid(), set rawState($items), fill each newly created getChildSchema($uuid), set its explicit ref, then callAfterStateUpdated() once and preserve the native partial-render behavior. Never pass through dehydrated/reindexed lists to append rows, and do not refill existing child schemas. This is a narrow UI helper, not an application writer or a new state class. Focus the first appended name after the corresponding morph. At the last UI Name, Enter invokes that same configured native Add row action; it does not append a client-only record. Wait for the normal blur/suggestion request before moving on, without synthesizing duplicate blur updates. Use absolute field paths plus stable refs for focus targets and ignore IME, Tags, textarea, suggestion-popup, or modified shortcut events as specified in section 2.

Subscribe only to the owning Livewire component's successful message/morph boundary using the existing localTableProfile pattern. When native data-validation-error markup occurs in a CSS-hidden optional field, reveal that column, then focus the first affected native input after layout updates. Do the same after mapped operation errors. Preserve chosen visibility through ordinary morphs and resizing, and unregister subscriptions on Alpine destroy. A validation-driven reveal becomes the current explicit visible state, so the field remains available for correction.

Every consumed picker opts into selectRowsByClick(): the Master chooser in AttributeValueWorkspace, the existing-Attribute chooser, and the existing shared-Value chooser. “New in this batch” remains a native searchable CheckboxList with a wrapping label that covers its row. Do not add another paginated database table for transient refs. Whole-row selection uses existing selection bindings/limits and performs no request solely to duplicate checkbox state.

### Focused Livewire 4 compatibility contract

- Keep WorkspaceTablePicker's inherited single `#[Modelable] state` and the parent's existing applyStateBindingModifiers('wire:model') mount binding. Livewire 4 supports that child-property binding; do not add .deep or a second public selection array to solve a folder-version concern. .deep addresses delegated descendant DOM input events, a different situation. Leave native Filament input binding modifiers in charge of on-blur name/ID updates.
- Reuse the app's component-ID-filtered Livewire.interceptMessage → onSuccess → onMorphed pattern for preference restoration, focus and error-column reveal. This API and the interceptor cleanup are present in the installed Livewire 4.4.7 bundle. Do not introduce a copied Livewire 3 message.processed hook, or register duplicate listeners after each morph.
- Keep data/ref-derived component keys stable across selection, search, row append and column changes. If a picker unmounts, its sole source of selected IDs remains the outer scalar draft. On remount, native Modelable state is seeded from that draft; selection never comes from untrusted event-provided display labels.
- Acceptance must exercise multiple consecutive blur/append requests, picker parent-child roundtrips, page/search changes, switching Attributes and returning, and cancellation/reopening. Source inspection confirms the API choices; only actual app component/browser tests can confirm the implemented behavior. No wholesale Livewire upgrade or package change is part of this feature.

### Fields

| Field | Component | Docs | Validation | Config |
| --- | --- | --- | --- | --- |
| Master UI Name | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required for a populated row; string, max:255 | label UI Name; first field; live(onBlur: true); existing SuggestedIdentifier helper |
| Master ID Name | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required for populated row; max:100; regex \A[a-z0-9]+(?:_[a-z0-9]+)*\z; unique values.semantic_id | editable; existing explicit regenerate suffix; never refill a manually cleared ID on submit |
| Tags | Filament\Forms\Components\TagsInput | [Tags](https://filamentphp.com/docs/5.x/forms/tags-input) | array/list, max:30; nonblank distinct strings max:80, existing trimming behavior | default([]), suggestions from one request-local vocabulary read; full-span in the ordinary form, optional column in the batch editor; hiding never excludes validation/submission |
| Specification | Filament\Forms\Components\Textarea | [Textarea](https://filamentphp.com/docs/5.x/forms/textarea) | nullable string, max:5000 | rows(3), full-span in the ordinary form, optional column in the batch editor; hiding never excludes validation/submission |
| Attribute Label | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required for populated row, string max:255 | first field; live(onBlur: true); SuggestedIdentifier helper |
| Attribute Key | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required for populated row, string max:100, unique attributes.key | editable suggested lowercase/underscore Key. Retain current manual-Key validation, including legacy hyphenated keys; no new blanket regex |
| Master/Attribute entries | Filament\Forms\Components\Repeater | [Repeater](https://filamentphp.com/docs/5.x/forms/repeater) | list; 1–500 completed root entries; validate every populated row | defaultItems(1), addActionLabel with resource name; stable refs; no relationship(); Master entries never collapsed |
| Persisted selection | App\Filament\Forms\Components\WorkspaceTableSelect | [Table selection](https://filamentphp.com/docs/5.x/forms/select#selecting-options-from-a-table-in-a-modal) and existing embedded source | array of distinct integer IDs; authoritative scope/status checks at save | multiple(), live(), tableConfiguration(existing selection class), tableArguments(owner/exclusions). No relationship(). Preserve 10/25/50 pagination |
| New in this batch | Filament\Forms\Components\CheckboxList | [Checkbox list](https://filamentphp.com/docs/5.x/forms/checkbox-list) | distinct refs belonging to the root Master pool | options only from staged Values, searchable(), live(); separate from persisted-ID picker |
| Association rows | Filament\Forms\Components\Repeater and Filament\Forms\Components\Repeater\TableColumn | [Table repeater](https://filamentphp.com/docs/5.x/forms/repeater#table-repeaters) | ≤500 Values per Attribute; distinct Master per parent, distinct global Codes across batch | table(UI Name, Code, plus Configurator-supplied Default), addable(false), reorderable(false), compact(); no relationship() |
| Association UI Name | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | display-only | readOnly(), dehydrated(false); current Master name and ID Name; badge for staged/new |
| Code | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required, exactly 2 ASCII alphanumeric chars, case-sensitive globally unique | length(2), trim(false), regex('/\A[A-Za-z0-9]{2}\z/D'); never generate, uppercase, or trim |
| Stable ref / persisted ID / default_ref | Filament\Forms\Components\Hidden | [Hidden](https://filamentphp.com/docs/5.x/forms/hidden) | ref syntax/membership and integer ownership; default_ref required for each selected new inclusion | serialize intent only; hidden is not a trust boundary |
| Local value UI name | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | nullable string, max:255 | existing label_override, live(onBlur: true); blank inherits main name |
| Local value subtitle | Filament\Forms\Components\TextInput plus Hidden mode/override state | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | mode enum; Custom requires nonblank max:255 | UI proxy _subtitle_text is dehydrated(false), live(onBlur: true), readOnly in Master mode; derive stored mode/text explicitly rather than dehydrating the visible linked name |
| Value preview | Filament\Schemas\Components\View | [Custom components](https://filamentphp.com/docs/5.x/schemas/custom-components) | no submitted value | escaped Blade view directly below name/subtitle, before hint; resolves current mode/name without writes |
| Local hint / flags | Existing Filament\Forms\Components\Textarea and Toggle fields | [Textarea](https://filamentphp.com/docs/5.x/forms/textarea), [Toggle](https://filamentphp.com/docs/5.x/forms/toggle) | retain existing local field constraints | preserve existing behavior; move hint after preview |

Completely empty extra rows are ignored, including generated refs/helper flags. Nonempty incomplete rows block the whole submission. Apply required rules conditionally to populated rows in the schema, then repeat the same normalization at the application boundary; unconditional required fields must not reject a blank extra row before normalization. An all-empty creation batch fails with a clear form-level message. Keep existing aggregate size limits and reject oversized batches; 10 records is the primary acceptance workload, not a hard limit.

Identifier suggestions retain today's **fill once**, rather than continuously following name changes. Extend the helper with an explicit creation-context callback so nested new rows work inside an edit page or generic Action. Pass other pending IDs/Keys to suggestion collision checks, excluding the current ref; persisted and pending collisions generate _2, _3 while respecting max:100. Existing collision fallback remains value for Master Values and attribute for Attributes. Manual duplicates are errors, not silently repaired. Scope suggestion flash/events by absolute field state path and Livewire ID so only the intended repeater row flashes.

### Columns and actions

Existing table/filter definitions stay in place; no new filter is required.

| Column | Component | Docs | Config |
| --- | --- | --- | --- |
| Saved Default | Filament\Tables\Columns\IconColumn | [Column actions](https://filamentphp.com/docs/5.x/tables/columns/overview#triggering-actions), [Icon](https://filamentphp.com/docs/5.x/tables/columns/icon) | existing stored_default, boolean(), state from fresh owner default ID, action(Action::make('setDefault')), tooltip/accessible label identifying Value and state |
| Staged Default | Filament\Forms\Components\Repeater\TableColumn with Filament\Schemas\Components\Actions and Filament\Actions\Action | [Table repeater](https://filamentphp.com/docs/5.x/forms/repeater#table-repeaters), [Actions](https://filamentphp.com/docs/5.x/actions/overview) | Configurator-only extra cell; iconButton(), label “Set [Value] as default”; Get/Set changes default_ref only; visible selected state; clear default if that staged Value is removed and require reselection |

All actions below use Filament\Actions\Action. Docs: [Actions](https://filamentphp.com/docs/5.x/actions/overview), [slide-overs and modal controls](https://filamentphp.com/docs/5.x/actions/modals). They use authorize('manage-catalog'); write callbacks also call the authorized application operation.

| Action / proposed factory | Location / visibility | Behavior |
| --- | --- | --- |
| createMany / /Users/studioycm/Herd/configurator/app/Filament/Resources/Values/Actions/CreateValuesAction.php | Master Values table header, authorized resource creators | 1. Open one-entry batch. 2. Stage add/remove/edit. 3. Call CreateMasterValues. 4. Refresh table/counts and show created count; retain the currently selected edit pane and its draft. |
| createMany / /Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Actions/CreateAttributesAction.php | Shared Attributes table header, authorized resource creators | 1. Open repeatable Attributes. 2. Stage inline Value selection/creation and Codes. 3. Call CreateSharedAttributes. 4. Refresh and report counts without switching the selected editor. |
| includeMany, label Add Attributes / /Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Actions/AddAttributesAction.php | Configurator Attributes header, authorized manager | 1. Select/create shared Attributes. 2. Review local inclusion settings and explicit table defaults. 3. Call AddConfiguratorAttributes. 4. Refresh saved lists while retaining other editor drafts. Keep action name includeMany for existing integration compatibility. |
| includeMany, label Add Values / /Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Actions/AddValuesAction.php | Included Values header, authorized manager even when no existing Value remains available | 1. Pick eligible shared Values and/or stage new associations/Masters inline. 2. Explain new associations are shared. 3. Call AddConfiguratorValues. 4. Refresh membership and preserve the existing default and local drafts. |
| Inline choose/create/use/remove | AttributeValueWorkspace, authorized manager | 1. Open the selector/composer inside the outer workspace. 2. Stage refs and Codes only. 3. Close/disclose that inline region without opening another picker modal. Confirm removal when it would discard nonempty entry data. |
| regenerateIdentifier | Identifier suffix, explicit creation context only | 1. Authorize. 2. Require nonblank name. 3. Recompute against persisted plus pending reservations for this row. No write. Existing-record edit retains no regenerate suffix. |
| setDefault | Saved Default cell, authorized manager | 1. Freshly scope clicked local Value to this owner. 2. Patch only default_configurator_option_id through the aggregate writer. 3. Refresh default indicators and persisted baseline metadata, preserving all unrelated drafts. No-op for current default. |
| linkSubtitle / unlinkSubtitle | Subtitle suffix, authorized manager | 1. Resolve the selected Value's actual Master. 2. Switch transient mode and visible proxy as specified above. 3. Save only through the local Value save operation. No canonical rename or immediate write. |
| discardDraft | Outer slide-over footer, visible while action is open | 1. If populated draft differs from its initial state, open native confirmation. 2. Keep editing returns to identical state. 3. Discard closes/cancels parent action and clears only its staged batch. Empty draft closes directly. |

Outer creation actions: slideOver(), stickyModalFooter(), databaseTransaction(false) because the application operation owns the transaction. Use the existing admin width preferences. Keep a labeled Create/Add button instead of an icon-only plus.

For explicit cancellation, set modalCancelAction(false), modalCloseButton(false), closeModalByClickingAway(false), closeModalByEscaping(false); supply discardDraft through extraModalFooterActions(), requiresConfirmation(condition), and cancelParentActions(parentName). This avoids unguarded close paths while preserving native confirmation/focus behavior. Confirmation labels are Discard and Keep editing. Navigation/unload protection must cover the mounted action too; do not assume unsavedChangesAlert alone confirms modal cancellation.

Mount only the currently opened inline picker and Master composer; preserve inactive drafts in outer scalar state. Do not render ten paginated Livewire selector children for ten collapsed/idle Attributes. Use stable owner/ref component keys; pending refs never enter an Eloquent table query.

## 5. Implementation tasks and acceptance gates

The sequence follows actual reuse: Master Values → shared Attributes → Configurator creation/addition → Configurator defaults and local presentation. Each task is reviewable independently. After the user authorizes implementation of the full plan, proceed through these dependent tasks without repeated phase approvals unless scope changes.

### Task 1 — Master Value batch creation and reusable identifiers

**Create:** CreateMasterValues and Values/Actions/CreateValuesAction.php listed above.

**Modify:** [ValueForm](/Users/studioycm/Herd/configurator/app/Filament/Resources/Values/Schemas/ValueForm.php), [SuggestedIdentifier](/Users/studioycm/Herd/configurator/app/Filament/Resources/SuggestedIdentifier.php), [Value](/Users/studioycm/Herd/configurator/app/Models/Value.php), [ListValues](/Users/studioycm/Herd/configurator/app/Filament/Resources/Values/Pages/ListValues.php), lang/en/catalog.php, lang/en/admin-ux.php, matching existing locale overrides where applicable.

- [ ] Add behavioral coverage in /Users/studioycm/Herd/configurator/tests/Feature/MasterValueCreationBatchTest.php; extend SuggestedIdentifiersTest and only the Master Value cases in SplitListCreationTest.
- [ ] Extract/reuse fields, support pending collision reservations and explicit creation context, and implement the transaction/error adapter. Include the batch refinement above: aligned columns, explicit optional-field visibility, Add 5/Add 10, and UI-Name-to-UI-Name entry.
- [ ] Set ListValues.inlineCreationEnabled false and prepend the labeled action by overriding makeTable() using the parent-configured table. Do not alter generic SplitListRecords or the other resources' creation behavior. Retain direct CreateValue.
- [ ] Prove 10 entered Values save with exact labels/IDs/tags/specification. Add manual-ID, manual-clear, duplicate row, persisted collision, 100-character suffix, empty-row, and later-row-error cases. Assert zero partial rows and no success notification on failure.
- [ ] Through mounted action interactions, prove regeneration affects just one row and action failure retains its entire state. An open existing editor remains selected and unchanged after successful batch creation.
- [ ] Implement the scoped frontend files specified in section 4. Add focused interaction coverage for hiding/reopening Tags after ten entered names, retaining existing tags/specifications while hidden and on submit, revealing a hidden invalid field, stable refs after Add 5/Add 10, and unchanged columns after resize. Browser-check Enter progression, manual ID preservation, IME/Tags/textarea Enter behavior, and keyboard access to the Columns chooser. No global vendor-view override or separate form-state engine.
- [ ] Add the picker-scoped row-click adapter/option and its Node coverage before its first reuse in Task 2. Keep ordinary table behavior unchanged; verify direct checkbox and keyboard controls remain native.
- [ ] Test a blank first/middle row followed by an invalid populated row and verify the error's original UUID path. Direct-operation tags with surrounding spaces normalize like the UI; duplicates after trimming remain validation errors.
- [ ] Verify the two hint Actions receive their actual owning Repeater, Add 5/Add 10 initialize only new children, and 495 rows + Add 5 succeeds while 496 rows + Add 5 preserves every row and returns a limit error for a forged call. UI-Name Enter at the final row uses that same guarded append lifecycle.

**Gate:** focused feature tests pass; native action keyboard/validation flow is reviewable. The existing single-record writer/route tests remain green.

### Task 2 — Shared Attribute batches and inline Value creation

**Create:** CreateSharedAttributes, AttributeValueWorkspace, AttributeBatchForm, and CreateAttributesAction listed above.

**Modify:** [AttributeForm](/Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Schemas/AttributeForm.php), [AddAttributeValues](/Users/studioycm/Herd/configurator/app/Actions/AddAttributeValues.php), [ListAttributes](/Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Pages/ListAttributes.php), [EditAttribute](/Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Pages/EditAttribute.php), [CreateAttribute](/Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Pages/CreateAttribute.php), ValueSelectionTable only for required exclusion arguments, and lang/en/attribute-options.php.

- [ ] Add /Users/studioycm/Herd/configurator/tests/Feature/SharedAttributeCreationBatchTest.php and extend AttributeValueBatchTest. Extend the Attribute cases in SplitListCreationTest without deleting other resource tests.
- [ ] Compose Label/Key and inline Value workspace; use the root pending Master pool and preserve per-Attribute selections/Codes on switching.
- [ ] Resolve the pool once before saving multiple Attributes. Extend saveDefinition's optional pool input for existing/direct Attribute editors; remove transport-only state before canonical validation, and clear pending UI state only after success.
- [ ] Replace the Attributes list plus/automatic create pane only, retaining its edit pane and direct route. Keep the existing shared Values table and table controls.
- [ ] Test an Attribute with no Values; 10 Attributes; existing Master selection; one pending Master reused in two Attributes with distinct Codes; exact Aa versus aA; forbidden whitespace/punctuation/non-ASCII/1- or 3-character Codes; global duplicate Codes; duplicate pair; duplicate Key; and cross-Attribute late failure.
- [ ] Assert exact identities and labels after reload, not just counts. A duplicate Code on the final Attribute rolls back every preceding Attribute, new Master, and association while preserving UI entries. Existing shared editor definition-plus-additions remains atomic.
- [ ] Opt persisted and pending Master choosers into the full-row contract. Test the persisted selector across search, page changes, and remounting; test one click/one toggle and disabled/loading limits in Chrome. Supply the complete pending pool to CreateMasterValues with the referenced-ref set, retaining original index mapping for a nonfirst failing row.

**Gate:** the same Master editor is demonstrably reused, with no nested selection modal and no default controls in shared Attribute creation.

### Task 3 — Configurator Add Attributes and Add Values

**Create:** AddConfiguratorAttributes, AddConfiguratorValues, ConfiguratorAttributeCreationForm, AddAttributesAction, and AddValuesAction listed above.

**Modify:** [SaveConfiguratorDefinition](/Users/studioycm/Herd/configurator/app/Actions/SaveConfiguratorDefinition.php), both Configurator relation managers, [ConfiguratorAttributeForm](/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Schemas/ConfiguratorAttributeForm.php), [ConfiguratorInclusionDrafts](/Users/studioycm/Herd/configurator/app/Services/ConfiguratorInclusionDrafts.php), the three existing selection-table classes only where needed, lang/en/configurator.php.

- [ ] Add /Users/studioycm/Herd/configurator/tests/Feature/ConfiguratorCreationFlowTest.php; extend ConfiguratorWorkspaceTest and relevant Catalog/MySql/ConfiguratorIntegrityTest cases.
- [ ] Establish the writer lock prefix before any new shared writes. Add fresh-owner additive operations and map pending refs to real IDs; no UI-only fragment may be passed as a replacement full definition.
- [ ] Compose existing selection and new creation in Add Attributes. Retain deselected review stashes; require an explicit default from the chosen Values for every inclusion, including one-Value Attributes. Keep the compiler's existing legacy auto-first behavior for unrelated callers, enforcing the new action boundary explicitly.
- [ ] Extend Add Values with existing shared selection and new associations/Masters; make its empty-results state useful. Exclude already included/attached records appropriately while keeping new creation available.
- [ ] Test selected existing plus newly created Attributes in one save; multiple new Attributes sharing one pending Master; selected existing plus newly created Values; a missing default; a default ref from another Attribute; a forged inclusion/owner; inactive/hidden records changed after selection; duplicate inclusion; final Code conflict; and unauthorized direct operation/action submission.
- [ ] Assert rollback of all canonical/local rows on late failure. Assert current defaults, rules, mappings, order, and unrelated local drafts are preserved on success and failure. Reopen the action and include a later shared Value without resetting the current default.
- [ ] For all newly mounted actions, test Discard versus Keep editing and tab/Attribute switching. On validation failure, retain native picker selection, pending Master refs, Codes, defaults, and nested composer data.
- [ ] Run the focused Livewire 4 contract above through component/browser interactions. Verify the embedded Modelable selection survives parent morphs and picker remounts, and preference/focus subscriptions are cleaned up rather than duplicated.
- [ ] Opt existing-Attribute/shared-Value choosers into whole-row selection. Clicking their name, Key, Code, or row whitespace toggles selection; clicking their native checkbox toggles once. A default action or another interactive control never changes picker selection. Recheck IDs/status/ownership in operations even when picker state is native.

**Gate:** the complete “new Master → new Attribute Value with Code → new/shared Attribute → Configurator inclusion with chosen default” flow saves atomically. Adding new Values remains available when every existing Value is included.

### Task 4 — Default selection in the table

**Modify:** both Configurator relation managers, ConfiguratorAttributeForm, ConfiguratorAttributeCreationForm, and ConfiguratorWorkspaceTest.

- [ ] Add saved and staged Default column actions using the component contracts above.
- [ ] Remove the default dropdown from the current Configurator workspace editor/review where the column replaces it. Update reviewComponents' current array_pop assumption; do not accidentally move or drop a different field. Retain the legacy standalone form's valid default input where no replacement table is present.
- [ ] Strip default_configurator_option_id from this workspace's general settings form state/dirty hash and ordinary Save Attribute patch. Treat the default as its own save boundary so a later settings save cannot restore a stale default.
- [ ] Save the default through a fresh aggregate patch, then reconcile only default metadata in the selected/stashed editor baselines. Do not call a full editor reload that discards drafts. A draft option's local name/subtitle/hint/flags remain untouched.
- [ ] Test “edit local name without saving → click another Default → switch row/back → save local name.” The new default and draft name both survive. Repeat with an unsaved Attribute setting and with a different Value selected in the editor.
- [ ] Test current-default no-op, Value outside owner rejection, and staged default removal requiring reselection. Retain current policy allowing a stored default that may be unavailable under runtime rules; runtime fallback is unchanged.

**Gate:** one clear visible default control in each target workspace; saved identity is the local inclusion ID, never a shared Option or Master ID.

### Task 5 — Explicit local subtitle intent, editor layout, and Rules compatibility

**Create:** the enum above, generated add_subtitle_mode_to_configurator_options_table migration, ConfiguratorValueTextFields, and /Users/studioycm/Herd/configurator/resources/views/filament/resources/configurator-value-preview.blade.php.

**Modify:** [ConfiguratorOption](/Users/studioycm/Herd/configurator/app/Models/ConfiguratorOption.php), [ConfiguratorOptionFactory](/Users/studioycm/Herd/configurator/database/factories/ConfiguratorOptionFactory.php), ConfiguratorInclusionDrafts, ConfiguratorDefinitionLoader, ConfiguratorDefinitionCompiler, SaveConfiguratorDefinition, [SaveConfiguratorOption](/Users/studioycm/Herd/configurator/app/Actions/SaveConfiguratorOption.php), ConfiguratorAttributeForm, AttributesRelationManager, OptionsRelationManager, [ConfiguratorEditorChanges](/Users/studioycm/Herd/configurator/app/Services/ConfiguratorEditorChanges.php), [BatchCatalogChanges](/Users/studioycm/Herd/configurator/app/Actions/BatchCatalogChanges.php), [configuration-fields Blade](/Users/studioycm/Herd/configurator/resources/views/components/catalog/configuration-fields.blade.php), and relevant Value-specific translation keys in lang/en/configurator.php and lang/en/catalog.php.

- [ ] Add /Users/studioycm/Herd/configurator/tests/Feature/ConfiguratorSubtitleTest.php, including migration upgrade fixtures; extend compiler, workspace, editor-change, batch-edit, and runtime integration tests at their existing owners.
- [ ] Add the column/backfill and pure enum resolver. Preserve custom text, set historical blanks to None, and apply the same normalization to missing-mode legacy drafts and newly staged Values.
- [ ] Carry mode through every loader/compiler strict-key list and normalized result, writer allowlist, inclusion draft, local form state, dirty comparison, signed editor capture, tested-change save, duplication roundtrip, and batch-edit state/token. Do not persist preview/proxy/helper fields.
- [ ] Retain SaveConfiguratorOption's partial-patch semantics: absent subtitle fields preserve mode/text; explicit clear selects None. In batch edits, entered text selects Custom, cleared text selects None, and an unchanged subtitle preserves Master mode. Validate mode/text jointly; do not leave a linked mode with ignored edited custom text.
- [ ] Implement the agreed field order and link controls. Use one escaped live preview beneath name/subtitle. Display a current Master name as a read-only proxy, never as copied custom data; exclude proxy/resolved-name values from dirtiness and signed payloads.
- [ ] Update runtime template conditions to require nonblank subtitle AND subtitle unequal to the final main label. Keep ConfiguratorOptionDTO.displayValue, runtime display_value, rule SetDisplayValue, rule import/export headers, and rule priority/tie semantics intact. Value-specific rule labels/help may say subtitle; do not globally rename Attribute-level “display value” wording.
- [ ] Test None/Custom/Master after save and reload, unchanged save, UI link/unlink restoration, explicit clear, missing/invalid mode, whitespace-only input, the valid subtitle "0", legacy nonblank text, and historical blank migration. Test two Values sharing a Master: one local name plus Master-linked subtitle and one Custom subtitle.
- [ ] Rename that Master through SaveCanonicalDefinition, reload/evaluate through ConfiguratorDefinitionLoader, and assert the linked subtitle changes, the local main name stays, and the Custom subtitle stays. Add matching/nonmatching Rules for SetLabel and SetDisplayValue; assert subtitle suppression uses the final label and no empty second-line element renders for select or toggle inputs.
- [ ] Run Preview & Test with captured pending local edits, save tested changes, and verify mode survives. Verify a signed capture containing a forged/invalid mode is rejected and an unchanged linked row does not become dirty just because its Master name changed.

**Gate:** blank now consistently means no subtitle, including preexisting rows; explicit links remain live; local and Rules-based presentation remain independently editable.

### Task 6 — Connected browser review and bounded efficiency checks

- [ ] Use isolated approved test data for writes. Walk all three entry points and existing editors in the actual Filament app; the static prototype is not implementation evidence.
- [ ] At desktop, 1024px, and a narrow phone viewport, verify Tags/Specification follow explicit batch column choices and stay visible in ordinary edit forms, no unintended horizontal overflow, readable Codes/validation, preview placement, accessible Default/link actions, and native search/filter/Columns/reorder/action controls.
- [ ] Verify keyboard entry, adding/removing rows, error focus, Save/Cancel focus restoration, Discard/Keep editing, and continued editing after failed validation. For each picker type, verify whole-row selection, checkbox Space behavior, search/page/remount retention, disabled/max-selection rows, and independence from links/buttons/default controls.
- [ ] Measure opening an empty form, ten Master Values, several Attributes with staged Values, switching active pickers, and one submit. Record browser request count/payload and server query count separately; no numeric performance improvement is claimed before measurement.
- [ ] Read tag vocabulary and record-label maps once per request/outer schema; eager-load only displayed relation data. Avoid per-row Attribute::find/Option::find in itemLabel callbacks and repeated full tag scans. Paginate persisted catalogs; do not preload the entire Value library or mount inactive picker children.
- [ ] Run the isolated MySQL integrity suite and exercise competing Code creation/inclusion and canonical edits from separate requests. Assert one conflict is reported cleanly, no partial graph persists, and owner defaults/drafts remain intact. Record concurrency behavior separately from SQLite results.
- [ ] Present the working app for review against section 2. Keep Groups and generalized layout refactors as later discussion.

## 6. Commands and verification

All commands below are for the later authorized implementation, from /Users/studioycm/Herd/configurator. Generator flags were checked with installed Artisan help; no generators or tests were run to produce this plan.

Recheck AGENTS.md, .ai/rules if it has appeared, versions, git state, and the latest architecture findings before editing. Establish an isolated implementation worktree using the normal managed-worktree workflow if needed; do not mix the current unrelated changes into this feature.

Create plain application/UI factory classes with php artisan make:class <name> --no-interaction, using these exact names:

    Actions/CreateMasterValues
    Actions/CreateSharedAttributes
    Actions/AddConfiguratorAttributes
    Actions/AddConfiguratorValues
    Filament/Resources/Values/Actions/CreateValuesAction
    Filament/Resources/Attributes/Actions/CreateAttributesAction
    Filament/Resources/AttributeValueWorkspace
    Filament/Resources/Attributes/Schemas/AttributeBatchForm
    Filament/Resources/Configurators/Schemas/ConfiguratorAttributeCreationForm
    Filament/Resources/Configurators/Schemas/ConfiguratorValueTextFields
    Filament/Resources/Configurators/Actions/AddAttributesAction
    Filament/Resources/Configurators/Actions/AddValuesAction

    php artisan make:enum ConfiguratorSubtitleMode --string --no-interaction
    php artisan make:migration add_subtitle_mode_to_configurator_options_table --table=configurator_options --no-interaction
    php artisan make:view filament.resources.configurator-value-preview --no-interaction
    php artisan make:view filament.resources.master-value-batch-columns --no-interaction
    php artisan make:test --pest MasterValueCreationBatchTest --no-interaction
    php artisan make:test --pest SharedAttributeCreationBatchTest --no-interaction
    php artisan make:test --pest ConfiguratorCreationFlowTest --no-interaction
    php artisan make:test --pest ConfiguratorSubtitleTest --no-interaction

No new resource, relation manager, Livewire component, factory model, or provider generator is required. Update existing test files rather than replacing them.

Use existing Livewire\Livewire::test and Filament\Actions\Testing\TestAction helpers. There is no installed Pest Livewire/browser plugin to assume; no dependency installation is part of this plan. Follow the current test bootstrap/factory conventions.

Run the narrowest affected file during each task, then the connected group:

**Test environment prerequisite (audit CA08 / existing S07):** Before any Feature test, verify bootstrap/cache/config.php is absent and run in an explicit isolated environment. Do not remove the developer's config cache implicitly; use a clean implementation/test worktree if needed. For each SQLite command below, prefix `env -u DB_URL -u DATABASE_URL -u APP_CONFIG_CACHE APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory:`. This clears inherited URL/cache overrides and pins the target despite phpunit.xml's non-forced entries. Check the resolved SQLite configuration if the bootstrap/config has changed; stop before destructive tests if it is not the in-memory target. The MySQL command instead uses its existing literal disposable-target guard and phpunit.mysql.xml; never apply the SQLite prefix to it. Fixing the general guard is separately tracked S07 work, not an implicit feature refactor.

    php artisan test --compact tests/Feature/SuggestedIdentifiersTest.php tests/Feature/MasterValueCreationBatchTest.php tests/Feature/ValueSemanticIdTest.php
    php artisan test --compact tests/Feature/AttributeValueBatchTest.php tests/Feature/SharedAttributeCreationBatchTest.php tests/Feature/Filament/SplitListCreationTest.php
    php artisan test --compact tests/Feature/ConfiguratorCreationFlowTest.php tests/Feature/Filament/ConfiguratorWorkspaceTest.php tests/Feature/Filament/ConfiguratorAdministrationTest.php
    php artisan test --compact tests/Feature/ConfiguratorSubtitleTest.php tests/Feature/ConfiguratorEditorChangesTest.php tests/Unit/ConfiguratorCompilerTest.php tests/Unit/OptionRulePresentationTest.php
    php artisan test --compact tests/Feature/Catalog/ConfiguratorIntegrationTest.php tests/Feature/Catalog/ConfiguratorRuntimeTest.php
    vendor/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Catalog/MySql/ConfiguratorIntegrityTest.php

MySQL prerequisite: the existing bootstrap requires the isolated configurator_catalog_test database and user on 127.0.0.1:3307 with APP_ENV=testing, and rejects URL/cached-config overrides. Respect its checks; never redirect this suite to the development database. If the isolated target is unavailable, report that check as unrun.

After PHP/Filament edits:

    vendor/bin/pint --dirty --format agent
    vendor/bin/filacheck --fix --dirty

Resolve any remaining FilaCheck violations; inspect the diff so these dirty-file tools do not sweep in unrelated work. This plan includes JS, Blade, and theme changes, so run the affected Node files during Task 1, then npm test and npm run build before connected browser acceptance. No front-end framework or bespoke JS form engine is proposed.

After affected tests pass, ask the user to run the complete suite with php artisan test --compact, as required by AGENTS.md. Report actual test/browser/MySQL results and any gaps. Commits, pushes, live database migration, and deployment require their own applicable authorization.

## 7. Verification references and plan status

Version-matched documentation used for design/API checks:

- [Filament 5 repeaters](https://filamentphp.com/docs/5.x/forms/repeater), [reactive forms](https://filamentphp.com/docs/5.x/forms/overview), [actions](https://filamentphp.com/docs/5.x/actions/overview).
- [Filament 5 action modals](https://filamentphp.com/docs/5.x/actions/modals), [column actions](https://filamentphp.com/docs/5.x/tables/columns/overview#triggering-actions), [action testing](https://filamentphp.com/docs/5.x/testing/testing-actions).
- [Laravel 13 transactions](https://laravel.com/docs/13.x/database#database-transactions), [Livewire 4 actions](https://livewire.laravel.com/docs/4.x/actions), [Filament code organization](https://filamentphp.com/docs/5.x/resources/code-quality-tips).

Installed source checks included InteractsWithActions::callMountedAction, Schema::getState, TableSelect/WorkspaceTableSelect Modelable embedding, Repeater::getAddAction/getRawState/rawState and child defaults, Field::hintActions and Action::schemaComponent injection, Livewire.interceptMessage/onMorphed cleanup, CanCallAction::action, CanOpenModal controls, and the existing application write/lock paths. Schema relationship writes may precede the action callback; the plan deliberately stages scalar data without those persistence hooks. The accepted Filament Examples source and its application-specific adaptations are recorded in section 1.

This document supersedes earlier single-create/default-dropdown/subtitle-fallback suggestions only for the scoped workflows. It does not absorb the separate Attribute Value class/table-renaming plan or the image-assignment plan.

## 8. Audit traceability and implementer instructions

| Finding | Adopted instruction | Owning task |
| --- | --- | --- |
| CA01 | One picker-scoped adapter invokes native checkboxes; preserve keyboard/limits and keep ordinary rows/defaults independent. | 1–3, 6 |
| CA02 | Native table Repeater plus exact scoped toolbar/JS/theme files; CSS-only optional-column hiding, error reveal, and bound hint Actions using the raw-state/default lifecycle. No vendor-view fork. | 1, reused in 2–3 |
| CA03 | Complete pools/lists, stable refs and original error indices; create referenced pending Masters once before Attribute loops. | 1–3 |
| CA04 | Acquire context before the aggregate patch callback; retain one writer/transaction and fresh additive patches. | 3, 6 |
| CA05 | Default column owns a narrow save; remove stale default from the target settings payload/hash and preserve drafts. | 4 |
| CA06 | Carry explicit subtitle intent through storage, serializers, signed captures, patches, batch edits, preview and final runtime text. | 5 |
| CA07 | Static UI factories compose fields/state; four concrete operations call existing authorized writers; no immediate relationship writes. | 1–5 |
| CA08 | Normalize tags at the shared creation boundary and establish explicit safe test targets; leave broad tag/test infrastructure remediation separate. | 1 and every verification gate |

For the implementing agent, after explicit implementation authorization:

1. Refresh checkout/rules/versions and reconcile concurrent architecture work. Restrict changes to the task file maps, required existing tests, translations, and the stated frontend registrations; report a necessary scope change before adding a new framework/base directory/dependency.
2. Work in task dependency order. Add meaningful regression coverage for the behavior/failure boundary before changing it, then use installed generators and existing sibling conventions. No new resource, public route, provider, policy system, DTO framework, or JS form engine is needed.
3. Treat application operations as the trusted boundary, UI paths/IDs as untrusted, and existing writers/constraints as authoritative. Keep entered IDs, exact Codes, local inclusion IDs, draft refs, and default refs distinct. Preserve missing/null/empty/unchanged semantics.
4. Complete each task's focused acceptance gate before dependent work. On any failure, preserve draft state and roll back the full operation. Do not report an unrun browser/MySQL test as passed or infer performance from source structure.
5. Format/check only authorized changes, run the connected Node/build/PHP/browser/MySQL checks in the isolated environments, and present the actual app against section 2 before requesting broader integration/deployment. Leave existing S06/S07/S11 and other audit work accurately tracked rather than declaring it globally resolved.

**Planning verification:** structure findings CA01–CA08 have been incorporated and reconciled in the re-audit; no folder-based example rejection remains. The overall UX, reusable component map, four operations and single-writer contracts are retained. The re-audit tightens the native append/action binding and explicit Livewire 4 acceptance instructions. Installed native APIs and write/serializer boundaries were inspected. The prototype's whole-row Master selection, direct checkbox/Space behavior, pagination/search retention, existing Attribute row selection, and existing Value row selection were exercised in the earlier Chrome review, with no captured browser errors; those checks were not rerun or claimed as new app evidence during this documentation re-audit.

**Implementation verification remains pending:** no application tests, migration, Laravel browser persistence checks, DB writes, or app implementation changes were executed for this handoff. Finalizing this plan and updating the prototype do not authorize application implementation, commits, pushes, or deployment.
