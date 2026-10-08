# Handoff to “Research Filament 5 admin UX”

Prepared 2026-10-07 in `/Users/studioycm/Herd/configurator`.

**Public filter UI update, 2026-10-08:** The count now uses a light box with a larger number above `profiles`, beside an equally tall `RESET` button. `Sub Groups` and its options share an inline row; clicking the selected option clears it. Separate Sub Group/Filters Clear controls were removed. Filter titles now use accessible section headers, with aligned header/first-button tracks retained. This supersedes the earlier active-preset no-op and fieldset/legend presentation only; card settings, aggregate saves, local computation and independent server card delivery remain as described below. Inspect current Git state rather than treating the historical working-tree note below as current.

## 1. Purpose and current status

The [Admin and Shared Filament UX plan](ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md) was written before the latest Product-card implementation. Reconcile its Group form, batch-edit, appearance and cross-surface work with this handoff before modifying those areas. Preserve the earlier plan's requirements and detail; incorporate the implementation rather than replacing it with the old baseline.

**Implemented locally:** the selected compact card design, six per-Group appearance/spacing settings, all-match differing-property comparison, guarded result notices and responsive filter-heading alignment. The existing fast local filters and independent server card delivery remain intact.

**Not implemented by this work:** global Admin Appearance persistence/page, P0–P12 administration improvements, new statuses, property colors/icons, property settings page, Livewire islands, pagination or Pest browser infrastructure. Those retain their existing approval/decision status. This handoff does not authorize a new phase or publication.

At handoff, the primary branch is `master`, HEAD is `f906004` (`Keep large catalog results responsive and update admin label assertions`), and the card implementation is still in the dirty working tree, including the new untracked `CatalogCardDisplay.php`. No commit, push or deployment of the appearance patch was performed here. The workspace also contains unrelated shell/navigation/resource/package changes. Inspect the current state again; do not reset, stash or broadly stage it.

Boost reconfirmed PHP 8.4, Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7, Flux 2.20.1, Pest 5.3.0 and Boost 2.10.2. Recheck installed versions before using new APIs. `.ai/rules` is currently absent; check again before editing.

## 2. Read these as the updated baseline

1. [AGENTS.md](../../AGENTS.md) and [docs entry point](../README.md).
2. [Card appearance implementation plan](CARD_APPEARANCE_IMPLEMENTATION_PLAN.md), especially sections 4–7 and section 13's actual implementation/verification evidence.
3. [Filament admin contract](contracts/FILAMENT_ADMIN.md): the two governing Group-settings amendments at its top.
4. [Public catalog contract](contracts/PUBLIC_CATALOG.md): local computation, Medium freshness, independent cards and the appearance amendment.
5. [Earlier admin UX plan](ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md), reconciled using section 7 below.
6. [Navigation plan](NAVIGATION_IMPLEMENTATION_PLAN.md) when touching the shared shell; do not reimplement or overwrite its separate work.

The [appearance kickoff](CARD_APPEARANCE_KICKOFF_PROMPT.md) is provenance for the completed implementation, not an instruction to execute it again. Older comma-only card, Light freshness, joint-rendering, islands and pagination recommendations are superseded. Historical query measurements are not the current card budget.

## 3. Group form: exact new controls and storage

The existing leaf-Group editor now has these sections:

- **Product cards:** Properties to display, Cards per row, Product update delay (ms), Show cards when results are at most.
- **Card appearance:** Only differing properties, Show property labels, Label and value layout, Property columns.
- **Card spacing:** Block padding, Inline padding.
- **Catalog filters** and **SubGroups/Presets:** existing structured definitions and their existing save ownership.
- **Results:** legacy pagination section remains hidden; its saved values are retained.

All six new keys live inside the existing `Group.result_settings` JSON. Their Filament state paths start with `catalog_settings.result_settings.`:

| Key | Form label / component | Stored type and allowed values | Default |
| --- | --- | --- | --- |
| `card_only_differences` | Only differing properties / Toggle | boolean | `true` |
| `card_show_labels` | Show property labels / Toggle | boolean | `false` |
| `card_property_layout` | Label and value layout / Select | seven finite strings below | `inline_space_between` |
| `card_property_columns` | Property columns / Select | integer `1` or `2` | `2` |
| `card_padding_block` | Block padding / TextInput, px | integer `0..16` | `4` |
| `card_padding_inline` | Inline padding / TextInput, px | integer `0..20` | `6` |

