# Select population, Product editing, and inline item-drawer review

Research date: **2026-10-08**. This extends the [FilamentExamples adoption proposal](FILAMENT_EXAMPLES_ADOPTION_PLAN.md). The original inventory below records recommendations for approval; the approved implementation checkpoint at the end distinguishes subsequent changes from those proposals.

## 1. Meaning and observed data

“Pre-populate” here means displaying initial available choices before typing. It does not mean selecting the first record, changing a stored default, choosing all rows, or creating associations. A cap is the initial suggestion count, not the complete allowed universe or the maximum number of selected records.

Boost reconfirmed the installed framework context: Filament 5.9.0 / Livewire 4.4.7 / Laravel 13.35.0. Read-only Boost schema/queries inspected the current local development data:

| Source | Current count |
| --- | --- |
| Shared Attributes | 13, all active |
| Master Values | 45 |
| Shared Options | 35, all active and visible |
| Configurators | 1, active |
| Groups | 2, active |
| Products | 501, active |
| Included Attributes | 10 in the existing Configurator |
| Included Options | 26 total; no Attribute has more than 10 local Options |
| Master Value tag vocabulary | 6 |
| Property keys in the current fixed import map (no database registration flag) | 18 |
| Global future-public context choices | 5 Territories / 4 Applications, before local overrides |

These counts are a dated local snapshot, not production measurements. Eligibility reduces many lists: new inclusions exclude existing/disabled/hidden records; parent Groups exclude descendants and invalid parents; preview Products belong to the selected Configurator's assigned leaf Groups.

## 2. Approval inventory: model-backed form choices

“Complete ≤20” means show the complete eligible list when small; use the initial cap plus full scoped search if it grows. Some lists already have immediate options; the approval then concerns retaining/bounding that behavior rather than adding `preload()`.

| ID | Screen / field | Current implementation | Recommended initial choices |
| --- | --- | --- | --- |
| S01 | Group Details — Parent (`parent_id`) | Complete eligible parent map, searchable | **10**, or complete eligible list ≤20; keep Root placeholder and all hierarchy safeguards |
| S02 | Group Details — Configurator (`configurator_id`) | Search-only callback, search result limit 50 | **10**; all one current Configurator; retain Unassigned and parent-Group assignment restriction |
| S03 | Shared Option create/edit — Attribute (`attribute_id`) | Search-only by label/key, result limit 50 | **20**; all 13 current Attributes with label + key |
| S04 | Shared Option create/edit — Master Value (`value_id`) | Search-only, result limit 50 | **10**; optional **20** if preferred; retain label + ID/context for ambiguous labels |
| S05 | Attribute's Options manager — Master Value (`value_id`) | Separate search-only field, limit 50 | **10**, or same approved 20 as S04 |
| S06 | Configurator Attribute inclusion — Canonical Attribute (`attribute_id`) | Complete eligible options map; canonical identity locked on existing inclusion | **20** / all eligible ≤20; only affects new/selectable inclusion paths |
| S07 | Inclusion review's Options repeater — Canonical Option (`options.*.option_id`) | Complete Options of selected shared Attribute; existing identity locked | **10** / complete scoped list ≤20; preserve inactive/stale existing references visibly for repair |
| S08 | Local Options manager — Shared Option (`option_id`) | Complete active/visible, not-yet-included Options of this Attribute; locked on edit | **10** / complete scoped list ≤20 |
| S09 | Inclusion editor/review — Stored default (`default_configurator_option_id`) | Complete local/draft Option list; includes removed-reference repair label | **All local Options** when small; searchable when large; never remove current/default or stale repair choice because of a cap |
| S10 | Mapping Rule — Driver Attribute | Complete owner-local Attributes, searchable | **All 10 current local Attributes**, complete ≤20 |
| S11 | Mapping Rule — Target Attribute | Same owner-local map | **All 10**, complete ≤20 |
| S12 | Advanced Rule effect — Target Attribute | Same owner-local map, per effect | **All 10**, complete ≤20 |
| S13 | Rule condition — Source Attribute | Same owner-local map, reused for standalone and grouped predicates | **All 10**, complete ≤20 |
| S14 | Advanced Rule effect — Target Options (`option_ids`) | Complete Options of selected local target; keeps stale selected references | **All scoped Options** while ≤20; otherwise initial 10 + complete search and selected-label resolution |
| S15 | Rule condition — Included Options / exact codes (`option_ids`) | Complete Options of selected local source; keeps stale selected references | Same as S14; preserve one-choice versus list-condition selection semantics |
| S16 | Configurator Preview & Test — Product Code (`product`) | Search-only, scoped to assigned leaf Groups, result limit 50 | **10**, optional **20**; code + name would help recognition; never preload all 501 Products |
| S17 | Configurator Preview — dynamic Attribute choice (`choices.<id>`) | Complete engine-derived nonhidden choices with illegal choices disabled | **Keep the complete runtime choice set**; no first-ten truncation and no automatic default changes |
| S18 | Dashboard/catalog Configurator — Attribute select in shared configuration-fields view | Native HTML select of complete nonhidden engine-derived choices | Same as S17; this is runtime selection, not an asynchronous library lookup |

Source locations: [GroupForm](../../../app/Filament/Resources/Groups/Schemas/GroupForm.php), [OptionForm](../../../app/Filament/Resources/Options/Schemas/OptionForm.php), [Attribute Options manager](../../../app/Filament/Resources/Attributes/RelationManagers/OptionsRelationManager.php), [ConfiguratorAttributeForm](../../../app/Filament/Resources/Configurators/Schemas/ConfiguratorAttributeForm.php), [local Options manager](../../../app/Filament/Resources/Configurators/RelationManagers/OptionsRelationManager.php), [ConfiguratorRuleForm](../../../app/Filament/Resources/Configurators/Schemas/ConfiguratorRuleForm.php), [ConfiguratorPreview](../../../app/Livewire/Catalog/ConfiguratorPreview.php), [shared runtime fields](../../../resources/views/components/catalog/configuration-fields.blade.php).

## 3. Approval inventory: relationship and vocabulary filters

| ID | Filter / occurrence | Current implementation | Recommendation |
| --- | --- | --- | --- |
| F01 | Products table — Group | Searchable relationship with `preload()` | **10** if it becomes a select; currently all 2 Groups can remain quick buttons |
| F02 | Options table — Attribute | Searchable relationship with `preload()` | **20**; all 13 current Attributes; keep complete search |
| F03 | Groups table — Configurator | Searchable relationship, no explicit preload; shared quick-filter adapter probes initial options | **10** if a select; current one choice + All can remain quick buttons |
| F04 | Groups table — Parent | Searchable relationship with `preload()` | **10** if a select; current small list can remain quick buttons |
| F05 | Master Values — tag filter, inline header and robust Filters schema | Complete vocabulary; ToggleButtons at ≤6, searchable multi-select beyond 6 | **Keep all 6 buttons**; show complete vocabulary ≤20 if it becomes a select; initial 10 + complete search for a much larger vocabulary |
| F06 | Batch Option table picker — Master Value tag filter | Reuses F05's field and vocabulary, with a different Option query scope | Same as F05; preserve the picker-specific filtering and selections |

Static table filters are already fully populated and do not need database preloading: Active/Disabled; Option Visible/Hidden; local Option Initial availability (Visible/Hidden/Disabled); Rule kind (Mapping/Advanced); Rule activation; Rule scope (Dashboard/Future public). Keep their small quick-button presentation and robust Filters equivalent. The search-scope selector contains table column names plus All; it is already a complete fixed list.

Source: [ProductsTable](../../../app/Filament/Resources/Products/Tables/ProductsTable.php), [OptionsTable](../../../app/Filament/Resources/Options/Tables/OptionsTable.php), [GroupsTable](../../../app/Filament/Resources/Groups/Tables/GroupsTable.php), [ValuesTable](../../../app/Filament/Resources/Values/Tables/ValuesTable.php), [OptionSelectionTable](../../../app/Filament/Resources/OptionSelectionTable.php), [TablePresentation](../../../app/Filament/Resources/TablePresentation.php), [StatusActions](../../../app/Filament/Resources/StatusActions.php).

## 4. Derived lists, operational selectors, and table pickers

These are included so that “all selects” does not accidentally mean only native `relationship()` fields.

