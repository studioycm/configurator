# YouTube discovery for the catalog/configurator plan

> Historical research evidence. Execute the [final plan](../IMPLEMENTATION_PLAN.md) and [guidelines](../IMPLEMENTATION_GUIDELINES.md); their contracts supersede earlier alternatives here. Dates, versions, access and test observations are snapshots, not fresh verification.

Requested and completed for this planning checkpoint on 2026-09-23. Channel scans, selected title/description follow-ups, the Laracon demonstration/source study and recent-project repository reviews are documented. Coverage limits below remain explicit; this is not a claim that every video was watched.

The user requested the **Videos sections** of Laravel Daily and Filament Daily as an additional discovery source for searches on LaravelDaily.com and FilamentExamples.com. The subsequent correction requires **titles and descriptions**, not title-only research. This extends the [existing research](README.md); the separately authorized two-skill import is recorded in the [skill inventory](SKILL_PROVENANCE.md).

## Coverage and method

- Target window: **2025-01-01 through 2026-09-23**, covering calendar 2025 and the rolling past year (2025-09-23 onward).
- Scan chronological upload cards for candidates, then read the descriptions of relevant videos across the time window before classifying them as reviewed leads or using them for publisher-site follow-up. Report scanned-card and inspected-description counts separately. Do not claim that every video was watched or transcribed.
- YouTube's relative label “1 year ago” can include late 2024. Scan beyond the required boundary where needed, and resolve exact dates for selected leads/boundary uploads. Distinguish the count of reviewed cards from the number of uploads with exact dates verified inside the window.
- Use titles to discover subjects, descriptions to locate publisher sources, and actual tutorial/source/official documentation to support implementation recommendations.
- Keep 2025-era Filament 3/4 and Livewire 3 examples explicitly separate from version-verified Filament 5/Livewire 4 APIs.
- Preserve the settled plan: public discovery filters and configurator rules remain distinct; Custom behavior, stages, parts redesign, asset mapping and saved configurations remain deferred.

## Parallel channel reports

