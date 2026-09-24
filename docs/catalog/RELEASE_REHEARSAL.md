# Catalog rebuild — deployment and rehearsal evidence

Updated 2026-09-24. **The user-authorized development promotion is live.** Application commit `62e7bb42ea8458e0769434fe93451393cf49a546` was pushed to primary `master` and deployed by Forge deployment `78450920` (finished 03:18:57 UTC). The original application and data remain recoverable. This is the working development release; approved client configuration definitions are still missing, D060 remains unassigned and Preview & Test remains a placeholder.

## Primary development workflow

| Purpose | Code / location | Database |
| --- | --- | --- |
| Primary local development | `master`, `/Users/studioycm/Herd/configurator`; `https://configurator.test` | `configurator_catalog_dev`, MySQL at 127.0.0.1:3307 |
| Remote development | GitHub `studioycm/configurator`, `master`; `https://ari.data4.work`; Forge `general-dev` server 845234, site 2954882 | `ari_configurator_rebuild`, MySQL at 127.0.0.1:3306, own restricted account |
| Preserved old local app | `codex/legacy-pre-rebuild`, commit `1b046ae`; reviewed original polish and skills included | Original `configurator_local`, retained |
| Preserved old remote app | Archived release 78345531, revision `0c43f99a817b74c4b4745b5c1b61f85b04fd64de` | Original `ari_configurator`, unchanged; restore-verified `ari_configurator_legacy_copy` |

Continue editing in the primary local folder. Use feature branches when useful, run affected tests and `npm run build`, then commit/push reviewed changes to `master`. GitHub **Deployment check** now runs on `master`; application build run `35950474987` passed. After the check passes, use Forge **Deploy Now** for this site (or the Forge connector's deploy action). Deploy-on-push remains off. This is a normal zero-downtime deployment: install locked production dependencies, build assets, guard the exact rebuild database/account, apply new migrations, compile caches, activate the release and restart this site's Horizon workers. Do not rerun data-copy scripts or replace the live environment on ordinary deployments.

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

1. Supply approved client Attributes, Options, codes, parent metadata and any SubGroups. Keep D060 unassigned until its real definition is approved. No temporary QA definitions were deployed remotely.
2. Complete a fresh interactive login with the retained account and any required 2FA, then review actual client content in administration. Restore Preview & Test functionality only when that deferred work is requested.
3. Remote inspection, backup/restore verification, separate target migration/import, worker/storage disposition, immutable build and the explicitly authorized development switch are complete above. Future populated retained-package content requires a new reviewed retention map; the local transfer guard remains unchanged.
4. Preserve old code/database/storage as a matching set. After new writes begin, rollback requires reviewed reconciliation of those writes, not an automatic switch back.

Forge deploy-on-push is stated disabled in the handoff. No Forge configuration was changed or deployment performed during this rehearsal. Real parent metadata, initial SubGroups and approved canonical meanings/codes remain client content inputs.
