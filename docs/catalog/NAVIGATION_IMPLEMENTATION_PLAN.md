# Compact Navigation and Page Headers Implementation Plan

> **Execution status (2026-10-06):** The user authorized implementation. N1–N4 are implemented locally; focused automated checks and the main authenticated browser flows pass. N5 remains partly open for the explicitly unverified browser checks listed in the execution evidence below.

**Goal:** Consistent, compact page headers and collapsible navigation across the Livewire catalog/account shell and Filament administration.

**Architecture:** Share visual tokens and one Livewire Blade header/shell. Keep Filament's native sidebar, persisted state, search and page-header rendering; adapt its existing theme and panel configuration. Keep Livewire presentation state in a small Alpine component.

**Tech stack:** Installed Laravel 13, Filament 5, Livewire 4, Flux 2, Tailwind 4 and Pest 5.

**Spec:** The user decisions and [visual/interaction contract](#visual-and-interaction-contract) in this document, together with the existing [catalog](contracts/PUBLIC_CATALOG.md) and [administration](contracts/FILAMENT_ADMIN.md) contracts. Later user instructions prevail.

Researched and implemented locally on 2026-10-06. This is a focused extension to the completed catalog rebuild, not a restart of T01–T12. The full browser acceptance matrix is not yet complete.

## Outcome and authority

Give the signed-in Livewire catalog, account settings and Filament administration a consistent visual shell: an always-dark sidebar, compact spacing, desktop collapse and one clear page/content header. Keep each framework's supported navigation and rendering mechanisms.

The user's latest requirements are settled:

- The sidebar stays dark in both light and dark content themes.
- Use the existing `public/images/logo-ari_dark.png` for both themes.
- The logo image, brand link and every logo/header wrapper have a transparent background. No white tile, colored plate, background image or shadow behind the logo. Only the enclosing sidebar paints the dark surface.
- Keep page/content headers consistent, with smaller padding and gaps on both surfaces.
- Base the implementation on authenticated FilamentExamples research, current Filament 5 documentation and the installed application.

The user authorized this plan’s implementation on 2026-10-06. Final dimensions are 216px expanded / 58px collapsed, with the density below. Publication and deployment are separate from this local implementation; the existing authenticated-access contract remains in force.

## Verified baseline

Installed versions checked through Laravel Boost and Composer: Laravel 13.33.0, Filament 5.8.4, Livewire 4.4.6, Flux 2.20.0, Filament Blueprint 2.4.0; the application uses Tailwind 4. Recheck versions before eventual execution.

- `AdminPanelProvider` already uses the same dark logo for both themes, `topbar(false)`, a 16rem sidebar, full-width content and database notifications. Desktop collapse is commented out.
- The Filament theme paints the sidebar dark, but separately paints its header, overrides the logo height to 3rem and gives outer page headers 24px/32px padding with a 96px minimum height.
- The Livewire catalog sidebar is approximately 218px wide. It has a mobile drawer, but no desktop collapse. Its header is at least 60px tall and uses wider desktop padding.
- Catalog breadcrumbs are already in the product page's header slot; group breadcrumbs currently render in the content body.
- Account settings use a second Flux sidebar layout with forced dark mode, different branding and an unconditional admin link. Consolidating its shell removes this inconsistency and lets the existing `manage-catalog` gate govern the admin link.
- Current catalog routes require authentication. The earlier “public” preview describes a possible presentation, not guest access in the application. Preserve the existing access contract.
- Product context is teleported into `#catalog-product-context`; catalog filters own their URL/history state. Both must survive shell interaction.

No models, migrations, database fields, CRUD workflows or new server mutation actions are needed. Existing authorization, configuration saves, filters and account actions remain their own boundaries.

## Research and the resulting choices

Authenticated access was verified in FilamentExamples as `studioycm`, and the browser could read its private `LaravelDaily/FilamentExamples-Projects` repository. The GitHub connector could read `studioycm/configurator`, but returned 404 for the private examples repository; browser access supplied that missing evidence. This confirms access to the sources actually reviewed, not universal account permissions.

| Source reviewed | Useful mechanism | Decision for this application |
| --- | --- | --- |
| [Collapsible Navigation Sidebar](https://filamentexamples.com/project/collapsible-navigation-sidebar), published as v3 | Native desktop collapse; group icons provide access to items when labels disappear. | Use the equivalent verified Filament 5 APIs. Retain an icon rail rather than hiding the whole sidebar. |
| [Branded Filament Panel with Sidebar Profile Card](https://filamentexamples.com/project/branded-filament-panel-with-sidebar-profile-card), full article and [private provider source](https://github.com/LaravelDaily/FilamentExamples-Projects/blob/main/v4/full-projects/branded-filament-panel-with-sidebar-profile-card/app/Providers/Filament/AdminPanelProvider.php) | Panel branding, existing Vite theme and a `SIDEBAR_NAV_START` hook keep customization outside vendor views. Its manifest requires Filament `^5.6` despite the `v4` folder; the lockfile was not inspected. | Adopt these extension boundaries. Keep our existing image branding and native account/notification controls; a profile card is unnecessary for this compact shell. |
| [Drag to Resize Sidebar](https://filamentexamples.com/project/drag-to-resize-sidebar-for-filament), full article and available source snippets | A small hook-mounted Alpine component can update native width/state and persist a width. | Defer drag resizing. The example's wider collapse threshold, hidden native buttons and mouse-oriented handle need adaptation; they do not improve the requested compact default. |
| [Search Above Sidebar Navigation](https://filamentexamples.com/project/filament-v4-filament-search-above-sidebar-navigation), full article | The article explicitly says its older hook solution was superseded by native support from v4.1. | Use native global-search positioning. Do not mount a second search component or hide a topbar search with CSS. |
| [Filament 5 navigation](https://filamentphp.com/docs/5.x/navigation/overview) and installed `HasSidebar`, sidebar Blade and Alpine store | Native widths, collapsed groups, mobile behavior, persisted desktop state and group flyouts. | Configure native APIs; do not create a second Filament sidebar store or publish vendor sidebar views. |
| [Filament 5 render hooks](https://filamentphp.com/docs/5.x/advanced/render-hooks) | Targeted additions without replacing a layout. | Use a hook only for content absent from the supported API. No hook is necessary for baseline collapse or logo styling. |
| [Filament 5 custom pages](https://filamentphp.com/docs/5.x/navigation/custom-pages), [styling](https://filamentphp.com/docs/5.x/styling/overview), and installed page/header Blade | Native headings, breadcrumbs, subheadings and header actions already form a reusable header. | Style the outer native header; preserve its rendering and action lifecycle. |
| [Filament 5 global search](https://filamentphp.com/docs/5.x/resources/global-search#moving-the-global-search-to-the-sidebar) | Native `GlobalSearchPosition::Sidebar`; disabling the topbar already places search in the sidebar by default. | Keep existing resource search behavior; explicit sidebar positioning may document the intent. Global record search is distinct from navigation search. |
| [Livewire 4 pages](https://livewire.laravel.com/docs/4.x/pages), [layout](https://livewire.laravel.com/docs/4.x/attribute-layout), [title](https://livewire.laravel.com/docs/4.x/attribute-title) and [navigation](https://livewire.laravel.com/docs/4.x/navigate) | Page layouts, metadata/slots and navigation lifecycle. | Reuse one Blade shell and header; keep collapse local to Alpine and handle cleanup on navigation. |

Version labels are discovery hints. Verify each borrowed API against installed v5 source. Do not copy paid example projects, credentials, browser state or subscriber-only source into this repository; the links and distilled design decisions are sufficient.

### Alternatives considered

| Option | Effect | Recommendation |
| --- | --- | --- |
| Native Filament icon rail + small Livewire shell component | Compact, persistent desktop navigation with discoverable controls. | Baseline. |
| Fully hidden desktop sidebar | More content width, but needs a separate, always-visible reopening control. | Available via native API; not the selected default. |
| Drag-resizable sidebar | Adds interaction, width bounds, storage, keyboard resizing and regression work. | Optional later enhancement if actual long-label use warrants it. |
| Replace vendor sidebar/header views | Makes upgrades and native behavior our responsibility. | Unnecessary for these requirements. |

## Visual and interaction contract

### Shared density

Create one plain CSS token file, imported into the existing app and Filament theme builds. Use namespaced `--aquestia-shell-*` variables; do not override Filament's state variables globally.

| Token / element | Proposed default | Constraint |
| --- | --- | --- |
| Sidebar expanded / collapsed | 216px / 58px (`13.5rem` / `3.625rem`) | Apply through Filament panel methods and Livewire layout variables. Test native footer/flyouts at 58px before finalizing. |
| Desktop breakpoint | 1024px (`lg`) | Match Filament's installed sidebar store. |
| Sidebar brand row | 48px minimum; 10px inline padding | Natural height if needed; controls must fit. |
| Expanded logo | 28px high; intrinsic aspect ratio | Same transparent PNG in both themes, no crop, filter or plate. |
| Sidebar navigation | 6px vertical / 10px horizontal item padding; 4px between items; 8px between groups | Keep at least a 32px desktop target and 44px mobile menu target. |
| Page header | 6px block / 10px inline padding; 48px minimum | Not a fixed height. Breadcrumbs, subtitles and wrapped actions may increase it. |
| Header heading | 20px / 24px line height | One page `h1`; escaped dynamic text. |
| Breadcrumb and subtitle | 12px / 16px line height; 2px text-row gap | Real ancestor links; allow long names to wrap. |
| Header actions | 8px gap | Wrap or move to the next row on narrow screens. |
| Main content | 12px padding; 8px main workspace gap | Scope to shell/workspace wrappers, not every form or table cell. |
| Related editor card | 12px padding; 8px header/content gap | Preserve nested editor boundaries and visible relationship controls. |
| Sidebar surface / active / hover | `#17212c` / `#294762` / `#22374a` | Fixed across themes; readable labels, icons and focus rings. |
| Content theme | Existing light/dark surfaces | The theme switch changes content, not the sidebar or logo. |

The wordmark is visible when expanded. When collapsed, let native Filament hide it and show the native expand control. Livewire follows that behavior. Do not squeeze the full wordmark into the rail or invent a replacement logo without a supplied asset.

The sidebar alone owns its background. In Filament, `.fi-sidebar-header`, its logo containers, the brand anchor and `.fi-logo` must all resolve to transparent background color and no background image in both themes and interaction states. Inspect installed DOM classes before targeting them. Remove the current header paint and 3rem logo override. Livewire applies the same rule to its brand row, anchor and image. Keyboard focus uses an outline rather than a filled logo background.

### One page/content header

Use the same visual order on both surfaces: breadcrumbs above the heading; optional subtitle below; actions on the right. On mobile, retain the menu button, then let text/actions wrap. Keep the header in normal document flow for this initial change.

- **Livewire:** add `resources/views/components/page-header.blade.php` with a required heading, optional subtitle and `breadcrumbs`, `leading` and `actions` slots. The enclosing layout remains responsible for the document title, navigation and theme control. Keep browser title and visible heading separate where needed.
- **Catalog index:** “Product catalog”; no invented breadcrumb or subtitle.
- **Group page:** current group name and existing description; move the ancestor breadcrumbs into the header slot and use their compact variant. Remove the body duplicate.
- **Product page:** preserve the existing product-name/code title fallback and current group breadcrumb behavior. Keep product code/facts in their existing content locations.
- **Account settings:** use the shared shell, “Settings” as the page heading and the existing account-settings subtitle. Profile/Password/Appearance/Two-Factor remain section `h2` headings. Give each page an appropriate browser title without a second page `h1`.
- **Filament:** retain native `getHeading()`, `getSubheading()`, `getBreadcrumbs()` and `getHeaderActions()` behavior. Style the native outer `.fi-header` to match the Livewire header. Do not override `getHeader()` for styling: it replaces the standard rendering path.
- Target outer Filament headers through the existing direct-child path `.fi-main > .fi-page > .fi-page-header-main-ctn > .fi-header`. Give nested related editor headings their own compact rules; do not convert them into another page header or expose duplicate breadcrumbs.
- Retain `topbar(false)`. The sidebar brand row and the page heading serve different purposes; adding another title bar would duplicate navigation chrome.

### Livewire state and accessibility

Register a `catalogShell` Alpine component through `resources/js/catalog-shell.js`, imported by the existing `resources/js/app.js`. Use Livewire's existing Alpine instance and initialization lifecycle; do not start a second Alpine instance or install a persistence package.

- State: `desktopCollapsed` (persisted), `mobileOpen` (ephemeral), `isDesktop` (media query). Desktop uses the saved preference; mobile starts closed and uses the expanded drawer width.
- Storage key: `aquestia:catalog:sidebar-collapsed:v1`. Accept only valid stored values; catch unavailable/corrupt storage and fall back to expanded desktop navigation. Do not reuse Filament's storage keys.
- Prefer an Alpine component inside the Livewire-managed layout content so its lifetime is explicit. Subscribe to the 1024px media query in `init()` and remove the listener in `destroy()`. Close the mobile drawer when crossing to desktop and after navigation; verify `wire:navigate` initialization before adding any global navigation listener. If one is necessary, register it once and remove it on teardown.
- Sidebar width and content offset derive from the same state; mobile uses no content offset. Collapse never calls Livewire, changes the URL, reloads a dataset or adds a network request.
- Keep links mounted. Hide visual labels while retaining their accessible names; provide hover and keyboard-focus tooltips for rail links. Preserve `aria-current` for catalog and account settings routes.
- Keep `#catalog-product-context` mounted exactly once. Reduce the teleported context summary to its existing labeled edit button in the rail; show its summary in the expanded sidebar. Do not conditionally destroy the teleport target or the context component.
- Keep auth footer actions accessible in the rail through labeled controls. Preserve logout as a POST form with CSRF.
- Buttons expose `aria-controls` and the correct `aria-expanded` state. Closed mobile navigation is not focusable. Opening the drawer moves focus into it; Escape/backdrop/close button dismiss it and return focus to the opener. Make background content inert while the modal drawer is open and contain keyboard focus.
- Account for `x-cloak`, reduced motion, 200% zoom and browser storage failures. No horizontal page overflow or clipped action menus.

### Filament configuration and extension boundaries

Use `Filament\Navigation\NavigationGroup`, `Filament\Support\Icons\Heroicon` and existing panel APIs:

```php
->sidebarCollapsibleOnDesktop()
->sidebarWidth('13.5rem')
->collapsedSidebarWidth('3.625rem')
->brandLogoHeight('1.75rem')
->globalSearch(position: \Filament\Enums\GlobalSearchPosition::Sidebar)
```

Keep existing `brandLogo()` and `darkModeBrandLogo()` pointed at the dark asset. The explicit search position documents the current no-topbar placement; it does not add a new search feature.

Replace only the navigation-group string configuration with matching `NavigationGroup::make()->label(...)->icon(...)` definitions. Preserve group names, resource ordering, route destinations and authorization:

| Existing group | Group icon | Existing item order and proposed item icons |
| --- | --- | --- |
| Product configuration | `Heroicon::OutlinedAdjustmentsHorizontal` | Values: `OutlinedListBullet`; Options: `OutlinedTag`; Attributes: `OutlinedAdjustmentsHorizontal`; Configurators: `OutlinedSquares2x2` |
| Catalog | `Heroicon::OutlinedCube` | Groups: `OutlinedFolder`; Products: `OutlinedCube` |
| Settings | `Heroicon::OutlinedCog6Tooth` | Context settings: existing `OutlinedCog6Tooth` |

Exact enum cases were checked in installed source. Give groups icons so native collapsed group flyouts remain discoverable. Filament suppresses child item icons in expanded icon groups by design; preserve that hierarchy. Allow native group collapse and its persistence; do not force groups closed on every render. Keep the dashboard and “Open dashboard catalog” links and the existing native account/notification footer controls.

Use the native `$store.sidebar` and its persisted desktop/group state. Do not add a competing collapse store, custom local-storage migration or hidden native toggle buttons. Verify mobile reopening with `topbar(false)` and the existing native menu control.

For a future contextual addition, prefer panel `renderHook()` with `Filament\View\PanelsRenderHook` over vendor view replacement. `SIDEBAR_NAV_START` suits a genuinely required compact context block; `SIDEBAR_START` can mount behavior absent from native APIs. Scope hooks to the relevant page/resource/component when content is not panel-wide. The branded example's large profile card, remote avatar lookup and disabled global search are not requirements here.

Do not add query-backed navigation badges or new eager components for this shell. No new database work is required by its visual state.

## Implementation sequence

Implementation used the established primary checkout, the relevant Laravel/Filament/Livewire/frontend/testing skills, and Laravel Boost. `.ai/rules` did not exist. Unrelated dirty files were preserved. The checklist records implementation evidence; the matrix and execution notes distinguish observed behavior from remaining verification.

### N1 — Establish shared density and transparent branding

Files: new `resources/css/shell.css`; existing `resources/css/app.css`, `resources/css/filament/admin/theme.css`, `resources/views/components/layouts/catalog.blade.php`, `app/Providers/Filament/AdminPanelProvider.php`.

Interface: plain CSS custom properties consumed by the two builds, starting with the following values. Use further named tokens for the dimensions in the density table rather than repeating independent numbers in each theme.

```css
:root {
    --aquestia-shell-sidebar-width: 13.5rem;
    --aquestia-shell-rail-width: 3.625rem;
    --aquestia-shell-header-padding-block: 6px;
    --aquestia-shell-header-padding-inline: 10px;
    --aquestia-shell-gap: 8px;
    --aquestia-shell-content-padding: 12px;
    --aquestia-shell-sidebar-surface: #17212c;
}
```

- [x] Define the namespaced shared tokens; import `./shell.css` from app CSS and `../../shell.css` from the Filament theme. Keep the current Tailwind/vendor imports and source scanning intact.
- [x] Set the provider's brand height to 1.75rem; remove the theme's competing height and painted logo/header wrappers.
- [x] Apply the always-dark sidebar and transparent brand-row rules to both surfaces, including dark mode, hover and focus.
- [x] Reduce outer header/main/workspace padding using scoped selectors. Leave existing form/table density rules alone unless they directly conflict with the shell.
- [x] Build both existing Vite entry points and inspect expanded logos in both themes. Check computed background color/image on each image, anchor and wrapper; check that the parent sidebar is still dark.

Acceptance: identical logo asset, undistorted and unboxed; reduced outer spacing without losing content or focus visibility.

### N2 — Consolidate page headers and the account shell

Files: new `resources/views/components/page-header.blade.php`; existing `resources/views/components/layouts/catalog.blade.php`, `resources/views/components/layouts/app.blade.php`, `resources/views/livewire/catalog/group-show.blade.php`, `resources/views/livewire/catalog/product-show.blade.php`, `resources/views/components/catalog/breadcrumbs.blade.php`, `resources/views/livewire/settings/{profile,password,appearance,two-factor}.blade.php`, `resources/views/components/settings/layout.blade.php`, `app/Livewire/Settings/{Profile,Password,Appearance,TwoFactor}.php`.

Interface: `<x-page-header :heading="$heading ?? $title" :subtitle="$subtitle ?? null">` accepts `heading` (escaped text), optional `subtitle` (escaped text) and three named Blade slots. A header with no actions or breadcrumbs leaves those areas empty. The app wrapper supplies “Settings” as the visible heading; browser titles remain Profile, Password, Appearance and Two-Factor Authentication. The catalog layout forwards existing named slots and retains its own document shell.

Before moving markup, add these meaningful regression cases to the existing suites and run them to observe the current failure. The public-catalog suite already signs in its user in `beforeEach`; the dashboard suite needs `actingAs` in the case below.

```php
// tests/Feature/Catalog/PublicCatalogTest.php
test('group breadcrumbs belong to the page header', function () {
    $root = Group::factory()->create(['name' => 'Root']);
    $group = Group::factory()->for($root, 'parent')->create(['name' => 'Leaf']);
    $html = $this->get(route('catalog.groups.show', $group))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//h1'))->toHaveCount(1)
        ->and($xpath->query('//*[@aria-label="Breadcrumb"]/ancestor::header'))->toHaveCount(1)
        ->and(trim($xpath->evaluate('string(//header//h1)')))->toBe('Leaf');
});

// tests/Feature/DashboardTest.php
test('ordinary users do not see admin navigation in account settings', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('profile.edit'))->assertOk()
        ->assertDontSee(route('filament.admin.pages.dashboard'), false);
});
```

- [x] Add/run those tests with `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/Catalog/PublicCatalogTest.php`; confirm failures concern the misplaced breadcrumbs and account admin link, not environment setup.

- [x] Render the Livewire header component from the catalog shell and forward breadcrumb/action slots. Separate visible heading from document title; escape dynamic heading/subtitle strings.
- [x] Move group breadcrumbs to `<x-slot:breadcrumbs>` using the compact variant. Preserve real ancestor order, URLs and the product header behavior.
- [x] Make the default `layouts.app` wrapper delegate to the catalog shell for the current account-settings consumers, passing “Settings” and its subtitle while retaining page-specific browser titles via `Livewire\Attributes\Title`.
- [x] Remove the four body-level `partials.settings-heading` includes so each route has exactly one page `h1`. Keep account section headings at level 2 and reduce only the settings layout's outer navigation/content gap.
- [x] Keep existing settings forms, redirects, validation, security controls and Flux scripts. Leave the old sidebar template unused rather than broadening the task into unrelated deletion.
- [x] Confirm both shells use the existing `manage-catalog` gate for admin navigation. Ordinary users must not see an admin link in their new account shell; direct URL protection remains authoritative.
- [x] Rerun the same focused suites and the affected settings tests; add the one-`h1`/header location assertion for product and settings routes, preserving their browser titles and escaped text.

Acceptance: index, group, product, account settings and representative Filament pages share the header's spacing/order; no duplicate page title or body breadcrumb; header actions remain available.

### N3 — Implement the Livewire collapse/drawer component

Files: new `resources/js/catalog-shell.js`; existing `resources/js/app.js`, `resources/views/components/layouts/catalog.blade.php`, `resources/views/livewire/catalog/context-selector.blade.php`, `resources/css/app.css` / `resources/css/shell.css`.

Interface: `catalogShell()` returns Alpine state with `desktopCollapsed: boolean`, `mobileOpen: boolean`, `isDesktop: boolean`, `init(): void`, `destroy(): void`, `toggleDesktop(): void`, `openMobile(): void` and `closeMobile(): void`. Derive effective collapsed state as `isDesktop && desktopCollapsed`. Use the existing `catalog-filters.js` registration pattern:

```js
document.addEventListener('alpine:init', () => {
    window.Alpine.data('catalogShell', catalogShell);
}, { once: true });
```

Read/write storage as the exact strings `'true'` and `'false'`. In `init()`, read with `try/catch`, create `matchMedia('(min-width: 1024px)')`, set `isDesktop = query.matches` and attach a `change` callback that updates it and closes mobile navigation. In `toggleDesktop()`, flip only `desktopCollapsed` and persist with `try/catch`. Store listener-removal callbacks in the factory closure and call them in `destroy()`.

Put `x-data="catalogShell"` on an inner shell wrapper rather than `<body>`; transfer the sidebar/main children into it. Use Alpine's already-bundled focus capability for the drawer if confirmed in the installed build; otherwise implement the focus contract without adding a package. The exact focus behavior must be proved by the keyboard checks below, not inferred from the presence of a directive.

- [x] Register the Alpine component with the existing Livewire initialization lifecycle. Add the storage parsing, width/offset derivation, media-query setup/cleanup and independent mobile state described above.
- [x] Add the desktop collapse control and mobile opener/closer, icon links, accessible labels, tooltips and current-route states. Preserve auth-gated links and POST logout.
- [x] Implement mobile focus containment, inert background, Escape/backdrop close, navigation close and focus return. Keep toggle controls usable without a visible wordmark.
- [x] Adapt the teleported context summary for the rail without destroying its target, changing its selected values or its immediate-update semantics.
- [x] Verify collapse/reload/back-forward and account `wire:navigate` transitions. The collapse gesture produces no HTTP request and does not alter catalog filters or product context.

Acceptance: desktop rail preference survives reload/navigation; mobile drawer is keyboard usable; context editing works expanded, collapsed and on mobile.

### N4 — Enable native Filament collapse and groups

Files: `app/Providers/Filament/AdminPanelProvider.php`, resource classes under `app/Filament/Resources/{Values,Options,Attributes,Configurators,Groups,Products}/`, `resources/css/filament/admin/theme.css`.

- [x] Enable the native methods and widths shown above; preserve existing panel registration, middleware, plugins, notifications and no-topbar layout.
- [x] Add the three matching navigation-group definitions and proposed icons; preserve resource sort values and the context-settings access checks.
- [x] Adjust scoped sidebar item/group/footer spacing so 58px fits the native controls and flyouts. If a native element cannot fit accessibly, document the smallest verified width instead of clipping it.
- [x] Verify native global search in the expanded sidebar, closed-rail behavior, group flyouts, group collapse persistence, notifications and user menu. Keep native mobile controls visible and operable.
- [x] Verify no sidebar/header vendor views were published or replaced and no second state store/search component was added.

Acceptance: the Filament rail uses native behavior and persistence; all permitted resources and footer actions remain reachable, with consistent branding/header density.

### N5 — Verify the integrated behavior and record evidence

Use the installed testing-best-practices skill. Extend existing coverage where it is already meaningful; do not add tests that merely mirror CSS or provider method calls.

- [x] Extend `tests/Feature/DashboardTest.php` for the account-shell admin-link gate if existing coverage does not catch it. Continue testing guest redirects and forbidden direct admin URLs.
- [x] Extend `tests/Feature/Catalog/PublicCatalogTest.php` for exactly one page `h1` and header-owned group/product breadcrumbs, using DOM assertions and escaped long names rather than fragile full-markup snapshots.
- [x] Check account settings render their shared page heading and retain their existing forms; add a narrow assertion to the existing settings tests where needed.
- [x] Run the affected tests. Existing catalog context, administration and account tests provide regression coverage for the preserved actions.
- [ ] Complete all real authenticated browser checks in the matrix below. Use Boost browser logs for current errors and inspect browser/network state separately from server performance. Do not describe mockup clicks as application verification.
- [x] Save reviewable screenshots of Livewire and Filament expanded/collapsed, each content theme and one mobile drawer. Keep screenshots local unless the user asks to publish them.

Expected focused commands after implementation:

```sh
php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/Catalog/PublicCatalogTest.php tests/Feature/Catalog/ContextSelectorTest.php
php artisan test --compact tests/Feature/Settings/ProfileUpdateTest.php tests/Feature/Settings/PasswordUpdateTest.php tests/Feature/Settings/TwoFactorAuthenticationTest.php
php artisan test --compact tests/Feature/Filament/CatalogAdministrationTest.php tests/Feature/Filament/ContextSettingsTest.php
vendor/bin/pint --dirty --format agent
npm run build
```

Pint applies when PHP files have changed. Run tests against the project's guarded disposable test database, never the active development database; verify test configuration first. Pest browser dependencies are not presently installed: use the available real browser tooling rather than adding dependencies for this task. The application is served by Herd; do not launch another server. Resolve local URLs through Boost's `get-absolute-url` before sharing or opening them. After focused tests pass, ask the user to run the full `php artisan test --compact` suite as required by AGENTS.md.

| Browser scenario | Required result |
| --- | --- |
| 360/390px, 768px, 1023px, 1024px, 1440px; 200% zoom | No horizontal overflow; correct drawer/rail transition; long headings and actions remain usable. |
| Light/dark theme in catalog, settings and Filament | Sidebar stays dark; logo asset is unchanged; every logo/header wrapper is transparent. |
| Desktop collapse, reload, navigation and new page | Saved preference restored; active link and reopening control visible. |
| Mobile open/close, Escape, backdrop, Tab/Shift+Tab | Focus contained and returned; closed drawer/background cannot receive inappropriate focus. |
| Filament group flyouts and user/notification controls | Keyboard and pointer access; no clipping at the chosen rail width. |
| Product context expanded/rail/mobile | One teleport target; selections and modal remain functional; no draft loss. |
| Catalog filtering, Back/Forward and collapse | URL/filter results unchanged by collapse; no extra request from the toggle. |
| Settings `wire:navigate` repeatedly | Theme/persistence work; one controller/listener set; no stale body state or duplicate scripts. |
| Ordinary user / eligible admin / guest | Links match the existing gate; direct route enforcement and guest redirects remain unchanged. |
| Dirty related editor draft + navigation/sidebar changes | Draft remains mounted; nested header/actions and relationship controls remain visible. |
| Unavailable or malformed local storage; reduced motion | Usable expanded fallback; no console exception or unnecessary animation. |

## Completion and review boundary

Review focus is pinned to these task-owned checks: long/escaped headings and ancestor trails (N2 DOM/browser checks); storage denial/corruption (N3 browser check); repeated `wire:navigate` and breakpoint transitions (N3 lifecycle check); rail context/flyout/notification controls (N3/N4 keyboard checks); theme and transparent ancestor wrappers including focus/hover (N1 computed-style checks). N5 repeats them as an integrated application check, not a substitute for each task's verification.

Implementation is complete only after N1–N5 pass with actual application evidence. Record changed files, chosen final dimensions, focused-test/build results and browser observations. Do not claim release or performance improvements based on styling alone.

The implementation uses the approved 216px/58px widths and 6px/10px/8px density. The logo choice, transparent logo wrappers, always-dark sidebar and consistent page-header requirement are already user decisions. Drag resizing, a large profile card, public guest access and extra navigation/search features are outside this plan.

The three standalone HTML examples outside the repository were updated on 2026-10-06 to show dark sidebars, the same dark logo with transparent wrappers and one consolidated page header. Static checks confirmed the embedded logo bytes, one header-owned `h1`, the transparency rule and matching breakpoint. Visual browser verification of this revision was blocked because the browser tool disallows local `file://` URLs; no workaround was attempted. The “public concept” remains a possible presentation, not enabled guest access. These are visual review artifacts, not framework implementation or application acceptance evidence; their standalone JavaScript is not to be copied into Laravel.

## Execution evidence — 2026-10-06

### Implementation and decisions

Boost confirmed Laravel **13.35.0**, Filament **5.9.0**, Livewire **4.4.7** and Flux **2.20.1** at execution. PHP is 8.4; the project uses Tailwind 4. The version change from the research baseline was already present in the checkout. Current Boost documentation and installed source support the chosen APIs.

The implementation adds `resources/css/shell.css`, `resources/js/catalog-shell.js` and `resources/views/components/page-header.blade.php`. Existing app/theme builds consume the shared density, typography, target-size and sidebar-color tokens. Catalog and account layouts delegate to one shell/header; group breadcrumbs moved into its header slot. The four account controllers retain page-specific browser titles and their sections remain `h2`s. The provider and six resource classes use native collapse, sidebar search and group/item icons. The existing product-context teleport remains mounted once.

The drawer uses local focus containment with an inert background rather than `x-trap`: a nested native Flux context dialog must be allowed to take focus. The trap yields while that dialog is open; its existing Escape handling closes the dialog first and returns focus to the context trigger. The component removes both media-query and navigation listeners on teardown. Existing forms, filters, authorization and save boundaries remain authoritative.

Task-owned PHP/view/CSS/JS/test changes are confined to the paths in N1–N5, plus `tests/Unit/CatalogShell.test.js`. Product header and breadcrumb components already met the contract and required no edit. No vendor sidebar/header views exist under `resources/views/vendor`; no vendor view was published. The existing plan/index are updated here as the execution ledger. Pre-existing dependency, instruction, asset and configuration-field changes were left intact. No database record was saved during browser verification.

### Executed checks

The initial new PHP regression cases failed on the expected breadcrumb location, missing Settings header and ordinary-user admin link. The first JavaScript run failed because the new shell module did not yet exist. After implementation and correction of a Blade directive compilation error, the final focused command passed against SQLite `:memory:` under the existing guarded test bootstrap:

```sh
php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/Catalog/PublicCatalogTest.php tests/Feature/Catalog/ContextSelectorTest.php tests/Feature/Settings/ProfileUpdateTest.php tests/Feature/Settings/PasswordUpdateTest.php tests/Feature/Settings/TwoFactorAuthenticationTest.php tests/Feature/Filament/CatalogAdministrationTest.php tests/Feature/Filament/ContextSettingsTest.php tests/Feature/Filament/ConfiguratorWorkspaceTest.php
node --test tests/Unit/*.test.js
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
```

- PHP: **65 passed, 418 assertions**. Coverage includes guest redirects, direct admin denial, account navigation gating, catalog/context behavior, settings actions and related-editor saves.
- JavaScript: **12 passed**, including four shell regressions for persistence, breakpoint transitions, denied/malformed storage and listener cleanup; the existing filter/transport checks also pass.
- Pint, both Vite CSS entry points and the JS build passed. Whitespace validation passed.
- A fresh reviewer inspected the task-owned changes and found no actionable correctness issues. The possible rail-width concern was checked in the browser: buttons end at x=53 inside the 58px rail; the native flyout spans x=61–285 without clipping.

### Actual authenticated browser observations

These checks ran against the Herd application, using an existing eligible-admin session and existing catalog data. They are separate from mockup review and server performance claims.

| Scenario | Observed evidence / remaining scope |
| --- | --- |
| 360/390/768/1023/1024/1440px | Livewire product and Filament resource pages had no horizontal document overflow. Mobile drawers use 216px; desktop rails use 58px. Menu controls change at 1024px. Long product headings wrap; Filament header actions wrap on narrow screens. |
| Compact dimensions | Livewire index/settings and Filament dashboard headers measured 48px. Breadcrumb-bearing headers grow naturally. Brand image height is 28px. |
| Light/dark branding | Catalog and Filament use `logo-ari_dark.png` in both themes. Computed backgrounds on image/link/brand wrappers are transparent with no background image; sidebar stays `rgb(23, 33, 44)`. Settings uses the same implementation; its light-mode layout was observed. Separate settings dark-mode and focused/hovered-logo computed-style checks remain unverified. |
| Desktop persistence | Livewire and Filament restore 58px after reload. Livewire account `wire:navigate` retains the rail and renders one Settings `h1` with browser title Profile. Repeated account navigation stress remains unverified. |
| Livewire mobile keyboard | Opening moves focus to Close navigation. Tab/Shift+Tab wrap between the first/last controls. Escape returns focus to Open navigation. The main wrapper has the inert attribute while open. Backdrop and navigation dismissal are implemented; their dedicated click checks remain unverified. |
| Product context | Edit opens from expanded sidebar, collapsed rail and mobile drawer. Exactly one teleport target is present. Closing the nested dialog with Escape keeps the drawer open and returns focus to its trigger. Existing selections were not changed. |
| Catalog state/network | Selected 6 bar / Threaded / 1-inch filters and six matching products survive collapse. URL unchanged. CDP observed **zero HTTP requests** from the collapse toggle. Browser Back restored those filters; Forward restored the product page. |
| Filament native behavior | Collapsed group flyout opened by keyboard without clipping. Product configuration group collapse survived reload. Native sidebar global search returned product links for D60. Notifications opened, account menu opened by keyboard, and native mobile opener/navigation worked. |
| Related editor | A temporary unsaved description remained intact through collapse/expand. It was cleared and cancelled without saving. Nested breadcrumbs stayed hidden; native nested-editor heading rendering remains separate from the outer page header. |
| Storage / reduced motion | Denied and malformed storage plus teardown were proved by executable JS tests. Livewire reduced-motion CSS removes the drawer transition. Browser storage denial/corruption and reduced-motion emulation remain unverified. |
| 200% zoom and fresh console logs | **Not verified.** Browser recovery lost the authenticated verification tab before these checks. A fresh tab reached the login page. Boost browser logs contained only old 2026-09-27 entries, which were excluded as current evidence. |

The final CSS refactor replaces repeated values with shared tokens at the same dimensions/colors shown in the captures; the production build passed afterward. Full N5 browser acceptance remains open for the cases explicitly identified above. PhpStorm inspections were unavailable for this checkout because the connected IDE had a different project open; the affected PHP was covered by Pint and executed tests. The full PHP suite was not run; AGENTS.md asks the user to run `php artisan test --compact` after the focused suites pass.

### Saved application captures

Captures are local and outside the repository at `/Users/studioycm/.codex/visualizations/2026/09/24/01a0d425-bb65-7411-af05-deb60bae700e/`:

- `implemented-livewire-expanded-light.jpg`, `implemented-livewire-collapsed-light.jpg`
- `implemented-livewire-expanded-dark.jpg`, `implemented-livewire-collapsed-dark.jpg`
- `implemented-livewire-mobile.jpg`
- `implemented-filament-expanded-light.jpg`, `implemented-filament-collapsed-light.jpg`
- `implemented-filament-expanded-dark.jpg`, `implemented-filament-collapsed-dark.jpg`
- `implemented-filament-mobile-dark.jpg`

These show the implemented application. The earlier standalone HTML artifacts remain design references. This local work has not been committed, pushed or deployed.
