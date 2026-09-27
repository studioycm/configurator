# Catalog rebuild — deployment and rehearsal evidence

Updated 2026-09-27. **The local-computation filter release is live:** application commit `d2f761082202400c6cdf0672e8836b1dde33f0c3`, Forge release `78678306`. The deployed browser/server evidence is recorded at the end of this document. The earlier library-import baseline is application commit `92290a41f88c9a84d0be3093bd895eab7677acba`, Forge release `78452711`, following the original promotion below. Updates use automatic deployment from primary `master`; a fresh Forge read again verified `quick_deploy: true`. The original application and data remain recoverable. Preview & Test remains a placeholder. The actual legacy library is partially imported; ten conflicting-code Options and the real Configurator definition remain pending. Local D060 is unassigned; remote D060 has a newer `test d060` assignment created before the library import and preserved by it.

## Full-width filters and legacy library update

- Filter choices fill each column and stack vertically; all seven columns were verified in the live browser.
- Private legacy source export SHA-256: `817404483c876dc9e949d4510884770e915b7a17beaf0296422a47af489916e3`. The additive import created 13 Attributes, 25 Options and 25 separate Values locally and remotely. Ten Options sharing five duplicated codes remain pending the user's code-uniqueness decision. No source code was invented or normalized, no labels were merged, and no Configurator membership or assignment was imported.
- A remote dry-run, hash-gated apply and repeat review verified the expected result, then zero new records. The source database was read only. Pre-import target dump: private `ari-catalog-cutover-20260924/rebuild-before-library.sql`, SHA-256 `e8615eb0f3ae30dd50c0888de482dfa465ee0f5b4bac6fe1586cef1c13a8c133`.
- Local browser confirmed the imported Attribute/Value/Option relationships. Remote rows were verified by database query; this browser still requires remote admin sign-in. Remote `test d060` was created 03:46:47 UTC and assigned 03:47:03 UTC, before this import; its zero inclusions were left unchanged.
- Validation: full SQLite suite 253 tests / 1428 assertions; targeted isolated MySQL suite 14 / 98; Pint and Vite build passed. GitHub check `35954825976` passed. Application revision and current Forge release match.

## Primary development workflow

| Purpose | Code / location | Database |
| --- | --- | --- |
| Primary local development | `master`, `/Users/studioycm/Herd/configurator`; `https://configurator.test` | `configurator_catalog_dev`, MySQL at 127.0.0.1:3307 |
| Remote development | GitHub `studioycm/configurator`, `master`; `https://ari.data4.work`; Forge `general-dev` server 845234, site 2954882 | `ari_configurator_rebuild`, MySQL at 127.0.0.1:3306, own restricted account |
| Preserved old local app | `codex/legacy-pre-rebuild`, commit `1b046ae`; reviewed original polish and skills included | Original `configurator_local`, retained |
| Preserved old remote app | Archived release 78345531, revision `0c43f99a817b74c4b4745b5c1b61f85b04fd64de` | Original `ari_configurator`, unchanged; restore-verified `ari_configurator_legacy_copy` |

Continue editing in the primary local folder. Use feature branches when useful, run affected tests and `npm run build`, then commit/push reviewed changes to `master`. GitHub **Deployment check** runs on `master`, alongside the automatic Forge push deployment. Verify the exact commit in both systems; do not trigger a second manual deployment. This is a normal zero-downtime deployment: install locked production dependencies, build assets, guard the exact rebuild database/account, apply new migrations, compile caches, activate the release and restart this site's Horizon workers. Do not rerun data-copy scripts or replace the live environment on ordinary deployments.

Do not point legacy code at the rebuilt schema when consulting the side branch. Use a separate checkout with a copy of the archived legacy environment and its matching old database. A code-only branch switch is insufficient. The earlier implementation worktree remains available but is no longer the primary folder.

## Remote cutover and verification

