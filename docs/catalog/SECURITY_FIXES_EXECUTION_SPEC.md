# Security fixes execution specification

**Status: proposed; planning only.** The user requested a detailed plan and execution specification on 23 October 2026 and explicitly prohibited implementation. This document does not authorize application edits, dependency installation, migrations, account changes, enrollment, commits, deployment, or messages to other chats.

**Companion:** [implementation plan](SECURITY_FIXES_IMPLEMENTATION_PLAN.md). This specification owns the behavior, interfaces, acceptance criteria, and exclusions; the plan owns task order and execution checks. Read both before implementation.

**Baseline:** `/Users/studioycm/Herd/configurator`, HEAD `244b305d083a90c731cd4bde23d996b4db0f8f3d`. PHP 8.4; Laravel 13.35.0; Filament runtime packages 5.9.0; Livewire 4.4.7; Fortify 1.41.0; Horizon 5.50.0; Pest 5.3.0. Refresh these at execution time. `.ai/rules` was absent. All paths below are relative to this repository root. Concurrent catalog/image/creation planning files and `docs/README.md` are outside the write allowlist.

## 1. Intended outcome and small scope

The two existing administrators retain their access. Administration comes from one protected database flag, and every password login uses the existing Fortify flow. Configured TOTP and recovery codes work on that flow. Existing sensitive Livewire components enforce recent password confirmation on every request. Import fields cannot sign arbitrary private storage paths. Patched packages and their published browser assets ship together. Public recovery gets temporary limits and generic account responses.

These are proposed implementation choices for review, not decisions the user has already approved:

- One `users.is_admin` boolean; no roles package, permissions editor, additional panel, user-management resource, CAPTCHA, permanent lockout, or custom authentication engine.
- Registration remains disabled on Fortify and Filament. Public GET/POST routes and publicly mountable Livewire registration entry points remain unavailable; no test may enable registration globally. A test harness instantiating a vendor component directly is not a public entry point or a reason to add an unrelated vendor override.
- Existing TOTP enrollment remains optional. Recommend that the two admins enroll after the fixes are deployed, but do not enroll them, generate production recovery codes, enforce mandatory MFA, or change their passwords as part of implementation.
- Keep the existing profile/email workflow and email-verification feature configuration. Email ownership is no longer an administration grant. Do not introduce a verification prerequisite that could lock out the current admins.
- Preserve authenticated catalog browsing for non-admin users. Deny their administrative pages and mutations.
- Historical owners retain their existing export/import-failure downloads after admin revocation. Earlier S03 is a policy decision, not a confirmed cross-user vulnerability. No new Export/Import policies or altered retention rules are in this scope.
- No CI trigger changes, infrastructure/security-header project, global query refactor, session purge, `APP_KEY` rotation, or changes to catalog rules, drafts, hashes, locks, and business semantics.

## 2. Priorities and finding traceability

Priority is implementation order/impact, not a claim of a production incident.

| Work | Priority | Evidence and required result |
| --- | --- | --- |
| SEC-00 Test target guard | P0 prerequisite | Earlier S07: a connection named `sqlite` can use a MySQL `DB_URL`. Refuse unsafe targets before providers, connections, or migrations. |
| SEC-01 Dependency/browser patch | P1 | F01/E1: Dashboard RichEditor reaches GHSA-9g92-9qj5-cqjc. Ship patched Filament and published assets; include the new Livewire patch. |
| SEC-02 Password confirmation | P1 | F02: retained 2FA snapshots bypass expired confirmation. Denial must stop the Livewire action, including JSON requests. |
| SEC-03 Private upload paths | P1 | F03/B1: import field metadata can sign owner-gated export paths. Reject client-substituted stored paths. |
| SEC-04 Stable admin/Horizon authority | P1 conditional risk | S01 remains source-confirmed, but the prior local snapshot had only two admins and both listed addresses occupied. S02 is a fail-closed list bug. Replace both email authorities with protected admin state. |
| SEC-05 One login/recovery flow | P1 additional tracing finding | Native Filament login does not use Fortify's TOTP pipeline. Redirect all native auth entry points and reject retained native auth snapshots. |
| SEC-06 Modest abuse controls | P2 | Generic recovery responses; 5 link requests/minute/IP; 10 reset submissions/minute/IP; direct verification resend 6/minute/user. Preserve existing login/TOTP limits and broker cooldown. |

### Corrections discovered while specifying execution

1. `AdminPanelProvider::panel()` currently calls `->login()` without native MFA providers. `Filament\Auth\Pages\Login::authenticate()` can create a session without Fortify's two-factor challenge. Fixing settings reauthentication alone would leave this path open.
2. Merely adding framework `RequirePassword` to Livewire's persistent list is insufficient on the audited version. `Livewire\Drawer\Utils::applyMiddleware()` aborts redirects, but `PersistentMiddleware` ignores an ordinary returned JSON 423 response. The application wrapper below must throw on that denial. Re-check this behavior after the package update, retaining the real HTTP regression regardless.
3. Changing a GET login route to a redirect alone does not invalidate already-issued native Login Livewire snapshots. The persistent bridge must explicitly abort those updates.

