> **Delivered-contract record — 1 November 2026:** K4-J1 CODE+TEST is accepted and locally committed at `5d0f9ad7bccccd32fa41f7c4e1f6b5b5ef844d0a`. The original planning/status snapshots below are retained as dated history, not current “unapproved/unimplemented” status or new execution authority. See [the current index](../README.md) and [complete Catalog §27](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for actual delivery/runtime status. Earlier dated planning and resource narratives below are provenance, not instructions to repeat setup or imply current release authority. This records scoped delivery, not overall Catalog/Images or native/browser/FPM/Main acceptance.

# Catalog K4-J1 — validated cross-page return navigation

> **For agentic workers:** Use `superpowers:executing-plans` inline, one source writer on Main. This is the bounded package for independent material-delta review; application implementation starts when the coordinator releases this exact package. The existing human mission authorizes feature implementation, meaningful tests and scoped local stage commits without another routine approval question.

**Goal:** Open a model Group, then a Product, and return to the intended Group browser and independent Product selections without losing chronology or accepting return metadata as filter authority.

**Architecture:** One concrete `CatalogJourney` service validates bounded return data and builds named-route links. A small JavaScript transport helper composes links inside the existing navigation/filter components; those components retain URL, history and request ownership. Existing availability, Group filtering, Product filtering, snapshots and configurator services remain authoritative. No provider, repository, persistent journey store, new Alpine registration or replacement filter engine.

**Tech stack:** PHP 8.4, Laravel 13, installed Livewire 4.4.7, Filament 5.9.0, current Alpine/Tailwind/Vite and Pest/Node test tools. Recheck changed dependency locks only if they actually differ at release.

**Spec:** `CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md` §§3–4, §9 K4, CAT09/10/15/16/21 and §18. Existing later Product layout work remains in K4; this first bounded package is journey integration, not a claim that all Product presentation or later K5–K8 work is complete.

## 1. Accepted baseline, authority and exclusions

Main: `/Users/studioycm/Herd/configurator`, branch `master`, K3 commit `3235a4c7466fdf986eb167d4a19fa00664856466`, parent `5aeed93909715fe0a2eaf589dd7d55dca9248f2d`. K1/K2/K3 are accepted and committed. K3's 18 application paths and build bytes match the reviewed cosmetic source snapshot `48bb7aa3…`. There were 35 unrelated pending paths and an empty index at preparation; preserve their bytes, symlink targets and status.

Evidence, not instructions to rerun: `/private/tmp/catalog-k3-cosmetic-20261031/commit.json`; accepted final-scope review; `/private/tmp/catalog-k3-cosmetic-20261031/k4-affected-plan-review.json` (`9e626f69…`). Exact proposed source hashes/ABSENT markers and read-only dependency hashes are in `/private/tmp/catalog-k4-plan-20261031/BASELINE_AND_SCOPE.json`.

- One writer directly on Main; no new worktree, merge, wholesale reference copy or push. Coordinator owns the allowlisted local commit after actual execution review.
- Keep current guard, providers, owner writers, schema and native Product/Group admin UX untouched. No application DB/schema activation or new test environment.
- Reuse existing approved guarded test resources and retained evidence. Resource-specific destructive/new-database authority remains separate; direct-Master source editing does not authorize testing against Main data.
- No K5 code loading, K6 comparison, Images storage/upload/native fields/library/import, Product pagination, new settings or engine rewrite. Group tags never become Product criteria.
- Cosmetic/visual/mobile review is deferred until approved features finish. 200% zoom is excluded entirely. Do not turn unavailable cosmetic browser access into a code-correctness gate or report deferred checks as passed.

## 2. Concrete return contract

The only new URL parameter is scalar `r`: unpadded canonical base64url of UTF-8 JSON, **at most 8192 encoded bytes**. It is public return metadata, not a signature, session, authorization token or URL. Reject invalid base64/UTF-8/JSON, unsupported version, excess nesting, unknown keys, scalar/array shape errors and invalid identifiers. Do not cast booleans, floats, leading-zero IDs or arbitrary objects into valid IDs.

Version-1 shape (explicit keys, `null` allowed only where shown):

```text
{
  version: 1,
  groupId: canonical positive owner Group ID,
  productId: canonical positive Product ID | null,
  catalog: {
    query: {branchId: ID|null, search: string, tagValueIds: ID[], mode: tree|tags, page: ID},
    anchor: "model-<groupId>" | null
  } | null,
  products: {
    criteria: {version: 1, filters: exact-key/string-value map,
               subGroupId: canonical decimal string|null, precedence: string[]},
    anchor: "product-<productId>" | null
  } | null
}
```

Do not include request IDs, revisions, versions of presentation/trees, pending intents, trees/result IDs, HTML, arbitrary selectors, absolute return URLs or configurator state. Group/Product identity uses the existing canonical identifier contract; filter values retain exact strings including `0`, `001`, case and whitespace. Optional catalog context must remain absent/null on direct entry rather than inventing a previous branch. Only compose a Product anchor when a Product identity exists. Anchor prefixes/IDs are bounded and derived from those identities, not evaluated as arbitrary CSS selectors.

### Server capability and input ownership

Create `App\Services\CatalogJourney`, concrete constructor injection of existing collaborators; no container binding needed. Public methods:

- `decode(mixed $encoded): array` — bounded strict decoding, `ValidationException` for invalid return data.
- `validate(User $actor, array $state): array` — typed canonical version-1 array; current actor exists, owning Group is currently visible and a physical leaf, optional Product is active and belongs directly to that Group. Reuse `CatalogAvailability`; do not impose `manage-catalog` on ordinary authenticated viewers. Source branch must be visible if present, selected tag IDs must exist; zero-match combinations remain valid return state.
- `encode(array $state): string` — pure canonical bounded transport encoding; never repeat authorization/query work per rendered card.
- `catalogUrl(array $state): string` and `groupUrl(array $state): string` — named routes only. The former serializes the approved source query into existing `g[...]`; the latter serializes saved Product criteria into existing `d[...]`. Both carry the validated return envelope where applicable.
- `compatibilityQuery(User $actor, array $query, string $destination, int $id): array` — only `group`/`product` destinations; preserve supported, bounded `g`/`d`/`r` for the actual route. No caller-supplied URL/class. Product compatibility accepts `r` in this package; configuration transport belongs to K5.

Expose the existing pure `CatalogNavigation::validateQuery(array $query): array` as public without changing its body, get/children behavior or limits. Reuse it for catalog-query shape normalization and remove `requestId` from return output. Do not duplicate the raw URL parser or Group matching/facet algorithm.

For saved Product criteria, reuse current `CatalogSnapshots` and `CatalogSnapshotMatcher::ids` for existing exact field/value/preset/precedence validation at route-entry validation, discard returned IDs, and do not copy its reconciliation algorithm. Retrieve snapshots outside a containing read transaction, as current GroupShow does. This is bounded entry work, never a per-link query or per-filter action. Existing native `d` parsing/normalization remains the final active Product-state owner after navigation.

Keep target availability separate from return recovery: a hidden/inactive/deleted actual destination retains its existing 404/availability outcome. Invalid or stale *return context* on an otherwise valid destination is dropped with one escaped translated status message and normal direct-entry behavior. Catch only expected return-validation failures; do not swallow infrastructure exceptions as an innocent reset. Do not mutate records or revisions. Current physical-branch redirect occurs before leaf Product handling; `r` cannot override it or turn a branch into a leaf.

### Browser capability and source of active state

Create `resources/js/catalog-journey.js` with named exports `encodeJourney(state)`, `journeyLink(baseUrl, state, origin)` and `productBrowsePosition(groupId, criteria, browser)`. They pick the specified wire keys, use UTF-8 encoding rather than Latin-1 `btoa` assumptions, enforce the encoded bound and accept only same-origin server-supplied base links. They do not read an arbitrary destination out of `r`, authorize records, match Products or register global events.

- Group model links compose from the **settled current projection/criteria** and clicked model ID, not pending search/branch drafts or stale return metadata. Product criteria/product ID start null. The SSR link uses the same context, encoded without per-card SQL; without JS, it remains a working named Group link.
- Product links compose from the current normalized Product engine state, the actual owning Group, the clicked Product ID and the accepted original catalog context. Do not copy Group tags into `filters`. The Product page receives return context but does not load configuration choices from it.
- Overflow cannot break the catalog render or truncate data silently: fall back to the ordinary valid named link, preserve existing history, and expose one concise return-context-not-carried notice. No modal or extra request.
- Preserve unrelated query/history fields. `g` remains owned by `catalog-navigation.js`; `d` by `catalog-filter-engine.js`/`catalog-filters.js`; `r` is return context, not a second active binding. Existing explicit `d` on Group entry wins; missing `d` uses normal defaults. Only the explicit generated return link materializes saved Product state as `d`.

## 3. Exact proposed application/test write allowlist

No unlisted application path is released. An unforeseen necessary seam requires a small reported scope delta before editing, not a new framework or whole-plan review.

| Operation | Path | Responsibility |
| --- | --- | --- |
| Add | `app/Services/CatalogJourney.php` | Decode, validate, encode and generate bounded named-route return links |
| Modify | `app/Services/CatalogNavigation.php` | Public visibility of existing pure `validateQuery` only |
| Modify | `app/Livewire/Catalog/Index.php` | Validate optional return context, expose safe return position/notice; compose initial model URLs without per-card SQL |
| Modify | `app/Livewire/Catalog/GroupShow.php` | Validate actual leaf target/return context; expose breadcrumb/recovery links; retain existing branch redirect and Product transport |
| Modify | `app/Livewire/Catalog/ProductShow.php` | Current Product/owner checks plus validated return links; no configurator mutation |
| Add | `resources/js/catalog-journey.js` | Pure bounded UTF-8 transport/link/history-position helpers |
| Modify | `resources/js/catalog-navigation.js` | `modelHref(card)` and accepted return-anchor consumption through existing lifecycle |
| Modify | `resources/js/catalog-filters.js` | `productHref(baseUrl, productId)`; Product-result position capture/restore through existing lifecycle |
| Modify | `resources/views/livewire/catalog/index.blade.php` | Validated initial return-position/notice data; existing component and headings retained |
| Modify | `resources/views/livewire/catalog/group-show.blade.php` | Return context data/breadcrumb props; invalid-context status and current recovery link |
| Modify | `resources/views/livewire/catalog/product-show.blade.php` | Safe context passed to existing breadcrumb component; flat configurator remains unchanged |
| Modify | `resources/views/components/catalog/group-card.blade.php` | Parent component's `modelHref(card)` binding and existing SSR URL fallback |
| Modify | `resources/views/components/catalog/product-card.blade.php` | Stable Product identity/focus marker and `productHref` binding on actual link |
| Modify | `resources/views/components/catalog/breadcrumbs.blade.php` | Optional generated catalog/current-Group return URLs; existing ancestor branch links stay ordinary navigation |
| Modify | `routes/web.php` | Current compatibility redirects forward only supported validated state; no new endpoint |
| Modify | `lang/en/catalog.php` | Concise invalid/stale/oversize return-context notices only |
| Add | `tests/Feature/Catalog/CatalogJourneyTest.php` | Transport, actual route/owner/link integration and invalid-context outcomes |
| Add | `tests/Unit/CatalogJourney.test.js` | Pure UTF-8/size/link composition and state separation |
| Modify | `tests/Unit/CatalogNavigation.test.js` | Actual model-link/restore integration in existing harness |
| Modify | `tests/Unit/CatalogFilterTransport.test.js` | Actual Product-link composition tracks normalized choices and inserted chunks |
| Modify | `tests/Unit/CatalogFilterRecovery.test.js` | Return position/history cooperates with freshness and teardown |

Twenty-one paths: 4 additions, 17 modifications. Planning edits are only this document and the complete Catalog plan's bounded status/process/§18 reconciliation. Build output is derived after JS changes; preserve unchanged files and regenerate once after the final source edits. No `app/Filament`, provider, model, owner writer, schema, Asset, shell component, `resources/js/app.js`, engine or test-guard edits.

## 4. Implementation tasks and discriminating checks

### Task A — transport and actual-owner validation

- [ ] Add failing Pest cases to `CatalogJourneyTest.php` using existing factories and normal Feature test isolation. The service is missing now; execute through the existing guarded runner only after package release.
- [ ] Cover a valid Unicode source query, exact `0`/`001` Product values and chronology, encode/decode/validate roundtrip; invalid scalar/array/version/base64/UTF-8/unknown-key cases; a just-within/over **encoded-byte** limit. The matrix belongs here, not repeated at every route.
- [ ] Cover ordinary authenticated viewer access and current visibility/physical-leaf/direct Product ownership; reject hidden ancestor, inactive/reassigned/foreign Product context without exposing its facts. Distinguish invalid return context from unavailable actual target.
- [ ] Generate the concrete service with installed `make:class`, implement the methods above and expose existing query validation. PHPDoc array shapes must distinguish decoded input from validated output; no interface/DTO/provider needed merely to rename the same data.
- [ ] Run the focused cases green. Do not add MySQL concurrency work: this operation has no writer, lock, migration or commit publication.

### Task B — native entries and real link consumers

- [ ] Add request assertions in the same feature file for Index SSR model link → real Group route → Product route → generated Group/catalog return URLs. Follow URLs, assert actual IDs/codes/owning Group and escaped notices, not just the presence of a translation key or class.
- [ ] Verify the initial model link encodes its actual query/anchor; direct entry without `r` is unchanged; malformed/stale `r` on a valid target gives an explained normal entry; hidden actual target remains 404. Compatibility Product redirects retain valid return state; Group compatibility does not spread unsupported input. Existing branch redirection and empty-leaf behavior remain unchanged.
- [ ] Wire Index/GroupShow/ProductShow, compatibility closures and the three card/breadcrumb components. Keep constructors/Livewire framework injection conventional; no Livewire component is used as an authoritative writer. Never rehydrate a form to derive state.
- [ ] Retain GroupShow `loadCards(criteria, revision, requestId, displayVersion)` and `CatalogCards` signatures/chunk production. Product card bindings execute inside the existing `catalogFilters` context after `x-html` insertion; do not add session-specific state to cached snapshots or card endpoints.
- [ ] Run only these new route tests plus unchanged `PublicCatalogTest.php` and `ProductConfiguratorAvailabilityTest.php`, because entry/redirect/breadcrumb behavior is affected. Their accepted engine/facts/Parts checks are reused, not cloned into new suites.

### Task C — client composition, history and restoration

- [ ] Add focused Node tests before implementing the pure helper: Unicode-safe canonical transport, encoded-size overflow, same-origin named base links, unrelated query retention, and independent source tags versus Product criteria. No code-string or CSS assertions.
- [ ] In the existing navigation harness assert `modelHref` uses the clicked model and last settled query while a newer draft/request is pending. It must not transfer pending branch retry/removal metadata, mutate selected tags, add history entries or trigger a request merely to build a link.
- [ ] Add `productHref` to the existing filter component. The actual Product anchor gets a stable `data-catalog-focus` key and reactive href; it must track current normalized filters/preset/precedence after ordinary choices and card-chunk replacement, including modified-click/copy-link use without a click interception modal.
- [ ] Use the existing `pagehide`/`pageshow`/`popstate` hooks and listener cleanup. Capture `history.state.catalogProductBrowse` with Group identity, canonical Product-state identity, scroll and safe focus key while preserving other keys. Restore only for the same Group and normalized state after the matching current chunks are committed; absent/filtered-out anchors fall back to the existing reset control, never broaden results. A pending stale chunk cannot scroll/focus the current page.
- [ ] Explicit catalog return uses the validated source query and model anchor. Existing K3 history keeps exact Back/Forward scroll; initial explicit-return anchor is consumed once after current results are ready and never replayed on later search/refresh. Existing `restorePosition`/generation/disposal guards remain authoritative.
- [ ] Run new helper tests and the three affected existing JS suites. Reuse unchanged shell/engine proof; run those suites only if their inputs/behavior actually change, not by habit. No new global registrar or second binding.

### Task D — proportionate verification and handoff

- [ ] Freshly confirm actual accepted HEAD, allowlist hashes, unrelated pending work, empty index and current guard/dependencies. Do not recreate K3 setup or rerun its unchanged acceptance matrix.
- [ ] Run targeted Pint on changed PHP paths, PHP syntax checks, `git diff --check`, and one necessary `npm run build` after final JS edits. FilaCheck is not applicable unless an separately reviewed scope change actually touches `app/Filament`.
- [ ] Capture the finite changed-entry query delta once in the feature fixture (no per-link SQL/cardinality growth); do not invent a universal warm 2/3-query promise or remeasure unrelated tree/engine performance. `r` absent must avoid new journey queries; encoding a larger card grid must not authorize/query each link.
- [ ] Record actual command exits, meaningful assertions and preserved source/build/unrelated hashes. One independent execution/source review, then only correction review if required. Coordinator owns the scoped local commit and downstream handoff. No broad staging/push.

## 5. Existing test execution mechanism — not run in preparation

Reuse `/private/tmp/catalog-k3-execution-evidence-20261031/run.py` and `guarded-run.php` after checking their unchanged bytes and current `TestDatabaseSafety`. This reviewed runner supplies inert SQLite `:memory:`, array cache/session/mail and guard-before-bootstrap. It accepts an unused log label plus `pest` or `artisan`; use new `k4-j1-*` labels, never overwrite old evidence. Do not execute the following bare against Main's `.env`.

```sh
python3 /private/tmp/catalog-k3-execution-evidence-20261031/run.py k4-j1-feature-green pest tests/Feature/Catalog/CatalogJourneyTest.php tests/Feature/Catalog/PublicCatalogTest.php tests/Feature/Catalog/ProductConfiguratorAvailabilityTest.php
node --test tests/Unit/CatalogJourney.test.js tests/Unit/CatalogNavigation.test.js tests/Unit/CatalogFilterTransport.test.js tests/Unit/CatalogFilterRecovery.test.js
npm run build
```

Use narrow `--filter`/separate unused labels for each initial red/green task. Existing schema-resetting or runtime-resource commands are not part of this package. If an unforeseen test genuinely needs an independent DB commit/other resource, report that exact need; do not weaken the guard or alter the current three-file independent-commit Pest partition. No DB/browser/test/generator/build command ran during planning.

Functional acceptance is the affected route and client behavior above, not screenshots, cosmetic inspection, mobile or zoom. If the existing authorized browser is available, a single ordinary Group → Product → return smoke can supplement the automated results; do not create/bypass a browser context, repeat setup or make deferred visual work a gate. Report exactly which evidence ran.

## 6. Preserved dependencies and genuine frontier

K4-J1 needs no new human product choice. The material-delta review and coordinator's release of these 21 paths remain the next engineering step. Any later Product presentation slice uses the existing K2 settings and unchanged exact facts/Parts/configurator contracts; do not label all of K4 complete after journey-only work.

Actual images remain a **K7 joint dependency**. Master still has no owner `main_image_id`/`mainImage` field or accepted Asset producer. Keep truthful placeholders; `product_pic` remains inert. Images M0–I1 stops at durable unassigned assets; real owner images require its native assignment/save/read contract and relevant I3/I3a proof. Dedicated workspace/batch is optional to a basic individual-image producer but no thinner package or reorder is authorized here. Shared library stays Phase 2; D60 import remains excluded.

K5 saved configuration-code restoration and K6 comparison are separate. K6's comparison boundary decision does not block this package. Do not reopen accepted K1–K3 findings, repeat unchanged full suites, or add another plan-audit framework.
