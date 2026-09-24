# Catalog rebuild — local release rehearsal evidence

Recorded 2026-09-24. Local rehearsal passed. **Release is not ready:** temporary QA definitions remain, approved production configuration content is missing, production retention has not been inspected and no immutable release revision has been made. Approved archival cleanup and final whole-branch review fixes are complete; full SQLite 245 / 1370 and guarded MySQL 94 / 595 passed. No commit, push, deployment or active database switch occurred.

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

## Before a real release

1. The [approved retired-code cleanup](LEGACY_ARCHIVE_REVIEW.md), preserved historical tests, green complete suite and whole-branch review are complete. Remove the explicitly temporary synthetic QA definitions. Record an immutable matching release revision and asset build; the current worktree is uncommitted.
2. Inspect the actual deployment database, storage disks, package references and queued jobs read-only. Local counts do not classify production. Resolve populated workflows/notifications, Bolt assets/extensions and any deliberately retained old-domain owner references before expanding the local-only transfer guard.
3. Take and restore-test a production backup with the matching code revision, required local patch, encryption key and all retained files. Freeze writes and stop/drain workers at the actual release boundary, or validate an explicit delta transfer. Repeat the source fingerprint immediately before apply; a changed fingerprint refuses transfer.
4. Apply reviewed migrations and transfer/import into a separate production target. Verify credentials, IDs, FKs, file/conversion hashes, sequences, counts, public/admin routes and approved client definitions. Test actual login. Do not run old jobs against the new schema or silently map old morph IDs.
5. Obtain the explicit instruction for the coordinated code/database switch. Keep old code, old database and compatible assets available together. Before accepting new writes, verify the new pair; if verification fails, reverse both to the frozen old pair. After new writes begin, rollback requires reviewed reconciliation of those writes, not an automatic switch back.

Forge deploy-on-push is stated disabled in the handoff. No Forge configuration was changed or deployment performed during this rehearsal. Real parent metadata, initial SubGroups and approved canonical meanings/codes remain client content inputs.