These are source traces, not completed browser exploit reproductions.

## 3. SEC-00: refuse unsafe test databases

### Exact files and interfaces

- Create `tests/TestDatabaseSafety.php`, namespace `Tests`, final class `TestDatabaseSafety`.
- Create `tests/bootstrap.php` and `phpunit.safety.xml`.
- Modify `tests/CreatesApplication.php`, `tests/bootstrap-mysql.php`, `phpunit.xml`, `tests/Unit/MySqlSafetyTest.php`, and the direct subprocess bootstrap in `tests/Feature/Catalog/MySql/CatalogImportTest.php`.
- Create `tests/Unit/TestDatabaseSafetyTest.php`.
- Keep `phpunit.mysql.xml`'s explicit MySQL bootstrap and restricted target unchanged.

The class exposes:

```php
public static function assertBeforeBootstrap(bool $mysqlRun): void;
public static function register(\Illuminate\Foundation\Application $app, bool $mysqlRun): void;
/** @param array<string, mixed> $environment @param array<string, mixed> $server @param array<string, mixed> $processEnvironment */
public static function assertEnvironment(array $environment, array $server, array $processEnvironment, bool $mysqlRun, bool $cachedConfigurationExists): void;
/** @param array<string, mixed> $configuration */
public static function assertConfiguration(array $configuration, bool $mysqlRun): void;
```

The two array-based methods are pure guards: no application boot, database connection, PDO, Artisan, filesystem writes, factories, or migration traits. Throw `RuntimeException` on rejection; messages name the condition without including credentials or the supplied URL.

**Default suite:** require literal `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`; configured default name/driver `sqlite`, database `:memory:`; no nonempty `DB_URL`, `DATABASE_URL`, `APP_CONFIG_CACHE`, active connection URL, or read/write overrides; no existing `bootstrap/cache/config.php`. Check every supplied environment source independently, so an empty value in one source cannot conceal an unsafe value in another. No aliases, filesystem SQLite paths, or `_test` suffix heuristics.

**Explicit MySQL:** only `tests/bootstrap-mysql.php` can establish `CATALOG_MYSQL_TEST_RUN === true`. Preserve the literal allowlist: environment `testing`, connection/driver `mysql`, host `127.0.0.1`, port `3307`, database and username `configurator_catalog_test`. Reject URL, read/write, socket, and config-cache overrides. Keep `assertCatalogMySqlSafety(?Application $app = null): void` as the compatibility wrapper used by existing workers/tests.

**Lifecycle:** ordinary `phpunit.xml` loads `tests/bootstrap.php`, which requires Composer autoload and performs the pre-bootstrap guard. `CreatesApplication` checks before requiring the app, registers callbacks, then boots the kernel. Register `afterLoadingEnvironment()` to repeat environment/cache checks and `afterBootstrapping(LoadConfiguration::class, ...)` to check configuration before `RegisterProviders`/`BootProviders`. Recheck after boot defensively. Apply this wiring to the manually booted MySQL subprocess too. Do not force-clear unsafe inputs in PHPUnit XML to make the guard pass.

**Safety runner:** `phpunit.safety.xml` bootstraps only Composer autoload and selects exactly `TestDatabaseSafetyTest.php` and `MySqlSafetyTest.php`. Those files must not use `Tests\TestCase`, application boot, DB migration traits, or live databases. Some other current Unit files use migration traits: the whole Unit suite is not the first verification command.

### Acceptance tests

| ID | Scenario and independent expected result |
| --- | --- |
| G01 | Literal SQLite memory environment/configuration accepted. File-backed SQLite, aliases, MySQL/MariaDB/PostgreSQL in ordinary mode rejected. |
| G02 | Connection named `sqlite`, synthetic MySQL URL/driver/database: rejected without creating a connection. |
| G03 | Each forbidden override in each environment source, including empty-in-one/nonempty-in-another: rejected. |
| G04 | Cached config file, alternate cache path, wrong resolved environment/driver/database, read/write overrides: rejected. |
| G05 | Complete explicit MySQL allowlist accepted; each host/port/user/database/environment/driver/socket/URL mismatch rejected. |
| G06 | Running only the bootstrap script with an unsafe synthetic environment exits before a success sentinel, and never boots the application. Remove inherited `DATABASE_URL` in controlled child fixtures too. |
| G07 | Error output excludes supplied credential/URL values. Default mode cannot select MySQL by changing configuration alone. |

## 4. SEC-01: package versions and served assets

**Files:** `composer.json`, `composer.lock`, and only the generated, tracked assets identified by the selected package update under `public/js/filament/`, `public/css/filament/`, and `public/fonts/filament/`.

