# Security fixes implementation plan

> **For agentic workers:** After explicit implementation authorization, use `superpowers:subagent-driven-development` or `superpowers:executing-plans` task by task. Checkbox steps track execution. This document authorizes no implementation, dependency changes, account changes, deployment, commit, push, or messages to other chats.

**Goal:** Fix the audited security boundaries while preserving disabled registration and a simple administration model for the two existing admins.

**Architecture:** Keep existing Laravel/Fortify/Livewire/Filament responsibilities. Add one protected admin flag, narrow HTTP adapters, one test-only safety guard, and native upload protection; route actual authentication/recovery through Fortify. Keep existing Gates and business writers.

**Tech stack:** PHP 8.4; Laravel 13.35.0; Fortify 1.41.0; Horizon 5.50.0; Pest 5.3.0. Proposed patched targets: Filament 5.10.2, Livewire 4.4.8. No additional dependencies.

**Spec:** [Security fixes execution specification](SECURITY_FIXES_EXECUTION_SPEC.md). All behavior/interfaces/test IDs in that document are normative proposed scope. Its Appendix A supplies the exact existing admin-fixture file list.

**Planning baseline:** 23 October 2026; `/Users/studioycm/Herd/configurator`; HEAD `244b305d083a90c731cd4bde23d996b4db0f8f3d`. Source/tests were inspected, not executed. Only this plan and its companion specification are authored in this turn.

## Global constraints

- Registration remains disabled on Fortify and Filament.
- Preserve access for the two existing admins; no automatic new account creation, password changes, production token inspection, verification bypass, or bulk admin grants.
- One `users.is_admin` boolean; no roles package, permissions editor, additional panel, user-management resource, CAPTCHA, permanent lockout, or custom authentication engine.
- TOTP enrollment remains optional; no production enrollment or mandatory-MFA rollout in this work.
- Default feature tests use literal SQLite `:memory:`; explicit MySQL keeps the existing literal disposable-target allowlist. No database-backed test before Task 1 passes.
- No dependency additions, unrelated package upgrades, app-wide refactor, CI trigger changes, or historical-download policy changes.
- Preserve dirty/concurrent work. Do not stage all files. Reserve only the two security documents while planning; implementation owns only the files declared in its tasks.
- Re-read `AGENTS.md`, `.ai/rules/index.md` if it appears, all matching rules, and keyword-search rules before edits. Confirm installed versions and refresh relevant advisory ranges before implementation.
- New PHP files use installed Artisan generators with `--no-interaction` after implementation approval. Tests use Pest; run affected tests after each test edit.
- PHP changes require `vendor/bin/pint --dirty --format agent`. Changes under `app/Filament/` require `vendor/bin/filacheck --fix --dirty`; all remaining violations must be resolved. Scope tool-generated changes to the reviewed allowlist.

## Review focus

Each cross-cutting failure has an owning test in the specification:

1. An exported URL or cached configuration changes a nominal SQLite test into a destructive database run: G02-G07, Task 1.
2. A JSON response is returned but a retained Livewire action still executes: C02-C03/L03, Tasks 3 and 6.
3. An email update or retained-account transfer grants/resets administrative authority: A02/A05, Task 5.
4. A dependency patch leaves the old published browser code served: D02, Task 2.
5. A fix denies both real admins or breaks an existing reset link/intended destination: A04/H01/L01/L04, Tasks 5-6 and release review.

## Dependency order and ownership

Task 1 gates every later test. Task 2 establishes patched vendor behavior. Tasks 3-4 can then be developed independently, but both edit `AppServiceProvider.php`, so one implementer owns their integration. Task 5 changes admin factories across the suite and must finish before new tests assume `admin()`. Task 6 owns authentication entry-point consolidation; Task 7 owns recovery limits/responses. One implementer integrates all edits to each provider; concurrent agents may review or author independent test files, not race on shared files.

| Task | Priority | Deliverable | Depends on |
| --- | --- | --- | --- |
| 1 | P0 prerequisite | Safe test bootstrap and non-destructive guard tests | Explicit implementation authorization |
| 2 | P1 | Reviewed package patch and actual served browser fix | 1 |
| 3 | P1 | Recent-password denial stops parent/child 2FA actions | 1-2 |
| 4 | P1 | Global upload path authorization, real RPC regressions | 1-2 |
| 5 | P1 | Stable admin flag, retained-data compatibility, Horizon | 1-4 |
| 6 | P1 | Fortify-only actual login/recovery, stale native denial | 2, 3, 5 |
| 7 | P2 | Temporary recovery/resend limits, generic responses | 5-6 |
| 8 | Completion gate | Integrated checks and reviewable release handoff | 1-7 |

