# Implementation contract: public catalog and discovery

**Governing implementation, 2026-09-27:** local computation, Medium freshness, independent renderless JSON card delivery, and card-only debounce defaulting to zero. The user's merged plan supersedes earlier Light freshness, islands, joint rendering and pagination recommendations. The sketch is a responsiveness benchmark, never an algorithm specification. Product JSON storage and its existing indexes remain intact.

**Filter-header amendment, 2026-10-08:** Show the exact result amount prominently in a light box, above the small dimmed label `profiles`. The `RESET` button has the same height and clears ordinary selections and the active Sub Group. Show `Sub Groups` inline with its toggle buttons; clicking the active Sub Group clears it without restoring discarded values. Remove separate Clear controls for both Sub Groups and Filters. Each filter is an accessible named section with a full-width `h3` header; preserve aligned heading tracks, first buttons and equal section heights within responsive rows. Header measurement runs on initialization/metadata/width changes, not on ordinary clicks. These changes preserve local interaction and independent card delivery.

**Card appearance amendment, 2026-10-07:** The [appearance implementation plan](../CARD_APPEARANCE_IMPLEMENTATION_PLAN.md) now governs Product cards. Render compact whole-card native links with Product code, real Group subtitles and neutral full-width property cells, rather than comma-separated values. The filter-header amendment above supersedes the earlier fieldset/legend presentation.

Group settings control only differing properties (default true), visible labels (false), label/value layout (`inline_space_between`), property columns (2), and block/inline padding (4/6 px). The seven supported layouts are inline start/center/space-between, label above start/center, and label below start/center. Padding is bounded at 0–16/0–20 px; property columns are 1 or 2. Existing `card_properties` and 1–6 cards-per-row settings remain independent. The requested card maximum adapts to actual container width, with a 180 px minimum and one column on narrow screens. An unpaired final property spans both columns.

`CatalogCardDisplay::compare()` performs one query-free scan of every matching Product already loaded by `CatalogCards`, after the read transaction and before chunking. Resolve the configured card properties in stable order; empty configuration uses Group filter order. Compare exact nonempty strings, including zero, leading zeros, case and Unicode. Missing/null/empty/malformed cells form one missing category. Populated versus missing differs; preserve that field's slot with an em dash. Globally unpopulated fields are omitted. Shared unselected properties disappear too; there is no separate hide-selected toggle. Card-only values never enter compact snapshot rows.

With difference mode on, one matching Product has no differing fields and stays navigable. When no fields remain, the JSON response supplies one escaped result-region `propertiesNotice`: unpopulated takes priority, then single match, then shared properties. Alpine owns `cardPropertiesNotice` alongside current cards; clear it on criteria, retry, suspension, destruction and exit, and accept it only under the same request/generation/revision guards. Stale responses cannot clear a newer freshness error or confirm freshness. Labels remain accessible as `dt` text when visually hidden. Only escaped server Blade HTML enters `x-html`; the whole link contains no nested controls.

New settings are additive schema-1 snapshot metadata. Old cached settings receive `CatalogPolicy` defaults without a mass write or rebuild. Difference computation adds no SQL or requests to the warm revision-plus-Product-query budget. Property icons/colors, their settings page, islands and browser-test infrastructure remain deferred. Current verification and remaining browser/production gaps are recorded in the appearance plan's implementation evidence.

## Current interaction contract

- One ordinary value per field; clicking it again clears it. Newer constraints win and incompatible older choices are removed with feedback. Incompatible options remain clickable.
- Compatibility means coexistence with the other ordinary choices and the active preset, excluding the option's own ordinary choice. There are boolean option states and one exact Product total; numeric option counts are canceled.
- Presets are atomic OR constraints, including properties without a visible ordinary filter. A newer ordinary choice can evict an incompatible preset. Singleton presets clear the corresponding manual value and hide its field; multivalue presets keep the field visible and retain compatible manual values. Clicking the active preset clears it. Removing it does not restore discarded values.
- `RESET` clears ordinary choices and the Sub Group; separate Clear controls are absent. Filters, compatibility, total, notices and versioned `d[...]` history update locally. Obsolete pagination parameters are removed without changing valid precedence; unrelated query parameters and history state survive. Back/Forward restores choices without another history entry or filter-state request.
- Missing/invalid precedence falls back to preset first, followed by selected fields in configured order, then newest-first reconciliation. Invalid shapes, unknown fields, foreign/stale presets and unsupported URL versions are bounded and produce adjustment feedback.
- Cards appear only for totals within `max_results`, with explicit `all` retained. No pagination in this flow. Legacy pagination storage is retained, with its admin controls hidden. Force-hide storage is inert and absent from runtime/admin payloads.
- `result_settings.products_debounce_ms` is a nonnegative integer in steps of 100, default `0`. It delays card requests only. Empty/above-threshold states need no card request. Effective changes cancel the pending timer; no-op actions do not duplicate requests.

