# Catalog card appearance — development kickoff prompt

Copy the prompt below into the development session. Supply the complete linked implementation plan; this prompt guides execution and does not replace its specifications. Writing this prompt has not started application implementation.

```text
Implement the complete plan at:
/Users/studioycm/Herd/configurator/docs/catalog/CARD_APPEARANCE_IMPLEMENTATION_PLAN.md

Work in /Users/studioycm/Herd/configurator. Carry the implementation through
focused tests, the production asset build, real-browser verification and a
final review. Do not stop at analysis or a proposed approach. This is a
bounded improvement to the existing catalog, not a new catalog implementation.

1. Ground the work before editing

Read the latest AGENTS.md, docs/README.md, the entire supplied plan, and the
relevant PUBLIC_CATALOG.md and FILAMENT_ADMIN.md contracts. If .ai/rules
exists, read its index, all applicable path rules, and search related
catalog/filter/card/cache/appearance/Livewire/Alpine rules before editing.

The user's latest decisions and this appearance plan govern this task.
Older plans and evidence logs are provenance, not instructions to restart
completed filter work or revive canceled features. Distinguish source facts,
historical measurements, user reports and fresh verification.

Inspect Git status and the current diffs of affected files. Preserve unrelated
work, including the shared shell, navigation, admin styling and package
changes. Never reset or stash the workspace, overwrite concurrent changes,
or stage a broad collection of files. Do not execute the independent admin
or navigation plans as part of this task.

Confirm installed PHP package versions using composer show --direct or a
targeted composer show command and Laravel Boost application information.
Check package.json and the lockfile for frontend versions. Use Boost
search-docs for version-sensitive APIs, scoped to the relevant packages;
reuse sufficient current findings rather than repeating completed research.

Activate the installed Laravel best-practices, Livewire development,
Alpine.js, Filament development, Tailwind development and testing skills.
Use UX Design Thinking for a proportional review of the two jobs: visitors
compare Products; administrators configure Group appearance. Do not restart
the design questionnaire. Follow the native executing-plans workflow for
the supplied task sequence; do not delegate unless separately authorized.

Resolve routine implementation details within the selected design. Retain
the plan's compact defaults and earlier requested padding controls unless
a later user instruction overrides them. State reversible assumptions.
Raise only a material behavior/access/data conflict, with evidence and the
smallest proposed adjustment; continue independent work where possible.

2. Implement the chosen visual and settings contract

Use option 1 from the final three-interpretation document: neutral, full-width
property rows/cells. The earlier removed first interpretation is not this
option. The design artifact is a visual reference, not a filtering algorithm.

Replace comma-separated values with ordered, distinct facts in a one/two-column
grid. Use compact block/inline padding, consistent cell/card heights, complete
wrapped values and a compact Product-code header with the existing decorative
link indicator. Place real Group subtitles directly underneath. Make the
whole card one native Product link, with no nested interactive elements.
Preserve accessible names, keyboard focus, new-tab behavior and escaping.

Retain the existing 1–6 cards-per-row setting. Fit the requested maximum to
actual container width and keep six-column cards readable. Property columns
and cards per row are separate settings. Use the plan's full-width final-cell
recommendation for an odd property count in two columns; do not add another
appearance mode or speculative control.

Add the exact Group.result_settings keys, allowed values, defaults and types
specified in section 4: card_only_differences, card_show_labels,
card_property_layout, card_property_columns, card_padding_block and
card_padding_inline. Keep card_properties, cards_per_row, max_results,
products_debounce_ms and legacy pagination storage intact.

Extend CatalogPolicy normalization, SaveGroupSettings's strict allowed-key
list/validation/canonical typing and the actual GroupForm together. Use native
Filament controls and the existing aggregate Save path. Preserve authorization,
transaction/locks, partial updates and no-op behavior. A real appearance change
advances the revision once; an unchanged save or rollback does not. Labels off
must retain the stored layout. Replace the obsolete comma-separated helper text.

Keep the public filters' actual fieldset/legend/button styling and states.
Align headings, first buttons and field bottoms within responsive rows.
Start with CSS; if browser evidence requires measurement, batch it only at
initialization, metadata replacement and width changes, not on each click.
Do not clip labels, shrink hit targets or disturb focus when a preset hides
the focused field.

3. Compute card differences at the existing server boundary

Use every matching Product already loaded by CatalogCards, before chunking,
after the coherent read transaction ends. Evaluate only the resolved configured
card_properties in stable order. Compare the complete matching set, not merely
selected filter keys, the first 24 cards or cards already inserted in the DOM.

Create the focused, query-free CatalogCardDisplay helper using Artisan and
the compare() interface in section 7. Return shared ordered field descriptors
and noticeReason from one O(matches × fields) scan with O(fields) state.
This PHP helper is expected; no additional infrastructure service, endpoint,
worker, external dependency or browser-side full Product dataset is needed.

Preserve exact nonempty string values: "0", leading zeros, case, quotes and
Unicode. Never use truthiness, stringify malformed cells or coerce values.
Follow section 5: a value versus missing is a difference; preserve the same
slot across cards and use an escaped em dash for missing. Omit globally empty
fields. Keep distinct Product rows even if their properties are identical.

With only-differences on, shared properties disappear, including shared
unselected properties. There is no separate hide-selected control. One match
has no differing fields, but remains a navigable card. Translate the helper's
reason into one result-region propertiesNotice, following the explicit
single/shared/unpopulated precedence in the plan, not one notice per card.

Resolve labels once from snapshot field metadata or the current property-key
label convention, without relationship queries. Use the trusted Group ID for
stable DOM IDs; CatalogCards's current in-memory Group has only a name.
Reuse escaped Blade markup, pass shared descriptors/appearance to chunks,
and keep card-only values outside the compact filter snapshot.

Include the new small settings metadata in rebuilt snapshots. Normalize
old-shaped cached settings through CatalogPolicy. Retain schema 1 for these
additive defaults; do not force a blanket rebuild/migration/schema bump.

4. Protect the existing fast filters and independent card transport

Keep local selections, newest-first reconciliation, compatibility, exact
total, notices and URL/history updates immediate. Preserve preset behavior,
Clear versus Reset, clickable incompatible options and feedback identifying
removed choices. Do not call CatalogDiscovery::prepare() in the card path,
rebuild vocabulary on clicks, or revive per-option counts/type audits.

Keep GroupShow class-based, Group identity locked and dependencies protected
and injected through boot(), without catalog queries in boot(). Preserve the
focused Livewire JSON action and server validation/authorization. Never trust
client Product IDs/totals. Retain expected-error mapping, one report for
unexpected exceptions and explicit rejected-promise handling.

Use Livewire's bundled Alpine once, the existing captured root and wire:ignore
ownership boundaries. Keep raw rows outside public Livewire snapshots and
large reactive proxies. No entanglement, wire:model.live filters, new state
owner or islands. Keep stable identities and application-rendered escaped
HTML as the only source for x-html.

Treat cardPropertiesNotice as current-card data. Reset it on criteria changes,
retry and exit. Only accepted instance/request/generation/revision responses
may change cards, notice, loading/error state or freshness confirmation.
Invalidate unfinished insertion and hide superseded cards immediately.
Preserve between-frame chunks of at most 24, avoid reparsing old chunks,
and eventually insert every eligible match without pagination.

Keep threshold/all behavior and card-only debounce at 0/100 ms steps.
No eligible Products or above-threshold state requires no card request;
no-op choices require no duplicate request. Preserve history restoration,
pagehide/pageshow including cached restoration, destroy cleanup, offline
recovery and the bounded refresh/card-retry cycle.

Preserve the cache/revision contract: plain arrays, one replaceable Group
entry, locks outside read transactions, rechecks/retry bounds and coherent
revision/data reads. Keep Medium freshness's shared 60-second confirmation
clock, single in-flight check, hidden-tab behavior and current-choice
reconciliation. Stale responses must never mark newer data fresh.

5. Execute and verify in the plan's task order

Follow tasks 1–6: current baseline/settings; all-match comparison; accessible
card rendering; guarded notices/filter alignment; measurements/tuning;
contracts/final review. Keep each step reviewable and run its meaningful
regressions before proceeding. Use the existing test conventions and
independent expected outputs; do not delete distinct existing coverage.

Cover Product 25 introducing a difference visible in chunk 1, card-only fields,
missing versus populated values, one/identical/zero results, exact strings,
escaping, settings save/reload/partial/no-op/rollback, old cached settings,
and delayed responses that must not replace current cards or notices.
Rerun the two historical admin-label failures on the current source before
claiming either failure still exists. Do not change correct product wording
merely to satisfy an obsolete assertion.

Use default SQLite or the explicitly allowlisted isolated MySQL test database
according to the current guards. Never run destructive test setup against the
active local or remote database. Do not create production browser fixtures.
Defer Pest browser installation, Vite test reconfiguration and test-only
clock/network infrastructure; tests support the improvement rather than
expanding its scope.

Use real authenticated browser clicks and DevTools with actual built assets.
Verify the complete matrix in section 9, including rapid clicks, incompatible
choices/removal feedback, presets, Back/Forward, Clear/Reset, debounce,
threshold crossings, delayed/partially inserted cards, freshness changes,
settings changes, lifecycle/session failures, keyboard/focus and narrow/dark
layouts. Check exact Product identities, console errors and unhandled promises.

Record at least 100 real interactions on the 501-Product reference corpus,
including all results, 1/3/6 cards per row, both property column counts and
labels off/on. Aim for p95 filter feedback below 50 ms; assess the practical
below-100-ms criterion on a recorded device. Report all-card outliers separately.
Distinguish computation, DOM update, paint estimates, parsing/layout/insertion,
network/server time, SQL counts/time and response/snapshot bytes. Count action
executions as well as HTTP requests; double-frame timing is not actual paint.

Retain budgets: local filter-state work has zero requests/SQL; unchanged
freshness has no Product reads; warm cards have a revision lookup plus one
scoped Product SELECT, no SQL counts; differences add no queries/requests.
Measure authentication/session/cache/transaction overhead separately.

If all-card feedback remains slow, inspect main-thread traces and tune the
measured bottleneck. Do not automatically introduce islands, pagination,
virtualization, workers or a larger dataset. Preserve cancellation and eventual
completeness. Explain residual defects with evidence instead of declaring a
small-results test sufficient or claiming unmeasured improvements.

6. Review, publication and handoff

Review the implementation against every plan requirement and applicable
Laravel/Livewire/Alpine/Filament/UX guidance. Update governing card/settings
contracts so obsolete comma-only guidance cannot conflict with the change.
Run the affected PHP/Node checks listed in section 9, then:
vendor/bin/pint --dirty --format agent
npm run build
git diff --check
If formatting changes affected code, rerun the relevant check. Follow the
project instruction to ask the user to run the complete suite after focused
feature checks pass. Do not inflate verification by rerunning unrelated suites.

Capture a comparable remote baseline where access is available. Remote
read-only normal catalog interactions are appropriate; do not modify data.
Repeat remote browser/server measurements after an authorized release.
Local evidence does not prove production improvement. If access or a case is
unavailable, name precisely what was not verified and continue other work.

Commit/push/deploy only within the applicable user authorization, with an
explicit reviewed file allowlist. Preserve Forge deploy-on-push and do not
trigger a duplicate manual release. Check command availability on the server
rather than assuming rg exists, and inspect Artisan help rather than using
the unsupported --columns option. Preserve targeted invalidation and writer
compatibility safeguards; never rerun imports or change databases to deploy
this appearance improvement.

Hand off the actual outcome: changed files/behavior, completed plan tasks,
test/build results, browser scenarios and recorded device, query/request/byte
budgets, measured timings including the all-card case, remaining defects or
unverified cases, and production verification status. Clearly separate
implemented, tested, historical and deferred items. Include an explicit diff
review and leave unrelated changes untouched.

Do not implement property colors/icons or their settings page, additional
visual interpretations, numeric option counts, force-hide, pagination,
islands, physical Product columns, dependency changes or shared-shell redesign.
Those ideas remain deferred as recorded in the complete plan.
```