## Task 1 — Fail closed before test database access

**Files:** create `tests/TestDatabaseSafety.php`, `tests/bootstrap.php`, `phpunit.safety.xml`, `tests/Unit/TestDatabaseSafetyTest.php`; modify `tests/CreatesApplication.php`, `tests/bootstrap-mysql.php`, `phpunit.xml`, `tests/Unit/MySqlSafetyTest.php`, `tests/Feature/Catalog/MySql/CatalogImportTest.php`.

**Interfaces:** produce `Tests\TestDatabaseSafety::{assertBeforeBootstrap,register,assertEnvironment,assertConfiguration}` with the signatures in spec §3. Preserve `assertCatalogMySqlSafety(?Application $app = null): void` for existing workers.

- [ ] Write pure G01-G07 acceptance/rejection tests. Do not bootstrap the application or use migration traits. The negative bootstrap child process runs only the guard script and a sentinel; unexpected acceptance still cannot reach a database.
- [ ] Implement the safety-only PHPUnit configuration, with explicit test files and Composer autoload. Establish a failing guard assertion before wiring the app; do not run an unsafe application process as the red test.
- [ ] Implement pure guard behavior and pre-environment/configuration callbacks; wire ordinary, explicit-MySQL, and direct-worker bootstraps exactly as specified.
- [ ] PHP-lint each changed/new PHP file. Run `vendor/bin/pest --configuration=phpunit.safety.xml --no-coverage`; require both allowed and refused fixtures to pass without application boot, external connection, or migration.
- [ ] Review the safety boundary independently. Only then run a narrow existing SQLite feature test through `vendor/bin/pest --configuration=phpunit.xml tests/Feature/Auth/RegistrationTest.php`.

**Reviewable result:** the old name-based MySQL guard is no longer a bypass, and no test mode can silently select a different target. Do not bypass a refusal by clearing environment values opportunistically.

## Task 2 — Patch packages and publish their assets

**Files:** modify `composer.json`, `composer.lock`; exact generated asset files identified by the update beneath tracked `public/js/filament/`, `public/css/filament/`, `public/fonts/filament/`.

**Interfaces:** produce the reviewed patched dependency lock and matching published browser bundle. No application API or npm change.

- [ ] Reconfirm installed versions and official advisories. Review the spec's explicit version targets; if a newer fix is needed, record the exact substituted versions before resolving.
- [ ] Raise the four direct minimum constraints from spec §4. Run this resolution preview; inspect every changed package before installation:

```sh
composer update filament/filament:5.10.2 filament/spatie-laravel-media-library-plugin:5.10.2 filament/spatie-laravel-settings-plugin:5.10.2 livewire/livewire:4.4.8 --with-dependencies --minimal-changes --dry-run --no-scripts --no-interaction
```

- [ ] After implementation/dependency authorization, execute the same reviewed update without `--dry-run --no-scripts`. Require successful scripts. If publication was skipped/failed, run `php artisan filament:upgrade --no-interaction` locally, then inspect generated changes.
- [ ] Run `composer validate --strict`, `composer check-platform-reqs`, and `composer audit --locked --format=plain`. Compare actual installed versions with the primary advisories even if audit reports zero.
- [ ] Run `cmp vendor/filament/forms/dist/components/rich-editor.js public/js/filament/forms/components/rich-editor.js`; require exit 0. Perform D02's real paste/drop browser smoke without sending a notification. Record bundle/version/evidence, not token or account-secret data.
- [ ] Run D03's focused dashboard/import/settings smoke and relevant existing PHP tests under the safe bootstrap. Do not describe a minified-source grep as a browser test.

**Reviewable result:** package and published-asset diffs form one coherent patch. No wildcard Blueprint update or manual minified-code patch.

## Task 3 — Enforce password confirmation throughout Livewire requests

**Files:** create `app/Http/Middleware/EnsureRecentPasswordConfirmation.php`, `tests/Feature/Settings/TwoFactorHttpSecurityTest.php`; modify `routes/web.php`, `app/Providers/AppServiceProvider.php`, `tests/Feature/Settings/TwoFactorAuthenticationTest.php`.