## Snapshot and write ownership

`BuildCatalogSnapshot` reads Product ID and only registered filter/preset JSON properties in one Product SELECT, ordered by ID. It derives vocabulary from that read, respecting configured ordering before the deterministic remaining values. `CatalogSnapshot` is an operation DTO; the cache stores only arrays/scalars because class deserialization is disabled.

The wire schema contains `schema`, string `groupId`/`revision`, columns/dictionaries, visible fields with explicit value/label/code records, presets, request/display settings, minimal Group card labels, diagnostic summaries, and rows `[string Product ID, integer value codes...]`. Code zero is valid; `-1` means missing/invalid. Canonical strings retain zero, leading zeros, case, whitespace, quotes and Unicode. Missing/null/empty cells make no options but retain their Product rows. Malformed numeric/boolean/structured cells are diagnosed, never string-coerced. Descriptions, parts, media and unrelated properties stay outside the dataset.

`groups.catalog_revision` defaults to 1. `CatalogRevisions` advances it atomically inside supported write transactions. Imports aggregate old/new Group IDs, hierarchy edits include affected descendants, settings include filters/presets/cards, and definition seeding advances only when it creates definitions. The Group editor coalesces its nested detail/settings actions into one advance per affected Group. Dry runs, unchanged imports, unchanged saves and rolled-back batches do not publish advances. Model observers are not the bulk-write boundary. Future Product writers must join these boundaries.

`CatalogSnapshots` uses one replaceable cache key and one rebuild lock per Group, scoped by environment/database host/port/name. Values contain schema/revision and expire after 24 hours. Do not create revision-specific cache files: file-cache expiration does not sweep abandoned keys.

Acquire the lock outside application read/write transactions. Wait at most two seconds on a 60-second lease. Recheck revision and cache after acquisition. Build in a short nonlocking REPEATABLE READ transaction, finish reads before publication, and publish only under the actual revision. Retry a concurrent revision change at most once; a timeout/expired lock or continued changes produce retryable 503, never an unprotected duplicate build. MySQL connection isolation is explicitly REPEATABLE READ. A warm card request reads revision and Products in one consistent read; on cache miss, leave that transaction, rebuild under the Group lock, then retry once. Never wait for cache while holding the card transaction.

## Medium freshness

Authenticated `catalog.groups.dataset` preserves the catalog's existing access boundary. Private conditional responses use a Group/schema/revision ETag. Unchanged checks return 304 with one revision lookup and no Product read, even if the cache entry expired. Initial data is safely JSON-encoded once in the page and removed from the DOM after parsing; it never becomes a public Livewire property or large Alpine reactive object.

One scheduler shares a confirmation clock and an in-flight refresh. Successful current-revision card responses confirm freshness without an extra version request. Visible pages check after 60 seconds without confirmation; returning/focusing checks when eligible. Hidden tabs do not poll. Failed attempts are also throttled; manual retry remains available. The recurring cost is at most one background check per minute per visible page plus replacement downloads. Suspended/offline/failed requests cannot guarantee a strict 60-second bound.

Replace a changed snapshot atomically and reconcile the visitor's **current** choices, never the choices captured when the request started. Preserve valid selections and explain removals. Ignore obsolete refreshes and confirmations. Failures retain usable filters and expose retry without claiming freshness. Initial failure is unavailable, not an empty catalog. Deleted/branch Groups exit leaf filtering; unsupported snapshot schemas require reload, not a refresh loop. Clean up listeners/timers/pending insertion in `destroy()`, invalidate on `pagehide`, and resume once on `pageshow`, including cached-page restoration.

