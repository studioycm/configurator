# Stage audit, commit and downstream plan review protocol

Authority: the human's standing delivery instructions, updated 1 November 2026. This applies to approved Configurator stages on Master; it does not expand feature scope, push/deployment/security permission or database lifecycle authority. Direct Master implementation is the default. No worktree is authorized for implementation or checking under the current mission. Any future exception needs a concrete justification and explicit human approval first. Old trees are read-only references, not merge sources.

## Roles and sequence

- Implementing owner: owns the slice's source, meaningful tests, fixes and evidence. One application/test/schema/dependency/asset writer at a time.
- End-of-stage auditor: Master Integration Audit, independent of the implementer. Reviews actual code/evidence and downstream implications; does not edit competing source or open a competing runtime window.
- Coordinator: Plan bulk creation forms. Checks evidence/conclusions, controls writer/resource ownership, relays findings to affected next owners and verifies acknowledgment plus next-plan review closure.
- Next-stage owner: reads the previous report and current Master, re-audits its own plan, updates affected clauses and obtains independent bounded review before implementation.
- Feature order: finish Catalog, then the remaining Images manager, then Rules Writing. The approved minimum Images foundation required by Catalog K7 is already committed. Other implementation writers wait. Report genuine dependencies before changing the remaining order.

Pipeline: finalized current-Master plan → one-writer implementation → verification from Master using approved test data → independent execution/impact audit → required fixes/retests/review closure → exact local stage commit → downstream handoff and acknowledgment → next-owner plan re-audit/update → independent plan-delta closure → next stage.

## Gate A — plan before code

Pin actual Master commit/source, package versions, allowlist and existing pending work. Read the prior stage's report and trace relevant changed producers/consumers in current source. Preserve settled product behavior and classify relevant findings as resolved, required-before-next-stage, accepted/deferred, unrelated or unverified. Update only genuinely affected files/methods/contracts/dependencies/verification/checkpoints/recovery in the next plan. Review material plan changes before releasing the writer. Reuse an unchanged accepted plan and its valid evidence; do not restart a full audit without demonstrated impact.

## Gate B — implementation and verification

Implement the reviewed slice directly on Master with one writer and preserve unrelated changes. Use current AGENTS/rules, relevant skills and installed-version APIs. Execute meaningful success/failure/no-op/rollback/concurrency/native checks where required. Test source on Master does not authorize using main data as a destructive test target: enforce explicit process environments, pre-bootstrap/effective-target guards, owned fixtures/storage and exclusive database windows. Record actual passed/failed/unrun checks, exact source, engine, fixture/resource identity and limits; historical results are separate evidence.

No worktree is needed for the current approved process. All implementation and checks run from Master. Approved in-memory/test databases and owned storage protect real data; this does not mean a separate code checkout. Any future worktree exception must be justified and explicitly approved before creation/use. Old worktree chat associations must be handled through safe native metadata operations; do not transfer unfinished Git state or delete retained source to make the UI look detached. Report unsupported detach-only behavior truthfully. No handoff implies feature integration authority.

Require checks that prove agreed functionality, data protection or a concrete regression risk. Reuse passing evidence for unchanged source and assumptions, and rerun only affected checks after a correction. Cosmetic and mobile review are deferred until the approved features are finished; 200% zoom testing is excluded. Preserve useful browser sessions. An explicit access restriction remains binding; record the unverified functional check and continue independent authorized work.

## Gate C — independent end-of-stage audit

Inspect the actual implementation diff/current consumers and its test/runtime evidence, not just the plan or a success toast. Check affected responsibility/write boundaries, data and serialized shapes, identity/no-op/rollback/lock/revision/cache behavior, schema/activation, registration/UI lifecycles and relevant existing regressions. Distinguish current-Master defects, pending-feature defects, supported risks, later improvements and unverified claims. Do not claim universal regression absence.

Every required finding has a stable ID, current evidence/trigger/consequence, affected source/consumers, implementing owner, smallest correction and discriminating acceptance check. The implementer fixes required findings within authority, reruns relevant checks and returns a versioned result. The auditor closes the finding on evidence; an open material finding prevents stage acceptance. A genuine new scope/product/resource decision waits for the human; routine engineering corrections do not create another approval loop. Broader security remains deferred.

### Findings must be pursued through correction and closure

