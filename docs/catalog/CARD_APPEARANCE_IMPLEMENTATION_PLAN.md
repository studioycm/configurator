# Catalog Filters and Product Card Appearance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: use superpowers:executing-plans to implement this plan task by task. The current request is planning only. Preserve the user's workspace and follow the existing application conventions.

**Goal:** Let visitors compare matching Products at a glance using compact, consistent cards, while keeping filter feedback almost immediate.

**Architecture:** Preserve the existing local filter engine, Medium freshness, class-based GroupShow and independent Livewire JSON card action. Calculate differing card properties from the complete Product collection already loaded by that action, then render escaped Blade chunks. Persist appearance through the existing Group result_settings and transactional aggregate save.

**Tech stack verified on 2026-10-06:** PHP 8.4; Laravel 13.35.0; Livewire 4.4.7; Filament 5.9.0; Pest 5.3.0; Laravel Boost 2.10.2. Tailwind v4 is configured in package.json; package-lock.json contains 4.3.3. Use Livewire's bundled Alpine.

**Spec and authority:** The user's latest decisions in this conversation; the selected [interactive design document](/Users/studioycm/.codex/visualizations/2026/09/25/01a0d67b-889d-7272-9db1-b69d37ab9009/catalog-design-board.html); this consolidated specification; [current public contract](contracts/PUBLIC_CATALOG.md); [admin contract](contracts/FILAMENT_ADMIN.md). Earlier competing proposals remain historical. The HTML is a six-Product visual reference, not the production filter algorithm or proof of application implementation.

## 1. Authority, scope and current evidence

The selected design is **option 1 from the final three-interpretation document**: full-width property rows/cells. Its property colors are deferred, so this stage uses a neutral appearance. This does not restore the original plain-grid interpretation that was removed before the final three were presented.

The visitor's job is to compare actual matching Products by their values and open a suitable Product without needing to select every filter. The administrator's job is to configure which properties and appearance a leaf Group uses. These remain separate public and administration surfaces.

| Evidence | Current finding | Consequence |
| --- | --- | --- |
| User decisions | Option 1, Group appearance settings, neutral styling now, property color/icon management only a future idea | Build one card treatment and reuse current public filter styling |
| Current product-card.blade.php | Populated property values are joined with commas; only “View product” is a link; header and subtitles have generous spacing | Replace the comma paragraph with distinct grid cells and make the whole card a native link |
| Current GroupForm and SaveGroupSettings | card_properties, cards_per_row, max_results and products_debounce_ms already exist | Extend their existing normalization, validation and aggregate save; retain their behavior |
| Current CatalogCards | One revision read and one scoped Product read on a warm eligible request; rendering happens after the read transaction | Compute differences from the loaded complete result set; do not add SQL or another request |
| Current BuildCatalogSnapshot | Rows contain filter/preset properties, while card_properties is small settings metadata | Keep card-only data out of the browser snapshot |
| Current catalog-filters.js | Generation/revision guards, independent card chunks, between-frame insertion and stale chunk retirement already exist | Integrate new result notices through those guards |
| Current CSS | Hidden-card handling and content-visibility support already exist | Measure the existing mechanism before changing it or reintroducing an earlier unsuccessful experiment |
| Historical local 501-Product evidence | At all/100 ms debounce, DOM p95 146.4 ms and paint-estimate p95 229.3 ms were recorded in the earlier implementation log | Reproduce on the current code; compact markup is a candidate improvement, not a measured fix |
| Historical admin failures | Current ContextSettingsTest now expects “Territory & Application”; the table standards test and Attributes column both use “Configurators” | Rerun the two tests; do not claim the historical failures remain |
| Current workspace | Many unrelated modifications, including GroupShow and app CSS, are present | Read the current diff and preserve concurrent work; no reset, stash or broad staging |

Application behavior was not tested again while writing this plan. Earlier local measurements, old deployment status and the user's report of fast remote behavior are not substitutes for a current comparable remote benchmark.

### Global constraints

- Implement through the existing Models, Services, Actions, DTOs, Blade components and class-based Livewire conventions.
- Preserve Product JSON storage, existing indexes, Product identifiers and import data. No schema migration is needed for these JSON settings.
- Keep local filter computation, exact total, compatibility, notices and URL changes independent of cards.
- Keep raw dataset rows outside public Livewire state and Alpine's reactive proxy.
- Preserve existing access rules and the manage-catalog authorization boundary.
- No dependency installation, second Alpine startup, additional infrastructure service/worker, browser-test harness or islands. The focused query-free PHP display helper in §7 is within scope.
- No pagination in this public experiment; retain its legacy storage and distinct legacy test coverage.
- No numeric counts beside filter options, no force-hide setting, no property colors or property icons in this stage.
- Do not change the shared navigation/header/sidebar as part of this work.
- Do not create or change production Product fixtures to verify the design.

### Review focus

1. A property appears only after the first 24 cards: difference detection must include it from the first chunk.
2. One Product is missing a value that another has: it must retain the same property slot and show a clear missing-value marker.
3. A newer selection arrives during partial card insertion: obsolete cards and their property notice must not reappear.
4. A layout or settings save occurs on an open page: revisions must refresh the display without restoring obsolete selections.
5. Six cards per row, long values and wrapped filter legends: all content must remain readable with correct alignment and keyboard access.

Each condition is assigned to a task and the verification matrix below.

## 2. Consolidated decisions and corrections

| Topic | Final direction |
| --- | --- |
| Primary priority | Correct, almost immediate filter interaction; cards may arrive later |
| Filter algorithm | Preserve the application's own local algorithm; the sketch is a responsiveness benchmark only |
| Filter appearance | Existing fieldset/legend/button style, type sizes, blue selected state, outlines and responsive layout |
| Filter geometry | Headings, first button positions and field bottoms should align within each responsive row |
| Product comparison | Distinct values in predictable positions; remove comma-separated presentation |
| Chosen interpretation | Option 1's full-width row/cell treatment, now neutral |
| Earlier first interpretation | Removed; do not reintroduce it as another selectable design |
| Card header | Compact Product code and link indicator; real root/actual Group subtitles directly underneath |
| Card navigation | Whole card is one native Product link |
| Card grid | Existing Group setting supports 1–6 cards per row; narrower screens fit fewer |
| Property grid | One or two columns, equal-height cells, minimal block/inline padding |
| Labels | Group-controlled show/hide; hidden labels remain accessible |
| Label/value layout | Inline, above or below; start/center; space-between for inline content |
| Differing properties | Group-controlled on/off; evaluate all matches, not selected fields or visible cards |
| Already selected properties | In difference mode, shared selected properties naturally disappear; no separate hide-selected control |
| Existing property selection | Keep card_properties; empty means current Group filter properties in configured order |
| Padding | Carry forward the earlier explicit request for controlled block/inline padding; use compact defaults |
| Final unpaired item | Technical recommendation: span the whole final row in a two-column property grid |
| Color dots/badges | No dots, chip-style badge inset or extra wrapper padding; no property color mapping |
| Property icons | Deferred, including icon display/order/inline controls |
| Future property settings page | Idea only: later centralized property labels/colors/icons; no placeholder screen or storage now |
| Cards and filters ownership | Existing Alpine-owned containers and renderless JSON delivery; islands remain deferred |
| Test infrastructure | Existing meaningful PHP/Node checks plus real browser/DevTools verification; defer Pest browser construction |
| Production proof | Repeat comparable browser/server measurements after an authorized release |

