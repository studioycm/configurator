# Catalog creation implementation specification

> **For the implementing agent:** This is the consolidated execution contract for Master Values, shared Attributes and Configurator creation/editing. It is documentation, not authorization to implement. After the user explicitly authorizes application implementation, use the executing-plans workflow to deliver Tasks 1–7 in order. Read this specification completely before editing; do not reconstruct requirements from the voice transcript or historical findings.

**Goal:** Deliver fast batch creation with reusable native Filament fields/selectors, Configurator-specific defaults and editing, explicit subtitle intent, atomic writes and independently reviewable evidence.

**Architecture:** The existing native Action slide-over owns one scalar draft and one pending-Master pool. Resource-local schema/Action factories compose native components. Four focused application Actions invoke existing canonical/aggregate writers. The existing adjacent editors remain. A same-host named schema is a conditional fallback after the connected lifecycle gate, not a second implementation to build in parallel.

**Status:** Consolidated specification; application implementation and runtime acceptance pending. Prepared 2026-10-23 in /Users/studioycm/Herd/configurator at HEAD 4e82c222da421d098e8d1a9b5e50ef5a442b6ecc, branch master. Boost and installed source confirm PHP 8.4 / Laravel 13.35.0 / Filament 5.9.0 / Livewire 4.4.7 / Pest 5.3.0 / Blueprint 2.4.0; previous CLI inspection reports PHP 8.4.27 and FilaCheck Pro 1.2.7. Refresh these before implementation. No dependencies change.

**Authority:** Current user instructions and applicable AGENTS/path rules govern. This specification consolidates and replaces the executable guidance of the earlier implementation plan and execution blueprint; those documents and the audits are provenance, not additional competing instructions. §§1–8 are the complete scoped implementation contract. A later explicit amendment must identify the affected contract/task. Installed source establishes API mechanics; a conflict that changes agreed behavior must be resolved, not silently redesigned.

**Execution amendment — Preview compatibility (2026-10-23):** The user subsequently authorized Tasks 1–7 with independent task reviews and final audits. Implementation uses the isolated /Users/studioycm/.codex/worktrees/catalog-creation/configurator checkout on codex/catalog-creation-preview, now based on the committed Preview & Test revision 244b305d083a90c731cd4bde23d996b4db0f8f3d. Earlier preparation-only status is historical. Preserve that commit's inspection/capture/snapshot/reconciliation contracts; this adds no new UX scope. Image implementation and Rules Writing mockup work remain separate, with compatibility checked before integration. No commit, primary merge, push or live migration/deployment is authorized here.

**Execution amendments — connected correctness reviews (2026-10-23):** These retain the agreed workflows and make the following corrections mandatory. They supersede an older mechanical instruction only where it conflicts with the stated correction; other clauses remain binding. The initial mounted/Node identifier proof is not final browser acceptance. Keep the existing requirement sub-IDs; append amendment clauses without renumbering the original ledger.

- **CA16 / C03, C11, C12 — identifier response ordering:** An automatic suggestion must not replace newer manual input, including a deliberate clear, while its request is pending. Keep the existing identifier algorithms, reservation providers, field factory interfaces and native editable state. The server computes a scoped candidate instead of writing the editable identifier into an automatic response; a small `resources/js/suggested-identifier.js` field adapter accepts it only for the current component, absolute field/source paths, stable row identity, source name and request intent. Retain exactly the bounded candidate/intent metadata needed by the native fields, nondehydrating and excluded from operation payloads; it is neither another record draft nor a second slug algorithm. Preserve same-request candidate reservations, existing/manual identifiers, fill-once behavior, explicit regenerate authorization and later-input precedence, and destroy/remove/reopen isolation. Grouped mounted updates and Node intent/lifecycle cases support the mechanism; actual pending-input, rapid two-row, nested context and Enter/append behavior must still pass in the browser.
- **CA17 / C01, C15 — ordinary shared Attribute comparison:** Opening Add Values, switching its disclosure/tabs or initializing wholly blank composer rows must not fabricate an unsaved edit or a stale-editor conflict. Keep raw editing state intact. Apply one Attribute-scoped meaningful-draft comparison consistently to the server baseline/current hash and the frontend initial/saved baseline, dirty marker and footer. Preserve exact Label/Key, pending associations/Codes/refs, meaningful pending Masters and composers, and nonempty selections. Ignore only presentation/helper state and wholly blank generated rows in the comparison; do not recursively strip underscore keys. A small `resources/js/attribute-editor-draft.js` adapter may mirror this projection; generic editors retain their existing raw comparison by default. Persisted-record fingerprints, real same-record conflict handling and save/reload acknowledgment remain authoritative.
- **CA18 / C15, C12 — saved Default visibility and existing profiles:** Declare the native saved Default column first, before Code. Existing profiles may retain its earlier display-only position, so the Options table performs one narrowly configured migration within the existing browser-profile mechanism: move only Default first, preserve every other relative order/toggle/setting and width key, and mark the client-only revision only after successful settled native restoration. Preserve existing profile keys and PHP restoration/validation, do not reset other tables, and respect subsequent explicit user reordering. Use the existing Options relation manager, table-toolbar configuration Blade and `resources/js/admin-profile.js`; no new persistence/store or migration registry. Verify physical click/Space and settled reload behavior at desktop, 1024px and phone widths.

## 1. Execution entry and constraints

- **Planning boundary:** Current authorization covers this document and review only. No application code, tests, migrations, database data, packages, commits, pushes or deployment change during specification preparation. Later full-feature implementation authorization covers Tasks 1–7 without repeated phase approvals; integration and live migration/deployment retain their applicable approval boundaries.
- **Checkout:** Read AGENTS and matching .ai/rules before implementation. That rule directory is absent at this snapshot. Preserve concurrent work: docs/README.md and the image-assignment plan/blueprint are excluded. Use a suitable isolated implementation checkout when needed; never reset/stage unrelated work. Reconcile current source/versions rather than blindly trusting historical line numbers.
- **Scope:** Master Value list batch Create; shared Attribute list batch Create and current direct/adjacent editors; Configurator Add Attributes, Add Values, table defaults, existing local editors and subtitles. Preserve existing search, filters, columns, pagination, ordering, row actions, scoped saves and direct create routes.
- **Exclusions:** Groups; Attribute Value model/table renaming; new routes/resources/panels; durable record-draft recovery after browser reload; a new base directory, provider, dependency, repository/DTO framework or JavaScript form engine. Broader existing S06/S07/S11 architecture/tag/test-infrastructure work remains separate unless a concrete feature dependency requires a scoped correction.
- **Skills:** Use planning-filament for this native component contract; filament-development/livewire-development for implementation; laravel-development-architecture and laraveldaily-structure-audit for responsibility boundaries; testing-best-practices for meaningful tests; reviewing-filament-plans for the final complete conformance ledger. Use installed-version Boost docs/source and the inspected Filament Examples patterns below, not a free-form replacement UI.
- **Review focus:** Manual/cleared identifiers; one pending Master reused under two Attributes; saved default changes while other edits remain unsaved; subtitle mode through partial/full/signed saves; stale/foreign membership; blank/deleted-row error positions. The named tests/gates below must discriminate these failures.

### Stable contracts and execution ownership

Contract IDs identify cohesive specification blocks; each independently falsifiable clause remains mandatory. The final review retains these IDs and gives split clauses stable sub-IDs in source order. CA IDs retain audit lineage; Task/Test IDs below identify executable acceptance, not a separate tracking system.

| Contract | Owning section | Implement and verify in |
| --- | --- | --- |
| C01 | Entry points and participating versus retained drafts | Tasks 1–2, 4, 6 |
| C02 | Native picker whole-row selection | Tasks 1–2, 4, 6 |
| C03 | Master columns, exact append and keyboard lifecycle | Tasks 1–2, 6 |
| C04 | Value text/preview and subtitle states | Tasks 3, 6 |
| C05 | Subtitle storage, migration and PATCH presence matrix | Tasks 3, 6–7 |
| C06 | Exact staged shape, identity, empty rules and error translation | Tasks 1–4 |
| C07 | Public operations and authoritative writer ownership | Tasks 1–5, 7 |
| C08 | Authorization, atomicity, lock order and fresh patches | Tasks 1–2, 4–7 |
| C09 | Fresh UI factories and exact public signatures | Tasks 1–4 |
| C10 | Explicit new-row model/create/uniqueness context | Tasks 1–2, 4 |
| C11 | Relative/absolute state, programmatic transitions and partial validation | Tasks 1–4 |
| C12 | Scoped frontend files/native append lifecycle | Tasks 1–2, 6 |
| C13 | Livewire picker binding, locked context and cleanup | Tasks 2, 4, 6 |
| C14 | Field constraints and native component classes | Tasks 1–5 |
| C15 | Default columns and native Action/discard lifecycle | Tasks 1–2, 4–6 |
| C16 | First connected lifecycle proof and fallback decision | Start Task 2 |
| C17 | Safe test targets, commands, browser and concurrency evidence | Every task, especially 6 |
| C18 | Final conformance/dependency audit and finding disposition | Task 7 |

## 2. Agreed interaction contract — C01

| Entry point | Workspace and result |
| --- | --- |
| Master Values → Create | One resizable slide-over with spreadsheet-style entry rows: UI Name, editable ID Name, optional Tags and Specification columns. Start with one row; add one, five, or ten more rows. A Columns control changes presentation without dropping data. One Create action saves the completed batch. |
| Shared Attributes → Create | One native slide-over with repeatable Attributes. Label first, editable Key second. Each Attribute can choose existing Master Values, create new Master Values inline, and enter a Code per association. A shared Attribute may be saved without Values. |
| Shared Attribute → edit | Retain the adjacent editor and its existing Values table. Reuse the same inline selection/creation workspace when staging additional Values; save the definition and additions together as today. |
| Configurator → Add Attributes | One native slide-over with Existing Attributes and Create Attributes sections/tabs. Both feed one staged review and one Save and add action. For each new local inclusion, choose at least one eligible Value and an explicit default in its Values table. New shared definitions are created and locally included in the same transaction. |
| Configurator → edit included Attribute | Retain the actual editable local pane and included Values table. Edit existing local Values there; the Add Attributes slide-over does not replace editing. |
| Included Values → Add Values | Extend the current inclusion action. Select existing shared Attribute Values, add associations using existing Master Values plus Codes, or create Master Values inline. Save and include commits the mixed batch. Keep the action available even when all existing shared Values are already included. Preserve the saved default. |
| Included Values → Default column | A named, keyboard-accessible icon action saves only the default change. Clicking the current default is a no-op. New-Attribute review uses the same visible column pattern but only stages the choice until the outer save. |

The two Configurator tabs are additive: final submission includes selected existing Attributes and populated new Attribute entries. Switching tabs preserves both branches.

**Participating versus retained state:** Keep selected reviews in the submitted `reviews` collection; deselected reviews and their unfinished UI composers live in a separate nondehydrating stash keyed by trusted Attribute/ref identity. Reselecting restores that same draft. Stashes never enter operation payloads or required-field validation, but count as meaningful dirty state for Discard. Final Save validates participating composers/rows only; it must not discard a nonempty participating composer silently. The review makes clear that deselected drafts are not being added. Clear the entire outer session only after success or confirmed Discard. Project each participating collection in its current native order, preserving all its rows/positions; excluding a stash is not filtering/reindexing a submitted collection.

Reuse is at the field, selector, staging, validation, and writer boundaries. The Configurator keeps local settings, a Default column, and its own arrangement. Shared Attributes have no stored-default concept.

### Selection rows — C02

In every batch picker, clicking an eligible row's noninteractive area toggles its checkbox. This applies to existing Master Values, pending Masters in “New in this batch,” existing shared Attributes, and shared Attribute Values in Add Values. Names, identifiers, Codes, and row whitespace are selection targets. The existing checkbox remains the keyboard control and exposes the checked state; do not add another row tab stop.

Direct checkbox and wrapping-label clicks toggle once. Links, buttons, editable inputs, selects, textareas, and other interactive controls retain their own actions. Ignore nonprimary clicks, text-selection gestures, and disabled/loading rows; honor native maximum-selection limits. Search and pagination preserve the same selected IDs/refs. Ordinary resource listing rows, existing editor rows, default actions, and reorder handles do not acquire this behavior. Selection does not itself create, include, save, or authorize a record.

Use the picker-scoped adapter specified in section 4, rather than a global table override or a second selection store. Filament 5.9's native `selectable()` enables checkboxes; it does not alone implement this row-click contract.

### Master Value rapid entry and optional columns — C03

The operator's later clarification replaces the always-visible requirement specifically for batch creation. Use the existing slide-over resize behavior from /Users/studioycm/Herd/configurator/resources/js/workspace-dialogs.js. A wider workspace presents UI Name, ID Name, Tags, and Specification as aligned columns.

