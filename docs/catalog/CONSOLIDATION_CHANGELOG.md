# Consolidation change journal

Specification: APP_CONSOLIDATION_EXECUTION_SPEC.md. User approved implementation on 25 October 2026; merge and push remain prohibited.

## CP0 — isolated entry

- Status: verified entry; step 1 in progress.
- Baseline: master 0567df0af03421f84f751d689e75188821064d0f; worktree /Users/studioycm/.codex/worktrees/app-consolidation/configurator; branch codex/app-consolidation.
- One writer: primary agent. Research/audit agents are read-only; Sol 6.1 medium/high respectively, max only for monitoring. No Astra.
- Copied only the approved untracked specification from primary. Cloned existing locked vendor/node_modules without installing/updating packages. Created checkout-local storage and a testing-only SQLite-memory environment with its own generated application key. No shared storage/cache symlinks.
- Primary's eight unrelated dirty/untracked files were hashed to /private/tmp/app-consolidation-evidence/primary-preservation.txt. They remain excluded from edits/staging. No other worktree was modified.
- .ai/rules absent. Read AGENTS, execution/worktree/TDD guidance and testing/architecture rules. Native worktree tool created the checkout; sandbox permission was granted for its branch and local setup.
- Boost search-docs uses the matching installed Laravel 13 context; runtime Boost connection remains primary, so it is not used as evidence of the worktree database. No DB calls or application tests have run.
- Preflight: N2/N4/N3 must preserve capture identity and fresh writer; N1/N9 share real outer transaction design; N5/N6 supply only actual N8 consumers; independent leaves do not imply prerequisite refactors.

## CON-01 — effective test target guard

- Status: verified, CP1 passed. Lineage N16 / V02 / INT-01.
- Authoritative target check: one Tests\\TestDatabaseSafety helper for default, explicit MySQL, resolved configuration, connection creation and subprocess/fork entry points. Existing security specification proposes the same helper; use compatible names rather than a competing guard.
- Ruling: initial inert safety tests and the guard are created without Artisan generators because Artisan boots before the currently unsafe test gate. This bounded exception avoids the failure the step is repairing; later generators can use the isolated protected context.
- Ruling: preserve unused declared database connections, but guard the selected resolved target before providers and every actual connection-factory creation, including runtime aliases/build. Reject URL/read-write/direct/socket overrides rather than reimplement their target-resolution rules. No broad configurable approval service.
- Red/green: absent shared guard initially failed 16 inert cases. Auditor's dotenv-origin cached-PHP gap reproduced with a temporary sentinel, then fixed by a pre-LoadConfiguration check; empty cache paths and array/string URL aliases each reproduced and corrected. Selected resolved configuration is checked before RegisterProviders; actual alias/on-demand factory construction is guarded before PDO. Raw signed business state and production database configuration remain unchanged.
- Verified: dedicated inert suite 24 passed / 49 assertions; old MySqlSafetyTest 9 passed / 18 assertions; focused initial application baseline 53 passed / 296 assertions. Full default baseline after copying the exact hash-verified private corpus: 1,273 passed / 8,474 assertions. The first default baseline's only two failures were the absent ignored fixture, not app defects. Full MySQL baseline: 45 passed / 335 assertions, against the exact fixed test identity. Later added focused wiring coverage is recorded with its result below; CP7 repeats the full changed result.
- MySQL admission: read-only DATABASE()/CURRENT_USER()/SHOW GRANTS confirmed configurator_catalog_test@127.0.0.1 has database privileges only on configurator_catalog_test (global USAGE only). No competing test runner was active before the dedicated run. Test password was privately copied only from that exact account's prior testing environment into this checkout's ignored .env.testing; no credential output. Other databases/worktrees were not written.
- Formatting: required Pint --dirty --format agent applied only within this isolated checkout. No app/Filament PHP changed in this step, so FilaCheck is not applicable yet. Boost runtime metadata still points at primary/MySQL; matching installed-version docs were used, while safe target verification used this worktree's guarded native connection directly. No wrong-context Boost DB query was issued.
- Independent source audit (Sol 6.1 high) verified the early lifecycle/factory wiring and identified the bounded cache/URL refinements now covered. No separate reviewer wrote code. Review of unsafe destinations is fail-closed, not a claim that arbitrary future raw PDO or malicious test code is sandboxed.
- Final focused verification after all guard changes/formatting: inert 24 passed / 49 assertions; actual named/on-demand factory and existing MySQL refusal checks 12 passed / 25 assertions. Pint and diff whitespace check passed. All eight recorded primary-file hashes remain unchanged. Full baseline counts above plus focused post-refinement checks are CP1 evidence, not a final CP7 claim for future business changes.

## CON-02 — exact editor comparisons

- Status: planned; begins after CON-01 checkpoint. Lineage N2 / R10 / comparison portion R04 / PW-07.
- Reuse ConfiguratorEditorChanges for nonvalidating comparisonState/sameMeaning; migrate export, inspect, prepare, rebase, acknowledgement and actual browser dirty/stash guards coherently. Preserve raw drafts and capture/signature/hash wire identity. Actor plumbing remains the separate next step N4.
- Research (Sol 6.1 medium, read-only) identified additional actual Overview Blade and editor-stash hash consumers; these belong to this comparison route, not a new global draft platform. Preserve literal text, known transport equivalence, ordered lists, map keys and tolerant unfinished Rule state.
