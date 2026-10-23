# Audit 3 — security-relevant findings (for implementation review)

**Snapshot.** 2026-10-23, checkout `/Users/studioycm/Herd/configurator`, branch `master`. Tree audited = HEAD `aad66ff` + the then-uncommitted changes, which another session committed as `87c7688` during the audit; line numbers below were re-verified against that tree. PHP 8.4.27, laravel/framework 13.35.0, livewire 4.4.7, filament 5.9.0, pest 5.3.0. `.ai/rules` does not exist.

**What this is.** The security-relevant subset of the third Laravel structure/responsibility audit (R/O/V register refresh). Three tracing passes plus two adversarial verification passes (two independent lenses per finding, source re-read + smallest-alternative) ran read-only. It is **not** a full penetration test or OWASP sweep; no runtime, browser or database tests were executed. `composer audit` and `npm audit --omit=dev` were run read-only: **0 advisories, 0 vulnerabilities.**

**Classification key.** *supported risk* = reachable from source, not reproduced; *confirmed source behavior* = literally true in code, no demonstrated incident; *latent inconsistency* = no bypass shown. Severity is the value of fixing, not an incident claim.

---

## S01 — Admin authority comes from a hard-coded, user-editable email list (supported risk, High)

- `app/Models/User.php:68-75` — `canAccessPanel()` returns `in_array($this->email, ['ycm@data4.work', 'beny.cr@gmail.com'])`; ignores `$panel`, never checks `hasVerifiedEmail()`. `MustVerifyEmail` import is commented out (`:5`).
- `app/Providers/AppServiceProvider.php:62` — the single Gate `manage-catalog` is just `canAccessPanel(admin)`; every Action, Livewire action and Filament action authorizes through it.
- `app/Livewire/Settings/Profile.php:31-57` — any signed-in user may change their own email (`required|lowercase|email|max:255|unique(ignore self)`), saved immediately; `email_verified_at` is nulled but nothing enforces verification (no `verified` middleware on `routes/web.php`, no `->emailVerification()` on the panel).
- Mitigations already present: public registration is disabled (`config/fortify.php:147` commented out), and the `unique` rule blocks taking an address that is already registered.
- Exposure: a signed-in non-admin can switch their email to a listed address that is **not** currently registered as a user and immediately pass `manage-catalog`. Not demonstrated; depends on whether both listed addresses exist as users.
- Smallest fix: move the allowlist to config (e.g. `config('catalog.admin_emails')`, env-backed), compare with `in_array(strtolower($email), $list, true)`, and require `$this->hasVerifiedEmail()` in `canAccessPanel()`. Add `UserFactory::admin()`.
- Test: a non-admin who updates their email to a listed address does not pass `manage-catalog` until verified; an admin with a verified listed email does.

## S02 — Horizon gate compares the email to the whole plural setting (confirmed source behavior, Medium)

- `app/Providers/HorizonServiceProvider.php:41` — `return $user->email === config('horizon.allowed_emails');` while `config/horizon.php:9` is `'allowed_emails' => env('HORIZON_ALLOWED_EMAILS', 'ycm@data4.work')`. A comma-separated list locks everyone out; this is also a second admin allowlist that drifts from S01.
- Smallest fix: split/trim the setting and `in_array(..., true)`, or point it at the same config list as S01. No test references `viewHorizon`; add one.

## S03 — Export/import downloads outlive admin rights; catalog export jobs never re-check the Gate (supported risk, Medium)

- No `app/Policies` and no `Gate::policy()`; vendor `filament/actions/src/Exports/Http/Controllers/DownloadExport.php:30-36` therefore falls back to `abort_unless($export->user()->is($user), 403)` (ownership only). Same pattern for failed-import CSV downloads.
- Rule exports re-check: `ConfiguratorRuleExporter.php:97`, `Exports/Jobs/ExportConfiguratorRulesCsv.php:25`, `Exports/Jobs/PrepareConfiguratorRulesExport.php:13` (`Gate::forUser($this->export->user())->authorize('manage-catalog')`). Catalog exporters (`CatalogExporter`, `ValueExporter`, `AttributeExporter`, `AttributeValueExporter`) have no Gate; only the UI trigger `CatalogDataActions.php:23-29` authorizes.
- Consequence: a former admin keeps downloading every earlier export via stored notification links; a queued catalog export started before revocation completes and is downloadable.
- Smallest fix: register policies for Filament's `Export` and `Import` models whose `view()` requires owner **and** `Gate::forUser($user)->allows('manage-catalog')` (Filament uses a policy when one exists). Creating `app/Policies` is a new base folder and needs approval. Optionally add the same Gate check to the catalog export job path.