The exact layout keys and labels are owned by `CatalogPolicy::CARD_LAYOUTS`:

| Key | Label |
| --- | --- |
| `inline_start` | Inline · Start |
| `inline_center` | Inline · Center |
| `inline_space_between` | Inline · Space between |
| `above_start` | Label above · Start |
| `above_center` | Label above · Center |
| `below_start` | Label below · Start |
| `below_center` | Label below · Center |

Layout stays editable and is retained when labels are hidden. Do not disable dehydration or clear its stored value as a side effect of the labels toggle. The form uses native components and the existing staged Save; no new per-field persistence path was introduced.

Existing settings remain independent:

- `card_properties`: searchable multiple Select of registered keys, in saved order. Empty means use the Group's filter properties in configured order. Helper text now describes stable positions, not comma-separated values.
- `cards_per_row`: maximum `1..6`, default `4`; the actual grid adapts to container width. This is separate from property columns.
- `max_results`: `1..24` or `all`, default `all`; cards appear only when eligible.
- `products_debounce_ms`: nonnegative integer, multiples of 100, default `0`. It delays card requests only. The maximum accepted value remains `2147483600`.
- `default_page_size`, `allow_page_size_change`, `page_size_options`: legacy storage remains intact; public pagination is inactive and its admin section stays hidden.

These are current source facts, not proposed default values for P9's global appearance system.

## 4. Protect the aggregate save and revision contract

`EditGroup` still owns one transaction and `CatalogRevisions::batch()` around `SaveCatalogGroup` plus `SaveGroupSettings`. It fills settings through `GroupForm::settingsState()`, maps validation errors back to `data.catalog_settings.*`, then dispatches the existing saved events and refills after successful Save.

Preserve these invariants when adding tabs, previews, batch editing or compact controls:

1. `manage-catalog` authorization, existing owner/definition locks and full-draft validation stay in domain actions.
2. `SaveGroupSettings` requires the `filters` and `sub_groups` lists and a `result_settings` object. **Only the inner result-settings object supports partial merging.** It is not a generic patch endpoint for omitted definition lists. Never pass empty lists merely to update appearance: that would mean removing definitions.
3. Merge unsubmitted result settings with the current normalized settings. Keep canonical booleans/integers, the exact key allowlist and existing registered-property validation. No raw JSON/model updates from a table cell or generic batch helper.
4. A real combined change advances the Group's catalog revision once. Unchanged saves and failed/rolled-back drafts do not. Saving previously implicit defaults may canonicalize storage without changing the effective revision.
5. New appearance defaults resolve on read without a migration, forced save or bulk rewrite of existing Groups. There is no ancestor inheritance added by this feature.
6. Keep stable Filter/Preset IDs, ordering, value labels, unrelated settings and legacy pagination through Save/Reload, record switches and partial edits.
7. Future batch edits still need unchanged/set/clear intent and mixed-value handling. An untouched field must not become the first selected Group's value. Preserve the earlier all-or-none and stale-preview contracts.

**Admin performance distinction:** public filter clicks no longer call `CatalogDiscovery::prepare()`. However, `GroupForm::settingsState()`, property-choice callbacks and `SaveGroupSettings` still use `CatalogDiscovery::vocabulary()` for admin options/write validation. Do not claim vocabulary work disappeared throughout the application or remove it because the public path is local. Measure those admin paths independently if deferral or form optimization touches them.

## 5. Product-card semantics the admin must accurately explain

The chosen design is option 1 from the final three-interpretation board: compact neutral full-width property cells, not the earlier removed interpretation. It has:

- One native link covering the entire card; no nested interactive controls. Product code is the heading, followed by real Group subtitles and a decorative navigation arrow.
- Distinct ordered facts in one/two columns; an odd final item spans both columns. Complete values wrap, missing slots align, and cards in the grid keep consistent heights.
- Visually hidden labels remain accessible as `dt` text; values and labels are escaped. Hidden labels do not mean anonymous facts for assistive technology.
- Responsive one–six card columns, fitting available width with a 180 px minimum; compact property/header typography and bounded per-Group padding.

