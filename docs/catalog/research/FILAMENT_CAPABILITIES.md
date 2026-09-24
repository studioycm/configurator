# Filament capabilities: Povilas Korop's Laracon talk and original sources

> Historical research evidence. Execute the [final plan](../IMPLEMENTATION_PLAN.md) and [guidelines](../IMPLEMENTATION_GUIDELINES.md); their contracts supersede earlier alternatives here. Dates, versions, access and test observations are snapshots, not fresh verification.

Research date: 2026-09-23. Read-only source research for the accepted catalog/configurator plan. No application changes, dependencies, database writes, or tests were performed. Recommendations below are proposals for implementation review, not new product requirements.

## Recording identity and coverage

The supplied [video](https://www.youtube.com/watch?v=vii6P0vJhTw) is **Laracon US 2026 | Day 2 Livestream | Live in Boston**, on Laravel's official channel, streamed July 29, 2026. Its expanded description identifies Povilas's chapter at [2:05:04](https://www.youtube.com/watch?v=vii6P0vJhTw&t=7504s); the next speaker starts at 2:28:46. The [official conference schedule](https://laracon.us/) also identifies his July 29 talk.

The official [standalone recording](https://www.youtube.com/watch?v=olvqO33PBI0), **Filament: Advanced Practical Examples | Povilas Korop at Laracon US 2026**, was published August 14, 2026 and runs 22:53. Its authored description covers custom visualizations, layout, hooks, forms and public components. It links his X profile, without a tutorial/repository list.

**Coverage:** both expanded descriptions were read. Supported transcript export reported no transcript for both videos; their players showed no available captions. Opening YouTube's transcript interface did not produce transcript text. The standalone recording was inspected through selected frames, including every numbered demonstration and opening/closing references. **This is not a complete viewing, listening, or spoken-transcript analysis.** Timestamps below identify observed frames, not exact segment starts. No quotations are attributed to speech.

Evidence labels used here:

- **V:** directly observed video metadata or presentation frame.
- **P:** publisher-authored tutorial/project page read; premium pages accessed through the existing authorized session where indicated.
- **C:** actual repository implementation read, without running it.
- **D:** version-scoped official documentation and/or installed vendor source checked.
- **Proposal:** our inference for this application; not a promise made by the source.

## All thirteen demonstrations mapped to original projects

Every row has V evidence for the numbered topic and P evidence for the matching publisher page. Project matches follow the visible subject/UI/code and the author's original catalog; they are not a claim that an exact URL was spoken. All GitHub links below were exposed by the authenticated publisher pages. Most point to folders in the membership repository, so access depends on the reader's entitlement. C coverage is limited to the files discussed later.

| Demo / observed frame | Original explanation | Author-linked source repository |
|---|---|---|
| 1 · [09:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=549s) · attendance matrix | [Attendance form](https://filamentexamples.com/project/filament-v4-managing-student-or-employee-attendance) | [student-or-user-attendance](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/full-projects/student-or-user-attendance) |
| 2 · [10:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=609s) · fleet availability | [Calendar with colored cells](https://filamentexamples.com/project/gantt-like-grid-calendar-with-colors) | [fleet availability widget](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/full-projects/gantt-like-fleet-availability-dashboard-widget-draft) |
| 3 · [11:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=669s) · contribution heatmap | [User profile with activity heatmap](https://filamentexamples.com/project/github-style-user-profile-with-activity-heatmap) | [profile and heatmap](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/full-projects/github-style-user-profile-with-activity-heatmap) |
| 4 · [12:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=729s) · records as cards | [Table columns arranged in a grid](https://filamentexamples.com/project/filament-v4-table-with-grid-columns) | [table-as-grid-with-cards](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/tables/table-as-grid-with-cards) |
| 5 · [14:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=849s) · sidebar identity card | [Branded panel](https://filamentexamples.com/project/branded-filament-panel-with-sidebar-profile-card) | [branded panel source](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/full-projects/branded-filament-panel-with-sidebar-profile-card) |
| 6 · [15:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=909s) · Material styling | [Custom theme](https://filamentexamples.com/project/filament-v4-custom-theme-material-design) | [material-theme](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/full-projects/material-theme) |
| 7 · [15:31](https://www.youtube.com/watch?v=olvqO33PBI0&t=931s) · sidebar search | [Moving global search](https://filamentexamples.com/project/filament-v4-filament-search-above-sidebar-navigation) | [search-to-sidebar](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/full-projects/search-to-sidebar) |
| 8 · [16:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=969s) · invoice line items | [Invoice editor](https://filamentexamples.com/project/filament-v4-invoice-editor-items) | [invoice-items-editor](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/forms/invoice-items-editor) |
| 9 · [17:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=1029s) · grouped employee inputs | [Tabs](https://filamentexamples.com/project/filament-v4-large-employee-form-with-tabs) and [sections](https://filamentexamples.com/project/filament-v4-large-employee-form-with-sections) | [tabs source](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/forms/large-employee-form-with-tabs), [sections source](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/forms/large-employee-form-with-sections) |
| 10 · [17:43](https://www.youtube.com/watch?v=olvqO33PBI0&t=1063s) · reviewable wizard | [Summary step and back buttons](https://filamentexamples.com/project/filament-v4-wizard-with-summary-overview-and-back-buttons) | [wizard-with-summary-step](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/forms/wizard-with-summary-step) |
| 11 · [19:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=1149s) · sports standings | [Tables organized by group](https://filamentexamples.com/project/filament-v4-sports-standings-groupped-table) | [sports-standings-tables](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/tables/sports-standings-tables) |
| 12 · [19:40](https://www.youtube.com/watch?v=olvqO33PBI0&t=1180s) · public product table | [Table outside a panel](https://filamentexamples.com/project/filament-v4-public-table-outside-any-panel) | [public-products-table](https://github.com/LaravelDaily/FilamentExamples-Projects/tree/main/v4/tables/public-products-table) |
| 13 · [20:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=1209s) · appointment selection | [Reuse an admin form publicly](https://laraveldaily.com/post/filament-appointment-booking-re-use-admin-panel-form-on-public-page) | [Filament-Appointment-Reservations-Demo](https://github.com/LaravelDaily/Filament-Appointment-Reservations-Demo) |

## What the implementations establish

### A custom editor can retain the Filament shell

**C/P:** [Attendance.php](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/main/v4/full-projects/student-or-user-attendance/app/Filament/Pages/Attendance.php) is a custom `Page` with a Blade view and a two-dimensional public selection array. The authenticated tutorial shows Filament checkbox/button components inside the custom table, plus a single save interaction. It is a close structural reference for an option mapping editor, without requiring one resource per matrix cell.

**Proposal:** transfer the grid arrangement and explicit save affordance into the planned staged mapping editor. Retain our typed input validation, membership checks, planned catalog-write gate preserving current panel eligibility, compiler checks and atomic domain action. The sample directly performs database writes in `saveAttendance()` and does not supply those application-specific boundaries. Its `getViewData()` also rebuilds selection data from the database; copying that lifecycle would risk replacing pending edits. It is not evidence that our draft/save/repair contract is already solved.

### Compute structured display data, then render it

**C:** [FleetAvailability.php](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/main/v4/full-projects/gantt-like-fleet-availability-dashboard-widget-draft/app/Filament/Widgets/FleetAvailability.php) eager-loads bounded booking/maintenance relationships, builds a fourteen-day array and resolves each cell's status before rendering its custom view. A separate legend maps statuses to presentation. This is a useful small example of separating a computed result from markup.

**Proposal:** the engine's settled DTO can feed the public configurator and admin Preview & Test, including allowed/disabled/hidden states and diagnostic contributors. Do not transfer the fleet's precedence rules into our engine: it has a simple sequential status resolver, not our acyclic dependency graph, intersecting restrictions, missing-selection semantics or restoration policy.

### Public Filament components are supported, with version-specific setup

**C/P:** [Products.php](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/main/v4/tables/public-products-table/app/Livewire/Products.php) puts a query-backed table in a standalone Livewire component, including search and price filtering. It has empty record/toolbar action lists. The [example's composer.json](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/main/v4/tables/public-products-table/composer.json) requires **Laravel ^12.10 and Filament ^4.0**, despite the publisher page's broader Filament 4/5 label. That label does not certify compatibility with this project's installed Filament 5.8.4 / Livewire 4.4.6.

**D:** current [Filament 5 table integration documentation](https://github.com/filamentphp/filament/blob/5.x/docs/12-components/02-table.md) uses `HasTable`, `HasSchemas`, `InteractsWithTable` and `InteractsWithSchemas`, plus action contracts/traits when needed. Its example also includes `RestrictsFileUploadsToSchemaComponents`. This trait exists in our installed [vendor source](../../../vendor/filament/schemas/src/Concerns/RestrictsFileUploadsToSchemaComponents.php:5); the [security documentation](https://github.com/filamentphp/filament/blob/5.x/docs/09-advanced/06-security.md) explains the inherited upload endpoints that make it relevant to public schema-bearing components. No public component was implemented or exercised in this research.

**Proposal:** public tables are an available option, not a reason to change the accepted public Livewire/card design. Our newest-filter-wins reducer, URL recency history, value counts and immediate ten-card pagination remain explicit application behavior. Any public Filament schema integration should use the current setup and deny unintended uploads; it should not expose admin actions or import the demo's media dependency merely to obtain an image column.

### Card layouts do not require replacing the table engine

**P/D:** the [grid example](https://filamentexamples.com/project/filament-v4-table-with-grid-columns) combines image/text columns with `Grid`, `Split` and `Stack`. Current [layout documentation](https://github.com/filamentphp/filament/blob/5.x/packages/tables/docs/05-layout.md) supports `contentGrid()` for responsive records-as-cards and view components for custom HTML. The installed [HasContent concern](../../../vendor/filament/tables/src/Table/Concerns/HasContent.php:37) contains `contentGrid()`.

**Proposal:** consider this for an admin product/option picker if standard table selection and pagination help. Keep public card layout in its owning annex unless a concrete comparison shows a better fit. Bind admin search/sort to `product_code`; the example's database-backed `name` column does not solve our accessor-alias SQL limitation.

### Use the smallest supported extension point

**P/D:** the [branded panel](https://filamentexamples.com/project/branded-filament-panel-with-sidebar-profile-card) uses a theme, logo view and sidebar render hook. [Official render-hook documentation](https://github.com/filamentphp/filament/blob/5.x/docs/09-advanced/01-render-hooks.md) supports scoped injections, and `SIDEBAR_NAV_START` exists in the installed [hook constants](../../../vendor/filament/filament/src/View/PanelsRenderHook.php:135). The [Material theme tutorial](https://filamentexamples.com/project/filament-v4-custom-theme-material-design) explicitly describes its styling as unfinished.

The [sidebar-search tutorial](https://filamentexamples.com/project/filament-v4-filament-search-above-sidebar-navigation) itself notes that Filament gained built-in support after the original hook approach. Current [global-search docs](https://github.com/filamentphp/filament/blob/5.x/docs/03-resources/10-global-search.md) specify `globalSearch(position: GlobalSearchPosition::Sidebar)`, confirmed by installed [HasGlobalSearch.php](../../../vendor/filament/filament/src/Panel/Concerns/HasGlobalSearch.php:31).

**Proposal:** prefer built-in configuration first, then a scoped hook/custom view for a demonstrated need. Neither a full Material theme, user plan badges nor relocated search is a requirement of the accepted catalog milestone. Avoid copying old chrome customizations solely because they appear in the talk.

### Forms show composition; they do not define our persistence policy

**P:** the [invoice editor](https://filamentexamples.com/project/filament-v4-invoice-editor-items) demonstrates repeating child rows, live totals and duplicate-choice prevention. [Employee sections](https://filamentexamples.com/project/filament-v4-large-employee-form-with-sections), [tabs](https://filamentexamples.com/project/filament-v4-large-employee-form-with-tabs) and the [wizard](https://filamentexamples.com/project/filament-v4-wizard-with-summary-overview-and-back-buttons) offer different ways to organize long input. The sports example composes child Livewire tables within a custom page; it is not a new definition of our catalog Group hierarchy.

**Proposal:** choose these layouts by editor task and amount of data. Retain the already specified separate settings, associations and mapping mutations. A Repeater relationship saver is not a substitute for validating the whole proposed definition before writes. Keep the accepted `Custom` placeholder; neither the wizard nor these projects authorizes adding visitor stages or saved configurations.

### Reuse domain computation across admin and public callers

**P:** the authenticated [appointment tutorial](https://laraveldaily.com/post/filament-appointment-booking-re-use-admin-panel-form-on-public-page), dated **August 17, 2023**, extracts `ReservationService::getAvailableReservations()` and calls it from both the resource and public Livewire form. The service and caller snippets were read. It is Filament 3-era material, including older `Form`/`Get` namespaces. It also permits all users into its demo panel and creates a user during public reservation submission.

**Proposal:** take the shared-service boundary only. Our existing engine action/DTO plan is the analogous solution. Do not copy that tutorial's admission policy, account creation, dependency setup or persistence behavior. Preserve this app's current panel-eligibility intent and explicit authorization on catalog mutations. No appointment flow is proposed.

## Other references actually visible in the recording

At [02:32](https://www.youtube.com/watch?v=olvqO33PBI0&t=152s), the author slide names [Filament Daily](https://www.youtube.com/@FilamentDaily), [Laravel Daily](https://www.youtube.com/@LaravelDaily), [AI Coding Daily](https://www.youtube.com/@AICodingDaily) and [NativePHP Daily](https://www.youtube.com/@NativePHPDaily). This establishes attribution, not a requirement to adopt an AI workflow. No specific course URL was visibly verified. Separate AICodingDaily research belongs to the coordinator's source review.

At [04:47](https://www.youtube.com/watch?v=olvqO33PBI0&t=287s), the slide uses SiteLedger's project overview as an application example. The recent-project research workstream owns the [Construction Management System](https://filamentexamples.com/project/construction-management-system) source review; this document does not duplicate its repository conclusions.

At [21:09](https://www.youtube.com/watch?v=olvqO33PBI0&t=1269s), two follow-up video cards are visible:

- [PHP Enums in Filament: Practical Example](https://www.youtube.com/watch?v=8DJpB0LMv8I), July 22, 2026. Its publisher description concerns Eloquent enum casts and shared behavior. Relevant to typed operators/effects/context, without proving our enum set or semantics.
- [NEW in Filament 5.7: Form/Table Performance Improved](https://www.youtube.com/watch?v=L8Sqpm-Kvbs), July 21, 2026. Its description concerns rendering benchmarks. Our installed release is newer; the video provides no measured performance result for our proposed matrix or Product cards.

Those two dates/descriptions were verified through publisher video search results; their spoken content was not reviewed.

## Effect on the accepted implementation plan

These sources strengthen the chosen [implementation plan](../IMPLEMENTATION_PLAN.md), rather than changing the data or rule-engine decisions:

1. **M1/M2:** retain public Livewire discovery and immediate paginated cards. Public Filament components and card tables remain optional implementation tools, with the version/security setup above if used.
2. **M3/M4:** keep the authoritative domain compiler/evaluator independent of Filament. Custom pages/widgets can render its structured result and a mapping matrix; one validated domain action owns each definition mutation.
3. **Administration:** use ordinary resources/forms for ordinary CRUD and focused custom views for the mapping/diagnostic task. Existing global 7xl modal sizing, admission gate, explicit repair workflow and unvisited-tab preservation remain in force.
4. **Scope:** no new theme, heatmap, invoice/booking model, roles, stages, AI runtime, media package or account-creation behavior follows from these demos.

## Acquisition limits and reproducibility

- Exact livestream and standalone metadata, description expansion and selected slides were inspected in the existing signed-in Chrome session. No media files were downloaded or redistributed.
- Authenticated FilamentExamples pages exposed all thirteen source destinations, and the Laravel Daily appointment tutorial was accessible. The GitHub connector returned 404 for the membership repository, while the same repository and selected files were readable in the authorized browser. This is an access-path limitation, not evidence that the source is missing.
- Actual GitHub code read: Attendance.php, FleetAvailability.php, public Products.php and its composer.json. Other repository links were resolved from publisher pages; they are not claimed as full repository audits. Source URLs use mutable `main`; re-check exact files/package locks during implementation.
- The talk is recent; several underlying examples are older or upgraded examples. Neither the conference date nor a Filament 4/5 badge establishes a project's latest commit or installed lock version.
- No new UI was run, no demo was installed, and no browser/test result for our proposed implementation is claimed. Spoken-content coverage remains the explicit gap until captions/transcript or another supported speech source is available.