- UI Name and ID Name remain visible. Following the operator's 2026-10-23 QA correction, Tags and Specification start hidden in every reused batch editor; Columns offers their unchecked checkboxes, and Reset columns restores that default. Deliberately remembered column preferences still take precedence. Show either optional column to edit that field across the same unsaved rows; ordinary single-record edit fields remain visible.
- Column changes preserve row refs, names, manually edited IDs, tags, specifications, errors, and focus context. Hiding means presentation only: hidden data is still submitted and validated. If an error belongs to a hidden column, reveal that column and focus the affected field.
- Provide Add row, Add 5 rows, and Add 10 rows. Append stable empty rows without changing existing entries or the chosen columns; respect the existing batch limit and focus the first newly added UI Name.
- Enter in UI Name commits/blurs that name, triggers its normal identifier suggestion, and focuses the next row's UI Name instead of submitting the form. At the last row, add and focus one new row within the limit. Shift+Enter moves to the previous UI Name. Tab/Shift+Tab retain normal field navigation so ID Name remains easy to edit. Do not intercept Enter inside Tags, Specification, suggestion popovers, or IME composition.
- Resizing never changes the chosen columns or their data. Keep UI Name and ID Name in a horizontal entry row at the normal desktop Attribute edit-pane width; showing optional fields must retain aligned rows, using local table scrolling when needed. Stack labeled fields only at a genuinely narrow phone-sized container, with no page overflow or tiny inputs. Remember only column preferences per user/browser and creation context using the existing browser-profile mechanism; do not persist record drafts there.
- Reuse this Master Value batch editor inside both Attribute creation flows, with the same optional-column and keyboard behavior. Existing single-record edit panes keep their present visible fields.

Filament 5.9 supplies table repeaters and the native field components. Its Repeater TableColumn is not the record table's toggleable column manager. The Columns control, responsive presentation, and keyboard focus behavior require a scoped UI extension; do not promise they work by applying toggleable() to a repeater column. Keep scalar staging and the existing writers unchanged. Do not rely on hidden() alone, which can omit field state from dehydration/validation.

### Value text and subtitle behavior — C04

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

### Persisted changes and subtitle input semantics — C05

Only the subtitle feature requires a schema change:

- Add configurator_options.subtitle_mode: string length 16, non-null, default none; no new index or relationship.
- Add App\ConfiguratorSubtitleMode at /Users/studioycm/Herd/configurator/app/ConfiguratorSubtitleMode.php, alongside existing root enums: None = none, Custom = custom, Master = master. Use TitleCase case names; implement HasLabel for UI labels. Its pure public resolve(?string $custom, string $masterName): string method returns a string, including an empty string for None; validation owns the mode/text invariant before resolution.
- Keep display_value_override as nullable varchar(255), storing text only for Custom. Keep ConfiguratorOptionDTO.displayValue typed string and the runtime display_value key; no nullable DTO/wire rename is necessary.
- Add the mode to ConfiguratorOption fillable and its unsaved-model default. Retain the current raw-string model convention rather than introducing an enum cast into array consumers.
- Existing blank/null/whitespace-only display_value_override → None and null text. Existing nonblank text → Custom and retained text. Use the same blank-text check in migration, UI normalization, and writers: null or a string whose trim is empty is blank; the string "0" is not blank. Do not otherwise trim or rewrite existing custom text. **Do not backfill blank rows to Master.**
- Legacy incoming drafts with no mode infer Custom from nonblank custom text, otherwise None. Explicit invalid/null/empty mode is a validation error. Explicit Custom requires nonblank text ≤255; None/Master normalize stored custom text to null.
- Normalize into the compiler's returned definition data as well as the DTO, so reload and unchanged saves preserve intent.

Use this key-presence matrix before merging subtitle patches. The same blank predicate is `null` or a string with `trim($text) === ''`; `"0"` is not blank. Preserve nonblank custom text verbatim.

| Input boundary | Submitted subtitle keys | Result |
| --- | --- | --- |
| Complete legacy definition/draft | no `subtitle_mode` | Infer Custom from nonblank `display_value_override`, otherwise None; return normalized mode/text in definition data as well as DTO. |
| Existing local-Value PATCH | neither key present | Preserve fresh saved mode and custom text. A hint-only PATCH never unlinks a subtitle. |
| Existing local-Value PATCH | text only | Nonblank text → Custom; blank/null → None. This legacy text-only PATCH deliberately replaces linked intent. |
| Any explicit mode input | mode None or Master | Store null custom text; live Master text is resolved, never copied. |
| Any explicit mode input | mode Custom | Require submitted nonblank string text ≤255. Custom with missing text is an error; do not borrow stale saved text. |
| Any explicit mode input | invalid, null or empty mode | Error before merging/writing, even if text could imply a mode. |
| New creation `local_fields` omitted | no block | Defaults to None/null. If the block is supplied, validate its complete shape below. |

The local editor sends a complete mode/text pair when subtitle intent changes. In batch-edit selection, an unchanged subtitle omits both keys; explicit entered/cleared text follows the PATCH rules. Full aggregate data already carrying Master/null remains Master across unrelated saves. The UI must switch mode to Custom when typing custom text, rather than leave ignored typed text under Master mode.

The additive migration must perform its small deterministic backfill without application model events. Deploy migration and compatible code together. A code rollback restores the old blank fallback, and dropping the mode loses live-link intent; document this as a semantic rollback limitation and prefer a forward fix.

All existing canonical tables, constraints, model names, route slugs, IDs, and exact Code semantics remain intact.

### Staged domain contracts — C06

Use PHPDoc array shapes, not a new DTO/draft framework. All new domain `ref` values use `new:<UUID>` generated server-side with `Str::uuid()`; validate the prefix and UUID suffix. Native Repeater item keys remain their separate bare UUIDs. Compare refs exactly. Ref namespaces distinguish Masters, associations, Attribute drafts, reviews and local review Values; refs are unique within each namespace across the entire submitted workspace. Association refs must be unique across Attributes because `option_ref` is not parent-qualified. Local review-Value refs must be unique across reviews, matching the compiler's existing local-ID discipline.

```text
MasterRow = {ref, label, semantic_id, tags?, description?}
AssociationRow = {ref, value_id? XOR value_ref?, code}
AttributeRow = {ref, label, key, values?}
Review = {ref, attribute_id? XOR attribute_ref?, settings?, values, default_ref}
ReviewValue = {ref, option_id? XOR option_ref?, local_fields?}
settings = {input_type, label_override, help_text}
local_fields = {label_override, subtitle_mode, display_value_override,
                hint, hidden_by_default, disabled_by_default}
```

| Shape/key | Exact type, presence, default and constraint |
| --- | --- |
| MasterRow | `ref` required even for an empty native row; participating populated rows require string `label`≤255 and `semantic_id`≤100 under C14; nested unreferenced rows are not subjected to participating field requirements. Missing `tags`→`[]`; missing `description`→null. No extra keys. |
| AssociationRow | Required ref and exact Code. Exactly one positive persisted `value_id` or root-pool `value_ref`; the unused key is absent, not null. No extra keys. |
| AttributeRow | Required ref; populated rows require `label`/`key`; missing `values`→`[]`. Values are AssociationRows. No default/local fields and no extra keys. |
| Review | Required ref, exactly one existing selected `attribute_id` or populated new `attribute_ref`, nonempty `values`, and required `default_ref` belonging to one of those local ReviewValue refs. One review per participating Attribute; no duplicate canonical inclusion. |
| settings omitted | Use `{input_type: 'toggle', label_override: null, help_text: null}`. |
| settings supplied | Exactly these three keys: `input_type` string `toggle` or `select` using App\ConfigInputType; nullable `label_override` string≤255; nullable `help_text` string≤5000. No IDs/order/default/active flags supplied here. |
| ReviewValue | Required ref; exactly one existing `option_id` or staged `option_ref`. The Option/association must belong to this review's Attribute. No extra keys. |
| local_fields omitted | Use `{label_override: null, subtitle_mode: 'none', display_value_override: null, hint: null, hidden_by_default: false, disabled_by_default: false}`. |
| local_fields supplied | Exactly these six keys; nullable label≤255, explicit valid subtitle mode and joint text rules in C05, nullable hint≤1000, strict boolean flags. Existing full-definition/legacy hint limits remain unchanged outside the new creation boundary. |
| Persisted IDs | Validate positive integers / integer-valued form strings before deliberate cast to int; reject arrays, booleans, zero and foreign records. Ref values never enter Eloquent ID queries. |
| Every submitted list | Native UI uses UUID-keyed arrays; the Action adapter projects ordered lists preserving all participating rows. Enforce ≤500 raw entries before empty-row normalization. Retain existing final aggregate ≤500 limits as well. |

The new operation payload roots are exact:

- `CreateMasterValues`: the MasterRow list plus separate optional referenced-ref list in its method signature.
- `CreateSharedAttributes`: the AttributeRow list plus separate MasterRow pool.
- `AddConfiguratorAttributes`: only `master_values`, `attributes`, `existing_attribute_ids`, `reviews`; missing root collections default to `[]`. Existing IDs are distinct. Require exactly one matching review for every selected existing and populated new Attribute; reject reviews of unselected/foreign drafts. An empty optional new branch does not call standalone CreateSharedAttributes.
- `AddConfiguratorValues`: only `existing_option_ids`, `new_values`, `master_values`; missing collections default to `[]`. `new_values` are AssociationRows; the owner Configurator and trusted local Attribute inclusion ID are separate arguments. All requested associations are for that one shared Attribute. Newly included rows use ConfiguratorInclusionDrafts defaults, including None subtitle; local editing remains in the existing editor.

**Blank-row rule:** Generated refs and helper flags do not populate a row. A user string is blank for row-presence testing only when null, empty, or whitespace-only; empty arrays are blank. A Master row is populated if name, ID, any tag entry or specification is nonblank. An Attribute row is populated if Label/Key or any association/participating composer is nonblank. This presence predicate does not trim or rewrite submitted identifiers/Codes. Tags normalize at C07; Codes always validate exactly. A selected Master with missing Code, or a name whose ID was deliberately cleared, is incomplete and fails. Apply the same predicate to schema required conditions, composer staging and operation normalization; do not reject wholly blank appended rows before normalization.

| Invocation | Minimum/result |
| --- | --- |
| Standalone CreateMasterValues (`referencedRefs === null`) | At least one populated Master; all-empty fails at collection root. Create all populated rows atomically. |
| Nested CreateMasterValues (`referencedRefs` is a list) | The pool may be empty. An empty ref list returns `[]` after authorization and structural/ref/limit validation, with no inserts. Validate full shape/refs; field requirements apply to referenced populated rows. Missing/blank referenced rows fail. Unreferenced staged Masters are not created. |
| Standalone CreateSharedAttributes | At least one populated Attribute; each may have `values: []`. No default concept. |
| Configurator Add Attributes | At least one actual inclusion. Existing-only selection works with empty new Attributes/Master pool. Every new local inclusion still requires ≥1 eligible Value and an explicit default, even one Value. |
| Configurator Add Values | At least one requested existing Option or new association. Existing-only selection works with no new Masters. A pool containing only unreferenced Masters is not an addition. Preserve the saved default. |

A local ReviewValue ref passes verbatim to the compiler as that new local option's `id`; `default_ref` becomes `default_configurator_option_id` referring to the same temporary local ID. The aggregate writer maps it to the saved numeric ConfiguratorOption ID. A review ref similarly becomes its temporary ConfiguratorAttribute ID. Shared Option IDs/Master IDs/association refs are never saved default IDs.

Initially stage all active nonhidden shared Values for a selected existing Attribute, using the current draft builder. Retain review edits on deselect/reselect. New shared definitions participating in Configurator Add Attributes must each have their matching local review; do not create unattached shared definitions as a side effect of that workflow.

Keep one pending Master pool per outer workspace. Create and use validates the current native Master composer only, appends staged pool rows and association refs, and performs zero inserts. Other Attributes choose these staged Masters through New in this batch. Keep unfinished composers in scalar UI state keyed by their stable owner while mounting only the active composer/picker. Final Save rejects any nonempty participating composer not staged or explicitly discarded. Unreferenced staged Masters remain visible with a will-not-be-created explanation. Remove a referenced pending Master only after removing its associations; show affected Attribute names.

**Submission and error mapping:** Preserve full submitted pools/lists and original index/ref maps even when normalization skips a blank row. The Action adapter projects only allowed domain keys; it never passes UI helpers/stashes/proxies or filters/reindexes away submitted rows. Direct operations reject unknown keys rather than silently strip them. Perform the following source-to-domain translation inside the operation that knows the source maps, then invoke the existing ConfiguratorFormErrors adapter exactly once against the actual owning Schema:

| Writer/source error | Domain error path |
| --- | --- |
| Canonical `definition.<field>` or `<field>` | `master_values.<originalPoolIndex>.<field>` or `attributes.<originalAttributeIndex>.<field>` |
| AddAttributeValues `pending_options.<resolvedIndex>.<field>` | `attributes.<originalAttributeIndex>.values.<originalAssociationIndex>.<field>` in batch creation; `new_values.<originalAssociationIndex>.<field>` in Add Values. Resolve `value_id` back to the submitted `value_id` or `value_ref` key; never invent the unsubmitted key. |
| Existing Attribute `saveDefinition` additions | Keep `pending_options.<originalIndex>.<field>` (translate resolved `value_id` to `value_ref` for a staged source); Master errors use `master_values.<originalPoolIndex>.<field>`. |
| New appended aggregate Attribute/Option path | Translate through original review/ref maps. Option label/subtitle/hint/flag fields → `reviews.<r>.values.<v>.local_fields.<field>`; `option_id` → the submitted `option_id` or `option_ref`; local `id` → `ref`. Attribute settings → `reviews.<r>.settings.<field>`; canonical `attribute_id` → submitted `attribute_id` or `attribute_ref`; Attribute `id` → `ref`. Server-generated order/unsupported internal fields become that row or form-level conflict messages, never nonexistent inputs. |
| Aggregate `default_configurator_option_id` error | `reviews.<originalReviewIndex>.default_ref` |
| Add Values aggregate error for a new requested inclusion | Map via the operation's Option/association source map to `existing_option_ids` or `new_values.<originalIndex>`; local field errors with no editable Add Values field become that row/collection message. |
| Aggregate error on an unchanged preexisting row | Form-level repair/conflict message; never attach it to a newly added row just because offsets coincide. |

ConfiguratorFormErrors maps these indexed paths to native UUID keys and prefixes `Schema::getStatePath()`; it does not rename collections or subtract aggregate offsets for us. Preserve Authorization/NotFound responses; report an unexpected exception through its existing generic draft-preserving error path. Never hardcode `mountedActions.0`, build another error framework, or refill the batch after failure. A leading blank/deleted row followed by a failing later row must target that later row. Keep every manual ID/helper/ref/Code/default and selection intact.

### Application operations — C07

All paths below are proposed additions inside existing directories. Concrete constructor injection suffices; no container binding or interface is needed.

| Operation / full path | Contract and reuse |
| --- | --- |
| App\Actions\CreateMasterValues — /Users/studioycm/Herd/configurator/app/Actions/CreateMasterValues.php | handle(User actor, array rows, ?array referencedRefs = null): array returns ref → persisted Value. Null means standalone: create every populated row. A ref list means nested: create only those referenced rows from the complete pool, once per ref, preserving original pool indices in errors. Authorize, validate shape/limits/refs, normalize tags, then call SaveCanonicalDefinition for each new Value in one transaction. Preserve field validation and unique-race messages; never generate a missing/cleared submitted ID during save. |
| App\Actions\CreateSharedAttributes — /Users/studioycm/Herd/configurator/app/Actions/CreateSharedAttributes.php | handle(User actor, array attributes, array masterValues = []): array returns ref → persisted Attribute. Validate cross-row refs/Codes, create referenced Master Values once, then call SaveCanonicalDefinition and AddAttributeValues for each new Attribute. No default/local settings. |
| App\Actions\AddConfiguratorAttributes — /Users/studioycm/Herd/configurator/app/Actions/AddConfiguratorAttributes.php | handle(User actor, Configurator owner, array payload): Configurator. Under the owner writer's lock, create any shared drafts, resolve IDs, validate selected existing records again, require explicit per-inclusion defaults, and append to a fresh full definition through SaveConfiguratorDefinition::change. |
| App\Actions\AddConfiguratorValues — /Users/studioycm/Herd/configurator/app/Actions/AddConfiguratorValues.php | handle(User actor, Configurator owner, int inclusionId, array payload): Configurator. Scope inclusion to owner; resolve/create new Master Values, call AddAttributeValues for new shared associations, then include selected/new shared Values through a fresh locked aggregate patch. Never replace the current default. |

Retain existing writer responsibilities:

- SaveCanonicalDefinition owns canonical field validation and writes. Value::suggestSemanticId retains its model-level algorithm.
- AddAttributeValues::handle retains resolved persisted value_id/code input and its void return. Direct callers that possess the resolved Master map (AddConfiguratorValues and the existing shared editor) query created Options once by trusted Attribute + resolved value IDs.
- AddConfiguratorAttributes receives only ref→Attribute from CreateSharedAttributes, not that operation's internal Master map. Batch-load Options for those returned new Attribute IDs, then match each submitted association by trusted saved Attribute ID + exact case-sensitive Code to recover association ref→Option ID. Require an exact match inside the transaction; a missing/mismatched association is a conflict and rolls back. Do not query bare pending refs as SQL IDs, infer Option IDs from order, or widen public results into a second graph/DTO framework.
- Extend `AddAttributeValues::saveDefinition(User $actor, Attribute $attribute, array $data, array $masterValues = []): Attribute`. Keep existing three-argument callers and root `pending_options` compatible. Accept legacy `{value_id, code}` rows there as well as new ref-based association rows. Resolve the shared pool once inside its existing transaction, remove transport-only `ref`/`value_ref` and helper fields, then call `handle` with only persisted `{value_id, code}` rows. Preserve original pending row indices in errors. Never widen `handle` into a draft-graph API.
- SaveConfiguratorDefinition remains the sole aggregate writer. SaveConfiguratorOption remains the narrow local-Value patch writer.
- BatchCatalogChanges continues to handle its current batch edits/removals; do not turn it into a generic bulk-create engine.

The new operations accept only the documented domain keys, using strict nested array allowlists. UI helpers such as _suggested_*, active picker, column choices, resolved labels, and subtitle proxies never enter writers. Use PHPDoc list/array shapes and deliberate integer normalization after validation. Error fields not tied to one entry remain at the relevant collection/form root.

Each outer action callback explicitly projects its validated native schema state into this domain shape; it does not forward the complete mounted form to an operation. Keep helper flags and editor stashes in native UI state, with nondehydrating helper/proxy fields where appropriate. Projection must retain every original row position and stable ref, including empty rows, so it cannot become an early filter/reindex step. Conversely, direct callers supplying unknown domain keys receive validation errors; writers do not silently strip unknown input. Keep this small adaptation in the owning action/page callback rather than introducing another payload framework.

`CreateMasterValues` trims each string tag before canonical validation, preserving the current UI's behavior. Validate the resulting nonblank/distinct strings; do not silently deduplicate, change case, or normalize identifiers/Codes as a side effect. All nested creation calls this operation. Broader tag-import normalization remains separate work.

Constructor dependency direction is fixed: CreateMasterValues → SaveCanonicalDefinition; CreateSharedAttributes → CreateMasterValues + SaveCanonicalDefinition + AddAttributeValues; AddConfiguratorAttributes → SaveConfiguratorDefinition + CreateSharedAttributes + existing inclusion-draft builder; AddConfiguratorValues → SaveConfiguratorDefinition + CreateMasterValues + AddAttributeValues. AddAttributeValues::saveDefinition may resolve/inject CreateMasterValues for the optional pool; it never calls CreateSharedAttributes. UI factories call these operations through action callbacks; operations never call UI factories. Concrete classes resolve transiently without provider registration or interfaces. Outputs are models/ref maps, not notifications or Filament components.

### Atomicity, lock order, and failure — C08

1. Authorize before selector data access, draft mutation, or database work. Retain the selectors' existing Gate checks; new action schemas must check access before loading choices/tag vocabulary. On submitted batch actions, use beforeFormValidated with Gate::authorize('manage-catalog') as well as action authorization and operation-level authorization, so permission revocation cannot leave database-backed validation ahead of the explicit check. Validate shape, limits, UUID uniqueness, referenced draft membership, and explicit default intent.
2. Each top-level operation owns one DB transaction. Nested existing writer transactions stay on the same connection; no partial saves or notifications escape.
3. For Configurator operations, modify SaveConfiguratorDefinition::change to acquire CatalogContextSettings::current(lock: true) immediately after locking the Configurator and before invoking the patch callback. This gives nested shared writes the same Configurator → global context → canonical Attribute/Option lock prefix used by save. Reuse ascending-ID canonical locking; do not add independent mutexes.
4. The fresh locked definition is the patch base. Append new Attributes at the existing independent display/code-order ends; append new Values at the local display-order end. Keep unrelated settings, rules, mappings, inclusions, defaults, and their IDs.
5. Existing shared definition edits already lock consuming Configurators before canonical rows. The new composition must not call an existing-definition rename/edit from inside its creation callback. It creates definitions/associations and includes them.
6. New Attribute inclusion requires an active shared Attribute. New Value inclusion requires an active, nonhidden shared Option belonging to the target Attribute. Recheck on submit; picker filtering is not enforcement. Preserve the existing rules for already included inactive definitions and their repair; Add Values must not silently introduce a new policy for editing an already included inactive Attribute.
7. Keep database unique constraints authoritative. Concurrent identifier/Code conflicts abort the whole operation and return a field error; do not silently rename, reuse another record, or partially succeed.
8. Notify and refresh tables only after the top-level transaction returns. Preserve Eloquent writes and current navigation-count invalidation. Runtime definitions already load canonical labels fresh; do not invent an observer, cached definition, or revision system for the live subtitle. If a caller already uses CatalogRevisions::batch, keep it inside its transaction.

The lock-order concern is a supported composition risk identified in source, not a reproduced deadlock. MySQL acceptance must cover uniqueness/rollback and concurrent inclusion/edit attempts; SQLite results alone cannot establish these guarantees.

## 4. Filament component specification

These are modifications to existing resources; no new resource scaffolding. Each outer schema uses columns(1); inner name/identifier grids use columns(['default' => 1, 'md' => 2]) and columnSpanFull(). No quarter-width nested grids.

Reactive imports are Filament\Schemas\Components\Utilities\Get and Filament\Schemas\Components\Utilities\Set. Use live(onBlur: true) for text-derived suggestions/previews, live() only for selector/mode/default changes. Local-only focus/disclosure behavior may use the app's existing Alpine integration.

### Reusable schema owners — C09

| Full path | Change / responsibility |
| --- | --- |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Values/Schemas/ValueForm.php | Extract public fields(reservedIdentifiers, tagOptions) and batch(name, reservedIdentifiers, tagOptions) factories. configure delegates to fields. Factories return fresh components; existing create/edit routes keep current behavior. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Schemas/AttributeForm.php | Extract definitionFields(reservedKeys); compose a shared inline AttributeValueWorkspace in valuesSection. Keep canonical name/Key and staged additions in the same existing save. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/AttributeValueWorkspace.php — new | Static UI factory for paginated persisted Master selection, pending-Master selection, inline Master creation, and the association Code table. Takes explicit state access and stable owner ref; changes only staging state. Expose associationTable(name, extraColumns = [], extraFields = []) so the Configurator supplies its default cell without a context-switching universal layout. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Attributes/Schemas/AttributeBatchForm.php — new | Repeatable Attribute definitions, one root Master pool, and per-Attribute staged associations. Uses existing ValueForm/AttributeForm factories; supports an explicit schema extension for Configurator review. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Schemas/ConfiguratorAttributeCreationForm.php — new | Existing-Attribute selection, shared creation composition, local inclusion settings, and staged default column. Does not duplicate shared field definitions. |
| /Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Schemas/ConfiguratorValueTextFields.php — new | Local UI name/subtitle/link controls and preview, reused by the selected Value editor and ConfiguratorAttributeForm's nested local-Value form. |

These are proposed public signatures, not claims that the methods already exist. Type every PHP parameter/return and document list/array shapes. Private composer/helper method names remain local implementation choices.

```php
// App\Models\Value (extend the existing algorithm owner)
public static function suggestSemanticId(string $label, array $reserved = []): string;
// App\Filament\Resources\SuggestedIdentifier
public static function unique(string $label, Builder $query, string $column, array $reserved = []): string;
public static function fields(TextInput $label, TextInput $identifier, Closure $suggest, ?Closure $reservedIdentifiers = null): array;
// App\Filament\Resources\Values\Schemas\ValueForm
public static function fields(?Closure $reservedIdentifiers = null, array $tagOptions = []): array;
public static function batch(string $name, ?Closure $reservedIdentifiers = null, array $tagOptions = []): Group;
// App\Filament\Resources\Attributes\Schemas\AttributeForm
public static function definitionFields(?Closure $reservedKeys = null): array;
// App\Filament\Resources\AttributeValueWorkspace
public static function make(string $associationName, Closure $rootStatePath, Closure $ownerRef, ?Attribute $attribute = null, array $tagOptions = [], array $extraColumns = [], array $extraFields = []): Section;
public static function associationTable(string $name, array $extraColumns = [], array $extraFields = []): Repeater;
// App\Filament\Resources\Attributes\Schemas\AttributeBatchForm
public static function components(Closure $rootStatePath, array $tagOptions = [], ?Closure $workspaceFactory = null): array;
// App\Filament\Resources\Configurators\Schemas\ConfiguratorAttributeCreationForm
public static function configure(Schema $schema, Configurator $owner, array $tagOptions = []): Schema;
// App\Filament\Resources\Configurators\Schemas\ConfiguratorValueTextFields
public static function fields(Closure $masterName): array;
```