**Only differing properties** compares every eligible matching Product, before 24-card chunking. It is not merely “hide selected filters,” not limited to visible cards, and not limited to the first chunk. Shared unselected properties disappear too. There is no separate hide-selected setting.

Exact nonempty strings are preserved, including `"0"`, leading zeros, case, quotes and Unicode. Missing/null/empty/non-string values are one missing category. A populated value versus missing is a difference; the corresponding card slot displays an em dash. Globally unpopulated fields disappear.

One matching Product has no differing fields when difference mode is on, but its card stays navigable. No fields produces one result-region notice, with priority: no populated configured properties, then single match, then shared properties. Do not add a repeated notice to every card or manufacture a fallback property.

Property colors/icons, their mapping/settings page and additional appearance interpretations are future ideas, not implemented or approved controls. The navigation arrow is retained; it is not a property-icon system.

## 6. Runtime and ownership boundaries relevant to admin changes

| Mechanism | Current owner | Consequence for the next session |
| --- | --- | --- |
| Choices, compatibility, newest-first repair, exact displayed total, filter notices and `d[...]` history | Pure JS engine + Alpine | No filter-state request/SQL; do not introduce entanglement or live filter models |
| Threshold decision, card delay, stale-response guards and loading/errors | Alpine | Cards are independent; immediate filter feedback must not wait for them |
| Card matching/verification, differing-field computation and HTML | Protected Livewire JSON action + PHP services/Blade | Warm budget: revision SELECT plus one Product SELECT, no SQL counts; comparison adds no queries |
| Card insertion/layout | Alpine + CSS | One response contains all chunks; insert up to 24 between frames, with stable keys and eventual completeness |
| Freshness | Normal authenticated dataset GET, shared browser scheduler | Medium 60-second confirmation policy; unchanged `304` reads no Products; successful current card responses also confirm freshness |
| Dataset cache/revisions | Existing snapshot service and writers | Schema 1 additive metadata, plain arrays, one Group cache entry, locks outside transactions, bounded retry and consistent reads |
| Filter heading alignment | Private browser measurement + CSS | At initialization/font readiness/metadata changes/width changes/pageshow; zero per-click heading reads |
| Sidebar, theme and responsive behavior | Existing shell/Flux/Alpine | Preserve component ownership; do not import the entire admin stylesheet into public pages |

Raw filter rows stay outside Alpine's large reactive proxy and repeated public Livewire state. Card-only values are absent from snapshot rows. New small appearance settings are metadata; old-shaped cache entries receive safe defaults without a blanket rebuild/schema bump.

The enclosing `GroupShow` remains class-based with locked Group identity, protected boot-injected card service and no catalog query in `boot()`. `#[Json]` skips normal component rendering; the card service explicitly renders escaped Blade fragments. Alpine owns the `wire:ignore` regions. `cardPropertiesNotice`, cards and freshness confirmation accept only current responses; stale work must not replace them.

Zero debounce can still produce one card request for each eligible effective click. Ignoring a stale result does not cancel already running server work. There is no browser card-response cache. Recent discussion suggested researching bounded repeat-result caching or slim card JSON/browser templates, but **neither is implemented or selected for this admin UX task**.

Preserve the settled public behavior: singleton presets hide their corresponding manual field automatically; multivalue presets keep it visible. Force-hide and numeric per-option counts stay canceled. Clear retains the preset, Reset clears it. Public pagination and islands remain deferred.

## 7. Reconcile these parts of the earlier admin plan

