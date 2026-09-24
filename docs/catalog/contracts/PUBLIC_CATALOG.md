# Implementation contract: public catalog and discovery

**2026-09-23 · Detailed execution contract; no implementation performed.** Read with the [implementation plan](../IMPLEMENTATION_PLAN.md), [guidelines](../IMPLEMENTATION_GUIDELINES.md), [decisions](../DECISIONS.md), [data/engine](ENGINE_AND_DATA.md) and [admin](FILAMENT_ADMIN.md) contracts. Later user decisions govern. The final handoff selects the class names, URL shape and documented edge behavior below as technical defaults; earlier “proposed” wording distinguishes their design provenance from explicit user decisions, not an instruction to reopen them.

This is a proportional review using the imported [UX Design Thinking skill](../../../.ai/skills/ux-design-thinking/SKILL.md): orient to the existing contracts, inspect evidence and apply the relevant Stage 4 lenses. It is **not** a complete design/user-research audit. The Livewire, Laravel and existing testing guidance inform the implementation boundaries. `.ai/rules` is absent. No app code, dependencies, database rows or tests were changed or executed for this annex.

Boost verified PHP 8.4, Laravel 13.33.0, Livewire 4.4.6, Filament 5.8.4 and Pest 5.2.1. MySQL 8.0.46 was established by the earlier read-only inspection. Relevant evidence is distilled in [Livewire research](../research/LIVEWIRE.md) and [Laravel Daily video research](../research/YOUTUBE_LARAVEL.md); the latter distinguishes upload-card discovery from inspected descriptions and source content.

## 1. Contracts, evidence and UX lens check

| Evidence | Status and scope | Consequence |
| --- | --- | --- |
| P1: living plan 4.1–4.3 and 9.2 | **Confirmed** user decisions | Actual Group tree, leaf discovery, optional SubGroup presets, immediate paginated cards, actual Product, explicitly assigned Configurator; no ancestor inheritance or one-result gate. |
| P2: source `conf.js` and `filter.js` in `/Users/studioycm/Documents/Projects/ARI/d60.filter.UI/` | **Observed** static source | One ordinary value per property; newest-first conflict replay; blank choices excluded; available choices derived from actual data; configured order followed by otherwise unlisted data values. |
| P3: hosted filter sketch | **Observed** real browser behavior | The same 63 matching Products can have different precedence. `25 bar → Flange → 1″` yields 36; `Flange → 25 bar → 1″` yields 9. The sketch has no URL state, SubGroups or pagination; it proves none of those integrations. |
| P4: current app/routes and installed package source | **Observed** local code, version-matched Boost docs | Keep class-based Livewire, existing singular `app/DTO`, named routes and server queries. Ordinary Livewire actions serialize; the URL/pagination design still needs its own browser proof. |
| P5: publisher examples | **Inspected** descriptions/source as documented in research | Reuse self-excluding counts, parent-owned coordinated state and plain reusable markup. Old-version dependent-select/cart examples do not supply this catalog's business algorithm. |

The visitor's jobs are to choose a family, compare actual matching Products and then configure the chosen Product. Each page should answer that job before offering the next action. A branch shows child Groups; a leaf shows current criteria, count and cards immediately; a Product shows fixed facts and a separate configuration area. Public discovery has no Save/Publish boundary or invented lifecycle. Admin ownership, import and rule-authoring jobs remain in their respective annexes.

**Lens findings:** automatic criterion removal needs visible feedback; zero compatibility counts need an explanation because those choices remain usable; selected state must survive Back; no hidden manual criterion should silently narrow a force-hidden preset; an unassigned Product remains a useful page. These are the material UX checks for this plan. New personas, extra dashboards and generic workflow machinery would not resolve them.

## 2. Proposed files, routes and component ownership

**Later user layout amendment, 2026-09-24:** public pages share centered navigation and a header light/dark toggle. Use full-width content with responsive padding. Group filters have compact 12px text and a responsive grid with up to eight columns on wide screens; narrower layouts retain usable controls and no horizontal overflow. The existing Flux preference owns appearance. This amendment changes presentation only, not discovery state, counts or precedence.

