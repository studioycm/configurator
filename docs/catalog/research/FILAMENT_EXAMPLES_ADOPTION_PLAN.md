# FilamentExamples adoption proposal for the Configurator workspace

Date: **2026-10-08**. This is the requested research-derived plan. It does **not** authorize application implementation or dependency installation. Evidence is in [workspace research](FILAMENT_EXAMPLES_WORKSPACE_RESEARCH.md) and [Laravel architecture findings](FILAMENT_EXAMPLES_LARAVEL_PATTERNS.md).

Follow-up: the [select and item-drawer approval inventory](SELECT_PREPOPULATION_AND_ITEM_DRAWER_REVIEW.md) lists actual selectors with initial-count proposals, Product edit ownership decisions, all available count-drawer row types, and Group/other persistent-tab link corrections. Its new capabilities remain proposals for approval.

## 1. Authority and current baseline

Retain the [admin implementation plan](../ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md), [Configurator workspace plan](../CONFIGURATOR_WORKSPACE_IMPLEMENTATION_PLAN.md), and [card appearance handoff](../CARD_APPEARANCE_ADMIN_UX_HANDOFF.md). This document adds proposals and identifies reusable work; it does not reset their requirements or mark unfinished work complete.

Research inspected HEAD **`fb0cf2a499ee209c2f0c652703df0aa9a55d0aac`**, the inline-creation/tags checkpoint, plus an actively modified Configurator working tree. The latter includes split sizing, rule/option editing, mapping behavior, preview, domain actions, and tests. Those dirty files were read only; their current presence is **not** a verification or completion claim. Before implementing an item below, reconcile that task's latest source and handoff and choose nonoverlapping edit ownership.

| Capability | Current source evidence | Plan treatment |
| --- | --- | --- |
| Master Value/Attribute/Option right-hand creation/editing | `SplitListRecords`, `SplitCreateRecord`, resource pages and shared related-editor view | Reuse; no return to standalone Create pages |
| Inline scoped search, robust Filters, quick filters/tags, action groups | `TablePresentation`, toolbar views; Master Value tags row | Preserve and assess gaps through actual visual review |
| Count hover previews and searchable item drawers | `ItemCountColumn`, `ItemLists`, `ItemListDefinition`, `ItemListDrawer` | Reuse for both relations and non-model lists |
| Table-column resizing | `resources/js/table-column-widths.js`, local scoped preferences and keyboard/reset behavior | Baseline; no header reordering |
| Modal/slide-over width steps | `resources/js/workspace-dialogs.js`, global action window adapter | Baseline: narrower/wider/reset; do not duplicate with demo sidebar JS |
| Configurator table/editor split | Dirty `workspace-split.js` and related-editor view; 33/50/67% stops | Concurrent work; validate after its session finishes before extending |
| Compact shell and appearance settings | `AdminAppearance`, its save action/page/model, panel width/logo APIs and theme | Approved settings remain canonical |
| Configurator tabs / badges / preview | Dirty EditConfigurator composes separate lazy child components; aggregate count badges | Measure current lifecycle before layering schema/badge deferral |

Existing constraints continue: inline search has priority; small selector or suffix/prefix control scopes it; robust combined constraints stay available. Quick toggles suit about six or fewer choices. Frequent actions remain visible and other icon actions have tooltips/accessibility labels. Cell padding stays 4–8 px vertical / 8–12 px horizontal, gaps 6–12 px, page-main padding 4–6 px. Hints use the existing question-mark helper pattern. A tab with a single general field group does not need a redundant bordered Section.

Do not reopen accepted status behavior. Required disabled Attributes/inclusions make configuration unavailable until repaired/re-enabled; shared Option Hidden and Disabled both prevent selection; disabling a Configurator offers the accepted three impact choices. Dashboard configuration ignores separate global Territory/Application controls; future public context authoring is retained/collapsed as agreed. Nothing in these examples changes the engine or discovery contract.

## 2. Recommended order

| Stage | Deliverable | Dependency / reason |
| --- | --- | --- |
| A0 | Reconcile active workspace work and record a visual/performance baseline | Prevent duplicate controllers and false “new” features |
| A1 | Contextual read area using existing usage/blocker drawers | Highest relevance to fewer screens/clicks; no package required |
| A2 | Selective Group/form schema deferral | Only after lifecycle and unvisited-save tests; no package required |
| A3 | Finish shared resize behavior and optional navigation-width adapter | Keep existing stepped controls; avoid draft-remount bugs |
| A4 | Small native/custom cell and quick-filter refinements | Preserve search, visible actions, and column manager |
| A5 | Optional compact catalog-health dashboard | Needs defined actionable metrics; native first |
| A6 | Optional right-click/FilaWidgets packages and profile styling | Separate choice and dependency approval; lowest priority |