**Interfaces:** consume framework `RequirePassword`; produce the typed wrapper from spec §5 and persistent registration. No new confirmation storage or changes to Fortify's normal HTTP middleware.

- [ ] Generate the middleware and Pest feature test with installed `make:middleware` / `make:test --pest` commands, `--no-interaction`; inspect `--help` first.
- [ ] Write C01-C05. Obtain genuine signed parent/child snapshots via GET; expire confirmation, submit actual HTTP updates, and assert denial plus unchanged stored auth attributes. Include JSON headers explicitly.
- [ ] Run the focused new file and establish that the existing code permits a mutation or fails the specified denial. A test that fails only from an invalid fixture/payload is not adequate.
- [ ] Implement the composition wrapper: delegate to framework middleware, throw `HttpResponseException` on its JSON 423, preserve redirects. Apply it to the existing conditional settings route and register it persistently.
- [ ] Rerun both settings 2FA files and `tests/Feature/Auth/PasswordConfirmationTest.php`; require recent-confirmation success and expired denial. Keep all fakes/time fixtures local to each case.

**Reviewable result:** a 403/423/redirect is accompanied by no sensitive action side effect, including on nested recovery components.

## Task 4 — Protect private import file paths

**Files:** modify `app/Providers/AppServiceProvider.php`, `tests/Feature/ConfiguratorRuleImportActionTest.php`; extend `tests/Feature/CatalogDataExchangeTest.php` only if U04's generic-import coverage fits that existing file better.

**Interfaces:** native `FileUpload::configureUsing()` + `preventFilePathTampering()`. Existing import helpers/action interfaces remain unchanged.

- [ ] Write U01-U02 and U04 using a real existing private file and real exposed component method. Derive field state/key from the mounted schema, not translated wizard paths. Assert foreign normal download refusal before tampering.
- [ ] Run the tests against the existing default; require a non-null signed URL to make the red case discriminating.
- [ ] Add the one global FileUpload default. No directory/filename exemption or schema rewrite.
- [ ] Extend the existing successful Rules import case to CSV and genuine XLSX, U03. Preserve preview-before-persistence and explicit confirmation assertions.
- [ ] Run the changed files, then `ConfiguratorRuleImportTest.php` and `ConfiguratorRuleExporterTest.php` under `tests/Feature/`; require preview invalidation, token/scope checks, and valid uploads to pass.

**Reviewable result:** client-substituted storage paths are blocked before URL generation; current import workflows remain usable.

## Task 5 — Stable admin authority and Horizon

**Files:** create `database/migrations/2026_10_23_230001_add_is_admin_to_users_table.php`, `tests/Feature/Auth/AdminAuthorizationTest.php`, `tests/Feature/Auth/AdminAuthorizationMigrationTest.php`, `tests/Feature/Auth/HorizonAuthorizationTest.php`; modify `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/HorizonServiceProvider.php`, `config/horizon.php`, `app/Actions/TransferRetainedApplicationData.php`, `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/Catalog/RetainedAccountsTest.php`, `tests/Feature/Catalog/RetainedDataTransferTest.php`, and only the intended admin fixtures/revocation cases in spec Appendix A. `AppServiceProvider`'s existing manage-catalog delegation can stay unchanged.

**Interfaces:** protected boolean cast; `User::canAccessPanel(Panel $panel): bool`; `UserFactory::admin(): static`; existing `manage-catalog`/`viewHorizon` gates. Retained imports consume the current `USER_COLUMNS`, never admin authority.

- [ ] Generate the additive migration and tests with Artisan after safety passes; use the reviewed filename and check for collisions. Write A01-A05/H01 before runtime changes. Migration testing uses isolated memory data only.
- [ ] Implement the column/default/cast, one-time historical two-address backfill, and flag-only admin gate. Do not add the flag to `$fillable` or the default factory definition.
- [ ] Add explicit `admin()` state and update the exact existing actor fixtures. Preserve outsiders/legacy sources and specific email assertions. Include the manually created MySQL subprocess actor. Update the three explicit email-based revocation scenarios identified in spec Appendix A to revoke the flag, preserving their denial/no-write assertions.
- [ ] Narrow only retained users' transferAll projection/comparison as specified; add no privilege copying or automatic grants on import.
- [ ] Make Horizon use enabled + the current common permission; replace the parent auth callback so local requests obey it too. Remove its obsolete email setting, without editing real environment files or worker behavior.
- [ ] Run new auth/Horizon/migration tests, `ProfileUpdateTest.php`, `RetainedAccountsTest.php`, and `RetainedDataTransferTest.php`; then run all affected actor-fixture files on SQLite except explicit MySQL files. The plain Unit actor file may require the same guarded application setup it already uses.
- [ ] Review migration preflight/release procedure with exact expected accounts and lockout refusal. No target database migration or account mutation occurs merely to complete this task's tests.

