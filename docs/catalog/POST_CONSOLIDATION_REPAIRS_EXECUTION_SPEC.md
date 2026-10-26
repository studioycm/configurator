# Post-consolidation repairs: execution specification

**Status: proposed for human approval. No application implementation is authorized.** Prepared 26 October 2026, Asia/Jerusalem.

**Independent review:** Cross-worktree integration audit completed a bounded read-only review and re-review; no remaining approval-blocking contradiction was found. This is specification readiness only. All implementation, migrations, application regressions, capacity measurements and runtime acceptance below remain unexecuted. The proposed nullable export-version marker is part of the requested human specification approval.

**Goal:** repair SA-01, RL-02/RL-01 and AD-01 while preserving current application behavior, other tasks' work and the accepted consolidation contracts.

**Architecture:** keep `CatalogAvailability` as the visibility authority. Give canonical data exchange one central policy owner, small CSV/artifact mechanics and scoped native-framework adapters. File evidence determines readiness; SQL counters remain an accounting projection. Keep the existing Rules pipeline independent.

**Baseline:** clean local master `541586a3d77c78cf3fb2e55f34b5d4b92041ab45`, `/Users/studioycm/Herd/configurator`. PHP 8.4, Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7, League CSV 9.28.0, Pest 5.3.0. Installed source and Boost were inspected. `.ai/rules` is absent. Refresh the actual implementation checkout before execution.

**Inputs:** [current audit](MASTER_APP_AUDIT_2026_10_26.md), [original consolidation specification](APP_CONSOLIDATION_EXECUTION_SPEC.md), [shared journal](CONSOLIDATION_CHANGELOG.md), and [catalog/Images handoff](CONSOLIDATION_HANDOFF_FOR_CATALOG_IMAGES_REVIEW.md). Earlier test results establish their recorded baselines, not passage of these new regressions.

## 1. Decisions, scope and approval boundaries

| ID | Agreed contract |
| --- | --- |
| P01 | Steps 5–7 belong to Plan bulk creation forms, with one implementation writer and independent read-only review by Cross-worktree integration audit. No Astra: Sol 6.1 medium for research, high for auditors, max for monitors. |
| P02 | Default order is SA-01, then RL-02/RL-01, then AD-01. AD-01 has no CSV dependency and may be completed as an independent checkpoint while CSV design/review proceeds. Code edits remain sequential; parallel work is read-only. |
| P03 | CSV A: new canonical CSV uses standard doubled quotes and an empty proprietary escape. Validate the entire selected CSV structure before dispatch/writes; reject nonstandard quoting with a useful explanation. No Legacy mode or dialect guessing is approved. |
| P04 | Readiness B: new canonical exports use a sealed plan, verified immutable artifact evidence, truthful row accounting and independent CSV/XLSX readiness. Proposed implementation includes one nullable contract-version marker on the existing export record; no new durable database ledger/outbox platform is proposed. |
| P05 | Additional rules for partial results, historical files, retries and format independence are accepted below. Centralize these decisions so later changes do not require editing many adapters/jobs. |
| P06 | Investigate one queued streaming worker as an optional added execution mode using the same policy/evidence contracts. It does not replace readiness B, change the default chunked mode or block the mandatory repairs. Its implementation/activation gate is section 8. |
| P07 | Security and package/served-asset work are deferred until the human explicitly requests them. There is no automatic security phase after these repairs or the feature review. Preserve existing authorization and safety guards. |
| P08 | Step 8 belongs to Plan catalog configuration and UX for both Image Manager and catalog redesign. This task supplies the final verified SHA/contracts/evidence; the owner audits and proposes alignment. No automatic combined-P6 implementation, merge, P7–P10 or prototype changes. |
| P09 | No push, deployment, dependency change, production operation, source database reset, feature-worktree import, legacy restoration or broad structural refactor. Merge and feature integration require their own later authority. |
| P10 | This specification approval must precede application/test edits. Current authority covers preparing/reviewing this document and updating its planning records only. |

