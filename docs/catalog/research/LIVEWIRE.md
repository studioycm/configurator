# Public catalog and Livewire research

> Historical research evidence. Execute the [final plan](../IMPLEMENTATION_PLAN.md) and [guidelines](../IMPLEMENTATION_GUIDELINES.md); their contracts supersede earlier alternatives here. Dates, versions, access and test observations are snapshots, not fresh verification.

**Researched:** 2026-09-23. **Status:** research and technical recommendations only; no application implementation, database write, dependency change, or deployment.

This document supports W1/W4 in [the living plan](../DECISIONS.md). Confirmed business decisions in that plan remain authoritative. The latest conversation additionally accepts a fresh MySQL database (item 11-A) and treats whole-configuration-code conditions as a legacy capability to assess, not an agreed new business requirement. Neither clarification changes the public discovery policies below.

## 1. Conclusion for the implementation plan

Use a full-page Livewire component to own each leaf Group's discovery interaction, with reusable Blade presentation and selected Filament components. Keep filters, SubGroup, selection precedence, counts, cards, pagination and their URL snapshot coordinated. A Product page is a separate page that loads the selected Product and its explicit Group configurator assignment.

Two domain operations must remain separate:

- A discovery reconciler finds existing Products and preserves the newest choice by removing incompatible older constraints. It does not evaluate configurator rules.
- The shared configuration engine evaluates options on the Product page and in admin Preview & Test. Disabled configurator options stay unclickable; they do not use discovery's conflict-clearing interaction.

The libraries supply rendering, state transport, URL binding and pagination. Neither the examples nor Filament's filter system supplies the agreed newest-choice/SubGroup policy. Implement that policy once in a small independently testable domain operation, with one reusable query predicate used for existence checks, results and facet counts.

## 2. Evidence and provenance

Laravel Boost `application-info` confirmed **PHP 8.4, Laravel 13.33.0, Livewire 4.4.6, Filament 5.8.4, Boost 2.9.1, Pest 5.2.1, MySQL**. No MySQL benchmark or new-schema rehearsal was performed. The living plan records a prior MySQL 8.0.46 inspection; reconfirm the server before schema work.

Read `AGENTS.md`, the complete living plan, the Livewire and Filament development skills, and the testing skill. `.ai/rules` did not exist during this pass. Existing application components are class-based under `app/Livewire`; `config/livewire.php` sets `component_layout` but does not select a generator format. Follow the application's component convention explicitly when implementation starts.

