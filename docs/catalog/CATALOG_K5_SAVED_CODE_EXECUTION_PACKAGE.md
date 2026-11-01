> **Delivered-contract record — 1 November 2026:** K5 CODE+TEST is accepted and locally committed at `ed3f2c054a4e08b2c3563e44c99402f54b9ff500`. The original planning/status snapshots below are retained as dated history, not current “unapproved/unimplemented” status or new execution authority. See [the current index](../README.md) and [complete Catalog §27](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for actual delivery/runtime status. Earlier dated planning and resource narratives below are provenance, not instructions to repeat setup or imply current release authority. This records scoped delivery, not overall Catalog/Images or native/browser/FPM/Main acceptance.

# K5 saved configuration code — bounded implementation plan

**Status:** Preparation only, 31 October 2026; one material-plan review and coordinator release precede application edits. Implement inline with the executing-plans skill under the existing sole-writer arrangement; coordinator owns the eventual scoped commit.

**Goal:** Load/share the current Product's saved-definition configuration through its canonical code, without preserving a visitor's previous hidden choices.

**Spec:** [Complete Catalog specification](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md), §4 and §20. This package supplies execution details; settled domain and URL semantics are not reopened.

**Baseline:** Actual Main/master `/Users/studioycm/Herd/configurator`, `eb82a02b541126ad4b46e5aa205f1399f28f8230`, after accepted K4. Private baseline/scope receipt: `/private/tmp/catalog-k5-plan-20261031/BASELINE_AND_SCOPE.json`; 37 existing pending entries preserved. The attached old worktree is reference-only.

**Stack:** PHP8.4, Laravel13.35.0, Filament5.9.0, Livewire4.4.7, Pest5.3.0; existing Alpine registrar and Vite8.3.3. Locks remain unchanged. Boost documentation tools are unavailable in this tool inventory; use the installed Livewire source/official versioned documentation for any disputed action/lifecycle API at implementation, rather than assume another major's hooks.

## Constraints and current source findings

- Reuse `ConfiguratorDefinitionLoader::forDashboardProduct`, compiler and `ConfiguratorEngine`; no second engine, schema, DTO, intent enum, writer, provider or dependency change.
- Retain K4's independent ProductConfigurator presentation read, shared page layout/scale, actual Group-filter public facts, Parts slots, flat choices and tabs, numeric maps, route-bound return state, current-chunk links/focus.
- Loader deliberately excludes public Territory/Application context conditions for the dashboard; other Rules remain effective. Current saved assignment/facts/definition are read on each command.
- `ConfiguratorPreview` inherits ProductConfigurator and overrides its input/evaluate/render. New inherited saved-code entry points must explicitly reject Preview before any state changes; do not reconstruct unsaved editor definitions.
- `runtime` is locked and contains selections/memory/context, **not** the generated code. Publish code from `ConfiguratorEvaluationResult`, never derive it from browser selections or treat runtime as containing a code field.
- Protected `preparedResult` is request-local. On rejected Load, falling through `render → result → evaluate` would silently mutate runtime. Supply a detached render result without assigning the old runtime; test after the actual render cycle.
- Compatibility Product links call `CatalogJourney::compatibilityQuery` and currently retain `r` but discard `configuration`. Forward bounded code text there; decoding belongs to the saved Product flow.
- Shared configuration-fields has exactly two consumers: Product view and Preview view. Add an opt-in Product adapter path; existing default/Preview behavior stays intact.
- No Images producer, library, D60 import, admin Product/Group redesign, K6 implementation, new environment, browser bypass or cosmetic/mobile/zoom gate.

## Exact application/test allowlist — 11 paths

| Path | Change and responsibility |
| --- | --- |
| `app/Services/ConfigurationCodeRestorer.php` | Add pure current-definition decoding/evaluation capability, constructor-injected existing engine. |
| `app/Livewire/Catalog/ProductConfigurator.php` | Add saved-only Load/history actions, narrow ordinary-action reply adapters, one-time initial query consumption and atomic state assignment. |
| `app/Livewire/Catalog/ConfiguratorPreview.php` | Add only the explicit saved-code capability exclusion. |
| `resources/views/livewire/catalog/product-configurator.blade.php` | Load input/help/error and copy-current-link controls; compose scoped Alpine adapter with existing tab state. |
| `resources/views/components/catalog/configuration-fields.blade.php` | Add opt-in saved Product action bindings/busy disabling; retain default and Preview branches. |
| `resources/js/catalog-configuration-url.js` | Add small Product-scoped URL/history adapter over ordinary Livewire promises. |
| `resources/js/app.js` | One import and one entry in existing once-per-Alpine registrar. |
| `app/Services/CatalogJourney.php` | Preserve only the bounded Product `configuration` query alongside existing validated return state. |
| `lang/en/configurator.php` | Labels, hidden-choice reset help, invalid/currently unavailable and copy feedback. |
| `tests/Feature/Catalog/ConfigurationCodeRestoreTest.php` | Add actual service/native-entry/Preview-exclusion/compatibility regressions. |
| `tests/Unit/CatalogConfigurationUrl.test.js` | Add focused adapter/history/lifetime tests using deferred ordinary-command promises. |

Read-only dependencies include loader/compiler/engine, evaluation/definition DTOs and policy, existing Preview view/JS, Writing consumers, availability, ProductShow, routes and journey/filter adapters. No edit to these dependencies is implied by inspection. Source/dependency hashes are in the baseline receipt.

## Task 1 — canonical restoration on the real engine

**Interface:** `ConfigurationCodeRestorer::restore(ConfiguratorEvaluationInput $input, string $code): ConfiguratorEvaluationResult`.

- [ ] Add discriminating failing tests in ConfigurationCodeRestoreTest before implementation: reversed display/code/dependency order and conditional applicability; exact case-sensitive two-character identities including `00`/`10`; unknown, duplicate-token, duplicate-owning-attribute, malformed, foreign and inapplicable tokens; inactive/invalid definition; complete exact roundtrip and over-bound input.
- [ ] Implement one linear token lookup from the compiled definition. Read its actual policy separator (currently `-`, policy rejects overrides), require exact `[A-Za-z0-9]{2}` tokens and no trimming/case folding/numeric coercion/positional guessing. Bound bytes by `min(32768, n*2 + max(0,n-1)*strlen(separator))`, where n is compiled attribute count; reject empty input/unusable definition.
- [ ] Create a new evaluation input retaining trusted definition, Product facts, dashboard context, configurator ID, diagnostics and trace flag; use **decoded selections only**, `remembered=[]` and a fresh `Reevaluate` interaction. Never forward the supplied runtime selections/memory or previous interaction.
- [ ] Evaluate through the existing engine once; require complete usable result, legal/applicable supplied selections and byte-identical regenerated code before returning. Expected invalid input raises configuration-field validation; unexpected infrastructure errors propagate. The service does not touch Livewire state or fetch records.
- [ ] Prove identical restored state/code from different previous active selections, dormant memory and pending SelectOption/ChangeContext intents. A subsequent real choice revealing an omitted attribute uses actual default/legal policy, not the old visitor's memory. Run affected tests to green.

## Task 2 — saved Product entry/actions and Preview boundary

**Interfaces:** add protected `supportsSavedCodeRestoration(): bool` (true on Product, false on Preview); public `loadConfigurationCode(mixed $code): array`, `restoreConfigurationHistory(mixed $code): array`, `chooseConfigurationOption(string $attributeId, string $optionId): array`, `reevaluateConfiguration(): array`. Ordinary existing `selectOption`, `changeContext`, `refreshDefinition` signatures remain unchanged for current consumers. The two narrow ordinary wrappers invoke existing methods; no generic command router or arbitrary method dispatch.

All new public actions first enforce the capability and current locked Product/availability. Replies contain `{componentId: string, productId: int, accepted: bool, code: ?string}` from the actual settled result; errors are rendered normally. Browser operation generations are local, not trusted server authority.

- [ ] Add failing native tests for initial absent/valid/invalid code, complete runtime preservation after invalid Load **including render**, valid same-code hidden-memory reset, current changed assignment/facts/unavailable definition, dashboard context exclusion and forged inherited Preview calls.
- [ ] Mount initializes normally, then consumes supplied scalar `configuration` once. Invalid supplied code retains the initialized baseline and reports error. Malformed array/over-32KiB input is rejected explicitly, never truncated or silently interpreted as absence. Ordinary actions/renders/refresh never consume query input again.
- [ ] Explicit Load obtains fresh saved dashboard input and uses Task1. Assign both `runtime` and `preparedResult`, then invoke the normal evaluated hook, **only after success**. On expected rejection retain the previous runtime byte-for-byte, set validation error and prepare a detached current-definition render result without assigning state. Do not mask genuine Product unavailability or unexpected loader failures as invalid text.
- [ ] History uses the same restoration for a supplied code. `null` means absent query and initializes a fresh baseline with no previous memory; empty string is invalid code. Rejected history retains current runtime. Add Preview's explicit false override; its mount, unsaved source, inspection, normal actions and view remain unchanged.
- [ ] Extend only Product compatibility-query handling: require a scalar string ≤32768 bytes and retain its exact bytes, plus current route-bound `r`. Preserve existing aggregate encoded-query limit; malformed transport yields existing validation failure. Routes need no edit; current closure already passes query to this helper. Semantic invalid text reaches native Product validation.
- [ ] Run Task1/2 tests to green, including native and compatibility entries with a valid return envelope, numeric token identities, unrelated parameters, actual fresh saved definition and unchanged flat/facts/presentation output. Do not rerun unrelated compiler/engine suites simply because the service consumes them.

## Task 3 — minimal Load/share/history UI

**Interface:** export `catalogConfigurationUrl(config)` and register once via `Alpine.data` in existing app registrar. Compose with existing Product tab state; do not replace the tabs' keyboard/focus behavior. Config supplies current Product/component identity, named native Product URL and actual settled code; the server already consumed the initial query.

- [ ] Add failing Node checks for Load→ordinary choice→refresh→Back/Forward, rejected/absent history, incomplete result, no-op output, repeated setup/cleanup, foreign Product/component reply and late disposed response. Use deferred `$wire` promises; test observable URL/code behavior rather than private helper names.
- [ ] Bind Product choices/refresh to the four specific Task2 actions. While one command is in flight, disable Load and ordinary commands plus sharing; drop ordinary clicks instead of queuing them. History during that command retains only the newest pending same-Product history intent, suppresses old URL publication and restores it after settlement. Never run overlapping native mutations or replay an earlier ordinary choice after Load.
- [ ] Check local operation generation, root lifetime and returned Product/component identity before publishing output. On pagehide/destroy invalidate publication and remove listeners; on persisted pageshow intentionally restore the current same-Product query. A changed Product path cannot restore old code. No custom Livewire request cancellation/interception layer.
- [ ] Push canonical code only after accepted Load or ordinary choice and only if URL changes. Refresh uses replace. History restoration writes nothing. Incomplete/unusable ordinary/refresh output removes stale configuration. Initial absence creates no entry. Preserve unrelated query (`r`, `d`, others) and existing `history.state`; no Livewire URL attribute competing with this adapter.
- [ ] Initial invalid query remains visibly rejected without manufacturing a replacement history entry. Rejected explicit Load leaves the previous URL intact. Share builds a link from **actual current settled code**, not draft/rejected input; disable when incomplete/busy. If current definition becomes unusable, no stale code may be shared. Surface copy failure without claiming success.
- [ ] Add inline accessible input/Load/copy controls and reset-memory help to the Product view, keeping light/dark utility conventions and no modal. Add an opt-in binding prop to configuration-fields; Preview/default branches retain their existing inputs/legal-option/busy logic. Run affected Node/native checks to green.

## Focused review and execution evidence

Five material failure modes are owned above: exact numeric/case tokens (Task1), dependency/code-order mismatch (Task1), failed-render runtime mutation (Task2), inherited Preview entry (Task2), and obsolete asynchronous URL/share publication (Task3). Existing accepted K4 journey/presentation and engine proof is reused where sources are untouched.

- [ ] Before implementation, coordinator reviews this material package once and releases exact scope/current baseline. Recheck HEAD, allowlist/dependencies and existing pending files; stop on material drift, do not overwrite concurrent work.
- [ ] Use the already approved guarded inert SQLite runner `/private/tmp/catalog-k3-execution-evidence-20261031/run.py`, with fresh K5 evidence labels/output directory and no helper/profile changes: `python3 /private/tmp/catalog-k3-execution-evidence-20261031/run.py k5-restore-green pest tests/Feature/Catalog/ConfigurationCodeRestoreTest.php --log-junit /private/tmp/catalog-k5-execution-20261031/green.xml`. Inspect existing runner parameters before execution; preserve guard-before-bootstrap. No DB provisioning/reset/migration/MySQL or browser activation follows from this plan.
- [ ] Run `node --test tests/Unit/CatalogConfigurationUrl.test.js` and the existing affected journey/navigation tests only where the modified compatibility helper/registrar changes their contract. Existing Preview regression selection must exercise its real saved/unsaved boundary without invoking unrelated destructive suites.
- [ ] Format modified PHP, lint, check scoped diff and run one necessary `npm run build` after final JS/Blade edits. No app/Filament edits, so FilaCheck is not applicable. Preserve unrelated dirty sources; do not run broad auto-fix over them.
- [ ] Record exact source/build/test results and preservation limits; send one actual execution review, correct identified defects and rerun only affected checks. No browser PASS is inferred from Node/PHP; blocked native smoke remains explicitly unrun. Cosmetic/mobile is deferred and 200% zoom excluded.
- [ ] Stop for coordinator acceptance/scoped local commit. No push or next-stage implementation from this package. Future minimum Images producer order and settled same-Group K6 comparison remain unchanged.

**Planning self-review:** Requirements map to the three tasks; service/command/adapter types agree; no engine/loader/writer change or new product decision is needed. Planning ran source/Git/hash inspection only, not tests, application bootstrap, database, browser, build or generators.