SA-01 preserves the current hidden versus disabled-but-visible distinction. AD-01 preserves stored values, compiler behavior and existing whitespace semantics. CSV A is the explicitly selected exception to the earlier no-acceptance-change default: previously accepted nonstandard quoting will be rejected rather than silently reinterpreted.

## 2. Working rules and review focus

- Read AGENTS, applicable `.ai/rules` if later present, relevant skills, sibling source and the journal before each checkpoint. Use installed-version Boost and verify native lifecycle seams rather than inventing hooks.
- After approval, reuse one suitable isolated consolidation lane based on accepted current master. The retained checkout at `222cf85` is older; refresh/reconcile it deliberately before implementation. Do not reset or import another task's pending work. Keep a clean primary checkout and explicit commit allowlists.
- Preserve all 88 pending Images files, its ignored runtime and the separate catalog prototype. Do not create implementation worktrees per finding. Do not run reset-based tests against an Images preview or the source database.
- Start each repair with a meaningful failing regression, then implement and run focused checks. Report failures honestly. Reviewers do not edit code. Read/update the shared journal with actual results and changed contracts.
- Never use an export/import fake as proof of generated bytes, physical commit behavior or browser success. No new browser/testing dependency is approved.

The highest-value review cases are: an authentic retained child snapshot after withdrawal; valid quoted text ending in a backslash versus malformed legacy quoting; preparation still discovering pages; storage failure after SQL accounting or after actual commit; and valid `"0"`/whitespace being replaced by a fallback. Each is assigned below.

## 3. Central policy and responsibility map

### One editable rule owner

**Proposed:** `app/Services/CatalogDataExchangePolicy.php`, a stateless concrete service resolved normally by Laravel. It owns named, readable CSV and export decisions. No competing config rule lists, browser flags or job-local policy copies.

| Rule group | Decisions owned in that file |
| --- | --- |
| CSV-01–04 | Standard-only mode, enclosure/empty escape, supported delimiter handling, grammar acceptance, complete pre-dispatch validation, preservation of quoted LF/CRLF and existing exact-text/spreadsheet rules. |
| EXP-01–03 | Sealed-plan requirement, artifact identity/layout/hash/count requirements, no missing or duplicated planned identities, and derivation of exported/rejected/unconfirmed counts. |
| EXP-04–06 | Legitimate partial results versus infrastructure failure; per-format pending/ready/failed/unconfirmed/no-data decisions; checked publication and independent workbook evidence. |
| EXP-07–09 | Conservative retry/reconciliation, qualified historical downloads, useful no-data feedback, and allowed notification/download actions for the assessed facts. |
| MODE-01 | Chunked default, optional single-stream availability and measured capacity gates. |

Use explicit named decision methods and centrally enumerated checks grouped by those IDs. This makes a rule easy to locate, alter or disable in one place. Mechanical parsing, hashing, streaming and queue handling live in the collaborators below; their callers pass evidence to the policy rather than repeating its decisions. Record a contract version in new artifacts; a meaning change increments that version and explicitly disposes of older artifacts.

Proposed public boundary: `csvControls(?string $delimiter = null): array`, `csvIssues(array $facts): array`, `assessExport(array $facts): array`, `retryDecision(array $facts): string`, and `executionMode(): string`. Add precise PHPDoc array shapes at implementation. Assessment returns per-format state/message codes/download eligibility and verified counts. Inputs explicitly include managed contract version, preparation seal, accounting commit outcome and each format's publication outcome; counts/hash alone cannot imply commit/publication. Retry results are reuse-verified-capture, retry-known-rollback, inspect-unconfirmed or deny; they never authorize rereading newer source data under an adopted identity. Execution modes are chunked (default) and single-stream (unavailable until its optional gate). Evidence is server-derived, never client-supplied. Policy is pure: it does not query, write files, send notifications or cache a user/export.

Availability remains centralized in `CatalogAvailability`. Native Gate/owner authorization remains authoritative at existing entrypoints. The exchange policy is not an alternate authorization system.