**Padding packaging:** The latest message explicitly listed four main Group appearance settings, while an earlier message explicitly requested controlled padding. This plan retains padding as a small adjacent “Spacing” group. An optional clarification was requested. If the user chooses fixed spacing, remove the two padding fields/keys/tests below and retain the 4 px block / 6 px inline CSS defaults. The four primary settings and all other tasks stay the same.

The final-item rule is a recommendation, not a previously selected global setting. The earlier adjustable orphan-item control belonged to the discarded icon interpretation; it is not another required admin control.

## 3. Protected filter and delivery contract

These are already present in the selected architecture. Verify them while changing presentation; do not rebuild the earlier discovery pipeline.

### Selection, compatibility and feedback

- One ordinary value per field; clicking the selected value clears it.
- Newer choices take precedence and remove incompatible older choices with visible, polite feedback naming the removed field/preset.
- Compatibility tests coexistence with the other ordinary choices and active preset, excluding the candidate field's own ordinary choice.
- Incompatible choices remain clickable. Selected styling takes precedence.
- Presets remain atomic; a newer ordinary choice may evict an incompatible preset.
- Singleton presets clear the corresponding manual choice and hide that field. Multivalue presets keep the field visible and retain compatible manual choices.
- Clicking the active preset is a no-op. Removing it does not restore discarded choices.
- Clear filters keeps the preset; Reset all clears both.
- Preserve exact total only, sharing links, precedence, unrelated query parameters, Back/Forward and bounded malformed-link normalization.
- Missing/null/empty cells do not generate filter options. Preserve exact strings, literal "0", dictionary index zero, leading zeros, case, quotes and Unicode.
- Do not reintroduce per-option SQL counts or whole-Group read-time diagnostics on clicks.

### Preserved computation and data design

Keep the pure JavaScript engine separate from DOM updates and transport. Normalize bounded incoming choices against the snapshot; reconcile constraints newest-first, retaining each only when an intersection still contains Products. For compatibility, scan rows satisfying the active preset: zero ordinary mismatches contribute to the exact total and every field's alternatives; one mismatch contributes only to that field's alternatives; two or more contribute to none. Keep separate Product rows when combinations are identical. This is the agreed self-excluding compatibility calculation, not the sketch's algorithm.

The compact snapshot contains schema, Group identity/revision, ordered field/value/label records, preset definitions including preset-only keys, small display/request settings, and integer-coded Product rows. Derive vocabulary from one projected Product read during a rebuild, preserving configured and deterministic fallback ordering. Do not rediscover vocabulary on every choice. Descriptions, parts, media and unrelated properties stay outside it. Keep IDs/revisions as strings and value records explicit, avoiding arbitrary canonical strings as PHP dictionary keys.

Stored-type validation belongs at import/write boundaries, the existing-data audit and snapshot construction. The public request boundary still validates shape, allowed keys/values, collection bounds, authorization and Group scope; that is request validation, not another whole-Group data audit. The trusted server snapshot determines matching IDs and total. Never accept browser-provided Product IDs or totals as authority.

### Threshold, requests and lifecycle

- Fetch/display cards only at or below max_results, with explicit all retained.
- Debounce defaults to 0 and uses 100 ms steps; applies to cards only.
- Empty and above-threshold states need no card request; effective no-ops need no duplicate request.
- Superseded cards are hidden immediately; filter controls remain usable.
- Requests and insertions must match component instance, generation/request ID and revision.
- Stale responses must not change cards, properties notices, errors/loading or freshness confirmation.
- Preserve one automatic snapshot-refresh/card-retry cycle, then explicit retry.
- Preserve pagehide/pageshow, cached-page restoration, destroy cleanup, offline recovery and current-choice reconciliation.
- JSON promise rejection handling, locked Group identity, authorization and bounded request validation remain required.

Retain protected service injection in boot(), with no catalog queries in boot(), and explicit request arguments on the JSON method. The installed JSON handler invokes the method directly and catches exceptions: map expected missing/forbidden resources deliberately, report unexpected exceptions once, and handle rejected promises on the client. Keep stable option identities based on Group/property/canonical value, explicit wire:ignore boundaries, one bundled Alpine initialization, escaped labels, and no entanglement or wire:model.live filter state.

The local owner preserves versioned d[...] filters/preset/precedence, one history entry per effective action and replacement on normalization. Invalid precedence uses preset first, then ordinary fields in configured order before newest-first reconciliation. Back/Forward restores without another filter-state request or history entry. Bound malformed versions/shapes, preserve unrelated history/query state, and remove obsolete pagination parameters without repair loops.

### Snapshot, cache and freshness

- One replaceable plain-array snapshot and rebuild lock per Group, scoped by environment/database; schema and revision validated, 24-hour expiry.
- Lock waiting occurs outside transactions; recheck cache/revision after acquiring it, bound waits/retries and preserve lease ownership checks.
- Snapshot construction and card reads retain verified nonlocking REPEATABLE READ coherence. Finish reads before rendering or cache publication.
- A card cache miss exits the read transaction before rebuilding and retries once.
- Relevant committing writers advance revisions once per affected Group/batch, covering bulk paths, old/new Groups on moves, descendants and imports. No-op/dry-run/rollback does not advance.
- Preserve conditional authenticated GET/ETag and 304 checks with no Product read.
- Medium freshness uses one shared 60-second confirmation clock and in-flight request. Hidden tabs do not poll; card responses can confirm the current revision without a separate version request.
- Obsolete responses cannot mark newer data fresh; failed refreshes retain usable filters and retry.
- Initial failure is unavailable, not a false empty catalog. Deleted/branch Groups exit leaf filtering. Unsupported schemas require reload.
- No new snapshot service, push connection, delta data format or card-only row projection is needed.

The old vocabulary/type/reconciliation/compatibility SQL pipeline remains a historical/admin facility, not the public card path.

## 4. Group settings and persistence specification

Use flat keys under Group.result_settings to match the current implementation. Keep all existing settings, including card_properties, cards_per_row, max_results, products_debounce_ms and hidden pagination storage.

| New key | Input/storage type | Allowed values | Recommended missing-key default |
| --- | --- | --- | --- |
| card_only_differences | boolean | true / false | true |
| card_show_labels | boolean | true / false | false |
| card_property_layout | string enum | inline_start, inline_center, inline_space_between, above_start, above_center, below_start, below_center | inline_space_between |
| card_property_columns | integer | 1 / 2 | 2 |
| card_padding_block | integer pixels | 0–16, steps of 1 | 4 |
| card_padding_inline | integer pixels | 0–20, steps of 1 | 6 |

The first four are the selected main appearance controls. The two padding controls carry forward the earlier requirement as described in §2. Defaults are technical recommendations reflected by the selected preview; the user did not separately dictate every default.

- CatalogPolicy owns normalization/defaults and the settings shape.
- SaveGroupSettings owns server validation, canonical typing and persistence inside the current authorization/transaction/lock boundary.
- Reject unknown enum values, unknown setting keys, invalid booleans, nonintegral/out-of-range dimensions and malformed property lists.
- Accept valid native-form numeric strings at the write boundary and store integers. Do not use loose coercion in read normalization.
- A partial settings update must retain unsubmitted existing values and legacy pagination keys.
- Changing labels off must retain the selected layout. It must not reset the saved layout.
- Layout remains editable in the Group form while labels are off, with helper text saying it applies when labels are shown.
- Repeated unchanged saves do not increment catalog_revision. A combined details/settings save increments once through the existing coalescing boundary.
- Existing Groups obtain missing-key defaults without a data migration or forced mass save.
- Leaf Groups own these settings; no new ancestor inheritance is introduced.