| ID | Control | Current / recommendation |
| --- | --- | --- |
| D01 | Group Presentation — Properties to display | Registered property names, not model rows; already complete. **Keep all 18** |
| D02 | Group Filters — Add filter properties | Same registry, multi-select; **keep all 18**, preserve existing filter rows/identities |
| D03 | Group Filters repeater — Product property | Same registry; **keep all 18** |
| D04 | Group Presets — Product property | Same registry; **keep all 18** |
| D05 | Group Presets — Allowed source values | Current full Group/property vocabulary; **complete ≤20**, otherwise **initial 10** + full vocabulary search; never truncate allowed/selected values |
| D06 | Rule ProductProperty condition — Product property | Same registry; **keep all 18** |
| D07 | Group batch edit — Card properties | Same registry in `changes.*.value`; **keep all 18** and make searchable if useful |
| D08 | Future-public Rule context — single Context choice | Resolved global/local context JSON, not Territory/Application model records; **complete small vocabulary**, future-public only |
| D09 | Future-public Rule context — multiple Context choices | Same as D08; **complete small vocabulary**, preserve stale references/repair semantics |
| O01 | Batch dependency inspection — Blocked record | Complete records in the reviewed blocked selection; **keep all**, add search if it grows; do not silently omit blocked records |
| O02 | Configurator status-impact inspection — Configurator chooser in Groups/Products drawers | Complete selected Configurators; **keep all**, searchable if large; never change reviewed scope |
| T01 | Batch include Attributes (`TableSelect`) | Query-backed first page already shows records; **10 rows/page**, with 25/50 available; no new preloading or auto-selection |
| T02 | Batch include Options (`TableSelect`) | Same pagination, constrained by Attribute and inclusion eligibility; **10 rows/page**, 25/50 available |
| T03 | Configurator assigned Groups (`TableSelect`) | Same pagination, eligible leaf Groups; **10 rows/page**, 25/50 available; retain current assignment selections |

D05 has meaningful size variation in the current leaf Group: nonblank source vocabularies include Model **125**, WT **117**, B **107**, A **64**, E **45**, AV and Connection Size **7** each, with the remaining properties at **1–5**. These are SQL source counts; the actual allowed vocabulary still comes from `CatalogDiscovery` with its type/availability policy. A ten-choice initial suggestion list must not turn into a ten-value eligibility rule.

Other fixed selectors require no approval for database preloading: appearance width presets; Cards per row; result threshold; card layout/column count; input type; effect kind/scope; condition source/operator; batch field/mode/match/occurrence choices. They already have complete option arrays. Master Value tag entry is a **TagsInput**, not Select, and already has all 6 vocabulary suggestions. Mapping-set source/target controls are checkboxes and remain unchanged, as requested. Future-public context sidebar uses buttons; it remains absent from the logged-in dashboard configuration.

## 5. Implementation rules after approval

1. Preserve the complete scoped search and separately resolve selected labels, including IDs outside the initial ten. Do not preselect new records. Keep existing record defaults and stale-reference repair labels.
2. Use deterministic current label/code ordering plus an ID tie-breaker; preserve local display order for included choices. Suggestions need not be “recently used” unless separately requested; no usage-tracking feature is implied.
3. For genuine native relationships, `Filament\Forms\Components\Select::preload()` and `Filament\Tables\Filters\SelectFilter::preload()` are supported. Installed Select's `optionsLimit()` also affects relationship search limits; it does **not** magically SQL-limit custom `options()` callbacks.
4. For search-only/custom callbacks, add a bounded initial `options()` query and retain a scoped `getSearchResultsUsing()` with its own result limit, plus `getOptionLabelUsing()` / `getOptionLabelsUsing()` where appropriate. Merely appending `preload()` to the existing custom search callback does not provide a first-ten database query.
5. Do not `limit(10)` a complete options array and lose browser-only search over the rest. Larger scoped maps require server search or deliberate retention of the complete small map.
6. Group parent eligibility must be computed with the full hierarchy/exclusion rule before limiting displayed suggestions. Applying a limit to the graph used to detect descendants would weaken its safeguards.
7. Quick-button qualification must test the actual eligible list (the existing up-to-seven probe), not a deliberately capped subset. Otherwise a large list could incorrectly become six buttons with missing choices.
8. Where the complete scoped set is already needed for compilation, mapping, or runtime rendering, avoid an extra query per repeated field. Cache a prepared owner map within the request/component lifecycle; never share actor-sensitive mutable state globally.

Meaningful regression coverage after implementation: initial choices without a search; a valid result outside initial cap; selected label outside cap; unknown/foreign/disabled new ID rejected by domain save; existing stale reference preserved for repair; multi-selection not truncated; quick toggles and robust filters remain equivalent. No app tests were run for this inventory-only review.