### Proposed file ownership

All new names are proposals in existing directory families, not files already created.

| Owner/path | Responsibility |
| --- | --- |
| `app/Services/CatalogCsv.php` | Streaming standard-grammar validation and configured Reader/Writer construction using the central policy. One grammar implementation, shared by validation/mapping/dispatch/output paths. No regex blacklist of backslash-quote. |
| `app/Filament/Imports/CatalogImportAction.php` | Scoped native ImportAction adapter; preserve native schema, upload, validation, mapping, options and job dispatch. |
| `app/Filament/Imports/CatalogFailedRowsCsvDownloader.php` | Empty-escape Writer plus native failure-content generation, retaining the no-failed-rows guard. |
| `app/Filament/Exports/CatalogExportFiles.php` | Private evidence/plan/page/workbook paths, checked I/O, immutable adoption and inspection facts. It does not decide UI readiness independently. |
| `app/Filament/Exports/CatalogExportDispatcher.php` | Scoped canonical dispatch/completion ordering; delegate unrelated exporters to native behavior. |
| `app/Filament/Exports/Jobs/PrepareCatalogCsvExport.php` | Capture and seal the selected record/page plan, checked standard headers, then dispatch page work. |
| `app/Filament/Exports/Jobs/ExportCatalogCsv.php` | Stream page data, preserve legitimate record rejection, capture immutable evidence and derive accounting under the export row lock. |
| `app/Filament/Exports/Jobs/CreateCatalogXlsxFile.php` | Build/verify a workbook from adopted CSV, with the same controls and checked publication. |
| `app/Filament/Exports/Jobs/CompleteCatalogExport.php` | One completion/notification owner, applying central assessment to actual evidence for each requested format. |
| `app/Filament/Exports/Downloaders/CatalogCsvDownloader.php`; `CatalogXlsxDownloader.php` | Reinspect the required artifact, apply the same assessment and retain native controller authorization. No uncontrolled on-demand workbook fallback. |
| Existing `CatalogDataActions`, `CatalogImporter`, `CatalogExporter`, `AppServiceProvider` | Select only canonical adapters and scoped bindings. Preserve existing Rules binding/dispatch/downloads and other exports. Use existing localization files for new explanatory/status text. |
| New Artisan-generated `add_catalog_artifact_version_to_exports_table` migration | Add nullable unsigned small integer `catalog_artifact_version` to existing `exports`; retain null for pre-contract records, no backfill or index. Record the generated timestamped filename at execution. |

No generalized repository, plugin registry, policy engine, new database table or universal export framework is proposed. The additive version marker provides classification, not a per-job recovery ledger. Reuse existing small proven primitives where their contracts match; do not clone Rules lookups, capture approvals or its complete recovery machinery. Keep each integration-only override bounded and characterized against the installed parent.

## 4. Step 5 — SA-01 availability

**Application owner:** `app/Livewire/Catalog/ProductConfigurator.php::render()`. After its fresh Product query, apply existing `CatalogAvailability::assertProduct()` before rendering facts/result. Keep `productId` locked. Do not place a public visibility assertion in shared `input()`/`evaluate()` and thereby change administrator Preview.

**Tests:** proposed `tests/Feature/Catalog/ProductConfiguratorAvailabilityTest.php`, with current `Catalog/PublicCatalogTest.php` and `ConfiguratorPreviewWorkspaceTest.php` controls.

1. Authenticate, GET the actual visible Product route, extract the genuine nested child's `wire:snapshot` by component identity and preserve its checksum/session.
2. Deactivate the Product or hide an ancestor, change identifiable facts/description, then POST an independent child `$refresh`, `refreshDefinition` or meaningful selection through `Livewire::getUpdateUri()`.
3. Assert the existing 404 contract and absence of newly rendered facts. Do not recreate the child after the change or fabricate its snapshot.
4. Cover normal visible and unassigned Product success, disabled-but-visible Configurator behavior, explicit hidden behavior, missing Product, and unchanged guest/locked-ID boundaries where applicable.
5. Assert administrator Preview continues under its existing separate render/input contract.