## S04 — Ambient authorization and actor inside ConfiguratorEditorChanges (latent inconsistency, Medium)

- `app/Services/ConfiguratorEditorChanges.php:37, :46, :60` — `Gate::authorize('manage-catalog')` on the ambient user; `:387` — the capture HMAC is bound to `auth()->id()`.
- `app/Actions/SaveTestedConfiguratorChanges.php:18/:28` — has `$actor` but calls `prepare()` without it. `app/Services/AdminNavigationCounts.php:15` also uses ambient `Gate::allows`. Every other Action uses `Gate::forUser($actor)`; `ConfiguratorDefinitionLoader::forPreview :88-90` does too.
- No cross-user or stale-fingerprint bypass was found (verified twice). Existing test `ConfiguratorPreviewWorkspaceTest ~:183` switches to an *unauthorized* user, so it proves the Gate, not the user-binding of the signature.
- Smallest fix: thread `User $actor` into `prepare()`/`verifiedRows()` (optionally `capture()`/`savedRows()`), use `Gate::forUser($actor)` and `$actor->id` in `signature()`; pass `$actor` from `SaveTestedConfiguratorChanges:28`, `auth()->user()` from Livewire callers. Do not split the class.
- Test: capture as authorized admin A, save as authorized admin B → `capture_expired` ValidationException.

## S05 — Reviewed status-change protocol lives in a Filament action factory (confirmed source behavior, Low)

- `app/Filament/Resources/StatusActions.php:86-99` owns `DB::transaction(attempts:3)` + `CatalogRevisions::batch` + `ChangeCatalogStatus::lockForReview` + `hash_equals(reviewToken)`; `reviewToken() :116-135` hashes `auth()->id()` whereas `BatchCatalogChanges::token :356` and `ManageValueTags :73` hash `$actor->id`. Same user in the same request, so a style difference, not a bypass. `CatalogImporter.php:90` legitimately calls `ChangeCatalogStatus` directly.
- Optional fix (matches BatchCatalogChanges/ManageValueTags): move `reviewToken()` into `ChangeCatalogStatus::review(User, Collection): string` plus `applyReviewed(User, Collection, string $token, bool $active, ?string $groupBehavior, ?bool $hidden)`; StatusActions keeps the Validator, form, notification and dispatches (`:100-107`).

## S06 — Two lock-order inversions against the Groups → Configurator → Context → canonical convention (supported risk, Medium; deadlock not reproduced)

- Convention (all `lockForUpdate`): `SaveCatalogProduct:31-32`, `AssignConfiguratorGroups:32-33`, `ChangeCatalogStatus::lockForReview:70-78`, `DeleteConfigurator:20-21`, `BatchCatalogChanges:101-109`, `SaveGroupSettings:30`, `SaveConfiguratorDefinition::save:40-43` (Configurator → Context → canonical), `ConfiguratorRuleImport::apply:66-71`.
- Outlier A: `app/Actions/SaveTestedConfiguratorChanges.php:23-25` locks Configurator, then `ConfiguratorEditorChanges::fingerprint(lock:true) :181-184` locks canonical Attr/Opt → Product → one Group row → Context. Group after Configurator; canonical before Context.
- Outlier B: `app/Filament/Imports/AttributeImporter.php:38-41` / `AttributeValueImporter.php:56-60` run `SaveCanonicalDefinition`/`SaveCanonicalOption` (lock using Configurators then the Attribute/Option) and then `CatalogImporter::saveStatus :90` → `ChangeCatalogStatus::handle` → `lockGroups()` inside one per-row transaction. Groups after Configurators.
- Only the outermost `DB::transaction` retries (vendor `ManagesTransactions`); relation-manager writes via `SaveConfiguratorDefinition::change :157-161` have `attempts` 1, so a victim shows "could not be saved". No lock-order test exists (`tests/Feature/Catalog/MySql/CatalogStatusConcurrencyTest` covers stale review, not ordering).
- Smallest fix A (2 lines, one file): in `SaveTestedConfiguratorChanges` add `app(CatalogIntegrity::class)->lockGroups();` before `:23` and `CatalogContextSettings::current(lock: true);` right after `:23` (mirrors `SaveConfiguratorDefinition::save :40-41`). The `json_encode` fingerprint payload at `ConfiguratorEditorChanges:186` is an explicit array, so hashes are unchanged.
- Smallest fix B: in `CatalogImporter`, when `mappedData(['is_active','is_hidden']) !== []` and the record exists, call `app(CatalogIntegrity::class)->lockGroups()` first inside the `saveRecord` transaction.
- Test: driver-agnostic `DB::listen` assertion that a tested save issues `FOR UPDATE` in the order `groups, configurators, catalog_context_settings, attributes, options, products`; optionally a two-process MySQL test like `CatalogStatusConcurrencyTest`.