- Actual source inspection: 32 tables; two users; one Bolt form, one section and three reviewed fields. Empty other retained package tables were classified. The preserved field types are historical data; their removed Bolt UI is not restored. No dependency was added.
- A private, one-time deployment script copied only the reviewed retained rows through PDO into the empty rebuilt schema. It checked exact source/target database names, identical encryption keys, unchanged source rows, empty/conflict-free targets, reviewed field types/assets and exact copied values. The application transfer command remains intentionally local-only; its guards were not broadened or bypassed.
- Fresh migration into `ari_configurator_rebuild`, reviewed three-hash import and D060 filter seeding completed. Live repeat: 501 unchanged, zero created/updated/absent. Seven filters, zero orphan Products, zero queued database jobs and zero remote synthetic Configurators. No parent metadata or real configuration codes were invented. The labelled local synthetic fixture remains local and unassigned.
- Legacy database dump restored into `ari_configurator_legacy_copy`: all 32 counts and SHA-256 values matched. A post-cutover source check found zero changed table fingerprints. Ten original/conversion media files matched the storage archive. Old media owner rows, sessions, reset tokens and five database jobs remain with the old schema/archive.
- The deployment boundary used maintenance mode, stopped only Horizon process 645746, repeated the retained-source check, switched the shared environment, activated the rebuilt release, brought the site up and restarted that process. Other sites/workers were untouched. Separate Redis, cache, Horizon and session-cookie prefixes prevent old jobs/sessions from crossing into the rebuilt app. Horizon is running.
- HTTPS browser checks passed for Home, Group filtering (501→85), Product facts and explicit unassigned state, shared theme persistence and admin login form. Retained account hashes/encrypted fields and application key match exactly. A fresh interactive password/2FA login has not been performed; no credentials were reset or authentication bypass added.
- Full local suite before promotion and again in the promoted folder: **245 passed / 1370 assertions**. Earlier guarded MySQL suite: **94 passed / 595 assertions**. Vite build and GitHub production build passed. Live assets match the local build: `app-lstW9z9N.css`, `theme-D9ls54Mt.css`, `app-Ppl_fNu9.js`.

## Preserved backups and rollback

Private server archive: `/home/forge/ari-catalog-cutover-20260924/` (mode 700). It contains the matching old release archive, old environment/key, database dump, storage archive, initial inventory, original Forge deployment script and one-time transfer evidence. Local private archive: Git-ignored `storage/app/imports/ari/promotion-20260924/`, including the original local environment/patch/Herd file and remote backups. Never commit these files. Source CSVs also remain Git-ignored.

Verified archive SHA-256 values:

| Artifact | SHA-256 |
| --- | --- |
| `legacy-release.tar.gz` | `bb10c2e07bf7ce0ef26ed4105fe8a5c94eae29bf68ab92d6d1fca7ef182b82dd` |
| `legacy-storage.tar.gz` | `01dc8b360e643c610dee5118a02151f9668818d5789669a407aad442f1b65e6c` |
| `legacy-database.sql` | `0f62b917ba19b82d4e8e3fe51c6d522b1dd973d9bb79b7ac9ea1a6a85bfafdc0` |

Rollback is a coordinated code/environment/database operation. First freeze rebuilt writes and stop only its worker; preserve any new rebuilt data and review reconciliation before discarding access to it. Restore the archived legacy release into a separate release directory, pair it with `legacy.env` and the preserved legacy storage/database, rebuild its caches against that pair, then activate it and verify before reopening. Restore the archived Forge branch/script settings as part of a deliberate rollback. Never run the rebuild migration baseline against `ari_configurator` or invoke a destructive reset. The old database restore was verified in a separate database; no live reversal was needed.

The sections below record the earlier local rehearsal, before the later development-promotion instruction. Their no-deployment statements are historical.

## Verified local boundaries

| Role | Database | Outcome |
| --- | --- | --- |
| Original installation | `configurator_local` | Preserved; all 32 table counts and SHA-256 hashes unchanged after rehearsal |
| Rebuild development | `configurator_catalog_dev` | D060 has 501 Products and is unassigned after QA |
| Disposable tests | `configurator_catalog_test` | Exact database/driver/environment/account guard; final combined MySQL check 94 tests / 595 assertions passed |
| Fresh rehearsal | `configurator_catalog_rehearsal` | New restricted account; fresh migrations, retained transfer, import and reimport passed |
| Rollback rehearsal | `configurator_catalog_rollback` | Restored source dump; every table matched; old code/demo returned HTTP 200 |

All are local MySQL 8.0.46 at 127.0.0.1:3307. Separate database-restricted accounts serve each new database. Application environment files were never switched. The activation/reversal rehearsal used process-local overrides, read-only rendering transactions and array sessions/cache.

## Retention and integrity