**Reviewable result:** a non-admin cannot self-promote by email/form/transfer, both intended admins keep authority across email edits, and Horizon follows that authority consistently.

## Task 6 — Consolidate actual authentication and preserve old URLs

**Files:** create `app/Http/Middleware/RedirectFilamentAuthentication.php`, `tests/Feature/Auth/FilamentAuthenticationBridgeTest.php`; modify `app/Providers/Filament/AdminPanelProvider.php`; extend `AuthenticationTest.php`, `TwoFactorChallengeTest.php`, `PasswordResetTest.php`, `RegistrationTest.php` under `tests/Feature/Auth/` for their owned cases.

**Interfaces:** consume named Fortify routes and existing intended-login responses; produce the three-route persistent compatibility bridge in spec §8. Keep native routes registered but prevent their auth/reset actions from executing.

- [ ] Write L01-L05 including the one-request test-only pass-through technique that captures old native snapshots, then restores the real bridge before the HTTP update. No forged memo/checksum.
- [ ] Establish failure with correct credentials on the old native login snapshot and no Fortify challenge. This is the missing boundary, not a test of the Login page's markup.
- [ ] Implement exact named-route GET redirects, original reset signature validation, intended destination preservation, and `abort(403)` for native snapshot updates. Register the bridge with panel `isPersistent: true`.
- [ ] Run bridge/Auth/2FA/reset/registration files. Require no authenticated session before TOTP/recovery completion, no stale native side effects, valid old reset-link compatibility, and disabled registration on every checked entry point.
- [ ] If execution also modifies any file under `app/Filament`, run the required `vendor/bin/filacheck --fix --dirty` and resolve remaining violations. The planned provider-only source edit does not itself trigger that rule. Do not expand scope solely to run the tool.

**Reviewable result:** existing admin URLs remain useful, but all real password authentication/recovery uses the existing Fortify flow.

## Task 7 — Small temporary limits and generic recovery replies

**Files:** create `app/Http/Middleware/ThrottlePasswordRecovery.php`, `app/Http/Responses/PasswordResetLinkResponse.php`, `app/Http/Responses/PasswordResetFailureResponse.php`, `lang/en/security.php`, `lang/he/security.php`, `tests/Feature/Auth/PasswordRecoverySecurityTest.php`; modify `app/Providers/FortifyServiceProvider.php`, `config/fortify.php`, `app/Livewire/Settings/Profile.php`, `tests/Feature/Auth/PasswordResetTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`.

**Interfaces:** exact middleware/response contracts, fixed copy, limiter names, values, and independent buckets in spec §9. No new custom service contract or provider.

- [ ] Generate middleware/response classes/tests with installed generators. Write R01-R06 with fake notifications/cache, frozen time, distinct existing/unknown accounts, independent IP/user buckets, and persisted-attribute assertions.
- [ ] Establish the old unequal responses/missing limits as the red cases without sending real mail or probing production accounts.
- [ ] Implement named route-scoped request limiting, transient response bindings, generic outcome handling, and the direct resend action limiter. Preserve all existing login/TOTP/broker limits and valid-password validation.
- [ ] Run new recovery security, existing reset, and profile files; rerun after each changed test. Assert malformed input still validates, rate overflow is 429 rather than disguised success, and transport exceptions remain failures.
- [ ] Verify equivalent English/Hebrew keys and user-visible feedback. No additional auth layout or terminology cleanup.

**Reviewable result:** bounded requests and consistent public account responses, with working recovery for both admins.

## Task 8 — Integrated verification and release handoff

**Files:** no additional runtime scope. Record evidence against test IDs and the exact reviewed diff; update only these security documents if needed for the implementation handoff.