## S07 — Destructive-test guard can be skipped by exported DB variables (supported risk, Medium)

- `tests/CreatesApplication.php:16` runs `assertCatalogMySqlSafety` only when `CATALOG_MYSQL_TEST_RUN` is defined or `database.default === 'mysql'`. The check keys on the connection **name**, not the resolved driver: an exported `DB_URL=mysql://…@127.0.0.1:3307/configurator_catalog_dev` under the default `sqlite` connection resolves to the MySQL driver, skips the guard, and `RefreshDatabase` (applied to all Feature tests in `tests/Pest.php:15`) runs `migrate:fresh` against that database. The MySql test files protect themselves (`require_once bootstrap-mysql.php`), the default suite does not.
- Smallest fix (≈4 lines, `tests/CreatesApplication.php` only): fail closed. If `CATALOG_MYSQL_TEST_RUN` is defined keep the current call; otherwise throw unless `database.default === 'sqlite'`, `connections.sqlite.database === ':memory:'` and `connections.sqlite.url` is empty. Optionally add `force="true"` to the `DB_*` entries in `phpunit.xml` and `<env name="DB_URL" value="" force="true"/>`.
- Test: `tests/Unit/MySqlSafetyTest.php` already covers the MySQL bootstrap; add one case for the non-MySQL branch with an exported `DB_URL`.

## S08 — Stored appearance settings are validated at every read; a future tightening breaks all admin and catalog renders with no in-app recovery (supported risk, Medium)

- `app/Services/AdminAppearance.php:68-72` — `current()` runs `validate()` (strict `array:` key whitelist `:35`, `between` ranges `:37`, cross-field fit rules `:49-54`) on the stored row and throws `ValidationException`. Readers: `AdminPanelProvider.php:49-51`, `AppServiceProvider.php:94` (every Action modal), `appearance-variables.blade.php:1`, `catalog.blade.php:7`, `ProductConfigurator.php:79`, and the repair page itself `Filament/Pages/AdminAppearance.php:53`.
- The only writer validates first (`SaveAdminAppearanceSettings:23`), so today a bad row needs a code change (tightened range, removed key, new fit rule) or a direct DB edit. Blast radius is total; recovery is DB-only.
- Smallest fix: in `current()` wrap the validate in `try/catch (ValidationException)`, `Log::warning(...)` and fall back to `$this->defaults()` (per-key clamping does not satisfy the cross-field rules). Keep `validate()` strict for the save path.
- Test: seed an out-of-range row, render the admin panel, expect defaults and a logged warning.

## S09 — Scoped appearance memo can hold rolled-back settings (narrow conditional defect, Low)

- `app/Actions/SaveAdminAppearanceSettings.php:33` calls `rememberSaved()` after `DB::transaction` returns, unconditionally; `AdminAppearance::current() :62-63` returns the memo before the `transactionLevel() > 0` branch (`:69`); `rememberSaved :76-79` is not after-commit aware; the service is `scoped()` (`AppServiceProvider:44`). Verified against vendor `DatabaseTransactionsManager` (rollback discards queued commit callbacks, but the memo write already happened).
- Only production caller (`Filament/Pages/AdminAppearance.php:105` via `ConfiguratorFormErrors::run`) runs at transaction level 0, so no live path. The existing test (`AdminAppearanceTest.php:122-129`) asserts on `new AdminAppearance`, not the scoped instance.
- Smallest fix: move `rememberSaved` into `DB::afterCommit(...)` inside the closure (same mechanism as `:28`) and drop `:33`. Test on `app(AdminAppearance::class)` after an enclosing rollback.

## S10 — Services/Actions throw HTTP exceptions that reach console and import paths (confirmed source behavior, Low)