- Preserved source snapshot: two users; one Bolt form, one section and three fields. All IDs, password hashes, encrypted account fields and row values survived. The application key matched. Original administration and the new development administration admitted the retained administrator; a fresh production sign-in was not performed.
- Bolt/workflow/notification schemas are retained. The installed runtime lacks the three historical Bolt field classes; their data is preserved, without claiming the old Bolt UI is available. No dependency was added. New populated package tables or unclassified references require a revised retention map; the transfer command refuses them.
- Seven media rows refer to old FileAttachment owners. Seven original files and three preview conversions were copied and SHA-256 verified into the private archive. They are not assigned new catalog owners. Original files remain untouched.
- Five queued jobs remain archived with the original database: four password-reset notifications and one old media conversion. None was copied into the new release queue or executed. Sessions, cache, reset tokens and queue recovery data stay with the old installation/archive.
- Rehearsal retained-row counts and hashes matched on repeat. New data: one unassigned D060 Group, 501 Products, seven reviewed filters, zero canonical production definitions. CSV SHA-256 `5a8adbd4439bb1f020ab625d84fa6fde3a62ce67fbd74f94cf2b2e3b19758f72`; map SHA-256 `8fe96bb514dd68393dda4c8e42eaf73758ed806db13898bb7396735a81879f54`. Reimport reported 501 unchanged and zero created/updated/absent. There are no orphan Products.
- Auto-increments after transfer/import: users 3, Bolt forms 4, sections 2, fields 4, Groups 2, Products 502. Public catalog, Group and Product routes returned 200 against the rehearsal database. MySQL tests independently cover exact Option codes, CHECK/FK constraints, JSON predicates, late rollback, reference ownership and import concurrency.

The import command was subsequently strengthened to require a third approval hash for normalized parent metadata, even when empty. The final SQLite/MySQL regression covers the revised command and 501-row repeat; the original rehearsal reports above remain historical evidence. Obtain all three hashes from a fresh dry-run for the next apply.

Private evidence resides under Git-ignored `storage/app/imports/ari/rehearsal/` in the implementation checkout. Its archive directory has private permissions and contains the database dump, environment/key, matching original revision plus application patch, file hashes and row-count/hash inventory. Do not publish it or add it to Git. The tracked report intentionally contains no credentials, account addresses, password hashes or queue payloads.

## Remaining client release work and historical gates

1. Resolve the ten legacy Options with conflicting codes, then supply the real Configurator definition, parent metadata and any SubGroups. The library import did not assign D060; the newer remote `test d060` assignment is separate user development state. No local synthetic QA definitions were deployed remotely.
2. Complete a fresh interactive login with the retained account and any required 2FA, then review actual client content in administration. Restore Preview & Test functionality only when that deferred work is requested.
3. Remote inspection, backup/restore verification, separate target migration/import, worker/storage disposition, immutable build and the explicitly authorized development switch are complete above. Future populated retained-package content requires a new reviewed retention map; the local transfer guard remains unchanged.
4. Preserve old code/database/storage as a matching set. After new writes begin, rollback requires reviewed reconciliation of those writes, not an automatic switch back.

The original handoff/rehearsal had deploy-on-push disabled. The subsequent user-authorized primary development workflow now has it enabled. Real parent metadata, initial SubGroups and approved canonical meanings/codes remain client content inputs.

## Local filter release — 2026-09-27

The user explicitly requested commit, push and rigorous remote browser testing, including feedback when values change. An exact 45-file filter allowlist was committed as `d2f761082202400c6cdf0672e8836b1dde33f0c3` and pushed to `origin/master`. Two existing local branding commits (`16aae98`, `93b1a2e`) accompanied it. Unrelated `herd.yml`, Finder files, `public/vendor/` and local media remained uncommitted.

