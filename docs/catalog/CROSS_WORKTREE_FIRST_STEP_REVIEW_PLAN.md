# Cross-worktree first-step review implementation plan

> **Historical record — publication note, 1 November 2026.** The original status, commands, approval gates and worktree assumptions below describe their dated October baseline; they are retained evidence, not current execution instructions. Groups One and Two were delivered on Master in `07701045e4dda35213e24b078eba19675d697fc6`. Use the [current delivery and history index](../README.md#current-delivery-and-historical-records), [stage protocol](STAGE_AUDIT_AND_DOWNSTREAM_REPLAN_PROTOCOL.md) and [current Catalog/Images execution plan](CATALOG_REBUILD_COMPLETE_PLAN_AND_EXECUTION_SPEC.md#27-k8-combined-acceptance-and-remaining-images-handoff--1-november-2026) for present scope and remaining verification. Host-local paths/hashes are historical evidence references, not bundled or fresh runtime proof.

> Planning/review authority only, 29 October 2026. Use `superpowers:executing-plans` for this bounded review when dispatched. No application implementation, tests, build, runtime, checkout or Git mutation follows from this plan. Agent delegation and outbound messages require their own authorization.

**Goal:** give the coordinator a complete, versioned decision packet for the first Catalog/Images and Writing packages, with immediate omissions/collisions resolved before implementation is delegated.

**Architecture:** retain the feature owners' plans and application authorities. The audit reads exact owner-supplied artifact versions, follows only their affected source/dependency paths, and returns actionable findings plus explicit evidence gaps. The coordinator arranges an independent check of this audit-owned package; self-review is not independent review.

**Tech stack:** inspected installed metadata remains PHP 8.4 application, Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7, Pest 5.3.0 and MediaLibrary 11.23.9. No package or API changes are proposed.

**Spec:** [first-step execution specification](CROSS_WORKTREE_FIRST_STEP_REVIEW_EXECUTION_SPEC.md). Full dispositions and provenance: [reconciliation](CROSS_WORKTREE_RECONCILIATION_2026_10_29.md) and [evidence manifest](CROSS_WORKTREE_RECONCILIATION_EVIDENCE_2026_10_29.json).

## Global constraints

- Baseline is master `8e1f640219ec8dbd2ee4c739041907aba4c4224d`, including repair merge `5788905a4a0ac95f26cf435cac4d82c09b576dd0`. Refresh before material review; do not move a checkout.
- No immediate master-code repair is established by the completed bounded reconciliation. Do not manufacture a fix-now task from S08/S10/R18, optional organization, or pending feature implementation.
- Preserve the nine preexisting pending documents, every retained worktree, untracked source, prototype and ignored runtime. Catalog's coordinated R2/R3 revisions and the coordinator's subsequently added `COORDINATION_REVIEW_PACKAGE.md` are attributed owner changes. This package owns only its four new audit documents.
- Scope is the original four streams: Product fields, Preview, Bulk plus consolidation/retirement/CP5–7, and pending Catalog/Images. Writing is an additional feature consumer.
- Technical review is not human approval to implement, run tests, create a checkout, merge, push, change dependencies or start security work.
- Do not restart the original whole audit, the deferred third architecture sweep, an exhaustive 1,155-clause conformance review or an unlimited browser matrix.

## Review focus

1. Native `create(true)` performs inherited reset/model rebinding before an enclosing commit; event/notification-only tests cannot close IMG-B01.
2. A matching image is an observation, not proof the missing-response request committed; fingerprint and actor/owner/full-draft/destination checks must reach completion.
3. A Rules-only save built from an incomplete aggregate can delete unselected Rules, memberships/defaults or subtitle intent.
4. Parallel packages can preserve their own happy paths while replacing the other's provider, registrar, writer, compiler/loader or revision behavior.
5. Old lane reports and logs in temporary storage may no longer exist. Historical hashes and supplied results do not become reproducible current evidence; no fresh runtime pass is implied.

## Task 1 — freeze a readable reconciliation packet

**Files created:** this plan, its execution spec, `CROSS_WORKTREE_RECONCILIATION_2026_10_29.md`, and `CROSS_WORKTREE_RECONCILIATION_EVIDENCE_2026_10_29.json`, all under `docs/catalog/`.

**Inputs:** completed 28 October deep source review, available full report, current source and protected-state receipt. **Output:** complete current dispositions, original ID/alias mapping, leftover-work classification, source availability and hashes.

- [ ] Verify the current HEAD, statuses and exact nine protected-document hashes against the manifest.
- [ ] Read the report's current reconciliation first; preserve the complete prior inventory as dated evidence with explicit corrections.
- [ ] Check all finding families, repaired/retired/duplicate decisions, source-only risks and missing evidence are represented. Do not claim every changed line or every historical test was independently re-executed.
- [ ] Confirm local links, coverage references and new-file allowlist; report unavailable historical pointers as unavailable rather than deleting their provenance.

**Checkpoint A:** a complete packet can be read from files without a truncated chat snapshot or access to a missing temporary lane report. Task 1's documentation is prepared; checkbox completion belongs to the eventual review record, not an automatic acceptance claim.

## Task 2 — review the owners' exact first packages

**Read-only inputs:** Catalog's `CATALOG_AND_IMAGES_MASTER_EXECUTION_SPEC.md` and `CATALOG_AND_IMAGES_REUSE_ASSESSMENT.md`; the Writing owner's refreshed plan/spec paths and hashes supplied by the coordinator. Do not guess a Writing revision from an older prototype.

**Output contract:** each review finding has ID, exact artifact/hash and section, affected source/caller, concrete trigger, consequence, source versus runtime confidence, smallest correction, owner, dependency, discriminating acceptance and blocking scope.

- [ ] Pin the Catalog artifacts and review M0 + I1–I4 only for the first execution decision; retain later phase dependencies without researching all their solutions.
- [ ] Review A01/A04 together; A02 lock/owner admission; A03/A07 startup/provider union; R01 goal-state recovery; B01/B02 native lifecycle/full-draft fencing; safe combined acceptance and rollback/protected work.
- [ ] Pin the now-supplied Writing plan and Slice 1 specification at the hashes recorded in the reconciliation. Review W1-0 through W1-4 only; independently continue Catalog and source-availability checks.
- [ ] Review one-Rule merge, complete current loader data, trusted in-process opening baseline/stale handling, exact literal keys/identities, unsupported native syntax/effect order and Enabled/priority preservation. Shared drafts, batch saving, selected-section refresh and Preview events are later slices: check non-interference/future compatibility, never require their implementation for W1.
- [ ] Produce a shared-file/contract ownership matrix and schedule proposals. Concurrent planning is fine; shared-file implementation needs one owner and ordered handoff or a reviewed semantic combination.
- [ ] Return package verdicts: ready for human implementation decision, changes required, or specific evidence/engineering decision pending. Never call an unexecuted test passed.

**Checkpoint B:** named immediate omissions/collisions have an owner and correction or an explicit narrow gate. Both owner artifact versions are now supplied; their bounded verdicts belong in the reconciliation. Native completion feasibility blocks only the affected Catalog subpackage, not all unrelated planning.

## Task 3 — bounded correction review and human handoff

**Inputs:** owners' corrected artifact hashes/deltas and independent review of this audit package. **Outputs:** revised findings, disposition and approval request prepared for the coordinator.

- [ ] Re-read only changed decisions and affected dependencies. Reopen a settled item only on new contrary evidence.
- [ ] Preserve unresolved engineering feasibility and unrun tests as explicit obligations. Do not close B01 from custom-event assertions alone.
- [x] Coordinator independently reviewed this plan/spec and the complete four-artifact packet at the receipt hashes recorded in the evidence manifest. The W1-scope clarification is incorporated; no further immediate planning correction was found. This is a different task's bounded verification, not author self-certification or runtime acceptance.
- [ ] Return the concrete proposed implementation package, exclusions, prerequisite decisions, future test target/coverage and exact human approval still needed. Stop at that gate.

**Checkpoint C — fulfilled at planning-review scope, 29 October 2026:** the coordinator independently accepted this four-artifact package for the human-decision handoff. This is not an implied implementation authorization or automatic merge.

**Current delivery:** all four audit documents are prepared; both feature packages have bounded technical verdicts in the reconciliation. Catalog R3 closes XWR-01/COORD-01; Writing W1 is ready at plan-review level. Plan bulk creation forms independently checked the complete packet and accepted it for human-decision handoff; the exact reviewed hashes and limits are recorded in the manifest and reconciliation. The human execution decision remains pending. This receipt-only update does not change substantive findings, feature scopes or acceptance obligations. Other checkboxes above describe the review protocol, not an execution or runtime-acceptance certificate.

## Options and recommendation

**Recommended:** finish this bounded artifact/source review first, then let feature owners implement separately approved packages from current-master isolation. It uses completed audit work and can catch shared-contract omissions before coding.

**Alternative:** require a new whole-app audit before either feature. Rejected for this first step: no new master integration defect establishes that prerequisite, and it would repeat completed work while delaying unrelated Writing/catalog decisions. A specific new source contradiction can justify a narrower investigation.

## Approval and rollback

This planning-only package is authorized. Application execution remains gated on explicit human approval of a named owner/package/lane. If the packet needs correction, update only these audit-owned documents; preserve every other owner file. No Git revert/reset, stash, cleanup, test database, migration or runtime rollback is authorized or necessary for document review.