Use `make:livewire <name> --class --no-interaction` for new components and `make:class` for new PHP classes, following the project's generators and conventions. Do not convert existing components to single-file syntax as part of this work.

| File / class | Responsibility |
| --- | --- |
| `app/Livewire/Catalog/Index.php` | Catalog root: real top-level Groups, ordered by `sort_order`, then ID. |
| `app/Livewire/Catalog/GroupShow.php` | Branch child navigation **or** one coordinated leaf-discovery state owner. Branches do not query descendant Product cards or inherit Configurators. |
| `app/Livewire/Catalog/ProductShow.php` | Resolve the actual Product and Group; show selected fixed facts and assigned/unassigned configuration area. |
| `app/Livewire/Catalog/ProductConfigurator.php` | M4 child adapter around the shared canonical engine; separate from discovery. |
| `app/Services/CatalogDiscovery.php` | Load current group metadata, normalize untrusted state, supply one Eloquent predicate builder and produce settled criteria/facets/pagination. |
| `app/Services/CatalogFilterReconciler.php` | Bounded newest-first replay with an injected existence predicate; no separate database or browser rule engine. |
| `app/DTO/CatalogDiscoveryState.php` | Readonly validated state, constructed after normalization; no trusted Product facts from the client. |
| `app/DTO/CatalogDiscoveryResult.php` | Prepared state, field/value/count presentation, pagination and change notices; request-local derived result, not public Livewire payload. |

Views match the class convention: `resources/views/livewire/catalog/{index,group-show,product-show,product-configurator}.blade.php`. The public layout is `resources/views/components/layouts/catalog.blade.php`, addressed as `components.layouts.catalog`. Reuse existing app assets and accessible controls. Presentation-only components under `resources/views/components/catalog/` can be `breadcrumbs.blade.php`, `group-tree.blade.php`, `subgroup-picker.blade.php`, `filter-field.blade.php`, `product-card.blade.php` and `pagination.blade.php`; create only those that remove real repeated markup.

Register `Route::livewire` in `routes/web.php`:

| Path | Named route | Class |
| --- | --- | --- |
| `/catalog` | `catalog.index` | `Catalog\Index` |
| `/catalog/groups/{group}` | `catalog.groups.show` | `Catalog\GroupShow` |
| `/catalog/products/{product}` | `catalog.products.show` | `Catalog\ProductShow` |

Use internal IDs with normal model resolution initially; no unrequested slug system. Resolve unknown records as 404. Preserve existing home, dashboard and account/settings routes. Product links use `route('catalog.products.show', ...)`; the browser's return path restores discovery, rather than trusting an arbitrary return URL. Breadcrumbs follow actual ancestors. Load their minimal fields deliberately and avoid a lazy relationship query for every tree row; cycle prevention belongs to the Group write operation.

Filters, count labels, pagination and cards are **Blade presentation within GroupShow**, not independently stateful sibling Livewire components. A Filament Table would otherwise bring its own filter/paginator state. Filament controls may be embedded where useful, but no Table state becomes a second discovery owner. For an actual embedded form, use Filament 5's documented schema contract and required assets; do not copy a Filament 3/4 interface from a tutorial. Include its documented `RestrictsFileUploadsToSchemaComponents` setup if using public schema-bearing components; this catalog flow has no upload input. The [Laracon source follow-up](../research/FILAMENT_CAPABILITIES.md) verifies the installed trait and explains the example's version difference. ProductConfigurator is a justified child because its lifecycle and rule policy are independent.

## 3. Metadata and settings shared with admin