| Earlier area | Required adjustment to its implementation baseline |
| --- | --- |
| P0 / surface inventory | Record the new Group sections and six keys as existing implementation. Read current dirty diffs; do not start from the earlier schema or treat local work as already released. |
| P1 / shared density, R03/R36 | Protect the dedicated public card/filter classes and heading measurement. Global table padding targets 4–8/8–12 px are not substitutes for Group card padding 0–16/0–20 px with 4/6 defaults. Scope changes and preserve focus/selected/compatibility states. |
| P5 / properties and values, R18 | `card_properties` and preset allowed values already use searchable multiple Selects. Preserve these. Filter order/labels have structured metadata; any adjacent batch selection must retain existing row IDs/settings rather than replacing them. Mapping checkboxes remain unchanged. |
| P6 / Group presentation batch edits | “Existing result_settings fields” now includes these six keys. Enumerate them explicitly in the code allowlist and preview; preserve nonedited fields and the full definition lists. Add meaningful mixed/unchanged/rollback/revision coverage if implementing those batch operations. |
| P7 / Group tabs, R28 | Details / Filters / Presets / Presentation remains a useful proposed layout. Presentation must now include Product cards, Card appearance and Card spacing. Keep leaf visibility, state paths, layout retention, validation-tab reveal and one Save. Do not hide critical errors or strip inactive-tab fields. |
| P7 / deferral | Investigate actual admin query/render work, including vocabulary callbacks. Loaded drafts must remain mounted and intact; public zero-call filter work does not establish cheap admin mount cost. |
| P9 / global Admin Appearance, R32/R36 | Still a separate proposed global Density/Navigation/Dialogs system. It is not the implemented per-Group catalog appearance. Do not move Group keys into its singleton, overwrite Group overrides with global defaults, or repurpose `CatalogContextSettings`. |
| P10/P11 / dialog and column resizing | Preserve Group editor state, validation and Save ownership when resizing or moving controls. Property columns are not table-column management or draggable column widths. |
| P12 / cross-surface acceptance | Add the Group/card roundtrip and runtime/query guarantees below to regression checks. Retain distinct existing tests and separate browser feedback from server/card cost. |
| P8 / statuses | G1 remains unresolved; this appearance work settles no lifecycle policy. Group visibility changes will need revision/freshness and direct-route behavior evaluated under the separately approved status contract. |

These are reconciliation requirements. Do not silently broaden the earlier baseline bulk-action scope, add a global-to-Group inheritance policy, or implement optional work solely because it is mentioned here.

## 8. Source map and changed files

Paths below are relative to the repository root. The Group editor page is an existing protected boundary, not newly created by this patch.

| Area | Files to inspect |
| --- | --- |
| Actual Group controls | [GroupForm.php](../../app/Filament/Resources/Groups/Schemas/GroupForm.php) |
| Existing form fill/save/events/errors | [EditGroup.php](../../app/Filament/Resources/Groups/Pages/EditGroup.php) |
| New settings normalization and strict write contract | [CatalogPolicy.php](../../app/Services/CatalogPolicy.php), [SaveGroupSettings.php](../../app/Actions/SaveGroupSettings.php) |
| Small metadata and trusted card path | [BuildCatalogSnapshot.php](../../app/Services/BuildCatalogSnapshot.php), [CatalogCards.php](../../app/Services/CatalogCards.php) |
| New query-free comparison helper | [CatalogCardDisplay.php](../../app/Services/CatalogCardDisplay.php) |
| Card markup/chunk integration | [product-card.blade.php](../../resources/views/components/catalog/product-card.blade.php), [card-chunk.blade.php](../../resources/views/components/catalog/card-chunk.blade.php) |
| Notice guards, headings and DOM bindings | [catalog-filters.js](../../resources/js/catalog-filters.js), [group-show.blade.php](../../resources/views/livewire/catalog/group-show.blade.php) |
| Dedicated styles mixed with pre-existing shell work | [app.css](../../resources/css/app.css) |
| Updated contracts/discovery | [FILAMENT_ADMIN.md](contracts/FILAMENT_ADMIN.md), [PUBLIC_CATALOG.md](contracts/PUBLIC_CATALOG.md), [docs/README.md](../README.md) |

The other dirty resource, shell, package, generated asset and skills files are not evidence that this card task implemented the independent admin plan. Review shared-file diffs before editing; preserve concurrent work.

## 9. Existing verification and the next session's regression floor

Recorded implementation checks, **not rerun for this documentation handoff**:

- 112 PHP tests / 873 assertions across GroupSettings, CatalogSnapshot, PublicCatalog, CatalogAdministration, ContextSettings and TableConfigurationStandards.
- Nine Node engine/transport checks, Pint, production build and diff check passed.
- Three 100-click local runs and a 100-click remote baseline. Final local p95 computation 1.6 ms, DOM update 15.5 ms, double-frame paint estimate 47.9 ms; actual paint timestamps were not captured.
- All 501 eligible cards completed with unique IDs. Complete zero-delay/all capture: 100 card calls/requests and 100 successful responses, zero filter-state/dataset requests during that interval.
- Warm-card regression: two catalog SELECTs, no SQL counts. Auth/session/cache/transaction overhead and remote PHP/MySQL timings were not independently measured.
- Real Group editor save/reload, default/no-op revisions, label-layout retention, one/two property columns, every 1–6 card maximum, narrow/dark layouts, delayed/obsolete responses, preset-removal feedback and a real four-line heading were checked. The original local settings/label were restored.
- The two historical admin-label failures did not reproduce in the initial focused baseline. Do not rename correct UI labels merely to satisfy an old assertion; inspect what the assertion actually addresses. Card-heading assertions now accommodate the native anchor/header nesting.

Relevant existing coverage:

- [GroupSettingsTest.php](../../tests/Feature/Catalog/GroupSettingsTest.php): defaults without rewriting, canonical partial settings, implicit-default no-op revision, strict invalid-draft rollback.
- [CatalogAdministrationTest.php](../../tests/Feature/Filament/CatalogAdministrationTest.php): actual editor save/reload, combined revision and layout retained with labels off.
- [CatalogSnapshotTest.php](../../tests/Feature/Catalog/CatalogSnapshotTest.php): additive metadata, old-cache defaults, card-only values excluded and warm-query budget.
- [PublicCatalogTest.php](../../tests/Feature/Catalog/PublicCatalogTest.php): order, escaping/native links, exact identities, missing slots and Product 25 affecting chunk 1.
- [CatalogFilterTransport.test.js](../../tests/Unit/CatalogFilterTransport.test.js): stale notice/status/freshness rejection, retry, Reset and suspension/resumption.

After an authorized admin behavior change, run the narrow affected checks and verify real form Save/Reload and hidden-tab errors. For Group form work, protect both property-column modes, labels off/on without layout loss, zero/boundary padding, unselected settings/definitions, implicit defaults and invalid aggregate rollback. Batch coverage must exercise mixed values and a failure that leaves every selected Group unchanged.

Use default SQLite `:memory:` or the explicitly allowlisted isolated MySQL test setup; never destructive test setup against the active database. Real production browser checks use normal interactions, no fixtures. Pest browser installation and special Vite/clock/network test infrastructure remain deferred. Tests support the UX work rather than expanding it.

Known evidence limits remain: appearance not released/verified remotely; several session/offline/focus/timer/lifecycle cases and every visual layout/maxima were not exhaustively browser-tested. Large card insertion recorded 111–126 ms main-thread tasks even while filter p95 was fast; slim responses/repeat caching are research candidates, not proven fixes. See the complete evidence in the appearance plan. No claim that all existing admin surfaces or all P0–P12 acceptance checks pass follows from these focused results.

## 10. Suggested opening instruction for the next session

```text
Continue the existing “Research Filament 5 admin UX” task, first reading:
/Users/studioycm/Herd/configurator/docs/catalog/CARD_APPEARANCE_ADMIN_UX_HANDOFF.md

Reconcile the earlier ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md with the actual
local Group/card implementation before executing any authorized phase.
Preserve the earlier plan's detail and independently authorized scope.

Treat the six per-Group appearance keys, staged aggregate Save, exact
differing-property semantics, local filter ownership, cache/revision rules
and focused regressions as the new baseline. Keep global Admin Appearance
separate. Update the Group inventory, presentation tabs, Group batch-edit
allowlist and cross-surface acceptance accordingly, without losing drafts,
stored layout, definition lists, unrelated settings or zero-call filters.

Read current AGENTS.md/rules and relevant skills, inspect dirty diffs, verify
installed APIs through Boost, and preserve concurrent shell/admin/package
work. Identify what is already implemented versus still proposed. G1 remains
unresolved; do not restart completed filter work or revive canceled features.
Use existing checks and real browser verification proportionate to the change.
Follow the user's authorization in this task for implementation/publication;
the handoff itself grants neither. Report concrete plan deltas and evidence.
```