[Deployment check 36292040999](https://github.com/studioycm/configurator/actions/runs/36292040999) passed. Forge release **78678306** built the same asset hashes, ran the additive catalog-revision migration, compiled caches, activated the release and terminated the old site worker. The live symlink and Git HEAD resolved to this release/commit; the new site worker's working directory was the new release. Other sites' workers were not changed. The live database was verified as `ari_configurator_rebuild`, with **REPEATABLE-READ** isolation. The Group snapshot was warmed successfully.

### Remote browser evidence

Real Chrome 152 interaction used the deployed production bundle on the 501-Product D060 catalog, at a 1470 × 741 desktop viewport on the same 8-core Mac. Existing remote settings were preserved: threshold **6**, six card columns, effective debounce **0**, and **no presets**. No production test data or temporary Group-setting changes were made.

| Final 100-click run | p50 | p95 | Maximum |
| --- | ---: | ---: | ---: |
| Computation/state publication | 0.9 ms | 1.7 ms | 28.9 ms |
| DOM feedback after Alpine's next tick | 14.0 ms | 16.6 ms | 34.7 ms |
| Two-frame paint estimate | 45.7 ms | 47.1 ms | 48.2 ms |

Paint is an estimate, not a measured paint event. All **100 independent total checks matched** the snapshot rows; totals exercised included 1, 2, 4, 5, exactly 6, 7, 9, 12, 21, 36, 63, 85, 384 and 501. The run produced **40 card POSTs / 40 `loadCards` action executions**, **zero filter-state requests**, and no dataset GET within the measured run. Card transfers totalled **79,014 bytes**; request duration p50 was **136.6 ms**, p95 **165.2 ms**. The browser also recorded 33 favicon lookups, mostly cache hits (155 total transferred bytes), which are separate from application requests. Livewire public data remained only `{"groupId":"1"}`. No application errors or unhandled promise rejections occurred in the measured run.

The earlier old-code remote baseline had selected-button feedback p50 370 ms / p95 434.9 ms for ten selections. Its sequence and viewport differ from this run; do not present the two as a controlled identical-sample percentage comparison.

Changed-selection feedback was directly verified:

- **25 bar → Flange → 1″** resulted in 36 Products, cleared pressure, and displayed **“Cleared Working Pressure to keep your newer choice.”**
- **Flange → 25 bar → 1″** resulted in 9 Products, cleared Flange, and displayed **“Cleared Connection Type to keep your newer choice.”** Back restored the 63-Product prior combination; Forward restored 9 without another history entry.
- Other transitions displayed the corresponding Connection Size removal notice. Voluntary replacement/toggling is shown through selected-button styling and the exact total; the notice names an automatically cleared field, not its former literal value.

Further live checks passed: all option compatibility states matched an independent own-field-excluding scan; incompatible buttons remained operable; the eligible Product was exactly ID **489**, code `D62SL-EL-P25-T1`, matching both browser and server identities. With 700 ms simulated network latency, Reset while cards were loading left 501 Products and no stale cards after late responses. Deliberately induced offline card/freshness failures retained usable filters, showed scoped retry feedback, and recovered through the real Retry buttons without unhandled rejection.

Two coincident freshness calls shared one promise and one real **304** response. A natural visible-page check occurred **60.2 seconds after navigation**, also returning 304. An old link containing pagination parameters preserved precedence and unrelated parameters while replacing the URL and displaying adjustment feedback. Navigation to Catalog and Back restored the selections with one component instance. Keyboard Space toggled selection correctly; a real 390 × 844 viewport had document width 390, without horizontal overflow. Network and viewport overrides were reset after testing.

The existing remote Group has no presets, so live preset scenarios were not fabricated; their earlier local and automated evidence remains applicable. Native cached-page restoration, a fully hidden polling interval, externally changed catalog values/revisions, schema-change reload, deleted/branch Group transitions and session expiration were not all induced remotely. Empty-dataset and `all` settings remain covered by the prior local/automated evidence rather than production data changes. The local all-501-card stress limitation remains open; the live threshold-6 run does not establish all-mode performance. Intentional DevTools/network test errors are distinct from application exceptions. The site's Laravel log remained empty after these checks.

### Remote service/query evidence

| Operation | Queries | SQL time | Service time |
| --- | --- | ---: | ---: |
| Snapshot builder | 5 SELECTs, 1 Product read, zero SQL counts | 29.90 ms | 47.75 ms |
| Warm eligible card operation | 2 SELECTs, 1 Product read, zero SQL counts | 4.51 ms | 50.32 ms |

Both operations ended with transaction level zero; rendering runs after the card read transaction. These are single service measurements, excluding HTTP/authentication/session work, and are not request latency percentiles. The remote revision-1 snapshot contained **501 rows**, **13,717 JSON bytes / 2,955 gzip bytes**, with no malformed-cell diagnostics. Remote SQL counts no longer include repeated vocabulary, compatibility or whole-group diagnostic work on card requests.

Pre-push checks were rerun: Node **8 passed**, guarded MySQL **22 passed / 100 assertions**, Pint/build/diff checks passed. The full existing suite again reported **332 passed, 2 unchanged admin-label failures / 1909 assertions**; the failures and their unrelated files are detailed in the local implementation evidence. They were not hidden, removed or modified for this release.