Proposed minimums: `^5.10.2` for `filament/filament` and both direct Spatie plugins; `^4.4.8` for `livewire/livewire`. The initial reproducible target is exactly Filament `5.10.2` and Livewire `4.4.8`. Recheck upstream advisories immediately before implementation and select a newer explicit patched version if these floors no longer suffice. Do not silently float versions, add dependencies, or upgrade PHP/Laravel/Flux/Pest/npm packages.

The expected twelve synchronized runtime Filament packages are `actions`, `filament`, `forms`, `infolists`, `notifications`, `query-builder`, `schemas`, both Spatie plugins, `support`, `tables`, and `widgets`. Do not include unrelated `filament/blueprint` in a wildcard update. Start with `--with-dependencies --minimal-changes`; expand the reviewed package list only for a concrete resolver conflict.

The existing Composer scripts run `package:discover`, `filament:upgrade`, and Laravel asset publication. `filament:upgrade` publishes Filament assets and clears configuration/route/view caches. The tracked `public/js/filament/forms/components/rich-editor.js` is served separately from Vite. Package installation or `npm run build` alone cannot establish that this browser fix shipped. Require successful publication and byte equality with `vendor/filament/forms/dist/components/rich-editor.js`.

**Acceptance D01:** selected package versions satisfy the official advisory ranges, platform requirements pass, and unrelated lock changes are justified individually. A zero-advisory Composer result is not sufficient: the earlier audit returned zero while the publisher listed affected versions.

**Acceptance D02:** open the actual Dashboard `sendNotification` modal in an isolated local browser session, load the patched published bundle, and paste then drop custom-block HTML whose encoded preview contains a harmless event-handler marker. The marker remains zero, active event HTML is absent, and ordinary editing works. Use a `data:` image fixture with no external request; close without sending mail. No browser-testing dependency is added. Existing application-level mail sanitization coverage remains useful but cannot replace this client test.

**Acceptance D03:** current settings, dashboard/modal behavior, resource navigation, import preview, and export downloads continue working after the package patch. The Livewire `#[Authorize]` advisory has no demonstrated current usage path, but the patch floor prevents retaining its affected installed version.

## 5. SEC-02: reliable recent-password enforcement

**Create:** `app/Http/Middleware/EnsureRecentPasswordConfirmation.php`, namespace `App\Http\Middleware`.

```php
public function __construct(private \Illuminate\Auth\Middleware\RequirePassword $passwordConfirmation) {}
public function handle(\Illuminate\Http\Request $request, \Closure $next): \Symfony\Component\HttpFoundation\Response;
```

Delegate to the injected framework middleware. If it returns `Illuminate\Http\JsonResponse` with status 423, throw `Illuminate\Http\Exceptions\HttpResponseException` containing that response. Return framework redirects unchanged; Livewire aborts those. Otherwise return the normal response. Keep the framework timeout and intended-redirect behavior; no custom timer, confirmation token, or duplicate password check.

**Modify:** `routes/web.php` uses this class in the existing conditional middleware for `two-factor.show`, replacing only its `password.confirm` entry. `AppServiceProvider::boot()` registers this class with `Livewire\Livewire::addPersistentMiddleware()`. Do not globally replace Laravel's password-confirmation alias or change Fortify's ordinary HTTP routes.

**Enforcement paths:** `App\Livewire\Settings\TwoFactor::{enable,confirmTwoFactor,disable}` and `App\Livewire\Settings\TwoFactor\RecoveryCodes::regenerateRecoveryCodes`. No public sensitive action may execute before the wrapper's denial. This task does not rewrite those components.

**Tests:** extend `tests/Feature/Settings/TwoFactorAuthenticationTest.php` and create `tests/Feature/Settings/TwoFactorHttpSecurityTest.php`.

| ID | Actual sequence and expected result |
| --- | --- |
| C01 | GET the settings page with recent confirmation; extract authentic parent and recovery child `wire:snapshot` attributes using DOM/XPath. Never rewrite memo/checksum. |
| C02 | Expire confirmation to timeout + 1 second, POST each mutation to `Livewire::getUpdateUri()` with `X-Livewire: true` and JSON Accept. Expect 423 and byte-identical password/2FA/recovery attributes. At the exact timeout boundary retain Laravel's existing comparison semantics. |
| C03 | Repeat an expired request with ordinary Livewire/browser headers; require denial/redirect and no mutation. A returned JSON response that permits the action is a failing implementation. |
| C04 | Recent confirmation permits legitimate enable, valid TOTP confirmation, disable, and recovery-code regeneration; assert the intended persisted change. Use valid encrypted fixtures and frozen time, not random factory 2FA strings. |
| C05 | Guest update cannot mutate; a page never previously confirmed is inaccessible; disabled Fortify 2FA retains the current forbidden behavior. |

Real HTTP payload shape: `components => [['snapshot' => $originalSnapshotString, 'updates' => [], 'calls' => [['path' => '', 'method' => $method, 'params' => $parameters]]]]`. Derive the update URI from Livewire. Direct `Livewire::test()->call()` is supplemental only: it does not execute the implicated persistent-middleware path. Test-local helper functions may live in this test file; do not add an application abstraction for the test transport.