Use the core annex's `GroupFilter` and `SubGroup` tables. `GroupFilter` has `group_id`, registered `property_key`, `label`, `sort_order`, `value_order` and `value_labels`; one record per Group/property. Display labels never become query keys. `SubGroup` has `group_id`, `label`, one registered `property_key`, nonempty distinct canonical-string `allowed_values`, `force_hide` and `sort_order`. No source `sub_group` value automatically creates an application preset. A preset may use a registered Product property without a corresponding GroupFilter: it still constrains results, but creates no ordinary filter control. In that case `force_hide` has no public effect; explain this in admin rather than rejecting a valid preset.

The initial seven filters, in source order, are `Working_Pressure`, `Valve_Type`, `Connection_Type`, `Connection_Size`, `Automatic_Type`, `Automatic_Config`, `Discharge_Outlet_Type`. Their labels and preferred value order come from `conf.js`; pass source HTML entities through the agreed **one-time** normalizer so, for example, size choices match the imported canonical strings. The property allowlist is the core import/property registry; no arbitrary JSON path from a URL or admin text is executable.

Derive vocabularies for the **union of configured GroupFilter keys and all configured SubGroup property keys**, using nonblank values actually present in the whole leaf Group. This union is bounded by the core property registry; rendering ordinary controls is still limited to GroupFilter records. Keep configured values that exist first, then append omitted source values. Proposed stable append order is the lowest Product internal ID containing the value, matching first-import occurrence without creating a new source-order column. Order configured fields and presets by `sort_order`, then ID. Diagnose configured values absent from data in admin; do not manufacture public zero-data choices. A field with no usable values is omitted from the public controls and flagged for maintenance.

These exact `Group.result_settings` keys/defaults are coordinated with the admin annex:

| Key | Stored type / initial value | Validation and public use |
| --- | --- | --- |
| `default_page_size` | Integer `10` | Positive integer; proposed safety range 1–100. This is the initial and fixed size when visitor changes are disabled. |
| `allow_page_size_change` | Boolean `false` | Show a visitor page-size control only when true. |
| `page_size_options` | Ordered flat integer list `[1, 2, 10]` | Distinct positive integers within the proposed 1–100 cap; must include the default when visitor changes are enabled. Admin Repeater storage is flattened to this list. |

The 100-card cap is a proposed technical limit, not an accepted business number. No automatic “all Products” size. Admin and public read the same normalized settings; a disabled visitor control also ignores a forged URL override. Changing size resets the page to 1. Size 1 still paginates the complete result set.

## 4. One typed URL state, including precedence and page

Proposed binding on GroupShow: `#[Url(as: 'd', history: true)] public array $discovery = [];`. The server DTO represents this exact canonical shape:

```text
version: 1
filters: map<registered property key, canonical string>
precedence: list<"filter:<property key>" | "subgroup:<internal id>">, oldest → newest
subGroupId: positive integer | null
page: positive integer, default 1
perPage: permitted integer, default current Group setting
```

Only `discovery` is URL-bound. The route is the authoritative Group. There is no public mutable copy of allowed values, counts, Product facts or paginator state. A valid snapshot contains each active ordinary field and the active preset exactly once in precedence. Identical filters with different precedence are distinct states because their next transition can differ.

**No `WithPagination` trait.** Call the installed Eloquent `paginate()` with explicit per-page, page and selected columns; its explicit-page signature was inspected. Render a custom pagination Blade component whose actions call `goToPage(int $page)` and replace the page in the canonical snapshot. Any ordinary `href` fallback encodes the same `d` snapshot, not a competing `?page=` parameter. Use length-aware pagination for total/page numbers; cursor/infinite scrolling would change the accepted interaction without an established need.

Normalize initial URL input, direct property updates and every action before using it. Canonicalization is an allowlisted parse, not a PHP cast of arbitrary arrays:

1. Recognize version 1. Missing version can use the documented compatibility fallback below; unsupported versions reset to the current default with an adjustment notice. Accept decimal scalar page/ID strings and validate their range; reject nested values, negative numbers and nondecimal forms. Treat a legitimate filter string `"0"` as a value.
2. Resolve the Group's current filter definitions, the filter/preset-property vocabulary union and in-group preset. Ordinary selections must use configured GroupFilter keys; the preset can use another registered property from that union. Discard foreign/stale presets, unknown fields and values no longer present. Property paths always come from the server registry. Bound selection count by configured fields and precedence count by that number plus one; compare strings with the finite server vocabulary before building predicates.
3. Deduplicate/filter precedence tokens. For retained selections with missing order, construct a stable **fallback oldest-to-newest** order: preset first, then ordinary fields in GroupFilter order. Use this complete fallback if an old/tampered snapshot cannot provide a complete unique order; do not pretend it recovers unknown past clicks.
4. Replay that valid order newest-first against current Products. Remove incompatible older constraints and explain meaningful adjustments. Enforce hidden-field normalization as below. Do not allow URL manipulation to widen the Group.
5. Preserve a valid restored page if criteria and page size survive unchanged. Criteria changes or invalid/out-of-range page return to page 1; total zero also uses page 1. A page change alone never invokes a blanket filter-reset hook.

An ordinary interaction assigns the complete final snapshot once and should create **one** history entry. URL repair should replace the current entry, not add a loop. Defaults may be omitted from an empty initial URL, but any stateful entry must restore all behavioral fields.

**Required implementation proof, not an established working fact:** installed Livewire 4.4.6 source locks URL tracking during `popstate` while applying `$wire.set`, and its replacement path can return while locked. The effect of server normalization on a malformed Back entry must be tested in the real browser. First prove native single-property binding for valid history and canonical repair. If repair fails, use a narrowly scoped completion-time URL replacement adapter for this property, preserving Livewire's history state; no second URL owner or global custom history framework. Do not ship a claim of repaired address-bar state based on PHP component tests alone.

## 5. Deterministic actions and SubGroup reconciliation

Use explicit GroupShow actions: `selectFilter(string $propertyKey, string $value)`, `selectSubGroup(?int $subGroupId)`, `clearFilters()`, `resetAll()`, `goToPage(int $page)` and `changePageSize(int $perPage)`. They validate against current metadata and delegate to CatalogDiscovery. Re-read current records on each request; stale client snapshots are not definitions. Invalid/stale actions return refreshed state and a bounded notice instead of accepting foreign values.

An ordinary selected-value click removes that filter and its order token, without a replacement. For a new value, seed the accepted constraint list with that value; visit prior active tokens newest-to-oldest and retain each only if at least one Product in the **whole leaf Group** satisfies the combined constraints. Replace the old token for the same field. Reverse the retained older list back to oldest-to-newest and append the new token. Return the removed field/preset labels for feedback, then reset page 1. Never reconcile only the ten visible cards.

A preset is **one atomic ordered constraint**: equality/OR over its one property's allowed values. It does not populate an invented ordinary filter selection. A newer ordinary choice can evict it; the actual Group remains unchanged. A visible ordinary selection on the same property intersects its allowed set while both survive.

Selecting or switching a preset removes the former preset token, gives the new preset highest precedence and replays existing ordinary constraints. Choosing no preset removes that token without reintroducing previously discarded filters. Selecting the already-active preset is a no-op; provide an explicit remove/reset affordance. Switching is not an implicit “clear all ordinary filters.” These action details are proposed technical completions of the settled newest-choice policy.

**Hidden-field completion proposed for review:** when the selected preset has one value or `force_hide=true`, clear any ordinary filter/order token for that same property before replay. Otherwise a hidden manual value could silently narrow a force-hidden multi-value preset. A multi-value visible preset may retain a compatible ordinary choice. Apply the same invariant to restored URLs; clearing/eviction of the preset restores the field, with no automatic manual choice. This edge case is absent from the sketch and needs the independent tests below.

`clearFilters()` removes ordinary choices/order tokens and preserves the preset. `resetAll()` removes both. Both reset page 1 and recompute visibility. Do not restore earlier discarded selections after an ordinary deselection; the reference does not keep that hidden history. Configurator remembered selections are an unrelated engine policy.

