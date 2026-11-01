**Authoritative boundary update, 31 October 2026:** [Group properties versus custom tags amendment](CATALOG_GROUP_PROPERTIES_AND_TAGS_RECONCILIATION.md) supersedes older conflation: Group ATTRIBUTES filter the root Group list and appear first on Group cards; Product PROPERTIES filter the Group's Product list; separate custom Group TAGS support flat grouping and follow attributes. Product GroupFilter is Product metadata. The new Group-attribute producer is not implemented: human direction is one dedicated JSON column/document and native explicit authoring with empty defaults, not normalized attribute/value tables. Fluid Type is confirmed. Existing custom-tag relationships/semantics remain; tentative JSON tags is not a migration decision. Finalize/review/activate the attribute package separately after K6, without fabricated assignments. Preserve accepted K1–K5 and their actual current-Master receipts; older source/status/order checkpoints below are historical, with active delivery order governed by the complete Catalog plan §§19–22. No new Images owner/schema/library scope is released.

# Catalog redesign — UX, settings and delivery plan

**Status, 28 October 2026:** updated planning/review only. The full scoped source audit is complete against master `8e1f640` / application repair merge `5788905`. This revision consolidates its six engineering refinements and the complete findings inventory. Application/prototype implementation, tests, migrations, runtime changes, integration, commit, merge and push remain unauthorized. Read the [consolidated audit](CATALOG_AND_IMAGES_PLAN_AUDIT.md) and [combined premerge plan](CATALOG_AND_IMAGES_PREMERGE_PLAN.md). The existing authenticated catalog is implemented; this redesign is not.

## 1. Authority and source map

Confirmed human decisions:
- Three stages: Groups catalog with filters/cards; a leaf Group's Products catalog with filters/cards; Product page with configurator and main image.
- Top criteria are collapsed initially, and sidebar navigation remains next to Group cards.
- Named grouped tags, multiple values per Group; no numeric-range tag controls.
- Optional inherited Product presets participate in newest-choice wins. Preserve explicit chronology, compatible older choices and exact clearing feedback. Group limits remain unchanged.
- Admin-managed shared defaults with selected Group overrides. No new per-user catalog-layout preference system.
- Group and Product card image placement can be Above, Below, Left or Right; settings also control allowed card content/navigation/presentation.
- Light/dark support, compact contextual controls and existing Product/Group data/configuration contracts.

Current sources:
- Current application source: primary master `8e1f640219ec8dbd2ee4c739041907aba4c4224d`, application repair merge `5788905a4a0ac95f26cf435cac4d82c09b576dd0`. SA-01/RL-02/RL-01/AD-01 and the sixteen consolidation dispositions are complete; preserve their contracts.
- Pending Images: detached `244b305` plus the preserved P0–P6 overlay, now 92 pending paths. Its dated §14.14 / blueprint §§19–20 are standalone evidence; the primary source plan §15 / blueprint §21 and current amendments govern integration. Combined P6 remains unaccepted.
- Static catalog-review-site at b6395c5: design reference with illustrative data, not an application adapter.
- [Prototype repair plan](/Users/studioycm/.codex/visualizations/2026/10/18/01a14fee-762d-7661-acdc-8d5213474de9/catalog-review-site/docs/superpowers/plans/2026-10-26-catalog-prototype-repairs.md) is a separately scoped source reference.
- Existing contracts: PUBLIC_CATALOG.md, FILAMENT_ADMIN.md, PRODUCT_DATA_IMPLEMENTATION_PLAN.md; inspect final source rather than inheriting historical implementation statuses.

The actual current catalog routes are authenticated dashboard routes. “Public catalog” in historical class/contract names does not authorize creating an anonymous catalog. Keep existing route names/redirect compatibility and access scope.

## 2. Product intent and visual direction

Primary jobs:
1. Find a relevant Group without losing hierarchy or filter context.
2. Compare actual Products within the selected leaf Group.
3. Configure an actual Product and share/restore its valid saved-definition selections.
4. Let an administrator adjust presentation without changing matching or access policy.

Recommended main direction: A.R.I Precision. Use code-led identity, restrained image trays, aligned engineering facts and compact criteria. Borrow the Field Guide's readable imagery and Technical Desk's orderly parameter grouping within this single visual system. The three prototype styles remain review references, not three separately maintained production themes.