Use `Closure`, `Illuminate\Database\Eloquent\Builder`, the native classes in C14, `Filament\Schemas\Schema`, `App\Models\Attribute` and `App\Models\Configurator` imports. `$reserved` is `list<string>`. The identifier reservation providers are `Closure(Get): list<string>` evaluated for the current row at suggestion/regeneration, excluding that row's ref; `$suggest` is `Closure(string, list<string>): string`. Keep three-argument SuggestedIdentifier callers compatible, updating its two current factory callers to use the optional reservation input and the existing algorithm owners. Do not create another slug algorithm.

`$tagOptions` is an authorized request-local `list<string>`, from `array_values(Value::tagOptions())`; outer owners read it once. `configure(Schema): Schema` remains unchanged for existing resource forms and supplies its vocabulary. `$rootStatePath` is `Closure(): string`, captured lazily from the actual outer Schema, not a fixed Action index. `$ownerRef` is `Closure(Get): string`; `$attribute` is a trusted persisted shared owner for exclusions or null for new creation. `$associationName` is `values`, legacy `pending_options`, or `new_values` as appropriate. `$extraColumns` is `list<Filament\Forms\Components\Repeater\TableColumn>` and `$extraFields` is `list<Filament\Schemas\Components\Component>`; return fresh native instances per schema.

`AttributeBatchForm::components` returns exactly one root pending pool plus the native Attribute Repeater; it owns the pool when used inside Configurator Add Attributes too, never duplicates it per tab/row. An optional `Closure(): Section` workspaceFactory returns fresh workspace composition with the Configurator's extra default/local cells; null uses shared-Attribute composition. Direct existing Attribute editors and Add Values each declare their one root pool in their outer schema. `ConfiguratorValueTextFields` uses `Closure(Get): string` for the current authoritative Master name, through the owner's request-local map; no per-row hidden queries or client-supplied label.

The two list Action factories expose `make(): Filament\Actions\Action`; Configurator AddAttributesAction exposes `make(Configurator $owner): Action`; AddValuesAction exposes `make(ConfiguratorAttribute $owner): Action`. Treat these model arguments as server-resolved context and requery scope/status at save. Use application collaborator parameter names such as `$createMasterValues`, never the reserved utility name `$operation`.

Use existing schema factories as legitimate UI owners. The application operations must not import these classes, query a resource for validation rules, or depend on a mounted Livewire component.

### Nested field model and creation context — C10 / CA09

The reused editor's creation context is explicit even inside a parent edit action. Do not infer it solely from the outer operation name or use a nested field's inherited parent record as a unique-rule exception. Installed Filament unique() infers its table from the schema model and can ignore its bound record by default; those defaults are unsuitable for new Master/Attribute rows inside a Configurator edit form.

- New Master ID fields use unique(table: 'values', column: 'semantic_id', ignoreRecord: false).
- New Attribute Key fields use unique(table: 'attributes', column: 'key', ignoreRecord: false), retaining the existing manual-Key validation rather than adding a new format restriction.
- New association Code fields retain unique(table: 'options', column: 'code', ignoreRecord: false), exact case, and the existing two-character rules.
- An existing single-record Master edit may ignore only the actual trusted Value being edited, passed deliberately by its owning resource/page. It must not ignore an Attribute/Configurator whose numeric ID happens to match a Value. No client-supplied ignore ID is accepted.
- Prefer native explicit creation boundaries: set model(Value::class) or model(Attribute::class) on the new-row Repeater or an inner Group, and operation('create') on its child Schema. An unbound Repeater resets cloned row-schema model to null, so setting only that row Schema model is insufficient. Scope this to new canonical rows, not the whole mixed Configurator review. SuggestedIdentifier can read the native create operation; do not thread an additional creationContext flag throughout factories unless the mounted blur/suffix proof demonstrates a need. Preserve the genuine model/record/edit context of existing shared-record edits.

These UI rules provide early feedback. The application operations and existing canonical writers remain authoritative for duplicate validation and database races. Task 1 adds the context-aware factory contract; Tasks 2, 4 prove it through each mounted nested action.

### Nested state and validation controls — C11 / CA10–CA15

- CA10: The actual owning Schema supplies the runtime root state path and the workspace supplies its stable owner ref. Use relative Get/Set for siblings and explicit absolute paths for root-pool access. Keep raw keyed UI/helper state on switches; project lists only for the operation and retain original error positions.
- CA11: Programmatic identifier, subtitle link/unlink and staged-default changes apply their complete mode/helper/ref transition explicitly. Set does not call update hooks by default; do not globally enable parent bubbling or rely on a typing hook to finish a programmatic transition.
- CA12: Query arguments of the embedded child are Locked, not reactive. Apply the focused key/remount proof above before selecting an adapter. Stable native selection and fresh operation-level membership checks remain required.
- CA13: Create and use is a staging action with explicit authorization and native composer-only validation. Use the actual composer child Schema with shouldCallHooksBefore: false, or native Repeater::getItemState($uuid) for its populated rows; do not use outer getStateOnly/getValidatedData as a partial validator. Other Attributes may remain incomplete. No writer runs until outer Save, which still validates the whole graph and rejects nonempty unstaged composers.
- CA14: Retain the already specified native discard footer and disabled passive exits. No catalog-creation-drafts.js or global close capture module is required. Keep editing must not cancel the parent; never set cancelParentActionsOnClose on the confirmation. Configure conditional modal opening when headings/descriptions could otherwise force an empty-draft confirmation.
- CA15: Use nonreserved capability names such as createMasterValues for typed application collaborators in Filament closures. Framework names such as operation, component, state, record and schema belong to their documented utilities. No new DI/binding layer.

### First connected lifecycle proof — C16

At the **start of Task 2**, after Task 1 fields exist and before broad shared/Configurator composition, build the smallest real native Action with two asymmetric Attribute drafts and the nested Master composer. Source inspection or prototype behavior does not pass this gate.

1. Blur and suffix regeneration see native new-row model/create context. A coincident-ID parent cannot become a unique exception; a real existing Value can retain its own ID.
2. Valid composer staging succeeds while another Attribute's Label/Code is incomplete. It creates zero rows and retains manual-cleared IDs/helper/ref state. Composer errors target its own fields, not unrelated required fields. Final Save still rejects participating unfinished state.
3. A blank/deleted earlier row and invalid later row retain their original native error/input identity after switch and failure.
4. A mounted picker sees stage/remove exclusion changes and A → B → A owner switches. Native selection/search/page/column changes do not remount it. Verify same-request leaf-key caching before a custom wrapper override.
5. Empty cancel closes directly; meaningful dirty state, including stashes/composers, opens native confirmation. Keep editing preserves it; Discard clears only this parent batch. Reopen/failure/focus/resize behave as specified; passive exits stay disabled.
6. The same pending Master reused twice inserts once at final Save; late failure rolls back everything. Actual mounted callback collaborator injection works.
7. Record active picker count, deepest path and request size with ten Masters and several Attributes. Do not raise Livewire guards automatically.

Retain Action ownership if these cases pass with the documented native boundaries. If supported configuration still needs disproportionate addressing/lifecycle glue, compare a **same existing host** named creation/composer Schema against these identical cases. That fallback has one `creationData` authority, native Action modalContent with no duplicated editable Action schema and no nested HTML form, explicit fill-once/begin, authorization-before-validation, submit and success/discard reset. Action unmount does not clear named state automatically. Reconcile the affected interfaces in this specification before adopting it. Do not build both full workspaces or silently add a new child/Page. A genuinely changed UX, state authority or unresolved domain conflict requires user clarification.

### Scoped frontend extensions and exact file map — C12

Keep native Filament fields, Actions, table selection, and repeater item lifecycle. These additions handle presentation and input targeting only; they do not duplicate record data in a JavaScript store.

| Full path | Exact responsibility |
| --- | --- |
| /Users/studioycm/Herd/configurator/resources/js/master-value-batch.js — new | Export masterValueBatch(config), an Alpine presentation/focus component. Own only optional-column booleans, preference subscription, and an ephemeral next-focus target. Use browserProfile from admin-profile.js with user + catalog-create:<context>:master-columns; context is the stable host/workflow kind, not a record ID, row UUID or active Attribute; allowlist tags/specification booleans. No labels, identifiers, record drafts, or selection IDs in browser preferences. |
| /Users/studioycm/Herd/configurator/resources/views/filament/resources/master-value-batch-columns.blade.php — new | The Columns dropdown using native Filament dropdown/button/checkbox Blade components, with Tags, Specification, and Reset columns. It shares the surrounding Alpine presentation component. No separate Livewire child or query. |
| /Users/studioycm/Herd/configurator/resources/js/workspace-table-selection.js — new | Export workspaceTableSelection(), with one delegated toggle(event) method. Locate only a native record row (.fi-ta-row or .fi-ta-record) inside the owning selector wrapper, then invoke its enabled .fi-ta-record-checkbox via click(). Skip nested interactive targets/labels, disabled controls, text selection, nonprimary/repeated click events, and nested unrelated tables. Let native selection state and fi-selected styling update; do not mutate IDs or checkbox.checked directly. |
| /Users/studioycm/Herd/configurator/app/Filament/Forms/Components/WorkspaceTableSelect.php — modify | Add a chainable selectRowsByClick(bool condition = true): static UI option, default false. Opt in the scoped creation/addition pickers with ->selectRowsByClick(). Merge data-workspace-row-selection, x-data workspaceTableSelection(), and x-on:click toggle($event) only into the existing wrapper when enabled; retain existing bindings/attributes and installed mount properties. The handler also checks the native checkbox's effective disabled state. |
| /Users/studioycm/Herd/configurator/resources/js/app.js — modify | Import/register both Alpine components through the existing alpine:init and already-initialized branches. Preserve other registrations. |
| /Users/studioycm/Herd/configurator/resources/css/filament/admin/theme.css — modify | Only scoped Master batch geometry, column visibility, and container-width stacking. Reuse native field/action colors and states. No global table/repeater rules or change to ordinary single-record editors. |
| /Users/studioycm/Herd/configurator/tests/Unit/MasterValueBatch.test.js — new | Test preference allowlisting, keyboard event exclusions, and focus-target decisions. Use the existing Node test conventions. |
| /Users/studioycm/Herd/configurator/tests/Unit/WorkspaceTableSelection.test.js — new | Test event targeting and exclusions, exactly one invocation of the native checkbox, and no second selection state. Browser acceptance separately proves native Alpine/Livewire integration. |

`ValueForm::batch()` returns one full-span Filament\Schemas\Components\Group containing the Columns View and a native Filament\Forms\Components\Repeater. Attach Add 5/Add 10 as native Filament\Actions\Action objects through that repeater's hintActions(), explicitly bound with schemaComponent($repeater); their callback receives the owning repeater as its named `component` parameter. Do not rely on a sibling Actions component to inject an unrelated Repeater. Use table() with four TableColumns in the fixed order UI Name, ID Name, Tags, Specification; defaultItems(1), maxItems(500), reorderable(false), cloneable(false), and the normal Add row/delete actions. Keep ordinary native input borders/backgrounds; compact() is omitted per the approved Task 1 visual correction. Keep helper Hidden fields before visible fields, so the installed renderer does not omit trailing helpers after its four-column counter is exhausted. The Column controls do not use hidden()/visible()/disabled() on those four fields.

Mark the Group data-master-value-batch and the field wrappers data-batch-field=label|semantic_id|tags|description; bind data-show-tags and data-show-specification from Alpine. Scoped CSS hides header/cells 3 or 4 together when deselected; all fields remain mounted. Because reordering is disabled and there are exactly four visible components, these cell positions are stable. In a narrow container, stack selected cells with their native labels; keep headers accessible and prevent overflow. Add a rendered-markup/browser regression case for this small dependency on the installed native repeater structure. Do not publish or copy its vendor template.

Add row/Add 5/Add 10 stage native repeater state only. Use one private appendRows(Repeater component, int count): void helper in ValueForm for counts 1, 5, and 10, called from the configured addAction() and the two bound hint Actions. Authorize before changing state; reject another count or a request exceeding 500 total rows, and disable Add 5/Add 10 when fewer than that number remain. Do not silently append a partial quantity. Match installed Repeater::getAddAction(): obtain getRawState(), append keys from generateUuid(), set rawState($items), fill each newly created getChildSchema($uuid), set its explicit ref, then callAfterStateUpdated() once and preserve the native partial-render behavior. Never pass through dehydrated/reindexed lists to append rows, and do not refill existing child schemas. This is a narrow UI helper, not an application writer or a new state class. Focus the first appended name after the corresponding morph. At the last UI Name, Enter invokes that same configured native Add row action; it does not append a client-only record. Wait for the normal blur/suggestion request before moving on, without synthesizing duplicate blur updates. Use absolute field paths plus stable refs for focus targets and ignore IME, Tags, textarea, suggestion-popup, or modified shortcut events as specified in section 2.