Use normal serialized Livewire actions, with no `.async` or `#[Async]` on discovery changes. Do not dispatch a separate browser request per filter, count and card region. The same final server result supplies controls, announcements, count and cards. Do not optimistically announce final matches or removals. Rapid action and Back-during-request tests remain necessary even though the framework documents serialization; duplicate/pending stale responses must not replace the current state after restoration.

## 6. Facet/count semantics, blanks and query boundaries

In `filter.js`, `configuredValueOrder()` skips exactly missing/undefined, null and `''`, then retains otherwise unlisted values found in the data. `getCompatibleValues(field)` removes **that field's ordinary selection**, tests each value against all remaining selections and returns boolean compatibility. The sketch's total is `matches.length`; it does **not** show numeric per-value counts. The following numeric counts are a proposed extension of that algorithm under the accepted count requirement.

For a visible field, count Products under the Group, active preset and all ordinary filters **except this field's ordinary selection**, grouped by the candidate scalar value. Keep the preset even when it uses the same property. Fill missing vocabulary members with zero. Counts span all result pages. They mean compatibility with the current other constraints, **not** the eventual result after newest-choice reconciliation. A zero remains clickable, with help such as “Choosing this may clear earlier choices.” Do not disable or hide it because of zero. Selected styling takes precedence over compatibility styling.

There is no synthetic “Not specified” option. Products with missing/null/empty properties remain in unfiltered results and can match other properties, but those blanks do not create filter buttons. Do not apply `empty()` or truthiness; canonical `"0"` is real. The original skip test does not trim whitespace; canonical import normalization is the agreed place for that work, not a new read-time mutation. Blank card facts can use an em dash, independently of facet vocabulary. Unexpected non-string JSON values need diagnostics; they are not coerced into equivalent string choices.

CatalogDiscovery owns a single composable Eloquent predicate for results, existence replay and aggregates: `group_id` scope; AND between ordinary scalar equality constraints; `whereIn` over a scalar JSON path for a preset OR-list. `whereJsonContains` is for array-valued JSON, not this scalar membership operation. Paths come from the registry and values are bound parameters. Verify strict case/Unicode/string matching and JSON-null behavior on actual MySQL against the JS/source contract.

Use SQL aggregates over extracted scalar values, with explicit JSON string/nonempty guards, for vocabulary/facet counts. A grouped query per required property is an acceptable initial boundary; a count query per button is not. Preserve all vocabulary values even when the active aggregate lacks them. Query count scales with registered/configured properties and active constraints, not Product count or value count: at most one vocabulary aggregate per property in the filter/preset union, one compatibility aggregate per visible field, bounded replay existence checks, one total count and one page-row query, plus bounded metadata/navigation loading. Reuse identical aggregates and the total where possible.

Read all data for one prepared result within one short coherent read operation; if using a transaction for snapshot consistency, verify the actual MySQL isolation behavior and keep it read-only with no row locks. Cache computed results **within the request** and invalidate that memo when an action changes discovery. Do not add cross-request caching before measuring and defining import/Product/metadata invalidation.

Order cards by `product_code`, then Product ID. **Later user amendment, 2026-09-24:** the Product Code is the card heading. Under it, show the real main (root ancestor) Group and the actual Product Group, omitting a missing main Group. Show every populated registered Product property as an escaped value without a visible label, in the property registry's stable order; preserve literal `"0"`, and omit blank/null/non-string entries. This replaces the former product-name heading and two labelled facts. Select only `id`, `product_code` and `properties` for the current page. **Keep those server-side view records out of Livewire public state; never select card `parts` or `extra_data`.** Load the Group trail once for the page and share it between the breadcrumb and cards. Discovery does not load configurator definitions, media or POC configuration relations. A source `product_pic` path is not yet a validated public image URL; use an honest absence/placeholder rather than automatic remote media lookup.

