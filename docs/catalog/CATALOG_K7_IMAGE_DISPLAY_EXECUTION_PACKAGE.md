# K7 managed Catalog image display — implementation plan

> **Recorded status update — 1 November 2026:** Actual existing-QA progress is now recorded:51tables/61migration rows, reviewed private facade/configuration and retained owned Group112/Products109,110/Configurator2; baseline/create/backend readback passed with pre-existing rows/pivots/settings/schema/history preserved. No image is assigned to those owners. Nine local synthetic files exist; their generation is not served/FPM proof. Both general QA transports remain disabled, browser access is policy-denied, and native served/FPM plus conditional Main rollout remain unrun. See [the current index](../README.md) and [complete Catalog §27](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for actual delivery/runtime status. Earlier dated planning and resource narratives below are provenance, not instructions to repeat setup or imply current release authority.

**Current status — 1 November2026:** exact21 CODE+TEST accepted and locally committed on actual Main/master `d44ecb609d17a67afabe0960d8704f9ef267ac79`, parent `a3bd44c`. Root commit receipt `/private/tmp/catalog-k7-execution-20261101/ROOT_COMMIT_RECEIPT.json`, SHA256 `411ee0a995b0256110a18a3980c3096fabf93785eca275a81f93c15701738fb4`; independent actual acceptance `/private/tmp/audit-k7-actual-code-test-exact21-local-commit-acceptance-20261101.json`, SHA256 `e3c12a56ee73c4bc195c65a4c49703455ff84233d75120d6ffdbe9a6463dd8ed`, no required findings. Affected PHP60/339, native2/85, Node11, Pint/syntax/build are accepted source-specific evidence, not a new summed suite or runtime proof. The original plan/before hashes and unchecked work steps below are execution provenance, not an instruction to repeat accepted S1–S3. Source/build stay stable for rollout. Both reviewed resource scopes are human-approved (`call_2fe71a9ccfa54256970ec137ced5f45a`, “Approve both reviewed scopes”); receipt `/private/tmp/catalog-k7-runtime-preparation-20261101/ROOT_RESOURCE_SCOPE_APPROVAL.json`, SHA256 `232bb3bda654555f431984937af8ffee8ff8fec56bd3fa967e4dfdbf3425c40f`. Scope approval is not an execution window or evidence of execution. Root completed the bounded QA schema/publication and fixture baseline/create/backend-readback operations recorded in complete Catalog §27; served native/browser/FPM proof and conditional Main remain unrun. Actual served/FPM/capacity/Main readiness remains unrun by this owner; [complete Catalog §27](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) carries the K8 handoff. No manager/library/D60/Writing/push scope follows.

> For agentic workers: use `superpowers:executing-plans` inline after focused material review and coordinator source release. The sole writer works in `/Users/studioycm/Herd/configurator`; Root owns the eventual scoped commit. This package releases no application edit, database operation or activation by itself.

**Goal:** show each eligible Group/Product's own managed image in the existing Catalog cards, Product configurator page and saved-Product comparison, with truthful fallback and the current placement controls.

**Architecture:** resolve ready image metadata once from already-authorized owner pointers inside existing read boundaries, using the committed `CatalogAssetDisplay::forIds(array): array`. Pass metadata into a small shared Blade/Alpine image component; keep all matching, configuration, revisions, authoring and storage operations with their current owners.

**Tech stack:** existing PHP8.4, Laravel13.35.0, Livewire4.4.7, Filament5.9.0, Alpine, Pest5.3.0, Media Library11.23.9, Spatie Image3.9.7 and Vite8.3.3. No new dependency, provider, repository or UI framework.

**Spec:** [complete Catalog specification](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md), §§6–9/26; [accepted minimum producer](IMAGE_ASSIGNMENT_MINIMUM_PRODUCER_EXECUTION_PACKAGE.md). This bounded package supersedes the old §8 proposal for an additional `forResultQuery(Builder)` API: the accepted `forIds` already deduplicates and chunks beyond500. Reusing pointers already loaded for the result avoids a second owner-query abstraction.

**Status/baseline:** PLAN ONLY, 1 November2026, actual Main/master `a3bd44cfd6ba8af138184675511a92030369da46`, parent `5497c09`. Source/test proposal is21 paths, with before hashes,43 pre-existing pending entries and functional source/build pins in `/private/tmp/catalog-k7-plan-20261101/BASELINE_AND_SCOPE.json`. Attached historical worktrees are reference-only. Source implementation may proceed after review with Images default off while genuinely new resource permission is pending; Main rollout is a separate operation.

## Accepted inputs and remaining facts

- Minimum Images exact52 CODE+TEST package is accepted/committed. Root commit receipt `/private/tmp/minimum-images-execution-20261101/ROOT_COMMIT_RECEIPT.json`, SHA256 `efe95f0702bde812619df84098e6d56a48434faba5735500c34b746ea5b1f956`; final audit `/private/tmp/audit-minimum-images-final-code-test-and-exact52-local-commit-acceptance-20261101.json`, SHA256 `29a1064c41609351fa6a8e2c9145e50da68b8b3fae80832eeba5077ee9b3cafd`.
- Actual reconciliation TEST ran the three additive migrations once and two non-resetting contenders:2 tests/41 assertions, cleanup/preservation passed, window closed. TEST is now51tables/61migration records. Receipt `/private/tmp/minimum-images-execution-20261101/ROOT_TEST_EXECUTION_RECEIPT.json`, SHA256 `f6ddc748e557c8e2c8c726849c76c2554605cb6209af035591decb9953b6fbea`. No49-table initialization, migration replay, old lease or reset follows.
- `MIN-SOURCE-RECOVERY-001` and `MIN-FILE-CONFINEMENT-PROOF-001` are independently closed. Native all-state/stable-ID/remount/bound-upload recovery, selected-version checks and actual filesystem confinement are delivered. Reuse that evidence; K7 does not rebuild the producer or manager.
- Main has not received the three Images migrations. `CATALOG_ASSETS_ENABLED` is absent/default false; aliases remain empty. Read-only file inspection found Main's local MySQL tuple `127.0.0.1:3307/configurator_catalog_dev`, same username, no DB URL override or cached configuration, and the existing `public/storage` link targets Main's `storage/app/public`. These are file/source observations, not a fresh connection/schema/FPM check or resource authority.
- Existing QA candidate is the private facade `/private/tmp/configurator-catalog-k2-qa-runtime-20261030`, host `configurator-catalog-k2-qa.test`, separate `configurator_catalog_k2_qa` database, using the actual Main source directory. Its current schema/readiness/permissions are UNVERIFIED. Its inspected driver admits only existing static mappings, so managed-image serving needs a bounded QA-only mapping/guard amendment; merely enabling the flag is insufficient. Do not bypass the old origin denial or assume an old source-pin release remains current.

## Global constraints

- Preserve GA1 optional raw-key omission/explicit[] semantics and exact JSON strings; GA2 g2/r2 object/list identity, independent tags and attributes-first cards; Product d1, configuration URLs, K6 Differences then Same values.
- Preserve Group model eligibility/limits, full ancestry/direct counts, current filters/tree/tag behavior, flat configurator, ordinary Product/Group resources and all authoring/lock/revision/CAS rules.
- Current settings remain authoritative: Group/Product placement `above|below|left|right`, scale `small|medium|large`; Group overrides remain selected and limited; Product page `compact_header|image_column` and scale remain shared only. No additional settings or theme choices.
- Only own ready managed images; no parent/family/demo inheritance, `product_pic` inference, D60 access/import, galleries, attachment/library/manager/batch/Apply work or Writing.
- No render-time filesystem/source/Media probes, `Schema::hasTable/hasColumn` checks, per-card queries or image data added to the pure filter snapshot cache.
- No application tests on Main/QA. Source checks use the existing guarded SQLite-memory/scratch route; persistent TEST/QA/Main each retain their own exact authority/profile/window. No new site/database/worktree by default.
- Cosmetic/mobile review remains deferred;200%zoom is excluded. Functional visibility, fallback, keyboard operation and image containment at changed surfaces remain relevant.

## Review focus

1. Feature off before Main schema exists: no explicit pointer-column select or Asset/Media query; existing truthful placeholders and ordinary behavior continue. S1 pins this.
2. A filtered/hidden/reassigned owner or stale request must not contribute another owner's image, properties or chunks. S1/S3 pin this without changing the current request/revision protocol.
3. More than500 unique pointers, repeated pointers and all-result differences must remain complete, deduplicated and query bounded. S1 pins this using synthetic metadata fixtures, not501physical photos.
4. Thumbnail404, original404, an old image event arriving after a new image and very small/tall images must preserve a useful frame and current identity. S2 pins this.
5. K5 configuration state, exact Product facts/Parts, K6 property partition, g2/r2 return state and image-only save/refresh remain independent. S3 pins this.

## Current consumers and chosen contract

`CatalogNavigation::get` already authorizes/filter-scopes Group cards and reads only the24-card page's Group settings/revisions; `mainImage` is currently null. Keep topology/treeVersion semantics unchanged. A new image does not collapse an unchanged tree.

`CatalogCards::get(int,array,string,string,?string):array` already reads all matching active Products inside a coherent Group-revision/shared-display transaction, compares properties across the complete set, then renders24-card chunks outside the transaction. Resolve pointer metadata in that same read; render chunks from the materialized map only. Empty/above-threshold responses read no image metadata.

`CatalogProductComparison::get(User,int,array):array` already authorizes exactly two current saved Products from one physical leaf Group. Add only `mainImage` to each identity entry; preserve pair order, saved property keys and Differences/Same sections. The current client accepts additive identity fields; no comparison controller/state rewrite is needed.

The actual Product-page image slot is `ProductConfigurator::render` / `livewire/catalog/product-configurator.blade.php`, inside `ProductShow`. Use its freshly authorized Product. Do not add a second image to ProductShow or put engine/cache evaluation inside a new image transaction.

**Read contract:** retain `CatalogAssetDisplay::forIds(array $assetIds):array<int,DisplayImage>` unchanged. `DisplayImage` is `{id:int,url:string,thumbnail_url:string,width:int,height:int,mime_type:string,filename:string,bytes:int,warnings:list<string>}`. Each caller builds an owner-ID→nullable-image map from its own authorized owners. With the flag off, do not select/access an absent pointer merely to build an empty map. With it on, select pointer columns once, discard nulls, call `forIds` once; it internally issues one join per≤500unique IDs. Zero pointers means0Asset/Media queries. Do not accept client-supplied Asset IDs or introduce `forResultQuery`.

**Freshness:** immutable winner URLs need no extra media version/cache. Pointer writes already advance the applicable Group revision; existing card revision/displayVersion/requestId guards reject stale chunks. Image maps stay outside cached engine snapshots. Root navigation is a fresh authorized read with its existing latest-request guard; topology tokens and g/r transport are unchanged. Flag transitions take effect on fresh document/request; no live-push or new display-version format is promised.

## Exact proposed source/test allowlist —21 paths

| Path | Work |
| --- | --- |
| `app/Services/CatalogNavigation.php` | Gate pointer select, batch metadata for returned card page, fill existing mainImage key. |
| `app/Services/CatalogCards.php` | Gate pointer select, resolve full matching-set image map in current read; pass to chunks. |
| `app/Services/CatalogProductComparison.php` | Scoped pair image map and additive mainImage identity field. |
| `app/Livewire/Catalog/ProductConfigurator.php` | Gate image read for its current authorized Product and pass view data. |
| `resources/views/components/catalog/main-image.blade.php` — new | Shared metadata-only frame, intrinsic image and existing placeholder fallback. |
| `resources/views/components/catalog/group-card.blade.php` | Replace image slot for both SSR and Alpine cards; preserve all card copy/order/link state. |
| `resources/views/components/catalog/product-card.blade.php` | Optional nullable image prop; preserve single Product anchor and external Compare control. |
| `resources/views/components/catalog/card-chunk.blade.php` | Pass owner-keyed materialized image, never query in Blade. |
| `resources/views/components/catalog/product-comparison.blade.php` | Same shared image frame in existing two identity headers. |
| `resources/views/livewire/catalog/product-configurator.blade.php` | Replace only existing image placeholder; current page layouts/fields/tabs unchanged. |
| `resources/js/catalog-image.js` — new | Focused Alpine load/error/original-fallback/current-image lifecycle. |
| `resources/js/app.js` | One import/registration in the existing idempotent registrar. |
| `resources/css/app.css` | Image frame/contain/no-upscale styles within current placement/scale selectors. |
| `lang/en/catalog.php` | Only image unavailable/fallback accessibility copy needed by these slots. |
| `tests/Feature/Catalog/CatalogImageDisplayTest.php` — new | Real read-consumer integration, owner scope, off-path and query/metadata fixtures. |
| `tests/Feature/Catalog/CatalogNavigationTest.php` | Own-image/card-page and unchanged topology/attributes/tag criteria behavior. |
| `tests/Feature/Catalog/CatalogProductComparisonTest.php` | Pair metadata/scope and unchanged exact Differences/Same values. |
| `tests/Feature/Catalog/CatalogProductPresentationTest.php` | Real Product child binding/layout/facts/configuration compatibility. |
| `tests/Feature/Filament/CatalogMainImageFieldTest.php` | Two focused real native assignment→Catalog read/replace/remove cases using existing true-commit fixture/helper. |
| `tests/Unit/CatalogImage.test.js` — new | Image lifecycle, stale events, one original retry and truthful fallback. |
| `tests/Unit/AlpineRegistration.test.js` | Add only new catalogImage expected registration; retain both startup/idempotency cases. |

Read-only dependencies include current Asset/Media/storage/intake/edit services/models/writers, CatalogAssetDisplay, availability, snapshots/matcher/field registry, revisions, presentation settings, navigation/filter/journey/comparison/configuration helpers, providers/routes/guards/Pest partition and QA helpers. No PHP dependency or schema source change in K7. Generate the new PHP feature test with installed guarded Artisan only after source release; its ordinary transactional partition is automatic. Existing native test file retains its SQLite-only true-commit partition. No guard/XML/Pest/helper amendment is proposed.

## S1 — owner-scoped read projection

**Consumes:** current signatures above and `CatalogAssetDisplay::forIds`; current `catalog-assets.enabled` flag. **Produces:** root `card.mainImage`, optional Product-card image prop, Product-page `mainImage`, comparison `products[n].mainImage`; all nullable, no new public endpoint.

- [ ] Add failing feature cases for flag off/no new schema queries, zero pointers, own ready winner, missing/nonready/corrupt winner/disk metadata and foreign/ineligible owners. Use explicit synthetic ready-winner metadata fixtures for read/query coverage.
- [ ] Pin one joined image query for a returned Group page/one Product pair/≤500unique matching Product pointers, two for501unique and one for>500repeated pointers. Empty/threshold-blocked results have0image queries. Assert no per-card lazy relation/filesystem read, no source/private metadata in payload.
- [ ] Append concrete `CatalogAssetDisplay` injection to the three Services, gating pointer columns and assembling owner maps inside their existing reads. Add the Product render data read after current availability checks. Keep public signatures and all-result property comparison unchanged; do not render the page/engine while holding a new image lock.
- [ ] Run only changed read-consumer cases under guarded SQLite-memory. Record query counts against the fixture used, not an old global query total.

## S2 — shared image rendering and failure lifecycle

**Interfaces:** pure Blade `main-image` receives nullable `image`, decorative/alt intent and existing frame attributes. Alpine factory `catalogImage(initialImage = null)` supplies `setImage(image)`, `onError(attemptIdentity)` and `onLoad(attemptIdentity)` plus current URL/status. Callers may use the existing Alpine scope to update it; do not create a generic binding language or HTTP image loader.

- [ ] Write focused Node cases for missing metadata, thumbnail success, metadata-declared original fallback, thumbnail error→one original retry, original error→placeholder, same URL with a new image identity, and late load/error events from an obsolete image/attempt. Events may affect only the matching current image/attempt. No retry loop or Livewire request per image.
- [ ] Add the Blade/Alpine component and single registrar entry. Render escaped SSR image metadata when present; dynamic Group/comparison slots call `setImage` from their current owner data. Preserve SSR empty/zero content and hydration boundaries. Reset failure state when identity/URL changes. Account for an already-complete broken SSR image at init.
- [ ] Preserve reserved frames and intrinsic width/height. Center `width:auto;height:auto;max-inline-size:100%;max-block-size:100%;object-fit:contain`; do not enlarge small source pixels or crop valve details. Keep white-photo-safe trays in dark mode. Existing side tray maxima64/96/128, above/below block maxima96/144/192 and Product page160/240/320 remain the only scale values; constrain to available container.
- [ ] Card/comparison images are decorative when the adjacent link/header already supplies the same identity; avoid duplicate link announcements. Product-page image alt uses existing readable Product identity. A failed image preserves the frame and truthful unavailable/placeholder state; private preview URLs are never used in Catalog cards.
- [ ] Replace only existing image slots and add the narrow CSS. Product anchors, Compare controls, Group content ordering, filters and configuration stay unchanged.
- [ ] Run new Node tests and the changed registrar's two startup cases; affected PHP markup cases, then build once for changed JS/CSS. Native browser behavior remains a separate resource check.

## S3 — actual producer integration and functional source acceptance

- [ ] In the existing native image field test, add Group and Product roundtrips using its real signed upload/private intake/scan/preparation/ordinary Save helper. Fresh Catalog reads must show the saved managed URL; replace/remove through current native actions and verify the new read/fallback and revision. Do not duplicate the40-case producer lifecycle suite or add another true-commit partition.
- [ ] Preserve g2/r2 criteria and ancestry/attributes-before-tags, Product d1 return/filter/position state, K5 choices/code/failed restore, K6 pair/property partition including0/001/whitespace/missing, and all ordinary editor fields. Test only the changed binding/metadata behavior; reuse unchanged accepted tests/evidence.
- [ ] Run affected feature files plus only added native roundtrip cases using the released guarded in-memory/scratch launcher. Semantic test selection: `CatalogImageDisplayTest`, changed `CatalogNavigationTest`, `CatalogProductComparisonTest`, `CatalogProductPresentationTest`, native roundtrip filter. Node: `CatalogImage.test.js`, `AlpineRegistration.test.js`; existing unchanged transport/property tests rerun only if their code or an actual concern changes. Run required Pint for PHP, PHP syntax, applicable checks and necessary Vite build. No persistent test command is implied by this document.
- [ ] Return exact21-path patch/hashes, source/build identity, query fixtures/results and truthful gaps for one independent actual review. Resolve required findings and retest affected cases only. Root owns the scoped CODE+TEST commit. Do not label Main usable or the overall Catalog complete from that commit.

## Finite resource scopes — separate from S1–S3

These were resource proposals at initial planning time. Both scopes are now human-approved as recorded in the current status; they are not executable profiles or an execution window. Root prepares/reviews the concrete private envelopes; actual mutation requires its released short stable-source window. No new generic operations platform, no second source writer and no repeated historical setup review.

### R-QA — existing runtime, capacity and native browser/FPM proof

Prefer the existing QA facade named above, sharing the current Main source and using its own database/storage/environment/session/cache. Its private profile target is database/username `configurator_catalog_k2_qa` at `127.0.0.1:3307`; that file-level tuple does not prove current schema. In the separately released preflight, check resolved identity/history/schema/source/build/browser admission. Do not assume it equals TEST51/61. It may predate GA1: conditionally name `database/migrations/2026_10_31_000001_add_catalog_attributes_to_groups_table.php` before the three Images paths if that nullable Group-attributes column/history is missing. Skip an already-present exact prerequisite; stop a conflicting/partial state rather than run all pending migrations or initialize/reset QA.

Review only necessary private runtime deltas: current source/build pins and ready/ownership metadata; explicit Images flag/managed and private disk-root assertions; allowlisted synthetic source rooted under QA private storage; managed-image URL serving restricted to QA's own public catalog-assets tree and safe image paths. The inspected driver currently lacks that mapping. Keep private import/temp files unserved and preserve the existing runtime admission/security boundary. No Main disk/source/env or shared TestDatabaseSafety changes; no public-storage link borrowed from Main. Resolve actual browser permission through the approved surface, never an alternate-origin/tool bypass.

Current capacity proof is per individual image on the selected authorized runtime: representative JPEG/PNG/WebP valid controls at the accepted3MiB/25MP boundaries, over-limit rejection, and successful original+160/480/960 contain/no-upscale output. Use named synthetic fixtures and one image/request at a time, scoped disk/owner IDs and captured cleanup. Read actual FPM upload/post/memory/execution and server request limits; measure request/lease-segment elapsed time, PHP peak and process RSS/native-GD memory. Show successful completion within the configured request, memory and claim limits and record observed margins. CLI success is not FPM success. A demonstrated failure requires its smallest concrete correction before activation; no invented percentage threshold, silent limit reduction or queue is introduced.

Retain the accepted2000-file/500MiB caps, bounded-scan automated evidence and short-request recovery behavior. A declared-limit bulk-source capacity run belongs to the remaining Images manager stage before its folder/bulk activation; it is not a gate for current individual-image/K7 activation while real aliases/bulk manager remain absent. Read-query501-ID fixtures are synthetic SQL metadata, not501physical photos.

Browser proof uses the current served build and a few labelled synthetic fixtures to cover representative distinct mechanisms, reusing accepted tests:

1. A real native chooser→signed upload/private proposal→ordinary Save with fresh readback/restore. Cover the distinct Product editor, Group aggregate editor and create-another context only where their different mechanisms need served proof; use one normal Save/shortcut path rather than replaying every lifecycle combination. Reuse the accepted native commit/rollback/flags/host tests.
2. One relevant stalled/recovered intake or lost-response path and a late response against a newer draft/current image. Reuse the accepted older-ID/foreign-scope/version and pipeline cases; do not seed a51-source browser exercise or repeat all recovery tests.
3. Group card→Product cards→Product configurator and the saved comparison image. Confirm own-image identity, one meaningful layout/scale variation, functional visibility in the existing light/dark modes, and safe thumbnail/original failure/current-image replacement. Preserve operable links/Compare/focus and reuse accepted K5/K6 choices/Back/property evidence rather than a settings/theme matrix.

No cosmetic/mobile/zoom review or exhaustive placement/scale/theme matrix is part of this gate. Main data is not used for QA fixture writes.

Read back named fixture changes and restore pre-existing QA business rows/ordered pivots/settings; delete only captured owned fixtures/files or deliberately retain them under the reviewed retention scope. Preserve baseline data, history, runtime ownership and Main isolation. Record actual browser/FPM/capacity evidence and unresolved limits once.

### R-MAIN — exact additive schema/storage and conditional activation

Proposed target from inspected Main environment: `mysql`, `127.0.0.1:3307`, database and username `configurator_catalog_dev`. Fresh resolved identity, exact capability/environment hash, current history/schema and preservation baseline must be checked inside the reviewed Main-only window; no connection was made in this plan refresh.

Run only these three exact **up** paths in one installed migrate path-array call; inspect existing users/media/groups/products/history first and refuse absent prerequisites, conflicting tables/pointers/history or unexpected pending prerequisites. No tests, reset/fresh/refresh/down/seed, replay of TEST or all-pending migrate on Main.

| Path | SHA256 |
| --- | --- |
| `database/migrations/2026_11_01_010001_create_catalog_assets_table.php` | `bd60d5c5cc48dfee95bcaafbd294643a3e0178a39c35440bd68cdf046a08f0fa` |
| `database/migrations/2026_11_01_010002_create_catalog_asset_import_sources_table.php` | `055139e7c3709fdc07faf1f5f21f190318e419e7c87b9769be1a9f11d0e5604a` |
| `database/migrations/2026_11_01_010003_add_main_image_id_to_catalog_records.php` | `01784f8fde3e64fcf167416d36fd72545f5d1b928af42eed04f96c002a959e37` |

Allow only two new initially empty tables, two nullable indexed/restrict-FK pointers and three history rows; old business rows/raw JSON/relations/schema/history remain unchanged, old pointers start null. Natural migration AUTO_INCREMENT advancement is allowed; do not reset counters or repair a partial run by rollback/guessing.

Storage scope: ensure only canonical managed `storage/app/public/catalog-assets` and private `storage/app/private/catalog-asset-imports` directories with the required permissions, preserving existing contents. Verify the already-existing `public/storage` link; do not recreate it. Include one exclusively created named synthetic PNG write/read/remove probe for managed URL serving and one private-disk probe if needed, never an existing asset/source or owner assignment. Bind exact probe paths/cleanup in the resource envelope. Private sources must remain inaccessible by public URL.

Keep the flag off during schema/storage work. Conditional activation in the same specifically approved envelope may add only `CATALOG_ASSETS_ENABLED=true` after R-QA/native/capacity checks and Main preservation/storage checks pass; aliases remain empty and no D60 or fabricated Product/Group association is imported. Verify actual resolved flag, fresh served build and read-only catalog/editor availability. No blanket cache clear, daemon restart or new site: currently no cached config was observed; any changed runtime condition requires its concrete narrow handling. Existing open documents need a fresh load; no live-push guarantee.

## Compact decision/release list for Root

| Item | Recommendation / who acts |
| --- | --- |
| Product/UX decisions | None pending for this slice. Reuse current defaults/overrides, own-image policy and one main image. No wizard/library/styles discussion reopens. |
| Source | Focused material review of this21-path package, then Root source+guarded in-memory/scratch release. This can proceed while a genuine new resource scope is pending. |
| QA/runtime | Prefer the existing same-Master facade. Preflight only in its released step, then bind the actual missing migrations/private mapping/fixture/browser/capacity scope in one concrete resource question. No assumed reset or new environment. |
| Main resources | One concrete, conditional R-MAIN envelope naming the tuple, exact3up paths, storage/probes/preservation and activation conditions. Earlier TEST authority does not cover Main. Do not ask again for routine source steps. |
| Acceptance/order | CODE+TEST review/commit is distinct from Main runtime readiness. Close required runtime gates before declaring the usable image feature; then refresh K8 from actual evidence, followed by remaining Images workspace and Writing. Shared library phase2/D60 remain excluded. |

No application code, migrations, database connections, fixtures, browser requests, build or tests were executed by this planning refresh. Source inspection, receipt reading and documentation/private planning artifacts are its evidence.
