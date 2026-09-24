# Filament Daily video discovery and source verification

> Historical research evidence. Execute the [final plan](../IMPLEMENTATION_PLAN.md) and [guidelines](../IMPLEMENTATION_GUIDELINES.md); their contracts supersede earlier alternatives here. Dates, versions, access and test observations are snapshots, not fresh verification.

Research date: 2026-09-23. Requested window: 2025-01-01 through 2026-09-23. This is a research supplement to the [living plan](../DECISIONS.md) and [Filament research](FILAMENT.md), not implementation approval or a replacement decision list.

## Coverage and evidence levels

The actual Chrome browser showed [Filament Daily — Videos](https://www.youtube.com/@FilamentDaily/videos), handle `@FilamentDaily`, with Videos and Latest selected. The authenticated FilamentExamples footer links this same channel. Its Videos section showed 326 uploads overall at inspection time.

- Reviewed **210 distinct video cards**, in uninterrupted newest-first order, including an older overrun. This is a reviewed-card count, **not** an exact count of uploads in the requested window.
- The newest card was the async-column video, verified as **2026-09-15**. Adjacent cards at the year boundary were the login-validation video at position 138, verified **2025-01-07**, and refactoring at position 139, verified **2024-12-19**.
- Continued beyond YouTube's rounded “1 year ago” labels: the first “2 years ago” card was position 184, Global Search Modal, verified **2024-09-19**. This establishes that the chronological scan passed all of 2025. It does not claim discovery of deleted/private uploads, Shorts, or livestreams outside the Videos section.
- Expanded and read **32 unique full descriptions with exact dates**: **29 in the requested window** and **3 older boundary/exclusion checks**. These are selected relevant leads and boundary checks; the other scanned titles remain discovery only. No claim is made that all 210 descriptions were inspected.
- Followed four newly linked publisher projects in authenticated Chrome: Pool Stock, advanced repeaters, construction, and the marketplace panel. Read actual Pool Stock importer code in authenticated GitHub; read advanced-repeater and marketplace source via FilamentExamples MCP. Earlier matrix/deferred-schema source findings remain in the first research document.
- YouTube-generated summary text was visible alongside some descriptions. It is not treated as the publisher's explanation or proof of an API. Publication dates and publisher-provided destination links are the reliable discovery evidence. Marketing size/speed claims are not local measurements.

## Description-inspected videos in the requested window

Every row below had its expanded description inspected. Short labels summarize topics; they are not transcript quotations. “Generic site only” means the publisher did not give a direct detail-page source. Links have campaign parameters removed.

| Published | Video | Description evidence / linked destination | Research disposition |
|---|---|---|---|
| 2026-09-15 | [Async table column](https://www.youtube.com/watch?v=LG5rHuGAQVU) | [Plugin directory entry](https://filamentphp.com/plugins/giacomo-masseroni-async-column) | Optional plugin lead only; no dependency proposed. |
| 2026-09-10 | [Deferred loading for bigger forms](https://www.youtube.com/watch?v=jMRlIm80Dd4) | Description contains a site promotion, no specific source. | Correlated with already inspected [complex product tabs](https://filamentexamples.com/project/filament-complex-product-form-with-tabs-defer-schema); this is a research mapping, not a description link. |
| 2026-09-02 | [Column filters](https://www.youtube.com/watch?v=6GFN7GFLUw4) | [Plugin directory entry](https://filamentphp.com/plugins/zvizvi-column-filters) | UX discovery, not a replacement for the catalog filter engine or a plugin recommendation. |
| 2026-08-05 | [CSV validation failures](https://www.youtube.com/watch?v=WzYxU-mBnG4) | [Pool Stock project](https://filamentexamples.com/project/pool-stock-inventory-management-system) | Importer source inspected; material cast/validation finding below. |
| 2026-07-21 | [5.7 form/table performance](https://www.youtube.com/watch?v=L8Sqpm-Kvbs) | [Official 5.7.0 release](https://github.com/filamentphp/filament/releases#release-v5.7.0); description promises before/after benchmarks. | Installed 5.8.4 is newer; no need to reproduce obsolete baseline tuning. |
| 2026-07-20 | [Fill repeater after select](https://www.youtube.com/watch?v=RHzsc8LzNes) | [Construction project](https://filamentexamples.com/project/construction-management-system); invoice-line auto-fill. | Publisher detail inspected; exact invoice callback not inspected. Prior verified state-hook cautions still apply. |
| 2026-06-09 | [Two table UX tips](https://www.youtube.com/watch?v=0sipJqwC_YY) | Description emphasizes fewer clicks; generic site/FilaCheck links only. | UX lead, no API claim from the title. |
| 2026-06-04 | [Large marketplace panel](https://www.youtube.com/watch?v=flKIQ-kK-og) | [Marketplace operations project](https://filamentexamples.com/project/e-commerce-marketplace-operations-panel) | Publisher detail and relevant MCP source inspected. |
| 2026-05-27 | [Three performance tips](https://www.youtube.com/watch?v=eoU6fkxhqmk) | Description says commonly overlooked issues; generic site links and a related Laravel Daily video. | Bounded corroboration by marketplace source, not independent benchmark evidence. |
| 2026-04-08 | [Eight form UX improvements](https://www.youtube.com/watch?v=p7FLgRVesVo) | Description explains a staged form improvement; generic site links only. | Candidate for visual reference. No eight-point checklist inferred from title alone. |
| 2026-03-05 | [Quote product picker](https://www.youtube.com/watch?v=5uS0otOU6V8) | [Custom table field and product picker](https://filamentexamples.com/project/quote-form-with-custom-table-field-and-product-picker-modal); description identifies nested tables/forms with Livewire refresh. | Root separately inspected source; stable IDs and pre-write validation required instead of its delete/recreate strategy. |
| 2026-03-01 | [Reusable forms/tables/components](https://www.youtube.com/watch?v=OwKNCaTviFE) | Description identifies reuse across forms/panels; generic site links only. | Supports an investigation into shared schema factories, not a new domain data model. |
| 2025-12-10 | [TableSelect auto-fill](https://www.youtube.com/watch?v=vB5kuSsEzok) | [Official Filament Discord post](https://discord.com/channels/883083792112300104/956270111176679516/1445479702100971673), generic site link. | Discord body not accessed; description-level lead only. |
| 2025-10-29 | [Advanced repeaters](https://www.youtube.com/watch?v=-zHTNCzF9gs) | [Five advanced use cases](https://filamentexamples.com/project/filament-repeater-five-advanced-use-cases) | Actual source inspected; useful mechanics and identity conflict below. |
| 2025-10-21 | [Table filter features](https://www.youtube.com/watch?v=7ubK3NgPklI) | Description offers visual filter examples; generic site link. | Catalog discovery UI only; table query-builder limits remain unsuitable for validating persisted configurator rules. |
| 2025-10-08 | [Official order form](https://www.youtube.com/watch?v=EtnZzyoDYFw) | [Official demo repository](https://github.com/filamentphp/demo) and [demo](https://demo.filamentphp.com/); explicitly v4 in description. | Reference for layout/composition; current demo may have moved beyond the video version. |
| 2025-09-29 | [Dynamic table columns](https://www.youtube.com/watch?v=NVDr730Pnu0) | [Database-driven table fields](https://filamentexamples.com/project/filament-dynamic-table-fields-from-database-data); description identifies PHP array unpacking. | Lead for group-configured result columns; detail source not inspected in this pass. |
| 2025-09-16 | [Two filter UX ideas](https://www.youtube.com/watch?v=wn4OpjpqBmc) | Generic site link only. | Description-level lead, no inferred engine behavior. |
| 2025-09-02 | [External API table data](https://www.youtube.com/watch?v=9lzDU2nxNog) | [Laravel Daily v4 course](https://laraveldaily.com/course/filament-4) and [v4 custom-data docs](https://filamentphp.com/docs/4.x/tables/custom-data#using-an-external-api-as-a-table-data-source). | Versioned reference; imported local catalog remains Eloquent-backed. |
| 2025-06-13 | [Homepage show/hide/reorder](https://www.youtube.com/watch?v=8cbntmnHz7s) | [Publisher GitHub example](https://github.com/LaravelDaily/Filament-Manage-Homepage-Sections). | CMS ordering lead only. Its show/hide vocabulary does not define configurator outcomes. |
| 2025-05-06 | [Tab badge counts](https://www.youtube.com/watch?v=g3eYHMezEWY) | [Complex table example](https://filamentexamples.com/project/complex-table-multiple-features). | Query-count lead; aggregate carefully and preserve the same authorization/filter scope. |
| 2025-04-10 | [Custom input and column](https://www.youtube.com/watch?v=hHZ8HvgU_Vs) | [Accounting plugin example](https://filamentphp.com/plugins/xoshbin-jmeryar-accounting); publisher description names MoneyInput/MoneyColumn. | Reusable component lead; no package installation or custom money requirement. |
| 2025-03-25 | [v3 table performance](https://www.youtube.com/watch?v=LBvgEiWYKHs) | [Publisher explanation](https://filamentexamples.com/tutorial/filament-v3-tables-slow-performance-views). | Historical v3 evidence; do not carry its performance conclusions into installed v5.8.4. |
| 2025-02-25 | [Attendance checkbox table](https://www.youtube.com/watch?v=LW7SLaU-qCY) | [Attendance project](https://filamentexamples.com/project/managing-student-or-employee-attendance); date/user checkbox editing. | Adjacent grid feasibility lead; exact source not inspected in this pass. |
| 2025-01-30 | [Many-to-many checkbox form](https://www.youtube.com/watch?v=-RLk03Xl7Lk) | [Original example](https://filamentexamples.com/project/many-to-many-checkbox-list-in-fieldset). | Earlier research inspected its [v4 port](https://filamentexamples.com/project/filament-v4-many-to-many-checkbox-list-in-fieldset). Useful matrix UI mechanics, not complete rule semantics. |
| 2025-01-16 | [Multiple-column default sort](https://www.youtube.com/watch?v=fAo2ubF7IN8) | Generic site link; publisher calls it undocumented. | Description-level lead; v5 documentation/source must decide actual API. |
| 2025-01-14 | [Report download form](https://www.youtube.com/watch?v=K1nN_T08SDo) | [Custom report page](https://filamentexamples.com/project/custom-page-to-download-excel-report). | Boundary-area discovery; no requested export workflow added. |
| 2025-01-09 | [Record-page widgets](https://www.youtube.com/watch?v=MBYtLZBWKbo) | [Record-specific chart tutorial](https://filamentexamples.com/tutorial/chart-on-view-page-based-on-current-record). | Boundary check; usage/reference counts need not become charts. |
| 2025-01-07 | [Login validation](https://www.youtube.com/watch?v=QPHpP3sc9B8) | [Login validation tutorial](https://filamentexamples.com/tutorial/login-extra-validation). | Year-boundary check only; no new login rules/roles requested. |

Three inspected descriptions were outside the window: [refactoring, 2024-12-19](https://www.youtube.com/watch?v=2mwl0g4_jtI), [dependent dropdowns/import, 2024-12-18](https://www.youtube.com/watch?v=q_yfd4Mq92k), and [Global Search Modal, 2024-09-19](https://www.youtube.com/watch?v=z6Qsshb0j44). They are not counted as 2025–2026 evidence. Other scanned titles, including numerous plugins and layout demos, were not promoted to API evidence.

## Findings from actual source, not titles

### Import casting and error handling

The [Pool Stock ProductImporter](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/main/v4/full-projects/pool-stock/app/Filament/Imports/ProductImporter.php) was read in authenticated GitHub. It separates row validation from field mapping, updates by SKU, creates an unknown category, rejects an unknown supplier with field validation, and reports failed rows. Its raw-price validation intentionally avoids the import column numeric cast.

Installed 5.8.4 confirms why: [ImportColumn::castNumericStateItem()](../../../vendor/filament/actions/src/Imports/ImportColumn.php:697) strips characters before converting to a float, and `integer()` delegates to numeric casting with zero decimal places. [Importer::__invoke()](../../../vendor/filament/actions/src/Imports/Importer.php:66) casts before resolving the record and validating. Therefore numeric casting is not proof that the source cell was a valid number. Retain raw input for validation where format matters; normalize the agreed identity before record resolution. Protect two-character codes and legacy identifiers from numeric coercion.

Adaptation: use the agreed `id` → Product legacy ID and `group_id` → Group legacy ID identities, application-generated internal IDs, source-wins imported fields, and explicit blank semantics. Do not copy this example's SKU identity, category-name identity, or widespread `ignoreBlankState()` calls. The existing review already covers pre-write authorization and exact row/chunk transaction decisions. [Official v5 import validation](https://filamentphp.com/docs/5.x/actions/import#validating-csv-data) confirms failed rows can be returned separately.

MCP query `pool stock` returned the older Inventory Stock importer, not this exact video-linked project. This mismatch is recorded deliberately: browser/GitHub inspection, not search ranking, established the relevant source.

### Repeaters, stable identities and staged data

The [advanced repeater project](https://filamentexamples.com/project/filament-repeater-five-advanced-use-cases) was inspected in authenticated Chrome; MCP returned actual `ProductForm.php`, `ProjectForm.php`, and `PricingTableForm.php` under `v4/forms/repeater-five-advanced-use-cases/app/Filament/Resources/`.

Useful mechanisms include typed conditional fields within each item, item labels, collapsed items, cross-field validation, `Get`/`Set` utility injection, related nested repeaters, and sibling data used as `CheckboxList` options. These can inform the shallow typed condition editor and local inclusion/default UI. They do not justify an unbounded nested rule language or automatically saving each matrix cell.

The pricing example's helper text describes stable feature keys, but its label-change and dehydration callbacks regenerate keys with `Str::slug()`. That is an observed mismatch within the example. Do not use it for global Attribute/Value/Option identity or two-character code policy. Keep record IDs stable, preserve case-sensitive codes, and reject reference-invalidating edits until explicit repair. No `fixIndistinctState()` or automatic clearing of referenced defaults should stand in for validation. [Official repeater behavior](https://filamentphp.com/docs/5.x/forms/repeater) and the prior installed-source lifecycle review remain authoritative.

The [construction project](https://filamentexamples.com/project/construction-management-system) uses a thin Filament UI over typed services according to its detail page. Its auto-fill video is a useful state-population lead, but this pass did not read that exact invoice callback. Do not infer safe replacement of existing selections from the description. MCP `construction invoice` returned a different invoice-editor example, so it was not treated as that project's source.

### Table scale is a query/design concern

The [marketplace project](https://filamentexamples.com/project/e-commerce-marketplace-operations-panel) and MCP source under `v4/full-projects/ecommerce-admin-panel` were inspected. `ProductsTable.php` eagerly loads images used by a closure column, combines relationship/aggregate columns with searchable relationship filters, and has explicit default sorting. `DashboardMetrics.php` caches computed metrics for 60 seconds rather than recomputing all dashboard aggregates on every request.

These are useful techniques, not a benchmark of this catalog. Its 100,000-product dataset is a publisher claim; the project was not installed or run. For our admin, choose explicit columns, page/search server-side, eager-load used relationships, and measure aggregate sorting/counts before adding caches. Do not copy a physical `name` SQL column: this catalog uses `product_code` for admin labels/search/sort and the chosen accessor; `product_name` is public display data. Group filters and public discovery retain their separate agreed semantics.

The [v3 performance article](https://filamentexamples.com/tutorial/filament-v3-tables-slow-performance-views) and [5.7 release](https://github.com/filamentphp/filament/releases#release-v5.7.0) belong to different framework generations. Installed Filament 5.8.4 is the verification target. No numeric performance guarantee is derived from video titles/descriptions.

### Deferred rendering remains separate from rule outcomes

The 5.8 video description gives no specific implementation link. The independently inspected [complex-product-tabs example](https://filamentexamples.com/project/filament-complex-product-form-with-tabs-defer-schema), its test source, and installed framework lifecycle already establish the narrower behavior: deferred markup still participates in schema validation and relationship-save traversal. Never treat an unvisited tab as absent business data or treat deferred rendering as a configurator disabled/hidden outcome.

The prior research documents the remaining successful-save gap for unopened relationship tabs. Keep association-retention, error reveal, and rollback cases in the eventual verification plan. Stages remain deferred; the final tab label remains exactly `Custom`, with no custom behavior introduced by this research.

## Search and verification record

FilamentExamples MCP used short topic searches including `repeater`, `pool stock`, `construction invoice`, and `marketplace`, with limits 1–2. Exact-match failures were resolved by following authenticated description links. Boost v5 searches covered import validation, failed rows and repeater relationship mutation. The previously verified Laravel 13.33.0 / Filament 5.8.4 / Livewire 4.4.6 baseline was retained; installed `ImportColumn` and `Importer` source was read for the material new casting finding.

The YouTube transcript panel for the deferred-schema video remained in a loading state during the attempted inspection. No transcript was used as evidence or exported. Root separately deepened the Callout and quote-picker sources; those findings belong to its synthesis rather than being counted as this agent's extra description inspections.

No application, dependency or database changes, Git commands, branch changes or commits were performed. No paid source archive, credentials, raw transcript or browser profile was copied into the repository. This document retains distilled findings and source links only.
