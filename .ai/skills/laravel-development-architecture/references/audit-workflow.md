# Structure audit workflow and deliverable

## Contents

- Establish the current snapshot
- Trace responsibilities
- Assess and report
- Plan only at the requested stage
- Preserve research

## Establish the current snapshot

Confirm audit/implementation scope. Record checkout path, timestamp, HEAD, branch, dirty state and installed versions. Do not infer current behavior from an old report or a different checkout. Read AGENTS/path rules and explicitly named project handoffs. Enumerate relevant PHP plus Blade/JS/config/routes/tests/CI where they affect the requested architecture; avoid vendor/generated code except for framework verification. A requested whole-app audit needs an inventory and coverage/gaps; a focused review must not expand into unrelated work.

Use `rg --files` and targeted searches to seed inspection. Search results are leads, not findings. No global `helpers.php` does not establish the absence of helper-like responsibilities. A file count, line threshold or string match cannot establish poor architecture.

## Trace responsibilities

For each candidate, inspect actual methods and callers: input, actor/tenant, reads, writes, external effects, cache/lifetime, output and UI coupling. Trace constructor injection, bindings, framework callbacks, strings/config, route/listener/command/component discovery, inheritance, traits and serialized references when applicable. No direct caller found is narrower than unused. A method with only test callers does not make its entire class legacy.

Check direction: UI/transport may call application operations; application/domain writes should not require UI factories or HTTP request lifecycles. Models may retain coherent persistence behavior. Identify the authoritative writer, compiler/DTO/snapshot/signed-payload contracts and every competing write path before proposing a split.

Challenge each extraction with the smallest alternative: retain code, rename only, move only, extract a method, reuse existing type, narrow a patch, or split a responsibility. A large class or static factory alone is not a defect. Preserve good examples explicitly so the next phase does not dismantle them.

## Assess and report

Each actionable finding records:

- Stable ID; priority based on impact and evidence, not pattern preference.
- Status: confirmed source behavior, reproduced runtime defect, supported risk, unresolved question, or optional organization/style.
- Current file/method and verified line; callers/trigger, observed responsibility leak and practical consequence.
- Evidence excerpt or trace; distinguish facts from inference, and include current source/version.
- Smallest recommendation and alternative; exact proposed file/FQCN map when supportable, marked proposed.
- Dependencies, protected contracts, migration/registration implications and compatibility concerns.
- Meaningful tests/checks that would verify the proposed benefit, and checks actually run/unrun.

Do not report inconsistent lock order as a reproduced deadlock. Do not call signed/authorization code a bypass without a demonstrated path. Do not infer performance from repeated SQL patterns alone: measure the relevant workload, server/database and authenticated browser separately when needed. Do not transform a cosmetic namespace preference into a correctness priority.

Preserve original finding IDs when reconciling audits. Mark each prior claim retained, narrowed, superseded, resolved or unverified; show lineage. Keep duplicate observations from different reviewers as corroboration, not inflated finding counts. Do not silently replace historical original answers.

Independent reviewers can split coherent domains; merge centrally and challenge the strongest claims. Record scan coverage, excluded paths and evidence gaps. Report test execution honestly: proposed tests are not passed tests; a successful class-load is not browser acceptance or concurrency proof.

## Plan only at the requested stage

If the user wants findings for review first, finish recommendations and stop before the detailed implementation plan/refactor. If a plan is authorized, enumerate phases in dependency order with current files, target paths/FQCNs, method/input/output changes, provider/discovery updates, contracts, tests, rollback/compatibility and acceptance gates. Cosmetic movement follows clarified responsibility boundaries unless a move is independently useful.

Implementation approval is separate from audit/plan approval when the user sets that boundary. Do not run generators during an audit just to make a proposal concrete. Never delete tests/classes/dependencies based solely on no direct references.

## Preserve research

Only save documents when requested or required by the established repository handoff. For a requested archive, distinguish original user-facing answers, raw available tool calls/results and derived recommendations. Exclude hidden reasoning, system/developer instructions, credentials and irrelevant private browsing/account data. Preserve recorded truncation markers; do not reconstruct missing historical output and label it raw.

Keep subscription/source bodies and sensitive returned payloads out of reusable skills and repository docs. A private archive still needs explicit scope, access restrictions, provenance, redactions if necessary and hashes. A future scan should receive a repository-safe consolidated handoff plus a private archive pointer where authorized, not the entire session log by default.
