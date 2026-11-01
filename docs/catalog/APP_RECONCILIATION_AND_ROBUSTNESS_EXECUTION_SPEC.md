# App reconciliation and robustness implementation plan

> **Historical record — publication note, 1 November 2026.** The original status, commands, approval gates and worktree assumptions below describe their dated October baseline; they are retained evidence, not current execution instructions. Groups One and Two were delivered on Master in `07701045e4dda35213e24b078eba19675d697fc6`. Use the [current delivery and history index](../README.md#current-delivery-and-historical-records), [stage protocol](STAGE_AUDIT_AND_DOWNSTREAM_REPLAN_PROTOCOL.md) and [current Catalog/Images execution plan](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for present scope and remaining verification. Host-local paths/hashes are historical evidence references, not bundled or fresh runtime proof.

> For agentic workers: the human has authorized Group 1/2 execution once technical reviews finish, without another routine approval. Plan bulk creation forms coordinates the independent readiness review and sole implementation writer in its own isolated checkout. Use executing-plans task by task after that review; this audit chat remains independent and must not implement concurrently. The plan itself grants no Catalog/Images, merge, push or security authority.

**Goal:** remove one duplicated default policy and address separately approved, demonstrated resilience problems while preserving current shared consumers.

**Architecture:** reuse the current inclusion draft service and authoritative writers. Keep appearance fallback local to stored-read validation. Retain transport-specific CLI handling and distinct presentation lifecycles.

**Tech stack:** PHP 8.4, Laravel 13; current-source Pest conventions. Refresh exact installed versions and affected source fingerprints before execution.

**Spec:** [bounded solution proposal](APP_RECONCILIATION_AND_ROBUSTNESS_PROPOSAL.md), baseline master `8e1f640219ec8dbd2ee4c739041907aba4c4224d`, 29 October 2026.

**Independent readiness receipt:** coordinator reports PASS for G1-A/G2-A. Its review clarified PAGE_START initialization, recovery metadata through effective reads/Save/rollback and the distinction between console Kernel::call and Kernel::handle; those changes are incorporated below. G2-B requires characterization before deciding on a patch. The coordinator is sole implementation writer at `/Users/studioycm/.codex/worktrees/app-reconciliation/configurator`; this audit does not edit that checkout or reuse its own verification snapshot for implementation. Exact reviewed artifact hashes were not supplied with this receipt; no implemented-candidate or runtime pass is claimed.

## Global constraints

- G1-A/G2-A/G2-B execution and meaningful tests are authorized after technical readiness review, without repeated human implementation approval. Stop for a genuine new scope/behavior decision or blocker. S08 selective recovery is settled.
- Writing alone owns its approved `SaveConfiguratorDefinition` changes. Recompare its current consumed contract; do not edit that writer/loader/compiler in these slices.
- G2-A and future Catalog K2 both plan changes to `AdminPanelProvider`. The coordinator must serialize that shared path and preserve the union of explicit Page registration and existing/new hooks; never replace the provider wholesale.
- Preserve all initial pending documentation and protected Images/prototype worktrees/sites/data. No broad stage/reset/stash/clean, merge or push.
- `.ai/rules` was absent at preparation. Recheck before editing; read the index/matching rules and required keyword search if it appears.
- No new dependencies/schema/base directories, service/provider framework, universal presenter or UI redesign.
- Tests use factories, meaningful actual-operation assertions and the current guarded environments. No main DB, tinker fixtures, unguarded Artisan or manual test-transaction escapes.

## Review focus

1. Omitted subtitle fields must not erase explicit Custom/Master intent; local defaults must not select a saved Default.
2. Canonical IDs, inclusion IDs and caller-owned order remain distinct; foreign ownership and late validation failures leave no partial graph.
3. A read fallback must never become an implicit settings repair or swallow infrastructure errors.
4. Same-instance warm/cold reads through nested rollback retain current cache/memo correctness.
5. Command failure must report any already-committed invalidation truthfully and restore query logging.

## Shared execution preflight

- [ ] Record approved slice, exact HEAD/diff, allowlist, relevant package versions and Writing dependency hashes; stop for unexplained shared-path drift.
- [ ] Establish isolated checkout/storage and effective disposable DB before application bootstrap. Acquire the coordinator's exclusive window before restricted-MySQL resets/contenders; no concurrent suite or `--parallel`.
- [ ] Refuse inherited `DB_URL`, `DATABASE_URL`, `DB_SOCKET`, `APP_CONFIG_CACHE`, `APP_BASE_PATH`, `LARAVEL_STORAGE_PATH`, conflicting connection variables or cached configuration. Do not automatically clear/unset/bypass anything.
- [ ] In the isolated checkout, run the inert standard guard before any application test:

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php -r 'require "tests/TestDatabaseSafety.php"; Tests\TestDatabaseSafety::assertBeforeBootstrap();'
```

All standard test commands below carry this explicit process prefix. Keep `CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array` explicit in the isolated test environment and disable installed monitoring integrations as in `phpunit.xml`. The framework's resolved-target guard must still pass before providers/PDO. A guard refusal is a stop, not a failing application assertion.

## Slice G1-A — shared new-inclusion defaults

**Modify:** `app/Services/ConfiguratorInclusionDrafts.php`, `app/Actions/SaveConfiguratorOption.php`.

**Test:** extend `tests/Feature/ConfiguratorCreationFlowTest.php`; consume existing `tests/Feature/ConfiguratorSubtitleTest.php`. No new test file is necessary if these existing operation scenarios can express the regression directly.

**Interfaces:** add public `ConfiguratorInclusionDrafts::optionLocalDefaults(): array`, documented as the exact six-field shape in the proposal. Keep `option(Option $option): array` and `SaveConfiguratorOption::handle(User $actor, ConfiguratorAttribute $owner, ?int $id, array $data): void` unchanged. Constructor-inject the existing draft service into the action; no provider binding.

- [ ] Add operation-level parity cases to the existing creation test: ordinary create versus batch Add Values, asymmetric owners, explicit chosen Default and raw saved custom controls. Assert six persisted local defaults, correct distinct IDs/order, unchanged selected Default and unrelated graph.
- [ ] Cover omitted and explicit None/Custom/Master intent plus foreign-owner/late-Code rejection using existing fixture helpers. Reuse an existing assertion where it already detects the defect; do not duplicate literal helper arrays as a test.
- [ ] Run the affected characterization tests before refactor. A passing baseline is expected for behavior-preserving work.
- [ ] Compose only the six local fields in both new-inclusion branches. Keep UUID/canonical ID/order/supplied data and partial updates at their current owners; preserve output key order and invalid/missing Option ID compiler behavior.
- [ ] Run both affected files with the standard prefix, then inspect raw unselected identities/order/Default preservation. A new field in the relation-manager submitted projection is outside this allowlist.

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/ConfiguratorCreationFlowTest.php tests/Feature/ConfiguratorSubtitleTest.php
```

**Exit:** behavior-equivalent creation defaults from one policy source; existing consumers pass. Revert only this reviewed slice if needed and authorized, never the shared writer or other tasks' changes.

## Slice G2-A — stored appearance read fallback

**Review gate:** selective recovery and a safe notice are settled. The coordinator's sole writer executes after technical readiness review under the latest human authority; no further approval or product answer is required for the current rules.

**Modify:** `app/Services/AdminAppearance.php`, `app/Providers/Filament/AdminPanelProvider.php`, `lang/en/admin.php`. **Create:** `resources/views/filament/resources/appearance-recovery-notice.blade.php`. **Test:** extend `tests/Feature/Filament/AdminAppearanceTest.php`. The existing strict `SaveAdminAppearanceSettings` writer and existing Admin Appearance preview behavior remain unchanged; the new Catalog Display no-preview contract is a separate feature.

**Interfaces:** retain `current(): array`, `validate(array $settings): array`, `rememberSaved(array $settings): void`. Add query-free `recovery(): ?array` with shape `{defaulted_fields:list<string>,ignored_unknown_count:int,invalid_payload:bool}` or null after a valid read. Use private `fieldRules(): array` / field validation-normalization helpers shared by strict validation and read recovery, plus `recoverStored(mixed $stored): array`; do not expose a new persistence API or catch row/cache/schema failures. Change the raw-read closure return type to mixed so an unusable stored payload is classified directly rather than caught as TypeError.

- [ ] Add observable recovery cases: sidebar288/control34/logo99/header48 defaults only logo28; header40/logo32/title20 defaults only logo28; header40/logo32/title30 defaults logo28/title20. Assert every unrelated known value survives, raw stored JSON/version remain unchanged and a generic notice renders.
- [ ] Cover valid input unchanged; invalid known range/type/enum/boolean; unknown-only input ignored/reported safely; normal legacy omissions default without notice; non-array stored payload uses validated defaults with an unusable-payload notice. Keep existing numeric-string/boolean acceptance equivalent to strict validation rather than introducing stricter read rules.
- [ ] Run new cases and record expected current failures before implementation; preserve the existing warm/cold nested transaction cases.
- [ ] Share the existing field rules and normalization without changing strict Save error fields/order. For read recovery, retain known fields, add defaults for omissions, replace independently invalid known fields only, then preserve validated header and default offending logo/title as specified. Finish with `validate($candidate)`. Current minimum-header/default arithmetic guarantees fit; do not add whole-map fallback for an unexpected final failure, arbitrary clamping or eight-candidate optimization.
- [ ] Maintain safe per-instance recovery metadata and once-only logging, with known field identifiers/unknown count only. Recompute effective recovery metadata on effective reads/Save/rollback; do not confuse once-only log suppression with permanently retained/suppressed notice state. A valid fresh read or committed valid Save clears the effective report; transactional reads never memoize recovery. An outer rollback must cause a fresh read and the correct notice again. Do not cache recovered values globally or erase the stored raw payload.
- [ ] Register the new notice through the installed panel's `PanelsRenderHook::PAGE_START`, keeping the existing styles/workspace hooks. The hook/view must call `current()` before reading the query-free `recovery()` report; style rendering happens later and cannot be relied on to initialize it. Render one translated status notice with polite live semantics, no focus stealing or raw settings. Only manage-catalog users receive an `AdminAppearance::getUrl()` repair link; ordinary readers receive generic text. Use native styling; add no global notification event or dependency. Test an initially unresolved service at this hook, not only a service prewarmed by a test.
- [ ] Assert invalid/unknown-key Save still fails with existing messages; authorized valid Save repairs/version-advances once, publication waits for actual commit, no-op and rollback retain same-instance correctness. Missing rows/DB/cache outages still propagate. Exercise provider/page notice wiring, including disappearance after valid repair and reappearance after rollback; do not merely test helper arrays.
- [ ] Run the entire affected file under the standard prefix. The existing independent-commit lifecycle must not be replaced with a new transaction escape. Include a real isolated-browser notice/repair/reload check in acceptance when that target is ready; it has not run here.

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Filament/AdminAppearanceTest.php
```

**Exit:** selected recovery behavior proven without implicit writes or weakened validation. A page notification alone is insufficient; inspect raw row/version/cache observations.

## Slice G2-B — characterize snapshot CLI, patch conditionally

**Create in the coordinator's reviewed implementation lane:** `tests/Feature/Catalog/CatalogSnapshotCommandTest.php`. Use the installed Artisan generator and its inspected help with `--pest --no-interaction`; the name must not include the Feature suite directory. This audit's baseline verification snapshot remains unchanged.

**Conditional modify:** `app/Console/Commands/CatalogSnapshotCommand.php` only, after actual characterization establishes a mismatch covered by the reviewed policy. No service/import/domain exception rewrite; a different issue requires stopping for a genuine decision rather than broadening the patch.

**Interfaces:** retain current signature, `handle(CatalogSnapshots $snapshots, CatalogRevisions $revisions): int`, valid JSON fields and `--invalidate` meaning. Proposed failure contract: invalid ID returns `INVALID`; expected unavailable/branch/busy produces a safe nonempty error and `FAILURE`; unexpected exceptions propagate.

- [ ] Exercise actual terminal exception rendering through `Illuminate\Contracts\Console\Kernel::handle(new Symfony\Component\Console\Input\ArrayInput(...), new Symfony\Component\Console\Output\BufferedOutput())`, or an explicitly isolated subprocess, with real services and guarded fixtures for invalid ID, unavailable Group, physical branch and valid leaf. Capture returned exit code/rendered output, query-log state and revision/cache effects. PendingCommand / `$this->artisan(...)` calls Kernel::call and is not proof of Kernel::handle exception rendering; retain it only for narrower command assertions. Do not mock the whole command/service or weaken current target guards.
- [ ] Exercise expected 503 at a controlled cache/lock seam; distinguish it from an unexpected DB/logic exception. Keep nondeterminism at that seam only.
- [ ] Verify invalid/unavailable/branch `--invalidate` never mutates revision/cache; valid admitted invalidation followed by snapshot failure reports the failure while retaining its already-committed revision. Do not replay automatically.
- [ ] If current behavior meets the contract, stop with tests/evidence and no production patch. Otherwise classify the exact mismatch against the reviewed policy; only a covered mismatch permits the narrow 404/409/503 `HttpExceptionInterface` mapping around admission/get, retaining log cleanup and unexpected propagation. Stop for an uncovered issue; do not request another approval for a routine covered correction.
- [ ] Rerun the affected file and existing snapshot regressions appropriate to the touched seam. Never invoke `catalog:snapshot --invalidate` directly against the main runtime.

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Catalog/CatalogSnapshotCommandTest.php
```

**Exit:** evidenced console contract; a production change follows only for an actual mismatch covered by the reviewed conditional policy and latest human authority, without another routine approval. Stop for an uncovered issue. A source-layering concern alone is not a confirmed user-visible defect.

**Frozen implementation receipt:** the coordinator reports six actual Kernel::handle cases passing and no production command patch required. This audit matched all nine candidate file fingerprints. Characterization's RefreshDatabase assertions establish operation effects, not an independent physical commit; targeted MySQL/browser proof remains separate. The receipt does not authorize another full baseline run or application edits by this audit.

## Checkpoints and final acceptance

- [ ] After any PHP edit, run the mandated Pint command only in the isolated, allowlisted checkout: `vendor/bin/pint --dirty --format agent`; verify it changed no outside path. No `app/Filament` edit is planned; if the scope expands, stop and include required FilaCheck review explicitly.
- [ ] Record narrow test commands/results, actual DB engine, before/after saved state and unrun gaps. SQLite does not establish MySQL locking; consume the separately coordinated combined verification for changed shared seams.
- [ ] Obtain independent bounded review per approved slice; author self-review is not that receipt. After affected feature tests pass, request the complete suite as required by project guidance without treating it as already run.
- [ ] Report exact allowlisted diff, original pending-path preservation, any Writing contract drift and S10 characterization/new blockers. S08 needs no repeated decision. No merge, push or runtime publication is implied; any Git checkpoint follows the coordinator's explicit scoped workflow.

G1-B presenter extraction, whole-app architecture, new Catalog behavior, security/CI and unbuilt feature acceptance remain excluded. The [combined acceptance proposal](COMBINED_REGRESSION_ACCEPTANCE_PROPOSAL.md) governs the separate finite existing-code verification package.