- `app/Console/Commands/CatalogSnapshotCommand.php:25` → `CatalogSnapshots::revision` → `CatalogAvailability::group :42` `abort_if(…, 404)`, `CatalogSnapshots :26` `abort_if(…, 409)`, `:70,:73` `ServiceUnavailableHttpException`; `ChangeCatalogStatus :42,:44,:109` `abort_unless` reached from `CatalogImporter::saveStatus :90` (falls into Filament's generic `Throwable` branch).
- Smallest fix: catch `HttpExceptionInterface` in the command and return `FAILURE` with the message; longer term throw `ModelNotFoundException`/`ValidationException`/a small domain exception and let HTTP callers map them (as `GroupShow.php:52-55` already does). Add one command test (hidden group, branch group).

## S11 — Tag normalization/validation has drifted across writers; import can store untrimmed duplicates (supported risk, Low; **files currently being edited by Codex**)

- `app/Actions/SaveValueTags.php:21-25` trims + `distinct:strict`; `BatchCatalogChanges.php:43,307-310` trims + loose `distinct`; `SaveCanonicalDefinition.php:39-40` (used by `ValueImporter`) does not trim, loose `distinct`. `ValueImporter.php:23-27,40-44` json-decodes after Filament trims the whole cell, so `[" red","red"]` is stored as two tags.
- Smallest fix: one shared `Value::tagRules()`/`Value::normalizeTags()` used by all three writers; at minimum trim in `SaveCanonicalDefinition` before validating. Re-verify lines after Codex's current tag-workspace changes land.

## S12 — No automated gate: PHP tests and Pint never run on master (confirmed source behavior; unresolved decision)

- `.github/workflows/tests.yml` and `lint.yml` trigger on `develop`/`main`, which do not exist (`git branch -a`: master, codex/*, legacy/*, upgrade/*); `deployment-check.yml` runs on master but not the PHP suite. `master` was added to tests.yml in `4e03216` and removed in `8cdb586` (owner decision; re-adding will likely fail on private composer repos for filacheck-pro/filament/blueprint without credentials). `lint.yml:37` runs `vendor/bin/pint` in fix mode with the auto-commit step commented out, so formatting drift cannot fail CI.
- Smallest fix, if the decision is reopened: `pint --test` (and `permissions: contents: read`), add `master` to both triggers, provide the private-repo credentials or install Pint via `setup-php tools: pint:1.32.1`. Pest's arch plugin is installed (via pest 5.3) but unused; a single guard `arch()->expect('App')->not->toUse('Tests')` passes today and is free.

---

## Controls that are correct and must be preserved

- `Gate::forUser($actor)->authorize('manage-catalog')` in every application Action (except S04); `Gate::authorize('manage-catalog')` on every admin Livewire action and in `ItemLists::definition :48`.
- `#[Locked]` on every non-form public Livewire property (`ItemListDrawer:67-83`, `TagValues:57-78`, `TagValueEditor:22-30`, `AttributeValueAssignment:28-32`, `InteractsWithConfiguratorRuleImport`, `InteractsWithScopedTableSearch::$batchPreview`). `tableSearchScope`/`tableSearchFields` are intentionally public for `wire:model.live` and fail closed.
- Fail-closed scoped search (`InteractsWithScopedTableSearch:212` `whereRaw('1 = 0')`; `ScopedTableSearchTest:25,55`).
- HMAC review/preview tokens in `BatchCatalogChanges::token :339-357`, `ManageValueTags :73`, `StatusActions::reviewToken`, and signed tested-change captures in `ConfiguratorEditorChanges`; `SaveTestedConfiguratorChanges` recomputes the fingerprint under `lockForUpdate` before writing.
- Drawer-editor membership re-check on `hydrate` (`InteractsWithItemDrawerEditor:39-43, :50-70`); `ItemListDrawer::selectRecord :298-305` (`findOrFail` inside the scoped query + `$resource::canEdit`).
- `TableBrowserProfile::restore :27-45` validates and gates restored browser state and drops legacy `tagFilters`.
- Rule export jobs and import rows re-check the Gate (S03 lists the lines); `ConfiguratorFormErrors` passes Authorization/404/HTTP exceptions through (`:27-29`).
- `tests/bootstrap-mysql.php` literal disposable-target checks and `TransferRetainedApplicationData.php:151-172` fail-closed allowlist (verified as deliberate; do not move to env/config).
- `CatalogRevisions` scoped batch coalescing + transaction guard; `CatalogSnapshots` lock/revision double-check; `SaveConfiguratorDefinition` as the single aggregate writer (all 27 call sites).

## Not covered / not run

No runtime, browser, database or concurrency tests were executed; no production or remote system was touched. Not reviewed for security: Fortify/2FA/passkey flows beyond S01, session/cookie config, CSP/headers, file-upload handling in the rule import beyond authorization, mail/notification content, Horizon dashboard exposure beyond the gate. A dedicated Filament security audit (the installed `filament-security-audit` skill) would cover those and is a separate pass.

## Suggested implementation order for Codex

1. S01 + S02 together (one config list, verified-email check, tests).
2. S06 fix A (2 lines) and fix B, with the `DB::listen` order test.
3. S07 fail-closed test guard.
4. S03 policies (needs approval for `app/Policies`), then S08, S09, S04.
5. S10, S05, S11 (after the tag-workspace changes land), S12 (owner decision).

Each change should keep Pint/FilaCheck green and add the named test. None of the items above changes enum backing values, snapshot hashes, `tableFilters` state paths, signed payloads or the lock order of existing Group writers.