| Channel | Report | Verified coverage |
| --- | --- | --- |
| [Laravel Daily Videos](https://www.youtube.com/@LaravelDaily/videos) | [Laravel channel report](YOUTUBE_LARAVEL.md) | 420 chronological cards scanned; 72 expanded descriptions/date checks, 69 inside the requested window; three transcripts inspected |
| [Filament Daily Videos](https://www.youtube.com/@FilamentDaily/videos) | [Filament channel report](YOUTUBE_FILAMENT.md) | 210 chronological cards scanned; 32 expanded descriptions/date checks, 29 inside the requested window; linked tutorial/source follow-ups |

Both scans reached verified dates before January 2025. The card counts include that older overrun; they are not counts of fully reviewed in-window videos. The root reviewer additionally inspected the February 2026 Callout video's expanded description and linked tutorial below. The quote-form video overlaps the Filament report and is not counted again. No claim is made that every scanned video was watched or every description read.

## Implications from the channel research

| Source finding | Plan implication | Limit |
| --- | --- | --- |
| Current Livewire 4 kit lists an E-Shop Sidebar Filters example | Stronger stack-matched reference for M2; verify its code when repository access is available | Publisher page inspected, repository invitation pending; it does not prove newest-first reconciliation or our URL contract |
| T-shirt picker separates page content and reactive choices | Keep a focused ProductConfigurator boundary in M4 | Its hierarchical reset/disabled-size semantics differ from public catalog filters |
| Custom quote field combines a Filament Field, nested Livewire editor and paginated picker | Mapping editor can fit inside Filament without a new frontend framework | Its delete/recreate persistence cannot preserve our canonical/default/mapping identities |
| Pool Stock validates raw price before destructive numeric casting | Import validation must preserve raw evidence and explicit identity strings | Its SKU matching and ignored blanks differ from our legacy-ID/source-wins contract |
| Callout tutorial keeps action errors beside the attempted edit | Domain errors can remain actionable inside the editing modal | Halting an action is not automatic transaction rollback |
| Repeaters and deferred forms provide useful presentation mechanisms | Use bounded condition groups and consider deferred markup after save/reload proof | A generated label slug is not stable identity; unopened tab state still needs preservation |
| Performance videos and marketplace source expose query/payload concerns | Measure real facets, cards, definition loading and admin query counts | No tutorial benchmark establishes this application's speed or justifies extra caching/packages |
| Pricing history and Rulebook examples have different domain contracts | Preserve our typed, shared evaluator and current-definition behavior | They do not introduce saved configurations, pricing, version publication or a rule-engine package |
| Laracon's thirteen demonstrations include custom editable grids, card tables and public schema components | Filament can host the sketch's unusual editor while Laravel owns its behavior | All demo frames and both descriptions inspected; selected source files read; complete spoken content unavailable |
| Recent SiteLedger and sidebar projects use current Laravel/Filament/Livewire majors | Reuse transactional service and supported schema-composition patterns | A separate weekly-grid save has partial-write/input-loss risks; a nested component still needs its own mutation checks |
| Recent external-data table uses arrays and a scoped result service | Useful reference for bounded read-only facts and prepared results | Bare collections need explicit pagination; request scope does not span isolated lazy widgets |

## Additional source requested by the user

The supplied [Laracon Day 2 livestream](https://www.youtube.com/watch?v=vii6P0vJhTw) contains Povilas Korop's **Filament: Advanced Practical Examples** chapter, beginning at **2:05:04** and followed by the next talk at **2:28:46**. This July 29, 2026 livestream sits outside the two channels' Videos-tab scan. The [standalone recording](https://www.youtube.com/watch?v=olvqO33PBI0) was published August 14 and runs 22:53. Both expanded descriptions and all thirteen demo topics were inspected; original project links and selected code were followed in the [capability report](FILAMENT_CAPABILITIES.md). Transcript/caption access was unavailable, so complete spoken-content review is not claimed. These recordings are not silently included in the channel counts above.

The separate [recent-project report](RECENT_PROJECTS.md) inspects four authenticated pages/directories and sixteen repository files, including exact lock versions and pinned code references. The [AI Coding Daily report](AI_CODING.md) follows the author's planning articles into the actual public workflow repository. Their useful implications are integrated into the [implementation proposal](../IMPLEMENTATION_PLAN.md); they do not introduce the examples' business features.

## Verified follow-up: errors inside an admin action modal

**Video and description inspected:** [Filament: Show Modal Action Errors in Callouts (not Notifications)](https://www.youtube.com/watch?v=LmIhjrZTRCE), published **2026-02-26** on Filament Daily. The expanded description directly links [the publisher's Callout tutorial](https://filamentexamples.com/tutorial/filament-custom-action-errors-notifications-callouts), which was read with its source snippets. The full video was not watched or transcribed.

**Useful pattern:** place expected rule/default/mapping validation feedback inside the current editing modal, with a concise explanation and a way to repair the affected input. Filament's Callout component and action halting support that presentation; domain validation and transaction boundaries remain application responsibilities. This strengthens the agreed admin diagnostics, rather than introducing a new error policy.

**Version/source check:** Boost's Filament 5 [Callout documentation](https://filamentphp.com/docs/5.x/schemas/callouts) and installed `vendor/filament/schemas/src/Components/Callout.php` confirm the component. Installed `vendor/filament/actions/src/Action.php:693` defaults `halt()`'s rollback argument to false; `Concerns/InteractsWithActions.php:372` commits an enabled action transaction on a normal `Halt`, and `Concerns/CanUseDatabaseTransactions.php` defaults action transactions off. **Keeping a modal open is not proof that prior writes were rolled back.** Validate before writes and use the chosen transactional persistence boundary; if using a transaction-enabled action, select rollback behavior deliberately.

**Adaptation:** the tutorial catches arbitrary exceptions and displays their messages. Expected domain failures should have controlled user-facing messages; avoid copying raw exception-message exposure. The tutorial's mounted-action array mutation is an example mechanism, not the proposed domain interface. No new admin behavior was implemented or exercised by this source review.

## Verified follow-up: a custom editor can remain inside Filament

The Filament channel reviewer inspected the expanded description of [the quote-form/product-picker video](https://www.youtube.com/watch?v=5uS0otOU6V8), dated **2026-03-05**, and followed its direct link to [Quote Form with Custom Table Field and Product Picker Modal](https://filamentexamples.com/project/quote-form-with-custom-table-field-and-product-picker-modal). The parent reviewer then read the authenticated tutorial in Chrome and retrieved the relevant PHP/Blade files through Filament Examples MCP using `product picker` / `custom table field`.

The example demonstrates an array-backed custom `Filament\Forms\Components\Field`, a nested Livewire `#[Modelable]` editor, and a searchable/paginated picker in a slide-over. It supports keeping unusual configuration editing inside the existing Filament/Livewire stack when ordinary fields are awkward. It does not implement our matrix, rule evaluator, or reference-repair policy.

Reuse the composition idea only where the sketch needs it. The inspected `QuoteProductsField::saveRelationshipsUsing` deletes and recreates all related items. That persistence strategy is unsuitable for canonical IDs, stored defaults and mapping references. Our editor must validate the complete proposed state, resolve IDs through the current Configurator's allowed records, and apply the accepted changes transactionally while preserving identities. Event payloads and UI visibility are not authorization. The example's direct use of `PartialsComponentHook::forceRender` is an implementation detail to avoid copying as an application contract.

The label says Filament 4/5; current APIs must still be checked against installed Filament 5.8.4 / Livewire 4.4.6. No quote/cart/RFQ feature or new dependency is proposed; this is an editor-composition reference only.