Subscribe only to the owning Livewire component's successful message/morph boundary using the existing localTableProfile pattern. When native data-validation-error markup occurs in a CSS-hidden optional field, reveal that column, then focus the first affected native input after layout updates. Do the same after mapped operation errors. Preserve chosen visibility through ordinary morphs and resizing, and unregister subscriptions on Alpine destroy. A validation-driven reveal becomes the current explicit visible state, so the field remains available for correction.

Every consumed picker opts into selectRowsByClick(): the Master chooser in AttributeValueWorkspace, the existing-Attribute chooser, and the existing shared-Value chooser. “New in this batch” remains a native searchable CheckboxList with a wrapping label that covers its row. Do not add another paginated database table for transient refs. Whole-row selection uses existing selection bindings/limits and performs no request solely to duplicate checkbox state.

### Focused Livewire 4 compatibility contract — C13

- Keep WorkspaceTablePicker's inherited single `#[Modelable] state` and the parent's existing applyStateBindingModifiers('wire:model') mount binding. Livewire 4 supports that child-property binding; do not add .deep or a second public selection array to solve a folder-version concern. .deep addresses delegated descendant DOM input events, a different situation. Leave native Filament input binding modifiers in charge of on-blur name/ID updates.
- Reuse the app's component-ID-filtered Livewire.interceptMessage → onSuccess → onMorphed pattern for preference restoration, focus and error-column reveal. This API and the interceptor cleanup are present in the installed Livewire 4.4.7 bundle. Do not introduce a copied Livewire 3 message.processed hook, or register duplicate listeners after each morph.
- Keep data/ref-derived picker keys stable across ordinary selection, search, page and column changes. A real owner/query-exclusion/disabled-limit context change must refresh the child's locked inputs: deliberate unmount/reopen or public context-sensitive leaf keying is preferred. Verify same-request key caching through the actual picker before adding a narrow wrapper override; pickerContext/getLivewireKey customization is not mandatory by default. Remount only the affected child, seeding its sole Modelable selection from the outer scalar draft. Selection never comes from untrusted display labels.
- Acceptance must exercise multiple consecutive blur/append requests, picker parent-child roundtrips, page/search changes, switching Attributes and returning, and cancellation/reopening. Source inspection confirms the API choices; only actual app component/browser tests can confirm the implemented behavior. No wholesale Livewire upgrade or package change is part of this feature.

### Fields — C14

