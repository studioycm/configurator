# Minimum managed-image producer — implementation package

> **Recorded status update — 1 November 2026:** Actual existing-QA progress is now recorded:51tables/61migration rows, reviewed private facade/configuration and retained owned Group112/Products109,110/Configurator2; baseline/create/backend readback passed with pre-existing rows/pivots/settings/schema/history preserved. No image is assigned to those owners. Nine local synthetic files exist; their generation is not served/FPM proof. Both general QA transports remain disabled, browser access is policy-denied, and native served/FPM plus conditional Main rollout remain unrun. See [the current index](../README.md) and [complete Catalog §27](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for actual delivery/runtime status. Earlier dated planning and resource narratives below are provenance, not instructions to repeat setup or imply current release authority.

> For agentic workers: execute inline with `superpowers:executing-plans` only after the coordinator releases this concrete package. This document is planning, not implementation, database or publication authority.

**Status:** accepted and locally committed CODE+TEST package, 1 November2026, actual Main/master `a3bd44cfd6ba8af138184675511a92030369da46` (parent `5497c09`). Exact52 commit receipt `/private/tmp/minimum-images-execution-20261101/ROOT_COMMIT_RECEIPT.json`, SHA256 `efe95f0702bde812619df84098e6d56a48434faba5735500c34b746ea5b1f956`; final independent acceptance SHA256 `29a1064c41609351fa6a8e2c9145e50da68b8b3fae80832eeba5077ee9b3cafd`. Reconciliation TEST3up applied once;2nonresetting cases/41assertions passed with cleanup/preservation, now51tables/61history, window closed. No Main Images migrations/storage/activation, served browser/FPM or capacity acceptance is claimed. K7 display is now accepted/locally committed at `d44ecb6`; [complete Catalog §27](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) governs current K8/runtime acceptance. Both reviewed QA/conditional Main resource scopes are human-approved but not executed by this owner; Root owns private-profile review and QA-first execution. Remaining manager work is mapped in [Images blueprint §23](IMAGE_ASSIGNMENT_PHASE_1_EXECUTION_BLUEPRINT.md#23-current-master-remaining-images-handoff--1-november-2026), not a repeat of this accepted producer. The original before-manifest/release/preparation clauses below are provenance, not instructions to reimplement, initialize/reset TEST or replay an old lease.

**Goal:** prepare owned ready images and assign one to an existing Group/Product or a newly created Group through current native editors, with honest persistence/recovery and a stable projection for later K7 display.

**Architecture:** retain the accepted source → fenced preparation → ready Asset/Media → locked owner assignment lifecycle. Add native image fields to current aggregate writers; keep their ordinary field paths intact. No new repository, generic save platform, event framework, queue or alternative Product/Group editor.

**Stack verified:** PHP8.4, Laravel13.35.0, Filament5.9.0, Livewire4.4.7, Pest5.3.0, Media Library11.23.9, Spatie Image3.9.7. No dependency changes. Boost search-docs is unavailable; installed source and official versioned documentation were checked. Filament Examples returned an ordinary Doctor FileUpload example; it is not evidence for this application's transaction/receipt lifecycle and is not copied.

**Specifications:** existing Images plan and blueprint remain authoritative for settled validation/storage/lifecycle choices. This package supersedes their old broad P0–P6 integration order only for the minimum producer. Historical worktree evidence stays historical. Current-source manifest and before hashes: `/private/tmp/minimum-images-plan-20261101/PROPOSED_SOURCE_MANIFEST.json`; baseline: `BASELINE.json` in that directory (731 functional source/dependency pins, 42 pending entries; macOS metadata excluded).

## 1. Finite boundary and protected contracts

Deliver minimum foundation + ready owned Group/Product assignment + native individual fields + actual I3a completion proof. Then stop for one actual execution review and findings closure; only afterward prepare K7 catalog image DISPLAY and K8. Remaining Images manager comes after K7/K8, then Writing.

Explicitly defer CatalogImages Page/inspector, table/search/pagination/Results/Checked/Next/Previous/Save-and-continue, recipe/batch review/Apply, batch journals/tables, shared library manager, replacing a shared file, galleries/PDFs/other entities and real D60 access/import. Their settled designs are preserved, not reimplemented now. The image-only action and goal-check remain actual backend contracts with real persistence proofs; no artificial inspector is built to demonstrate them.

Product/Group resource tables, filters, drawers, create/edit structure and ordinary save behavior stay unchanged except the main-image field and directly related controls. No Product Create page is introduced. Group images are allowed on actual organizing Groups too; existing leaf ownership rules for Products/Configurator and the Group-card-properties drawer remain unchanged.

Protect GA1 optional raw-key dehydration/extraction (missing preserves, explicit[] clears), exact Group JSON/future data, tags/settings/hierarchy/Configurator and one aggregate revision lifecycle. Protect Product properties/Parts/Others, record+Group fingerprints and all broad BatchPreview/drawer guards. Protect GA2 g2/r2 object/list identity and legacy g1/r1 compatibility, Product d1/config/K6, exact values including `0`/`001`/whitespace/case/Unicode, literal Group names, attribute-before-tags order, full ancestry and active direct counts. No change to these Catalog consumers in this package.

## 2. Storage and schema: only the producer needs

Retain one `CatalogAsset` and nullable `groups.main_image_id` / `products.main_image_id`; do not put the pointers in mass-assignable fillable arrays. Add `mainImage(): BelongsTo` and integer casts to existing models without replacing relationships/casts. No backfill/inference from legacy product fields. Asset attachments are later.

Three additive migrations only (names fixed in manifest):

- `catalog_assets`: id; nullable created_by FK users/null-on-delete; source_kind varchar20 (`upload`/`folder`); source JSON; sha256 char64; nullable unique import_key char64; preparation_status varchar12 default preparing; nullable ULID preparation_token; nullable preparation_expires_at; nullable unique original_media_id FK media/restrict-on-delete; nullable verified_metadata JSON, error_code varchar64, error_detail text, prepared_at; timestamps.
- `catalog_asset_import_sources`: ULID id; nullable actor_id FK users/null-on-delete; source_kind varchar20; source JSON; unsigned scan_version default1 and scan_revision default0; status varchar20 default uploading; cursor/upload_ledger JSON; traversal_complete/uploads_sealed bool false; unsettled_uploads/file_count/visited_nodes unsigned default0; byte_count unsigned bigint0; nullable scan_token ULID/scan_expires_at, manifest_path varchar1024, manifest_sha256 char64, error_code varchar64/error_detail; timestamps.
- Nullable main_image_id FKs on Groups/Products, restrict-on-delete. Existing Media schema is reused; no new Media table or batch table. Schema names/FKs are checked against the actual authorized target before applying; table count is not authority to migrate.

`CatalogAsset` implements existing Spatie HasMedia/InteractsWithMedia, guarded[*], hides source/attempt internals. Register `preparation-attempts` and `original` on catalog-assets disk; only the verified winner becomes original. Keep originals and abandoned attempts for operator-managed cleanup; no user Delete/library action or automatic purge. Source retention never implies app access to arbitrary folders. Both models keep the archive guarded writes/casts and exact source/status transition validation; no new cleanup command or delete policy is exposed.

Two local disks, no global Media behavior rewrite:

- `catalog-asset-imports`: private `storage/app/private/catalog-asset-imports`, throw=true. Originals, immutable manifests and working copies here. Initial folder is an allowlisted read-only import source, never final storage.
- `catalog-assets`: public `storage/app/public/catalog-assets`, URL `<APP_URL>/storage/catalog-assets`, throw=true. Asset-only custom path generator uses `<media-id>/`, conversions subfolder. Existing public/storage link already points to Main's public storage; do not recreate it or borrow it in tests. Activation/storage rollout has its own explicit scope.

Accept JPEG/PNG/WebP only, positive bytes ≤3,145,728 and positive width×height ≤25,000,000, actual decode required. Preserve original and non-upscaled contain `Fit::Max` conversions up to160/480/960, asset-only `nonQueued()`. Existing default GD/media settings remain. Source cap2000 files/500MiB, traversal4000 nodes/depth16; source steps at most100 new nodes,10 hashes,16MiB and a500ms soft scheduling budget (not a hard timeout). Claim60s with existing owned renewal/fencing; actual worst-case decode/time/memory proof must establish viability before activation. No hidden lower limits or fallback jobs.

Config feature defaults off; real source aliases default empty. Do not register/read D60 while implementing or testing. Synthetic source folders and generated files prove the workflow. Keep the later review5000/Apply50/paging defaults in the overarching plan, not as minimum UI or extra schema.

## 3. Service ownership and exact interfaces

Reuse the archive implementation as a read-only design/source reference, never wholesale overlay it. Archive root is `/Users/studioycm/.codex/worktrees/catalog-images-p0-p1/configurator` at244b305; current Master owns shared files. New concrete services autowire; no bindings/singletons are required.

| Owner | Contract and adapted responsibility |
| --- | --- |
| `App\Services\CatalogAssetTargets` | `profile(string):array`, `eligibleQuery(User,string,array):Builder`; only `group.main_image`/`product.main_image`. Use current `ItemListMembers::query`, not archive ItemLists UI definitions. Empty context admits authorized admin records; exact supported list_key+parent_id scope is checked each time. Group-card-properties resolves its one leaf Group, never property-key IDs. |
| `CatalogAssetSources` | `startUpload`, `reserveUpload`, `commitUpload`, `sealUploads`, `startScan`, `scanNext`, `finalize`, `status`, `manifest`, `selectedEntry`, `preview` retain archive signatures. Own actor/context, source journals, short claims, immutable artifacts and classified progress. No file decoding in owner transactions. |
| `CatalogAssetSourceFiles` / `CatalogAssetFiles` | Confined local observations versus bounded copying/actual image decoding. Realpath containment, no symlink/traversal/client path, growth check reads at most cap+1, source identities+hash rechecked. No arbitrary path or URL fetching. |
| `CatalogAssetStorage` | `prepare(User,array $sourceChoice,string $targetKey,string $importKey):array`; sourceChoice exact source_id/scan_version/entry_id/sha256. Independently committed import-key identity and per-attempt token/Media/files; SQL publishes exactly one verified winner. No owner pointer write. Existing ready/busy/paused/failed outcomes and safe correlation reporting retained. |
| `CatalogAssetPathGenerator` | Asset-only Spatie PathGenerator; no global path changes or file movement. |
| `CatalogAssetAssignments` | `fingerprint(Model):string`, `validatedAsset(string,int):CatalogAsset`, `applyToLockedRecord(Model,CatalogAssetChange):bool`. Pure in-memory pointer mutation under caller's owner lock, validated ready winner/metadata/disk/target. It does not save or advance revisions itself. |
| `CatalogMainImageEdits` | `draftHash(array):string`, `stageSource(User,array,array):array`, `prepare(User,array,array,string):array`, `resolve(User,array,array,string,string):CatalogAssetChange`, `reconcile(User,array,array,string,string):array`. Actor/context/source/full raw draft-bound encrypted receipts; no generic operations table. Resolve verifies source, actor, target and ready-winner evidence before returning trusted change. |
| `App\Actions\SaveCatalogMainImage` | Retain focused `handle(User,string,int,array,string,?CatalogAssetChange,?Closure=null):Model` domain API. Use exactly one trusted change source: a direct internal DTO or a SQL-only resolver callback. Native callbacks resolve receipts under the fresh owner lock/CAS before core fill; raw client asset IDs never become a change. Fresh actor/Gate/current membership, locked owner/full fingerprint and one pointer+revision transaction; cannot save profile/settings/attributes. Future manager adapter will call this real action. |
| `CatalogAssetDisplay` | `forIds(array):array` ready-winner metadata projection for native field previews and later K7. Zero IDs means0queries; one joined Asset/Media query per ≤500 UNIQUE identities, bounded chunks beyond that, keyed results. Validate scalar positive IDs before dedup; no 500-input-before-dedup or limit500 silent truncation. No filesystem/source probes or per-card relations. |

`CatalogAssetChange` stays a small readonly internal value (`keep|set|remove`, assetId only for set), not a client authority token. Preserve existing source/upload/Save receipts as the external evidence boundary. `resolve` must reject wrong actor/source/owner/full raw draft, 24-hour receipt/source-token expiry, changed source/Media/winner and unexpected keep/remove source data. No public action accepts a client-supplied ready ID directly. Full raw draft hashing must retain exact typed values and supported temporary-file identity; never hash file contents into Livewire state or reuse authoring trim/coercion for Group facts.

Error handling retains stable stage/outcome/code/message/correlation and no internal paths/exception details in UI; 401/403/419 or systemic file/DB failures pause/deny without pointer changes. A failed journal write cannot be reported as a durable failure row. No jobs/failed-jobs mechanics are added to this foreground path.

Source recovery retains exact-source/version/context: lost scan/finalize response uses `status()` and published artifact state, then the next explicit bounded step; no restart or reupload by guessing. Active uploaded/scanning and failed/paused owned sources stay reachable by their stable ID after reload. Source eligibility filtering precedes any list limit, and a selected stable ID is inspected directly under that scope. Scan replay/cursor revision and competing retry ownership are enforced in SQL; an old lease cannot publish/delete a newer attempt. Bytes/conversions prepare outside owner locks, using attempt-isolated Media and files.

## 4. Native field and one ordinary Save

**Field:** `Filament\Forms\Components\FileUpload`; Docs https://filamentphp.com/docs/5.x/forms/file-upload; Validation JPEG/PNG/WebP max3072KiB plus shared authoritative bytes/pixels/decode; Config single `image()->acceptedFileTypes([...])->maxSize(3072)->storeFiles(false)->dehydrated(false)`. Do not attach user uploads directly to an owner or give FileUpload the final managed disk. Its temporary upload is actor/host/owner-or-creation-session bound, then copied into private intake and prepared through the services above.

**Layout:** existing `Filament\Schemas\Components\Group`, columnSpanFull; a focused `Filament\Schemas\Components\View` for current/proposed image, states and inline finder. `Hidden` intent controls are nondehydrated transport; read their raw state only through the focused adapter. No ordinary field/schema moved. **Actions:** directly related `Filament\Actions\Action`/inline field controls Upload, Find in folder, Remove, Revert; auth manage-catalog plus current owner membership on each operation. Remove drafts a null pointer, never deletes storage. Revert removes only image draft. No standalone global Save-image or Next UI is added to normal editors.

Finder selects an allowlisted folder or eligible owned source, advances short scan requests on screen, displays exact filename candidates and explicit ambiguity/missing outcomes. Product's optional `profile_fields.product_pic` is only a filename-reference hint: basename without extension, exact case-insensitive stem initially, optional case sensitivity; do not change product properties or infer model-family imagery. Ambiguity never chooses first. Source → chosen candidate → proposed thumbnail is visible; actual pointer changes only on normal Save/Create. Advanced recipes and batch matching remain later. A finished single upload is automatically staged/sealed/scanned as its one proposed candidate; there is no additional Use-upload/second Save gate. Upload/scan/preparation states are visible and unfinished work blocks final Save. Superseded upload replies cannot replace a newer proposal.

One save intent from button/Enter/mod+s is intercepted by the component-scoped `catalogImageEditor` (registered via current idempotent Alpine registrar). For an actual image draft it invokes `prepareImageSave()` outside transactions; this calls `form->validate()`, not `getState()` (installed `getState` dehydrates and can save relationships). Once ready, call the original native method exactly once with original arguments. Direct unprepared image Save fails before mutation. No-image intent delegates normal flow without preflight/receipt/no-ambient restriction. Client generation+raw draft/context change invalidates preparation and blocks obsolete completion; only a current reply may clear the image draft or mark the editor saved.

Upload endpoint: POST `/catalog-images/native-upload`, name `catalog.images.native-upload`, existing auth/web middleware plus signed relative URL and actor/host/nonce/context-bound ticket; retain the native parent upload handler and CSRF/signature validation. Source preview: GET `/catalog-images/source-preview/{source}/{version}/{entry}`, name `catalog.images.source-preview`, auth+signed relative URL, Gate/owned source and immutable entry evidence, private/no-store response. No other route/controller or public source path is exposed.

Native raw-data adaptation must preserve GA1 key presence. Strip only image transport before current strict core/settings whitelists; do not reconstruct other form state. In CreateGroup/EditGroup, keep current CatalogRevisions::batch aggregate, CatalogAttributeFingerprint, settings extraction and error/stale mapping. Extend SaveCatalogGroup with optional change/fingerprint parameters after existing arguments, comparing against the freshly locked record before pointer mutation. Keep Configurator-ID participants before lockGroups and the reassigned Configurator guard. Extend SaveCatalogProduct with optional change after its current record+Group fingerprint arguments, applying the pointer before its existing one save/revision step. Unchanged arguments/keep defaults preserve all existing non-image callers.

## 5. I3a: supported actual native completion, not notification-only repair

Verified installed ordering (source is the API authority): EditRecord::save begins optional transaction → beforeValidate → form getState/afterValidate/beforeSave → authoritative update → afterSave/framework RecordUpdated/RecordSaved → commit → rememberData/Saved notification/redirect. CreateRecord::create(bool $another=false) similarly commits before rememberData/Created notification/create-another reset/redirect; its afterCreate and framework events precede commit. Current custom editor afterSave refresh/refill/events/drawer completion therefore need image-path deferral. No application listeners for framework record events are introduced; no vendor Save/Create body is copied.

Select this bounded adapter:

1. Existing-image-change paths only: public native `save(bool $shouldRedirect=true,bool $shouldSendSavedNotification=true):void` on the two Edit pages and `create(bool $another=false):void` on CreateGroup delegate to parent. Preserve all arguments and native embedded create-another restriction. At entry, actual set/remove/upload-in-progress invalid intent must validate/deny; keep/omitted/no image draft goes straight to current parent behavior.
2. For a real prepared set/remove request, require the relevant owner connection at transactionLevel0 before parent invocation. Set an operation-local protected flag in try/finally; `hasDatabaseTransactions():bool` returns true only for that image-write invocation, otherwise parent. No panel/global transaction change. `beforeValidate` requires active invocation and matching prepared receipt for an image draft, blocking alternate inherited entry bypass. Do not use the source preparation service inside the write transaction.
3. Since this selected path owns the outer transaction, parent's inherited completion now follows a real commit without overriding its notification/create-another internals. In current afterSave/afterCreate, register only application image completion/refresh/refill/events/drawer effects through the owner connection's afterCommit, fenced to saved host/record-or-creation-session/owner-context/full raw draft. Ordinary path retains its existing code and tests. Domain revision batching remains inside the write transaction. Rollback discards callbacks and retains draft/error; do not emit success before commit.
4. Guard browser completion with the same host/session/draft generation. A newer ordinary or image draft must survive an older response; stale saved/create events cannot reset a new create-another session. Native create(true) resets once after confirmed commit; embedded create(true) remains denied. Upload tickets/source replies for a rotated creation session are rejected.
5. Existing ordinary form calls, keeps, explicit native save flags and default-off feature behavior must remain compatible, including accepted RefreshDatabase tests with an outer test transaction. Only newly introduced image-enabled true-commit tests use the narrow independent-commit partition. Do not weaken existing guards, make every native test nontransactional, manually escape a test transaction, override the shared SplitCreateRecord, or introduce a general completion service.

I3a native CODE+TEST proof is accepted: actual Group Create/Edit and Product Edit methods use real signed HTTP test-client FileUpload/intake/prepared Asset and installed parent Save/Create; commit/rollback/create-another and installed Livewire pre-effects lifecycle cases passed. These are server/source-map tests, not served browser/FPM acceptance, which remains unrun. If the chosen adapter cannot satisfy commit/rollback/create(true)/newer-draft semantics, stop that slice with the reproduced limitation; do not accept an unassigned-asset-only producer or silently bypass native lifecycle behavior.

Native full-form lost Save/Create response always remains `review_required`, even if image matches. No metadata save attribution, automatic replay, notification or create retry. The image-only backend `reconcile` instead reports `goal_matches | baseline_matches | conflict` plus a fresh authorized fingerprint where applicable: 'Image matches your choice' does not mean 'this request committed'. It performs no writes. Future manager continuation must revalidate exact current draft/context/destination and consume/recheck that fingerprint; that UI is deliberately deferred. Keep/no-op remove/someone else meeting the same image goal never prove an earlier Save happened.

## 6. Work packages and discriminating proofs

- [x] **M1 — schema/intake/ready asset.** Generate only listed new classes/factories/migrations with installed `php artisan make:* --no-interaction` after release; do not regenerate existing files. Build two tables/three additive migrations, disks, registry and bounded source/preparation services. Red/green feature tests: exact3MiB and+1, exact25MP and+1 with other bounds valid; header-valid undecodable data; growth/path/symlink/foreign actor/expired scope; interrupted upload/scan/finalize response recovery; same import key replays same asset, competing attempts only one winner and stale claimant cannot affect winner; original/conversion bytes remain outside owner transaction. No owner pointer yet does not finish minimum.
- [x] **M2 — actual individual assignment + native I3a.** Implement receipt/assignment/native field adapters semantically on current files. Actual action proves set/replace/remove/keep and one owner+affected revision publication while preserving every unrelated field; unchanged image causes no revision churn. A stale fingerprint, source/winner change or mutation failure rolls back pointer and all aggregate data. Native completion checkpoint then proves actual FileUpload→private source→prepare→ordinary Save→fresh reload for Product Edit, Group Edit, Group Create and create(true). Inject a throwing precommit RecordSaved/RecordCreated listener to prove rollback suppresses actual custom and inherited success/reset/redirect; no mock afterSave-only claim. Observe callbacks at tx0 and raw event/notification/redirect/session counts. Test original Save flags, embedded create denial, guarded direct Save and new local draft/session arriving before old completion.
- [x] **M3 — recovery/projection/acceptance.** Real image-only Save + lost response goal check, no-Save keep and already-empty remove, another writer satisfying goal, intervening writer after inspect and before future completion boundary, revoked actor/deleted or reassigned owner, changed draft/source/Media. Without inventing a Next UI, the intervening-writer case calls the actual reconcile API again and checks its fresh conflict/fingerprint result; the future manager owns continuation integration. Inspection makes0writes and never claims operation attribution; native full save remains review_required. Actual reciprocal cases: Group image-only save keeps catalog_attributes incl0/001/whitespace/future keys, ordered tags/settings/parent/Configurator; ordinary Group aggregate save keeps pointer; Product image-only keeps exact properties/Parts/Others and ordinary Product edit keeps pointer. Verify broad BatchPreview/drawer stale behavior with the actual new pointer/revision. Projection uses explicitly synthetic ready-winner metadata fixtures for bulk query counts and one actual prepared/saved native image for real roundtrip evidence; it does not claim501physical files were created. Projection0/1/200/501unique/>500repeated IDs is complete and chunk-bounded, no filesystem probes, ready-corrupt/missing fallbacks truthful. Stop after actual whole-package review; no K7 execution here.

Meaningful test paths are fixed in the manifest. Reuse the archive tests for relevant scenarios but adapt their fixtures/expectations to current writers rather than copying assertions or running its broad old suite. Add only these seven new zero-ambient files to current tests/Pest.php independent-commit list: CatalogAssetFoundationTest, CatalogAssetSourcesTest, CatalogAssetStorageTest, CatalogMainImageAssignmentTest, CatalogMainImageRecoveryTest under Feature/Catalog, and CatalogMainImageFieldTest/CatalogMainImageCompletionTest under Feature/Filament. Display tests keep the ordinary transactional partition. The seven are SQLite :memory: only: exclude them unconditionally from the default RefreshDatabase file scan, and register DatabaseMigrations for them only when the already-guarded environment explicitly binds DB_CONNECTION=sqlite and DB_DATABASE=:memory:. On another engine, bind a pre-test denial for just these files with no database-reset trait (`pest()->extend(TestCase::class)->beforeEach(fn () => throw new LogicException("SQLite-only Images commit proof"))->in(...$imagesCommitTests)`; installed Pest UsesCall::beforeEach/in are verified), so a late skip cannot run after DatabaseMigrations. Preserve all existing list entries and guard/profile code. The two new MySQL files are never DatabaseMigrations tests; only the current nonresetting partition and separately scoped engine window apply. New MySQL contenders join the existing nonresetting partition and current private target guard. No tests/TestDatabaseSafety/profile/helper/XML changes are proposed.

## 7. Completed producer execution and remaining resource boundary

Planning runs no Artisan, tests, migrations, build, browser or app boot. Generators later: existing installed `make:model` with factory for the two models; `make:migration` with the exact table/column names above; `make:class` for the listed concrete classes/DTO; `make:controller` for two controllers; `make:test --pest` (no Feature/ prefix in name) for the listed tests. No Filament Page/Resource generator.

Historical generation commands used for the delivered producer; do not regenerate committed files:

```bash
php artisan make:model CatalogAsset --factory --no-interaction
php artisan make:model CatalogAssetImportSource --factory --no-interaction
php artisan make:migration create_catalog_assets_table --create=catalog_assets --no-interaction
php artisan make:migration create_catalog_asset_import_sources_table --create=catalog_asset_import_sources --no-interaction
php artisan make:migration add_main_image_id_to_catalog_records --no-interaction
```

Rename only newly generated migration files to the exact approved manifest names, keeping Assets → sources → owner FK order. The two model commands already generate their factories. For each listed new Services/Actions/DTO class and CatalogMainImageField use installed `make:class` with its exact namespace-relative name; use `make:trait Filament/Resources/InteractsWithCatalogMainImage --no-interaction` for the image adapter; the two HTTP controllers use `make:controller CatalogMainImageUploadController --no-interaction` and `make:controller CatalogAssetSourcePreviewController --no-interaction`. Use `make:test --pest Catalog/<listed test basename> --no-interaction`, `Filament/<basename>` and `Catalog/MySql/<basename>` respectively. JS/Blade/config files have no PHP generator. Existing files are edited directly, not regenerated. Fresh PHP headers/classes must match siblings; generators do not authorize migrations or app activation.

This package used the released guarded SQLite-memory/scratch route, then the separately reviewed exact TEST-only3up+2nonresetting window. TEST is now51tables/61history. Its additive migrations and engine proof are complete; no migration replay,49-table initialization/reset or old lease reuse is needed. Future affected checks need only their newly source-bound finite release. Main DDL, managed/private storage, actual served browser/FPM/capacity and conditional activation remain separately named resource operations in the K7 rollout plan. No new DB/site/worktree is the default.

Run narrow files for each changed seam; meaningful existing non-image GroupAttributesForm, GroupSettings, Product editor and ItemDrawer tests only when their adapters change. Do not replay GA1/GA2/K1–K6 whole suites. Run focused Node `CatalogImageEditor.test.js`, appropriate PHP lint, required Pint/FilaCheck for changed Filament files, scoped diff and necessary frontend build. Functional FileUpload/native request/Enter/mod+s/newer-draft behavior needs actual existing integration/JS evidence; no new browser framework/site, generic cosmetic/mobile or200%zoom gate. Preserve installed Livewire upload handler signatures and non-image upload delegation; do not modify global payload limits/security middleware. Real-runtime claims need actual observed evidence and that runtime's explicit scope. File/byte/hash/request/decode/time/memory/lease margins remain capacity evidence, not guessed numbers.

### 7.1 Original resource envelope — TEST fulfilled; Main/runtime unrun

| Step | Resources actually needed later | Exclusions / admission |
| --- | --- | --- |
| Code and fast proof | Current Main5497c09 checkout, proposed51 paths; existing guarded SQLite :memory: launcher; per-run fake imports/assets/Livewire-temp roots under `/private/tmp/configurator-images-minimum/<run-id>/` | No app env rewrite, Main storage roots, new site/worktree/DB or source folder borrowing. Freeze actual scratch roots in the released run; no resource work now. |
| Existing TEST additive schema | Current coordinator-owned49-table TEST target; execute only actual three new migrations (two tables plus two nullable pointers/FKs), existing Media table | Root must bind its exact current target/profile/driver and new additive window after reviewing these migration files. GA1 initialization/reset authority is closed and not reusable. No migrate:fresh/drop/reseed or new database. |
| Actual MySQL contenders | The two named nonresetting test files; existing guarded TEST + separately scoped engine/private-capability window; isolated run-tagged source/assets/Media/owners and bounded cleanup of only those fixtures | No automatic reset trait/helper or pre-existing record deletion. Verify current schema/authority before connecting; state which preparation/owner interleaving each separate process exercises. No guard/profile/env relaxation. |
| Main activation, later | Actual reviewed three additive migrations, nullable pointers only; managed/import disk roots and feature flag/aliases only in an explicitly named rollout scope | No Main image content/backfill, D60 access, link recreation or storage writes follows source approval. Existing public/storage is recorded as a read-only observation. |

A resource mismatch is reported at the affected execution step; it does not reopen settled product choices or authorize a new isolated setup. Capacity/runtime claims use the actual authorized target. Queues, failed-jobs cleanup, shared library and remote systems need no resources here because they are not implemented.

## 8. Adaptation audit and remaining decisions

No new human product choice is needed: one image, public managed copy/private retained source, formats/limits, explicit Save, fields in native editors, manager later and shared library phase2 are settled. Root clarified the minimum excludes even a small CatalogImages inspector. Native transaction adaptation is image-change scoped, not an unconditional new restriction on ordinary saves.

Bounded material/source and actual CODE+TEST acceptance, native lifecycle proof and2/41preparation/owner contender proof are complete. `MIN-SOURCE-RECOVERY-001` and `MIN-FILE-CONFINEMENT-PROOF-001` are independently closed. Still unrun: Main3up/storage/flag rollout, actual served browser/FPM and measured allowed-input time/memory/lease margins. A CODE+TEST PASS is not Main/runtime/push or overall feature acceptance.

Carry forward resolved GA2 findings: strict raw r2 value collections are JSON arrays before decode; literal Group names use callback replacement; SSR description/legacyId preserve0. Carry GA1 narrow attribute CAS and optional extraction/atomic batches to the real image adapter. Future K7 must extend actual GA2 card/projection fields rather than reconstructing metadata or changing its query/URL semantics. It uses this ready owned producer, not folder paths, import journals or a demo family-code mapping; projection accommodates >500 repeated/unique IDs without silently truncating existing result sets.

## 9. Verified references and source basis

- Official FileUpload temporary storage: https://filamentphp.com/docs/5.x/forms/file-upload ; installed BaseFileUpload::storeFiles/saveUploadedFiles.
- Native creation/parameters/reset: https://filamentphp.com/docs/5.x/resources/creating-records ; installed CreateRecord::create. Native edit: https://filamentphp.com/docs/5.x/resources/editing-records ; installed EditRecord::save.
- Transactions: https://filamentphp.com/docs/5.x/panel-configuration#database-transactions ; installed CanUseDatabaseTransactions::hasDatabaseTransactions/begin/commit. Laravel transaction callbacks: https://api.laravel.com/docs/13.x/Illuminate/Database/Connection.html#method_afterCommit .
- Installed Schema HasState::getState saves relationships/dehydrates; validate-only preflight avoids that path. Livewire4 WithFileUploads::_startUpload/_finishUpload and signed FileUploadController::handle are checked installed dependencies; native upload controller must retain those signatures and parent validation/signature protection.
- Source inspection, current commit and Composer versions are verified. No implementation, engine/runtime/browser/capacity pass is claimed. Direct original instructions and later coordinator scope outrank older historical files.

## 10. Exact proposed source scope

The following51 source/test paths are proposed, not changed. Only 15 existing paths receive semantic additions;36 are new. Before hashes/ABSENT markers and the current731-pin baseline are in the private manifest; no archive overlay. Any demonstrated extra path returns as a small material delta.

| Path | Change |
| --- | --- |
| `app/Models/CatalogAsset.php` | New |
| `app/Models/CatalogAssetImportSource.php` | New |
| `database/factories/CatalogAssetFactory.php` | New |
| `database/factories/CatalogAssetImportSourceFactory.php` | New |
| `database/migrations/2026_11_01_010001_create_catalog_assets_table.php` | New |
| `database/migrations/2026_11_01_010002_create_catalog_asset_import_sources_table.php` | New |
| `database/migrations/2026_11_01_010003_add_main_image_id_to_catalog_records.php` | New |
| `config/catalog-assets.php` | New |
| `app/DTO/CatalogAssetChange.php` | New |
| `app/Services/CatalogAssetTargets.php` | New |
| `app/Services/CatalogAssetFiles.php` | New |
| `app/Services/CatalogAssetSourceFiles.php` | New |
| `app/Services/CatalogAssetSources.php` | New |
| `app/Services/CatalogAssetStorage.php` | New |
| `app/Services/CatalogAssetPathGenerator.php` | New |
| `app/Services/CatalogAssetAssignments.php` | New |
| `app/Services/CatalogMainImageEdits.php` | New |
| `app/Services/CatalogAssetDisplay.php` | New |
| `app/Actions/SaveCatalogMainImage.php` | New |
| `app/Http/Controllers/CatalogMainImageUploadController.php` | New |
| `app/Http/Controllers/CatalogAssetSourcePreviewController.php` | New |
| `app/Filament/Resources/CatalogMainImageField.php` | New |
| `app/Filament/Resources/InteractsWithCatalogMainImage.php` | New |
| `resources/js/catalog-image-editor.js` | New |
| `resources/views/filament/forms/catalog-main-image.blade.php` | New |
| `tests/Feature/Catalog/CatalogAssetFoundationTest.php` | New |
| `tests/Feature/Catalog/CatalogAssetSourcesTest.php` | New |
| `tests/Feature/Catalog/CatalogAssetStorageTest.php` | New |
| `tests/Feature/Catalog/CatalogMainImageAssignmentTest.php` | New |
| `tests/Feature/Catalog/CatalogMainImageRecoveryTest.php` | New |
| `tests/Feature/Catalog/CatalogAssetDisplayTest.php` | New |
| `tests/Feature/Filament/CatalogMainImageFieldTest.php` | New |
| `tests/Feature/Filament/CatalogMainImageCompletionTest.php` | New |
| `tests/Feature/Catalog/MySql/CatalogAssetPreparationConcurrencyTest.php` | New |
| `tests/Feature/Catalog/MySql/CatalogMainImageConcurrencyTest.php` | New |
| `tests/Unit/CatalogImageEditor.test.js` | New |
| `app/Models/Group.php` | Modify |
| `app/Models/Product.php` | Modify |
| `config/filesystems.php` | Modify |
| `config/media-library.php` | Modify |
| `routes/web.php` | Modify |
| `app/Actions/SaveCatalogGroup.php` | Modify |
| `app/Actions/SaveCatalogProduct.php` | Modify |
| `app/Filament/Resources/Groups/Schemas/GroupForm.php` | Modify |
| `app/Filament/Resources/Products/Schemas/ProductForm.php` | Modify |
| `app/Filament/Resources/Groups/Pages/CreateGroup.php` | Modify |
| `app/Filament/Resources/Groups/Pages/EditGroup.php` | Modify |
| `app/Filament/Resources/Products/Pages/EditProduct.php` | Modify |
| `resources/js/app.js` | Modify |
| `lang/en/catalog.php` | Modify |
| `tests/Pest.php` | Modify |

## 11. Delivery and remaining rollout

Root TEST execution receipt `/private/tmp/minimum-images-execution-20261101/ROOT_TEST_EXECUTION_RECEIPT.json`, SHA256 `f6ddc748e557c8e2c8c726849c76c2554605cb6209af035591decb9953b6fbea`, records the closed51-table/61-history TEST state and preserved baseline. Native40/641, new filesystem5/44 and12Node correction snapshots support the accepted source; overlapping earlier snapshots are not added into a fictitious suite total. Root's final acceptance carries the actual runtime/capacity/Main gaps.

The current next source package is [K7 managed Catalog image display](CATALOG_K7_IMAGE_DISPLAY_EXECUTION_PACKAGE.md). It consumes this producer; it does not re-open native authoring/locks/recovery, add the deferred Images workspace or import D60. Its R-QA/R-MAIN envelopes separate genuine new resource operations from routine source implementation. No new source/resource operation is authorized by this status amendment.