## Local engine, Alpine and cards

`catalog-filter-engine.js` is pure computation plus URL encoding. Normalize within available fields/presets, intersect newest-first constraints, then compute total/compatibility with one mismatch scan. Among rows satisfying the preset: zero ordinary mismatches contribute to total and all fields; one mismatch contributes only to that field; multiple mismatches contribute to none. Duplicate combinations remain distinct Product rows.

`catalog-filters.js` registers one Alpine data component using Livewire's bundled Alpine. Raw rows/engine/indexes and transport bookkeeping live in the component factory closure, outside the reactive proxy. Reactive state holds only choices, display states, notices, totals and request status. Stable option identities use Group/property/canonical value. Keep option objects stable between selections; update only changed selected/compatibility flags. Capture the component root in `init()`: Alpine `$el` can refer to the button invoking a method, so asynchronous dataset URLs and Group identity must use that captured root. Labels use escaped text. Controls remain keyboard-operable and focus is moved deliberately if its field becomes hidden. Explicit `wire:ignore` boundaries give Alpine ownership; no entanglement, second Alpine start, `wire:model.live`, islands or overlapping URL binding.

`Catalog\GroupShow` remains class-based with a locked string Group ID. Its `#[Json] loadCards(criteria, revision, requestId)` uses protected `boot()` service injection with no catalog queries in boot. The installed JSON handler invokes the method directly, so action argument DI is not assumed. Validate shape, keys, exact values, preset ownership and authorization; never trust client Product IDs/totals. Map expected missing/forbidden exceptions to HTTP exceptions, report unexpected exceptions once, and handle rejected JSON promises. Responses contain requestId/revision/status/verified total/escaped Blade HTML chunks; statuses are `ready`, `empty`, `above_threshold`, `refresh_required`.

Cards bypass `CatalogDiscovery::prepare()` completely. Match trusted snapshot rows, enforce the current threshold, then fetch Products once, scoped by Group and ordered by code/ID. Shared Group labels come from the snapshot; Blade performs no relationship queries. Render after ending the database read transaction. Append escaped application HTML in chunks of at most 24 between animation frames, without reparsing older chunks. Hide superseded cards immediately and retire their chunks between frames before inserting the new response; do not clear hundreds of cards in the filter-update task. All eligible Products eventually appear.

Every selection/reset/history restore/snapshot replacement invalidates earlier results and unfinished insertion. Responses apply only for the current component instance, request identifier and revision; superseded responses cannot touch cards, filters, loading/errors or freshness. Hide old cards while current cards load, with results-only errors/busy state. Permit one automatic refresh/card retry on revision mismatch, then require explicit retry. Cancellation is an optimization; identity checks establish correctness and cannot undo server work already performed.

## Verification and release

Use real browser interaction/DevTools, focused existing PHP tests and dependency-free Node engine checks. Preserve independent A/B/C expected identities, presets/compatibility, zero/Unicode/escaping, thresholds, 0/100/300 delay, no-op requests, reordered/delayed cards, partial insertion, malformed URLs, Back/Forward, conditional freshness, failure/recovery, keyboard/focus/narrow screens and page lifecycle coverage. Count HTTP requests and action executions separately; distinguish deliberately induced failures from application defects. Performance target: p95 local feedback below 50 ms on the recorded 501-Product browser/device, measured separately from cards/network/SQL and with paint estimates explicitly labeled.

Catalog budgets: local choices zero filter-state requests/SQL; unchanged freshness one revision lookup/no Products; warm eligible cards revision lookup plus one Product SELECT/no COUNT; snapshot rebuild one Product SELECT plus bounded metadata reads. Auth/session/cache/transaction overhead is reported separately. Local evidence does not establish production improvement.

Browser-test infrastructure remains deferred. Retain for a later task: inspected starting versions Pest browser 5.0.1/Playwright 1.62.1 need revalidation; shared tests disable Vite and browser tests need built assets; PHP time travel does not drive browser timers; persistent locks need a unique persistent store; concurrent DB tests need committed fixtures/two real connections outside fixture transactions; verified network controls must retain real success responses; collect console errors/unhandled rejections; initially run serially behind the existing database allowlist.