A0–A4 extend the accepted workspace direction. A5–A6 are optional ideas, not new mandatory business features.

## 3. A0 — Integration boundary and baseline

1. Read the current plans, local rules if present, and active session handoff. Inspect Git status without altering another task's changes. Reconfirm installed package versions before relying on a newer API.
2. Keep changes within existing directories. For any new PHP/test class, inspect siblings and the relevant `php artisan make:* --help`; generate with `--no-interaction`. No scaffolding is needed to finish this research.
3. Use `planning-filament`, `filament-development`, `livewire-development`, `laravel-best-practices`, the performance skill for query/lifecycle work, and Alpine guidance for sizing. Read `testing-best-practices` before adding behavior tests. Search Boost documentation scoped to relevant packages before new framework-dependent changes. Pure copy/layout edits need visual verification rather than artificial PHP tests.
4. Establish desktop, narrow desktop, and 390 px mobile evidence for Master Values, Options, Attributes, Groups, Products, Configurators, admin settings, action dialogs, and dashboard pages using Filament components. Include open/closed navigation and dark mode. Use Boost `get-absolute-url` for local links and recent `browser-logs` for errors. Herd already serves the app; do not start another server.
5. For performance candidates, separate initial response/SQL time and query count, Livewire payload, browser rendering, and first tab/drawer-open latency. Record several interactions with warm and cold caches. Do not substitute the example publisher's figures for our evidence.

Gate: only implement a change where the latest code still has the relevant gap; document existing functionality as reuse. This gate requires source reconciliation, not another permission prompt for work already authorized in an implementation session.

## 4. A1 — Context inside the record workspace

**Proposed behavior.** The current right editor gains a compact, secondary “Usage & blockers” read area for the selected shared record. Start with Attribute, Option, and Master Value because our canonical usage registry already supplies Options, inclusions, defaults, and Rules. For Groups/Configurators, reuse their supported assigned Groups, Products, and inclusion lists where relevant. Show a label/status and bounded count links; do not render a complete relationship table beside another full table until requested.

**Placement.** Keep table and editor as the two main panes. In the editor, use a collapsible context area or a small secondary tab; stack below the form on mobile. Do not place a third sidebar beside the pair. A saved-context label makes it clear that usage/availability describes persisted data. Hints and count previews remain accessible by focus as well as hover.

**Implementation surfaces.** Reuse [ItemLists](../../../app/Services/ItemLists.php), [ItemCountColumn](../../../app/Filament/Resources/ItemCountColumn.php), [ItemListDrawer](../../../app/Livewire/Catalog/ItemListDrawer.php), shared editor views, and existing `App\DTO\ItemListDefinition`. Existing keys such as `canonical-attribute-rules`, `canonical-option-defaults`, and `canonical-value-inclusions` are allowlisted; resolve them through the service instead of taking a model class/query from client state.

If independently refreshing context warrants a child component, propose `App\Livewire\Catalog\RecordContext` under the existing Catalog directory, rendered by `Filament\Schemas\Components\Livewire::make(RecordContext::class)->data(...)->key(...)`. Use a one-column child schema. Lock the resource key/owner ID, map resource keys through a server allowlist, and reauthorize `manage-catalog` before every read/action. Keep it read-only; do not add sidebar status writes. Do not create this component when an ordinary prepared Blade section already serves the needed lifecycle.

