# FilamentExamples: workspace and interface research

Research date: **2026-10-08**. Scope: the eight examples requested by the user, their application source, relevant plugin source, and our installed framework/source. This document records findings; the [adoption plan](FILAMENT_EXAMPLES_ADOPTION_PLAN.md) proposes subsequent work. **No application changes, package installation, example execution, database changes, or publication are part of this research.**

Read alongside [Laravel architecture findings](FILAMENT_EXAMPLES_LARAVEL_PATTERNS.md), the [admin implementation plan](../ADMIN_FILAMENT_IMPLEMENTATION_PLAN.md), the [Configurator workspace plan](../CONFIGURATOR_WORKSPACE_IMPLEMENTATION_PLAN.md), and the [card appearance handoff](../CARD_APPEARANCE_ADMIN_UX_HANDOFF.md). User decisions take precedence over example behavior.

## 1. Evidence, versions, and limits

Our target was verified with Laravel Boost and Composer: **PHP 8.4, Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7**. `npm ls` confirms resolved Tailwind 4.3.3 and Vite 8.3.3. These are snapshots, not minimum dependency requirements.

Seven examples were inspected in `/Users/studioycm/PhpstormProjects/FilamentExamples`, whose origin is `studioycm/FilamentExamples` and upstream is `LaravelDaily/FilamentExamples-Projects`. Their relevant tracked files were unchanged relative to local commit **`74981c2bbd4835f8cd6b9b3aad5697e89437ddae`**, dated 2026-07-14. The clone overall has unrelated changes and is behind remote main; it was not fetched, reset, synchronized, or modified. The seven profiles below describe that pinned source, not an audit of their newest remote revisions.

The eighth example is absent from that local snapshot. Its actual code and lock file were read through authenticated **Chrome**, at upstream commit **`d67dfddf9ce906d554f34ef4435d15de3a0a8ad8`**, dated 2026-09-10. This includes the deferred-product addition, commit `f31b512fbd8b1fb455b8f0faa4ea81d388eec576`. The private GitHub connector returned 404; browser access succeeded. No credentials, browser profile data, or subscription source archives were copied.

All eight public project pages were read. FilamentExamples MCP supplied a matching sidebar-resizer implementation; that is supporting source evidence, not independent runtime verification. Selected source tests were read, **not run**. No demo was benchmarked or exercised in a browser. Performance statements below distinguish code-level query behavior from measurements we still need.

The repository's `v4/` directory name is misleading for these snapshots: their lock files use **Filament 5 and Livewire 4**.

| ID | Repository directory | Locked Laravel / Filament / Livewire | Additional locked package |
| --- | --- | --- | --- |
| E1 | `v4/forms/livewire-component-in-editform-sidebar` | 13.3.0 / 5.4.3 / 4.2.3 | — |
| E2 | `v4/full-projects/filawidgets-dashboard` | 13.2.0 / 5.4.1 / 4.2.1 | `laraveldaily/filawidgets` 0.1.2 |
| E3 | `v4/tables/table-customized-design-viewcolumn` | 13.1.1 / 5.4.1 / 4.2.1 | — |
| E4 | `v4/full-projects/branded-filament-panel-with-sidebar-profile-card` | 13.6.0 / 5.6.0 / 4.2.4 | — |
| E5 | `v4/full-projects/chart-filter-buttons` | 13.9.0 / 5.6.3 / 4.3.0 | — |
| E6 | `v4/full-projects/right-click-menu` | 13.18.0 / 5.6.8 / 4.3.3 | `leek/filament-right-click` 1.3.3 |
| E7 | `v4/full-projects/drag-to-resize-sidebar` | 13.15.0 / 5.6.7 / 4.3.1 | — |
| E8 | `v4/forms/products-tabs-complex-form` | 13.31.0 / 5.8.1 / 4.4.4 | — |

## 2. E1 — Livewire component in the edit-form sidebar