Use the existing Instrument Sans catalog font and existing branding. Proposed token direction: primary #0877e8, ink #182231, light surface #ffffff, canvas #f8fafc, secondary text #64748b, separators #dbe3ec. Dark surfaces use #182231/#121b25 with light text; verify actual contrast. Reuse existing theme tokens where equivalent. Avoid a marketing hero, oversized photography, arbitrary animation or three competing stylesheets.

The image is technical supporting information. Keep contain-fit, measured aspect ratio, reserved space and a white-photo-safe tray in both themes. Do not crop meaningful valve details or imply that a family/demo image is a specific Product's managed image.

## 3. Three-stage layout and navigation contract

### Groups catalog

- Compact page identity/breadcrumb, selection-criteria trigger with active count, search and reset.
- Criteria open below that bar; on narrow screens a single accessible drawer replaces the wide columns. Opening criteria never resets selections.
- Sidebar on the left in LTR; main responsive Group-card grid alongside. One page scroll; no permanently nested fixed-height scroll boxes.
- Selecting a branch updates the catalog's branch context and URL/history. Expanding a Free Tree plus icon changes expansion only; the name selects. A leaf Group opens its Products page.
- Focus Path shows ancestors plus one current-level list. Free Tree uses one flat expandable row stream, bounded indentation and full labels. Tag cards filter the current eligible Group set; they are not a second tree.
- Default recommendation: Focus Path plus immediate children. Admin alternatives are described below. Existing Group visibility, ancestry, branch and identity limits always apply.

### Group Products page

- Breadcrumb and return link restore the originating branch/criteria and useful scroll position.
- Compact Group identity, active inherited-context summary, standard Product filters and responsive Product cards.
- Keep current Group filter/preset definitions, field order, exact values, local compatibility/counts, independent server cards and stale-response protections.
- A configured, representable exact mapping can transfer a Group criterion to a real Product filter/preset. Label-based guessing is forbidden. Unmapped criteria are clearly shown as context, not silently applied constraints.
- Existing engine constraints matter: direct Product fields are single-value and there is one active preset. Unsupported multi-value/multi-preset transfers remain context unless a separately approved engine extension is designed. Never select an arbitrary first value.
- Newest optional Product choice may clear an older inherited Product preset with feedback. It does not edit the upstream Group selection.
- A Product card opens that actual Product. No new comparison/purchase feature is implied by prototype-only review controls.

### Product/configurator page

- Compact upper section: Product code, valid name/Group context, managed image, description when present, and return to the exact Product results.
- Keep the existing configurator/loader/compiler and its context, restrictions and error behavior. Preserve native facts/parts/others ownership.
- Configuration-code load/share must use a real saved definition and actual engine validation. The prototype decoder is not production logic. Unsaved editor changes are not reproduced.
- Preserve filters, selection chronology and return context through Back/Forward and supported direct links; validate bounded state against current records/definitions.
- This initial redesign keeps a compact header; a second full Product-page layout system is not required.

## 4. Admin settings contract

One Catalog display administration surface, shared across logged-in catalog viewers. Use a staged Save and an unsaved preview; do not persist every toggle. Keep existing Admin Appearance shell settings and CatalogContextSettings distinct.

| Setting | Finite choices | Recommended default | Group override |
| --- | --- | --- | --- |
| Group sidebar | Focus Path / Free Tree / Tag cards | Focus Path | No; stable catalog-wide navigation |
| Branch card view | Immediate children / Descendant model groups | Immediate children | No initially |
| Group-card image | Above / Below / Left / Right | Left | This Group's own card |
| Product-card image | Above / Below / Left / Right | Left | Products inside this leaf Group |
| Group-card optional content | Description, named tags, explicitly labeled counts, existing Legacy ID | Short description + selected tags | This Group's own card |
| Group-card tag categories | Ordered category selection | A small useful set | This Group's own card |
| Sidebar tag presentation | Cards / chip grid / wrapping chips / rows; bounded 1–3 columns | Cards, two responsive columns | No initially |
| Optional Group tags on a new branch | Keep still-available choices / Clear for the new branch | Keep still-available choices | No initially |
| Group → Product transfer | Apply configured representable choices / Context only | Apply configured representable choices | No initially |
| Top criterion container | Inline on wide screens / drawer | Inline wide, drawer narrow | No |
| Criterion columns/choices | Plain or divided columns; checkboxes or named buttons | Divided columns + checkboxes | No initially |
| Product-card properties/labels/differences/columns/spacing | Existing current Group controls | Existing saved/effective values | Existing authority retained |