- [ ] Run the safety-only suite first again. Run affected PHP tests directly via `vendor/bin/pest --configuration=phpunit.xml <files>` so an outer Artisan application cannot boot before the guard.
- [ ] Run `vendor/bin/pint --dirty --format agent`, inspect its changes, and rerun tests whose PHP behavior changed. Run required FilaCheck only under the project rule; all unfixable relevant violations must be resolved.
- [ ] Run `composer validate --strict`, `composer check-platform-reqs`, `composer audit --locked --format=plain`, `npm run build`, and `npm test`. Review new upstream advisory ranges and the published-bundle comparison.
- [ ] Repeat the actual browser editor smoke and the login/intended/2FA/settings/recovery smoke locally with disposable accounts and faked outbound effects. No real admin passwords, production 2FA secrets, reset tokens, or recovery codes are recorded.
- [ ] After affected feature tests pass, ask the user to run the complete default suite with `php artisan test --compact`, as required by this project's instructions. Report separately if it has not run. The guard must already be proven safe before that command.
- [ ] Run explicit MySQL tests only if the changed guard/transfer/fixture paths require them and the existing disposable target is available and authorized. Use `vendor/bin/pest --configuration=phpunit.mysql.xml` with the exact allowlisted environment and restricted credential, not a developer/production database. If unavailable, report that validation gap; SQLite does not prove MySQL behavior.
- [ ] Re-read the diff for unintended grants, stale snapshot paths, bypassed denial responses, unexpected asset/dependency changes, and concurrent file edits. Independent final review must challenge both successful and refused flows.
- [ ] Prepare a concrete release checklist from spec §10: reviewed two-account identity preflight, additive migration before activation, lockfile/asset install, both admins accepted, outsider denied, old reset links valid, no registration. Deployment remains separately authorized.

## Generator and command notes

The commands below are instructions for the future authorized execution, not commands performed during planning. First inspect `php artisan list` and the relevant `--help`. Do not run `make:` commands, install packages, or run application tests to write the plan.

```sh
php artisan make:migration add_is_admin_to_users_table --table=users --no-interaction
php artisan make:middleware EnsureRecentPasswordConfirmation --no-interaction
php artisan make:middleware RedirectFilamentAuthentication --no-interaction
php artisan make:middleware ThrottlePasswordRecovery --no-interaction
php artisan make:class Http/Responses/PasswordResetLinkResponse --no-interaction
php artisan make:class Http/Responses/PasswordResetFailureResponse --no-interaction
php artisan make:test --pest Auth/AdminAuthorizationTest --no-interaction
php artisan make:test --pest Auth/AdminAuthorizationMigrationTest --no-interaction
php artisan make:test --pest Auth/HorizonAuthorizationTest --no-interaction
php artisan make:test --pest Auth/FilamentAuthenticationBridgeTest --no-interaction
php artisan make:test --pest Auth/PasswordRecoverySecurityTest --no-interaction
php artisan make:test --pest Settings/TwoFactorHttpSecurityTest --no-interaction
php artisan make:test --pest --unit TestDatabaseSafetyTest --no-interaction
```

Do not include the suite directory `Feature/` or `Unit/` in test names. The safety test class may be generated only after its generator can boot safely, or authored as a plain test-support exception without app boot to establish the initial safety boundary; it must remain independent of `Tests\TestCase`.

Example targeted groups after Task 1 passes:

```sh
vendor/bin/pest --configuration=phpunit.xml tests/Feature/Settings/TwoFactorHttpSecurityTest.php tests/Feature/Settings/TwoFactorAuthenticationTest.php
vendor/bin/pest --configuration=phpunit.xml tests/Feature/ConfiguratorRuleImportActionTest.php tests/Feature/ConfiguratorRuleImportTest.php tests/Feature/ConfiguratorRuleExporterTest.php tests/Feature/CatalogDataExchangeTest.php
vendor/bin/pest --configuration=phpunit.xml tests/Feature/Auth tests/Feature/Settings tests/Feature/Catalog/RetainedAccountsTest.php tests/Feature/Catalog/RetainedDataTransferTest.php
```

No automatic commit/push is implied. If publication is later requested, review an exact file/hunk allowlist including intended fixture/asset changes, preserve concurrent work, and verify the resulting commit/remote state. Do not use `git add -A`.

## Handoff acceptance

The implementation is ready for review only when each specification test ID has evidence or an explicit unmet limitation; the selected packages and served editor match; both intended administrators retain access; all registration paths remain disabled; and no unreviewed production operation occurred. Passing linters or a source scan alone does not satisfy these security boundaries.

The plan and specification are ready for user review. Implementation remains pending explicit authorization.