**Events and drafts.** Current saves dispatch `catalog-record-saved` and Configurator operations use `configurator-updated`. Add owner/resource information or owner-qualified child refresh only where needed, preserving current listeners. The listener reloads read-only data after a successful domain save. It must not call `fill()` on the editable form or recreate a keyed draft component. Switching records retains the existing discard/draft-restoration behavior. `#[On]` is `Livewire\Attributes\On`; props require deliberate reactivity, not assumed automatic refresh. See [embedded components](https://filamentphp.com/docs/5.x/schemas/custom-components) and [events](https://livewire.laravel.com/docs/4.x/events).

**Action contract.** Drawer launch uses the existing authorized `Filament\Actions\Action`, `->slideOver()->stickyModalHeader()->stickyModalFooter()`. Delete/remove remains the existing domain action with confirmation and one navigable blocker link/action for each blocking type. Do not implement custom relationship deletion in a ViewColumn or Blade click handler. Existing filtered resource URLs are generated by named resource routes/API, not hardcoded URLs.

**Verification.** Extend relevant `ItemListDrawerTest`, `CanonicalDefinitionsTest`, and split-editor tests only for changed behavior: wrong/unknown owner denied; preview bounded; each blocker link resolves the right subset; successful save refreshes the selected context; failed save keeps draft and does not report success; unrelated workspace refresh cannot overwrite another draft. Browser-check keyboard drawer opening and mobile editor visibility.

## 5. A2 — Selective deferral and simpler forms

| Form / area | Recommendation | Reason |
| --- | --- | --- |
| Master Value, Attribute, Option | Keep ordinary fields eager; avoid new tabs purely to enable deferral | Small forms and immediate creation/editing benefit from fewer interactions |
| Group Details | Eager; general fields need no extra sole Section | Immediate identity/status/assignment context |
| Group Filters and Presets | Candidate child schemas, each with a stable unique key | Larger repeated lists/association controls; verify saved content in unvisited tabs |
| Group Presentation | Candidate expensive subsections only; preserve useful multi-section grouping | Existing card/filter/SubGroup settings share one aggregate save |
| Product details | Keep current scope; tabs only when at least two meaningful field groups exist | Do not import E8's inventory/SEO/variants domain |
| Configurator Groups/Attributes/Rules/Preview | Evaluate current independent lazy children first | A second deferral layer may add requests without avoiding hydration/query cost |
| Small preloaded count badges | Keep eager | Deferring a cheap aggregate can introduce unnecessary loading requests |
| Truly expensive count badge | Use native deferral after measurement | Avoid doing hidden-tab badge queries during initial render |

**Exact native components.** `Filament\Schemas\Components\Tabs`, `Filament\Schemas\Components\Tabs\Tab`, and `Filament\Schemas\Schema`. Keep explicit Tabs keys; wrap the selected child in `Schema::make()->key('unique-owner-area')->components([...])->deferLoading()`. Use `Tab::make(...)->badge(fn ...)->deferBadge()` only for the measured expensive count and retain unique parent identity. Distinct content/footer schemas require distinct keys. Do not key by a changing timestamp/revision or unstable list index. See [schema deferral](https://filamentphp.com/docs/5.x/schemas/overview#deferring-the-loading-of-a-child-schema) and [tabs](https://filamentphp.com/docs/5.x/schemas/tabs).

**Save contract.** Deferral changes rendering, not Group/Configurator validation and persistence. `SaveGroupSettings` preserves complete filter/SubGroup lists and stable IDs; only inner `result_settings` has its existing partial-merge behavior. Configurator updates continue through its complete-definition action. Do not add `->relationship()` to a domain-owned editor solely to match E8. Record switching must rebuild the existing cached editor schema before filling when its definition changes.

**Selection contract.** Existing-record batch association uses the already selected table-picker approach where scale justifies it. Mapping-set mapping stays on-workspace with simple checkboxes. Non-model catalog properties remain multi-selects unless another agreed need warrants a different control. Avoid preloading thousands of choices; deferring HTML does not eliminate the query/payload cost.

**Tests before rollout.** Extend `AdminTabLoadingTest` and relevant Group/Configurator tests: edit/save without visiting Filters/Presets preserves every association and unknown/unmodified setting; required error in an unopened deferred schema reveals the correct fields; invalid hidden relationship causes no partial write; switching record/schema type fills the correct schema; saved aggregate and revision are correct. Exercise actual domain entry points. Do not claim E8 already tests unvisited relationship saves.

Acceptance: measured improvement in initial render/payload without excessive first-open delay, extra SQL growth, altered saved data, or lost draft state. Keep the existing eager implementation if deferral only relocates cost and worsens interaction.

## 6. A3 — Resizing without form-state changes

Keep four separate purposes and one preference policy; do not turn E7's navigation resizer into a universal widget.

Individual form Sections do not each need a drag controller. Use the shared table/editor split as the main resize boundary, and let internal grids wrap with `minmax(0, ...)`/bounded content. Arbitrary independently resized nested sections would complicate mobile layout and preference persistence without a demonstrated workflow benefit.

| Purpose | Baseline / proposed control | Bounds / state |
| --- | --- | --- |
| Table/editor split | Reuse concurrent `workspaceSplit`; drag snaps to 33%, 50%, 67%; reset to 50%; keyboard arrows/Home/End/Enter | Client-only ratio; existing user/owner/tab versioned preference; stacked below 980 px container width |
| Table columns | Reuse `table-column-widths.js`; pointer + keyboard + reset | Existing 64–640 px bounds and table/user scope; no header drag reordering |
| Modal / slide-over | Reuse `workspaceDialog`; narrower, wider, reset | Existing 640 / 960 / 1280 / viewport steps, viewport clamp, action-purpose/user preference |
| Navigation sidebar | Optional new small adapter inspired by E7, keep native collapse | Existing AdminAppearance open width 192–320 px, rail 58–80 px; reset to current admin default |

**Optional navigation adapter design.** Native `Filament\Panel::sidebarWidth()`, `->collapsedSidebarWidth()`, and `->sidebarCollapsibleOnDesktop()` remain canonical. Use a narrow render hook only for the missing grip; leave existing collapse buttons available. Desktop-only grip is a focusable separator with vertical orientation, accessible name/current bounds, arrow keys for 16 px steps, Home/End for bounds, and reset control. Drag clamps width, uses pointer capture/identity, and cancellation restores the drag-start value. Do not auto-collapse at a narrow drag threshold in the first version; native collapse remains explicit.

Read/write one versioned user/panel-scoped width preference with guarded storage access. Blocked storage leaves the UI usable. No server write/request during drag. Reset removes the local override and uses the latest validated `AdminAppearance` default. Clamp a restored override to current bounds/viewport. Use the admin native `--sidebar-width` carefully; the dashboard catalog uses the app shell token `--aquestia-shell-sidebar-width` and different markup, so it needs an explicit adapter if adopted there. A global root override must not accidentally change unrelated layout previews.

Use existing `resources/js/app.js` registration/Vite loading and lifecycle cleanup. Do not register duplicate Alpine/Livewire instances. `destroy()` removes observers/listeners and cancels pending work; Livewire morph/navigation must not create duplicate grips. See [navigation](https://filamentphp.com/docs/5.x/navigation/overview) and [assets](https://filamentphp.com/docs/5.x/advanced/assets).

**Verification.** Review the completed concurrent split changes and `WorkspaceSizing.test.js` before editing. Test persistence after Livewire morph, record/tab switch and page return; pointercancel; blocked/corrupt storage; RTL movement/keys; collapsed rail/native toggle; narrow viewport; reset to changed admin defaults; unsaved editor text/selections survive. Check current table-column pointer math for RTL before claiming RTL support for that controller. In the browser, pointer movement must generate zero resize-specific Livewire requests. Width preferences remain separate from forms and drafts.

No free-pixel split resizing, server-backed per-account preference model, or plugin is needed for the first version. Those remain optional later choices if stepped behavior is too restrictive in real usage.

## 7. A4 — Custom cells, quick filters, and header behavior

**Custom cell candidates.** A two-line code/name cell, a compact rule kind/summary, or bounded tag overflow. Try `Filament\Tables\Columns\TextColumn` first. If it cannot express the desired cell, use `Filament\Tables\Columns\ViewColumn::make('field')->view('...')` with explicit underlying search/sort columns. Keep queries/eager loading in table configuration; no queries inside Blade. Use approved colors and escaped labels. An icon-only action retains label as tooltip and accessible name. See [custom columns](https://filamentphp.com/docs/5.x/tables/columns/custom-columns).

**Count cells.** Reuse `ItemCountColumn` + `ItemLists`; keep the hover/focus multiline preview bounded and click into the full searchable drawer. Non-model selected properties use an array definition, not an invented relation. Relevant edit/remove/association actions are domain-approved adapters; viewing a list does not automatically authorize deleting shared records. No macro migration is planned.

**Quick filters.** E5 contributes the compact segmented presentation. Integrate with the existing `TablePresentation` filter-state adapter, not chart `$filter` or a parallel state property. Suitable choices: current binary status, Option availability states with exact existing semantics, or a small known tag/choice set. Use exclusive radio semantics for one choice and `aria-pressed`/checkbox semantics for multi-choice filters. Values are server-allowlisted, Reset clears the same canonical table state, and the robust Filters interface reflects inline changes and vice versa.

**Header layout.** Inline search and its small scope control come first. Use `Search · Filters · Columns` plus visible frequent actions and a nonempty More group for narrow tables as already agreed. Table-level configuration chooses grouping; avoid a complex breakpoint-driven action registry. Master Value tags remain the second header row. Preserve native deferred filter updates for both `tags` and nested `tags.*` paths. No individual under-column search row returns.

**Verification.** Extend `ScopedTableSearchTest`, `TableConfigurationStandardsTest`, and relevant list/filter tests only when behavior changes: Code contains A AND Value contains Flange; global versus scoped search; quick toggle plus combined constraints; invalid values; reset; nested tag updates; selections/batch actions with a custom cell. Visual review confirms Products actions remain visible and Groups More is not empty, row/icon actions are discoverable, column visibility/order persists, and 390 px layouts fit without hiding search.

## 8. A5 — Optional actionable catalog-health view

Start with native `Filament\Widgets\StatsOverviewWidget` or a short native table/list. Propose only current-data metrics with useful destinations: disabled shared records, Configurators needing repair, or unassigned/blocked items **when a trusted existing query/policy can identify them**. Do not guess availability from an inactive-count shortcut when the actual compiler/availability policy is required. Reuse count definitions/filtered resource destinations; show distinct counts rather than double-counted joined rows.

Define the metric and scope before creating classes. If there is genuinely shared read logic, put a focused service in the existing `App\Services` directory and explicit DTO in `App\DTO`; keep the widget a presenter. Read queries are authorized for the existing admin actor and bounded by aggregate/page scope. No Orders/revenue subsystem, visitor analytics, time-series table, new authorization hierarchy, or invented “health percentage” is included.

For a chosen implementation, tests cover the exact metric with zero data, disabled/hidden distinctions, multiple references to one record, owner scope, and a bounded query shape; cache tests distinguish two authorized scopes and a changed revision. Benchmark real lazy requests instead of assuming container memoization spans them. Native widget reference: [statistics widgets](https://filamentphp.com/docs/5.x/widgets/stats-overview).

FilaWidgets is optional if the user later wants several specialized chart types. Approve the package addition first, confirm a current compatible release, then test its DTO/filter/cache integration. Its inspected 0.1.2 resolver/cache contracts are in the research, not a claim that 0.1.2 should be installed as the latest release. Add vendor Tailwind view scanning to the existing theme if needed; do not generate a replacement theme.

## 9. A6 — Optional shortcuts and brand details

**Right-click.** Defer until visible actions work. If approved, evaluate `leek/filament-right-click` on one simple resource first; inspect the chosen release's compatibility and source again. Wrap existing record/bulk action factories so permissions, confirmations, status choices, blocker links, and save paths remain identical. Retain visible row/action-group access for touch. Test pointer menu, Shift+F10/context-menu key, focus restoration, scroll/dismiss/navigation, selection sync, filtered “select all,” stale/deleted records, server denial, and atomic failure. Use the plugin rather than a new hand-built full context-menu framework unless it demonstrably fails the requirement.

**Brand/profile.** Keep the native logo sizing, equal shell header heights, compact navigation and existing account controls. A tiny sidebar identity/footer can be considered only if it improves account switching/context without duplicating controls. Use local/native avatar handling. No external email-derived avatar URL, fake plan badge, removal of global search, or broad white-only theme override. Hooks are reserved for missing native placement; prefer current slots/APIs for adjacent heading context.

## 10. Completion evidence for a future implementation

An implementation report should name reused versus changed components, affected screens, tests actually run, and current visual/performance evidence. Read source tests are not passing test results. A measured render improvement is not automatically a SQL or interaction improvement.

For PHP behavior changes, run the narrow affected Pest files through `php artisan test --compact <file>` and `vendor/bin/pint --dirty --format agent` under agreed edit ownership; review formatting scope so concurrent work is not inadvertently changed. For frontend changes, run the existing JS tests and `npm run build`, then verify the served browser UI. After affected feature tests pass, ask the user to run the full `php artisan test --compact` suite as required by project guidance. Publication remains governed by the implementation session's explicit authorization and reviewed file allowlist.

Research completion here means all eight source patterns, backend boundaries, version differences, and adoption choices are recorded. It does not mean these optional changes have been implemented, their tests passed, or the active Configurator session has finished.