Identity is required: a Group name and Product code cannot be hidden. No Group “model code” field is invented: the real model has name/legacy_id, not the prototype's modelCode property. Only expose a code field if a real authority is later established.

Count labels must name their set: child Groups on a branch, actual Products on a leaf. Do not add descendant Product aggregation/per-option Product counts under a generic count toggle. Group-tag matching counts, if offered, describe the eligible Group-card set and need bounded query proof.

Image Left/Right are implemented with logical inline positioning so the current text direction and accessible reading order remain coherent. At narrow widths side placements become a contained image row without overflow; Above/Below retain their order. Explain the responsive preview in settings.

Inheritance:
- New absent override means use the shared default; an explicit override retains its intent even when equal to the current default.
- Reset to shared removes the override key; it does not save today's shared value as a local override.
- Preserve exact false/zero/empty values according to each field's rules.
- No ancestor inheritance is added. “This Group's card” follows the Group wherever displayed; “Products in this Group” follows that leaf's cards.
- Do not reinterpret existing Group result_settings as absent/inherited. Existing card_properties empty-list fallback, labels, difference computation, spacing and thresholds keep their current meanings.

Controls:
- Use native Filament grouped ToggleButtons with named/icon options for short exclusive layout choices.
- Use searchable multiple Select with reorderable selected options for ordered content/categories; native Toggle for booleans and bounded numeric inputs only where needed.
- Each override has a clear Use shared / Override control and effective-value hint. Avoid a separate modal per field.
- Shared settings preview may choose one authorized Group and a bounded set of records. It shows saved managed images or truthful placeholders; it is not another image-upload manager.
- Save/Reset, permission errors and stale-edit handling remain within an authoritative settings action.

## 5. Group scope, tags and layer behavior

Grouped tags are separate catalog metadata, not hidden additions to configurator Attributes/Master Values. Proposed normalized categories, values and Group/value associations provide stable identities, multiple values, reusable vocabulary and later extension seams. Around ten categories does not justify a generic attribute engine. JSON-only tags are smaller initially but complicate shared renaming, exact identity and future per-value assets.

Default immediate-child mode preserves the original rule: available tags come from visible immediate children of the selected Group, before applying the current tag choices. Multiple selected values within a category are OR; categories are AND. Exact IDs/labels preserve zero, leading zeroes, case and Unicode.

The proposed Descendant model groups setting is a paired view policy: cards, available tag options and Group counts all refer to the visible descendant leaf-model set under the same selected branch. Label the view “Models below this Group”. This is an explicit recommended amendment for that optional mode, not silent widening of the default child-derived tag rule. Obtain product review of this pairing before execution.

On a new branch/layer selection, the shared retention setting either keeps still-available optional Group tag choices (with unavailable choices removed) or clears the optional tag choices for that new branch. Explain what changed. This setting never changes the selected Group/branch or mandatory eligibility. Back/Forward restores the recorded state rather than applying the new-branch reset again. Do not remove compatible-but-zero-result Group tag combinations automatically: newest-choice Product reconciliation does not redefine Group filtering.

Parent/child relationships, active ancestry and membership come from current Group/availability authorities, not sidebar expansion or tags. Descendant model view changes which Group cards are shown; opening a leaf still queries only its actual Products. A server-validated branch identity is never an arbitrary client descendant query.

Under the shared transfer setting, either apply configured representable incoming choices or carry them as context only. Selected tag values may constrain Products only through an explicit allowlisted mapping to real fields/current values or existing presets. Recommend representing supported transfer definitions at their existing Group filter/preset boundary rather than creating a parallel Product field registry. Unsupported transfer is visibly informational. The transfer setting governs a new catalog-to-Products entry; it must not erase explicit validated Product deep-link criteria or restored Product history.

## 6. Proposed Laravel/UI responsibility map

These are proposed seams reviewed by the completed final-baseline source audit, not created classes or approved migrations. The feature owner must finalize concrete signatures, schema and native extension-point feasibility in the execution proposal.