Suggested form organization within the existing Group editor:

1. Product cards: existing Properties to display and Cards per row.
2. Appearance: Only differing properties; Show property labels; Label and value layout; Property columns.
3. Spacing: Block padding (px); Inline padding (px), if retained.
4. Product updates: existing threshold and update delay.

Replace the obsolete “Values appear inline, separated by commas” helper text. Explain that difference mode compares the complete matching set and that one match has no differing properties.

Example validation additions, alongside the existing allowlist and rules:

~~~php
'settings.result_settings.card_only_differences' => ['sometimes', 'required', 'boolean'],
'settings.result_settings.card_show_labels' => ['sometimes', 'required', 'boolean'],
'settings.result_settings.card_property_layout' => ['sometimes', 'required', Rule::in([
    'inline_start', 'inline_center', 'inline_space_between',
    'above_start', 'above_center', 'below_start', 'below_center',
])],
'settings.result_settings.card_property_columns' => ['sometimes', 'required', 'integer', Rule::in([1, 2])],
'settings.result_settings.card_padding_block' => ['sometimes', 'required', 'integer', 'between:0,16'],
'settings.result_settings.card_padding_inline' => ['sometimes', 'required', 'integer', 'between:0,20'],
~~~

Use Filament Toggle and Select controls with the existing catalog_settings.result_settings paths. Use native schema layout and the existing staged Save behavior. There is no separate appearance save button or visitor settings UI in this stage.

## 5. Exact difference semantics

Candidate fields come from resolved card_properties, in their existing stable order. An empty saved list resolves to configured Group filter keys. Do not replace the user's explicit property choice with every registered property.

Define a display cell as its original nonempty string; missing, null, empty string, numeric, boolean and structured cells become a missing sentinel for comparison. Do not stringify malformed cells, trim/lowercase read data, decode HTML again or normalize numeric-looking strings.

| Matching-set condition | Difference mode | All-properties mode |
| --- | --- | --- |
| More than one canonical value | Show the property in every card | Show |
| Same nonempty value across all matches | Hide | Show |
| Nonempty value versus missing | Show; missing card uses an em dash | Show; missing card uses an em dash |
| All rows missing/invalid for that property | Hide | Omit globally empty property |
| One matching Product | No differing fields; retain compact header/link and explain once in the results region | Show its populated configured fields |
| Zero Products | Existing empty results state; no cards | Same |
| Multiple Products with identical facts | No differing fields; retain each distinct Product/card and one results-region explanation | Show shared configured fields |

The globally empty-field and missing-slot policies are technical recommendations to preserve alignment while avoiding invented facts. They replace per-card omission only when doing so would shift another property's position. Filter vocabulary semantics stay unchanged.

Differences are based on **all matched Product rows** before splitting them into chunks. Two Products with identical combinations remain separate matches/cards. A preset-only constraint and ordinary choices both affect the comparison set through the existing trusted matcher.

Labels use the GroupFilter label from snapshot.fields when that key has a configured filter; otherwise reuse the current GroupForm convention, replacing underscores in the registered property key with spaces. Resolve this map once without a database lookup. Card values remain escaped canonical Product values. No custom value color/icon interpretation is added.

### Recommended computation boundary

Use the complete collection already fetched by CatalogCards after IDs/threshold are verified:

1. Leave the coherent read transaction with Products and the trusted snapshot.
2. Resolve candidate field descriptors and labels once.
3. Scan Products and those candidate keys once, tracking first normalized cell, whether any valid string exists, and whether any different cell exists.
4. Select one ordered field list for the complete response.
5. Pass it and normalized appearance settings into each Blade chunk.

Complexity is O(matches × configured card fields), with O(fields) comparison state. No distinct queries, counts, per-property SQL, per-card relations or extra browser work are needed. The currently loaded Product properties JSON is sufficient.

Do not expand the filter snapshot with card-only values to calculate these differences locally. Cards are already allowed to wait for the server, and such expansion would increase the initial payload and local filtering work for no interaction benefit.

## 6. Public card anatomy and filter presentation

### Card

- Keep each Product's existing ordered position: product_code, then ID.
- Retain an article wrapper if useful for semantics/tests; one native anchor fills the whole card. No nested link/button or separate “View product” interaction.
- Use the existing named Product route and normal same-tab navigation. Preserve browser open-in-new-tab, copy-link and keyboard behavior.
- Compact header: approximately 6 px block / 8 px inline padding; Product code about 12 px at six columns, with safe wrapping. Group subtitles about 10–11 px directly under the code, increasing only where width allows.
- Keep a small decorative link indicator in the heading. “No icons” refers to property icons; this navigation indicator is retained.
- Root Group subtitle is omitted when absent; use the real actual Group. Do not invent parent names or a Product photo.
- Neutral borders/surfaces support existing light/dark themes. Property backgrounds do not encode value categories.
- Body is a CSS grid of one or two property columns. Each value owns the full cell width; no dot, badge inset, comma separators or outer property-wrapper inline padding.
- Use 12 px values in the dense view, minimal padding and safe wrapping. Avoid ellipsis that hides distinctions.
- Keep a fixed field order/list across all cards in one response. Equal-height cells within each card and equal card heights within each result row; the header reserves consistent space.
- For an odd field count in two columns, the last cell spans both columns.
- Preserve focus outline around the link and sufficient text contrast.
- Render property names semantically using dt/dd; labels can be visually hidden with the project's screen-reader-only class.
- Values, labels, Product codes and Group names remain Blade-escaped. Only application-rendered HTML enters x-html.

Illustrative component structure:

~~~blade
<article class="h-full" data-product-card>
    <a href="{{ route('catalog.products.show', $product->id) }}"
       class="catalog-product-card"
       aria-labelledby="catalog-card-title-{{ $group->id }}-{{ $product->id }}"
       aria-describedby="catalog-card-facts-{{ $group->id }}-{{ $product->id }}">
        <header class="catalog-product-card__header">
            <h3 id="catalog-card-title-{{ $group->id }}-{{ $product->id }}">{{ $product->product_code }}</h3>
            {{-- Decorative navigation indicator; no property icon. --}}
            @if ($mainGroup)<p>{{ $mainGroup->name }}</p>@endif
            <p>{{ $group->name }}</p>
        </header>
        <dl id="catalog-card-facts-{{ $group->id }}-{{ $product->id }}"
            class="catalog-product-card__facts">
            @foreach ($fields as $field)
                <div class="catalog-product-card__fact" data-property-key="{{ $field['key'] }}">
                    <dt @class(['sr-only' => ! $appearance['card_show_labels']])>{{ $field['label'] }}</dt>
                    <dd>{{ is_string($product->properties[$field['key']] ?? null) && $product->properties[$field['key']] !== '' ? $product->properties[$field['key']] : '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </a>
</article>
~~~

Actual implementation should preserve the component attribute bag and use normalized appearance classes/data attributes/CSS variables. The snippet specifies semantics rather than every class.

CatalogCards currently constructs an in-memory Group with its name only. Supply its trusted groupId as well, or pass that ID explicitly into the view, so DOM description IDs are stable and correctly scoped without adding a Group query. Product code must remain a clear accessible link name; do not let every property value turn into an excessively long accessible name.

Use finite class mappings or data-attribute selectors; do not interpolate arbitrary admin strings into Tailwind classes. Bound integer padding can use generated CSS custom properties. Include all dynamic classes in source scanned by the production build.

With labels hidden, render values in a consistent start alignment. With labels visible, apply the stored layout: inline start/center/space-between, above start/center, or below start/center. Space-between does not pretend to distribute a stacked label/value pair.

### Responsive results grid

Retain cards_per_row 1–6 as an existing distinct setting. Property columns and cards per row are different controls.

Fit the requested maximum to actual available container width; do not force six unusably narrow cards merely because the viewport reached xl. Suggested minimum usable card width is approximately 180 px, verified against real codes and longest property values. Preserve the existing responsive layout if it meets that constraint; adjust finite breakpoint classes or CSS grid sizing if browser measurements show it does not.

For six cards per row, use the compact header and 12 px property text with two columns where feasible. On narrow cards/screens, wrapping must retain complete values. One property column remains an administrator option; no special “slim style” toggle is needed because the selected design is already compact.

### Filters

Preserve the current native fieldset/legend structure, outlined rounded buttons, blue selected state, typography and responsive 1/2/4/7-column pattern unless an actual fitting defect requires a scoped change. Do not copy another visual treatment from the comparison board.

Within each responsive filter row, align:
- legend/heading positions;
- first option/button positions;
- fieldset bottoms and overall height.

Handle wrapped legends and uneven option counts without clipping labels or shrinking hit targets. Start with CSS grid/stretch and a consistent legend track. Verify native fieldset/legend behavior in the real browser. If CSS alone cannot align varying legend heights, use one bounded layout pass after initialization, metadata replacement and container-width change; batch reads before writes, never measure every click, and avoid ResizeObserver height loops.

Keep the current min-height 32 px buttons and keyboard focus behavior. Do not introduce new empty controls, uniform option counts, hidden labels or a different filter state owner just to align the layout.

## 7. Code ownership and interfaces

| File | Change/responsibility |
| --- | --- |
| app/Services/CatalogPolicy.php | Typed defaults, normalization and finite appearance choices |
| app/Actions/SaveGroupSettings.php | Extend strict allowed settings/rules, canonical casts and existing revision-aware save |
| app/Filament/Resources/Groups/Schemas/GroupForm.php | Existing property/card settings plus appearance/spacing controls and corrected helper text |
| app/Services/BuildCatalogSnapshot.php | Include normalized appearance metadata; do not add card-only row values |
| app/DTO/CatalogSnapshot.php | Keep schema 1 for additive optional settings; revisit only if representation actually changes |
| app/Services/CatalogCards.php | Trusted all-match field calculation and shared view preparation after reads |
| app/Services/CatalogCardDisplay.php | New focused query-free comparison returning ordered fields and a notice reason |
| resources/views/components/catalog/product-card.blade.php | Compact whole-card link and semantically distinct facts |
| resources/views/components/catalog/card-chunk.blade.php | Pass shared fields/appearance once to every card |
| resources/views/livewire/catalog/group-show.blade.php | Scoped result notice and any necessary filter/grid geometry changes |
| resources/js/catalog-filters.js | Guard/reset the result property notice with existing identities; preserve transport/scheduling |
| resources/css/app.css | Scoped card/grid/filter presentation; preserve unrelated shell/theme work |
| tests/Feature/Catalog/GroupSettingsTest.php | Persistence, validation, partial updates and unchanged saves |
| tests/Feature/Catalog/CatalogSnapshotTest.php | Settings/revisions, cache compatibility, query budgets and JSON response |
| tests/Feature/Catalog/PublicCatalogTest.php | Whole-card navigation, exact values, escaping, field order and result scope |
| tests/Feature/Filament/CatalogAdministrationTest.php | Actual Group form save/reload and no dropped hidden layout values |
| tests/Unit/CatalogFilterTransport.test.js | Superseded property notice alongside response/insertion guards |
| docs/catalog/contracts/PUBLIC_CATALOG.md; FILAMENT_ADMIN.md; docs/README.md | Update governing presentation contract and link this plan during implementation |

Do not create a second action, route, Alpine component or standalone settings page.

Proposed service interface:

~~~php
/**
 * @param iterable<Product> $products
 * @param list<string> $propertyKeys
 * @param array<string, string> $propertyLabels
 * @return array{fields: list<array{key: string, label: string}>, noticeReason: 'single_match'|'shared_properties'|'no_populated_properties'|null}
 */
public function compare(
    iterable $products,
    array $propertyKeys,
    array $propertyLabels,
    bool $onlyDifferences,
): array;
~~~

Inject CatalogCardDisplay into CatalogCards through its existing constructor pattern. GroupShow's protected boot-injected CatalogCards dependency and JSON method arguments stay as they are.

Add a small response field: propertiesNotice, a string or null. Translate the helper's noticeReason in CatalogCards. Use a single result-region message when there are eligible cards but no fields to show. It must not be announced once per card.

- Single match: “Only one product matches, so there are no differing properties to show.”
- Multiple identical matches: “These products share the configured properties.”
- No valid configured fields: “No populated card properties are configured for these products.”
- Other response states: null.

The helper records row count and whether any candidate property is populated during the same comparison scan. If fields are visible or no rows exist, its reason is null. Otherwise, no populated candidates takes precedence and yields no_populated_properties in either mode. With populated candidates hidden by difference mode, return single_match for one row or shared_properties for multiple rows. Never show a “no differing properties” explanation merely because all-properties mode has no usable data.

Use the current translation conventions. In Alpine, call the observable property cardPropertiesNotice, initialize/reset it to null on criteria changes/retries/exit, and only set it from a current valid response. Display it with x-text after accepted cards become visible.

### Additive cache compatibility

Adding appearance settings does not alter integer-coded rows or URL version. Keep snapshot schema 1 and normalize snapshot.settings through CatalogPolicy before rendering, so an older cached snapshot receives the same safe defaults.

BuildCatalogSnapshot includes the new keys on subsequent builds. A real appearance save increments the existing Group revision and refreshes that Group normally. No blanket cache flush, schema mismatch reload or all-Groups revision bump is justified just for new optional defaults.

Test an old-shaped cached snapshot containing none of the new keys. If execution reveals an actual incompatible data-shape change, use a documented schema bump across PHP/JS/refresh handling and fixtures, preserving unsupported-schema reload behavior. Do not conflate snapshot schema and discovery URL version.

## 8. Task-by-task implementation

### Task 1 — Pin the current baseline and settings behavior

**Files:** Read all files in §7, their current diffs, relevant contracts and existing tests. Modify only the planned settings tests/policy/action/form when beginning implementation.

**Consumes:** Existing Group settings input and aggregate Save behavior.
**Produces:** Typed appearance settings persisted in result_settings and returned by CatalogPolicy.

- [ ] Read AGENTS.md, docs/README.md and matching .ai/rules if present; search relevant keywords. Confirm installed versions through Boost/Composer and current asset versions.
- [ ] Record baseline statuses for the two historical label tests and existing focused catalog/admin/Node checks. Run behind current SQLite/MySQL guards only.
- [ ] Add settings behavior checks for missing-key defaults, round-trip typed values, valid numeric strings, partial updates, invalid input rollback, unchanged save and one revision for a combined save.
- [ ] Extend CatalogPolicy, SaveGroupSettings and GroupForm together. Update the strict allowed-key list as well as individual validation rules.
- [ ] Rerun GroupSettingsTest and the relevant Filament tests. Confirm labels-off does not lose the stored layout.

Example regression to adapt to existing test conventions:

~~~php
test('appearance survives a partial update without advancing an unchanged revision', function () {
    $actor = User::factory()->create(['email' => 'ycm@data4.work']);
    $group = Group::factory()->create();
    app(SaveGroupSettings::class)->handle($actor, $group, groupSettingsInput([
        'result_settings' => [
            'card_only_differences' => false,
            'card_show_labels' => true,
            'card_property_layout' => 'below_center',
            'card_property_columns' => '1',
        ],
    ]));
    app(SaveGroupSettings::class)->handle($actor, $group, groupSettingsInput([
        'result_settings' => ['products_debounce_ms' => 300],
    ]));
    $revision = (string) $group->fresh()->catalog_revision;

    app(SaveGroupSettings::class)->handle($actor, $group, groupSettingsInput([
        'result_settings' => ['products_debounce_ms' => 300],
    ]));

    expect($group->fresh()->result_settings)->toMatchArray([
        'card_only_differences' => false, 'card_show_labels' => true,
        'card_property_layout' => 'below_center', 'card_property_columns' => 1,
    ]);
    expect((string) $group->fresh()->catalog_revision)->toBe($revision);
});
~~~

**Exit:** Settings persist/reload through the real Group editor; invalid submissions leave the whole draft unchanged.

### Task 2 — Compute one trusted display field list

**Files:** Create app/Services/CatalogCardDisplay.php using Artisan; modify CatalogCards.php and BuildCatalogSnapshot.php; extend PublicCatalogTest/CatalogSnapshotTest. Use existing test files unless a dedicated pure comparison file is clearer.

**Consumes:** Complete loaded Product collection, ordered configured property keys, shared property labels and normalized appearance.
**Produces:** compare()'s ordered field descriptors and noticeReason, translated into propertiesNotice by CatalogCards.

- [ ] Add an independent 25-Product fixture where Model varies only in Product 25. Assert the Model field appears in the first 24-card chunk too.
- [ ] Add exact string/missing-value cases and comparison across preset-filtered matching identities. Expected values must be handwritten, not calculated with the helper under test.
- [ ] Implement one query-free field scan using strict normalized-cell comparison. Preserve duplicates as separate Products.
- [ ] Resolve labels once from snapshot field metadata/fallback property labels and pass shared view data after the transaction ends.
- [ ] Normalize old cached settings and include new metadata on rebuilt snapshots.
- [ ] Assert warm cards still have one scoped Product SELECT, no SQL counts and no per-option/per-card query growth.

Core comparison logic:

~~~php
$first = [];
$populated = [];
$different = [];
foreach ($products as $product) {
    foreach ($propertyKeys as $key) {
        $raw = $product->properties[$key] ?? null;
        $cell = is_string($raw) && $raw !== '' ? $raw : null;
        $populated[$key] = ($populated[$key] ?? false) || $cell !== null;
        if (! array_key_exists($key, $first)) {
            $first[$key] = $cell;
        } elseif ($first[$key] !== $cell) {
            $different[$key] = true;
        }
    }
}
$visibleKeys = array_values(array_filter(
    $propertyKeys,
    fn (string $key): bool => ($populated[$key] ?? false)
        && (! $onlyDifferences || ($different[$key] ?? false)),
));
~~~

Use the §7 compare interface to return labels with those keys and a noticeReason from the same scan. Track row count and any populated candidate; do not make another Product query or rescan for the notice. Do not use truthiness or numeric coercion for values.

**Exit:** Differences cover the complete matching set before chunking; card-only fields work without becoming ordinary filters or browser dataset columns.

### Task 3 — Render compact accessible whole-card links

**Files:** product-card.blade.php, card-chunk.blade.php, app.css; PublicCatalogTest.php.

**Consumes:** Task 2 fields and Task 1 appearance.
**Produces:** Escaped compact card HTML with a consistent grid and one Product link.

- [ ] Update existing behavioral card tests to assert native whole-card navigation, code/Group text, ordered individual facts, accessible names and escaped malicious content.
- [ ] Replace the comma paragraph with dt/dd grid cells and a full-card anchor; keep article identity and data-product-id wrapper.
- [ ] Implement the finite layout mappings, light/dark styling, bounded padding variables, equal-height rows and full-width last item.
- [ ] Check long values, missing slots, literal "0", leading zeros, Unicode and labels at one/two property columns.
- [ ] Build production assets and inspect real browser geometry at 1–6 cards per row; no styling-only snapshot tests.

**Exit:** Each card can be scanned and activated anywhere without hiding distinctions, nested interactions or horizontal overflow.

### Task 4 — Integrate notices and align the existing filter layout

**Files:** group-show.blade.php, catalog-filters.js, app.css; CatalogFilterTransport.test.js; relevant existing engine tests remain unchanged unless a genuine regression is found.

**Consumes:** Existing JSON response identity and optional propertiesNotice.
**Produces:** A result-scoped explanation tied to current cards, plus aligned current-style filters.

- [ ] Extend the Node transport regression to delay response A, publish criteria B, then resolve A. Assert A cannot change cardPropertiesNotice, cardChunks, status or freshness.
- [ ] Reset the notice during publish, retry and exit; apply it only after existing request/revision checks.
- [ ] Render the one notice with escaped x-text when accepted cards are visible; no per-card live regions.
- [ ] Preserve existing DOM ownership and captured root; do not use child $el for transport identity.
- [ ] Align legends/first buttons/field heights within responsive rows using CSS first. Add bounded initialization/resize measurement only if real browser evidence requires it.
- [ ] Verify presets hiding the focused field still move focus deliberately and removal notices remain visible.
- [ ] Exercise Back/Forward, Reset, threshold exit, partial insertion and snapshot replacement with delayed responses.

**Exit:** Cards and their explanation always represent the current criteria; filter presentation remains familiar and responsive.

### Task 5 — Verify efficiency and resolve measured all-card bottlenecks

**Files:** Existing scoped CSS/transport/service files only when measurements justify changes; relevant regression tests for any logic correction.

**Consumes:** Integrated UI with real assets and a recorded baseline.
**Produces:** Local and post-release browser/server evidence, and narrowly justified fixes.

- [ ] Record at least 100 real interactions on the fixed 501-Product reference corpus, including bursts and all results. Do not produce only a threshold-1 success claim.
- [ ] Separate local filter computation, DOM update, paint estimates, card HTML parsing, layout, chunk insertion/retirement and server/query time.
- [ ] Repeat labels off/on, one/two property columns and 1/3/6 cards per row. Include padding extremes if controls are retained.
- [ ] If all-card p95 exceeds 100 ms, inspect main-thread traces and the exact frame causing delay. Try smaller chunks or a per-frame insertion budget only when parsing/layout is responsible; verify cancellation and eventual completeness.
- [ ] Preserve existing content-visibility/hidden-chunk behavior unless measurements establish a regression or a better intrinsic estimate for shorter cards.
- [ ] Do not introduce pagination, islands, browser-side full Product data, a worker or virtualization as an automatic fix. Explain any unresolved bottleneck and the smallest additional proposal.
- [ ] Rerun any newly affected checks once; avoid repetitive broad suites after unchanged results.

**Exit:** No unexplained application/Livewire/Alpine error; query budgets preserved; card improvements have measured evidence and residual limitations are reported explicitly.

### Task 6 — Update contracts and release review

**Files:** This plan's evidence checklist, PUBLIC_CATALOG.md, FILAMENT_ADMIN.md and the existing docs/README.md.

- [ ] Update the governing presentation sections so old comma-only/label-free/card-link guidance cannot override these settings. Preserve historical rationale separately.
- [ ] Record verified commands, fixtures, browser/device, current revision/settings and comparison numbers.
- [ ] Run required formatting/build and the focused checks below; review the explicit diff allowlist against concurrent work.
- [ ] Provide a concrete implementation review before publication. Planning this work does not itself authorize a new commit/push/deployment.
- [ ] When authorized, use the existing deployment workflow and then repeat comparable remote scenarios. Do not trigger a second manual release on top of deploy-on-push.
- [ ] Use portable server commands: confirm command availability instead of assuming rg exists, and inspect Artisan --help instead of using the unsupported --columns option from the historical Forge attempts.
- [ ] Warm affected Group snapshots as needed; after out-of-band data changes or restores, use targeted catalog invalidation and preserve the existing restore/revision safeguards.

**Exit:** Current contracts, actual application behavior and deployment evidence agree; unverified cases remain named.

**Execution status, 2026-10-07:** Tasks 1–4 are implemented locally. Task 5 has focused regression and direct-browser evidence below, with remaining cases explicitly listed. Task 6's contracts and self-review are complete; publication and the post-release remote comparison remain pending. The original unchecked task lists remain the full acceptance reference, not a claim that every browser scenario or release step has been executed.

## 9. Verification matrix and acceptance

### Meaningful existing checks

Use existing PHP/Node tools, real database records in guarded test environments and independent expected outputs.

~~~bash
php artisan test --compact tests/Feature/Catalog/GroupSettingsTest.php
php artisan test --compact tests/Feature/Catalog/CatalogSnapshotTest.php
php artisan test --compact tests/Feature/Catalog/PublicCatalogTest.php
php artisan test --compact tests/Feature/Filament/CatalogAdministrationTest.php
php artisan test --compact tests/Feature/Filament/ContextSettingsTest.php
php artisan test --compact tests/Feature/Filament/TableConfigurationStandardsTest.php
node --test tests/Unit/CatalogFilterEngine.test.js tests/Unit/CatalogFilterTransport.test.js
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
~~~

Default PHPUnit uses SQLite :memory:. Any MySQL execution must preserve tests/bootstrap-mysql.php's exact isolated configurator_catalog_test/host/port/user allowlist. Do not fall back to configurator_catalog_dev or the remote database. Persistent-cache tests need unique stores; committed concurrency fixtures need the existing two-connection isolation approach. Leave the shared test base's Vite behavior unchanged; browser checks use real application assets. Ask the user to run the complete suite after focused feature checks pass, according to the project instructions.

Do not delete distinct tests, replace a user-facing title to appease an obsolete assertion, or report tests passing based on source inspection.

| Area | Required proof |
| --- | --- |
| Settings | Save/reopen every control; typed values; absent defaults; partial update; hidden layout retained; invalid full draft rolls back |
| Revisions | Changed appearance increments once; no-op/rollback does not; old-shaped cached snapshot renders safe defaults |
| Difference scope | Card-only properties; Product 25 variation shown in chunk 1; same vs different vs missing; equal rows counted separately |
| Constraints | Ordinary selection/preset change alters differences over exact matching IDs; multivalue presets retain variation |
| Empty/single/identical | Zero produces empty state; one and identical matches keep links with one explanation; all mode restores shared facts |
| Labels/layout | Off/on; inline/above/below; start/center/space-between; one/two columns; odd/even field counts |
| Strings/security | "0", "01", case, quotes, Hebrew/Unicode, markup, missing/null/empty/malformed cells; values remain escaped |
| Headers/navigation | Real Group metadata; compact headers; whole-card Enter/click/new-tab; no nested links; stable accessible names |
| Filter appearance | Legends and first buttons align; full labels; current button type/states; equal field bottoms; no new hidden criteria |
| Responsive/theme | Narrow 390 px view, intermediate container widths, 1–6 cards per row, light/dark, zoom and long content |
| Races | Delayed A followed by B, Reset, Back, threshold exit, refresh and partial insertion; obsolete notice/error/cards cannot win |
| Freshness | 304, changed revision/settings, focus+timer single request, hidden tab, offline retry, unsupported schema, deleted/branch Group |
| Lifecycle | Navigation away/back, native cached-page restoration, destruction cleanup, session expiry and child-button retries |
| Counts/requests | Exact total only; zero filter-state requests/SQL; no numeric option-count queries or repeated dataset-bearing state |
| All results | Every eligible Product eventually appears; distinct exact IDs; no pagination; user can keep filtering during insertion |

Keep the independent A/B/C precedence fixture from the accepted plan:
A = 25/Flange/2, B = 16/Flange/1, C = 25/Threaded/1.
25 → Flange → 1 yields B; Flange → 25 → 1 yields C. Assert the actual removed choice as well as final IDs.

### Query/request budgets

| Operation | Budget retained |
| --- | --- |
| Filter-state change | Zero filter-state HTTP/action calls and zero SQL |
| Unchanged freshness GET | Revision lookup; zero Product reads/rebuilds |
| Warm eligible cards | Revision lookup + one scoped Product SELECT; zero SQL counts |
| Difference calculation | Zero extra SELECTs, zero extra requests; one bounded in-memory field scan |
| Snapshot rebuild | One projected Product read plus bounded metadata reads; no card-only values added to rows |

Authentication/session/cache and transaction protocol work are measured separately. Count HTTP requests and action executions: bundling requests is not proof of less server work.

### Performance acceptance

Recommended recorded-device criterion: **p95 filter feedback below 100 ms**, with below 50 ms preferred. Report computation and DOM update separately; label double-animation-frame timings as paint estimates, not actual paint measurements. Report outliers and the all-card result separately rather than hiding them in an aggregate.

Record raw/gzip snapshot and card-response bytes, SQL count/time, service elapsed time, peak practical memory if available, all-card insertion duration and main-thread long tasks. Compare before/after on the same corpus, settings, browser/device and viewport. Card latency may exceed filter feedback, but must not block controls or apply stale results.

A single server snapshot of low load/available memory/no FPM backlog does not establish historical saturation. Existing logs/metrics can provide history if they contain timestamped request latency, queue/backlog or resource information. Their absence is an evidence gap, not a reason to create a new monitoring service in this UI task.

### Deferred browser automation guidance retained

Pest browser construction is optional future work, not a dependency of these improvements. Previously inspected Pest browser 5.0.1 / Playwright 1.62.1 must be revalidated before installation. Real assets must be enabled for future browser tests; PHP time travel does not control browser timers. Use verified network/timer APIs, preserve real success responses, capture console errors and unhandled rejections, begin serially and retain isolated-database guards.

## 10. Future ideas and alternatives preserved without implementation

- A dedicated Product property settings page could later own labels, icons and colors. First clarify whether color describes a property or a canonical value: the earlier color-row sketch used value distinctions, while the later idea describes attaching metadata to properties.
- Property icons, icon-before/after-label order, inline icon positioning and icon/value alignment remain in the earlier exploration. Their controls and storage are deferred with the icon feature.
- Value colors, badges, dots and multiple visual interpretations are not current appearance modes.
- A separate hide-selected toggle is superseded by the selected only-differences control; shared unselected properties are hidden too.
- Livewire islands remain an evidence-driven later option only if a remaining bottleneck would benefit.
- Slim server-computed filter responses remain an alternative, not a second implementation beside selected local computation.
- Full browser-side Product cards/data were declined; server cards remain independent.
- Physical columns/generated indexes remain a separate measured database question; current card differences require no new SQL predicates.
- A different final-item arrangement can be reconsidered if the chosen full-width orphan row fails a real layout requirement.
- Browser-test infrastructure and new monitoring infrastructure remain separate tasks.
- Pagination and force-hide behavior stay inactive/removed from this flow.

## 11. Self-audit and completion checklist

This is a proportional implementation-design review using current source, the user's decisions and applicable skills; it is not a new complete usability study.

- [ ] Every selected behavior in §2 is implemented or explicitly retained as an existing contract.
- [ ] The padding packaging decision is reflected consistently in settings, validation, styles and tests.
- [ ] Differing properties use all matches and card_properties, not a filter-selected-key shortcut or only the first chunk.
- [ ] Missing-cell policy preserves positions without creating fake filter values.
- [ ] One whole-card link and escaped dt/dd facts work with hidden labels.
- [ ] Settings normalization, allowed-key validation, Form state, snapshot metadata and view props agree.
- [ ] Stale property notices obey the same identities as cards and cannot confirm freshness.
- [ ] Plain-array caching, transaction/lock boundaries, lease checks and write-owned revisions remain intact.
- [ ] No second Alpine, entanglement, wire:model.live filter state, public Product dataset or CatalogDiscovery::prepare card call has appeared.
- [ ] No per-option counts, pagination, force-hide, property color/icon controls, speculative settings page or islands were introduced.
- [ ] Existing independent tests remain; historical label failures were checked on current code.
- [ ] Real local and remote evidence is distinguished; large-all and lifecycle gaps are named.
- [ ] Required formatting/build and focused regression checks were run after implementation.
- [ ] Only authorized reviewed files are staged/published; unrelated dirty work is preserved.

## 12. Implementation kickoff prompt

Use the [complete development kickoff prompt](CARD_APPEARANCE_KICKOFF_PROMPT.md) together with this entire plan. It expands the skills guidance, task boundaries, verification and handoff instructions. The compact reminder below is retained for reference; it does not replace the full specification or complete prompt.

~~~text
Implement docs/catalog/CARD_APPEARANCE_IMPLEMENTATION_PLAN.md.

Read AGENTS.md, docs/README.md, the plan's complete specification and current
public/admin contracts. Inspect .ai/rules/index.md and applicable rules if
present, and search catalog/filter/card/cache/appearance constraints.
Read Git status and the current diffs; preserve unrelated concurrent work.

Activate UX Design Thinking for proportional scope/contract orientation,
Laravel best practices and its relevant rule files, Livewire development,
Alpine.js, Filament development, Tailwind development and testing guidance.
Verify installed versions through Laravel Boost and Composer/package files.
Use version-matched Boost docs for APIs; reuse sufficient existing research.

Implement the selected option 1 as neutral full-width property cells.
Persist the selected Group settings through CatalogPolicy, SaveGroupSettings
and the existing Group editor. Respect the padding decision in section 2.
Preserve existing card property selection, cards-per-row, threshold, debounce,
legacy pagination storage, aggregate save authorization and revision rules.

Calculate differing card fields from every matching Product already loaded
by CatalogCards, before chunking, outside the completed read transaction.
No extra SQL/counts, no card-only browser row data, no additional infrastructure
service. The focused query-free PHP display helper is within scope.
Use the focused query-free display helper and section 5's exact string,
missing-value and one-result behavior.

Use compact whole-card native links, escaped ordered facts, accessible
property names and the specified one/two-column label/value layouts.
Match the current filter fieldset/legend/button design and align its tracks
without adding per-click layout work.

Keep GroupShow class-based and protected boot injection. Keep the existing
JSON action, Alpine owner, wire:ignore boundaries, captured root, small
reactive state and between-frame chunk scheduling. Treat the new properties
notice like card data: reset it with criteria and reject stale responses.
Do not import/start Alpine again or add entanglement or live filter models.

Do not implement property colors/icons, a properties settings page,
islands, pagination, numeric option counts or force-hide. Do not rework
navigation, the database schema, Product JSON storage or dependency setup.

Use meaningful existing PHP/Node regressions and direct real-browser clicks
with DevTools. Defer Pest browser infrastructure. Verify the complete matrix,
especially Product 25 differences in chunk 1, missing slots, rapid clicks,
Back/Reset during delayed cards, refresh/settings changes and all 501 cards.
Measure filter feedback separately from card parsing/layout and network/SQL.
Aim below 50 ms; report the practical p95 below-100-ms criterion and
all-card outliers honestly. Count action executions as well as HTTP requests.

Complete a Laravel/Livewire/Alpine/UX review against the plan, update the
governing contracts, and run focused tests, Pint, production build and diff
checks. Record current results, unresolved defects and unverified cases.
Provide a concrete review before publication; commit/push/release only under
the applicable user authorization. Repeat comparable remote measurements
after an authorized release, using normal catalog interactions.
~~~

## 13. Implementation evidence — 2026-10-07

### Delivered locally

The selected neutral-cell design is implemented through the existing Group settings boundary, compact snapshot metadata, a query-free `CatalogCardDisplay` helper, server Blade chunks and the existing Alpine card transport. Difference fields are calculated over all eligible matching Products before the first 24-card chunk. No browser Product dataset, schema migration, new endpoint, dependency, worker or additional filtering owner was added.

Cards have one native link, compact code/Group headers, escaped accessible `dt`/`dd` facts, one/two property columns, the seven finite label layouts, controlled padding and a full-width orphan cell. Card-grid columns adapt to available width. Current filter controls retain their styling and states. A shared heading height aligns wrapped labels and first buttons: CSS falls back to 40 px, then a private measurement establishes the required height with a 20 px floor at initialization, font readiness, metadata replacement, width changes and page restoration. Hidden preset fields are measured without showing them. Ordinary clicks perform no heading measurements.

Current-card notices clear on criteria, retry, suspension, destruction and Group exit. The transport regression proves an obsolete response cannot replace cards, notice or status, or clear a newer freshness error. The cache/revision/read-transaction/lock boundaries and protected Livewire JSON action remain intact. Old cached settings receive safe defaults with no rebuild.

Changes are bounded to the settings action/form/policy, snapshot metadata, card service/helper, card views, filter transport/view, scoped CSS, meaningful existing test files and these governing documents. Existing navigation/header/shell changes in shared files were preserved. No staging, commit, push or deployment occurred in this execution. The distinct final review was a self-review, without separately authorized delegation; PhpStorm inspection tools were not exposed in this session.

### Focused checks

- Baseline: 87 PHP tests / 720 assertions and 8 Node checks passed. The two historical admin-label failures did not reproduce; correct UI labels were not renamed to appease assertions.
- Final integrated checks: 112 PHP tests / 873 assertions passed across GroupSettings, CatalogSnapshot, PublicCatalog, CatalogAdministration, ContextSettings and TableConfigurationStandards. Default SQLite `:memory:` isolation was used.
- After Pint: the affected CatalogSnapshot/PublicCatalog checks passed again: 38 tests / 210 assertions. Formatting touched only the new helper and the scoped snapshot test; copies of all dirty PHP files confirmed unrelated files were unchanged by Pint.
- Latest engine/transport checks: 9 Node tests passed, including independent A/B/C precedence identities, exact strings, URL behavior, stale notices/freshness, retry, Reset and suspension/resumption.
- `vendor/bin/pint --dirty --format agent`, `npm run build` and `git diff --check` passed. The built main JS bundle is 15.77 kB / 5.83 kB gzip. No Pest browser/Vite test infrastructure was installed or changed.

Coverage includes typed partial settings updates, invalid full-draft rollback, no-op revisions, real Group-editor save/reload, Product 25 affecting the first chunk, card-only properties, missing cells, duplicate combinations, exact strings/escaping, exact filtered/preset Product identities and old snapshot defaults. A dedicated regression proves saving implicit appearance defaults does not advance a legacy Group's revision. Existing distinct behavioral checks remain.

### Real-browser evidence

Recorded environment: Chrome 154 on macOS, 1470 × 685 CSS pixels, device pixel ratio 2. Hardware model and independent CPU/network benchmarks were not recorded. The local and remote reference catalogs both contain 501 Products, but their presets/settings differ; the remote measurements are a current baseline, not a controlled before/after release comparison.

The local sequence repeated pressure, connection, size, incompatible replacements, automatic type, valve type and Reset across 100 clicks. Configurations included 6 cards/two property columns/labels off, 3 cards/one property column/labels above, and 1 card/two property columns/labels off with a 300 ms card delay. There were 103 feedback samples including normalization/freshness publishes in addition to those 100 clicks.

| Metric | Local p50 | Local p95 | Local maximum | Remote baseline p95 |
| --- | ---: | ---: | ---: | ---: |
| Filter computation | 0.9 ms | 1.4 ms | 36.2 ms | 1.8 ms |
| DOM update | 13.5 ms | 15.1 ms | 42.6 ms | 14.3 ms |
| Double-frame paint estimate | 46.4 ms | 48.4 ms | 59.4 ms | 47.0 ms |

These paint estimates are not actual presentation timestamps. Remote maximum paint estimate was 99.5 ms. Remote instrumentation initially had two listeners; the 200 entries were sampled once per event for the 100-click baseline. Normal local/remote instrumentation reported no console/unhandled-rejection errors.

After the long-heading correction, two further 100-click local runs used six cards, two property columns, labels off, `all` and zero debounce. The final run measured computation p95 1.6 ms, DOM-update p95 15.5 ms and double-frame paint-estimate p95 47.9 ms (maximum 55.4 ms). Instrumentation observed zero heading measurements over those 200 clicks and no application errors. The preceding run's paint-estimate p95 was 48.0 ms. These runs establish that heading alignment adds no per-click layout-read work.

All 501 eligible local cards completed with 501 distinct DOM Product IDs. Six initial cards shared a 161 px height and 195 px width. During large card work, filter p95 stayed below the preferred 50 ms estimate and the practical 100 ms criterion. Card insertion produced main-thread tasks above 50 ms, with a recorded 126 ms initial-load task and a 111 ms later task. These remain card-delivery costs; a complete trace splitting HTML parsing from style/layout was not captured. No additional architecture was introduced to hide that cost.

The initial mixed-settings 100-click interval submitted 69 card HTTP requests and 69 `loadCards` action calls, including revision retries and the effect of the optional 300 ms delay. Four freshness responses included two `304` and two replacements. No filter-state action was submitted. The final zero-delay/all-results interval captured events every 20 clicks to avoid debugger buffer loss: 100 card HTTP requests, 100 `loadCards` calls and 100 successful HTTP 200 responses, with zero filter-state or dataset requests. This counts action calls sent and completed responses; an independent server execution trace was not added.

For the final 501-card response, Resource Timing recorded 1,708,140 decoded body bytes, 37,106 encoded body bytes, a 37,406-byte transfer estimate including browser-estimated headers, and a 118.2 ms network duration. These are client network measurements including request overhead, not isolated PHP/SQL time. The earlier response measured approximately 99.6 ms locally. A replacement compact dataset contained 501 rows and approximately 14.4 kB of decoded JSON.

Additional direct checks passed: every 1–6 card maximum, one/two property columns, labels off/above/below, zero/default spacing, a full-width final item, dark styling, 390 px and 900 px layouts without horizontal overflow, and aligned first buttons/bottoms in each responsive filter row. Native card Enter navigation and Back restored the current selection. A singleton hid its field; a multivalue preset retained a compatible manual pressure, and a newer pressure visibly named the evicted preset. A single match remained a link with one shared result notice and no invented facts.

The initial fixed heading minimum failed an extended-label check: a four-line heading displaced its first button by 40 px. The shared measurement corrected this. A 54-character label was then saved through the actual local editor; all seven headings measured 80 px and their first buttons aligned. At 900 px, the shared height adapted to 60 px. The original `Discharge Outlet Type` label was restored afterward.

Deliberately blocking the card endpoint produced a results-only retry message while filters remained usable; unblocking and retry recovered. With 750 ms emulated network latency, rapid replacements, Back and Reset ended at 501 distinct cards with no selected choices or stale notice. Temporary network/viewport/DOM instrumentation was removed or lost with closed verification tabs. Screenshots were visually inspected through the browser tool; its download export did not supply a saved file.

The local D060 settings were restored to their original threshold 6, cards-per-row 6 and delay 0; appearance defaults are now explicitly stored. Existing selected card properties and legacy pagination settings remained intact. Final local revision was 21. No production records or settings were modified and no production fixtures were created.

### Query budget and final review

The warm-card regression observes exactly two catalog queries: revision lookup and one scoped Product read. This also passes with old cached metadata; no rebuild, relationship query or SQL count is added. All-match comparison uses O(matches × configured fields) work and O(fields) state. Snapshot rows still exclude card-only values. Authentication/session/cache work, actual MySQL SQL time, pure service elapsed time and peak memory were not independently profiled in this execution.

The final Laravel/Livewire/Alpine/Filament/UX review checked strict canonical settings, aggregate authorization/rollback/revisions, cache shapes, transaction completion before comparison/rendering, bounded style inputs, escaped labels/values, native link semantics, stable Product/option/chunk identities, public-state size and stale-response/lifecycle guards. The discovered long-heading defect was corrected and verified; no unresolved implementation defect was identified in the completed checks. The only shared-file changes from this task are the card/filter styles and bindings; prior shell/header edits remain separate work.

Remaining evidence gaps: the new appearance is not released or verified remotely; session-expiry/offline recovery, simultaneous focus/timer deduplication, hidden-tab timing, unsupported-schema reload, deleted/branch Group exit, and forced focus relocation were not all exercised in a real browser. Their existing mechanisms were preserved; PHP/Node coverage and source review must not be called equivalent to those browser cases. Padding maxima, maximum-length arbitrary headings, zoom and every label layout were not exhaustively visually tested; the actual four-line/54-character heading case passed. Full all-card parsing/layout traces, exact insertion duration, peak memory and remote server/SQL timings remain unmeasured. The full application suite is left for the user to run with `php artisan test --compact` as required by AGENTS.md.

Publication remains a separate review step under the kickoff's explicit scope. After an authorized release, repeat the remote comparison on equivalent settings and record server/action/SQL evidence. Preserve deploy-on-push, portable Forge commands and targeted snapshot handling; no extra release/import/database change is needed for this appearance patch.