Coordinator owns follow-through, not merely notification. Track each required finding as open → assigned → corrected → verified → independently closed, with exact owner/source/test/review references. If an audit reveals a gap or the first correction is incomplete, assign a bounded repair/research task to the best-informed implementation owner, obtain the correction, request the necessary re-audit, and repeat until evidence supports closure. A message sent or recommendation written is not a fix. Keep approved deferrals and genuine human decisions distinct from resolved defects.

The human permits focused subagents where useful. They may investigate a concrete issue or perform a bounded correction, but only with explicit task/file scope and the same Master-only/resource rules. If a helper writes, the owning implementer pauses its own edits so there remains one source writer. The independent end-stage auditor must not both author a correction and solely certify it; maintain an independent final check. Do not spawn agents or repeat whole audits merely for activity. Material product/scope/resource decisions still wait for the human; pursue ordinary authorized engineering fixes without another routine approval request.

## Gate D — accepted stage and local commit

After the meaningful required stage checks, independent audit and required-finding closure, commit only the exact reviewed stage paths under the human's conditional local-commit authority. Preserve unrelated pending content/modes/status and avoid broad staging/reset/stash/clean. Recheck reviewed source/after hashes before committing; verify committed blobs and parent/base after committing. Do not rerun unchanged complete suites merely because a commit was made. No push, merge, deployment, source-tree promotion or unrelated cleanup follows.

The accepted stage receipt records local commit/base, exact source/build/allowlist, test and audit receipts, closed/remaining findings, protected work/resources and any limits. A code commit alone does not prove a destination database migration or runtime activation occurred; record that separately and require its authority.

## Gate E — required downstream impact report and handoff

The end-of-stage auditor must append an impact report to its acceptance, even when there is no relevant change. Required fields:

| Field | Required content |
| --- | --- |
| Accepted state | Stage, actual Master commit/source and exact reviewed implementation/evidence versions. |
| Findings | Stable IDs, fixes actually made, disposition and residual limits; distinguish proposals from implementation. |
| Changed contracts | Concrete files/classes/methods, schemas/serialized shapes, write/lock/revision/cache/lifecycle behavior and compatibility assumptions. |
| Downstream matrix | Each affected remaining stage and owner, relevant finding IDs, expected impact, required plan/test/dependency changes and blocking scope. Explicit evidence-backed no-impact is valid. |
| Verification and resources | What passed, failed or remains unrun; constraints on tests, actual engines, private launchers, storage and exclusive resource windows. |
| Recommendation | Smallest next action and necessary research/review; genuine human decisions separated from ordinary engineering. |

Coordinator sends the actual relevant report/links and accepted source identity to every affected next owner, explicitly requiring a plan re-audit. Record successful dispatch AND owner acknowledgment; dispatch alone is not handoff completion. Ask a shared decision once and propagate its answer to all affected owners. If return messaging is permission-blocked, pull the owner's saved acknowledgment/updated packet through supported read-only tools; do not bypass the restriction or stall on an unnecessary reply.

## Gate F — next owner re-audits the plan

The receiving owner must read the report and inspect relevant actual current Master source. Reconcile its old assumptions and all relevant finding IDs; perform bounded research when the real change requires it. Update implementation plan, execution spec, reuse decision, exact paths/ordering, tests, environment/activation constraints and recovery only where affected. Return a versioned impact-to-plan mapping, revised hashes and real remaining decisions. Do not assume a link was reviewed or simply append a generic warning.

Master Integration Audit independently reviews material changes in this concrete delta and source baseline. Coordinator confirms required findings are accounted for, all mandatory plan corrections close, resource authority applies and the next writer has sole ownership. Only then may the next already-authorized slice start. Recheck material source drift immediately before code; changed assumptions reopen only their affected review. No routine repeated approval is needed for already settled/authorized behavior.

## Stops and scope

Stop affected work for unsafe/unknown target, lost permission, material source drift, unapproved dependency/scope, unresolved product choice or a real blocker. Preserve source/evidence and hold other writers. Do not silently change product behavior, borrow another target/window, reset shared state or equate source reversal with data/media rollback. Continue independent safe authorized preparation while a decision waits and report it in writing.

Resource authority comes from the human's named approvals and the corresponding operation receipts, not from this protocol. Consult the current coordination state for the applicable target, lifecycle and activation conditions. Closed windows remain historical evidence and are not reused. Images manager, richer Writing, matching and Apply remain bounded by the approved plans and decisions.