| Owner | Responsibility |
| --- | --- |
| Existing Index/GroupShow/ProductShow/ProductConfigurator and named routes | Existing journey entry, availability and engine boundaries |
| Proposed CatalogNavigation read service | Authorized branch/current-level/descendant-card projection, tree rows, Group facets and counts; no writes or compiler behavior |
| Proposed CatalogPresentationSettings model + SaveCatalogPresentationSettings action | Shared catalog display singleton, finite validated keys, version/stale guard and after-commit publication |
| Proposed CatalogPresentation service | Effective new global/default + explicit Group override resolution and small read DTO; no per-user persistence |
| Existing Group settings writer/policy/form | Extend only reviewed presentation keys and Group overrides; preserve definitions, raw settings, locks and revisions |
| Proposed Group tag category/value models and focused Save action | Exact shared vocabulary and Group assignments, dependency checks and relevant revision/invalidation |
| Existing CatalogCards/CatalogCardDisplay, snapshot/matcher/reconciler | Product matching, complete-result difference calculation and coherent server card reads |
| Existing/pending CatalogAssetDisplay and main-image component | Managed-image projection and rendering; no filename-to-image inference during browsing |
| Existing Alpine registrar + narrowly scoped catalog components | Local choices, history, disclosure/focus and stale-response protection |
| Native Filament page/schema factories | Settings UI and preview adaptation; not direct database writes |

Prefer extending Group.result_settings through its existing owner for allowlisted new presentation keys after checking branch/leaf handling and full/narrow save round trips. Do not materialize new defaults into old Groups or drop unknown settings. Do not move GroupForm's serializer into a business service. Coordinate GroupForm hydration/dehydration, full SaveGroupSettings normalization, raw narrow patches, explicit key deletion and revision detection. Branch cards need their own controls even where Product controls are leaf-only. array_replace alone cannot express reset-to-shared deletion. Preserve raw omitted settings and current field ownership; do not hydrate a UI form inside the writer to decide persistence. A concrete inability to fit this boundary requires a bounded alternative before adding persistence.

Settings/version/cache:
- New global defaults are separate from existing shell appearance and compiler context.
- Publish cache updates after the enclosing commit; rollback must not poison same-instance memoization.
- A global display edit must not mass-update every Group revision. Resolve a coherent display version and apply it on next page open/refresh; preserve active choices during an explicit presentation refresh.
- Keep global resolution bounded/cache-backed and measure cold/warm cost. Do not claim the current card-query budget remains proven merely because new settings are “small”.
- Group/tag changes require an explicit freshness path: current Group revisions do not automatically invalidate every cached ancestor facet. Prefer bounded fresh authorized navigation reads initially; an added cache needs proven ancestor dependency invalidation. Define exact category/value IDs, ordering, rename/removal and assignment writes. Do not add broad clears, per-node queries or a generic settings/repository framework.

## 7. Interaction and acceptance matrix

| Scenario | Required evidence later |
| --- | --- |
| Free Tree deep path | Plus versus select, full labels, cycle/hidden-parent safety, bounded indentation, no rigid nested scroll containers |
| Focus Path | Correct ancestors/current children, back navigation and restored branch/filter state |
| Tag sidebar | Cards/chips/rows, columns, exact identities, OR/AND, correct vocabulary/count scope and unavailable-choice feedback |
| Group → Products | Exact leaf membership, explicit representable transfer, visible unmapped context, newest-choice order and return state |
| Product cards | Required identity, real managed image, all-match property differences, accessible whole-card link and exact values |
| Product configurator | Availability on independent child requests, actual saved-definition code restoration, stale definition/error states and ordinary Preview continuity |
| Admin settings | Shared defaults, selected overrides, reset-to-shared, stale editor, authorization, rollback and no unintended rewriting |
| Images | Each placement on both card types, contained/non-upscaled imagery, missing/broken fallback, meaningful alt and white-photo-safe dark surfaces |
| History/lifecycle | Bounded deep links, precedence, Back/Forward, scroll/focus, repeated initialization/teardown and stale card responses |

Use pairwise visual coverage rather than claiming every Cartesian combination:
1. Wide/light, Focus Path/children, Group Left and Product Left.
2. Wide/dark, Free Tree/models-below, Group Above and Product Right.
3. Narrow/light, tag cards/children, Group Below and Product Above.
4. Narrow/dark, Focus Path/models-below, Group Right and Product Below.
Then add targeted long-name/deep-tree/empty/broken-image cases and every critical permission/scope path.

Preserve current local filter request/SQL behavior, current result limits/all-results semantics, separate card delivery and exact text. Update image/global-settings query budgets with measured evidence; no new result cap, entanglement, per-option SQL or Blade queries.