**CP5:** first expose the current failure, then pass the focused HTTP/component/Preview checks. No availability-policy redesign, persistence change or public image integration.

## 5. Step 6a — CSV A, exact acceptance and native adapters

### Accepted examples

Backslashes below are literal. The valid standard example must yield two data records:

```csv
import,semantic_id,label
1,example_a,"Pressure, path\"
1,example_b,Next
```

Labels: `Pressure, path\` and `Next`. The current proprietary escape merges them into one record. This standard doubled-quote example yields one label `He said "Open"`:

```csv
import,semantic_id,label
1,example_a,"He said ""Open"""
```

This nonstandard legacy form must fail structure validation before any import dispatch/write:

```csv
import,semantic_id,label
1,example_a,"He said \"Open\""
```

Current parsing retains those backslashes; changing only the escape permissively damages the text. Therefore empty escape is not itself a strict validator. Preserve correctly doubled quotes, literal backslashes, quoted LF and CRLF, commas, Unicode/BOM, existing delimiter choices, `001`, `0`, whitespace and intentional apostrophes. Do not add trimming, numeric coercion, a Legacy toggle or dialect guessing.

Validate enclosure transitions, doubled quotes, proper field closure and end-of-file under the selected delimiter. Preserve existing blank-record, missing-field, header mapping and row-validation contracts; do not silently add unrelated new acceptance restrictions. Diagnose row/column and correction without echoing the whole uploaded file. Validate the complete immutable uploaded bytes before import dispatch/catalogue writes, using streaming within existing upload/row limits; reuse the same delimiter/mode for subsequent stages. Existing temporary-upload I/O remains valid. Place validation at the upload/dispatch boundary, reuse only proof bound to the same bytes/controls, and avoid repeatedly scanning the entire file during unrelated renders.

### Actual extension points

Installed `ImportAction` creates Readers at lines 120, 169, 199 and 563. Each dynamically calls `getCsvDelimiter(?Reader $reader = null): ?string` before parsing. The scoped `CatalogImportAction` may configure the supplied Reader's escape before delegating delimiter handling; preserve null Reader calls and explicit/guessed delimiters. This is a version-coupled configuration seam, not an invented official `configureReader` API. Characterize every actual upload/header/schema/validation/count/dispatch route.

Keep `ImportCatalogCsv`, `ShouldQueueAfterCommit`, outer chunk ownership, participant planning and known-rollback/uncertain-outcome protocol. `CatalogImporter::getFailedRowsDownloader()` retains its `failedRows()->exists()` 404 and native controller ownership, then delegates to the scoped Writer/native failure generator. Preserve BOM and existing formula protection/restoration. Account separately for native `downloadExample` Writer: generate it through the same CSV mechanics or prove the current fixed examples conform; the Reader override does not configure that Writer.

Export headers, page Writers, failed-row output and workbook Readers use the same central controls. Scope `job()`/`getExportCsvJob()`, dispatcher and XLSX bindings to Value/Attribute/AttributeValue exporters. Keep unrelated/Rules paths untouched. Do not edit vendor or globally change filesystem `throw`.

**Tests:** proposed `tests/Feature/CatalogDataExchangeFilesTest.php`; preserve existing `CatalogDataExchangeTest.php` and import transaction tests.

- Drive actual streamed export/download bytes through upload, header mapping, validation, counting/dispatch, native import job and persisted read-back. Do not substitute row arrays or `ExportAction::fake()` for this proof.
- Cover the exact examples, both line endings inside quotes, adjacent backslash/quotes, empty/trailing cells, non-ASCII, explicit and detected delimiters, duplicate-header validation, native example download and rejection before any queued import/write.
- Exercise all three canonical families and resource/Data Exchange entrypoints with representative cases. Preserve codes, memberships, unmapped metadata, helper columns and the explicit spreadsheet-text restoration option.
- Download actual failed-row CSV with valid quoting and existing formula behavior, including missing/foreign-owner/no-failed-row denial controls.
- Read generated XLSX cells/rows using installed OpenSpout. Exact text and row counts must agree with the captured successful rows.

**CP6a:** CSV acceptance, policy/grammar and adapter characterization pass. RL-02/RL-01 share an integration surface: do not call the complete export repair accepted or release a half-migrated pipeline before CP6b.

## 6. Step 6b — readiness B lifecycle

### Private versioned evidence

Within the existing export-specific private directory, use a checked immutable plan/evidence contract:

- Header/index: version, export/exporter/actor identity, selected options and column/CSV contract, requested formats, planned page identities and a preparation-complete seal.
- Page plan: ordered unique selected record identities, page number and payload binding. Incremental planning may use private page-plan files; seal the complete index before dispatch/publication. File enumeration is not a plan.
- Adopted page evidence: export/page/payload identity, ordered successful identities and known rejected identities/reasons, processed/success/rejection counts, CSV hash/size and staged/published source references.
- Workbook evidence: export/contract and input evidence binding, independent workbook path/hash/size/row/layout facts and publication outcome.

No source row or entire dataset is buffered solely to create this metadata. Its facts prove captured artifact consistency/completeness, not that every source value represents one database instant. Metadata is private and never created from client flags.

### Non-downgradable format classification

Use the existing persisted export record as the authority: `catalog_artifact_version = 1` marks this managed contract; null identifies pre-contract/unmanaged historical records. The scoped canonical creator must stamp version 1 **before insert/dispatch**, including all resource and Data Exchange entrypoints. A bounded creating hook for canonical exporter classes may enforce this; the class list/version decision lives in the central policy. Do not mark Rules/unrelated exports. The new column is never filled from browser input.

An export marked 1 with absent, corrupt or unsupported metadata is unconfirmed, never historical. Unknown non-null versions fail closed. Removing a file, changing a filename or comparing timestamps cannot downgrade it. The migration leaves old rows/bytes untouched. During any future mixed-code rollout, describe outputs from pre-contract producers as unmanaged rather than invent chronological proof. Test both old-null and new-marked creation paths, the live creator hook and metadata deletion. Keep the column during ordinary code rollback; no automatic schema rollback/data clearing.

### Capture, adoption and accounting

1. Prepare the authorized selection and checked headers with the canonical contract. Seal the actual plan before page work can make the export Ready. Planning failure leaves the export unconfirmed; do not fabricate row rejections.
2. Stream each page to an attempt-specific private staged artifact, collecting successes and positively classified individual formatting rejections. File reads/writes, missing planned records/pages and infrastructure failures are not ordinary formatter rejections.
3. Check all writes, hashes, sizes, identity and layout. Under the existing export row lock, adopt immutable page evidence without overwriting a conflicting capture and derive SQL counters from the verified adopted facts. Do not blindly increment counters on repeated delivery.
4. Publish checked artifacts after the real enclosing commit. New prepare/page/workbook adapters retain after-commit dispatch semantics so a synchronous batch repository or enclosing caller cannot run them prematurely. SQL rollback cannot roll back filesystem operations. Preserve possibly adopted evidence across ambiguous exits. Reconcile exact identities/content before retry; never rerender newer database values under an old adopted page identity.
5. If evidence cannot establish the outcome, return unconfirmed and withhold the affected artifact. Existing-file presence, green counters or current database equality are not commit-attribution proof. Only finite retries of known rollback/transient failures are automatic; unknown business work is not replayed.
6. Workbook generation consumes the verified captured CSV, then publishes its own verified artifact. A definitely failed workbook may regenerate from that captured input. No silent current-database re-export.

`CatalogExportFiles` gathers facts; `CatalogDataExchangePolicy` decides readiness/retry/actions. The completion job and both downloaders use that same assessment. Missing, stale or conflicting new metadata cannot be reconstructed by assertion or from current database values.

Commit facts distinguish pending-enclosing-commit, known-rollback, known-committed and unknown. Publication facts distinguish staged, published, failed and unknown per artifact. Ready requires a verified committed accounting projection and published verified format bytes; a complete-looking private file during an enclosing transaction remains pending. The publication coordinator runs after a real level-zero commit, reads the committed export/evidence, and records the checked publication outcome. A later process may verify committed projection against adopted evidence without claiming which uncertain request committed; reconstruction of the projection never implies readiness before that publication check. Download/inspection requests are read-only and cannot repair evidence or publish files as a side effect.

### Results, formats and notifications

Let `N` be planned records, `S` verified exported records, `R` known individual formatter rejections and `U` unconfirmed records. Ready requires the sealed plan, valid required artifacts, no duplicated/missing planned identities, `N = S + R` and `U = 0`. SQL counters alone cannot establish those facts.

| Case | Central policy result |
| --- | --- |
| 100 selected, 100 exported | CSV Ready after verification; XLSX independently Ready after its verification. |
| 100 selected, 98 exported + 2 known rejections | Offer 98-row output, clearly completed with 2 rejected records. Preserve legitimate partial success. |
| 100 selected, 75 exported + 25 unconfirmed | Affected CSV is not Ready; no fabricated 25 rejected records or silent remaining-page download. |
| Counters green, required page absent/wrong/hash-mismatched | Not Ready. |
| CSV Ready, workbook pending/failed | CSV remains available; workbook is Preparing/Failed and has no premature button. |
| Verified workbook remains, its preparation CSV disappears | Workbook may remain independently available if its own captured evidence/file still verifies. |
| Every record rejected / no records selected | Truthful no-records-exported / nothing-to-export feedback; no misleading useful-success artifact. |

States are per format: pending, ready, failed, unconfirmed, no-data; qualified historical availability is distinct from verified readiness. `CompleteCatalogExport` owns status messages and allowed actions through the central policy. CSV may become available while XLSX prepares, with subsequent truthful workbook feedback under the same export reference. Repeated inspection must not blindly repeat success effects; tests cover re-entry and ambiguous notification exits without promising exactly-once delivery.

Native CSV+XLSX dispatch currently completes before its workbook job. The scoped canonical dispatcher/completion path must explicitly correct this behavior; a guarded downloader alone is insufficient. Keep native authorized export endpoints, and prevent the canonical native XLSX fallback from generating unverified work outside this coordinator.

### Older export files

Do not rewrite, upgrade or delete historical bytes. Preserve authorized readable older downloads as **Historical—not reverified**, with basic available-file/consistency checks and no invented completeness evidence. Block known missing/inconsistent artifacts. This branch applies only to the persisted null-version classification above; missing metadata on a managed record never invokes it. Do not apply the new import acceptance policy retroactively as a claim about historical values. A new export is new data capture, not reconstruction of an old snapshot.

### Needed tests

Proposed `tests/Feature/CatalogExportStorageFailureTest.php`, `CatalogExportTransactionTest.php`, and `tests/Feature/Catalog/MySql/CatalogExportPublicationTest.php`:

- Actual canonical job chain: false header put; false page put while header succeeds; thrown infrastructure error; false workbook write; read/publish failure. Assert artifact contents, counters, row outcomes, notification/actions and authorized download readiness together.
- Positive multi-page and 98/2 formatter-rejection cases; all rejected/no-data; missing/wrong/duplicated page identity; wrong header/columns/hash; stale metadata; unsealed preparation even when early pages finish.
- CSV-only, XLSX-only and both formats; workbook pending/failure; verified workbook independent of subsequently missing preparation CSV; controlled workbook regeneration.
- Re-entry/duplicate delivery, legitimate captured identity reuse and conflict, real rollback, real level-zero postcommit failure and unrelated enclosing rollback. A file/counter set that looks complete before the enclosing commit remains pending; failed/unknown publication cannot become Ready. Preserve adopted evidence; no replacement by later source values.
- Concurrent page adoption/planning completion on guarded MySQL using existing contention helpers. Assert sealed-plan gating, no lost page metadata/counters and bounded correct outcomes; do not claim all schedules or crash recovery proved.
- Historical null-version readable files, new version-stamped creation through every canonical entrypoint, missing/deleted managed metadata without historical fallback, unsupported versions, known inconsistent files, legacy qualification and same-owner/foreign-owner downloads. Unrelated and Rules exporters keep their existing pipeline.
- Change central rule inputs in isolated policy tests and prove completion/downloaders derive the same result. Do not assert class names or grep for literals as a substitute for behavior.

Physical-commit tests follow the existing transaction-manager/cleanup pattern; a wrapping test savepoint is not evidence of final commit. The real export/import file-chain tests must also use that physical-commit harness where after-commit dispatch is expected, restoring the test transaction manager and cleaning only their owned records/files afterward. Do not weaken production after-commit behavior to make the default suite wrapper run jobs early. Pure CSV/policy cases need no database. Keep native import after-commit regressions in the targeted matrix.

**CP6b:** all CSV/readiness integration and focused physical/MySQL tests pass, independent reviewer accepts the evidence contract, and the shared adapters are coherent. No new durable DB ledger, outbox, global storage setting or Rules clone.

## 7. Step 7 — AD-01 exact presentation

**Owners:** `app/Services/ConfiguratorEditorChanges.php` (new Rule headings, references and change/compiler-error labels), `app/Services/ConfiguratorPreviewInspection.php` (Mapping causes), `app/Livewire/Catalog/ProductShow.php` (page title), and `resources/views/livewire/catalog/tag-values.blade.php` (before/after tags).

Replace only the falsey fallback decisions that discard string `"0"`. Use each site's existing null/empty-string or empty-list semantics, preserving whitespace where `?:` currently preserves it. Do not replace these with a broad `blank()`/`filled()` rule, trim data, alter variables/persistence/compiler meaning or introduce a universal presenter.

**Tests:** extend `ConfiguratorEditorChangesTest.php`, `tests/Unit/ConfiguratorPreviewInspectionTest.php`, `Catalog/PublicCatalogTest.php`, and `TagWorkspaceTest.php` as appropriate. Assert actual inspected/prepared/new-Rule/reference/error labels, Mapping explanation causes, rendered Product title and rendered tag-review before/after `['0']`. Include null, empty, whitespace and `001` controls. Existing storage/compiler/tag tests remain.

**CP7:** focused presentation regressions fail first and pass after the narrow changes. This checkpoint may be taken while CSV reviews run, provided the one writer is not editing both concurrently. Preserve SA-01's ProductShow/Preview tests and document the actual order taken.

## 8. Optional added mode — one queued streaming worker

The human accepted investigating this as an added capability. The approved choice remains readiness B with a chunked default; this is not a substitute or an automatic scope expansion.

Proposed future `app/Filament/Exports/Jobs/StreamCatalogCsvExport.php` uses one queued worker to stream a complete temporary CSV, then the same checked adoption/publication and policy assessment. One planned artifact is still a sealed plan. It retains individual row rejections, verified workbook generation, authorization and unknown-outcome handling. It does not run the export in the HTTP request, buffer the dataset in memory, bypass metadata or promise a whole-dataset point-in-time snapshot.

**CP6-OPT, investigation first:** measure representative current/large accepted selections, generated bytes, peak memory, wall time, query count and failure/restart behavior in an isolated target. Inspect the actual effective worker/job timeout, retry-after and memory limits; source Horizon defaults alone do not establish them. Do not increase global timeouts, cap legitimate exports or change queues to make the option appear viable.

Technical budget proposed for review: finish within 80% of the smallest effective worker/job timeout and 80% of the lower PHP/worker memory budget, retaining timeout below retry-after. Record test volume and actual values. These are optional-mode admission budgets, not new catalog limits or existing performance facts.

If proof supports it, return a bounded opt-in mode proposal and tests before implementation/activation. Keep it unavailable/default-off until that checkpoint is accepted. Test output equivalence, streaming memory bounds, row rejections, false write, interruption, unknown commit, and parity of CSV/XLSX readiness/legacy/ownership decisions across both modes. Failure to meet the budget leaves the mandatory repairs and chunked default intact. A durable DB recovery ledger remains separate unapproved scope.

## 9. Execution checkpoints and test commands

| Gate | Required result |
| --- | --- |
| CP0 | Human approval; exact checkout/HEAD/dirty/versions; clean participating lane; guards and isolated test/storage/runtime verified. No source/Images reset. |
| CP5 | SA-01 failing real child HTTP regression, narrow repair, positive/Preview controls passing. |
| CP6a | Central policy/CSV grammar, all reader/writer stages and real file roundtrips characterized and passing. |
| CP6b | Sealed-plan/artifact/readiness/completion/download integration, failure and physical/MySQL cases passing; independent read-only review. |
| CP7 | Exact-text/whitespace presentation tests passing; report whether taken before or after CP6. |
| CP6-OPT | Optional measurement/admission report only; no automatic mode implementation/activation. Does not block CP5–7. |
| CP-FINAL | Combined relevant regressions, complete guarded suite, restricted MySQL, JS and actual isolated browser acceptance; reviewed diff and preserved worktree hashes. |
| CP-HANDOFF | Final verified SHA/contracts/evidence supplied to catalog owner; step-8 read-only audit/plan gate. No automatic security, feature execution, integration or push. |

Use the narrowest relevant direct Pest files first with `--compact`; create new tests with installed Artisan generators after safe environment admission. Reuse the existing `RefreshDatabase` convention and explicit physical-commit cleanup patterns rather than change suite traits globally.

Ordinary guarded launcher, after checking the effective target:

```sh
env -u DB_URL -u DATABASE_URL -u APP_CONFIG_CACHE -u APP_BASE_PATH -u LARAVEL_STORAGE_PATH -u DB_SOCKET APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/pest --compact
```

For MySQL, retain the same removed overrides and the existing restricted target: `127.0.0.1:3307`, database/user `configurator_catalog_test`, using `phpunit.mysql.xml`. Load its password privately from the approved testing setup. Verify grants/disposability and no competing reset run first. Never reset `configurator_catalog_dev` or an Images/realistic-data copy. SQLite is not evidence of MySQL lock behavior.

Run existing JS tests with `npm test` (currently `node --test tests/Unit/*.test.js`). Modified PHP requires `vendor/bin/pint --dirty --format agent`; changed `app/Filament` files require `vendor/bin/filacheck --fix --dirty` and resolution of remaining applicable violations. Rerun relevant tests after formatter/adapter fixes. Run a frontend build only if served assets changed. Preserve full output/exit codes and distinguish old evidence from this run.

The old Consolidation QA domain/database are retired. Before new browser acceptance, provision a separately approved isolated runtime/MySQL copy with privately verified login and resolved Herd URL; do not assume the old site works. Then exercise actual CSV upload/mapping/import, successful/rejected output and download, actual export CSV/XLSX and availability/Preview/zero-label read-back. Report manual assistance, unvisited surfaces and any limitation honestly. No new browser package is required.

## 10. Dispositions and rollback

Keep security findings and their older package/asset plan recorded as deferred until explicit request. Updating this spec does not implement, close or schedule them. Step 8 remains with the catalog task; later security changes trigger targeted compatibility refresh only where they affect those features.

Each execution checkpoint uses an explicit reviewed file/hunk allowlist and local commit, preserving concurrent work. No bulk staging, reset, archive or automatic merge/push. Update the journal after actual changes/tests. Roll back application code only through a reviewed compatible checkpoint; preserve immutable captured artifacts/evidence. Never restore permissive CSV parsing or false-success readiness silently, delete user export history, or run a database rollback to conceal an uncertain outcome.

**Approval requested next:** review this specification, including the central rule owner and optional-mode gate. Application implementation starts only after the human explicitly approves it. No proposed regression, performance budget, metadata lifecycle or browser check above has been executed merely by writing this document.