The proposed `(group_id, product_code, id)` index supports scoping and ordering. Measure representative initial, heavily filtered, zero-compatibility and paginated requests on the fresh MySQL schema; record query count/time, returned/rendered payload and `EXPLAIN`. Add generated/expression indexes only for demonstrated hot property predicates, with matching extraction/type/collation. SQLite success cannot establish these results. No generic search service or added package is justified by 501 starting Products.

## 7. Loading, no-match and Product behavior

The first leaf response renders count, cards and pagination immediately. Keep settled results visible during an update; use scoped delayed loading feedback and a busy result region, then a polite final count/removal announcement. Keep keyboard focus on the initiating control where it survives; if a preset change hides that field, move focus deliberately to the preset/current-criteria area. Use stable keys based on Group/property/value identity and Product ID. Do not use list indexes as identity or colour alone as state. Server labels are escaped; source HTML is not rendered as trusted markup.

Do not present ordinary zero-count choices as disabled configurator options. A request-in-flight indication is a separate state; use the normal action queue so consecutive valid choices are not silently lost. Clear/reset controls identify their different effect. A transient request failure keeps the last settled UI and allows retry; no fake empty-success screen.

Distinguish a Group with no Products from a filtered/stale state with no matches. Show an honest empty message and useful Group navigation; when criteria exist, provide Clear filters and Reset all. No “NEXT” gate or invented substitute Product. A stale preset whose values no longer exist is normalized away with feedback; an empty actual Group stays empty.

ProductShow resolves the actual Product's trusted facts and its actual Group assignment. Display `product_name` and `product_code` separately from the generated configuration code. An unassigned Group shows Product information without a fabricated configuration. The initial placeholder becomes the shared ProductConfigurator in M4; no “first ProductConfiguration” fallback or hardcoded demo profile remains.

ProductConfigurator receives a locked Product identity, resolves current assignment/definition server-side on each interaction and calls the core annex's `ConfiguratorDefinitionLoader`, compiler and engine. The public adapter consumes the settled engine result. Admin Preview & Test is a static placeholder under the later user amendment; when resumed, it must consume the same result. Territory/Application are each single-select with `All` unrestricted; session persistence is deferred. Illegal/hidden/disabled options are rejected/refreshed, without clearing upstream choices to make them legal. Discovery's newest-choice reconciler is never reused here. Imported parts and future assets/specification mapping are not implied by discovery or placeholders; The admin tab layout follows the later user amendment in the admin contract; no Custom tab is exposed.

## 8. Independent validation targets

These are required future checks, **not tests written or run by this evaluation**. Use the existing upstream testing guidance, Pest feature coverage and MySQL for query semantics. Keep expected fixture results independent of the reconciler. Proposed test files are `tests/Unit/CatalogFilterReconcilerTest.php`, `tests/Feature/Catalog/CatalogDiscoveryTest.php` and `tests/Feature/Catalog/PublicCatalogTest.php`; browser history/timing/accessibility checks supplement them rather than being asserted by a PHP-only test.

Independent three-Product fixture: **A** = pressure 25, Flange, size 2; **B** = pressure 16, Flange, size 1; **C** = pressure 25, Threaded, size 1. Preset **S** allows pressure 25. These simplified fixture strings do not rename production values.

