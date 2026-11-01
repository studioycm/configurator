# Code-Grounded Execution Specification with Verification Checkpoints
## Integrate the existing Groups One and Two repairs into current Master

**Status: specification only; execution is not authorized.** Prepared 29 October 2026 from current files. No application code, tests, databases, worktrees, merges or pushes were changed/run for this specification.

> For the implementing agent: read this specification, the adjacent integration manifest, the reviewed patch, current `AGENTS.md`, and the twelve skill sources recorded in the manifest. Execute only after separate human integration/verification authority. Use the existing main checkout; do not create a worktree, rewrite the repairs, integrate Writing, or treat plan approval as execution approval. Follow the checkpoints in order. A failed checkpoint stops dependent work.

**Goal:** put the exact reviewed shared-inclusion defaults and selective appearance recovery into the main checkout, prove their behavior with current Master’s actual consumers, and record whether the result is merely an uncommitted main-checkout change or a committed Master revision.

**Architecture:** retain the existing aggregate writer and its locks, compiler, ownership checks and revision behavior. Reuse the stateless inclusion-draft service for local defaults. Recover invalid stored appearance at the scoped read-service boundary; keep strict validation and explicit persistence in their existing writer. Add the existing panel render hook and presentation-only notice without changing editor state or browser protocols.

**Artifacts:**

- `GROUPS_ONE_TWO_CURRENT_MASTER_INTEGRATION_MANIFEST.json`: exact thirteen paths, before/after hashes, source/skill versions and preservation inventory.
- `GROUPS_ONE_TWO_CURRENT_MASTER_REVIEWED.patch`: full reproducible text diff of those thirteen reviewed files, **not applied**. SHA256 `3f5dccc40a8daa43de66a892fae3ad797de3f15ab8a0eb9cad4cc34ada6f9254`.
- Original reviewed source: `/Users/studioycm/.codex/worktrees/app-reconciliation/configurator/.superpowers/sdd/APP_RECONCILIATION_AND_ROBUSTNESS_EXECUTION_SPEC/ROOT13_PROFILE_R2_FROZEN_20261029/source/`.
- Original receipt SHA256: `3c60bf504f11abcee80e30a13214462802185450a731d9b47a0bae986a2e80b9`.

The patch is documentation/preparation, not a new implementation. Every resulting selected file must match its manifest’s reviewed hash. No adaptation is currently justified by intervening application changes. If one becomes necessary, stop, show the precise difference and review a versioned amendment before applying it.

## 1. Actual baseline and authority

| Item | Fresh source evidence |
|---|---|
| Destination | `/Users/studioycm/Herd/configurator`, branch `master`, HEAD `8e1f640219ec8dbd2ee4c739041907aba4c4224d`. |
| Repair checkout | `/Users/studioycm/.codex/worktrees/app-reconciliation/configurator`, detached at that same HEAD; nine modified and four new selected files. All thirteen current files match the reviewed receipt. |
| Intervening application changes | No intervening commits between this repair base and current Master. All **752** checked tracked application/configuration/test/public/package files in main match that commit. This is source evidence, not a fresh runtime test. |
| Main’s concurrent work | **31 pending entries** captured before these three specification artifacts: planning/evidence documents, `AGENTS.md`, `CLAUDE.md`, `boost.json`, `.junie/mcp/mcp.json`, and six architecture/research skill links. Preserve them individually; they are not part of this integration. Do not read or publish MCP credentials. Hashes/link-target hashes are in the manifest. |
| Current project rules | `.ai/rules` is absent in main and the repair checkout. Therefore there is no index/glob/keyword rule pass to run against that directory. Recheck before execution; read any rules introduced meanwhile. |
| Current AGENTS amendment | Main now includes “Plain-language project status reporting.” Preserve it. Report what changed, where, actual test results, whether on Master, and the exact remaining approval/action. The manifest pins the captured AGENTS hash. |
| Earlier changes already in Master | `66aaaab` guards catalog availability and exact zero labels; `03ab3e4` preserves canonical CSV text/export publication; merge `5788905`, then documentation commits `fed1d5e` and `8e1f640`. They are the destination baseline, not changes to reapply. |
| Implementation order | **Groups One–Two first**, after approval; verify their actual resulting Master. Writing may finish its separately authorized isolated work, but its **integration waits**. Catalog/Images then refresh affected plans against verified Master. |

### Installed versions, inspected without booting the application

`composer show --direct --format=json` and installed lock/source inspection establish PHP **8.4.27** (project constraint `^8.4`), Laravel **13.35.0**, Filament **5.9.0**, Livewire **4.4.7**, Pest **5.3.0**, Pest Laravel plugin **5.0.1**, Pint **1.32.1**, FilaCheck Pro **1.2.7**. The JS lock records Vite **8.3.3** and Tailwind / its Vite plugin **4.3.3**. Do not use examples requiring another installed major or PHP 8.5 syntax.