Official references: [form selects](https://filamentphp.com/docs/5.x/forms/select), [select filters](https://filamentphp.com/docs/5.x/tables/filters/select), [tabs](https://filamentphp.com/docs/5.x/schemas/tabs).

## 6. Product edit form: proposed scope and unresolved ownership

Verified current source: [ProductResource](../../../app/Filament/Resources/Products/ProductResource.php) has only Index/View pages and returns false for create/edit/delete; [ProductInfolist](../../../app/Filament/Resources/Products/Schemas/ProductInfolist.php) shows identity, Group, legacy IDs, and facts. `Product` stores identity, name, description, Group, `properties`, `parts`, `extra_data`, and status. There is no existing Product save-form action to reuse. Status already has an authorized domain action.

**Suggested workspace.** Retain the Products list/right-pane layout. A future `App\Filament\Resources\Products\Pages\EditProduct` and reusable `Products\Schemas\ProductForm` can serve both the resource and a compact item-drawer editor. Keep View for inspection. Keep Create/Delete disabled; an edit form does not imply adding those capabilities.

| Area | Proposed first scope |
| --- | --- |
| Details | Product name and description are candidate editable fields; code and Group shown read-only initially; existing Change status action, not an unreviewed generic status write |
| Properties | Read-only structured property list first; editable property values require an explicit source-ownership/validation decision |
| Parts / Source | Read-only source facts, legacy IDs and extra data; collapsible or secondary tab; no raw JSON editor |
| Context actions | Open catalog, inspect/open Group, and navigate to its assigned Configurator where present |

Use tabs only if the structured facts make the form genuinely long. The Details tab should contain its fields directly without a redundant sole bordered Section. If only identity fields are editable, keep one compact form and collapsed facts instead.

**Decision needed before implementing editable imported fields.** [ImportCatalogProducts](../../../app/Actions/ImportCatalogProducts.php) fills source-owned core fields and merges imported JSON buckets; overlapping source keys overwrite direct edits. Choose:

- **Direct edits:** change the existing stored name/description/approved facts, with an explicit hint that reimport restores source values.
- **Persisted local overrides:** retain source values and separate admin display overrides that survive reimport. This requires an agreed storage/read contract across cards, catalog, search, resource forms, and imports.

Do not silently select either policy. The earlier admin plan treats imported facts as read-only, so making them editable is an amendment, not a missing implementation of an already approved field.

Group reassignment is a separate issue: the importer rejects an existing Product whose current Group's legacy identity conflicts with `legacy_group_id`. Do not make `group_id` casually editable. If later approved, its selector would use **10 initial eligible Groups**, with full search and an explicit identity/import policy. Product-code changes likewise require the source identity/uniqueness contract. Legacy IDs, parts identity, and arbitrary extra-data editing are excluded from the first form.

Future persistence would use a focused `App\Actions\SaveCatalogProduct` only after the field policy is settled: `manage-catalog`, a freshly locked Product, strict allowlisted fields, existing status action where appropriate, atomic write, and correct `CatalogRevisions` invalidation for the affected Group. It must preserve unedited JSON keys and work identically from the resource and the drawer. Name/description limits follow current storage/domain conventions (255/5000), not mass assignment of the entire form payload.

## 7. All current item-drawer row types and editor suitability

The [ItemLists registry](../../../app/Services/ItemLists.php) supports the following **eight Eloquent row types plus one array type**. Some keys serve dependency/status inspection rather than a currently visible count column. `canonical-{attribute|value|option}-{options|inclusions|defaults|rules}` expands into twelve supported aliases and is included below. This is the current registry, not an inventory of every model in the application.

| Row type | Registry keys / origins | Editor below table? |
| --- | --- | --- |
| Shared `Option` | `attribute-options`, `value-options`, and canonical `*-options` aliases | **Yes, first wave.** Reuse shared Option schema/save action; clearly label shared impact |
| `ConfiguratorAttribute` | `attribute-inclusions`, `configurator-attributes`, `local-option-defaults`, canonical `*-defaults`, and canonical Attribute `*-inclusions` | **Yes, first wave.** Local label/help/input/default settings; preserve owner and complete-definition save |
| `ConfiguratorOption` | `option-inclusions`, `value-option-inclusions`, `inclusion-options`, canonical Option/Value `*-inclusions` | **Yes, first wave.** Local label/value/hint/initial availability; shared Option identity remains locked |
| `ConfiguratorRule` | `configurator-rules`, `inclusion-blocking-rules`, `local-option-blocking-rules`, canonical `*-rules` | **Yes, next wave.** Reuse the dedicated Rule editor after concurrent work settles; whole-rule/owner validation |
| `Group` | `configurator-groups` | **Yes, first wave.** Compact Details; relevant Filters/Presets/Presentation tabs when available; normal Group settings save |
| `Product` | `group-products`, `configurator-products` | **After Product edit policy/form exists.** Read-only View can be embedded sooner, but must not be called an editor |
| `MappingSet` | `rule-mappings` | **Open its owning Rule editor below the table**, selecting/focusing the set where supported; no independent blind row update |
| `RuleEffect` | `rule-effects` | **Open its owning Rule editor**, selecting/focusing the effect where supported; preserve complete Rule constraints |
| Selected card-property array row | `group-card-properties` | **Yes: parent Group presentation editor.** These are property keys, not models. Edit the selected ordered property list/relevant presentation controls; no fake per-property record |

No current registry list directly returns shared `Attribute`, `Value`, or top-level `Configurator` rows. An Attribute “Configurators” count returns **local Attribute inclusions**, and a Master Value Options count returns **shared Options**. GroupFilter/SubGroup/RuleCondition rows are not current drawer types; adding their list/editor is a separate extension.

### Drawer interaction proposal

Retain the single searchable/paginated table at the top. Row click or a pencil action selects a row and reveals one compact editor **under that table inside the same slide-over**. Keep a separate external-link icon for the full resource workspace. Do not render the entire resource/relation-manager table again beneath the drawer table.

Reuse existing schema/action contracts rather than copying them: `SaveCanonicalOption` for shared Options, current Group actions for Groups/presentation, and `SaveConfiguratorDefinition::change()` / the existing local Option adapter for local changes. A server allowlist maps row type to editor; never accept a model class/component path supplied by the browser. The selected ID must still belong to the freshly resolved base item list, independently of current pagination/search, and each read/save reauthorizes `manage-catalog`.

Store selected editor identity separately from filter/page state. Changing row or closing a dirty editor uses the existing discard/draft-preservation flow. Resizing, table filtering, and read-only refresh must not recreate the draft component. On successful save, refresh the table/count/context; if the row no longer belongs to the list, explain that state rather than selecting a different row silently. Failed validation keeps the editor open and shows its errors. Do not broadcast a refill into a different mounted unsaved editor.

Array properties resolve to the parent Group, not an artificial row ID. Presentation save preserves complete filter/preset lists and unmodified settings. Empty `card_properties` intentionally uses Group filter properties; the count/list is explicitly **selected** properties, not the resulting fallback display list. Do not change that semantic while adding editing.

## 8. Contextual links and persistent tabs

**Verified problem.** `GroupForm` creates Details / Filters / Presets / Presentation tabs without `persistTabInQueryString()`. `ItemLists::definition('group-card-properties')` generates a plain Group edit URL. Consequently the link has no Presentation target; it is not solved merely by adding a query parameter that the Group form does not read.

Only Configurator edit currently persists its tabs. Admin Appearance (Density/Navigation/Dialogs) and Context Settings (Territories/Applications) also have nonpersistent Tabs. Master Value, Option, and Attribute base forms do not have comparable tabs to persist.

**Proposed fix after approval:**

1. Group tabs use `Filament\Schemas\Components\Tabs::persistTabInQueryString('group-tab')`. A dedicated key avoids collisions with the parent Configurator's `tab` when Group editing is embedded. Keep the existing stable Tabs key.
2. The card-properties full-editor link supplies `group-tab => presentation::data::tab`. Use the actual Filament-generated Tab ID, not just the visible label `Presentation` or a guessed numeric index. Current installed Tab identity derives from label plus state path; verify it through the actual schema/test when implementing.
3. Relevant Group links for filters and presets target `filters::data::tab` / `presets::data::tab`; a generic Group link can remain Details. Use one Group editor-link helper/descriptor so resource and drawer links agree.
4. Appearance and Context Settings may use namespaced `appearance-tab` and `context-tab` persistence for their existing tabs. Context Settings remain future-public and hidden from normal navigation as agreed.
5. Keep existing Configurator `tab` / `attribute` / `rule` deep links. A local Option link currently selects its owning Attribute but not the specific Option editor. MappingSet/RuleEffect links select their owning Rule, but not a specific child row. Add child selection/focus only through a verified, owner-validated component contract; the current links do not already provide it.
6. In an embedded drawer, use the explicit editor descriptor/initial tab rather than clobbering the main page's unrelated tab state. Child state paths can differ from `data`; do not reuse a standalone page's serialized Tab ID blindly.

Installed native persistence matches exact tab IDs, while unauthorized/missing owners remain subject to normal domain lookup. Test the resulting selected tab/schema as well as the URL parameter. Invalid/stale tab names fall back safely; parent/non-leaf Groups do not have leaf Presentation settings and should not receive a misleading Presentation link.

Future checks: opening card properties lands on Presentation; Filters/Presets links select the intended tab; reload and Back/forward do not overwrite drafts; nested Group tab state cannot change Configurator's tab; local Option and Rule-child focus rejects foreign IDs; inline editor save maintains the original list scope. These fixes were diagnosed from source, not implemented or browser-tested in this review.

## 9. Approval choices

The user can approve IDs or groups of IDs rather than a blanket preload-everything change. Recommended changed search-only fields first: **S02, S03, S04, S05, S16**, plus retaining bounded relationship filters **F01–F04**. Existing complete small lists mostly need no visible change. D05 deserves a separate large-vocabulary search improvement; first-ten suggestions alone would otherwise hide valid choices. The first drawer-editor wave is Shared Option, local Attribute/Option settings, Group settings, and card presentation. Product imported-field ownership remains open; Rule/MappingSet/Effect reuse follows the dedicated workspace session.

## 10. Approved implementation checkpoint — 2026-10-09

The user selected **Shared Options first, then Groups/Presentation**. Count drawers now reuse `EditOption` and `EditGroup` beneath their existing tables, with row clicks and an explicit local-edit action. Selected card-property array rows open their real parent Group on Presentation; empty leaf Groups also have an Edit Presentation action. Parent Groups have no Presentation editor. These are existing domain-owned editors, not separate persistence paths or synthetic property records.

`ItemCountColumn` explicitly opts into local editing. Status/dependency/batch inspection drawers retain their full-editor links without local editors, because changing their external scope selector would otherwise discard a mounted draft. Child editors recheck fresh base-list membership at mount, hydration and save, independently of search/page state. Successful saves refresh the matching drawer; unrelated drafts are retained. Failed validation and stale-record failures preserve the draft. Embedded Option deletion is omitted; usage inspection remains available.

Row switching and drawer closing use inline **Keep editing / Discard drafts** choices, including the native footer Close, header X, Escape and backdrop. The parent split-list guard only inspects its own right editor and excludes modal transitions. Desktop width controls and a 390px viewport were checked with an unsaved Group draft retained and no page overflow; browser review did not save or reorder development records.

Group tabs now persist with `group-tab` outside drawers. The verified Presentation ID is **`form.group-settings.presentation::data::tab`**; the shorter IDs in the original proposal above were unverified examples. Drawer tabs use an explicit initial Presentation selection without modifying the parent URL. Local Attribute/Option settings, Rules/MappingSets/Effects and Product drawer editors remain outside this first implementation wave. The selector preload inventory and measured form deferral remain separate decisions.

The same implementation adds header-plus creation in the right pane for Groups and Configurators. Group creation selects its new editor; Configurator creation opens its dedicated manage page. Resource and existing Configurator table/editor layouts share a keyboard-accessible draggable divider with saved one-third/half/two-thirds stops and mobile stacking. Saved order scopes gain drag controls alongside default up/down controls: sibling Groups, Configurator Attributes/local Options/Rules, their matching item drawers, mapping sets, selected card-property order, and existing ordered form repeaters. Shared libraries, Products and the Configurators resource gain no artificial order.

Regression coverage is in `GroupConfiguratorCreationTest`, `RowOrderingTest`, `ItemListOrderingTest` and `ItemDrawerEditorsTest`, alongside the existing affected resource/workspace/search suites. No dependencies or database schema were changed. Publication verification on 2026-10-09 passed the full default suite (521 tests / 3,172 assertions), the guarded disposable MySQL suite (23 tests / 108 assertions), all 28 JavaScript tests, Pint, FilaCheck (zero issues), and the production asset build. Git history records the publication of this checkpoint.

## 11. Product editor and import decisions — 2026-10-10

These decisions supersede the unresolved imported-field ownership and read-only property proposals in sections 6 and 9. The initial Product editor is implemented locally as recorded in section 12; the flexible importer and Product data redesign remain pending.

- Product editing belongs in the existing right pane. Edit Product code, name, description and values of **every existing key** in that Product's `properties`, `parts` and `extra_data`. Render JSON as repeaters with fixed labels, not editable keys; derive those labels from the Product's actual JSON, without requiring import-map membership. **No input value suggestions for now**; this supersedes the earlier suggestion approval. Keep Group assignment and both legacy IDs read-only for now.
- Field-key pickers for Group filters/card properties and Configurator rules should suggest keys from relevant Products while accepting manually entered future keys. Keep saved references even when no current Product has that key. This is field-definition authoring, not permission to add/rename a key through the Product editor. Missing-property policy is global only, initially Condition false, as specified in section 14; implementation remains pending.
- **Approved eligible sources:** Group filters/presets, card-field selections and Configurator rules can use non-Part facts from both `properties` and `extra_data`. Display-bucket placement must not limit eligibility to Properties. Parts remain Product/drawing-specific and are excluded from this expansion.
- Use **Add only** and **Replace** import modes. Add only preserves existing Products. When Replace is selected, fields supplied by the export replace corresponding stored content, including admin edits; a separate persistent override layer is not requested.
- In Replace, preserve previously stored fields that are absent from the export. An explicitly blank exported cell clears its corresponding value. The parser must retain column-presence information so an absent column cannot become an accidental blank write.
- Preserve every source column, including previously unseen columns. During import, set/map which columns go to `properties`, `parts` and `extra_data`. Review new columns' mapping and intended use; do not silently discard them or automatically create filters/rules. The earlier phrase "review registration" does **not** approve a property registry/database flag, shared definitions or eligibility model; those need further discussion.
- **Approved import key rule:** retain ordinary source headings as fixed keys; mapping chooses the destination bucket, not a renamed destination key. Blank/repeated headings may receive distinct generated keys explicitly shown in review, while retaining their original headings and column positions. Product-editor keys remain read-only. Section 15 records these approved naming rules.
- **Approved reusable reviewed mapping profiles:** save accepted source-column bucket choices and generated keys for reuse with the same export layout. New or changed columns still require review before Apply; profile reuse does not authorize silent remapping or applying an upload. This capability is independent of the unresolved Product-storage choice. Section 16 records its planning boundaries.
- Provide a reviewed admin Import action with a compact summary of created/updated/skipped Products, new columns and errors, plus expandable changed-row details before Apply. Add only/Replace selection and source-column mapping belong to that review.
- The legacy file is a flattened, Group-based export combining Product fields, specifications, properties and parts from several source tables. The known 67-column map is one observed layout, not the complete legacy schema.
- Preserve performance boundaries: catalog discovery uses cached Group snapshots; Configurator evaluation receives Product facts in memory. Any new storage must load required facts together, with no database query inside an individual rule evaluation.
- **Confirmed storage goals and filter scope:** the operator cares about both performance and clearer Product-data structure. Catalog filters remain scoped to their Group/branch context. Admin table filters may span unrelated Groups and are a separate surface/workload; a future alternative catalog filtering experience is not part of this storage decision.
- **Operator-reported planning scale:** approximately 50 leaf Groups, mostly 50–200 Products each; around 5–10 larger Groups, with roughly 100–1,000 Products and a possible upper case near 3,000 in one Group. These are estimates, not limits or a confirmed total Product count. Section 16 separates representative scenarios from measured performance.

Current-source gap: `CatalogImportParser` requires exactly its 67 positional headers and derives 18 allowed property keys from that fixed map. "Registered" currently means this PHP allowlist, not a database registration field. Flexible exports and reviewed mappings require parser and consumer changes, not only an upload action or Product form.

Products can have different property fields. Outside Parts, identical keys have the same intended meaning, but values may use different units or representations across Product families. Legacy values are full, unnormalized text: for example `10 PSI`, `10psi - 16 psi`, `XX psi (XX bar)`, `5` or `10.45`. Preserve that text without inferring a unit, parsing a range or treating a numeric-looking string as a canonical number. Stable keys do not by themselves make values comparable.

The three JSON buckets originally served the three-column Product presentation, not proof of three domain entities. Parts came from an actual legacy table; their current exported values are part name/material text. `Part10` identifies position 10 on **that Product's graphical schema**, not the same part type across Products. Parts must not acquire shared semantic identity merely from their ordinal key. Shared Part records are not planned for this phase; reconsider them after Product data and its mechanisms are refined.

Future Product data structure remains open, including the possible need for one or two additional models/tables. Do not let export layouts dictate the domain model or assume every Product shares the first export's fields. Numeric/range comparisons are explicitly deferred. Revisit the Product filter/discovery mechanisms and Configurator rule/compiler/engine contracts together with the data redesign. Preserve main Product identity and relations unless separately approved. The earlier request for HTML examples remains part of that architectural review before storage/behavior changes.

## 12. Initial Product editor implementation checkpoint — 2026-10-10

- [ProductForm](../../../app/Filament/Resources/Products/Schemas/ProductForm.php) and [EditProduct](../../../app/Filament/Resources/Products/Pages/EditProduct.php) now serve the existing Products list's right pane. Details, Properties, Parts and Extra data are separate tabs without redundant Sections. Group and legacy IDs render as read-only entries. View remains available; Create/Delete remain disabled.
- Compact table repeaters display each existing JSON key beside its value, with no key editing, adding, removing or reordering. This editor works with unknown keys that are absent from the fixed import map. All three buckets use free text inputs without datalist suggestions or a suggestion-query path. Units, ranges and numeric-looking source strings stay literal.
- [SaveCatalogProduct](../../../app/Actions/SaveCatalogProduct.php) authorizes `manage-catalog`, follows the existing Group-before-Product locking order, checks a locked stale baseline, validates an explicit content-field allowlist and rejects altered/missing/duplicate JSON keys. It preserves unedited scalar/null/nested values, literal whitespace/zero prefixes, existing keys and original JSON object shape. Present text values are editable; unsupported non-text source values remain visible/read-only pending type semantics. All current local imported values are text.
- Successful changes advance the affected Group revision in the same transaction; unchanged saves do not. The form refreshes to saved values before establishing its next baseline. Stale drafts survive a rejected save and have an explicit Reload control. Existing Product filters and rule eligibility are not widened by editing an unknown key.
- Fresh verification after removing suggestions: **540 default-suite tests / 3,288 assertions**, **18 guarded disposable MySQL tests / 113 assertions**, Pint, FilaCheck (zero findings after the documented shared-cache false-positive exception) and diff check. The earlier Vite build remains valid because no frontend assets changed in this correction. Product-specific coverage includes unknown keys, key tampering, duplicate codes, stale drafts, permission enforcement, typed-data preservation, blank values, numeric object keys, no datalist suggestions, literal units/ranges, cached vocabulary refresh and repeated saves. The MySQL run corrected a test's incidental JSON key-order assumption; persisted keys and text remain strictly checked.
- Browser review uses existing local records without saving them: right-pane row selection, all four tabs, read-only Group/legacy identity, two-column facts at narrow pane/mobile widths, keyboard divider resizing with a retained draft, and 390px document/viewport parity. The unsaved-record-switch confirmation was cancelled and retained the draft; temporary review values were restored before closing the review tab. No production verification, database migration, import workflow or new data model is claimed. These changes are not yet committed/pushed.

## 13. Product data redesign: affected mechanisms to revisit

| Mechanism | Current responsibility / assumption | Redesign requirement |
| --- | --- | --- |
| `CatalogImportParser::COLUMNS` | Fixed, positional export layout and bucket/key destinations | Reviewable destination-bucket mappings for variable exports, retaining ordinary source headings as keys and reviewing generated keys for blank/repeated headings; preserve all source columns and distinguish omitted columns from explicit blanks |
| `CatalogImportParser::propertyKeys()` | Derived PHP allowlist of only the first export's property fields | Replace first-export membership with relevant observed/saved/future keys from Properties and Extra data; one logical non-Part key regardless of display bucket |
| Group form, `SaveGroupSettings`, `BatchCatalogChanges` | Field choices plus write validation for filters/presets/card selections | Relevant, explicitly chosen fields with stable references; handle Products with different field sets |
| `CatalogDiscovery`, `BuildCatalogSnapshot` | Allowlisted SQL JSON paths into Properties, retained filters/presets, cached vocabularies and matched rows | Read configured facts from either eligible source using the same resolved identity, safe bound paths and coherent cache revisions; preserve the cached discovery boundary |
| `CatalogPolicy`, `CatalogCards`, `CatalogCardDisplay`, Product-card Blade | Retained card field selections; fetch, compare and render only Properties today | Make selected Extra data facts available consistently without silently dropping saved selections or exposing unrelated data; retain literal text and contextual presentation |
| Configurator rule form / `ConfiguratorDefinitionCompiler` | Product-property picker and compiler validation against the same fixed field list | Separate source mapping from rule eligibility; stable meanings can still have incompatible units/text representations; migrate saved references if identities change |
| `ConfiguratorDefinitionLoader`, `ConfiguratorEngine` / evaluation input | Loader supplies Properties only; engine reads those facts in memory with exact-string and Contains behavior | Supply required Properties and Extra data facts together through the resolved fact contract; no per-condition database queries; numeric/type semantics remain a later decision |
| Product Parts / read-only facts view | Ordinal drawing positions; current display also assumes `Part1`–`Part28` | Preserve Product/drawing ownership and position; do not equate ordinal keys across Products or retain a universal fixed range blindly |

Changing the provider of a field list while keeping stable keys/string values is smaller than renaming keys, changing types or moving values into tables. The latter requires explicit migration of saved filters, presets, card selections and rule references, plus read/query/cache verification. This table is a scope inventory, not approval of a final storage design.

## 14. Suggested/future field keys and missing-property policy — design discussion

**Confirmed:** observed keys from Properties and Extra data are suggestions, not an eligibility limit. An admin can enter a future key before importing its Products. Single-key controls can use a text input with a datalist; the ordered card-key list can accept typed keys plus suggestions. Suggestions combine relevant Product keys with saved keys so empty Groups and temporarily absent fields remain manageable. Parts are excluded. No property-registry model is implied. Native references: [TextInput datalist](https://filamentphp.com/docs/5.x/forms/text-input#autocompleting-text-with-a-datalist), [TagsInput suggestions](https://filamentphp.com/docs/5.x/forms/tags-input#autocompleting-tag-suggestions).

This needs coordinated validation/query/compiler changes, not only a new form input: replace import-layout membership checks, validate exact nonempty key strings within current 100-character reference columns, construct JSON paths safely using bound parameters, retain saved filters/presets/card/rule references, and invalidate Group snapshots when definitions change. Do not silently normalize keys, equate units or create Product facts when saving a future field definition. Empty-key vocabularies must not invalidate a saved future key.

**Current engine:** `ConfiguratorEngine::matches()` treats absent/null/non-text Product values as false before operator dispatch, including NotEquals/NotIn. An empty string is a present text value. AND/OR groups retain normal Boolean behavior; a false child does not necessarily make an OR rule false. The compiler derives an immutable `ConfiguratorPolicy` and puts it in `ConfiguratorDefinition`. The loader already selects the Configurator row and passes `policy_overrides` through its draft; overrides are currently rejected by `ConfiguratorPolicy::resolve()` and stripped by the compiler. This phase does not enable that field: resolve the new global settings into the policy instead. Supporting the settings requires explicit policy/compiler/loading and authorized global-settings save-contract changes.

**Approved policy scope and modes:** global settings only, initially **Condition false**, preserving current behavior with the option defaults below. Offer all three selectable modes. The latest user decision supersedes the earlier global-default-plus-per-Configurator proposal: no per-Configurator overrides in this phase, no override form controls and no new Configurator policy fields. The configurable comparisons, Boolean-group behavior, missing-value classification, unavailable interaction and diagnostic visibility are approved as recorded below. These are future requirements, not implemented engine settings.

| Choice | Missing property result | Engine work |
| --- | --- | --- |
| Condition false | That condition is false for all operators, including NotEquals/NotIn; other OR branches may still match | Existing behavior plus optional deduplicated diagnostics |
| Skip affected rule | Skip the entire active rule if it references a missing Product fact | Precompute referenced keys per rule; build an in-memory skipped-rule set once per evaluation |
| Configuration unavailable | Stop configuration with a missing-data diagnostic and no code | Check relevant active-rule keys against Product facts once before settling |

Never implement "skip" by deleting only the missing condition from an AND/OR group; that can change a rule's meaning. Missing values match negative comparisons only when the admin explicitly selects that approved option; NotEquals/NotIn alone do not imply that fallback.

### Approved mode options and defaults

| Control | Approved options | Approved default |
| --- | --- | --- |
| Global mode | Condition false; skip affected rule; configuration unavailable | Condition false |
| False mode: negative comparisons | Missing produces false for NotEquals/NotIn; or explicitly let absence satisfy these negative comparisons | False |
| False mode: OR groups | Evaluate remaining branches normally; or make an enclosing OR group false when any of its branches references a missing fact | Normal Boolean evaluation |
| Unavailable mode: blocking threshold | Any missing fact referenced by an active rule; or only when missing data leaves an active rule's truth undecidable | Any missing referenced fact |
| Missing classification | Absent keys/null only; optionally also treat empty or whitespace-only text as missing | Blank-text option off |
| Unavailable interaction | Lock controls while preserving previous valid choices; no incomplete interactive draft | Lock controls only |
| Diagnostic visibility | Short repair message for catalog agents when blocked; exact keys/rules in authorized admin/Preview | False/skip modes quiet for agents |

Missing classification is an evaluation decision, not a storage transformation. Preserve the original string, including whitespace. `"0"`, numeric-looking text, units and ranges are present strings. Existing non-text scalar/nested values have no newly approved type semantics: retain the current false-result compatibility until the later typed-data design, rather than silently classifying every unsupported value as missing or parsing it.

For `Pressure = 10 OR Material = Brass`, with Pressure absent and Material Brass, normal OR evaluation can match; the strict OR alternative fails that group. Under the selective unavailable threshold, the known true branch settles the OR and configuration need not be blocked. For `Pressure = 10 AND Material = Steel` with Material Brass, the known false branch settles the AND without needing Pressure. These approved choices are specified for future implementation; current engine behavior remains unchanged.

Selective blocking needs a distinct internal Missing/Unknown result until Boolean groups resolve it. It must not depend on branch order or short-circuit accidents. Strict OR-group behavior is distinct from skipping the whole rule: an outer OR group can still have another satisfiable branch unless its own strict missing-data rule applies. Do not reuse the ordinary false value to stand for unknown data before resolving the selected policy. Negative-comparison options apply to the continue/false mode; unavailable mode does not silently use them to manufacture a known result.

### Blocked-state lifecycle and diagnostic boundary

When unavailable mode blocks, return no final configuration code and disable the configuration controls. Preserve the previous valid choices in server-owned state; do not reset them merely because a fact is absent. Reject selection intents server-side while blocked, including direct/forged Livewire actions. Do not treat arbitrary client option IDs as a preserved valid draft. Once data is repaired or the policy changes, re-evaluate retained choices through the normal legal-option checks before making the controls usable again. New incomplete interactive drafts are excluded from this phase.

Catalog agents receive only a short repair message when blocked. False/skip modes do not add missing-data messages to that surface. Authorized admin/Preview can inspect deduplicated missing keys and affected active rules, the resolved mode/options and why the selected threshold did or did not block. Keep those detailed diagnostics out of ordinary agent response payloads, rather than only hiding their HTML. Apply the same effective server policy and result projection to first load, later requests and repaired-state evaluation. No unit interpretation or numeric operators are introduced.

### Acceptance examples for future implementation

All examples refer to active rules unless explicitly stated. `Missing` below means an absent key or null; blank-text classification is off unless enabled in the row. Outcomes describe the rule condition, not a guarantee that unrelated configuration requirements are complete.

| Product facts / rule | Policy option | Required outcome |
| --- | --- | --- |
| Pressure missing; `Pressure = 10` | Condition false | Condition false |
| Pressure missing; `Pressure != 10` or `Pressure not in [10, 16]` | Condition false; negative fallback false/true | False by default; true only with the explicit negative fallback |
| Pressure missing, Material Brass; `Pressure = 10 OR Material = Brass` | Condition false; normal/strict OR | True with normal OR; false with strict OR |
| Pressure missing, Material Brass; `Pressure = 10 OR Material = Brass` | Skip affected rule | Skip the entire rule despite the true Material branch |
| Pressure missing, Material Brass; `Pressure = 10 OR Material = Brass` | Unavailable; any missing/only undecidable | Block with any-missing threshold; do not block with selective threshold because OR is known true |
| Pressure missing, Material Brass; `Pressure = 10 AND Material = Steel` | Unavailable; only undecidable | Do not block: AND is known false |
| Pressure missing, Material Brass; `Pressure = 10 AND Material = Brass` | Unavailable; only undecidable | Block: AND remains unknown |
| Pressure missing, Material Steel; `Pressure = 10 OR Material = Brass` | Unavailable; only undecidable | Block: OR remains unknown |
| Pressure `""` or whitespace; `Pressure != 10` | Condition false; negative fallback false | Blank option off: present text compares unequal and is true; option on: missing fallback is false; stored text is unchanged |
| Pressure `"0"`; `Pressure = 0` | Any mode; blank option either setting | Present text and exact comparison true; no missing-data block |
| A missing key occurs only in a disabled rule, or is not referenced | Skip/unavailable | Do not skip/block active rules for that key |

Also cover nested AND/OR groups and reversed branch order, whole-rule skipping without weakened conditions, valid-state → blocked-state → repair transitions, denied selection intents with unchanged retained choices, hidden diagnostic payloads for agents, the same global policy across different Configurators, and cache invalidation after settings change. Per-Configurator payloads must not override the global missing-property policy. These are independently specified expectations for future tests; the existing Product-editor test results do not verify the proposed policy.

**Approved scope / cost design:** resolve the global settings once while building server-owned evaluation input, with no SQL query or cache lookup inside individual condition evaluations. Use a versioned cache plus request memoization; a warm read still depends on the cache backend (this local environment currently uses file cache). Invalidate on an admin change; do not rely on rarity of changes for correctness. Leave existing unrelated Configurator data intact and do not read `policy_overrides` as a missing-property override. If compiled definitions are cached later, include global policy version/invalidation there too. The current loader recompiles definitions from database data; a complete definition cache is a separate optimization and is not required for this policy.

The expected added runtime work is an in-memory enum branch and, for skip/block/diagnostics, one pass over unique referenced Product keys plus bounded rule membership checks. Selective blocking/strict OR options add Boolean-group resolution and a broader regression matrix; their implementation is more involved than the three baseline modes, while still requiring no per-rule database access. This is an architectural estimate, not a measured timing result. Runtime implementation remains pending the coordinated Product-data/field-source design and its implementation plan. An interactive policy example illustrates these semantics; it does not replace the still-pending storage/import architecture examples.

## 15. Eligible Product facts and fixed source headings — 2026-10-10

The approved fact sources are Properties and Extra data, for Group filters/presets, card-field choices and Configurator rules. This is eligibility for explicitly configured fields, not automatic creation of filters or publication of every source column. Parts retain their Product/drawing meaning. Future import mappings keep ordinary source headings as exact keys and choose the bucket only. Do not silently trim, case-fold, rename or standardize keys; display labels may be friendlier without changing stored references. Source values remain literal text and no unit conversion is implied.

### Verified current gaps

- `GroupForm`, `SaveGroupSettings`, `BatchCatalogChanges`, `CatalogPolicy` and the rule compiler still depend on `CatalogImportParser::propertyKeys()`. Group filter limits and card-selection limits are also tied to the observed 18-field layout; decouple those assumptions when implementing variable-field support, without treating the number of suggestions as an eligibility limit.
- `CatalogDiscovery` reads Properties JSON only. The cached `BuildCatalogSnapshot` path likewise projects selected Properties keys. `CatalogCards` fetches `id`, `product_code`, `properties`; both `CatalogCardDisplay` and Product-card Blade read only Properties. Changing a picker alone would therefore produce accepted definitions with missing runtime data.
- `ConfiguratorDefinitionLoader` passes only `$product->properties` to server-owned evaluation input. Supply the required non-Part facts together before evaluation; do not fetch Extra data or settings inside each rule condition. Admin/Preview must resolve the same facts as the agent surface.
- The canonical source map has a blank header at position 48, currently represented as `_unnamed_column_48`. `CatalogImportTest` verifies preservation of that entry. Thus fixed source headings need an explicit blank-header exception, rather than an assumption that every heading is nonempty and unique.
- The JSON structure permits the same key in Properties and Extra data. The existing reimport test deliberately preserves `app_note` in both buckets. This does not prove live imported data contains conflicts, but it rules out assuming cross-bucket uniqueness without an audit and a defined conflict rule.

### Approved identity and heading rules

1. One logical non-Part field key works across Properties and Extra data. A `Model` filter/rule applies whether a Product stores that key in one bucket or the other. Retain display-bucket/source provenance and surface conflicting copies on a single Product for review. Do not silently pick a winner with an array merge or fallback expression. Existing saved key references and literal values must survive the chosen design. Identical copies can resolve to the same fact without discarding their source provenance; different typed/literal values require review.
2. Import review may propose distinct generated keys for blank/repeated headings, such as `_unnamed_column_48`, retaining every original heading, column position and value. Ordinary headings remain exact keys. Generation must avoid collisions with real headings and other generated keys; retain the accepted mapping rather than assigning a different identity silently on reimport. A changed/ambiguous layout needs renewed review. Do not overwrite repeated headings or discard blank-heading values.

These are approved design requirements, not runtime changes or approval of extra models/tables. Section 16 compares storage options against the application's actual query/cache/rule boundaries.

### Implementation acceptance boundaries

- A configured Extra data fact works in picker/save/reload, snapshot filtering, matching-card comparison/rendering and server rule evaluation; it is not lost at an intermediate Properties-only read.
- Observed/saved/future keys remain selectable when no current Product has the field. Import-map membership no longer decides eligibility. Do not broaden the Product value editor into key editing.
- Retain exact keys containing spaces, quotes, punctuation or non-ASCII text, with safely encoded/bound JSON paths. Moving a non-Part key between presentation buckets does not rename its saved filter/card/rule reference.
- Reimport preserves omitted fields and clears explicit blanks under Replace. Review catches source-column/key conflicts before writes. When a supplied logical key changes bucket under Replace, review and apply one replacement fact atomically rather than retaining an old contradictory copy; preserve its original source lineage.
- Discovery keeps cached Group snapshots and the existing warm card-request query contract; project only required data and expose only configured card/filter facts. Do not add per-card or per-rule queries. Invalidate/rebuild affected cached revisions when Product facts or definitions change.
- Update existing MySQL/SQLite projection tests to allow required Extra data facts while still excluding unrelated JSON data. Cover the same key in different buckets across Products, identical/conflicting copies within one Product, generated-key collisions, stable reimport identity and reviewed bucket moves. Current tests are not evidence that this redesign is implemented.

## 16. Product fact storage comparison — research and examples, 2026-10-10

This is a design comparison, not a migration specification or implementation authorization. Installed versions verified for this review: Laravel **13.35.0**, Filament **5.9.0**, Livewire **4.4.7**, Boost **2.10.2**; the configured local database is `configurator_catalog_dev`, MySQL **8.0.46**. Boost schema/query tools confirmed the current three Product JSON columns and read aggregate sizes without modifying records. There are **501 Products**, **9,018 Properties entries**, **7,515 Extra data entries**, and **14,028 Parts entries**; the maximum non-Part key count is **33** and the maximum serialized Properties-plus-Extra-data length is **989 bytes**. This sample is the known fixed-layout import, not coverage of unseen exports. These are storage-shape observations, not query timings, network measurements or benchmarks.

### Common contract for all storage candidates

- Main Product identity, Group relation and status stay unchanged. Parts remain Product/drawing-owned JSON for this phase; a shared Part model or separate Part-position table is not required by this comparison.
- Use one exact non-Part key and retain the original typed value. Strings, whitespace, unit/range text, null, booleans, numeric scalars and nested values must not be coerced during migration/import. Only approved text behavior participates in existing filter/rule comparisons; later type/numeric semantics remain deferred.
- Keep Properties/Extra data as presentation/source placement, not two identities. No separate global property-definition table is necessary for observed/saved/manual-future-key suggestions. A Product need not contain a fact for an admin to author its future filter/card/rule reference.
- Resolve Product facts into a server-owned key/value map before engine evaluation. The engine consumes that map and the resolved global policy; it never loads a model relationship or queries storage inside an individual condition.
- Keep Group snapshots for discovery and configured card-field projection. Preserve the current warm card-request query contract; do not add one eager-loading query per card or a new relationship query by default. Preserve absent Products/facts in projection rather than accidentally dropping them with an inner join.
- Reviewed import mapping/source lineage must retain file identity, layout/mapping identity, source heading, source column position and effective key. Neither the current Product JSON nor a fact-row table alone preserves the full flattened export's provenance. Reusable reviewed mapping profiles are approved, independently of Product storage. Their exact persistence/versioning and review-metadata retention belong in the later import plan; the approval does not select an extra import model or a raw-file retention period.

### Confirmed goals and separate read workloads

The operator confirmed **both performance and clearer data structure** as goals. Catalog filtering is Group/branch-scoped; do not infer a requirement for catalog-wide filtering across unrelated Groups. Admin table filters may cross Groups and must be evaluated separately. An alternative future catalog filtering experience remains future scope, not an approved requirement of this redesign.

Current source reinforces this distinction: `BuildCatalogSnapshot::build()` selects active Products for one `group_id` and requires a leaf Group; the current `ProductsTable` has a separate Filament query with a Group relationship filter. Preserving the operator's Group/branch context does not authorize adding filters to non-leaf Groups or changing which descendants they include. The leaf/branch navigation behavior remains its own existing contract.

| Workload | Required boundary / comparison |
| --- | --- |
| Catalog filter interaction | Retain cached Group discovery; compare cold snapshot rebuilds and warm click-to-settle behavior within the existing Group context |
| Catalog matched cards | Load only matched Products and configured display facts; preserve the warm query contract and record payload/server/browser timings separately |
| Product configuration | Load required facts together before evaluation; compare input-loading cost separately from the unchanged in-memory rule execution |
| Admin Product filters | Preserve authorized Filament query scope; measure actual implemented filters separately, including cross-Group scope where supported/requested |
| Future alternative catalog browsing | Excluded from this decision; do not invent a cross-Group catalog path to justify a storage migration |

The existing 501-Product sample comes from one known export layout. The operator now estimates approximately **50 leaf Groups**, most with **50–200 Products**, and roughly **5–10 larger Groups** with around **100–1,000 Products** and a possible upper case near **3,000 in one Group**. These are planning estimates, not configured limits, a confirmed total or representative coverage of unseen export shapes. Do not extrapolate a JSON/table speed result from this count information or from hypothetical cross-Group catalog queries. Structure can improve through one shared data contract even when physical storage stays unchanged. Neither additional rows nor another model establishes a performance advantage without representative reads/writes and measurements.

### Representative scale scenarios — proposed comparison, not benchmark results

| Group size | Why include it | Compare |
| --- | --- | --- |
| 100 Products | Typical Group within the reported 50–200 range | Cold snapshot build, warm filter/card behavior and cache refresh after an edit/import |
| 200 Products | Upper end of the typical range | Same outcomes/query boundaries with a larger ordinary Group |
| 1,000 Products | Larger Group case | Snapshot build/serialized payload, matched-card projection and import/update throughput |
| 3,000 Products | Possible upper case, not a hard maximum | Same workload with selective and broad matches, reporting SQL/server/browser costs separately |

Use identical Product values, configured keys, constraints and match sets when comparing storage; vary observed/saved/future keys and export layouts without changing outcomes. The number of configured filter/card fields and their value cardinality matter as well as the Product count. Their future distribution is not yet established by the single known export. A table comparison must account for projected SQL rows, indexes, joins and PHP materialization, not only the number of SELECT statements. Do not generate benchmark data in an active or production database; any later benchmark must use an isolated disposable dataset and an explicitly agreed probe.

For a simple size illustration only, 3,000 Products with 33 non-Part entries each would represent 99,000 Product-field rows under Option B, compared with 3,000 Product data objects under JSON storage. The 33-entry width comes from the observed local maximum; it is not a guaranteed future width. This arithmetic does not measure latency, memory or on-disk size. Configured projections can read fewer entries under either design.

The Product-data work for an individual configuration remains scoped to that one Product: load its required facts together, then evaluate in memory. A Group containing 3,000 Products must not cause a scan of all 3,000 Products for every rule or selection intent. Evaluate policy/definition complexity separately from the storage choice; retain the existing server-owned input boundary.

### Option A: existing JSON plus a shared fact resolver

Keep `properties`, `extra_data` and `parts` on Product. A shared resolution operation reads the two non-Part buckets, returns one fact per exact key and reports conflicts instead of overwriting one source. Use the same rule in picker suggestions, write validation, query/snapshot projection, cards, Product save/import and engine input; do not create independent PHP and SQL precedence rules. The resolver is an application boundary, not a required model or registry.

This avoids a data migration and preserves the completed editor's storage contract. Snapshot building projects configured keys from the two JSON sources with safe encoded/bound paths; cache revisions still control discovery refresh. Product saves/imports must validate cross-bucket identity and atomically replace a moved logical fact, since JSON columns cannot directly enforce uniqueness across their keys. Read-only auditing/review of existing conflicting copies precedes activation; no automatic cleanup is authorized.

MySQL's native JSON representation supports element lookup, but generic arbitrary object keys do not acquire a reusable index merely because their column is JSON. Targeted scalar indexes use defined extraction expressions. Thus retaining JSON does not mean adding a generated/indexed column for every newly observed export heading. The existing cached Group snapshot reduces the need for direct SQL facet queries on every click. [MySQL 8.0 JSON storage/indexing](https://dev.mysql.com/doc/refman/8.0/en/json.html), [generated-column JSON indexes](https://dev.mysql.com/doc/refman/8.0/en/create-table-secondary-indexes.html).

### Option B: one Product-fact row table, Parts JSON retained

Proposed shape only: one owned row per Product/non-Part key, containing Product ID, exact field key, Properties/Extra-data placement and the original typed value (for example a JSON value), plus source provenance/reference from the reviewed import. An absence is no row; an explicit null is a present row with null value. A unique Product/key constraint expresses one logical fact without a separate property-definition registry. Key/value equality must preserve case, Unicode, whitespace and literal value distinctions under the chosen MySQL/SQLite collation/types.

This makes uniqueness and per-fact writes explicit and can support reusable field/value query indexes across varying exports. It requires a migration, conflict audit, fixed-key editor adaptation, importer rewrite and coordinated revision/cache updates. Never keep the table and Product JSON as two editable authorities. Preserve original/raw source lineage separately from the current authoritative values.

Rows are not automatically faster. Long literal text or JSON values cannot simply receive an unlimited ordinary full-value index; a measured prefix/hash/extracted-text design may be needed, retaining full exact-value checks. Do not infer units or canonical numbers as an indexing shortcut. [MySQL long-value index strategy](https://dev.mysql.com/doc/refman/8.0/en/optimize-blob.html).

For the engine, batch-load the required fact rows once and build the same in-memory map as Option A. For cards/snapshots, one joined/projected read can assemble selected facts while preserving the existing request query boundary; ordinary `with('facts')` would add a relationship SELECT and must not silently change that contract. Constrain selected columns and retain relation keys if eager loading is used elsewhere. [Laravel 13 constrained eager loading](https://github.com/laravel/docs/blob/13.x/eloquent-relationships.md#constraining-eager-loads), [specific-column eager loading](https://github.com/laravel/docs/blob/13.x/eloquent-relationships.md#eager-loading-specific-columns).

### Additional discussion candidate: one Product-owned data record

A third candidate separates the Product's non-Part data into one owned record per Product while retaining JSON values and display-bucket information there. Parts can remain Product/drawing-owned JSON as in the first two options. This is not a global property registry and does not imply a reusable shared field-definition model. Product identity, Group relation and status remain on Product.

This can express separate data ownership through a one-to-one model without one row per field. It still permits different keys per Product, still needs the same exact-key/conflict contract, and still relies on JSON extraction for field-level SQL. It requires a migration/backfill and coordinated editor/import/read changes. Separate ownership alone does not provide per-field uniqueness, general field/value indexing or faster reads; the joined/projected loading strategy must preserve the existing card/query and engine boundaries. Treat it as a structure candidate, not a selected route or a measured optimization. [Laravel 13 one-to-one relationships](https://github.com/laravel/docs/blob/13.x/eloquent-relationships.md#one-to-one).

This candidate has not been added to the earlier two-option HTML example. If developed into a proposed storage design, show its concrete records and read/write behavior before architecture implementation, following the operator's example-before-change requirement.

### Concrete examples to preserve in every design

| Example | Required result |
| --- | --- |
| Product A has Properties.Model `D-060`; Product B has Extra data.Model `D-061` | One `Model = D-061` filter/rule targets Product B regardless of bucket; no per-condition query |
| Model exists in both buckets with different literal values on one Product | Review conflict; do not choose a bucket silently or treat it as missing data |
| Replace supplies Model in Extra data after it previously lived in Properties | One reviewed replacement fact, new display placement, saved `Model` references unchanged |
| Replace supplies blank Pressure, but export omits Strainer | Pressure clears; Strainer survives; unrelated Parts/identity/status remain unchanged |
| A future key has no Product values yet | Its admin reference remains valid; global missing-property policy controls rule evaluation |
| Both Products have Part10, with different material/name text | Two Product/drawing positions; no shared Part identity or inclusion in non-Part fact suggestions |
| Two headings are Model and another is blank | Ordinary Model remains unchanged; review assigns collision-free keys to other occurrences and retains heading/position lineage |

### Reusable import mapping profiles — approved scope

The operator approved reusable reviewed profiles and requested further storage discussion. A profile retains the accepted export layout's source headings/positions, effective keys (including reviewed generated keys), and Properties/Parts/Extra-data destinations. Ordinary headings remain fixed keys. Reuse must preserve the accepted identity rather than regenerate blank/repeated-heading keys for each upload.

The import plan must distinguish the saved reusable mapping from the review of a particular upload. Reusing a profile supplies the mapping; each upload still receives the approved compact summary, Add only/Replace choice and expandable changes before Apply. New/changed/ambiguous columns require renewed mapping review. A layout match must not depend only on the column count. Do not silently remap a changed layout, drop new columns or use profile reuse to bypass source/map validation.

The later implementation plan must specify a stable profile/layout identity and which accepted mapping version each reviewed upload uses. Apply must validate the actual source and reviewed mapping snapshot; editing a reusable profile must not retroactively change that snapshot. Follow the existing importer authorization, reviewed-input hashes, locking, transaction and cache-revision boundaries. Exact profile persistence, versioning UI and metadata retention remain design work; no profile table, import history UI or variable-layout parser has been implemented.

### JSON selected for planning; implementation review remains open

**Latest operator decision, 2026-10-10:** plan the shared Product-data contract and corrected consumers using the existing JSON first. Storage remains unchanged until the implementation plan is reviewed. This supersedes the earlier request to discuss the route further, while preserving both performance and clearer structure as goals and the Group/branch versus admin workload distinction. Reusable reviewed import profiles remain separately approved. The fact-row and one-record-per-Product JSON candidates remain comparison material; neither is selected for implementation. Route selection is not a benchmark result.

**Selected planning route:** make the shared Product-data contract and corrected consumers the first phase, retaining Option A's existing JSON. The reported scale, cached Group discovery and one-Product in-memory evaluation do not by themselves establish a need for per-field rows or a second JSON table. This choice follows the existing read boundaries and change scope, not a claim that JSON is faster. Option B remains a possible later domain-storage choice if explicit per-field ownership/database integrity is preferred. The one-owned-record JSON candidate separates responsibility but does not itself resolve JSON indexing or cross-bucket identity enforcement.

The concrete foundation plan is [PRODUCT_DATA_IMPLEMENTATION_PLAN.md](../PRODUCT_DATA_IMPLEMENTATION_PLAN.md). It covers exact fact resolution, lossless typed JSON handling, safe query projection, snapshots/cards, observed/saved/manual-future-key authoring, the existing Product editor/importer and server-owned engine facts. Flexible reviewed Import/profiles and configurable global missing-data behavior retain their accepted requirements as separate engineering plans. On 2026-10-13 the operator approved rule-scoped conflict blocking and retention of saved values with observed-only new values; section 17 records both decisions. The plan includes their enforcement and UI lifecycle and awaits implementation review, rather than further answers to these two policy questions.

Additional verified implementation constraints: the saved `property_key` references currently use case-insensitive `utf8mb4_unicode_ci` at length 100; the plan proposes an exact-comparison reference-column migration, separately from Product JSON storage. Installed Filament TagsInput trims new tags in JavaScript, so literal keys should use `TextInput->trim(false)` plus datalist suggestions and a simple repeater for ordered lists. Product details also hard-code Part1–Part28; the foundation should display the Product's actual saved Parts keys without treating positions as shared Part identities. These findings came from installed source and read-only local schema inspection; no schema or runtime changes were made.

Before a final migration decision, compare representative varied exports and the proposed Group sizes, snapshot cold rebuilds, warm card reads, edit/import throughput, SQL plans and authenticated browser click-to-settle behavior. State timings separately and preserve exact outcomes/query limits. A derived searchable projection can be considered later if measurements justify it; it must not become a second write authority. A decision to keep JSON is not permission to retain the first-export allowlist or Properties-only consumer assumptions.

The examples illustrate both storage shapes with the same observable filtering/rule outcomes. No schema, runtime API, import behavior or existing Product records were changed for this research.

## 17. Approved conflict and retained-value policies, 2026-10-13

**Existing conflicting non-Part copies:** show the conflict in authorized admin tooling; exclude that key from resolved catalog facet/card values. Block configuration only if an active rule in the current evaluation scope references the conflicted key. An unused conflict, an inactive rule or a public-context rule excluded from dashboard evaluation must not hide the Product or block unrelated configuration. Identical typed copies are unambiguous; different literal/typed copies have no silent bucket winner. Newly introduced editor conflicts fail validation; imports introducing conflicts fail review before writes.

The foundation plan's Task 6 specifies the blocked Result, guard and controls. Check nested active rule references before settling or accepting an intent; an otherwise true OR branch does not bypass the conflict guard. Lock controls, keep still-valid prior server-confirmed choice IDs without claiming current rule legality, and return no final code. First-load blocking invents no defaults. Product choice/Refresh remain usable; repair plus Refresh resumes normal evaluation of retained choices. Agents receive a short repair message, while authorized Preview shows the exact keys and affected rules. Facts/conflicts remain server-owned and evaluation adds no per-rule queries. This implements the conflict reason only; configurable missing-property settings remain the accepted separate follow-on.

**Group preset/filter values:** preserve already saved values, labels and order when no current Product has those values. Newly added values must be observed in the relevant Group's current catalog vocabulary. Retention applies only to the original same owned, locked row and exact unchanged key, never a client flag, foreign row, draft cache or old values carried to a new key. Explicit removal remains available. Public controls continue to expose observed values; a retained preset can have no current matches.

An admin may still type a future **field key** for filters/card fields/rules before any Product contains it. An empty future-key filter is valid; a new preset needs at least one observed value, while an existing preset may retain its saved values. This restriction concerns values in Group settings; it does not limit Product text editing or introduce an observed-values requirement for rule operands. Product input suggestions remain off. Task 4 covers hydration, strict server validation, reactive draft preservation and atomic failure/no-op tests.

Both domain questions are closed. The implementation plan, including its reference-only exact-comparison migration, still awaits review and execution authorization. This checkpoint changes only documentation; no runtime implementation, data changes, tests, benchmarks, commit or push occurred for these decisions.