[Project](https://filamentexamples.com/project/livewire-component-in-edit-form-sidebar) · [pinned project source](https://github.com/studioycm/FilamentExamples/tree/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/forms/livewire-component-in-editform-sidebar)

**Mechanism.** `EditTicket::content()` composes the native edit form and relation managers with `Filament\Schemas\Components\Grid`, `Group`, and `Livewire`. Desktop uses a two-thirds form and one-third sidebar; smaller screens stack. `TicketSidebar` is a separate Livewire component receiving the record. The edit page dispatches a refresh event after saving, and the sidebar refreshes its saved model. A computed relationship query eager-loads the transition actor. Enum methods supply allowed transitions, labels, and colors. The sidebar is sticky only at the desktop breakpoint.

**Useful here.** A small contextual area can show shared-record usage, references blocking removal, current saved status, and links into our existing item drawers. It can refresh after a successful save without rebuilding the editable form. This fits our one-workspace direction for Master Values, Options, Attributes, and the dedicated Configurator editor. It does not require polling or WebSockets merely to be “live.”

**Adaptation required.** This is a record-context sidebar, distinct from the panel's navigation sidebar. Do not add a third permanent column beside our existing table/editor pair. Put compact context inside the right editor, or in its secondary tab. The source transition method creates history and updates the Ticket in separate writes, without an explicit transaction or authorization call in that method. It also uses `TicketStatus::from()` on client input, which can throw on an unknown value. Retain our domain actions and accepted status rules; do not copy direct updates into a new sidebar. Bound history/usage previews rather than copying its unpaginated `get()`.

Nested Livewire components are independently hydrated. Passing a record during schema construction does not automatically keep its props reactive. Use stable record identity and targeted invalidation; a refresh must not refill or discard another component's unsaved draft. Distinguish “saved configuration” from any explicit draft preview.

**Inspected files:** `EditTicket.php`, `TicketSidebar.php`, `TicketStatus.php`, `TicketPriority.php`, `Ticket.php`, `StatusTransition.php`, the sidebar Blade view and transition migration. Source tests cover transition validity, terminal states, timestamps, actor recording, and ordinary resource editing. They do not establish our atomicity, draft-preservation, or multi-workspace event behavior.

Critical source: [sidebar write/read methods](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/forms/livewire-component-in-editform-sidebar/app/Livewire/TicketSidebar.php), [page composition](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/forms/livewire-component-in-editform-sidebar/app/Filament/Resources/Tickets/Pages/EditTicket.php).

## 3. E2 — Custom dashboard widgets / FilaWidgets

[Project](https://filamentexamples.com/project/custom-dashboard-widgets-filawidgets-plugin) · [pinned project source](https://github.com/studioycm/FilamentExamples/tree/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/filawidgets-dashboard)

**Mechanism.** The Dashboard has native filter-schema ToggleButtons for a preset and date range. Typed enums resolve labels and date windows. Presets choose concrete widget classes rather than one giant switch inside a universal widget. Widgets prepare typed plugin data objects for sparkline rows, gauge, heatmap, breakdown, and progress displays. Models expose an enum cast and a completed-order local scope.

**Useful here.** Start with compact native Filament statistics or a short actionable health list: disabled records, assignments needing attention, or references blocking an operation. Counts should lead to an existing filtered list/drawer. Specialized heatmaps and trends are optional only when the app actually records the necessary time-series data. The demo's orders, revenue, fulfillment targets, and subscription-like concepts are not requirements for this catalog.

**Performance findings.** `RevenuePulseWidget::completionRateRow()` iterates dates and counts all orders, then completed orders when a day has orders: up to two queries per day for that series. This is a source-level finding, not a measured dashboard query total. `DailyRevenueWidget` instead groups by date in one aggregate query. Adapt the latter approach: bounded grouped aggregates, explicit zero-filled days, and correct denominators. A larger date range must not create a proportional number of SQL round trips.

Plugin **0.1.2** was separately inspected at [commit `59bc019`](https://github.com/LaravelDaily/FilaWidgets/tree/59bc01958ad5a6450c3655d758fb00cd1058d84c). Its service provider registers views and a command. Widget classes accept either a local `getData()` implementation or a typed resolver contract instantiated through Laravel's container; their presenter helpers format DTO rows. Optional caching incorporates widget/resolver/filter/options inputs, but does not implicitly add the current user, panel, owner, or catalog revision. An authorized catalog result needs those dimensions where relevant. Request-scoped memoization does not carry over to separate lazy-widget requests.

**Recommendation.** No plugin is necessary for the first catalog-health widget. FilaWidgets becomes worth evaluating if several of its specific visualization types are wanted. Its declared major-version compatibility is useful but does not prove our CSS, hydration, scope isolation, or query budgets. A dependency addition remains a separate approval and integration task.

Critical source: [query loop](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/filawidgets-dashboard/app/Filament/Widgets/RevenuePulseWidget.php), [cache contract](https://github.com/LaravelDaily/FilaWidgets/blob/59bc01958ad5a6450c3655d758fb00cd1058d84c/src/Support/WidgetDataCache.php), [typed resolver](https://github.com/LaravelDaily/FilaWidgets/blob/59bc01958ad5a6450c3655d758fb00cd1058d84c/src/Contracts/ResolvesSparklineTableWidgetData.php). The demo's widget tests cover filter hydration, data presentation, empty periods, and cache-disabled freshness; they were not executed here.

## 4. E3 — Custom table design with ViewColumn

[Project](https://filamentexamples.com/project/custom-table-design-viewcolumn) · [pinned project source](https://github.com/studioycm/FilamentExamples/tree/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/tables/table-customized-design-viewcolumn)

**Mechanism.** `AccountsTable` uses `Filament\Tables\Columns\ViewColumn` for custom cells: image/name, industry pills, dates, and revenue treatment. It explicitly eager-loads industries and configures sorting. Blade contains presentation, while the model provides casts and relationship definitions. A small helper escapes its header label before returning HTML with an icon.

**Useful here.** Apply to a few cells that benefit from structured content: code plus name, a concise rule summary, compact tag overflow, or a status/context indicator. Prefer native `TextColumn` formatting first. Keep the rest of the table's selection, global/scoped search, combined filters, sorting, column manager, and visible actions. The demo has empty filter/action arrays and does not implement our complete table workflow.

The existing `App\Filament\Resources\ItemCountColumn` and `App\Services\ItemLists` already supply reusable count previews/drawers for Eloquent and array lists. Retain that explicit helper instead of replacing it with a global macro. Use `ViewColumn` only if the count cell's markup actually needs more than a native column can express. Columns are schema objects, not independent Livewire components; do not place cell-specific Livewire state or database writes in a column class.

**Cautions.** Its horizontal padding exceeds our 8–12 px target; retain our appearance variables. Arbitrary CSS-variable color values need an allowlisted palette or validated color format. Custom views do not automatically make nested rendered text searchable: keep explicit query-backed search/sort definitions. Blade loops must use eager-loaded bounded data. This example provides no resizing mechanism.

Critical source: [table/query setup](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/tables/table-customized-design-viewcolumn/app/Filament/Resources/Accounts/Tables/AccountsTable.php), [model/presentation helpers](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/tables/table-customized-design-viewcolumn/app/Models/Account.php). Its stock example tests do not prove custom-cell search, accessibility, or responsive behavior.

## 5. E4 — Branded panel with sidebar profile card

[Project](https://filamentexamples.com/project/branded-filament-panel-with-sidebar-profile-card) · [pinned project source](https://github.com/studioycm/FilamentExamples/tree/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/branded-filament-panel-with-sidebar-profile-card)

**Mechanism.** The panel provider configures logo view/height, theme, auth-page classes, and a `SIDEBAR_NAV_START` render hook for a profile card. The Blade view uses the Filament guard's current user. Styling changes most of the panel, not just the profile card.

**Useful here.** Native panel logo, sidebar sizing, and theme APIs are the appropriate starting point. An optional small account identity/menu could live near the sidebar footer; our native user controls and `AccountWidget` already exist. A large card above navigation would compete with the compact search/navigation space the user requested.

**Do not copy.** The example disables global search, applies broad white/black CSS across the panel, removes borders, and displays a mocked FREE badge. Its avatar URL sends an email/name-derived identifier to an external image service. Use our current Filament avatar handling, initials, or local image if an avatar is needed. Preserve dark mode, inline search, accessible contrast, existing logo-height settings, and collapsed-sidebar behavior. No new registration fields or account-plan model is required by this research.

Critical source: [provider configuration](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/branded-filament-panel-with-sidebar-profile-card/app/Providers/Filament/AdminPanelProvider.php), [profile view](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/branded-filament-panel-with-sidebar-profile-card/resources/views/filament/admin/sidebar-profile.blade.php).

## 6. E5 — Segmented button filter for chart widgets

[Project](https://filamentexamples.com/project/segmented-button-filter-for-chart-widgets) · [pinned project source](https://github.com/studioycm/FilamentExamples/tree/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/chart-filter-buttons)

**Mechanism.** A ChartWidget subclass supplies enum-backed filter choices and a custom widget view. The view puts small buttons in its header, changes the widget's filter through `$set`, and retains Filament's chart initialization and `wire:ignore` canvas boundary. The model uses a typed traffic-source scope and SQL aggregates. Polling is disabled, while the example explicitly disables lazy widget mounting.

**Useful here.** The compact visual pattern supports our quick table filters, particularly small status/choice sets. Keep a single canonical table-filter state shared with the robust Filters interface. The widget's `$filter` property is not a table-filter API. Search remains inline and more prominent; Master Value tags remain on their second header row.

**Cautions.** Add `aria-pressed` for multi-toggle buttons or radio semantics for exclusive choices. Unknown client filter values should be rejected or normalized deliberately: the example's enum `tryFrom()` fallback treats unknown values as “all,” which broadens the query. Its aggregate PHPDoc describes a date as a string while consumers use the model's date cast—keep DTO/type contracts consistent. Do not copy `$isLazy = false` across expensive widgets.

Native `HasFiltersSchema` provides chart filter forms, but its default trigger opens a dropdown; it does not by itself produce this inline header. A custom chart view carries upstream-maintenance cost. Our existing table-toolbar views are the smaller integration surface for table buttons.

Critical source: [widget](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/chart-filter-buttons/app/Filament/Widgets/EngagementRateChart.php), [header/chart view](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/chart-filter-buttons/resources/views/filament/widgets/engagement-rate-chart.blade.php), [aggregate model](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/chart-filter-buttons/app/Models/PerformanceMetric.php).

## 7. E6 — Table right-click menu

[Project](https://filamentexamples.com/project/filament-right-click-menu-in-table) · [pinned project source](https://github.com/studioycm/FilamentExamples/tree/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/right-click-menu)

**Mechanism.** The panel registers `Leek\FilamentRightClick\FilamentRightClickPlugin`. Table macros configure context-menu record actions and bulk actions. `ContextMenuItem` wraps normal Filament actions; selection-aware JavaScript mounts those server actions rather than issuing raw record updates from JavaScript.

Plugin **1.3.3** source was inspected at [commit `21ff14c`](https://github.com/leek/filament-right-click/tree/21ff14c0a5c4ac426c6bbd292e8bdc449e7b7eaf). It supports the context-menu keyboard key and Shift+F10 as well as pointer interaction. Its integration uses global Table macros, closure access to table action caches, Filament DOM selectors, and Alpine selection internals. Those are important upgrade-test surfaces even though Composer supports Filament 5.

**Useful here.** An optional shortcut for frequent “open editor,” “view usage,” and existing batch actions. It must supplement the visible row/action-group triggers, including on touch devices. Frequent actions should remain visible in the header/row, as requested. A hidden context menu cannot fix missing Products actions or an empty Groups action group.

**Cautions.** Do not copy the demo's direct status-write closures, partial bulk cancellation, or max-ID-based duplicate order numbering. A disabled/hidden button is not sufficient domain authorization or concurrency protection. Existing actions must revalidate ownership/status/dependencies through our domain layer when mounted and executed. Keeping these actions shared avoids divergent confirmation dialogs or bypasses of blocker links.

**Recommendation.** Lower priority; do not hand-build a full keyboard/selection-aware menu as a supposedly tiny replacement. Evaluate the plugin only after visible actions, touch behavior, and narrow headers are correct, with separate dependency approval. The example's tests exercise context configuration and mounted record/bulk actions; they do not prove the actual pointer menu, focus restoration, or our aggregate invariants.

Critical source: [demo table](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/right-click-menu/app/Filament/Resources/Orders/Tables/OrdersTable.php), [plugin macros](https://github.com/leek/filament-right-click/blob/21ff14c0a5c4ac426c6bbd292e8bdc449e7b7eaf/src/Macros/RegisterMacros.php), [plugin browser behavior](https://github.com/leek/filament-right-click/blob/21ff14c0a5c4ac426c6bbd292e8bdc449e7b7eaf/resources/js/filament-right-click.js).

## 8. E7 — Drag to resize the navigation sidebar

[Project](https://filamentexamples.com/project/drag-to-resize-sidebar-for-filament) · [pinned project source](https://github.com/studioycm/FilamentExamples/tree/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/drag-to-resize-sidebar)

**Mechanism.** Native `sidebarCollapsibleOnDesktop()`, `sidebarWidth()`, and `collapsedSidebarWidth()` remain in the panel provider. A sidebar render hook hosts an Alpine resizer. Pointer movement adjusts the root `--sidebar-width` variable; native sidebar open/close state handles collapse. Width persists in localStorage. Near the minimum width it collapses, with a small expand/collapse hysteresis; labels fade at narrow widths. Double-click switches width/collapse. Assets are registered through `FilamentAsset`.

**Useful here.** A plugin-free model for optional **navigation** width adjustment. Transfer the client-only sizing idea, not its hardcoded measurements or automatic-collapse policy. Our approved admin settings already define sidebar width 192–320 px, default 216 px, and a separate collapsed rail 58–80 px, default 58 px. Keep these controls and native collapse toggle as the baseline.

**Gaps.** The demo uses a shared storage key, hardcodes a 104 px collapsed rail, lacks guarded storage access, and its grip is hidden from assistive technology without an equivalent keyboard resizer. It hides native collapse buttons in CSS. Window-level pointer handlers do not use pointer capture/identity, and cleanup is limited. These need adaptation, not wholesale copying.

This example **does not** resize table columns, a form/editor split, or action slide-overs. Our project already has separate controllers for those: table-column widths, stepped dialogs, and a concurrently developed 33/50/67% workspace split. Use their existing persistence and reset contracts. Keep all dragging local; do not send Livewire requests on pointer movement or serialize form drafts into sizing preferences.

Asset registration alone does not publish local files. Installed Filament source and [asset documentation](https://filamentphp.com/docs/5.x/advanced/assets) require `php artisan filament:assets` for its asset pipeline. Our application already uses Vite; prefer that existing build/load path for new local workspace code.

Critical source: [panel/hook](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/drag-to-resize-sidebar/app/Providers/Filament/AdminPanelProvider.php), [Alpine controller](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/drag-to-resize-sidebar/resources/js/filament/sidebar-resizer.js), [CSS](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/drag-to-resize-sidebar/resources/css/filament/sidebar-resizer.css), [asset registration](https://github.com/studioycm/FilamentExamples/blob/74981c2bbd4835f8cd6b9b3aad5697e89437ddae/v4/full-projects/drag-to-resize-sidebar/app/Providers/AppServiceProvider.php). The source's `SidebarResizerTest` checks rendered markup/assets; it does not test dragging or persisted width after Livewire morphing.

## 9. E8 — Complex product form with tabs and deferred schemas

[Project](https://filamentexamples.com/project/filament-complex-product-form-with-tabs-defer-schema) · [pinned upstream source](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/d67dfddf9ce906d554f34ef4435d15de3a0a8ad8/v4/forms/products-tabs-complex-form)

**Mechanism.** `ProductForm` separates eight tabs into private schema methods. General content renders immediately; expensive child `Filament\Schemas\Schema` objects use `deferLoading()`, controlled by a demo config flag. Tab state persists in the query string. Pricing content/footer have separate explicit schema keys. Repeater-item schemas rely on item state paths for identity. The form still owns relationship repeaters for variants/images and a multi-select self-relationship for related products.

**Useful here.** Groups' Filters, Presets, and Presentation are realistic candidates, after measuring their initial rendering cost. Keep small Master Value/Attribute/Option forms eager and uncomplicated. Configurator tabs already embed separate components with lazy mounting; evaluate this composition before adding a second layer of schema deferral. General identity/status stays eager; avoid a bordered Section around a tab containing only one ordinary main field group.

**Important boundary.** Deferral reduces rendering of an unseen child schema. It does not mean no PHP schema construction, no relationship hydration, no validation, no state in the Livewire snapshot, or automatic suspension of loaded child components. It is not repeater virtualization. Preloaded relationship choices and expensive badge/query closures can still cost work. The demo's related-product select preloads choices from a large product set; use asynchronous search/table selection for large existing-record associations here instead. Keep our simple mapping-set checkboxes and non-model property multi-selects.

The source tests verify create/edit rendering, relative initial markup reduction, and a Save that reveals an unopened deferred SEO schema with a required-field error. **They do not test saving relationship data while its tab remains unopened.** That regression test is required here before deferring Group associations or another relationship editor. The publisher's performance figures are not our measurements.

The product model has enum/date/decimal/boolean casts, typed relationships, ordered HasMany children, and explicit self-pivot foreign keys. Its unguarded mass-assignment setting is a demo convenience, not a pattern to copy. Source inventories contain ordinary models/providers, not a service/DTO layer implementing catalog semantics.

Critical source: [form schema](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/d67dfddf9ce906d554f34ef4435d15de3a0a8ad8/v4/forms/products-tabs-complex-form/app/Filament/Resources/Products/Schemas/ProductForm.php), [product model](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/d67dfddf9ce906d554f34ef4435d15de3a0a8ad8/v4/forms/products-tabs-complex-form/app/Models/Product.php), [deferred-schema tests](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/d67dfddf9ce906d554f34ef4435d15de3a0a8ad8/v4/forms/products-tabs-complex-form/tests/Feature/ProductDeferredSchemaTest.php).

## 10. Version-matched framework conclusions

These were checked with Boost documentation search and relevant installed Filament 5.9.0 / Livewire 4.4.7 source.

| Topic | Supported API / behavior | Application implication |
| --- | --- | --- |
| Deferred children | `Filament\Schemas\Schema::make()->components(...)->deferLoading()`; unique schema identity; validation errors load affected deferred content | Defer selected render-heavy children and prove unvisited saves; do not defer every tab |
| Deferred badges | `Filament\Schemas\Components\Tabs\Tab::deferBadge()` with a closure; explicit parent Tabs key | Use for expensive counts only; aggregated/preloaded counts may already be cheaper |
| Embedded child components | `Filament\Schemas\Components\Livewire::make(...)->data(...)->key(...)`, optional `->lazy()` | Stable owner identity; targeted events; no forced remount on every revision |
| Sidebar sizing | Native `Filament\Panel` width/collapse APIs | Keep native state and fallback controls; optional drag layered on top |
| Custom cells | `Filament\Tables\Columns\ViewColumn::make(...)->view(...)`, or an explicit reusable Column class | Selective presentation; explicit query-backed search/sort; no database reads in Blade |
| Dialogs | `Filament\Actions\Action::slideOver()`, sticky header/footer; `overlayParentActions()` for an appropriate nested confirmation | Preserve draft and context while confirming destructive nested work |
| Livewire refresh | `Livewire\Attributes\On`, dispatch to a component / owner-qualified event | Invalidate read models only; mounted components need authorization on every operation |
| Assets | Vite theme/app bundle, or `FilamentAsset` plus asset publishing | Use one existing application asset pipeline; no duplicate Alpine runtime |

Official references: [schema deferral](https://filamentphp.com/docs/5.x/schemas/overview#deferring-the-loading-of-a-child-schema), [tabs and badges](https://filamentphp.com/docs/5.x/schemas/tabs), [schema custom components](https://filamentphp.com/docs/5.x/schemas/custom-components), [navigation](https://filamentphp.com/docs/5.x/navigation/overview), [custom table columns](https://filamentphp.com/docs/5.x/tables/columns/custom-columns), [action modals](https://filamentphp.com/docs/5.x/actions/modals), [chart widgets](https://filamentphp.com/docs/5.x/widgets/charts), [Livewire events](https://livewire.laravel.com/docs/4.x/events), [Livewire JavaScript](https://livewire.laravel.com/docs/4.x/javascript).

**Research conclusion:** contextual read areas, selective schema deferral, segmented quick filters, and deliberate custom cells fit our workspace. Existing local sizing controllers are the best baseline for table/editor/dialog resizing. Navigation dragging and the two plugins are optional extensions with distinct costs, not prerequisites for the accepted compact admin UI.
