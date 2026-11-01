# Combined current-master regression acceptance

> **Historical record — publication note, 1 November 2026.** The original status, commands, approval gates and worktree assumptions below describe their dated October baseline; they are retained evidence, not current execution instructions. Groups One and Two were delivered on Master in `07701045e4dda35213e24b078eba19675d697fc6`. Use the [current delivery and history index](../README.md#current-delivery-and-historical-records), [stage protocol](STAGE_AUDIT_AND_DOWNSTREAM_REPLAN_PROTOCOL.md) and [current Catalog/Images execution plan](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for present scope and remaining verification. Host-local paths/hashes are historical evidence references, not bundled or fresh runtime proof.

**Status, 29 October 2026:** bounded verification of existing code is approved through the coordinator. This document fixes that lane's finite scope and checkpoints. These verification commands do not authorize application fixes, broad test refactors, retained-runtime mutation, merge or push. Separately, the human now authorizes Group 1/2 execution after technical review, with the coordinator as sole implementation writer in a different isolated checkout. This audit stays independent; Catalog/Images implementation and security remain unauthorized.

**Observed source baseline:** master `8e1f640219ec8dbd2ee4c739041907aba4c4224d`; 14 pending documentation paths before this three-document proposal. A private metadata receipt records 671 application/resource/route/config/database/test/dependency/bootstrap files. This is a selected source inventory, not every repository/ignored/runtime file or a restorable backup. At original proposal creation, no application checks had run; versioned execution updates below supersede that initial status.

## Evidence lanes and finite scope

1. **A — existing master:** verify actual installed code and retained contracts below. Missing new Catalog/Images/Writing UI is NOT IMPLEMENTED, not a regression and not a pass.
2. **B — approved candidate:** after Writing or another approved slice changes a consumed seam, pin its exact candidate/diff and compare affected contracts only. Do not merge a candidate to create a test target without authority.
3. **C — future features:** Catalog C0/C1–C3, Images I1–I4/native lifecycle and later bulk/display are tested only after their own implementation approvals. Old prototypes/worktrees are historical/exploratory; they cannot accept current master or new production behavior.

No finite package can prove absence of every regression. This package does not recertify every historical clause, establish production security/capacity, or substitute historical suite totals for fresh execution.

## Checkpoint V0 — freeze source, environment and ownership

- Record HEAD, branch, exact diff/allowlist and source hashes, composer/package lock hashes, installed PHP/package/Node/browser versions, locale and viewport. Record compiled manifest/bundle hashes separately from source; PHP tests call `withoutVite()` and do not certify served assets.
- Recheck source hashes between stages. Stop on a moving application checkout; attribute owner-document changes separately. Record pre-Writing baseline versus a later approved Writing candidate explicitly.
- Record safe effective DB driver/host/port/name/account and schema identity, isolated filesystem/media/cache/session/mail/queue roots, served site/build identity and external side effects. Never emit passwords, app keys, cookies, source paths containing private content or raw production data.
- Preserve main and retained Images/prototype code, DBs, sites and media. No shared service restart, cache clear, environment edit or DB refresh. An ignored runtime copy is not made safe by a Git worktree alone.
- Reuse a suitable isolated checkout if available. Otherwise establish a fresh approved local verification runtime before boot; do not symlink dependencies in a way that makes autoloaded application paths resolve back to the live checkout. No vendor edits/dependency upgrades.

## Checkpoint V1 — guarded automated checks

Use current `TestDatabaseSafety` before app bootstrap. Refuse conflicting overrides/cached configuration; never clear/unset or weaken the guard to proceed. Execute only from the isolated target after V0.

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php -r 'require "tests/TestDatabaseSafety.php"; Tests\TestDatabaseSafety::assertBeforeBootstrap();'
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Unit/TestDatabaseSafetyTest.php tests/Feature/TestDatabaseSafetyTest.php
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact
npm test
```

The standard run uses the current `phpunit.xml` exclusions and explicit isolated array cache/session/mail, sync queue and disabled monitoring integrations. Full PHP and JS commands each run once at the pinned baseline; rerun only an affected subset after a change/failure. Inspect subprocess/file/storage effects of selected tests first. No new verification script, dependency or new test is authorized by these commands.

**Pure Node exception:** the existing `tests/Unit/*.test.js` suite may run against the pinned primary source without a Laravel runtime when inspection establishes only source reads and mocked browser/network state. It does not bootstrap PHP, access DB/media or certify real-browser behavior. Capture source hashes before/after and stop for drift. This exception does not apply to PHP tests, browser writes, builds or new tests.

Eight retained contract families map the combined scope:

| Family | Meaningful evidence |
| --- | --- |
| Product profile/Parts | Exact literal keys/types and independent field fingerprints; unrelated profile/Parts preserved. |
| Nested creation | No canonical/local writes before final operation; indexed late-error rollback, selected identity and defaults. |
| Inclusion Default/subtitle | Ordinary/batch defaults and None/Custom/Master behavior; explicit saved Default remains independent. |
| Rule editing/capture | Current ownership, explicit actor/HMAC, stale rejection and exact meaningful-state comparison. |
| Preview | Saved/current snapshot distinction, recollection, new-Rule identity acknowledgement and later-text preservation. |
| Group settings | Full/narrow raw preservation, membership/order, exact no-op/revision behavior. |
| Availability | Fresh retained-child admission after ancestor/configuration/Product changes; separate admin Preview authority. |
| CSV/export | Actual bytes/round trips/accounting, immutable capture and independent CSV/XLSX readiness/publication. |

## Checkpoint V2 — one exclusive restricted-MySQL window

The human approved fresh isolated reconciliation resources. V2 uses only `127.0.0.1:3307`, database/user `configurator_reconciliation_test`, with explicit `CATALOG_MYSQL_TEST_PROFILE=reconciliation`. This replaces this lane's earlier proposed catalog-test target; the existing catalog/Rules resources and guards remain intact. The coordinator owns one exclusive serial window on the new test DB. No browser/other writer, `--parallel` or audit-owned DB window; contention children belong to the suite owner.

Use the private0600 test profile outside served roots, with its password supplied privately, never printed in commands/reports. Execute only from the pinned isolated candidate containing reviewed Root13 profile support, not primary master or an older candidate lacking that support. The shared guard is accepted W4 plus one exact reconciliation profile entry; this declared overlap must be resolved explicitly when combining Writing, preserving its other suite/test overlays. QA is never a PHPUnit profile. Refuse forbidden overrides and cached configuration without weakening the guard.

```sh
APP_ENV=testing CATALOG_MYSQL_TEST_PROFILE=reconciliation DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=configurator_reconciliation_test DB_USERNAME=configurator_reconciliation_test php -r 'require "tests/TestDatabaseSafety.php"; Tests\TestDatabaseSafety::assertBeforeBootstrap(true);'
APP_ENV=testing CATALOG_MYSQL_TEST_PROFILE=reconciliation DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=configurator_reconciliation_test DB_USERNAME=configurator_reconciliation_test vendor/bin/pest --configuration=phpunit.reconciliation-mysql.xml
```

The initial Root13 XML pins thirteen existing Catalog MySQL files plus root CreationFlow/appearance/CLI, with no Writing cases. The first run's retained-transfer wrapper cannot establish its contracts on this new target: the production transfer allowlist deliberately permits testing only on the preserved catalog-test target. Keep that application restriction. R2’s reviewed configuration removes only this wrapper from the NEW XML (twelve Catalog files plus three root files), retaining all seven wrapper cases as BLOCKED here: three target refusals and four misleading passes under the wrong target precondition. Do not delete the tests, broaden production allowlists or count those cases as accepted. Version and review the exact changed XML/test files before a rerun.

Existing deterministic contention helpers cover canonical/Preview participants, Group/Product ownership/classification, native-import rollback and export adoption/outer-commit ordering. Record connection identities, contender order and saved readback; sleeps alone prove nothing. Preserve existing test lifecycle conventions. Explicit SQLite-memory appearance aliases remain SQLite cases even under this MySQL launch. Label actual-engine versus SQLite/physical-commit evidence per case. Any missing assertion or further failure requires bounded diagnosis and scoped review, not an inferred application repair or general test rewrite.

## Checkpoint V3 — six existing-code browser journeys

Browser writes use the separately approved persistent synthetic QA database/user `configurator_reconciliation_qa` at `127.0.0.1:3307`, distinct from retained sites and the destructive test DB. This QA tuple must be rejected by every PHPUnit entry/profile and late connection. Private0600 credentials, app environment and storage remain separate. Pin served source/assets before browser execution. The standard `DatabaseSeeder` intentionally creates no catalog fixtures; approved bounded synthetic setup uses reviewed existing fixture paths, without changing that seeder, adding a QA framework, using tinker or importing retained/D60 data.

Before browser mutation, identify a disposable synthetic dataset from an already approved fixture/import path and capture exact fixture IDs and unrelated control rows. If no such setup can be established within existing authority, present the concrete missing setup choice; retain browser cases as BLOCKED while completing safe automated checks. No browser sign-in, client-download or native-file-chooser limitation is a pass. Request human sign-in/permission assistance when encountered rather than bypassing it.

| Journey | Steps and independent saved-state proof |
| --- | --- |
| B1 Product fields | Edit exact profile/Parts values → Save → reload; second-tab stale classification challenge; compare typed raw storage and both fingerprints. |
| B2 Nested creation | Stage one shared Master across asymmetric Attributes → observe no premature writes → late Code failure retains draft → correction saves exactly selected graph. |
| B3 Inclusion controls | Add Values, choose Default independently, save/reload; None/Custom/Master subtitle and canonical rename preserve intent and later local draft. |
| B4 Preview | Evaluate saved/current edits → Review → Save a new Rule; duplicate action/later edit does not lose identity/text; external change leaves snapshot truthful until rebuild. |
| B5 Availability | Retain an existing child page → separately change disposable owner visibility → refresh/action rejects stale admission; administrator Preview remains separately authorized. |
| B6 Data exchange | Native CSV download → inspect real bytes → file-picker upload/import → saved read-back; attempt actual XLSX save and independently parse captured bytes. Test per-format readiness/failure without equating HTTP response with a saved browser file. |

Use real available browser tooling, existing app controls and actual served bundles. Record action sequence, decisive screenshots, recent safe console/network errors, downloaded files/digests and DB read-back after reload. Screenshots/notifications alone do not prove persistence. Hardware IME, exhaustive viewport permutations and physical file-chooser cases not exercised remain explicit gaps.

## Failure and comparison evidence

Reuse existing checks for five failure families: late creation/ownership rollback; native import infrastructure rollback versus legitimate row rejection; export storage/readiness failures per format; post-commit publication/callback uncertainty without replay; Preview rollback/unknown/committed-refresh-failure with draft/receipt preservation. Inspect setup → entry point → discriminating assertion before counting a family covered.

For each actual write, capture expected changed rows and unrelated controls: raw JSON/types, child/join IDs/order gaps, relevant timestamps, revisions and asset/export digests. Compare exact expected deltas; no blanket timestamp normalization that conceals an unintended write. Test-case rollback is not independent proof of real commit behavior; use the existing actual-engine partition for that claim.

Compare baseline and an approved candidate on identical synthetic fixtures. Behavior-preserving G1-A expects identical observable output; a selected S08/approved S10 patch has a narrowly allowlisted intentional difference. Historical RED/GREEN evidence can establish a fix but does not by itself establish merge causation. S08/S10 are known conditional leads until characterized; do not report an expected baseline limitation as a newly introduced regression.

## Stop conditions and final deliverable

Stop the affected lane for unsafe target/override, unavailable exclusive window, moving source/assets/schema, unexpected external effect, foreign/unselected write, partial save, unknown commit outcome, unexpected lock cycle or inability to read back. Preserve evidence and fixtures; no automatic reset/replay/cleanup or production fix.

Deliver a finite ledger with PASS / FAIL / BLOCKED / NOT RUN / NOT IMPLEMENTED, exact source/build/fixture/environment identity, command/result counts, decisive before/after evidence, known baseline findings and unvisited gaps. Keep PHP/JS, MySQL, browser rendering, browser file saving and future-feature acceptance separate. Obtain independent bounded review before an acceptance claim; no universal no-regression or merge-ready certificate follows.

**Original baseline execution update:** V0 pinned source snapshot and isolated SQLite/storage established; V1 JavaScript 253/253 passed. Standard PHP initially 1464 passed with two missing-private-fixture failures; the exact audited fixture was copied read-only into the disposable snapshot, and those two cases passed on targeted rerun. Unique standard cases 1466/1466 and 9241 assertions across those runs; this is not one uninterrupted green full run. Safety 27/56 passed after supplying an empty disposable `.env`; the initial missing-file warnings are retained in evidence. V2 restricted MySQL and V3 real browser remain NOT RUN pending their isolated targets/window/credential source. Future feature lanes remain NOT IMPLEMENTED/NOT RUN. See the [verification report](COMBINED_REGRESSION_VERIFICATION_REPORT_2026_10_29.md) for exact results and limits.

**Later combined candidate:** the coordinator's non-Git base+frozen Group1/2+WritingW1/W2 snapshot completed with an inspected log summary of1578 passed/9807 assertions/101.19s. This audit matched all26 overlay fingerprints to both frozen receipts before/after; zero overlap. Original shell exit delivery is unavailable, so accept the observed completed-test summary with that qualification, never inferred exit0. No duplicate full run was performed here. This excludes MySQL/browser, W3, CatalogK1 runtime and merged-master acceptance. Frozen W1/W2 owner totals are PHP 1,547/9,661 and MySQL 62/391, old window closed; the coordinator now separately reports W3 focused 85 tests green and its Rules-only window open. These are owner reports outside this audit's own executions. The audit holds no catalog window and the private source question is still pending.

**W3 extension checkpoint:** a separate non-Git candidate includes unchanged root9 plus exactly frozen Writing W1/W2/W3 twenty paths. All29 overlay hashes and677 selected project files match. Inspected durable receipt/process JSON/log report1598 passed/9889 assertions/106.45s, guard exit0 and suite exit0. This audit accepts bounded combined SQLite evidence without rerunning the suite; the earlier W1/W2 missing-exit limitation stays historical and unchanged. Frozen Writing-owner W3 totals are PHP1567/9743 and Rules MySQL86/517, window closed; the previous85/open-window note belongs to its earlier stage. Combined MySQL/browser, K1 runtime, W4 and merged master remain outside this acceptance, and no catalog credential/window is inferred.


**Current V2/V3 update — 29 October:** fresh test/QA resources and strict profile review are complete. R1 failed MySQL143/14/937 remains preserved; accepted R2 selected reconciliation150/939/19.11s retains its original engine provenance and seven BLOCKED retained-transfer cases. The latest combined Root13R2+WritingW6 candidate passed full Node316 and full guarded SQLite1723/10599/112.72s, both exit0, after explicit45-path composition; no new MySQL run. Independent-process R2 CLI evidence now proves revision commit1→2 survived the controlled snapshot lock-timeout failure, with control1 unchanged; it does not prove every cache failure or cross-process lock exclusion.

Root explicitly released the separate QA lease to this audit. B1–B5 and native CSV save/upload/import passed with independent exact-QA readbacks and47 unchanged source/asset hashes. CSV imported10/10, preserving the edge text/CRLF/tags under explicit protected-export restoration; mapped blank descriptions/tags and timestamps changed as documented, so this is not a no-op. XLSX generated/received-workbook integrity passed after finding four matching valid partial files; completed native download remains BLOCKED at browser finalization. No bypass, application/source/build/config edit, main/retained/Rules/TEST mutation by audit, new Writing UI, merge or push. Read the [latest report](COMBINED_REGRESSION_VERIFICATION_REPORT_2026_10_29.md) and [structured evidence](COMBINED_REGRESSION_VERIFICATION_EVIDENCE_2026_10_29.json) for distinct identities and limits.

**Stalled XLSX follow-up:** after initially reporting “downloaded,” the human clarified that the entries were stuck and supplied a screenshot. The missing-filename question is resolved: four attributable `.crdownload` files in Downloads contain the complete valid workbook,4891bytes each, with exact server hash equality. The existing request bytes reached disk; the browser has not finalized them. This points to browser download finalization, but the precise cause is unproven. Buffered network history was truncated, and native Codex download-manager inspection was tool-refused as protected; no new request or alternate access followed. No application fix is justified by this evidence alone. [Structured partial-file proof](/private/tmp/audit-b6-xlsx-stalled-partials.json).