| Source | Evidence obtained | Appropriate use / limitation |
| --- | --- | --- |
| [Livewire 4 URL state](https://livewire.laravel.com/docs/4.x/url), [pagination](https://livewire.laravel.com/docs/4.x/pagination) | Version-scoped Boost documentation; relevant installed PHP and compiled JavaScript inspected | Current API authority. Browser behavior of the proposed catalog bindings still needs a focused check. |
| [Livewire 4 nesting](https://livewire.laravel.com/docs/4.x/nesting), [islands](https://livewire.laravel.com/docs/4.x/islands), [async actions](https://livewire.laravel.com/docs/4.x/actions#parallel-execution-with-async), [loading](https://livewire.laravel.com/docs/4.x/wire-loading) | Version-scoped Boost documentation and installed island implementation | Supports component boundaries and loading policy; no catalog performance claims established. |
| [Filament 5 embedded forms](https://filamentphp.com/docs/5.x/components/form), [table filters](https://filamentphp.com/docs/5.x/tables/filters), [card grids](https://filamentphp.com/docs/5.x/tables/layout#arranging-records-into-a-grid) | Version-scoped Boost documentation | Current API authority for adapting examples. |
| [Laravel JSON queries](https://github.com/laravel/docs/blob/13.x/queries.md#json-where-clauses) | Version-scoped Boost documentation | Distinguishes scalar JSON comparisons from array membership. |
| [MySQL JSON indexing](https://dev.mysql.com/doc/refman/8.0/en/create-table-secondary-indexes.html), [generated-column index matching](https://dev.mysql.com/doc/refman/8.0/en/generated-column-index-optimizations.html) | Official MySQL 8.0 reference read online | Feasible index options; does not prove a chosen expression/index will be used on the eventual schema. |
| [LaravelDaily sidebar filters](https://laraveldaily.com/post/livewire-sidebar-filters-e-shop-products) | Full premium article, code and discussion read in an authenticated browser; published May 2, 2023 | Useful self-excluding facet counts and a shared predicate. Livewire 2-era syntax/state shape is unsuitable for direct copying. |
| [LaravelDaily Livewire 4 Islands lesson](https://laraveldaily.com/lesson/livewire-v4/islands) and [course repository](https://github.com/LaravelDaily/Livewire-v4-Course-Demo) | Authenticated lesson opened, repository link verified, actual Vimeo Transcript UI opened and introductory cues read | Existing PodText notes contain deeper prior course analysis against 4.3.4; key island mechanisms were rechecked in installed 4.4.6. The entire video was not rewatched. |
| [LaravelDaily homepage defer/lazy article](https://laraveldaily.com/post/optimize-laravel-e-shop-homepage-with-livewire-defer-and-lazy) | Authenticated page and video availability confirmed; dated June 4, 2026 | Discovery lead only. Its video was not analysed in full and is not used as proof of a catalog implementation. |
| [FilamentExamples public table](https://filamentexamples.com/project/filament-v4-public-table-outside-any-panel) | Publisher page plus actual MCP-returned source for `v4/tables/public-products-table/app/Livewire/Products.php` | Proves public embedding pattern. Page is currently labelled Filament 4/5; source is stored under a v4 path. Adapt to current schema contracts. |
| [FilamentExamples dynamic filters](https://filamentexamples.com/project/filament-v4-dynamic-real-estate-filters) | Publisher page plus actual MCP source for `v4/tables/real-estate-table-with-complex-sale-rent-price-filter/.../HomesTable.php` | Useful reactive visibility and grouped controls. Uses deferred filtering; does not implement recency reconciliation or compatible-value counts. |
| Client filter reference | Static `filter.js`/explanation inspected and real browser choices exercised at the provided hosted sketch | Business interaction evidence, including actual order-sensitive cases below. Single-result NEXT behavior remains superseded. |

The local PodText `docs/research/laraveldaily/README.md`, `index.md`, `catalogue.md` and `livewire-v4-notes.md` were used for discovery and prior research context. Their old browser tool names, project conventions, installed versions and private acquisition workflow were not copied into this application. These are distilled notes; no raw subscriber archive, video, session, or credentials was copied into the repository.

## 3. Browser findings: selection order is necessary state

The reference implementation stores both a selected field/value map and an oldest-to-newest field list. Selecting a new value starts a fresh result with that value, then attempts to restore old selections **newest to oldest**, keeping each only if a Product still matches the combined constraints. Clearing a selected value removes that field from the order. This is more precise than simply clearing every field individually incompatible with the new value.

The reference was exercised in a separate research tab and reset to 501 matches afterwards:

| Interaction | Observed result |
| --- | --- |
| 40 bar → Standard Flow | 24 → 10 matches |
| Then click incompatible Composite | 84 matches; pressure cleared, Standard Flow retained |
| Reset; 25 bar → Flange | 85 → 63 matches |
| Then click incompatible 1″ | 36 matches; Flange retained, 25 bar cleared |
| Reset; Flange → 25 bar | 384 → the same 63 matches |
| Then click incompatible 1″ | 9 matches; 25 bar retained, Flange cleared |

Both two-filter states describe the same 63 Products, yet their next result differs. Therefore **saving only selected values in the URL loses behavior**. Browser Back, refresh and shared links must restore the precedence data as well as the visible choices. This follows from measured reference behavior; it is not a request for a new feature.

The sketch currently keeps the same URL throughout these interactions and has no SubGroup controls or pagination. It cannot verify the planned URL/SubGroup integration. That work remains an implementation acceptance target.

## 4. Proposed discovery state and transition boundary

The following is a technical design candidate, not approved class names or a new business-policy register.

State needs the leaf Group identity from the route, ordinary selections, an ordered list of active discovery constraints, optional SubGroup identifier, page, and permitted page size. Product code is the initial sort; expose no arbitrary client-supplied SQL sort column. Facets, counts, matching Products, visibility and explanatory notices are derived results rather than trusted client inputs.

An ordinary choice action should:

1. Resolve the Group and current group-level filter metadata on the server. Validate the requested property/value against the leaf Group's actual selectable catalog.
2. If that field/value is already selected, remove it and its precedence entry. Do not auto-select a replacement.
3. Otherwise make the new selection highest precedence and replay older active constraints newest to oldest. Test coexistence against the **whole leaf Group**, never the current paginator page.
4. Keep compatible constraints; remove conflicting ordinary choices and any conflicting preset. Return what changed so the page can briefly explain it.
5. Recalculate field visibility, reset the page to 1, then render counts and paginated cards from the settled state.

**SubGroup adaptation to validate in examples:** represent a selected preset as one ordered constraint containing an OR set of values for its configured property. The preset remains distinct from an ordinary selection: it does not populate that field with an invented manual choice. Selecting/switching a preset is a new discovery action; replay remaining constraints with the same reconciler. A conflicting newer ordinary selection can remove the preset. This is a proposed extension of the observed algorithm, not a claim that the existing sketch implements preset ordering. Specifically demonstrate three-way conflicts involving a preset during design review so that any business interpretation is visible.

Single-value preset hides its field; multi-value preset leaves it available unless force-hide is set. Removing the preset restores ordinary visibility. An ordinary constraint on the same property intersects the preset's allowed values while both survive. Never allow preset removal to broaden the actual Group scope.

Use explicit actions for `selectFilter`, preset change, Clear filters and Reset all. Clear filters removes ordinary choices and their precedence entries while retaining the preset. Reset all clears both. Pagination actions only change the page. This avoids a blanket `updated()` hook resetting pagination during URL restoration or unrelated state changes.

## 5. URL and pagination details that need a small browser proof

Livewire 4 supports array URL properties, aliases and `history: true`. Attribute URL state uses history replacement by default. The installed paginator tracks its page using history by default. `resetPage()` is the supported criteria-change operation. A normal length-aware `paginate()` fits the requested count/page-number interface; cursor pagination would change navigation semantics and offers no established need for 501 starter Products.

**Final handoff resolution:** the public contract selects one URL property and explicit Eloquent pagination, with **no WithPagination**. The alternative below is retained as earlier research only.

**Candidate binding shape:** one URL-bound discovery snapshot with a schema version, selected filters, ordered constraint keys, preset, page and permitted page size. A single coordinated property makes one user interaction a coherent history step. If using `WithPagination`, its separate URL tracking can be disabled with `WithoutUrlPagination` and connected to this snapshot through one small pagination adapter. Keep the adapter as the sole page owner; do not maintain two independently authoritative page values.

**Why verify before committing to the shape:** installed Livewire 4.4.6's `supportQueryString` logic registers a commit handler per URL property and invokes `push()` for each changed history property; the history coordinator batches replacement but performs pushes directly. Thus separate history-bound preset, filters, order and paginator values may create intermediate entries when one action changes several values. This is a source-based risk, not a reproduced framework bug. Browser-check a multi-property alternative if preferred; reject any binding that requires multiple Back presses or restores an intermediate combination. Do not add custom global JavaScript or a query-string package before this focused proof.

Relevant installed source:

- `vendor/livewire/livewire/src/Features/SupportPagination/HandlesPagination.php`: paginator query-string history and page hooks.
- `vendor/livewire/livewire/src/Features/SupportQueryString/BaseUrl.php`: array hydration and invalid-type handling.
- `vendor/livewire/livewire/dist/livewire.js`: `HistoryCoordinator`, `track2` and `js/features/supportQueryString.js` sections.

**Proposed validation/replay rules for old or manipulated URLs:**

- Route Group identity is authoritative. Resolve presets within that Group and use server metadata for JSON property paths; ignore unrecognised keys rather than concatenating them into SQL.
- Bound collection sizes and string lengths. Preserve canonical source strings, including case, Unicode, leading zeros and a legitimate `"0"`. Do not use PHP truthiness or `empty()` as value validity.
- Remove stale fields, absent values and presets no longer available to the Group, with a concise adjustment notice when relevant. Never trust URL-supplied facet lists, allowed values or result counts.
- Precedence entries may reference only retained selections/preset; deduplicate and remove unknown entries. If an old link has valid selections but no usable order, use a documented deterministic group-filter order. This cannot reconstruct unknown historical recency; label it as compatibility fallback, not equivalent restoration.
- Reconcile an incompatible restored snapshot deterministically using that order and current data. Keep a valid restored page; reset only if reconciliation changed criteria or the requested page is outside the result range. Validate page-size choices against group settings; disregard visitor changes when that control is disabled.
- Canonicalizing malformed/stale state should replace the current history entry, not create a Back-navigation loop. Do not apply the ordinary click page-reset hook blindly to every property update caused by `popstate`.

Use a complete snapshot on every state-changing action. A Product link should retain an ordinary browser back path to discovery; configurator Territory/Application session persistence remains deferred.

## 6. Facets, counts and MySQL JSON

Define one query contract: leaf Group scope + optional preset + validated ordinary constraints. Ordinary fields use equality; a preset with several allowed scalar values uses `whereIn` on that JSON path. `whereJsonContains` is for JSON arrays and is not a substitute for matching one string against a preset's alternatives. Apply AND between independent property constraints.

**Proposed count meaning:** for each visible field, count Products under all active constraints **except that field's ordinary selection**. Preserve the preset while computing these compatibility counts, even when it references the same property. Group by the candidate scalar value across the whole matching scope, not the ten displayed cards. This combines the reference's compatibility test with LaravelDaily's self-excluding count pattern.

This means a value can display zero for the current combination and still be clickable. Its click may clear older choices or the preset and produce results. The count is not a forecast of the reconciled result. Hiding all zero-count choices or disabling them would break the approved interaction. A short contextual explanation should make this understandable without technical language.

Build the value vocabulary from actual Products in the leaf Group. Use configured value order first and append existing data values omitted from that ordering, matching the reference's protection against stale configuration. Values absent from data should be diagnosed in admin rather than shown as meaningful choices. Reconcile metadata against the agreed import mapping; no new generic EAV store is needed.

For scalar strings, grouped SQL can use the same unquoted JSON extraction expression as the predicates. Keep unknown/missing keys, JSON null, empty strings and legitimate values distinct until the import/field contract specifies their treatment. The sketch skips missing/null/empty values; preserve that as the initial candidate, without silently converting meaningful source blanks to false.

Prefer one aggregate per configured field, or a measured equivalent, over a count query per value. Use existence checks during the bounded recency replay. Do not load all Product models and filter the paginator in PHP. Select card facts only and eager-load any necessary relationships; avoid loading `parts`, `extra_data`, media or configurator definitions merely to list cards. Cache derived results per request; any longer-lived facet cache needs invalidation for imports, product changes and group metadata changes.

Start by measuring the resulting queries on the fresh MySQL rehearsal. A Group/product-code index can support scoping and stable ordering; use Product ID as a deterministic tie-breaker where needed. JSON itself is not directly indexed; generated scalar columns or supported expression indexes are available for demonstrated hot paths. Generated-column expression type and unquoting must match the queries; inspect the actual `EXPLAIN` result before claiming improvement. Index decisions here do not introduce the deferred physical Product `name` column.

Record representative cold/warm query duration, query count, result correctness and rendered payload size. SQLite passing tests do not verify JSON extraction, collation, null semantics or MySQL index selection.

## 7. Component composition and reusable examples

| Surface | Suggested boundary | Reason |
| --- | --- | --- |
| Group navigation | Server-rendered page/Blade tree or a small page component if interaction requires it | Parent nodes navigate; filtering and configurator inheritance do not happen there. Load a bounded hierarchy deliberately. |
| Leaf discovery | One Livewire page owning the complete discovery state | Filters, facet counts, results and recency are tightly coupled. Reduces event synchronisation and stale sibling state. |
| Filter groups, count labels and Product cards | Blade components receiving prepared data | Reuse markup without one reactive component/snapshot per value or Product. Use stable keys for moving/replaced rows. |
| Product page | Independent route/page, selected Product resolved server-side | Fixed product facts and group assignment come from the actual record, not the POC slug or first stored configuration. |
| Product configurator | Reusable adapter/component around the shared engine result | Public configuration and admin preview use the same engine. Product facts remain trusted server inputs. |

The FilamentExamples public-table source demonstrates a Livewire component rendering a Filament table outside a panel. This makes a Filament card-grid table feasible. However its default filter form, indicators, pagination state and count behavior must not become a second discovery state model. A custom Livewire page with reusable Filament controls is the simpler initial candidate for the reference's clickable incompatible values. Decide the precise markup during the UI plan; this research does not require a table-based public UI.

The dynamic real-estate example contributes `live()` controls, conditional groups and table-state-driven visibility. Its `deferFilters()` choice conflicts with the catalog's immediate updates; Filament 5 documents `deferFilters(false)` for live tables. Its example query uses truthiness for some numeric values, which should not be copied into source-string matching.

For embedded Filament 5 forms use the current `HasSchemas` / `InteractsWithSchemas` contract, a form state path, initial `fill()` and validated/transformed `getState()` for submission. The older example's `HasForms` shape is not the preferred current guide. A filter click still needs server validation against group metadata; a form component does not establish business validity by itself. Include action contracts/modals only when those actions are actually used. Public layouts must load the required package assets and must not expose admin actions.

Livewire child components are independent; a parent rerender does not automatically refresh a child's input props. Do not solve ordinary presentation by splitting filters, counts and cards into siblings that all maintain copies of selections. Use nested components only for reusable independent state/lifecycles. Islands share parent state and can leave separately rendered regions stale; they are not automatically appropriate for this coordinated discovery surface.

## 8. Loading and interaction behavior

Render initial cards/count/pagination immediately, per the agreed plan. Keep existing results visible while a normal filter request settles, with a scoped delayed loading indication and a busy state on the affected results region. Preserve focus and provide a polite announcement of count/automatic removals. Colour alone must not distinguish selected, compatible and incompatible values; expose selected state and explain that an incompatible value remains actionable.

Use ordinary serialized Livewire actions for criteria/preset changes. Livewire 4's `.async` bypasses the request queue and is expressly unsuitable for shared UI state mutations. Multiple independent island requests can also compete for state. A rapid click sequence must yield the same final selections as the reference's sequential actions; test it, including delayed responses.

`wire:loading.delay` avoids brief flashes and `wire:target` can scope indicators. Do not disable incompatible values because their current compatibility count is zero. A request-in-progress indication is a separate condition. Optimistically announcing a final count or automatic removal before server evaluation is inappropriate here.

Deferred/lazy loading is a later optimisation for genuinely independent expensive content, such as a future data-heavy Product tab. It must not gate the initial catalog cards or pretend deferred mapper content exists. Existing installed island code confirms `lazy` is intersection-driven, `defer` uses initialisation, and `always` affects parent rerender behavior; adopt only when a measured bottleneck justifies the synchronisation cost.

## 9. Concrete validation targets

These are proposed acceptance cases, not newly written/passing tests. Use feature coverage for query/state contracts and a small browser pass for history, focus and request timing. Use an independent three-product fixture for precedence logic rather than deriving expected outcomes from the reconciler. Retain a separate real-data check for the measured source sequences.

| Test target | Discriminating expected result |
| --- | --- |
| Same selections, different order | Real-data 25 bar → Flange → 1″ gives 36; Flange → 25 bar → 1″ gives 9, with the appropriate older field cleared. |
| Back then new incompatible click | Snapshot of 25 bar → Flange at 63; navigate onward, Back to that snapshot, then choose 1″. Flange survives and result is 36. Reverse-order snapshot yields 9. |
| History atomicity | A choice that removes two old fields and a preset is one coherent history step. One Back restores the previous complete choices, order, preset, visibility and page. |
| Valid restored page | Browse to page 2, change filters (page 1), then Back. Restore page 2 when still valid; URL hydration itself must not erase it. |
| Ordinary toggle | Clicking a selected value clears it with no replacement; its precedence entry disappears. |
| Preset interaction | Single-value preset hides its field; multi-value preset leaves it available; force-hide works; conflicting newest choice clears preset, restores field and stays in Group. Include a three-way conflict. |
| Reset distinction | Clear filters preserves preset; Reset all clears preset; both reset page. |
| Facet meaning | Self-selection excluded from its own counts; other ordinary constraints and preset retained. Zero candidate remains clickable. Counts include all pages. |
| Pagination | 23 records, sizes 10/2/1, every record accessible in deterministic order. Size 1 does not require one total result. |
| Stale/manipulated URL | Unknown property, foreign preset, repeated precedence token, invalid value/type, oversized page size and deleted Product values yield a bounded canonical state without SQL-key interpolation or restoration loops. |
| JSON precision | Canonical strings with leading zeros, `"0"`, Unicode/quotes, missing key, JSON null and blank follow the agreed field contract; verify on MySQL. |
| Rapid actions | Consecutive choices under delayed network responses preserve latest-action precedence and show matching controls/count/cards. |
| Record identity | Two Products in one Group and one in another use their own facts and correct assigned definition. Unassigned Product remains visible with honest absence/placeholder state. |
| Shared engine boundary | Same Product/context/selections evaluated by public component and admin preview produce identical configuration results; discovery never clears disabled configurator choices. |
| Browser accessibility | Keyboard toggles, focus after rerender, clear/reset labels, busy/count announcements and narrow screens remain usable; no horizontal overflow inherited from the sketch. |

## 10. Iteration results and remaining gaps

First pass established APIs, example suitability and the reference's exact reconciler. Second pass closed material gaps by checking installed URL/history and island source, reading the authenticated sidebar article, inspecting a Livewire 4 lesson transcript, checking MySQL index constraints, and comparing reversed selection order in the actual browser.

No authentication blocker remains for the material read. FilamentExamples MCP provided actual source excerpts, not only search snippets. Publisher pages offered videos/source access, with no live application demo link observed for the two selected examples. Their apps were not installed or executed.

Remaining work belongs to the reviewed implementation design: demonstrate the preset-as-ordered-constraint extension; select and browser-prove atomic URL/paginator binding; settle the exact field/count labels and malformed-link feedback; supply real parent-group metadata; measure final JSON queries on the new MySQL schema. No full course reread, extra UI framework, search service, dependency or speculative generic rule engine is needed to close those gaps.
