# Current project status — 6 November 2026

This maintained status record takes precedence over historical preparation, queue and approval language in older plans. Dated audits, receipts and reviewed patches remain historical evidence; they are not new implementation or migration instructions.

## Delivered source and publication

Catalog, Images Manager and Rules Writing are integrated into Master. Their later acceptance and release receipts supersede the November 2 Images checkpoint and the old statement that Writing is queued. Writing's testing workspace remains a separate deferred feature decision.

The following Admin UX changes are committed and pushed through `31e24e5099e0879eaee03f91cc2937b1f5119fb1`:

| Commit | Delivered change |
| --- | --- |
| `4286e68` | Immediate Assign tags operation choices |
| `50a15c3` | Shared bulk-edit and Find-and-replace operation choices |
| `31e24e5` | Group Presets: wrapping multiple-choice buttons for suitable small vocabularies, searchable fallback otherwise |

The S14 receipt records 91 PHP tests / 546 assertions, focused desktop verification and a closed independent review. Earlier batches retain their own verification receipts; these are separate runs, not an aggregate test total. GitHub's Deployment check run `42298074385` passed for `31e24e5`. That workflow covers production dependencies, assets, JavaScript and deployment caches; it does not establish full PHP/MySQL CI coverage.

This push did not deploy to Forge. Automatic deployment was verified off before pushing. The last completed rollout receipt is Admin Appearance at `a1e221b`, release `82304781`; do not describe the three newer UX commits as live before an authorized manual deployment.

The other six conditional Select contexts, S08/S09/S15/S16/S17/S18, remain deferred. Their existing controls remain in place. No additional Select implementation is queued automatically.

## Current Product data contract

Current `Product` storage uses `profile_fields` and separate `parts`. Migration `2026_10_23_110018_consolidate_product_profile_fields.php` records the transition from the old `properties` / `extra_data` pair. `ProductFields`, `ProductFieldQuery` and `ProductEditorData` implement the current field vocabulary, consumers and editor presentation. Group-selected editor fields and filter fields have separate presentation roles.

The older [Product Data Foundation plan](catalog/PRODUCT_DATA_IMPLEMENTATION_PLAN.md) is a historical proposal, including its superseded storage assumptions and then-pending readiness statement. Do not replay its tasks or revive the old columns from that paragraph. This correction does not claim that every separately listed flexible-import or ERP follow-on has shipped. The [consolidation journal](catalog/CONSOLIDATION_CHANGELOG.md) records `fcac0ad` and `4e82c22` as integrated work.

Group attributes and Product properties remain distinct, and Group tags are an independent flat discovery route rather than a replacement for the family tree or Group attributes.

## Corrected historical findings

- **Old S08 appearance recovery is delivered**, in `0770104`; current recovery and validation tests remain. Its historical pending label does not reopen the repair.
- **Old S10 command/import exception handling is characterized and accepted.** The actual console Kernel handles failure reporting/rendering and exit status. Seven existing snapshot command cases cover admission, invalidation, contention, unexpected failures and query-log cleanup. The configured native import uses `ImportCatalogCsv`, with rollback and failure accounting covered by `CatalogNativeImportTransactionTest`. The small current-source assessment found no required production fix. A missing local catch is not itself a new defect.
- The accepted S10 evidence is indexed in [the combined verification report](catalog/COMBINED_REGRESSION_VERIFICATION_REPORT_2026_10_29.md) and [reconciliation execution record](catalog/APP_RECONCILIATION_AND_ROBUSTNESS_EXECUTION_SPEC.md). Existing results are not relabelled as new test executions.
- **Master PHP/MySQL/format CI coverage remains separate outstanding work.** The existing PHP test/lint workflows still target `main`/`develop`; isolated MySQL CI and submitted-format-drift enforcement need their own bounded change. This is not an added publication gate for the accepted UX commits.
- R18 measurement, O08 package ownership and PF-L01 recovery rehearsal remain optional/operational follow-ups. Historical IME/device/capacity/download proof gaps and Group/draft attribution are qualified evidence gaps, not established current defects or automatic rerun instructions.

## Cleanup and evidence custody

The exact plan and execution receipts live outside the application repository:

- `/Users/studioycm/.codex/task-artifacts/configurator-cleanup-plan-20261106/CLEANUP_PLAN.md`
- `/Users/studioycm/.codex/task-artifacts/configurator-cleanup-execution-20261106/`
- `/Users/studioycm/.codex/task-artifacts/configurator-cleanup-archives-20261106/`

The user authorized cleanup after publication of the finished UX commits. Execution remains bounded by that exact plan: archive unique content before retiring its working copy; preserve Main, current K2 QA, reconciliation TEST, all server databases/accounts, shared services, browser profiles and the two latest live backups. The old `catalog-rebuild` environment points to Main's `configurator_catalog_dev` database and must never be used as a database deletion target.

All 170 historical pending paths are mapped to custodians: 49 identical to Master, 97 differing, 24 absent at their old paths. They are preserved history, not 170 unfinished fixes. The recovery packages retain untracked and needed ignored data as well as Git provenance. Refer to execution receipts for actual completed and held items; this document does not mark a planned removal complete.

The protected photo `/Users/studioycm/Downloads/event-map-venue-background.jpg` matches its QA ledger by size and SHA256. Its original is recoverable, while the configured QA working original/manifest remain absent. Preserve the original and QA metadata; no cleanup receipt may claim that the QA inconsistency was restored.

Some old `/private/tmp` evidence originals were already missing before this cleanup. Retain surviving equivalents and record their absence honestly. The archive index preserves the surviving ROOT10/ROOT13 evidence; it cannot recreate missing originals.