## 6. SEC-03: private upload path authorization

**Modify:** `app/Providers/AppServiceProvider.php::boot()`. Register the existing API globally:

```php
\Filament\Forms\Components\FileUpload::configureUsing(function (\Filament\Forms\Components\FileUpload $fileUpload): void {
    $fileUpload->preventFilePathTampering();
});
```

**Field:** `Filament\Forms\Components\FileUpload` at `app/Filament/ConfiguratorRuleDataActions.php::import()`; also fields constructed by `Filament\Actions\ImportAction` through `app/Filament/CatalogDataActions.php::import()`. Validation remains required, MIME-allowlisted, max 10240 KiB for Rules, private, `storeFiles(false)`, `previewable(false)`. No existing default/template/copy fills require exceptions. Do not introduce a broad directory exception.

**Affected hosts:** Rules on `app/Filament/Pages/DataExchange.php` and `app/Filament/Resources/Configurators/RelationManagers/RulesRelationManager.php`; generic imports on DataExchange and these exact pages:

- `app/Filament/Resources/Attributes/Pages/ListAttributes.php`
- `app/Filament/Resources/Options/Pages/ListOptions.php`
- `app/Filament/Resources/Values/Pages/ListValues.php`

Existing action authorization remains `manage-catalog`; preview/confirmation/persistence behavior remains unchanged.

**Tests:** extend `tests/Feature/ConfiguratorRuleImportActionTest.php`.

| ID | Scenario and expected result |
| --- | --- |
| U01 | Admin A, private existing export owned by admin B, known file path, fake local disk. First assert normal export download is 403. Mount the real import action, substitute that path, call the exposed metadata method: no signed URL (`['foreign' => null]`). Parameterize DataExchange and RulesRelationManager hosts. |
| U02 | Repeat with a same-owner pre-existing export path: reject it too, because this import field accepts fresh uploads, not arbitrary stored-file selection. Assert the fixture exists so a missing file cannot make the test pass. |
| U03 | Fresh CSV and real XLSX both preview without persistence, then create the expected rule only after confirmation. Existing preview/token/scope/escaping tests continue passing. |
| U04 | Exercise one generic ImportAction from DataExchange or ListValues through its real mounted field and exposed method; reject the foreign path. This proves the global default covers framework-created fields too. |

Use `Filament\Actions\Testing\TestAction`. DataExchange mounts `TestAction::make('importRules')->schemaComponent(true, 'content')`; RulesRelationManager uses its existing `ownerRecord`, `pageClass => EditConfigurator::class`, and table-action convention. Resolve the mounted schema with `getSchema(getMountedActionSchemaName())`, then `getComponentByStatePath('file', withHidden: true)`. Derive `getKey()`/`getStatePath()` dynamically; set adversarial state with `set($statePath, ['foreign' => $path])`, then `call('callSchemaComponentMethod', $fieldKey, 'getUploadedFiles')->assertReturned(['foreign' => null])`. Do not hardcode translated wizard keys or invoke the field directly.

Keep temporary file cleanup in `finally`. Reuse OpenSpout already installed for the XLSX fixture; no dependency or new parser.

## 7. SEC-04: one stable admin authority

### Model, transition, and imports

**Create migration:** `database/migrations/2026_10_23_230001_add_is_admin_to_users_table.php`. Generate with Artisan at implementation time and normalize the generated filename to this reviewed name if it remains collision-free; on collision, record a later exact filename before editing.

**Model:** `App\Models\User`, existing `users` table. Add `is_admin`, boolean, not nullable, default false; boolean cast in `casts()`. Do not add it to `$fillable`, profile validation, registration data, import input, or public component state. Existing relationships and auth secret casts/hidden fields remain unchanged.

**One-time backfill:** the migration grants the flag only to existing records at the two historically authorized addresses, `ycm@data4.work` and `beny.cr@gmail.com`. Use the query builder and literal addresses in this historical migration, not the evolving User model, a future mutable config list, observers, seeders, or runtime email inference. Fetch candidate IDs/emails and compare email strings strictly in PHP before updating by ID; plain SQL `whereIn` is not sufficient under MySQL's case-insensitive collation. Before altering a populated users table, require exactly one existing match for each address; otherwise throw before any schema/data change. Empty fresh databases migrate successfully; new users always default false. Deployment preflight must identify and review the two actual target accounts before this migration; the source audit cannot establish their identity on production. Do not create missing accounts, change passwords, mark emails verified, overwrite secrets, or grant every existing user administration. Update only the two reviewed IDs without changing timestamps. `up(): void` owns the column/backfill; `down(): void` drops only this column for disposable development rollback. Verify the affected IDs and expected count before enabling the new application release.

`User::canAccessPanel(Panel $panel): bool` requires the `admin` panel ID and `is_admin === true`. Keep `manage-catalog` delegated through that method so existing application Actions, workers, resources, and custom components continue using the existing Gate. An admin changing email retains admin authority; a non-admin taking an old allowlisted email gains none. Email changes still clear verification as today.