Boost `application-info` / `search-docs` tools were not exposed in this session. Fallback: installed package metadata/source, plus official [Laravel 13 container documentation](https://laravel.com/framework/docs/13.x/container), [database transactions](https://laravel.com/framework/docs/13.x/database#database-transactions), [Filament 5 render hooks](https://filamentphp.com/docs/5.x/advanced/render-hooks), and [Livewire 4 properties](https://livewire.laravel.com/docs/4.x/properties). No API audit or forms UX audit was used. No generator, formatter, build, test or application Artisan command was run for this specification.

### Source-reference notation

`M:` means the main checkout at the captured baseline; `R:` means the reviewed repair source/current matching repair checkout. Paths and lines below were inspected, not inferred from filenames. New files have no Master line reference. Future changes invalidate line references; re-resolve them before execution.

## 2. What is being fixed, and what is deliberately retained

| ID | Established behavior/problem | Selected outcome |
|---|---|---|
| G1 | Ordinary new local Values and batch draft inclusion independently encode the same six defaults (`M:SaveConfiguratorOption.php:40`; `M:ConfiguratorInclusionDrafts.php:12`). They match today; this is a shared-rule maintenance repair, **not evidence of a current differing-default bug**. | One stateless `optionLocalDefaults(): array`, used by both paths. Explicit supplied values retain precedence. Existing local IDs/order/controls and separately selected Default remain untouched. |
| G2 | `M:AdminAppearance::current():71–75` combines stored values with defaults then strictly validates on reads. A stored invalid field can throw while header/sidebar/modal/Product consumers resolve appearance, including the repair page. Source and historical isolated cases establish the failure path; **no live corrupt setting or current outage was found**. | Recover only invalid recognized fields in memory; validate dependent logo/title fit; preserve unrelated valid values; warn; never persist fallback implicitly. Strict new Save remains strict. |
| G2 visibility | `recovery()` deliberately performs no loading. An earlier hook that only read metadata could miss the notice on a cold page. | The proven hook explicitly calls `current()` before `recovery()`. Keep this order. |
| Verification | Earlier test setup used random spare Codes that could collide with canonical fixture Codes; JSON representation/key order and SQL-quote assumptions also differed by engine. | Reuse the reviewed deterministic `X0/X1/X2` fixture and the reviewed raw-JSON/key-order/database-grammar assertions. Do not weaken the protected assertions or rewrite factories. |
| CLI investigation | Real console rendering and independent-process evidence supported existing snapshot-command behavior, including committed invalidation surviving a later controlled cache failure. | Add characterization tests only. **No production snapshot-command patch** is selected. |

A missing appearance singleton, unavailable database/cache, or unrelated exception remains an operational error; do not catch every exception and pretend it was an invalid preference. Missing legacy keys backfill quietly; unknown stored keys are ignored and reported by count without logging their names/values. An unusable non-map payload has no valid individual preferences to preserve, so defaults are used only in that case.

### Required contracts

- Six local defaults: `label_override=null`, `subtitle_mode='none'`, `display_value_override=null`, `hint=null`, `hidden_by_default=false`, `disabled_by_default=false`.
- Local inclusion identity remains distinct from canonical `Option` identity. Creation uses a new draft UUID; append order follows the existing display-order axis. Do not reset the selected Default, other Values, other Attributes, unrelated Configurators, or retained editor drafts.
- Keep explicit None/Master/custom intent and exact custom text, including **`"0"`** and significant spaces. Existing subtitle normalization runs first; defaults are spread before explicit data.
- Appearance ranges/enums/boolean validation stay centralized. Header fit remains `logo_height <= header_height - 12` and `ceil(page_title_size * 1.2) <= header_height - 12`.
- Example: stored `sidebar_width=999`, `control_height=34` resolves to `216` and `34`; saved raw JSON/version do not change. Header `40`, logo `32`, title `30` resolves logo `28`, title `20`, preserving header `40` and unrelated fields.
- Valid coercible old values retain existing normalization (`"288"` → `288`, boolean `"0"` → `false`). Recovery is for reads; `validate()`/Save still reject invalid new settings and unknown input keys.
- Keep request-scoped memoization, raw shared-cache storage, transaction-aware cache bypass and after-commit invalidation. Do not cache a repaired map over the stored raw map or publish uncommitted/rolled-back values.
- The notice is escaped/localized, `role="status"`, `aria-live="polite"`; the repair link requires `manage-catalog`. The actual page/writer still authorize server-side.

## 3. Applied skills and bounded conclusions

All twelve requested SKILL.md files were read. Their exact paths/resolved paths/SHA256 values are recorded in the manifest. The main checkout’s versions were used consistently; the different personal performance-skill copy was not substituted. Relevant architecture, performance, Laravel and testing reference files were also consulted.

| Skill | Applied to this package; bounded conclusion |
|---|---|
| `laravel-development-architecture` | Traced UI adapters → shared drafts → existing aggregate writer; scoped read recovery → existing explicit writer. Retain current Actions/Services and DI. No repository/interface/DTO/provider abstraction is justified. |
| `filament-performance-audit` | Checked global PAGE_START hook, existing appearance reads and modal/sidebar callbacks. New hook adds bounded in-memory validation/metadata, no row loop or polling. Repeated nontransactional reads reuse the scoped service. No measured latency/query improvement is claimed; no index/query-shape change proposed. |
| `laraveldaily-eloquent-audit` | Checked existing owner-scoped lookups, canonical loading, array/integer casts, lock/transaction boundaries and write preservation. Singleton primary-key read is not N+1. Do not bulk-update around the aggregate writer or change eager loads/casts. |
| `laraveldaily-permissions-audit` | Checked `manage-catalog` at writer, page, repeated Livewire actions and notice link. UI hiding is not the write boundary. No role/permission redesign, tenant framework or separate hardening task is included. |
| `laraveldaily-queues-audit` | **Queue domain unchanged:** no selected Job, listener dispatch, exporter, worker/configuration or queued payload changes. Existing appearance writer’s after-commit cache effect remains; it is not a queued job. No queue feature or broad queue audit is justified. |
| `laraveldaily-structure-audit` | Checked responsibilities, container lifetime, provider extension point, public method consumers and source/target maps. Valid static UI factories and existing large writers are preserved. |
| `laraveldaily-testing-audit` | Mapped each behavior to existing tests and explicit remaining browser gaps. Retain negative/authorization/rollback/identity cases; do not equate status/toast with persistence or broad coverage totals with runtime proof. |
| `technical-debt-manager-php-laravel` | Prioritize the availability/recovery repair and shared-rule drift prevention; preserve existing correctness work. No dependency update, security scan, CI redesign, quarterly roadmap or speculative performance project. Those domains are unaffected/deferred. |
| Official `filament-development` | Main’s skill is **byte-identical** to `vendor/filament/filament/resources/boost/skills/filament-development/SKILL.md` (SHA `229854cd2d92d07c80b82ee8bab87ddafd82e741245de7b1368a6d10edd250f2`). Use native panel `renderHook`, existing Page URL and section/link components; no custom Livewire notice component. |
| `laravel-best-practices` | Retain concrete injection, centralized validation, scoped cache/lifetime, explicit writer authorization/transactions, localized escaped output, and existing configuration. No dependency/schema change. |
| `livewire-development` | Preserve server-owned editor state, action authorization, hydration/stash/recollection behavior and existing Alpine registration/events. Notice has no `wire:*`, editor binding or browser state. Verify coexistence through native UI. |
| `testing-best-practices` | Reuse real operation/component tests, deterministic minimal fixtures, explicit outcomes and isolated actual-engine runs for engine claims. Add no redundant implementation-mirroring tests or new test dependency. Existing project fixture/hook conventions remain. |

The request’s source-only boundary overrides skill examples that run tests, formatters, generators, dependency installs/audits or broad repository scans. They are proposed below only where relevant after approval. Schema reasoning used existing migrations/models; no live database inspection or index recommendation was needed.

## 4. Affected consumers, unchanged responsibilities and other work

| Consumer / owner | Current source / overlap | Preservation requirement |
|---|---|---|
| Ordinary Value editor | `M:OptionsRelationManager::saveOption():180`, `AttributesRelationManager::saveOptionEditor():219`; call `SaveConfiguratorOption::handle`. | Constructor gains a concrete stateless collaborator; existing callers resolve through container. No manual `new SaveConfiguratorOption` call was found. Keep FIELDS, ownership and normalization. |
| Batch Add Values / Add Attributes | `M:AddConfiguratorValues::handle():24`, append at112; `ConfiguratorInclusionDrafts::attribute():18` loads eligible canonical Options and maps `option()`. `AddConfiguratorAttributes` consumes that service. | Do not change batch payloads, locks, canonical creation, ordering, active/hidden checks, or explicit chosen Default. |
| Authoritative definition writer | `M:SaveConfiguratorDefinition::change():156–168`: authorize → transaction → owner lock → context lock → fresh graph/canonical locks → callback → existing writer. | Unchanged file. No direct local-row persistence or second compiler path. Current Master’s writer is the integration target; do not copy Writing’s separate writer changes. |
| Separately saved Default | `M:OptionsRelationManager::setDefault():133–155`; Attribute settings/editor comparison intentionally omit separately saved Default. `M:AttributesRelationManager::reconcileOptionBatch():470` preserves dirty state. | “Local creation defaults” are not the selected Default. Preserve current saved Default through old drafts and inclusion refreshes. |
| Appearance persistence | `M:SaveAdminAppearanceSettings::handle():17–35`: Gate → strict validate → singleton lock → changed map/version → after-commit cache forget → `rememberSaved()`. `AdminAppearanceSettings::casts():18`. | Unchanged writer/model/schema. No automatic repair, migration, version bump, cache flush or transaction rewrite during integration. |
| Appearance page | `M:App\Filament\Pages\AdminAppearance::mount():50`, `save():101`, reset/personal actions77–99. | Existing explicit Save persists; reset only stages; personal settings remain browser-local. No page redesign or new live preview. |
| Admin shell / dialogs | `M:AdminPanelProvider::panel():49–51,68–70`; `AppServiceProvider::register():55–57`, `boot():98–120`; appearance-variables and workspace-assets views. | Preserve styles/body hooks, resource/page registration and browser profile behavior. Keep `AdminAppearance` scoped; no singleton actor/state leakage. |
| Product/configurator runtime | `M:App\Livewire\Catalog\ProductConfigurator.php:85` reads loading-indicator setting. | Safe read behavior reaches this consumer; its engine, filters and Product rendering are unchanged. No admin notice is added to public/catalog layout. |
| Preview UX | Current Preview code already in Master; V20/V21 Workspace tests and Preview snapshot/save tests exercise its contracts. | Preserve recollection, active/stashed drafts, new Rule identity and snapshot signatures. No Preview source file is copied over. |
| Writing | Original reviewed Writing package contains57 paths; the **only exact path overlap** with these thirteen is `tests/TestDatabaseSafety.php`. Shared writer/loader/admin shell consumers also matter without same-file edits. Writing’s isolated selection/HTTP-test corrections are **not defects in current Master**. Its latest independent audit report closes those three corrections against a new recorded source snapshot (`W7_FROZEN_20261029_v2`, receipt `b80ad6c2…`); final combined/native-browser acceptance remains separate. Those reported results do not authorize its integration or block this independent Groups One–Two specification. | This package includes only the reviewed test-profile support, not Writing application/JS/routes/theme/resource code. Writing integration waits until Groups One–Two are verified. Later composition must preserve the strict guard union and PAGE_START hook. |
| Catalog / Images | Held `catalog-k1` checkout is based on `8e1f640`; older catalog-rebuild reference is at `62e7bb42`. Retained Images source is at `244b305`, with92 protected paths; its direct selected-path overlap is `AdminPanelProvider.php`. | Keep all reference folders/sites/media/data. Later page/provider additions must preserve the notice and existing registration. Catalog presentation settings remain a separate domain. |
| Other active work | Audit and planning owners have main documents/guidance in progress. Older consolidation tree has independent history; it is not this package’s source. | Acquire exclusive main-checkout write window before execution; do not stage, clean, reset, stash, merge or copy whole checkouts. Preserve all31 captured entries and later acknowledged owner changes. |

### Intervening-change policy

At execution, compare current HEAD and dirty source against the manifest again. A changed HEAD does not justify blindly rejecting good work or overwriting it: inspect its exact relevant diff and produce a reviewed compatibility amendment. New application/consumer changes, package-major changes, selected-path drift or another main writer are **stop conditions**. Concurrent planning/guidance changes must be acknowledged and preserved; they are never silently rolled back to this document’s historical hash.

## 5. Historical verification and remaining proof

These results were inspected as existing receipts/log evidence. **No tests were rerun for this specification.**

| Evidence | What it establishes | What it does not establish |
|---|---|---|
| Reviewed Root13 R2 source and portability review | Exact production bytes, meaningful tests, deterministic fixture correction and engine-portable raw JSON/SQL assertions. | A fresh run on current Master after integration. Original source receipt’s earlier “review pending” field is historical; later review/acceptance receipt closes that bounded review. |
| `combined-w5-reconciliation-r2-acceptance-receipt.json` | Root13 + Writing W5 composition: SQLite1723/10328, selected-profile150/939, recorded guard/suite exits0. | Root13-alone Master acceptance. Explicit SQLite aliases still have SQLite provenance. Seven retained-transfer MySQL cases were excluded, not passed. |
| `combined-w6-acceptance-receipt.json` | Later Root13 + W6 composition:316 Node,1723 PHP/10599, exact45-path source composition. | Writing UI, MySQL/W6-specific browser proof, or integrated Master. Do not use its totals as expected totals for this smaller package. |
| `cli-physical-commit-proof/acceptance.json` | Four separate processes prove subject revision1→2 persists after controlled real-kernel failure; unrelated control stays1. | Every infrastructure failure, cross-process cache locking, or proof on future resulting Master. |
| Audit B1–B5 / native CSV and G2 browser artifacts | Bounded current-feature readbacks on **Root13 + W5 QA code**, including explicit appearance repair and preserving valid controls. | The source identity of future Master, Writing UI or universal regression absence. |
| XLSX partial-file evidence | Workbook generation, received bytes and parse are valid. | Successful completed native browser save. That independent outstanding export observation is not a demonstrated G1/G2 regression and is not silently repaired here. |

### Three known nonblocking coverage gaps, now assigned finite verification

1. **Same inclusion chain:** Add Value → graph refresh → save older active/stashed Attribute/Value drafts, while preserving the newly added local ID/order **and** a newer separately saved Default. Existing action parity, different-Attribute mounted retention, and V20 preservation cases cover parts separately. They do not prove the whole chain.
2. **Add Attributes precision:** the existing “exact local defaults” case checks atomicity, Master reuse and default ownership, but not all six fields plus a two-value, non-first chosen Default through loader readback.
3. **Notice with unsaved Preview:** the notice’s cold ordinary page test and earlier Preview browser checks occurred separately; coexistence with unsaved Preview has not been browser-proven.

Do not relabel these as reproduced bugs or claim old tests cover them. S6 closes these through finite native workflows and saved-data readback on exact resulting-Master code. No new production change or redundant automated matrix is preauthorized by a gap. If a workflow fails, preserve its evidence and reconsider the smallest affected change.

## 6. Execution sequence and checkpoints — all commands below are NOT RUN

### S0 — Obtain authority, revalidate source and preserve concurrent work

**Files/methods:** all thirteen manifest entries; current main `AGENTS.md`; unchanged consumers in §4. **Problem:** a tested isolated package must not overwrite a progressed or dirty destination. **Behavior/architecture:** transfer exact reviewed changes, not a new implementation or whole old branch.

- [ ] Obtain explicit authority for applying this exact package and its stated isolated verification. Commit authority is separate unless explicitly included. No push, merge, deployment or security action follows.
- [ ] Acquire one main-checkout writer window. Other tasks may continue their isolated/read-only work; they must not write main during this window. Writing integration remains queued afterward.
- [ ] Run read-only `git status --short --untracked-files=all`, `git rev-parse HEAD`, `git branch --show-current`, `git log --oneline 8e1f640..HEAD`; recheck current rules/versions and relevant consumers.
- [ ] Match the manifest’s nine existing selected before-hashes and four absent paths; verify reviewed receipt/patch and all thirteen after-hashes. Recheck the protected92 Images/53 prototype paths and their HEADs using the existing reconciliation evidence.
- [ ] Capture private preimages/existence/file modes for selected paths, the current index/status, and hashes/link-targets of all unrelated pending work. Do not include secrets in reports. Do not stash, clean or commit everything to make main look clean.
- [ ] From main, run `git apply --check docs/catalog/GROUPS_ONE_TWO_CURRENT_MASTER_REVIEWED.patch` only after the above gates and execution authority. This checks the proposed transfer; it does not accept runtime behavior.

**Checkpoint C0:** exact source/versions/ownership/preservation verified; patch check clean; no selected-path conflict or new behavior decision. **Stop:** another writer, unexpected source/HEAD/lock/API change, missing source artifacts, any existing supposedly-new path, or unsafe test environment. Do not force application, resolve by dropping newer work, or start the other feature integrations.

### Shared test-launch environment for S1–S5

Keep main `.env` and all existing QA/feature environments unchanged. Tests must not inherit a DB URL, path override, read/write connection or production credentials. Use fresh process environment and the existing pre-bootstrap/resolved/late-alias guards. The following ephemeral shell function launches **existing tests**, not a new application verification framework; it creates no source file:

```bash
g12_check() {
  python3 - "$@" <<'PYTEST'
import base64, json, os, pathlib, secrets, subprocess, sys
mode, command = sys.argv[1], sys.argv[2:]
assert mode in ('sqlite', 'mysql') and command
isolated_env = {k: v for k, v in os.environ.items()
                if k in ('PATH', 'HOME', 'USER', 'LOGNAME', 'TMPDIR', 'LANG', 'LC_ALL', 'SHELL')}
isolated_env.update(APP_ENV='testing', APP_DEBUG='false',
    APP_KEY='base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
    DB_CONNECTION='sqlite', DB_DATABASE=':memory:', DB_URL='', DATABASE_URL='', DB_SOCKET='',
    CACHE_STORE='array', SESSION_DRIVER='array', QUEUE_CONNECTION='sync', MAIL_MAILER='array',
    BROADCAST_CONNECTION='null', BCRYPT_ROUNDS='4', HORIZON_ENABLED='false',
    PULSE_ENABLED='false', TELESCOPE_ENABLED='false', NIGHTWATCH_ENABLED='false')
if mode == 'mysql':
    profile_path = pathlib.Path('/private/tmp/configurator-reconciliation-private-20261029/configurator_reconciliation_test.json')
    assert profile_path.is_file() and not profile_path.is_symlink()
    assert profile_path.stat().st_mode & 0o777 == 0o600
    profile = json.loads(profile_path.read_text())
    expected = {'DB_CONNECTION':'mysql', 'DB_HOST':'127.0.0.1', 'DB_PORT':'3307',
        'DB_DATABASE':'configurator_reconciliation_test', 'DB_USERNAME':'configurator_reconciliation_test'}
    assert all(str(profile[k]) == v for k, v in expected.items())
    assert isinstance(profile.get('DB_PASSWORD'), str) and profile['DB_PASSWORD']
    isolated_env.update(expected, DB_PASSWORD=profile['DB_PASSWORD'], CATALOG_MYSQL_TEST_PROFILE='reconciliation')
check = "require getcwd().'/tests/TestDatabaseSafety.php'; Tests\\TestDatabaseSafety::assertBeforeBootstrap(" + ('true' if mode == 'mysql' else 'false') + ");"
subprocess.run(['php', '-d', 'zend.exception_ignore_args=1', '-r', check], env=isolated_env, check=True)
raise SystemExit(subprocess.run(command, env=isolated_env).returncode)
PYTEST
}
```

Run from `/Users/studioycm/Herd/configurator`. Do not trace/export password values to tool output or put them in command arguments. If the reviewed private profile is unavailable, stop the MySQL step; do not reset another account, ask for a pasted password, borrow an environment, or invent a replacement target. Persist command/exit/source/asset identities and sanitized logs privately when executing. Refusal is not a test pass.

**MySQL prerequisite:** coordinator grants an exclusive test window; selected engine is existing MySQL8.0.46 at127.0.0.1:3307; credentials are restricted to the dedicated destructive test schema. Verify effective identity/grants through the existing reviewed private mechanism before tests. Never run migrations/refresh/fixtures against main, Rules, retained Catalog/Images or persistent browser QA. SQLite-memory success is not MySQL lock/commit evidence.

**Per-step transfer rule:** before each S1–S5 apply command, run the same command with `--check` added and compare that step’s before-images. After applying, compare its selected after-hashes. Never retry a partially applied step blindly or apply the whole patch again after earlier sections have been applied.

### S1 — Carry the reviewed test-profile fence before running integration checks

**Exact files:** `tests/TestDatabaseSafety.php` (`Tests\TestDatabaseSafety::mysqlTarget`, `assertEnvironment`, `assertConnection`); new `tests/Unit/ReconciliationTestDatabaseSafetyTest.php`; new `phpunit.reconciliation-mysql.xml`. `M:TestDatabaseSafety.php:16` has the original catalog target; `R:19–45,70–75,106` adds strict named selection. Existing `tests/bootstrap.php`, `bootstrap-mysql.php`, `CreatesApplication.php` and `TestDatabaseConnectionFactory.php` stay unchanged.

**Problem/intent:** select the existing dedicated reconciliation test target safely. The reviewed test-only package also retains Writing’s strict Rules profile support, without bringing Writing application code into Master. Default selection remains catalog. Conflicting environment sources, unknown profiles, DB URLs/path/socket overrides, wrong effective targets and late QA aliases must refuse before PDO.

**Focused diff shape (full exact bytes are in patch):**

```php
private const MYSQL_PROFILES = [
    'catalog' => self::MYSQL,
    'rules' => [...self::MYSQL, 'database' => 'configurator_rules_test', 'username' => 'configurator_rules_test'],
    'reconciliation' => [...self::MYSQL, 'database' => 'configurator_reconciliation_test', 'username' => 'configurator_reconciliation_test'],
];
// assertEnvironment() and assertConnection() use the same selected exact tuple.
```

- [ ] Apply only these three patch sections, with `git apply --include=tests/TestDatabaseSafety.php --include=tests/Unit/ReconciliationTestDatabaseSafetyTest.php --include=phpunit.reconciliation-mysql.xml docs/catalog/GROUPS_ONE_TWO_CURRENT_MASTER_REVIEWED.patch`.
- [ ] Run `g12_check sqlite php artisan test --compact tests/Unit/TestDatabaseSafetyTest.php tests/Unit/ReconciliationTestDatabaseSafetyTest.php`.

**Assertions/expected:** all existing catalog refusals remain; exact reconciliation tuple accepted; conflicting `$_ENV`/`$_SERVER`/process values rejected; catalog/Rules remain exact; every browser QA tuple excluded; late aliases refused before PDO. These are safety-contract tests, not ordinary DB-connect tests. The XML explicitly lists15 files and excludes seven retained-transfer MySQL cases whose production action intentionally pins the original catalog target.

**Checkpoint C1:** tests exit0, complete named cases pass, three hashes match. **Stop:** any widened allowlist/QA admission, changed default profile, missing guard, or proposed change to the retained-transfer production boundary. Do not open a MySQL test window merely to test refusal paths that need no PDO.

### S2 — Reuse the shared local inclusion defaults

**Exact files/methods:** `app/Services/ConfiguratorInclusionDrafts.php` (`M:option():12`, `R:optionLocalDefaults():12`, `option():18`); `app/Actions/SaveConfiguratorOption.php` (`M:__construct():15`, new branch40; `R:__construct():16`, new branch40–42); `tests/Feature/ConfiguratorCreationFlowTest.php` (`R:61–109`, existing327/447 onward).

**Problem/intent/justification:** consolidate the identical ordinary/batch six-field defaults in the existing stateless service. This clarifies shared responsibility without changing current default values, adding a new service/interface, touching the aggregate writer or making creation choose a Default.

```php
// Before: identical six-field literals in ordinary and batch paths.
public function optionLocalDefaults(): array
{
    return ['label_override' => null, 'subtitle_mode' => 'none',
        'display_value_override' => null, 'hint' => null,
        'hidden_by_default' => false, 'disabled_by_default' => false];
}
// option(): preserve its new UUID and canonical option_id, then spread defaults.
// SaveConfiguratorOption: inject ConfiguratorInclusionDrafts after the existing writer.
// New option: UUID + shared defaults + computed display_order, THEN ...$data.
// Existing option edit branch and additionalParents remain byte-for-byte unchanged.
```

- [ ] Apply `git apply --include=app/Services/ConfiguratorInclusionDrafts.php --include=app/Actions/SaveConfiguratorOption.php --include=tests/Feature/ConfiguratorCreationFlowTest.php docs/catalog/GROUPS_ONE_TWO_CURRENT_MASTER_REVIEWED.patch`. Do not change `AddConfiguratorValues`, `AddConfiguratorAttributes`, the compiler, loader or shared writer.
- [ ] Run `g12_check sqlite php artisan test --compact tests/Feature/ConfiguratorCreationFlowTest.php --filter='new local Values share defaults|ordinary new local Values retain explicit subtitle intent'`.
- [ ] Run `g12_check sqlite php artisan test --compact tests/Feature/ConfiguratorCreationFlowTest.php tests/Feature/ConfiguratorSubtitleTest.php`.

**Assertions/expected:** ordinary/batch persist all six literal values; new local identity and correct owner; display order2 in the tested two-value fixture; old local controls/options/other Attributes/other Configurator exactly unchanged; non-first selected Default retained. Explicit None/Master/custom including `"0"` and spaces override defaults after existing normalization. Existing invalid/foreign/revoked/late-conflict cases still reject atomically. Preserve SQL-marker assertions: grammar-aware matching plus required observed event/order markers, not a listener that never matches.

**Dependencies/risks:** C1; existing concrete container construction; fresh graph/parent locks remain owned by `SaveConfiguratorDefinition`. A constructor call made manually by future code needs the collaborator; do not introduce a binding solely for a resolvable concrete service.

**Checkpoint C2:** affected tests exit0; new six-field cases and existing negative/rollback cases pass; selected three hashes match; no writer/loader/data-schema changes. **Stop:** any lost explicit override, selected Default/ID/order/draft change, unexpected query/lock change or need to modify another writer.

### S3 — Reuse selective appearance read recovery

**Exact files/methods:** `app/Services/AdminAppearance.php`: `M:validate():33`, `current():60`, `rememberSaved():79`; `R:fieldRules():58`, `recoverStored():74`, `recovery():108`, `current():114`, `rememberSaved():135`. `tests/Feature/Filament/AdminAppearanceTest.php`: `R:28–169` new recovery cases, existing persistence/cache/lifecycle cases171–379. Existing writer/page/model in §4 are dependencies, not edits.

**Problem/intent/justification:** invalid stored presentation preferences must not break otherwise usable content. Recovery belongs beside the service’s existing defaults/ranges/validation and scoped memo, while explicit persistence remains in the existing authorized locked writer.

**Focused before/after in `current()` (other hunks are in the reviewed patch):**

```diff
-$read = fn (): array => AdminAppearanceSettings::findOrFail(1)->settings;
+$read = fn (): mixed => AdminAppearanceSettings::findOrFail(1)->settings;
 $settings = $transactional ? $read() : Cache::remember(self::CACHE_KEY, 3600, $read);
-$settings = $this->validate(array_replace($this->defaults(), $settings));
+$settings = $this->recoverStored($settings);
```

**Proven algorithm to reuse:**

1. Extract `fieldRules()` from current validation; keep strict full-map validation and fit checks in `validate()`.
2. Begin with defaults. For a stored array, count unknown keys and merge only recognized keys over defaults.
3. Validate field rules; replace only failed recognized fields with their own defaults.
4. Check logo/title fit against resolved header; default the dependent logo/title only when that fit fails. Validate the final map strictly.
5. Retain a report of known defaulted fields/unknown count/unusable payload and log that sanitized report once per scoped instance. Never log raw stored keys/values.
6. `current()` still bypasses shared cache/memo in transactions, reads raw stored settings outside them and memoizes only its resolved result per lifecycle. `rememberSaved()` clears recovery metadata and avoids publishing uncommitted values.

- [ ] Apply `git apply --include=app/Services/AdminAppearance.php --include=tests/Feature/Filament/AdminAppearanceTest.php docs/catalog/GROUPS_ONE_TWO_CURRENT_MASTER_REVIEWED.patch`.
- [ ] Run `g12_check sqlite php artisan test --compact tests/Feature/Filament/AdminAppearanceTest.php --filter='invalid stored appearance|stored appearance recovery|unusable stored appearance|legacy omissions|appearance recovery does not hide|appearance recovery follows'`.

**Assertions/expected:** sidebar999→216 with valid control34 retained; invalid integer/enum/boolean and linked logo/title recovery; unknown payload content excluded from logs; old omissions/coercible values accepted quietly; raw persisted representation/version unchanged on read; transaction/rollback recovery state remains correct; missing singleton/cache errors remain errors. Final full page/hook tests wait for S4; do not call their absence a service failure at this intermediate stage.

**Dependencies/risks:** C2; unchanged `AdminAppearanceSettings` array/integer casts and existing migration singleton; existing scoped binding in AppServiceProvider. Recovery does not repair malformed storage or commit data. No new schema, defaults/ranges, settings scope, distributed cache scheme or lifetime is selected.

**Checkpoint C3:** targeted cases exit0 and both exact hashes match. **Stop:** all-settings fallback for one bad independent value, changed unrelated values, any implicit UPDATE/version bump, suppressed operational errors, or transactional/cache regression. Do not add a broad catch or save repaired values automatically.

### S4 — Reuse the panel notice and permission-controlled repair route

**Exact files:** `app/Providers/Filament/AdminPanelProvider.php` (`M:panel():68–70`, `R:71–77`); new `resources/views/filament/resources/appearance-recovery-notice.blade.php:1–12`; `lang/en/admin.php` (`M:59–63`, `R:62–65`). `Filament\Panel\Concerns\HasRenderHooks::renderHook():18–30` is the installed native extension point.

```php
->renderHook(PanelsRenderHook::PAGE_START, function () {
    $appearance = app(AdminAppearance::class);
    $appearance->current();
    return view('filament.resources.appearance-recovery-notice', [
        'recovery' => $appearance->recovery(),
    ]);
})
```

**Notice contract:** render only for a non-null recovery report; escaped translated generic message in `role="status" aria-live="polite"`; repair link uses `App\Filament\Pages\AdminAppearance::getUrl()` inside `@can('manage-catalog')`. The actual page/writer retain their existing Gate calls. There is no new route, mutation, polling, editor event or public Livewire state.

- [ ] Apply `git apply --include=app/Providers/Filament/AdminPanelProvider.php --include=resources/views/filament/resources/appearance-recovery-notice.blade.php --include=lang/en/admin.php docs/catalog/GROUPS_ONE_TWO_CURRENT_MASTER_REVIEWED.patch`. Preserve STYLES_AFTER, BODY_END, panel middleware, resource/page lists and all existing hooks.
- [ ] Run `g12_check sqlite php artisan test --compact tests/Feature/Filament/AdminAppearanceTest.php`.
- [ ] Run `g12_check sqlite php artisan test --compact tests/Feature/LocalAdminProfileTest.php tests/Feature/Catalog/ConfiguratorIntegrationTest.php tests/Feature/UiWordingTest.php`.

**Assertions/expected:** cold ordinary page shows notice and permitted link; rendered notice for a reader omits the link (this partial test is not proof that an otherwise forbidden reader can enter the admin panel); `recovery()` itself adds zero SQL; explicit Save repairs validated settings/version once and clears warning; strict invalid new Save still fails; reset/personal changes do not persist shared values; Product loading-indicator behavior remains. Never rely on some earlier style/brand callback to initialize the report.

**Dependencies/risks:** C3 and unchanged `manage-catalog` gate/page registration; global panel reach includes Preview and future Writing/Catalog/Images admin pages. No provider overwrite from an older feature tree is acceptable. The shared gate derives from existing panel admission; no permissions rework is selected.

**Checkpoint C4:** named tests exit0, hashes match, no unauthorized link/write path or missing cold notice. **Stop:** duplicate hooks, cold page order dependence, UI state/event coupling, unexpected per-row queries or replacement of existing provider registration.

### S5 — Transfer the proven characterization/fixture support; verify resulting source together

**Exact files:** new `tests/Feature/Catalog/CatalogSnapshotCommandTest.php` (`snapshotTerminal():15`, cases24–106); `tests/Feature/Filament/ConfiguratorWorkspaceTest.php` (`M:V20:601`; `R:601–607` deterministic spare Options). Production `M:App\Console\Commands\CatalogSnapshotCommand::handle():17–46` is unchanged.

**Problem/intent:** remove accidental fixture-code collision while preserving every original V20 assertion. Characterize the actual console kernel’s exception renderer; do not rewrite a command because a direct `handle()` test bypassed its real renderer.

```php
// The only Workspace test-data correction:
Option::factory()->count(3)->sequence(
    ['code' => 'X0'], ['code' => 'X1'], ['code' => 'X2'],
)->create();
```

- [ ] Apply `git apply --include=tests/Feature/Filament/ConfiguratorWorkspaceTest.php --include=tests/Feature/Catalog/CatalogSnapshotCommandTest.php docs/catalog/GROUPS_ONE_TWO_CURRENT_MASTER_REVIEWED.patch`; the selected thirteen-file source is now complete.
- [ ] Run `g12_check sqlite php artisan test --compact tests/Feature/Catalog/CatalogSnapshotCommandTest.php tests/Feature/Filament/ConfiguratorWorkspaceTest.php`.
- [ ] Run `g12_check sqlite php artisan test --compact tests/Feature/ConfiguratorEditorChangesTest.php tests/Feature/ConfiguratorPreviewSnapshotTest.php`.
- [ ] Compare all thirteen after-hashes and all unrelated before-hashes/status to the manifest/window record. Confirm the changed set has exactly six application and seven verification paths; no Writing/backend, package lock, schema or other feature path.
- [ ] Apply project formatting only after confirming no other dirty PHP paths: `vendor/bin/pint --dirty --format agent`. Review its diff. If it changes reviewed bytes, stop for a versioned formatting amendment rather than silently certifying old hashes.
- [ ] No selected file is under `app/Filament/`; therefore that specific AGENTS FilaCheck trigger is not fired. If an approved amendment touches such a file, run `vendor/bin/filacheck --fix --dirty`, resolve relevant remaining violations and review all auto-edits. Do not auto-fix unrelated feature code.
- [ ] Build exact resulting code: `npm run build`; then `npm test`. Save actual built-asset manifest hashes. Existing Writing-only assets and old QA assets are not interchangeable with this build.
- [ ] After the narrow checks, obtain/confirm full-suite execution authority as part of the approved verification package; run `g12_check sqlite php artisan test --compact` once. The project instruction to request a complete-suite run does not authorize it during this planning request. Record actual totals and exit; do not demand historical combined-Writing totals.
- [ ] In the announced isolated MySQL window, run `g12_check mysql php vendor/bin/pest --compact --configuration=phpunit.reconciliation-mysql.xml`. Use direct Pest so the MySQL bootstrap/guard is installed before application boot. Close the window afterward, even on failure.

**Assertions/expected:** malformed command ID → exit2/no revision change; unavailable/branch admission → exit1 before invalidation; valid leaf → exit0 with structured JSON; expected contention → truthful exit1 and query log disabled; `--invalidate` preserves its committed revision advance despite later rebuild failure; unexpected exception is rendered distinctly from contention. Existing V20/V21 and Preview tests preserve active/stashed edits, independently saved Default, identity, rollback and snapshot/recollection contracts. All selected tests/builds exit0 with source unchanged.

**Engine scope:** seven original-target retained-transfer MySQL cases remain excluded/blocked. Do not widen production target guards to make them green. Cases explicitly constructing SQLite aliases retain SQLite evidence; a MySQL-selected suite is not proof every assertion used MySQL. Physical-commit claims require no ambient wrapper transaction and an independent connection/process readback. Existing four-process CLI evidence is historical proof. To claim that result on actual resulting Master, execute the separately reviewed adaptation below; the old helpers hardcode the old Root13+W5 runtime and occupied evidence directory and **must not be rerun unchanged**. Changing only the shell working directory would still test old code. No production command patch or test-transaction escape is permitted.

#### S5-P — Rebind the existing physical-commit proof to resulting Master

This is a bounded **private verification-setup adaptation**, not another application implementation or a new test framework. Its demonstrated need is the inspected old runtime/path binding and occupied one-shot receipts. Do not create/run it under the present planning authorization.

| Existing inspected source | Proposed separate adaptation |
|---|---|
| `/private/tmp/configurator-cli-commit-proof-20261029.php` | `/private/tmp/configurator-g12-master-cli-proof-20261029.php` |
| `/private/tmp/configurator-cli-commit-proof-run-20261029.py` | `/private/tmp/configurator-g12-master-cli-proof-run-20261029.py` |
| Old hardcoded runtime `/private/tmp/configurator-combined-w5-reconciliation-r2-20261029` | Both helpers bind to `/Users/studioycm/Herd/configurator` and the exact resulting source identity. No environment/base-path override or caller-controlled runtime. |
| Old `cli-physical-commit-proof` evidence and43-file composition | New private root `/private/tmp/configurator-g12-master-verification-20261029`, source manifest `resulting-master-source.json`, fresh `cli-physical-commit/` evidence/fixture paths. Preserve all old helpers/receipts. |

After S5’s source/build identity is fixed, record the full resulting project-source inventory, thirteen reviewed hashes, current HEAD plus any qualified dirty overlay, and this specification/patch identifiers in the new0600 source manifest. Proposed helper signatures retain PHP modes `prepare`, `observe`, `fail-after-invalidate`; the Python runner adds `--preflight` for a refusal-only setup check. Retain the four-process protocol and bounded cache-lock-timeout seam unchanged. Replace the misleading historical `all43_source_hashes_unchanged` result name with `all_resulting_master_source_hashes_unchanged`.

Before execution, a source reviewer must compare both adaptations to those inspected old helpers and verify these exact refusal conditions:

- fixed real main runtime; all manifest source hashes match before/after; no unexpected application-source file; config/path/DB URL/read/write/socket overrides refused;
- only the exact restricted reconciliation tuple/profile, selected physical server/identity and exclusive window; no main/QA/Rules target or ambient application/PDO transaction;
- new evidence root is private0700, manifest/helpers/receipts regular0600 with no symlinks; result/fixture/start-claim paths absent; old evidence untouched;
- claim persisted exclusively **before fixture writes**, checked private receipt I/O, sanitized failures and a `finally` window close; any partial failure blocks automatic replay and records owned-state recovery needs;
- no schema reset, grants, global cache/service changes, test wrapper escape, new failure seam or altered expected outcome. If adapting more than source/evidence binding, explicit preflight/receipt safety and resulting-source labels is needed, stop for review of that concrete difference.

Exact future invocation, after the helper source review and approved exclusive window:

```bash
g12_check mysql python3 /private/tmp/configurator-g12-master-cli-proof-run-20261029.py --preflight
g12_check mysql python3 /private/tmp/configurator-g12-master-cli-proof-run-20261029.py
```

Preflight must exit0 without fixture/data writes; a refusal stops the run. Execution must record four distinct PIDs: owned fixture preparation0 → independent before-observer0 (subject/control1) → real kernel failure1 (`--invalidate`, expected translated failure, query log off, application/PDO transaction level0, old local cache sentinel removed) → independent after-observer0 (subject2, exact control row unchanged). The outer runner exits0 only if all assertions and final source hashes match, otherwise nonzero with no replay. A process-local array cache proves no cross-process cache lock or universal infrastructure behavior. Do not hardcode prior fixture IDs or reuse prior receipts.

**Checkpoint C5:** exact manifest, reviewed diffs, narrow then full requested suites/build, guarded selected-engine results and truthful exclusions; preservation verified. **Stop:** source drift, failed assertion/build, unsafe effective target, missing exclusive window or need to change production CLI/writer/transfer logic. An environment/fixture failure is diagnosed separately from an app regression; do not remove assertions or relabel an unrun case as passed.

### S6 — Prove the finite browser/readback gaps on exact resulting-Master code

**Files/classes:** no additional application edit. Exercise current relation managers, Preview page, appearance page/service and the exact built assets produced in S5. Source must equal resulting Master’s complete application identity, not the older Root13+W5 audit runtime.

**Isolation and order:** obtain authority for the browser verification setup as part of execution. Use an explicitly approved non-Git snapshot of resulting Master with private copied dependencies/assets, its own `.env`, fresh key, unique host cookie, local file sessions, array cache/mail, sync queue and no external side effects. No Git worktree or web-server process is required: Herd serves a separately linked domain. Proposed reserved names are `configurator-g12-master-qa.test`, `/private/tmp/configurator-g12-master-qa-20261029`, and isolated schema/user `configurator_g12_master_qa@127.0.0.1:3307`. Check all names/paths/account-host variants absent before any creation; stop on collision rather than adopt/reset/drop. Use only own-schema grants, no GRANT OPTION and private0600 profiles outside served source. No existing database/site is borrowed, including the audit’s leased QA or Writing’s new QA resources.

Provisioning/migrations/synthetic fixtures are future authorized setup actions, **not executed or authorized by this document**. Use reviewed existing setup mechanisms and models/factories; verify physical/resolved target before schema/fixture operations. Preserve main/retained environments and data. Use `database/factories/UserFactory.php::withoutTwoFactor():51` with the existing allowed synthetic actor identity. Build the representative definition through `tests/ConfiguratorFixtures.php::canonicalDefinitionFixture():52` and `App\Actions\SaveConfiguratorDefinition::handle()` after the QA target is verified; do not bootstrap the destructive test profile against browser QA. Use `Group::factory()` and `Product::factory()->for($group)` from their existing factory classes, retaining Product’s non-null synthetic `legacy_id`. Add an unrelated Group/Product and retain their exact readback controls. Record every owned allocation/partial stage before replay could occur. No real client data import.

| Journey | Native action and readback | Required result / stop condition |
|---|---|---|
| B-G1 | Existing Attribute with two Values and a non-first saved Default; stage local Attribute/Value changes, active and stashed. Add a Value to **that same inclusion**, refresh, select a newer separately saved Default, then save the older Attribute and Value drafts through supported controls. Repeat only the active/stashed variants needed to establish both paths. | New local ID/order survives; all intended draft fields survive; newer saved Default remains; unrelated graph/control rows unchanged. On failure, retain exact state and reconsider the affected boundary; no speculative rewrite. |
| B-G1-Attributes | Add an Attribute with two eligible canonical Values; deliberately choose the second as Default; save/reload. | Read loader/DB to check all six local fields on both Values, canonical/local identities, correct owner/order and the explicit non-first Default. Do not infer six fields from a toast or a case name. |
| B-G2 | In QA only, seed invalid sidebar999 with valid control34; load a cold ordinary page, then the repair page; save288 explicitly and reload. | Before Save: sidebar216/control34, generic warning, authorized link, stored raw map/version unchanged. After Save: exact valid map/control34 retained, one version increment, warning gone. Invalid new Save must remain rejected. |
| B-G2-Preview | With invalid QA appearance and **unsaved Preview/editor changes**, open/use Preview & Test; change a selection and inspect saved/current-edit truth. | Notice coexists with usable content, unsaved fields/drafts persist, selection code updates, inspection alone writes nothing, saved snapshot remains truthful. Do not press a broad Save just to dismiss the notice. |
| B-consumers | Check an existing dialog/personal-profile surface and a Product configurator with the explicit loading indicator setting. Inspect recent browser errors/Livewire responses. | Existing size/profile/indicator behavior works; no duplicate Alpine registration, lost editor state, extra polling or unexpected query fan-out introduced by notice. No claim of exhaustive viewport/IME/performance coverage. |

Readback uses approved read-only database tools or the existing reviewed private readback mechanism. Readback itself must not mutate app records. Store synthetic fixtures, actions, actual source+asset hashes, observations and raw-versus-resolved values privately. Restore only deliberately toggled QA controls when safe; do not overwrite a partner’s changes or touch main data.

**Checkpoint C6:** exact resulting source/assets identified; all finite journeys pass with saved-data controls; no unexplained browser errors or failure. **Stop:** source mismatch, unsafe target, app-state loss, hidden write, invalid linked recovery result or unrelated data drift. A browser/download policy refusal is a recorded untested limitation, not a pass or permission to bypass policy. No universal “every possible regression absent” claim follows.

### S7 — Accept the actual Master result and refresh dependent plans

**Files/owners:** all thirteen selected paths; result/evidence records; Catalog/Images planning files owned by `Plan catalog configuration and UX`. No new feature code.

- [ ] Record current `master` HEAD, thirteen actual source hashes, complete tested source/build identity, test commands/exits/totals/engine scope, native observations/readbacks, exclusions and unrelated-work preservation.
- [ ] Obtain an independent source/evidence review of the actual main result against this spec. A plan review or old worktree result is not that review. Resolve concrete findings without expanding scope; new behavior/scope decisions return to the human.
- [ ] If separately authorized, commit **only the exact thirteen reviewed paths**, inspect staged diff/path list and verify no preexisting index content is accidentally included. Do not use broad staging, merge an entire old branch, push or deploy. If commit authority is absent, stop with an explicitly labeled **verified but uncommitted main-checkout overlay**; do not say committed Master contains it.
- [ ] Only after integration and actual-Master verification, give the resulting committed SHA (or clearly qualified approved source identity) and change map to Catalog/Images. Require their final plan/source alignment on that actual baseline, then refresh a clean feature checkout before approved implementation. Retain all old reference work/media/sites until verified replacement; do not create/reset/remove a checkout under this spec.
- [ ] Assess Writing integration **after** Groups One–Two are integrated/verified. Its independent application changes,57-path review, HTTP/browser results and shared-file composition require their own integration manifest and authority; no automatic inclusion here.

**Checkpoint C7:** all acceptance criteria below are met, exact delivery state is reported, owners have the correct baseline and remaining holds are explicit. **Stop:** uncommitted work presented as committed Master, absent source/build provenance, unreviewed adaptation, unacknowledged concurrent changes, or a dependent task trying to resume against stale Master.

## 7. Acceptance criteria for resulting Master

- [ ] Exactly the manifest’s thirteen files changed: six application, seven verification; nine existing/four new; no deletions. Each after-hash equals the reviewed hash unless an explicitly reviewed amendment replaces this manifest.
- [ ] Both inclusion paths use the shared six defaults, supplied values retain precedence, and IDs/order/defaults/old drafts/unrelated graphs remain correct through S2/S5/S6.
- [ ] Bad stored appearance fields recover selectively with safe linked-field handling; valid unrelated values and raw storage/version are preserved until explicit authorized Save. Missing singleton/cache failures are not hidden.
- [ ] Cold-page warning, authorized repair link, strict Save, request lifetime, transactions/cache, Product consumer and unsaved Preview coexistence are proven within the defined cases.
- [ ] Actual selected source/build passes required narrow, complete requested SQLite/Node/build and guarded MySQL checks, with seven retained-transfer exclusions and explicit SQLite aliases reported honestly. No old combined totals are substituted for current results.
- [ ] CLI characterization holds through the real console renderer; resulting-code physical commit claims have actual independent-process proof. Production command bytes remain unchanged.
- [ ] All unrelated main entries/index content, protected92 Images/53 prototype paths/HEADs, other checkouts, environments, databases/media/services and existing QA ownership remain preserved apart from explicitly authorized disposable setup.
- [ ] Main commit/dirty-overlay state is accurate; no merge, push, deployment, dependency/schema/security change or unapproved Writing/Catalog/Images code is included.
- [ ] Catalog/Images receive the verified actual baseline and complete only affected plan/dependency/source refreshes before future implementation. Writing integration remains ordered after this package.

## 8. Recovery that preserves concurrent changes

1. **Before application:** failed patch/hash/ownership/target check means no transfer. Resolve the source fact or request a reviewed amendment; do not force or reconstruct from memory.
2. **During integration/testing:** stop dependent work, retain logs/source identity and selected preimages. Do not run `git reset --hard`, broad `git restore`, `git clean`, stash, reset a database or restore another task’s whole folder.
3. **Uncommitted transfer, no concurrent edit of selected paths:** compare all selected bytes to this package’s exact applied bytes. Only if they still match, use a checked reverse patch or recorded selected preimages; remove a newly added file only if its bytes still match this package and no other owner has adopted it. Preserve unrelated/index changes. If a selected file differs, stop for a reviewed three-way, field/hunk-level recovery.
4. **Committed integration:** do not rewrite shared history. Prepare a targeted revert only after authority and review, preserving later changes and testing its actual result. No automatic revert/commit/push is authorized here.
5. **Data/cache:** this package has no migration or automatic data repair to roll back. Never restore a main database from QA. Explicit appearance Saves performed only in disposable QA affect owned fixtures; any operational repair of real settings requires its own clear authority. Do not globally clear cache or restart services as a substitute for diagnosis.
6. **QA partial setup:** record stage/owned resource names and stop; never automatically replay a partly executed fixture/provisioning operation. Unlink/drop/remove only the dedicated resources whose ownership and human cleanup authority are verified. Retained sites, old audit QA/manual-download evidence and Writing resources remain outside this cleanup.

## 9. Decisions, holds and deferred work

**No known product-behavior decision remains for these two repairs.** Selective fallback, warning, preservation of valid settings, no implicit persistence, and shared local defaults are settled. Current source inspection found no intervening application change requiring adaptation.

**Required before execution:** human authority to apply this exact package and perform its specified isolated verification; exclusive main/test windows; valid restricted private test profile; explicit disposable browser-setup authority; separate commit authority if desired. These are execution boundaries, not a request to redesign the settled behavior. Do not request or paste passwords.

**Reconsider only when evidence requires it:** a changed Master/consumer/API, failing acceptance case, unsafe isolation, or new scope/behavior choice. Present the concrete finding, affected paths, smallest compatible option and recommendation. Routine preparatory reads and revising this specification are already authorized.

**Deferred/excluded:** all other security/hardening, API/forms UX audits, new queue/export work, XLSX browser-finalization diagnosis, architecture reorganizations, dependency/CI improvements, seven original-target transfer cases, new Rules Writing functionality/integration, Catalog/Images implementation, full style packages/live preview, and cleanup/removal of retained worktrees/sites/media. No new production command, migration, setting field, engine, permission system or broad test rewrite.

### Required Catalog/Images refresh after verified integration

The owning catalog chat must compare the actual new Master against its current plan/reuse assessment, especially: shared local inclusion defaults; separate selected Default and preserved drafts; selective appearance reader/strict writer split; PAGE_START notice, styles/body hooks and panel registrations; shared test guard union; snapshot/revision contracts; current Product/Group/browser behavior. Update only genuinely affected clauses and dependencies, then independently review those changes. Recheck retained source reuse/adaptation/discard decisions against actual code, not the old mockup or this planning document alone.

Preserve the authoritative product choices: Precision, bounded centralized settings without live preview, flat configurator choices; product-bearing end-Group cards with parent taxonomy; tree and custom Group tags as alternative Group navigation; Group tags never become Product filters; Product filters operate independently inside the selected Group. No plan refresh is proof of future runtime acceptance. Existing feature holds remain until their proper next authorization.

## 10. Exact integration manifest

The adjacent JSON is authoritative for per-file before/after hashes and preservation. The full patch is reproducible from the recorded Master base and reviewed source. Its selected paths are:

| Step | Exact path | Role |
|---|---|---|
| S1 | `tests/TestDatabaseSafety.php` | Modify verification fence |
| S1 | `tests/Unit/ReconciliationTestDatabaseSafetyTest.php` | Add meaningful refusal/compatibility cases |
| S1 | `phpunit.reconciliation-mysql.xml` | Add explicit restricted test suite |
| S2 | `app/Services/ConfiguratorInclusionDrafts.php` | Modify shared local-default authority |
| S2 | `app/Actions/SaveConfiguratorOption.php` | Modify ordinary new inclusion reuse |
| S2 | `tests/Feature/ConfiguratorCreationFlowTest.php` | Modify parity/override and portable assertions |
| S3 | `app/Services/AdminAppearance.php` | Modify selective read recovery |
| S3 | `tests/Feature/Filament/AdminAppearanceTest.php` | Modify recovery/lifecycle/permission coverage |
| S4 | `app/Providers/Filament/AdminPanelProvider.php` | Modify native notice registration |
| S4 | `resources/views/filament/resources/appearance-recovery-notice.blade.php` | Add presentation-only notice |
| S4 | `lang/en/admin.php` | Modify localized notice/link copy |
| S5 | `tests/Feature/Filament/ConfiguratorWorkspaceTest.php` | Modify deterministic V20 setup only |
| S5 | `tests/Feature/Catalog/CatalogSnapshotCommandTest.php` | Add real-console characterization |

The three planning artifacts themselves are **not application integration paths** and must not cause the implementer to stage other pending documents. Specification preparation is complete only after self-review/source-reference/manifest consistency checks; implementation and all proposed verification remain unexecuted under this request.