Before release, migrate the revision column, ensure every supported writer uses revisions, drain old writer processes, build assets, and warm with `php artisan catalog:snapshot GROUP_ID`. After restores/out-of-band changes, use targeted `--invalidate` before reuse and reload already-open pages after a restore that rewinds revision history. Do not clear unrelated application caches. Repeat the same browser/server comparison remotely after an authorized deployment. Reconsider islands only for a remaining measured bottleneck.

---

## Earlier discovery design: historical reference

Server-owned discovery actions, joint rendering, Light freshness, islands and pagination recommendations below are superseded by the governing contract above. Unrelated Product/configurator requirements remain applicable.

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

**Lens findings:** automatic criterion removal needs visible feedback; incompatible options need an explanation because those choices remain usable; selected state must survive Back; no hidden manual criterion should silently narrow a singleton preset; an unassigned Product remains a useful page. These are the material UX checks for this plan. New personas, extra dashboards and generic workflow machinery would not resolve them.

## 2. Proposed files, routes and component ownership

**Later user layout amendment, 2026-09-24:** public pages share centered navigation and a header light/dark toggle. Use full-width content with responsive padding. Group filters have compact 12px text and a responsive grid with up to eight columns on wide screens; narrower layouts retain usable controls and no horizontal overflow. The existing Flux preference owns appearance. This amendment changes presentation only, not discovery state, counts or precedence.

**Public shell amendment, 2026-09-24:** use the same Aquestia PNG favicon as the Filament admin panel. Add a Log in link at the right end of the header, after the appearance toggle, targeting the existing named admin login route. Keep the navigation centered; small screens place the right-aligned controls above the centered navigation to avoid overlap. No authentication or access behavior changes.

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
| `app/DTO/CatalogDiscoveryResult.php` | Current prepared state, option compatibility, matching total, pagination and change notices; request-local derived result, not public Livewire payload. |

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

Use the core annex's `GroupFilter` and `SubGroup` tables. `GroupFilter` has `group_id`, registered `property_key`, `label`, `sort_order`, `value_order` and `value_labels`; one record per Group/property. Display labels never become query keys. `SubGroup` has `group_id`, `label`, one registered `property_key`, nonempty distinct canonical-string `allowed_values` and `sort_order`. No source `sub_group` value automatically creates an application preset. A preset may use a registered Product property without a corresponding GroupFilter: it still constrains results, but creates no ordinary filter control.

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

When the selected preset has one value, clear any ordinary filter/order token for that same property before replay, then hide that field. Multi-value presets keep the field visible and may retain a compatible ordinary choice. There is no force-hide setting. Apply the same invariant to restored URLs; clearing/eviction of the preset restores the field, with no automatic manual choice.

`clearFilters()` removes ordinary choices/order tokens and preserves the preset. `resetAll()` removes both. Both reset page 1 and recompute visibility. Do not restore earlier discarded selections after an ordinary deselection; the reference does not keep that hidden history. Configurator remembered selections are an unrelated engine policy.

Use normal serialized Livewire actions, with no `.async` or `#[Async]` on discovery changes. Do not dispatch a separate browser request per filter, count and card region. The same final server result supplies controls, announcements, count and cards. Do not optimistically announce final matches or removals. Rapid action and Back-during-request tests remain necessary even though the framework documents serialization; duplicate/pending stale responses must not replace the current state after restoration.

## 6. Option compatibility, total count, blanks and query boundaries

Current option compatibility is boolean. Numeric per-option counts are canceled. The only numeric count required is the total number of matching Products. The user retained compatibility previews and alternative evaluation in the 2026-09-26 review. The sketch provides a responsiveness comparison, not an algorithm contract.

The current baseline asks whether at least one Product matches the Group, active preset and all ordinary filters **except this field's ordinary selection**, plus the candidate value. Keep the preset even when it uses the same property. Compatibility covers the entire Group, not just displayed cards. It describes coexistence before conflict reconciliation, not the eventual result after clearing earlier choices. Incompatible choices remain clickable; selected styling takes precedence over compatibility styling. Computing a numeric count for every value is unnecessary.