**Factory:** add `Database\Factories\UserFactory::admin(): static`, setting only `is_admin => true`. Do not add `is_admin` to the default factory definition: retention tests deliberately create users on old schemas without that column. Do not infer the state from an email. Explicitly convert authorized actor fixtures listed in Appendix A; preserve outsider fixtures and old-schema source-user factories.

**Retained-data boundary:** modify `app/Actions/TransferRetainedApplicationData.php::transferAll()` for the `users` table only to select, compare, hash, and import the existing reviewed `USER_COLUMNS` projection on both source and target. Do not add `is_admin` to that constant. `handle()` already uses the projection; preserve it. Source data cannot grant administration; a new imported account defaults false; existing target grants survive repeat transfers without a false whole-row conflict. Preserve all other table comparisons, reviewed hashes, encryption-key checks, and target restrictions. A changed projection requires a fresh transfer review hash, not reuse of a historical token.

### Horizon

**Modify:** `app/Providers/HorizonServiceProvider.php`, `config/horizon.php`.

`viewHorizon` requires `horizon.enabled`, a real user, and that user's current `manage-catalog` permission. Remove the active/commented `allowed_emails` setting and treat `HORIZON_ALLOWED_EMAILS` as obsolete; no environment file is edited as part of the code task. After `parent::boot()`, explicitly set `Horizon::auth()` to reject a missing request user and use `Gate::forUser($request->user())->allows('viewHorizon')` otherwise. This prevents Horizon's parent `|| environment('local')` callback from bypassing the same rule locally. Keep worker configuration, queue balancing, metrics, and Redis unchanged.

### Tests

Create `tests/Feature/Auth/AdminAuthorizationTest.php`, `tests/Feature/Auth/AdminAuthorizationMigrationTest.php`, and `tests/Feature/Auth/HorizonAuthorizationTest.php`; extend `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/Catalog/RetainedAccountsTest.php`, and `tests/Feature/Catalog/RetainedDataTransferTest.php`.

| ID | Scenario and expected result |
| --- | --- |
| A01 | Default user/non-admin denied admin panel and manage-catalog, even at either historical email and with `email_verified_at` set. Explicit admin accepted at an unrelated email. Other panel IDs denied. |
| A02 | Submit profile changes and unexpected `is_admin` input as a non-admin: flag stays false. Changing to a free historical email gives no rights. Admin profile/email change preserves true. |
| A03 | Fresh request after `is_admin` revocation denies a previously authorized panel snapshot/action and writes nothing. Preserve current shared catalog access for authenticated viewers. |
| A04 | On isolated SQLite only, prepare a pre-column users schema, run this migration, assert exactly the two pre-existing expected accounts gain the flag; an outsider stays false and credentials/timestamps/secrets remain unchanged. A populated table missing either exact identity, or containing only a case variant, refuses before the column is added. Empty-schema migration succeeds and fresh users default false. Restore test schema in `finally` or isolate this case in its own disposable in-memory connection. |
| A05 | Legacy-source transfer first/repeat succeeds, new imported user false, existing target true preserved, and a source `is_admin=true` cannot grant target rights. Existing ID/hash/encryption/rollback assertions remain. |
| H01 | Gate and `Horizon::check($request)` deny guest/non-admin, accept each admin when enabled, and deny everyone when disabled in both local and production environments. Use `app()->detectEnvironment()` and restore it; changing config alone does not exercise the local shortcut. No Redis connection required. |

## 8. SEC-05: Fortify is the single password authentication path

**Create:** `app/Http/Middleware/RedirectFilamentAuthentication.php`, namespace `App\Http\Middleware`, `handle(Request $request, Closure $next): Symfony\Component\HttpFoundation\Response`.

**Modify:** `app/Providers/Filament/AdminPanelProvider.php::panel()`. Keep native `->login()` and `->passwordReset()` to preserve their named URLs, but add the bridge through `->middleware([RedirectFilamentAuthentication::class], isPersistent: true)`. Leave registration disabled. This middleware affects exactly:

| Existing named route | Ordinary GET behavior |
| --- | --- |
| `filament.admin.auth.login` | Redirect to named `login`. Preserve an existing intended URL; for a direct admin-login visit with none, use the admin Dashboard URL as the intended destination. |
| `filament.admin.auth.password-reset.request` | Redirect to named `password.request`. |
| `filament.admin.auth.password-reset.reset` | Explicitly validate the original signed URL before redirecting to `password.reset` with its `token` and `email` query values. Invalid/expired signature gives 403. Do not log either value. |

For a request replaying one of these native auth snapshots (`X-Livewire` update), explicitly `abort(403)` before redirecting or calling `$next`. Do not merely return a denial response, and do not rewrite signed snapshots. Persistent middleware rematches the original route from the signed memo path/method. Other panel pages pass through.