## 8. Delivery and decisions

Recommended visual/default choices above are ready for human review, not claimed approved. Admin-managed shared defaults plus selected Group overrides, optional inherited newest-choice policy and the overall three-stage scope are confirmed.

Remaining review points:
- **D1:** accept the recommended Precision / Focus Path / immediate-child defaults and finite presentation choices, or specify changes.
- **D2:** accept paired descendant leaf Group cards/tag vocabulary/counts for optional models-below mode; immediate children remains the unchanged default.
- **D3:** approve the actual representable tag→Product field/preset mappings to support. The bounded transfer principle is fixed; an engine expansion requires a separate specification.

Sequence after separate execution approval:
1. Adopt the completed source audit and this synchronized plan; pin the final baseline, isolate targets and approve selected execution units. Refresh only changed contracts if the baseline drifts; do not repeat completed research as a blanket gate.
2. Approved standalone prototype repairs and chosen UX refinements, with their focused checks.
3. Catalog display/tag foundations and settings inheritance; prove existing Group save behavior.
4. Group catalog/sidebar/facets, then actual Group Products transition/cards.
5. Actual Product/configurator presentation/code/return-state integration.
6. Joint image display acceptance with Images P9, without folding the Images workspace or library into this feature.

No part of this plan imports the prototype's static fixtures/decoder as production authority or authorizes coding automatically when a master SHA arrives.

## 9. Required final-source-audit refinements and proof

These are future implementation requirements, not new master defects. The same IDs appear in the audit; do not count IMG-A05 twice.

| ID | Required implementation boundary | Meaningful acceptance |
| --- | --- | --- |
| CAT-S08-01 | New override keys across GroupForm, full settings Save, narrow raw patch, explicit reset deletion and revisions; own branch-card versus leaf Product-card settings | Branch/leaf full+narrow round trips; unknown/omitted keys retained; false/0/empty/ordered lists; reset actually removes; no-op/rollback/revision; old result_settings semantics unchanged |
| CAT-S08-02 | Separate real code-restore engine adapter using current saved definition, canonical two-character identities and applicability. Current generation is working; code loading is a new feature | Valid exact round trip; conditional order/applicability; stale/foreign/inactive definitions and malformed code; failure retains choices; preserve dashboard context/Rule exclusions and unsaved Preview boundary |
| CAT-S08-03 | Coherent shared display version independent of Group revisions, integrated with request ETag/snapshot, browser refresh and server cards; after-commit cache/memo publication | Shared edit without mass Group updates; initial open/explicit refresh agree; warm/cold enclosing rollback; stale card generation fenced; active choices and chronology retained |
| CAT-S08-04 | Small separate Group taxonomy with exact identities/order, rename/removal/assignment rules and bounded authorized navigation reads | Literal 0/001/case/Unicode; unavailable/removed values; ancestry/reassignment/cycles; only-hidden-child branch stays physical branch; immediate-child and approved descendant set parity. Do not reuse ValueTag |
| CAT-S08-05 = IMG-A05 | CatalogCards authorized result-query image projection; retain bounded forIds for small admin reads | 0/1/200/501+ distinct pointers and >500 repeated pointers; all results/no raw 500-ID cap; complete-set differences; measured extra query; no per-card media/file/source reads or compiler payload |
| CAT-S08-06 | Bounded Group→Products→Product→return envelope over actual routes, criteria, chronology, branch/anchor; preserve compatibility redirects | New entry versus validated link/history precedence; Back/Forward/BFCache, scroll/focus, stale responses, hidden/reassigned records; exact membership and current availability including independent child requests |

Prototype repairs remain separately approved work: **CB-P01** includes optional inherited preset choices in newest-choice reconciliation and retains real chronology through serialization; **CB-P02** uses “Low Pressure (6 bar)” with exact 6 still included. Neither changes Group limits, AND matching or real Product data. Prototype Node/browser passes cannot certify app persistence or engine acceptance.

Catalog C0–C3 may proceed independently of Images P7/P8 when there is no actual shared dependency. The Product header/return flow and code restoration are separate acceptance units; neither a cosmetic header nor the prototype decoder delivers code loading. Managed image display is jointly accepted with Images P9 after its writer/storage prerequisites, on the existing or redesigned presentation actually approved. No new anonymous routes, purchase/comparison flow, per-user layout store or phase-2 library is added.