There is no synthetic “Not specified” option. Products with missing/null/empty properties remain in unfiltered results and can match other properties, but those blanks do not create filter buttons. Do not apply `empty()` or truthiness; canonical `"0"` is real. The original skip test does not trim whitespace; canonical import normalization is the agreed place for that work, not a new read-time mutation. Blank card facts can use an em dash, independently of facet vocabulary. Unexpected non-string JSON values need diagnostics; they are not coerced into equivalent string choices.

CatalogDiscovery owns a single composable Eloquent predicate for results, existence replay and aggregates: `group_id` scope; AND between ordinary scalar equality constraints; `whereIn` over a scalar JSON path for a preset OR-list. `whereJsonContains` is for array-valued JSON, not this scalar membership operation. Paths come from the registry and values are bound parameters. Verify strict case/Unicode/string matching and JSON-null behavior on actual MySQL against the JS/source contract.

The existing server implementation obtains vocabulary and boolean compatibility using distinct scalar values with JSON string/nonempty guards; it does not compute per-option counts. Preserve vocabulary values even when incompatible. The measured repeated vocabulary, type-diagnostic, reconciliation and compatibility queries are redesign candidates, not required per-click operations. Evaluate what can be prepared once, computed locally, reused, or returned independently of Product cards.

Read all data for one prepared result within one short coherent read operation; if using a transaction for snapshot consistency, verify the actual MySQL isolation behavior and keep it read-only with no row locks. Cache computed results **within the request** and invalidate that memo when an action changes discovery. Do not add cross-request caching before measuring and defining import/Product/metadata invalidation.

Order cards by `product_code`, then Product ID. **Later user amendment, 2026-09-24:** the Product Code is the card heading. Under it, show the real main (root ancestor) Group and the actual Product Group, omitting a missing main Group. Show every populated registered Product property as an escaped value without a visible label, in the property registry's stable order; preserve literal `"0"`, and omit blank/null/non-string entries. This replaces the former product-name heading and two labelled facts. Select only `id`, `product_code` and `properties` for the current page. **Keep those server-side view records out of Livewire public state; never select card `parts` or `extra_data`.** Load the Group trail once for the page and share it between the breadcrumb and cards. Discovery does not load configurator definitions, media or POC configuration relations. A source `product_pic` path is not yet a validated public image URL; use an honest absence/placeholder rather than automatic remote media lookup.

The proposed `(group_id, product_code, id)` index supports scoping and ordering. Measure representative initial, heavily filtered, zero-compatibility and paginated requests on the fresh MySQL schema; record query count/time, returned/rendered payload and `EXPLAIN`. Add generated/expression indexes only for demonstrated hot property predicates, with matching extraction/type/collation. SQLite success cannot establish these results. No generic search service or added package is justified by 501 starting Products.

## 7. Loading, no-match and Product behavior

The first leaf response renders count, cards and pagination immediately. Keep settled results visible during an update; use scoped delayed loading feedback and a busy result region, then a polite final count/removal announcement. Keep keyboard focus on the initiating control where it survives; if a preset change hides that field, move focus deliberately to the preset/current-criteria area. Use stable keys based on Group/property/value identity and Product ID. Do not use list indexes as identity or colour alone as state. Server labels are escaped; source HTML is not rendered as trusted markup.

Do not present incompatible ordinary choices as disabled configurator options. A request-in-flight indication is a separate state; consecutive valid choices must not be silently lost. Clear/reset controls identify their different effect. A transient request failure keeps the last settled UI and allows retry; no fake empty-success screen. The update ownership and scheduling mechanism is part of the current redesign review.

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
| Same-property hiding | Selecting a single-value preset clears a prior manual filter for that property. A multi-value preset keeps its field visible and can keep its compatible manual filter. Clearing/evicting S restores the field without inventing a choice. Legacy hide flags have no effect. |
| Preset without ordinary field | A SubGroup on a registered property with no GroupFilter still restores from URL, constrains total/cards and participates in precedence. No ordinary control appears. |
| Toggle and resets | Reclicking a selected ordinary value removes it with no replacement. Clear filters preserves S; Reset all removes S. All real criteria changes use page1. |
| Boolean compatibility | With S+Flange, both Flange and Threaded are compatible alternatives (exclude ordinary connection, retain S); size1 is incompatible and size2 compatible. Size1 remains actionable. With only manual pressure25, both pressure16 and pressure25 are compatible alternatives. No numeric per-option counts are returned. |
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