Fortify's existing login and two-factor response contracts already honor `redirect()->intended(...)`; keep them unchanged. Only a completed TOTP/recovery challenge may create the session when that account has confirmed 2FA. Native Filament recovery submissions become inaccessible; new recovery goes through the same Fortify limits/responses below. Existing valid native reset links still reach the form using the same configured password broker. Logout and ordinary settings URLs remain unchanged.

**Tests:** create `tests/Feature/Auth/FilamentAuthenticationBridgeTest.php`; extend `tests/Feature/Auth/AuthenticationTest.php`, `TwoFactorChallengeTest.php`, `PasswordResetTest.php`, and `RegistrationTest.php` only for the scenarios owned here.

| ID | Scenario and expected result |
| --- | --- |
| L01 | Guest admin deep link -> native login compatibility route -> Fortify login -> successful login returns to the original admin URL. Direct admin/login defaults to admin Dashboard. No redirect loop for authenticated users. |
| L02 | A real confirmed-2FA admin, correct password: guest remains unauthenticated until valid TOTP or recovery code. Wrong code denies; used recovery code cannot be reused. Admin without configured 2FA retains ordinary login. |
| L03 | Real retained native login snapshot with correct credentials is 403 and leaves session guest. Retained native request/reset snapshots likewise cannot send mail or change password/tokens. |
| L04 | Valid signed old native reset URL preserves token/email and reaches Fortify form; invalid/expired signature fails. Successful reset still validates the broker token, changes only the intended account's password, and invalidates the token. |
| L05 | Fortify GET/POST `/register` remain 404 with no user/session change; panel registration route is absent and `/admin/register` GET/POST remain unavailable. No legitimate public registration snapshot is issued. Do not use `Livewire::test(Register::class)` to fabricate a registration entry point the application never exposes. |

For L03, a test-only one-request pass-through binding of the bridge may capture the real old native GET/snapshot. Restore/forget that binding before POSTing to the actual Livewire endpoint. This simulates an already-issued snapshot without forging memo or checksum. New tests must exercise both the JSON header path and no-side-effect assertions. Do not disable application auth/CSRF middleware globally to make a test pass.

## 9. SEC-06: temporary recovery limits and generic responses

### Exact new boundaries

- Create `app/Http/Middleware/ThrottlePasswordRecovery.php`, typed `handle(Request $request, Closure $next): Response`; inject `Illuminate\Routing\Middleware\ThrottleRequests`. Apply its named limiter only when `routeIs('password.email', 'password.update')`; other Fortify routes pass through.
- Modify `config/fortify.php` middleware to retain `web` and add this class. Do not re-register all Fortify routes or mutate them after registration.
- In `App\Providers\FortifyServiceProvider::configureRateLimiting()`, register `password-recovery`: 5/minute for `password.email`, 10/minute for `password.update`, keyed by separate route-name/IP prefixes. No account-only lockouts; no new login limits. Existing login 5/minute/email+IP, TOTP limits, reset-token expiry, and broker's 60-second account cooldown remain.
- Create `app/Http/Responses/PasswordResetLinkResponse.php`, implementing both Fortify `SuccessfulPasswordResetLinkRequestResponse` and `FailedPasswordResetLinkRequestResponse` contracts. Constructor accepts `string $status` supplied by Fortify; `toResponse(mixed $request): Response` emits the same public response for sent, unknown user, and broker cooldown statuses. JSON: 200 with `message`; browser: redirect back with the same `status`, no account-existence field errors. Syntactically invalid input retains ordinary validation. Do not swallow mail/provider exceptions or change a 429 into success.
- Create `app/Http/Responses/PasswordResetFailureResponse.php`, implementing Fortify `FailedPasswordResetResponse`. Same constructor/response signature. Nonexistent user and invalid/expired/used token share the generic `email` error (JSON 422, browser redirect back). Keep password-policy validation and successful reset behavior unchanged.
- Bind these contracts in existing `FortifyServiceProvider::register()` using normal transient bindings. No new service provider or custom response interface.
- Modify `App\Livewire\Settings\Profile::resendVerificationNotification()` to apply a 6/minute/user Laravel limiter immediately before sending. The seventh call sets a user-visible validation error and sends nothing. The already-verified no-send branch stays as today. The existing Fortify verification endpoint keeps its own 6/minute throttle; this is a bound on the direct action, not a claim of one combined global bucket.
- Create `lang/en/security.php` and `lang/he/security.php` with matching keys `password_reset_requested`, `password_reset_invalid`, and `verification_rate_limited`.

**Exact English copy:**

- `password_reset_requested`: `If an account exists for this email address, you will receive password reset instructions.`
- `password_reset_invalid`: `This password reset link is invalid or has expired. Request a new link.`
- `verification_rate_limited`: `Too many verification requests. Please try again in a minute.`

**Hebrew copy:**

- `password_reset_requested`: `אם קיים חשבון עם כתובת האימייל הזו, יישלחו אליו הוראות לאיפוס הסיסמה.`
- `password_reset_invalid`: `הקישור לאיפוס הסיסמה אינו תקין או שפג תוקפו. יש לבקש קישור חדש.`
- `verification_rate_limited`: `נשלחו יותר מדי בקשות אימות. אפשר לנסות שוב בעוד דקה.`