| Case | Independently expected result |
| --- | --- |
| Ordinary precedence | Pressure25 → Flange gives A; then size1 gives B, retaining Flange and dropping pressure25. Flange → pressure25 → size1 gives C, retaining pressure25 and dropping Flange. |
| Atomic preset precedence | S → Flange → size1 gives B and removes S; Flange → S → size1 gives C and removes Flange. This proposed three-way extension must be visible in review; the sketch cannot prove it. |
| Same-property hiding | Selecting a single-value or force-hidden preset clears a prior manual filter for that property. A visible multi-value preset can keep its compatible manual filter. Clearing/evicting S restores the field without inventing a choice. |
| Preset without ordinary field | A SubGroup on a registered property with no GroupFilter still restores from URL, constrains counts/cards and participates in precedence. No ordinary control appears; force-hide does not change results. |
| Toggle and resets | Reclicking a selected ordinary value removes it with no replacement. Clear filters preserves S; Reset all removes S. All real criteria changes use page1. |
| Numeric count meaning | With S+Flange, connection counts are Flange1/Threaded1 (exclude ordinary connection, retain S); size counts are size1=0/size2=1. Size1 remains actionable. With only manual pressure25, the pressure facet is 16=1/25=2 because its own filter is excluded. |
| Blank/type precision | Missing key, JSON null and empty string create no filter value but remain unfiltered Products; string `"0"` is selectable. Case, quotes, Unicode and leading-zero strings remain distinct as defined by canonical import. Test malformed non-string JSON explicitly on MySQL. |
| Pagination and settings | 23 ordered Products at10 give10/10/3; at2 give12 pages; at1 all23 remain reachable. Duplicate Product codes sort by ID. Disabled visitor size ignores forged URL size; enabled sizes use the admin list. Out-of-range page becomes1. |
| Atomic Back | One action removing two fields and a preset creates one history step. One Back restores prior filters, preset, order, field visibility, allowed page size and valid page2. |
| Back then incompatible click | Real-data snapshot25bar → Flange at63; move on, Back, then1″ gives36 with Flange retained. Reverse-order snapshot yields9 with25bar retained. A valid restored page is not reset simply because `popstate` occurred. |
| Tampered/old URL | Unknown field, foreign preset, stale value, duplicate/missing token, unsupported version, nested value and bad page shape normalize within bounds. Assert deterministic fallback and actual address-bar replacement; Back does not loop through repair entries. |
| Rapid requests / restoration | Under delayed network responses, sequential ordinary clicks produce the same final state as serial fixture steps; Back during a pending request does not let a stale response overwrite the restored state. Observe controls, count, cards and URL together. |
| Scope/payload | A foreign Group's Product never enters counts/cards; branch routes do not issue leaf-result queries. Response/snapshot contains only needed fields and no parts/extra_data or full Product property map. Query count does not grow per option button. |
| Actual Product / shared engine | Two Products in a Group and one in another use their own facts/current assignments. Unassigned Product is valid. Public and admin same-input engine results match; forged unavailable choices cannot clear upstream selections. |
| Browser usability | Keyboard toggle/reset/pagination, focus after hidden-field changes, polite count/busy/removal feedback and narrow-screen controls work without horizontal overflow. No one-result gate or premature empty state. |

## 9. Remaining inputs and review boundary

Outstanding content inputs are real parent Group names/relationships (the export has none), initial administrator-authored SubGroup definitions, any preferred labels not already supplied by `conf.js`, and actual canonical configurator definitions/context choices needed for a populated M4 demonstration. The D060 Group and Products can be shown without fabricating those inputs. Parts/specification/media mappings remain explicitly deferred.

The final handoff selects names/routes, preset ordering and hidden-manual cleanup, fallback URL order, count meaning, settings cap and pagination ownership as technical defaults. The material implementation uncertainty is native Livewire malformed-Back canonicalization; prove it before accepting URL behavior. This does not reopen the fresh-database choice, accepted filter policy, context cardinality or whole-code conditions. Whole-output conditions remain an omitted legacy branch, not a new public-flow requirement.

Primary API references: [Livewire4 URL history](https://livewire.laravel.com/docs/4.x/url), [Livewire4 action serialization](https://livewire.laravel.com/docs/4.x/actions), [Livewire4 loading](https://livewire.laravel.com/docs/4.x/loading-states), [Laravel13 pagination](https://laravel.com/docs/13.x/pagination), [Laravel13 JSON queries](https://laravel.com/docs/13.x/queries#json-where-clauses), [Filament5 components outside a panel](https://filamentphp.com/docs/5.x/components/overview). Publisher follow-ups and observed interaction provenance are linked in the two owned research reports above. API availability is distinct from tested integration in this application.