| Field | Component | Docs | Validation | Config |
| --- | --- | --- | --- | --- |
| Master UI Name | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required for a populated row; string, max:255 | label UI Name; first field; live(onBlur: true); existing SuggestedIdentifier helper |
| Master ID Name | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required for populated row; max:100; regex \A[a-z0-9]+(?:_[a-z0-9]+)*\z; unique values.semantic_id | editable; existing explicit regenerate suffix; never refill a manually cleared ID on submit |
| Tags | Filament\Forms\Components\TagsInput | [Tags](https://filamentphp.com/docs/5.x/forms/tags-input) | array/list, max:30; nonblank distinct strings max:80, existing trimming behavior | default([]), suggestions from one request-local vocabulary read; full-span in the ordinary form, optional column in the batch editor; hiding never excludes validation/submission |
| Specification | Filament\Forms\Components\Textarea | [Textarea](https://filamentphp.com/docs/5.x/forms/textarea) | nullable string, max:5000 | rows(3), full-span in the ordinary form, optional column in the batch editor; hiding never excludes validation/submission |
| Attribute Label | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required for populated row, string max:255 | first field; live(onBlur: true); SuggestedIdentifier helper |
| Attribute Key | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required for populated row, string max:100, unique attributes.key | editable suggested lowercase/underscore Key. Retain current manual-Key validation, including legacy hyphenated keys; no new blanket regex |
| Master/Attribute entries | Filament\Forms\Components\Repeater | [Repeater](https://filamentphp.com/docs/5.x/forms/repeater) | list; raw max:500; operation-specific populated minimum in C06; validate participating populated rows | defaultItems(1), addActionLabel with resource name; stable refs; no relationship(); Master entries never collapsed |
| Persisted selection | App\Filament\Forms\Components\WorkspaceTableSelect | [Table selection](https://filamentphp.com/docs/5.x/forms/select#selecting-options-from-a-table-in-a-modal) and existing embedded source | array of distinct integer IDs; authoritative scope/status checks at save | multiple(), live(), tableConfiguration(existing selection class), tableArguments(owner/exclusions). No relationship(). Preserve 10/25/50 pagination |
| New in this batch | Filament\Forms\Components\CheckboxList | [Checkbox list](https://filamentphp.com/docs/5.x/forms/checkbox-list) | distinct refs belonging to the root Master pool | options only from staged Values, searchable(), live(); separate from persisted-ID picker |
| Association rows | Filament\Forms\Components\Repeater and Filament\Forms\Components\Repeater\TableColumn | [Table repeater](https://filamentphp.com/docs/5.x/forms/repeater#table-repeaters) | ≤500 Values per Attribute; distinct Master per parent, distinct global Codes across batch | table(UI Name, Code, plus Configurator-supplied Default), addable(false), reorderable(false), compact(); no relationship() |
| Association UI Name | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | display-only | readOnly(), dehydrated(false); current Master name and ID Name; badge for staged/new |
| Code | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | required, exactly 2 ASCII alphanumeric chars, case-sensitive globally unique | length(2), trim(false), regex('/\A[A-Za-z0-9]{2}\z/D'); never generate, uppercase, or trim |
| Stable ref / persisted ID / default_ref | Filament\Forms\Components\Hidden | [Hidden](https://filamentphp.com/docs/5.x/forms/hidden) | ref syntax/membership and integer ownership; default_ref required for each selected new inclusion | serialize intent only; hidden is not a trust boundary |
| Local value UI name | Filament\Forms\Components\TextInput | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | nullable string, max:255 | existing label_override, live(onBlur: true); blank inherits main name |
| Local value subtitle | Filament\Forms\Components\TextInput plus Hidden mode/override state | [Text input](https://filamentphp.com/docs/5.x/forms/text-input) | mode enum; Custom requires nonblank max:255 | UI proxy _subtitle_text is dehydrated(false), live(onBlur: true), readOnly in Master mode; derive stored mode/text explicitly rather than dehydrating the visible linked name |
| Value preview | Filament\Schemas\Components\View | [Custom components](https://filamentphp.com/docs/5.x/schemas/custom-components) | no submitted value | escaped Blade view directly below name/subtitle, before hint; resolves current mode/name without writes |
| Local hint / flags | Filament\Forms\Components\TextInput and Filament\Forms\Components\Toggle | [Text input](https://filamentphp.com/docs/5.x/forms/text-input), [Toggle](https://filamentphp.com/docs/5.x/forms/toggle) | hint nullable string max:1000; flags boolean | retain current field types/constraints; move hint after preview |

Apply C06's shared presence predicate and operation-specific minimums. Conditional schema requirements and application normalization must agree; blank appended rows do not trigger required errors, but a nonempty incomplete row blocks its participating submission. Enforce the raw-row cap before skipping blanks. Ten records is the primary acceptance workload, not the batch maximum.

Identifier suggestions retain today's **fill once**, rather than continuously following name changes. Use the native new-row model/create-operation boundaries above so the existing helper receives creation context even inside an edit page or generic Action; verify ordinary blur and suffix Actions before adding a custom context callback. Pass other pending IDs/Keys to suggestion collision checks, excluding the current ref; persisted and pending collisions generate _2, _3 while respecting max:100. Existing collision fallback remains value for Master Values and attribute for Attributes. Manual duplicates are errors, not silently repaired. Scope suggestion flash/events by absolute field state path and Livewire ID so only the intended repeater row flashes.

### Columns and actions — C15

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
| Inline choose/create/use/remove | AttributeValueWorkspace, authorized manager | 1. One Add Values entry opens the inline workspace. 2. Native Existing Master Values and New Master Values tabs separate selection from entry, preserving both drafts. 3. Put Add selected Values immediately below the picker/pending chooser; put Add new Values and secondary Clear entries immediately below the composer. 4. Stage refs and Codes only; retain one outer save. Confirm Clear entries for nonempty composer data; clear only that composer. Hide empty pool/chooser copy. Do not place all four actions in one global toolbar. |
| regenerateIdentifier | Identifier suffix, explicit creation context only | 1. Authorize. 2. Require nonblank name. 3. Recompute against persisted plus pending reservations for this row. No write. Existing-record edit retains no regenerate suffix. |
| setDefault | Saved Default cell, authorized manager | 1. Freshly scope clicked local Value to this owner. 2. Patch only default_configurator_option_id through the aggregate writer. 3. Refresh default indicators and persisted baseline metadata, preserving all unrelated drafts. No-op for current default. |
| linkSubtitle / unlinkSubtitle | Subtitle suffix, authorized manager | 1. Resolve the selected Value's actual Master. 2. Switch transient mode and visible proxy as specified above. 3. Save only through the local Value save operation. No canonical rename or immediate write. |
| discardDraft | Outer slide-over footer, visible while action is open | 1. If populated draft differs from its initial state, open native confirmation. 2. Keep editing returns to identical state. 3. Discard closes/cancels parent action and clears only its staged batch. Empty draft closes directly. |

Outer creation actions: slideOver(), stickyModalFooter(), databaseTransaction(false) because the application operation owns the transaction. Use the existing admin width preferences. Keep a labeled Create/Add button instead of an icon-only plus.

For explicit cancellation, set modalCancelAction(false), modalCloseButton(false), closeModalByClickingAway(false), closeModalByEscaping(false); supply discardDraft through extraModalFooterActions(), requiresConfirmation(condition), and cancelParentActions(parentName). This avoids unguarded close paths while preserving native confirmation/focus behavior. Confirmation labels are Discard and Keep editing. Navigation/unload protection must cover the mounted action too; do not assume unsavedChangesAlert alone confirms modal cancellation.

Mount only the currently opened inline picker and Master composer; preserve inactive drafts in outer scalar state. Do not render ten paginated Livewire selector children for ten collapsed/idle Attributes. Use stable owner/ref component keys; pending refs never enter an Eloquent table query.

## 5. Implementation tasks and acceptance gates

The sequence follows actual dependencies: Master Values → shared Attributes and native lifecycle proof → subtitle storage/transports/existing-editor fields → Configurator creation/addition → table defaults. Configurator creation must not submit subtitle_mode before the compiler and writers support it. Each task is reviewable independently. After the user authorizes the complete feature implementation, proceed through these dependent tasks without repeated phase approvals unless scope changes.

### Task 1 — Master Value batch creation and reusable identifiers

**Create:** CreateMasterValues and Values/Actions/CreateValuesAction.php listed above.

**Modify:** [ValueForm](/Users/studioycm/Herd/configurator/app/Filament/Resources/Values/Schemas/ValueForm.php), [SuggestedIdentifier](/Users/studioycm/Herd/configurator/app/Filament/Resources/SuggestedIdentifier.php), [Value](/Users/studioycm/Herd/configurator/app/Models/Value.php), [ListValues](/Users/studioycm/Herd/configurator/app/Filament/Resources/Values/Pages/ListValues.php), lang/en/catalog.php, lang/en/admin-ux.php, matching existing locale overrides where applicable.

- [ ] Add behavioral coverage in /Users/studioycm/Herd/configurator/tests/Feature/MasterValueCreationBatchTest.php; extend SuggestedIdentifiersTest and only the Master Value cases in SplitListCreationTest.
- [ ] Extract/reuse fields, support pending collision reservations and explicit creation context, and implement the transaction/error adapter. Include the batch refinement above: aligned columns, explicit optional-field visibility, Add 5/Add 10, and UI-Name-to-UI-Name entry.
- [ ] Set ListValues.inlineCreationEnabled false and prepend the labeled action by overriding makeTable() using the parent-configured table. Do not alter generic SplitListRecords or the other resources' creation behavior. Retain direct CreateValue.
- [ ] Prove 10 entered Values save with exact labels/IDs/tags/specification. Add manual-ID, manual-clear, duplicate row, persisted collision, 100-character suffix, empty-row, and later-row-error cases. Assert zero partial rows and no success notification on failure.
- [ ] Through mounted action interactions, prove regeneration affects just one row and action failure retains its entire state. An open existing editor remains selected and unchanged after successful batch creation.
- [ ] Implement CA09's explicit uniqueness/creation context. Preserve unchanged actual Value edits; test a new nested Master with an existing semantic ID under a parent edit whose numeric ID matches that Value. It must fail on the correct draft row, with no parent-table query/ignore exception or persisted row. Keep helper flags out of operation payloads without losing them after validation/remount.
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
- [ ] Complete the first connected native lifecycle proof above before broad composition: two asymmetric drafts, native new-row context, composer-only validation, original-index error retention, picker exclusion changes, and native discard/reopen. Include real callback collaborator injection. Retain the standard Action root unless the same-owner named schema demonstrates a concrete reduction in lifecycle work.
- [ ] Resolve the pool once before saving multiple Attributes. Extend saveDefinition's optional pool input for existing/direct Attribute editors; remove transport-only state before canonical validation, and clear pending UI state only after success.
- [ ] Replace the Attributes list plus/automatic create pane only, retaining its edit pane and direct route. Keep the existing shared Values table and table controls.
- [ ] Test an Attribute with no Values; 10 Attributes; existing Master selection; one pending Master reused in two Attributes with distinct Codes; exact Aa versus aA; forbidden whitespace/punctuation/non-ASCII/1- or 3-character Codes; global duplicate Codes; duplicate pair; duplicate Key; and cross-Attribute late failure.
- [ ] Assert exact identities and labels after reload, not just counts. A duplicate Code on the final Attribute rolls back every preceding Attribute, new Master, and association while preserving UI entries. Existing shared editor definition-plus-additions remains atomic.
- [ ] Opt persisted and pending Master choosers into the full-row contract. Test the persisted selector across search, page changes, and remounting; test one click/one toggle and disabled/loading limits in Chrome. Supply the complete pending pool to CreateMasterValues with the referenced-ref set, retaining original index mapping for a nonfirst failing row.
- [ ] Exercise nested Master ID validation inside both new and existing Attribute actions, plus explicit new-Attribute Key uniqueness. An unrelated bound record must neither disable creation suggestions nor become the ignored unique record. Direct operations reject unknown nested keys; UI callbacks submit only the documented domain shape.

**Gate:** the same Master editor is demonstrably reused, with no nested selection modal and no default controls in shared Attribute creation.

### Task 3 — Explicit local subtitle intent, editor layout, and Rules compatibility

**Create:** the enum above, generated add_subtitle_mode_to_configurator_options_table migration, ConfiguratorValueTextFields, and /Users/studioycm/Herd/configurator/resources/views/filament/resources/configurator-value-preview.blade.php.

**Modify:** [ConfiguratorOption](/Users/studioycm/Herd/configurator/app/Models/ConfiguratorOption.php), [ConfiguratorOptionFactory](/Users/studioycm/Herd/configurator/database/factories/ConfiguratorOptionFactory.php), ConfiguratorInclusionDrafts, ConfiguratorDefinitionLoader, ConfiguratorDefinitionCompiler, SaveConfiguratorDefinition, [SaveConfiguratorOption](/Users/studioycm/Herd/configurator/app/Actions/SaveConfiguratorOption.php), ConfiguratorAttributeForm, AttributesRelationManager, OptionsRelationManager, [ConfiguratorEditorChanges](/Users/studioycm/Herd/configurator/app/Services/ConfiguratorEditorChanges.php), [BatchCatalogChanges](/Users/studioycm/Herd/configurator/app/Actions/BatchCatalogChanges.php), [configuration-fields Blade](/Users/studioycm/Herd/configurator/resources/views/components/catalog/configuration-fields.blade.php), and relevant Value-specific translation keys in lang/en/configurator.php and lang/en/catalog.php.

- [ ] Add /Users/studioycm/Herd/configurator/tests/Feature/ConfiguratorSubtitleTest.php, including migration upgrade fixtures; extend compiler, workspace, editor-change, batch-edit, and runtime integration tests at their existing owners.
- [ ] Add the column/backfill and pure enum resolver. Preserve custom text, set historical blanks to None, and apply the same normalization to missing-mode legacy drafts and newly staged Values.
- [ ] Carry mode through every loader/compiler strict-key list and normalized result, writer allowlist, inclusion draft, local form state, dirty comparison, signed editor capture, tested-change save, duplication roundtrip, and batch-edit state/token. Do not persist preview/proxy/helper fields.
- [ ] Retain SaveConfiguratorOption's partial-patch semantics: absent subtitle fields preserve mode/text; explicit clear selects None. In batch edits, entered text selects Custom, cleared text selects None, and an unchanged subtitle preserves Master mode. Validate mode/text jointly; do not leave a linked mode with ignored edited custom text.
- [ ] Implement the agreed field order and link controls. Use one escaped live preview beneath name/subtitle. Display a current Master name as a read-only proxy, never as copied custom data; exclude proxy/resolved-name values from dirtiness and signed payloads.
- [ ] Update only Value choice/selected-value runtime template conditions to require nonblank subtitle AND subtitle unequal to the final main label. Preserve the separate Attribute-level display-text path. Keep ConfiguratorOptionDTO.displayValue, runtime display_value, rule SetDisplayValue, rule import/export headers, and rule priority/tie semantics intact. Value-specific rule labels/help may say subtitle; do not globally rename Attribute-level “display value” wording.
- [ ] Test None/Custom/Master after save and reload, unchanged save, UI link/unlink restoration, explicit clear, missing/invalid mode, whitespace-only input, the valid subtitle "0", legacy nonblank text, and historical blank migration. Test two Values sharing a Master: one local name plus Master-linked subtitle and one Custom subtitle.
- [ ] Rename that Master through SaveCanonicalDefinition, reload/evaluate through ConfiguratorDefinitionLoader, and assert the linked subtitle changes, the local main name stays, and the Custom subtitle stays. Add matching/nonmatching Rules for SetLabel and SetDisplayValue; assert subtitle suppression uses the final label and no empty second-line element renders for select or toggle inputs.
- [ ] Run Preview & Test with captured pending local edits, save tested changes, and verify mode survives. Verify a signed capture containing a forged/invalid mode is rejected and an unchanged linked row does not become dirty just because its Master name changed.

**Gate:** blank now consistently means no subtitle, including preexisting rows; explicit links remain live; local and Rules-based presentation remain independently editable.

**Preview-base compatibility for Task 3:** Also update /Users/studioycm/.codex/worktrees/catalog-creation/configurator/resources/views/livewire/catalog/configurator-preview-values.blade.php so Value subtitles in All states require nonblank text and differ from the final label; preserve Attribute-level display text. Carry mode through ConfiguratorEditorChanges::inspect/prepare/captureHash/savedRows and inspect their existing signatures before editing. Preserve operationId-based repeat-safe acceptPreviewSave acknowledgements, immutable captures and stable previewView. Extend ConfiguratorPreviewSnapshotTest, ConfiguratorPreviewPresentationTest, ConfiguratorPreviewReconciliationTest and relevant ConfiguratorPreviewInspectionTest cases where these paths prove subtitle promises. No replacement preview state or acknowledgement protocol is introduced.

### Task 4 — Configurator Add Attributes and Add Values

**Create:** AddConfiguratorAttributes, AddConfiguratorValues, ConfiguratorAttributeCreationForm, AddAttributesAction, and AddValuesAction listed above.

**Modify:** [SaveConfiguratorDefinition](/Users/studioycm/Herd/configurator/app/Actions/SaveConfiguratorDefinition.php), both Configurator relation managers, [ConfiguratorAttributeForm](/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/Schemas/ConfiguratorAttributeForm.php), [ConfiguratorInclusionDrafts](/Users/studioycm/Herd/configurator/app/Services/ConfiguratorInclusionDrafts.php), the three existing selection-table classes only where needed, lang/en/configurator.php.

- [ ] Add /Users/studioycm/Herd/configurator/tests/Feature/ConfiguratorCreationFlowTest.php; extend ConfiguratorWorkspaceTest and relevant Catalog/MySql/ConfiguratorIntegrityTest cases.
- [ ] Establish the writer lock prefix before any new shared writes. Add fresh-owner additive operations and map pending refs to real IDs; no UI-only fragment may be passed as a replacement full definition.
- [ ] Compose existing selection and new creation in Add Attributes. Retain deselected review stashes; require an explicit default from the chosen Values for every inclusion, including one-Value Attributes. Keep the compiler's existing legacy auto-first behavior for unrelated callers, enforcing the new action boundary explicitly. Deliver the staged Default column/default_ref actions in this task, including removal clearing and required reselection; otherwise this task cannot pass its complete creation gate. These actions change draft state only.
- [ ] Extend Add Values with existing shared selection and new associations/Masters; make its empty-results state useful. Exclude already included/attached records appropriately while keeping new creation available.
- [ ] Test selected existing plus newly created Attributes in one save; multiple new Attributes sharing one pending Master; selected existing plus newly created Values; a missing default; a default ref from another Attribute; a forged inclusion/owner; inactive/hidden records changed after selection; duplicate inclusion; final Code conflict; and unauthorized direct operation/action submission.
- [ ] Assert rollback of all canonical/local rows on late failure. Assert current defaults, rules, mappings, order, and unrelated local drafts are preserved on success and failure. Reopen the action and include a later shared Value without resetting the current default.
- [ ] For all newly mounted actions, test Discard versus Keep editing and tab/Attribute switching. On validation failure, retain native picker selection, pending Master refs, Codes, defaults, and nested composer data.
- [ ] Run the focused Livewire 4 contract above through component/browser interactions. Verify the embedded Modelable selection survives parent morphs and picker remounts, and preference/focus subscriptions are cleaned up rather than duplicated.
- [ ] Opt existing-Attribute/shared-Value choosers into whole-row selection. Clicking their name, Key, Code, or row whitespace toggles selection; clicking their native checkbox toggles once. A default action or another interactive control never changes picker selection. Recheck IDs/status/ownership in operations even when picker state is native.
- [ ] Repeat CA09 through the actual Configurator Add Attributes and Add Values actions: persisted duplicate Master IDs/Attribute Keys/Codes remain errors despite the outer edit model; valid new rows succeed and keep their creation-only suggestion controls. Preserve the existing eligibility rules for repair of inactive inclusions.

**Gate:** the complete “new Master → new Attribute Value with Code → new/shared Attribute → Configurator inclusion with chosen default” flow saves atomically. Adding new Values remains available when every existing Value is included.

### Task 5 — Default selection in the table

**Modify:** both Configurator relation managers, ConfiguratorAttributeForm, ConfiguratorAttributeCreationForm, and ConfiguratorWorkspaceTest.

- [ ] Add the saved Default column actions using the component contracts above; reuse and regression-check the staged default controls already delivered in Task 4. The existing owning relation manager exposes `setDefault(int $id): void`, with `$id` the clicked local ConfiguratorOption ID, explicit manage-catalog authorization and fresh owner membership checks; its callback uses SaveConfiguratorDefinition::change and returns no model/UI data. Do not add a fifth application writer.
- [ ] Remove the default dropdown from the current Configurator workspace editor/review where the column replaces it. Update reviewComponents' current array_pop assumption; do not accidentally move or drop a different field. Retain the legacy standalone form's valid default input where no replacement table is present.
- [ ] Strip default_configurator_option_id from this workspace's general settings form state/dirty hash and ordinary Save Attribute patch. Treat the default as its own save boundary so a later settings save cannot restore a stale default. Apply the same workspace projection to `previewOriginals`, stashed Attribute drafts and exported Preview & Test changes; keep before/after capture shapes aligned. Do not globally remove legacy default support from ConfiguratorEditorChanges/standalone callers that still own a default field. Existing tested captures must retain their normal stale-conflict behavior rather than overwrite a newer table default.
- [ ] Save the default through a fresh aggregate patch, then reconcile only default metadata in the selected/stashed editor baselines. Do not call a full editor reload that discards drafts. A draft option's local name/subtitle/hint/flags remain untouched.
- [ ] Test “edit local name without saving → click another Default → switch row/back → save local name.” The new default and draft name both survive. Repeat with an unsaved Attribute setting and with a different Value selected in the editor.
- [ ] Test current-default no-op, Value outside owner rejection, and staged default removal requiring reselection. Retain current policy allowing a stored default that may be unavailable under runtime rules; runtime fallback is unchanged.

**Preview-base compatibility for Task 5:** Apply the ordinary-settings/default projection consistently to current and stashed state, previewOriginals and exported before/after captures. Keep global ATTRIBUTE_FIELDS/legacy default support and older immutable captures' existing stale-conflict behavior. A newer table default must survive both a later local settings save and Preview & Test reconciliation; operationId acknowledgement remains repeat-safe.

**Gate:** one clear visible default control in each target workspace; saved identity is the local inclusion ID, never a shared Option or Master ID.

### Task 6 — Connected browser review and bounded efficiency checks

- [ ] Use isolated approved test data for writes. Walk all three entry points and existing editors in the actual Filament app; the static prototype is not implementation evidence.
- [ ] At desktop, 1024px, and a narrow phone viewport, verify Tags/Specification follow explicit batch column choices and stay visible in ordinary edit forms, no unintended horizontal overflow, readable Codes/validation, preview placement, accessible Default/link actions, and native search/filter/Columns/reorder/action controls.
- [ ] Verify keyboard entry, adding/removing rows, error focus, Save/Cancel focus restoration, Discard/Keep editing, and continued editing after failed validation. For each picker type, verify whole-row selection, checkbox Space behavior, search/page/remount retention, disabled/max-selection rows, and independence from links/buttons/default controls.
- [ ] Measure opening an empty form, ten Master Values, several Attributes with staged Values, switching active pickers, and one submit. Record browser request count/payload and server query count separately; no numeric performance improvement is claimed before measurement.
- [ ] Check the actual deepest property path and effective request/component limits against the installed Livewire payload guards. Keep one flat Master pool and only active picker children mounted; do not raise guards automatically. Document any unverified maximum-batch behavior separately from the 10-row acceptance workload.
- [ ] Read tag vocabulary and record-label maps once per request/outer schema; eager-load only displayed relation data. Avoid per-row Attribute::find/Option::find in itemLabel callbacks and repeated full tag scans. Paginate persisted catalogs; do not preload the entire Value library or mount inactive picker children.
- [ ] Run the isolated MySQL integrity suite and exercise competing Code creation/inclusion and canonical edits from separate requests. Assert one conflict is reported cleanly, no partial graph persists, and owner defaults/drafts remain intact. Record concurrency behavior separately from SQLite results.
- [ ] Present the working app for review against section 2. Keep Groups and generalized layout refactors as later discussion.

### Task 7 — Post-implementation conformance and connected structure audit — C18

This is a required review of the eventual implementation, separately requested by the user. It is not complete now and does not authorize unrelated refactors.

- [ ] Freeze the implemented revision/dirty snapshot and this specification plus any explicit later amendments. Using reviewing-filament-plans, extract a complete requirement ledger with stable IDs, including behavior, mandated mechanisms and required tests; trace each to active registration, source and actual verification evidence. Keep process notes/exclusions separate from requirement counts.
- [ ] Using laraveldaily-structure-audit and laravel-development-architecture, trace related callers and dependencies beyond the changed-file list wherever they can affect a feature promise. Include services, operations, enums, helpers, models, validation, factories, compiler/loader/DTO and signed-capture transports, lock/revision owners, selectors, component state, runtime rendering and tests. Expand by real dependency, without an arbitrary depth/file cap or an unrelated whole-app sweep.
- [ ] Record both sound boundaries to retain and justified corrections. Preserve CA01–CA15 lineage, including CA14's superseded custom-guard claim; distinguish reproduced defects, source mismatches, supported risks, optional organization and unresolved evidence. Propose a Service, enum, DTO, trait or extraction only for a demonstrated responsibility/value boundary; line count or class suffix is insufficient.
- [ ] Publish the complete conformance ledger at /Users/studioycm/Herd/configurator/docs/catalog/MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_REVIEW.md and the connected architecture report at /Users/studioycm/Herd/configurator/docs/catalog/MASTER_VALUES_ATTRIBUTES_CREATION_POST_IMPLEMENTATION_STRUCTURE_AUDIT.md. Every finding has a caller/trigger, exact owner/evidence, smallest correction, affected requirement IDs, verification, and disposition. Document inspected paths and any unresolved dependency frontier.
- [ ] Fix feature deviations within the authorized implementation scope, run the affected checks, then recheck the impacted lifecycle. Propose wider unrelated refactors separately. Do not claim full conformance with a missing/mismatched, blocked, unverified or unreviewed in-scope requirement. Show final evidence and any external verification limitations to the user.

**Gate:** all three implemented flows are accounted for against this specification, related responsibility boundaries are reviewed, and every prior/new finding has an explicit disposition. Source-only conclusions remain labelled source-only; a prototype or unrun test never substitutes for app evidence.

## 6. Commands and verification — C17

All commands below are for the later authorized implementation, from /Users/studioycm/Herd/configurator. Generator flags were checked with installed Artisan help; no generators or tests were run to prepare this specification.

Recheck AGENTS.md, .ai/rules if it has appeared, versions and git state before editing; reconcile current source with this specification. Historical audits are evidence, not additional unbounded implementation scope. Inspect attached worktrees and establish/reuse a suitable isolated implementation checkout if needed; do not mix the current unrelated changes into this feature.

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

Use existing Livewire\Livewire::test and Filament\Actions\Testing\TestAction helpers. There is no installed Pest Livewire/browser plugin to assume; no dependency installation is part of this specification. Follow the current test bootstrap/factory conventions.

Run the narrowest affected file during each task, then the connected group:

**Test environment prerequisite (CA08 / existing S07):** Before every SQLite Feature invocation, verify the effective configuration target using the same explicit environment as the test. Merely unsetting a variable is insufficient: dotenv may reload it from an environment file. Do not print connection URLs/passwords, remove the developer's config cache, or modify general test infrastructure here. Use a clean implementation/test checkout when needed.

Define this task-local shell function in the implementation session; it creates no project file and performs only bootstrap/config resolution before tests. The fixed task-specific cache path must not exist. A failed assertion stops before RefreshDatabase. Both database URL variables are explicitly empty so dotenv cannot reintroduce them; the explicit cache path prevents dotenv redirecting configuration cache. Do not run config:cache at this path during the task.

```sh
catalog_test() {
    local -a catalog_test_environment=(
        DB_URL= DATABASE_URL=
        APP_CONFIG_CACHE=/private/tmp/configurator-catalog-creation-testing-config.php
        APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory:
        CACHE_STORE=array MAIL_MAILER=array QUEUE_CONNECTION=sync SESSION_DRIVER=array
    )
    env "${catalog_test_environment[@]}" php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $cachePath = $app->getCachedConfigPath();
        if ($cachePath !== getenv("APP_CONFIG_CACHE") || is_file($cachePath)) {
            throw new RuntimeException("Unsafe test configuration cache target.");
        }
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $config = $app["config"];
        $connection = $config->get("database.default");
        $configured = $config->get("database.connections.".$connection);
        if (! $app->environment("testing") || $connection !== "sqlite"
            || ! is_array($configured) || ! empty($configured["url"])) {
            throw new RuntimeException("Unsafe configured SQLite test target.");
        }
        $resolved = (new Illuminate\Support\ConfigurationUrlParser)
            ->parseConfiguration($configured);
        if (($resolved["driver"] ?? null) !== "sqlite"
            || ($resolved["database"] ?? null) !== ":memory:"
            || is_file($app->getCachedConfigPath())
            || $app->getCachedConfigPath() !== $cachePath
            || $config->get("cache.default") !== "array"
            || $config->get("mail.default") !== "array"
            || $config->get("queue.default") !== "sync"
            || $config->get("session.driver") !== "array") {
            throw new RuntimeException("Unsafe resolved SQLite test target.");
        }
        echo "Verified testing/sqlite/:memory: with isolated transports.\n";
    ' || return
    env "${catalog_test_environment[@]}" "$@"
}
```

Read-only safety assertion expected output: `Verified testing/sqlite/:memory: with isolated transports.` Check the safety prefix/assertion after any test bootstrap change and use it on **every** later SQLite invocation, including the eventual full-suite request. The code above has been source-reviewed, not executed during this documentation task. The underlying S07 general guard remains separate work. MySQL uses the existing literal disposable-target guard/phpunit.mysql.xml instead; never run that suite through catalog_test.

    catalog_test php artisan test --compact tests/Feature/SuggestedIdentifiersTest.php tests/Feature/MasterValueCreationBatchTest.php tests/Feature/ValueSemanticIdTest.php
    catalog_test php artisan test --compact tests/Feature/AttributeValueBatchTest.php tests/Feature/SharedAttributeCreationBatchTest.php tests/Feature/Filament/SplitListCreationTest.php
    catalog_test php artisan test --compact tests/Feature/ConfiguratorCreationFlowTest.php tests/Feature/Filament/ConfiguratorWorkspaceTest.php tests/Feature/Filament/ConfiguratorAdministrationTest.php
    catalog_test php artisan test --compact tests/Feature/ConfiguratorSubtitleTest.php tests/Feature/ConfiguratorEditorChangesTest.php tests/Unit/ConfiguratorCompilerTest.php tests/Unit/OptionRulePresentationTest.php
    catalog_test php artisan test --compact tests/Feature/Catalog/ConfiguratorIntegrationTest.php tests/Feature/Catalog/ConfiguratorRuntimeTest.php
    vendor/bin/pest --configuration=phpunit.mysql.xml tests/Feature/Catalog/MySql/ConfiguratorIntegrityTest.php

MySQL prerequisite: the existing bootstrap requires the isolated configurator_catalog_test database and user on 127.0.0.1:3307 with APP_ENV=testing, and rejects URL/cached-config overrides. Respect its checks; never redirect this suite to the development database. If the isolated target is unavailable, report that check as unrun.

After PHP/Filament edits:

    vendor/bin/pint --dirty --format agent
    vendor/bin/filacheck --fix --dirty

Resolve any remaining FilaCheck violations; inspect the diff so these dirty-file tools do not sweep in unrelated work. This specification includes JS, Blade, and theme changes, so run the affected Node files during Task 1, then npm test and npm run build before connected browser acceptance. No front-end framework or bespoke JS form engine is proposed.

After affected tests pass, ask the user to run the complete suite with catalog_test php artisan test --compact after defining/inspecting the verified session function above, as AGENTS.md requires. Do not suggest an unprefixed destructive full-suite command against inherited development configuration. Report actual test/browser/MySQL results and any gaps. Commits, pushes, live database migration, and deployment require their own applicable authorization.


### Verification prerequisites that are part of C17

For every later test/browser run, record the actual isolated target, checkout, command, result and side-effect controls before claiming a pass. No app tests or writes were run while preparing this specification.

- `tests/Pest.php` applies RefreshDatabase to Feature tests. Two-connection MySQL tests need committed disposable fixtures; uncommitted harness fixtures are invisible to the second connection. Follow the existing /Users/studioycm/Herd/configurator/tests/Feature/Catalog/MySql/CatalogStatusConcurrencyTest.php pattern: end the harness transaction deliberately, create owned committed fixtures, coordinate independent connection/process attempts with bounded waits, purge/clean only owned fixtures in finally, and restore the harness transaction. The current IntegrityTest alone checks constraints, not contention. Add the Code race/opposing writer cases there or alongside it using that existing pattern; no new test framework.
- Browser mutation acceptance must name a separately isolated application/database/data target before writes. Herd serves the app; never launch another Laravel server. Resolve its URL with Boost get-absolute-url, use approved disposable records, and inspect recent browser logs. If an isolated browser target or guarded MySQL service is unavailable, retain that evidence gap; do not use the working developer database to make a gate green.
- Fake/isolate mail, queues, storage and external APIs as each test path requires. No real notifications or external writes are necessary for this feature. A PHP/Node pass does not establish actual browser selection/morph/focus or MySQL locks.
- Reconcile installed request byte/depth/component limits with actual measured form state. The 500-row domain guard is not a claim that an unmeasured maximum-size nested workspace is efficient; do not increase framework guards automatically. Record any maximum-batch runtime limit separately from the required ten-row workload.

## 7. Decisive acceptance cases and audit traceability

These stable test IDs are minimum discriminating cases, not instructions to mirror implementation structure. Tasks 1–5 add them at the named existing/new test owners above before changing their behavior, observe the relevant failure, implement the minimum change and rerun the narrow file. Use existing factories and Livewire::test / TestAction conventions. Private helper names and exact test prose may follow siblings; preserve these scenario IDs in the eventual evidence ledger.

| ID / owner | Trigger and independently expected result |
| --- | --- |
| V01 / Task 1 | Ten completed Master rows persist exact names/IDs/tags/specifications together; a later invalid row persists none and emits no success. |
| V02 / Task 1 | Suggest `Light Blue`→`light_blue`; persisted/pending collision produces `_2`/`_3` within100 characters. A manual ID or deliberate clear survives another blur, append and failed Save. |
| V03 / Task 1 | Initial one row; Add5 then Add10 adds exactly those quantities with unchanged prior UUIDs/helper/data. 495+5 succeeds; a forged496+5 fails without appending any. |
| V04 / Task 1, browser | Hide Tags/Specification after entry, append names, reopen columns and submit: all data survives. Hidden invalid field reveals/focuses. Resize preserves explicit choices; IME/Tags/textarea Enter stays native. |
| V05 / Tasks 1–2, 4 | Actual existing Value edit accepts its unchanged ID; a nested new Value under a coincident numeric parent ID rejects the persisted duplicate at the new row. Normal blur and suffix target only their owner. |
| V06 / Tasks 1–2, 4 | Leading/middle blank or removed row followed by a later writer error targets the original native UUID field; helper/ref/Code/default data remains intact. |
| V07 / Task 1 | Tag strings trim at new creation boundary; duplicates created by trimming fail rather than deduplicate. Unknown domain/helper keys fail for direct callers. |
| V08 / Task 2 | A shared Attribute with no Values saves; all-empty standalone Attribute Create fails; ten populated Attributes save together. |
| V09 / Task 2 | One pending Master referenced by two Attributes persists once, with two exact association Codes/identities. Final duplicate Code rolls back every earlier new record. |
| V10 / Task 2 | Aa and aA are distinct; whitespace, punctuation, non-ASCII,1/3-character Codes fail; duplicate global Code or Attribute/Master pair fails. Attribute manual legacy key compatibility remains. |
| V11 / Task 2 gate | Composer staging works with another Attribute incomplete and zero inserts; invalid composer/ref remains editable; final Save rejects unfinished participating state. |
| V12 / Tasks 2, 4, browser | Mounted picker context stage/remove/switch A→B→A has current owner/exclusions and one Modelable selection. Checkbox/search/page/column work does not remount it. |
| V13 / Tasks 2, 4 | Existing direct Attribute saveDefinition works with legacy pending rows; new pool rows resolve once and canonical definition plus additions commit or roll back together. |
| V14 / Task 4 | Existing-only Add Attributes and existing-only Add Values succeed with empty new-Master/new-definition branches. A pool-only/unselected final addition fails. |
| V15 / Task 4 | Existing and new Attributes, one reused pending Master, coded associations and local reviews save atomically with explicit defaults. Missing/cross-Attribute default_ref, foreign option_ref or duplicate ref fails with no writes. |
| V16 / Task 4 | Revoked permission before validation, stale inactive/hidden selected record, forged owner/inclusion or duplicate inclusion is denied. Preserve current inactive-repair rules for existing inclusions. |
| V17 / Task 4 | Add Values stays usable with all existing shared Values included; create a new association/Master, include it, and retain the prior default, local identities, rules/mappings/order and drafts. |
| V18 / Tasks 1–2, 4, browser | Row name/Code/whitespace clicks toggle the native checkbox once; checkbox/label/Space preserve native limits. Links/buttons/editors/default/reorder controls never accidentally select. |
| V19 / Tasks 1–2, 4, browser | Empty Cancel closes; meaningful dirty stash/composer opens confirmation. Keep editing retains it; Discard clears only the intended outer session; failed Save/reopen/focus work. No extra custom close guard. |
| V20 / Task 5 | Unsaved local name/settings→choose another saved Default→switch/back→save old edit: both the new default and old edit survive reload. Another selected Value's draft also survives. |
| V21 / Tasks 4–5 | Current default click is no-op; outside-owner Value fails; staged default removal requires reselection. Saved default is a local numeric inclusion ID; runtime unavailable-default policy remains unchanged. |
| V22 / Task 3 | Upgrade fixtures: null/empty/whitespace subtitle→None/null, nonblank custom preserved, `"0"` retained. No blank row backfills to Master. |
| V23 / Task 3 | None/Custom/Master save/reload and unchanged saves preserve intent; link/unlink restores custom draft. Rename shared Master: linked subtitle follows, local main name and other Custom subtitle stay. |
| V24 / Task 3 | Hint-only patch of Master-linked row preserves link; legacy text-only patch changes to Custom/None; Custom with missing text and explicit invalid/null/empty mode fail before write. |
| V25 / Task 3 | Mode survives compiler data/DTO, signed capture, Preview & Test tested save, duplication, batch edits and local dirty stashes. Forged mode rejected; Master rename alone does not create local dirtiness. |
| V26 / Task 3, runtime | Matching/nonmatching SetLabel/SetDisplayValue Rules stay independent. Blank/equal-to-final-label subtitles render no second-line element for select and toggle; strings remain escaped. |
| V27 / Task 6, MySQL | Competing global Code creation and opposing aggregate/canonical operations on committed disposable fixtures produce clean conflict/serialization, complete rollback and unchanged unrelated defaults/identities. Independent requests/connections are mandatory. |
| V28 / Task 6 | Actual desktop/1024px/phone app retains native controls and readable selected columns, errors, preview and keyboard/focus. Record ten-row/multi-Attribute request/payload/query/depth/active-child measurements separately. |
| V29 / Task 7 | Complete contract/test ledger and dependency audit reconcile all scoped paths/CA findings, executed evidence and unavailable checks. Broader refactors remain separate proposals. |

| Finding | Mandatory disposition/control | Contract / acceptance |
| --- | --- | --- |
| CA01 | Scoped adapter clicks one native checkbox; no second selection store. | C02/C12, V18 |
| CA02 | Native table Repeater; bound hint Actions; raw append/new children only; CSS-only columns/error reveal. | C03/C12, V03–V04 |
| CA03 | One complete Master pool, stable refs/original errors, insert each referenced Master once. | C06, V06/V09/V15 |
| CA04 | Owner→context→canonical lock order before patch callback; fresh full aggregate. Supported risk, not reproduced deadlock. | C08, V27 |
| CA05 | Default saves separately and leaves ordinary settings payload/hash. | C15, V20–V21 |
| CA06 | Explicit subtitle intent through all transports and final Rules-adjusted text. | C04–C05, V22–V26 |
| CA07 | UI factories remain UI; four concrete operations compose existing authoritative writers; no staging writes. | C07/C09, V09/V11/V13/V15 |
| CA08 | New creation tag normalization and proven isolated test targets. Broader S07/S11 not declared fixed. | C07/C17, V07/V27 |
| CA09 | Explicit table/no-ignore plus native new-row model/create operation; genuine Value edit exception only. | C10, V05 |
| CA10 | Runtime root/owner/ref and raw/native state distinct from projected lists; source→domain→UUID errors. | C06/C11, V06/V15 |
| CA11 | Complete explicit programmatic suggestion/subtitle/default transitions; no blanket hook bubbling. | C11, V02/V20/V23 |
| CA12 | Prove locked-picker context refresh with native leaf-key/unmount first; override conditional only. | C13/C16, V12 |
| CA13 | Authorized composer-only validation; final full graph validation; no extra component. | C11/C16, V11 |
| CA14 | **Superseded missing-guard claim.** Retain native confirmed discard/disabled exits; no catalog-creation-drafts.js/global guard. | C15–C16, V19 |
| CA15 | Nonreserved typed collaborator names; actual mounted callback checks; no DI layer. | C09/C11, V05/V11 |

Task 7 reports each finding with affected contract/test IDs, trigger/caller, exact source location, evidence level, expected/observed outcome, smallest correction, executed command/result and disposition. Retain sound boundaries as well as defects. Assign CA16 onward only to genuinely new causes. Complete conformance cannot be claimed while an in-scope requirement is missing/mismatched, Blocked, Not verified or unreviewed. Source-only risk, inspected assertion, executed test and browser observation remain distinct.

## 8. Implementation handoff and review protocol

1. Before coding, read this complete spec and refresh checkout/rules/versions/isolated targets. Map Task files and exact public interfaces; keep a single Task checklist in this specification or its execution session, not parallel completion ledgers in old docs.
2. For each task: add its meaningful named regression cases, run the narrow isolated command and establish the intended failure, implement the smallest native change, rerun the relevant checks and inspect the resulting diff. Pure copy/layout does not need mirrored tests; required interaction behavior does.
3. Respect Task 2's connected gate before downstream composition. If a genuine design conflict arises, preserve independently useful work and ask about that conflict only. Do not reopen settled column/Add5/Add10/subtitle/default decisions.
4. After Tasks 1–5, run Task 6 actual app/Node/build/MySQL acceptance safely. Report exact evidence; prototype checks from earlier work do not transfer as app passes.
5. Finish Task 7 conformance and the dependency-expanding Laravel structure review, correct in-scope deviations, rerun affected checks and review impacted paths. Do not use file counts/folder names alone to justify Services/enums/DTOs or broad refactors.
6. Present working app, final evidence and remaining limitations. No automatic commit/push/deploy/live migration follows from a passing document or app review.

**Ready-to-use task instruction after separate implementation authorization:**

> Implement the Catalog creation specification at /Users/studioycm/Herd/configurator/docs/catalog/MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_SPEC.md. Treat it and explicit later amendments as the feature contract. Complete Tasks 1–7 in dependency order, with the native connected gate at the start of Task 2, safe isolated verification and both required final audit reports. Preserve concurrent work and existing writer/serialization/identity contracts. Do not implement rejected alternatives or unrelated refactors. Report actual verification and limitations; do not commit, push, migrate live data or deploy without applicable authorization.

This instruction is a future handoff template, not current authorization. A new chat is optional: it needs this spec, target checkout and explicit implementation authorization, not the entire voice transcript. If implementation is delegated, give each worker its task plus all global/data/lifecycle contracts and exact neighboring interfaces; one integration owner keeps writer/state decisions consistent. Do not let concurrent workers modify the same file or run shared destructive test databases without coordination. A fresh final reviewer reads the completed implementation and evidence rather than relying on implementer summaries.

## 9. Provenance and specification verification

Historical/supporting material (not additional executable requirements): [earlier plan](MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_PLAN.md), [earlier blueprint](MASTER_VALUES_ATTRIBUTES_CREATION_EXECUTION_BLUEPRINT.md), [structure audit](MASTER_VALUES_ATTRIBUTES_CREATION_STRUCTURE_AUDIT.md), [nested reuse audit](MASTER_VALUES_ATTRIBUTES_CREATION_NESTED_REUSE_AUDIT.md), [two investigations and challenger](MASTER_VALUES_ATTRIBUTES_CREATION_CONTAINER_RESEARCH.md). The [interactive prototype](/Users/studioycm/.codex/visualizations/2026/10/19/01a154ae-2ef4-7163-a3f7-c484195c8f7e/catalog-create-workspace.html) is an interaction reference, not proof of persistence.

Inspected Filament Examples support fresh reusable field/schema factories (Invoice Wizard), native Repeaters/Get/Set (advanced repeaters), embedded Modelable/Locked picker/context keys (Quote Products), resource-local composition (LMS) and same-host multiple forms (profile/Page examples). Retained `v4/` paths are the shared4/5 collection, not grounds for rejecting code. Recheck actual custom Livewire4 hooks/plugins/APIs, not folder naming. Do not copy relationship delete/recreate saves into this app's stable inclusion graph. Actual MCP snippets/public excerpts were inspected; no example was installed, executed or fully dependency-audited.

Laravel Daily's public Service-or-Action lesson informed cohesive application operations; Beyond the Controller/When Not to Extract access was limited to visible previews. No gated lesson/video/private repository access is claimed. Laravel structure recommendations here are application-specific synthesis and the local skills, with installed framework source as API authority.

Primary references: [Repeaters](https://filamentphp.com/docs/5.x/forms/repeater), [form lifecycle/validation](https://filamentphp.com/docs/5.x/forms/validation), [Action modals](https://filamentphp.com/docs/5.x/actions/modals), [schema context](https://filamentphp.com/docs/5.x/schemas/overview), [multiple forms](https://filamentphp.com/docs/5.x/components/form), [Action tests](https://filamentphp.com/docs/5.x/testing/testing-actions), [Livewire4 nesting](https://livewire.laravel.com/docs/4.x/nesting), [Laravel transactions](https://laravel.com/docs/13.x/database#database-transactions), [Filament5 shared-example compatibility](https://filamentphp.com/insights/danharrin-filament-v5-blueprint), [Laravel Daily Service or Action](https://laraveldaily.com/lesson/laravel-projects-structure/save-data-service-action).

Installed source checks cover Action schema/relationship-before-callback ordering, Schema model/operation/Get/Set validation, Repeater raw-state/new-child lifecycle, unique-rule inference/no-ignore, native picker Locked/Modelable embedding, callback utility injection, message/morph cleanup and native discard policies. This spec adds precise payload/minimum/error/PATCH contracts; actual implementation/runtime gates remain pending.

**Document review completed:** Two focused reviewers checked contract coverage and execution/architecture precision; a fresh third reviewer audited the standalone specification against the original plan, blueprint, audit history and disputed installed source, then rechecked the corrections. The primary agent completed coverage/interface/task-order self-review. The final corrections pin operation-specific empty rules, exact payload/ref/PATCH semantics, source-to-domain error fields, subtitle prerequisites, staged-default delivery, association resolution and effective test-target checks. No remaining consequential document blocker was found. This is readiness for later authorized implementation, not runtime proof.

**Document verification:** Six related documents have valid local links, consistent task/contract/test/finding IDs and clean whitespace/newlines; the task-local test wrapper and embedded PHP passed syntax-only checks. Neither the wrapper nor any application test/bootstrap/migration/browser/concurrency path was executed for this documentation task. All feature/runtime acceptance and both post-implementation audits remain pending.