**Tests:** extend `tests/Feature/Auth/PasswordResetTest.php` and `tests/Feature/Settings/ProfileUpdateTest.php`; create `tests/Feature/Auth/PasswordRecoverySecurityTest.php`.

| ID | Scenario and expected result |
| --- | --- |
| R01 | Existing, unknown, and broker-throttled valid addresses produce equal JSON status/body and equal browser redirect/status/error shape. Notifications are faked; only eligible existing account receives mail. Do not claim constant-time/timing-enumeration protection. |
| R02 | Link requests 1-5 allowed, 6th gives 429; reset submissions 1-10 reach validation, 11th 429. Different IP independent; the endpoint buckets independent; >60 seconds permits requests again. Invalid input counts too. |
| R03 | Repeated existing-account link request within 60 seconds returns the same generic response but sends only one notification. Requests for different unknown addresses cannot evade the IP bucket. |
| R04 | Unknown user + fabricated invalid token and existing user + invalid/expired/used token share the same generic failure. Password policy/confirmation errors remain meaningful for valid tokens. |
| R05 | Valid reset token still resets only its account and cannot be reused; no change to 2FA secrets or admin flag. |
| R06 | Direct resend action permits 6 calls, blocks 7th, is independent between users, recovers after 60 seconds, and sends nothing for an already-verified account. Freeze time and fake notifications; no live email. |

## 10. Execution and release boundaries

All new classes live in Laravel's existing conventional `app/Http` and `tests` structure. No new top-level domain folders, generic security service, role package, or separate framework are needed. No new Resource/widget/filter/layout is added. Existing `Filament\Actions\Action`/`RichEditor` on Dashboard remain `manage-catalog`-authorized; their visual layout and message validation are unchanged. Existing import actions retain their locations, visibility, and Gate authorization.

Deployment is a separate future authorization. Rehearse locally first. Use the reviewed lockfile with `composer install`, never `composer update` on the server. Review target identities before the additive admin migration, migrate before the new app code serves requests, verify exactly the intended two accounts have the flag, refresh assets/caches with existing deployment mechanisms, and verify both admins' access plus outsider refusal. Preserve old valid reset links; do not request or reset production credentials for testing. Do not activate the new release if either expected admin is missing or if the grant count/IDs differ from the reviewed preflight.

Rollback does not automatically drop `is_admin`, erase grants, rotate keys, or run `migrate:rollback` on production. Keep additive schema/data and use a reviewed compatible application release or fix forward. Reverting to the old email admission/native-login code reopens the identified weaknesses and is not the normal recovery plan. Repair a broken test guard before further database-backed testing; never bypass it.

## 11. Evidence and documentation

