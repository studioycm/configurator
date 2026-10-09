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
| Registered Product property keys | 18 |
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