- Earlier source register: [Audit 3 security findings](research/laravel-architecture/AUDIT_3_SECURITY_FINDINGS.md). Its snapshots/classifications are historical; this specification supersedes the proposed S01/S02 fixes and records the newer findings from this chat.
- [Filament rich-editor preview advisory](https://github.com/filamentphp/filament/security/advisories/GHSA-9g92-9qj5-cqjc), [tenant-tooltip advisory](https://github.com/filamentphp/filament/security/advisories/GHSA-9f9v-vm6w-3wx6), [upload-extension advisory](https://github.com/filamentphp/filament/security/advisories/GHSA-q2ph-j76w-c7qx).
- [Livewire authorization advisory](https://github.com/livewire/livewire/security/advisories/GHSA-mhf7-xp6r-6qrc), [persistent middleware](https://livewire.laravel.com/docs/4.x/security#configuring-persistent-middleware).
- [File path authorization](https://filamentphp.com/docs/5.x/forms/file-upload#authorizing-existing-file-paths), [Filament security](https://filamentphp.com/docs/5.x/advanced/security), [export authorization](https://filamentphp.com/docs/5.x/actions/export#authorization).
- [Fortify password confirmation/recovery](https://laravel.com/framework/docs/13.x/fortify), [rate limiting](https://laravel.com/framework/docs/13.x/routing#rate-limiting), [Horizon authorization](https://laravel.com/framework/docs/13.x/horizon#dashboard-authorization), [Laravel testing environment](https://laravel.com/framework/docs/13.x/testing#environment).
- Installed sources verified: `vendor/livewire/livewire/src/{Drawer/Utils.php,Mechanisms/PersistentMiddleware/PersistentMiddleware.php}`, `vendor/filament/filament/{routes/web.php,src/Panel/Concerns/HasAuth.php,src/Panel/Concerns/HasMiddleware.php,src/Auth/Pages/Login.php}`, Fortify routes/controllers/responses, Laravel bootstrap/config URL parsing, and the served/published editor bundle.

Only documentation was written for this handoff. No application tests, dependency resolution/install, application boot, migrations, runtime security probes, or production connections were performed for the planning turn. Verification of document structure/links is not evidence that any fix works.

## Appendix A. Exact existing admin-actor fixture files

These 64 files contained allowlist-email actor fixtures at the planning baseline. Convert only fixtures that intentionally represent administrators; preserve explicit email assertions, outsider cases, legacy source schemas, and unrelated concurrent edits. Convert the raw subprocess actor in the MySQL import test as well. Do not globally replace every `User::factory()` or make default users administrators.

Also adapt existing revocation scenarios in `tests/Feature/ManageValueTagsTest.php:83`, `tests/Feature/ConfiguratorRuleImportActionTest.php:148`, and `tests/Feature/TagWorkspaceTest.php:145`: they currently revoke authority by changing email. Revoke `is_admin` explicitly through trusted test setup and assert the same denial/no-write result. Keep separate A02 coverage proving that an ordinary admin email edit preserves authority. Search all affected tests for equivalent email-based revocation assumptions; do not convert the obsolete assumption into a false failure of the new design.

```text
tests/Feature/AdminUxRefinementsTest.php
tests/Feature/AdminValueIdentificationTest.php
tests/Feature/AdminValuePresentationTest.php
tests/Feature/AttributeValueAssignmentTest.php
tests/Feature/AttributeValueBatchTest.php
tests/Feature/Catalog/CatalogDiscoveryTest.php
tests/Feature/Catalog/CatalogImportTest.php
tests/Feature/Catalog/CatalogSchemaTest.php
tests/Feature/Catalog/CatalogSnapshotTest.php
tests/Feature/Catalog/CatalogStatusTest.php
tests/Feature/Catalog/ConfiguratorDefinitionTest.php
tests/Feature/Catalog/ConfiguratorIntegrationTest.php
tests/Feature/Catalog/ConfiguratorRuntimeTest.php
tests/Feature/Catalog/ContextInheritanceTest.php
tests/Feature/Catalog/GroupSettingsTest.php
tests/Feature/Catalog/LegacyCanonicalLibraryImportTest.php
tests/Feature/Catalog/MySql/CatalogDiscoveryTest.php
tests/Feature/Catalog/MySql/CatalogImportTest.php
tests/Feature/Catalog/MySql/CatalogStatusConcurrencyTest.php
tests/Feature/Catalog/MySql/ConfiguratorPreviewSaveConcurrencyTest.php
tests/Feature/CatalogDataExchangeTest.php
tests/Feature/ConfiguratorEditorChangesTest.php
tests/Feature/ConfiguratorPreviewPresentationTest.php
tests/Feature/ConfiguratorPreviewReconciliationTest.php
tests/Feature/ConfiguratorPreviewSnapshotTest.php
tests/Feature/ConfiguratorPreviewWorkspaceTest.php
tests/Feature/ConfiguratorRuleExporterTest.php
tests/Feature/ConfiguratorRuleImportActionTest.php
tests/Feature/ConfiguratorRuleImportTest.php
tests/Feature/Filament/AdminAppearanceTest.php
tests/Feature/Filament/AdminBadgesTest.php
tests/Feature/Filament/AdminTabLoadingTest.php
tests/Feature/Filament/AttributeValueNavigationTest.php
tests/Feature/Filament/BatchCatalogActionsTest.php
tests/Feature/Filament/CanonicalDefinitionsTest.php
tests/Feature/Filament/CatalogAdministrationTest.php
tests/Feature/Filament/CompactResourceHeaderTest.php
tests/Feature/Filament/ConfigEngineDemoInteractionTest.php
tests/Feature/Filament/ConfigurationPartFormTest.php
tests/Feature/Filament/ConfiguratorAdministrationTest.php
tests/Feature/Filament/ConfiguratorRulesTest.php
tests/Feature/Filament/ConfiguratorWorkspaceTest.php
tests/Feature/Filament/ContextSettingsTest.php
tests/Feature/Filament/GroupConfiguratorCreationTest.php
tests/Feature/Filament/ItemDrawerEditorsTest.php
tests/Feature/Filament/ItemListDrawerTest.php
tests/Feature/Filament/ItemListOrderingTest.php
tests/Feature/Filament/ProductEditingTest.php
tests/Feature/Filament/ProductProfilesTableTest.php
tests/Feature/Filament/RowOrderingTest.php
tests/Feature/Filament/ScopedTableSearchTest.php
tests/Feature/Filament/SplitListCreationTest.php
tests/Feature/Filament/TableConfigurationStandardsTest.php
tests/Feature/FilamentMailTestNotificationTest.php
tests/Feature/ItemListContextTest.php
tests/Feature/LocalAdminProfileTest.php
tests/Feature/ManageValueTagsTest.php
tests/Feature/ProductFieldsTest.php
tests/Feature/ProductProfileEditingTest.php
tests/Feature/SuggestedIdentifiersTest.php
tests/Feature/TagWorkspaceTest.php
tests/Feature/UiWordingTest.php
tests/Feature/ValueSemanticIdTest.php
tests/Unit/OptionRulePresentationTest.php
```
