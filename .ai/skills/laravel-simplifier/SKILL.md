---
name: laravel-simplifier
description: "Secondary simplification audit for PHP/Laravel changes. Invoke only after the ux-design-thinking skill has framed the work, and only when a simplification audit is explicitly wanted — never as the main development skill and never as an enforcement gate on ordinary implementation. When invoked: first a read-only dry-run audit reporting the smallest safe approach, then, after explicit approval, implement the approved scope."
---

# Laravel Simplifier

**Position (operator ruling 2026-07-31):** this is a secondary assistant, not
the main development skill and not an enforcer. It runs only after
`ux-design-thinking` has framed the product/UX shape of the work, and only
when a simplification audit is explicitly requested. Ordinary Laravel/PHP
implementation does not require this skill and is not gated by it.

When invoked, use it as a two-stage state machine. The invoked scope starts at
Stage 1. A valid, explicit approval for the current audit starts Stage 2.
Stage 1 ends the turn after reporting; it never flows directly into
implementation.

## Stage 1 — Read-only dry-run audit

Inspect repository instructions, plans, relevant code, tests, and recent
changes. Identify reuse, unnecessary abstraction, future-only scope, and the
smallest safe route to the requested outcome.

Do not edit or create files. Do not run generators, formatters, migrations,
package commands, database writes, storage writes, queue dispatches, or external
mutations. Do not begin implementation.

Avoid running tests during Stage 1 by default because tests can mutate database,
storage, cache, logs, or other state. Existing targeted tests are allowed only
when repository evidence confirms an isolated test environment and owned test
artifacts. Run them sequentially and recheck the worktree afterward. Otherwise,
schedule them for Stage 2.

### Choose a proportional audit

Use a compact audit for one localized, well-understood change with no schema,
dependency, authorization, security-boundary, or cross-surface impact. Include
all compact report fields below, but keep the report brief.

Use a full audit for cross-cutting work or any change involving schema,
dependencies, authorization, security boundaries, multiple surfaces, or
uncertain behavior. Approval is mandatory for both audit sizes.

### Classify scope before recommending it

Classify every meaningful requirement as one of:

- **Required now** — directly necessary for the requested outcome.
- **Required for safety or correctness** — necessary to avoid a concrete defect,
  regression, or security failure.
- **Optional robustness (excluded)** — potentially valuable, but not required
  for this implementation.
- **Future-only (defer or reject)** — preparation for hypothetical roles,
  panels, integrations, scale, or workflows.

Do not allow optional or future-only work into the recommended option merely
because it produces a more general or architecturally robust design.

### Complexity alarms

Expose the cost and offer a smaller option when a proposal introduces any of:

- a new dependency or subsystem;
- a new authorization architecture or security boundary;
- preparation for future roles, panels, integrations, or workflows;
- multiple migrations or persistent models;
- multiple independently shippable outcomes; or
- more than one bounded implementation task.

A bounded task has one user-visible outcome, one coherent code path, and one
final verification sequence. If the requested outcome cannot fit that boundary,
split options visibly rather than hiding the work inside a single estimate.

### Audit report contract

Every audit report must include:

1. **Audit ID** — a stable identifier such as `LS-YYYYMMDD-01`.
2. **Baseline** — branch, commit SHA, scoped worktree state, and relevant dirty
   files.
3. **Requested outcome** and observable acceptance condition.
4. **Current behavior and invariants** that must remain true.
5. **Requirement classification** using the four categories above.
6. **Reuse and complexity findings**, including triggered complexity alarms.
7. **Options** with stable option IDs, including the smallest safe option.
8. **Recommendation** and why larger alternatives are excluded.
9. **Projected change surface** — files, new classes, migrations, dependencies,
   bounded tasks, effort range, and confidence.
10. **Tests and quality gates** planned for Stage 2.
11. **Risks, assumptions, and deliberately deferred work**.
12. **Exact approval wording** referencing the Audit ID and Option ID.

For a compact audit, concise entries satisfy this contract. A valid outcome may
also be **no change** when the behavior already exists, the report identifies
designed behavior rather than a defect, or every available implementation would
add unjustified complexity.

End the turn after delivering the audit. Do not edit files in the same turn.

## Approval gate

Implementation requires a new user message after the audit, for example:

> Approved — implement Option A from Laravel Simplifier audit LS-20260717-01.

Approval is valid only when it references the current Audit ID and Option ID and
the audit remains available in the conversation. An initial implementation
request is not post-audit approval. If the audit is unavailable after context
loss or in a new task, rerun Stage 1 or ask the user to provide the report.

The audit becomes stale when:

- scoped files materially change;
- the branch or baseline commit changes in a way that affects the scoped work;
- requirements expand or acceptance conditions change;
- implementation needs an unreported dependency, migration, persistent model,
  public interface, security boundary, or subsystem; or
- projected tasks or effort would be materially exceeded.

Unrelated user changes do not invalidate the audit. Preserve them. If dirty
changes overlap the approved scope and ownership is unclear, stop and report the
conflict. A stale or expanded scope returns to Stage 1 for an amended audit and
new approval.

## Stage 2 — Approved implementation

After valid approval:

1. Recheck the audit baseline, scoped files, and worktree.
2. Follow the repository's normal research, planning, and verification rules.
3. Implement exactly the approved outcome and preserve behavior outside it.
4. Apply the simplification guidance below only inside touched, approved code.
5. Stop for an audit amendment if a material discovery changes scope or cost.
6. Perform a final simplification pass without adding adjacent cleanup.
7. Run the approved tests and repository quality gates.
8. Compare forecast with actual files, classes, migrations, dependencies,
   bounded tasks, effort, and deviations.

## Simplification guidance

Prioritize readable, explicit Laravel code over compact or generalized designs.

### Preserve the right behavior

- For feature or fix work, implement exactly the approved requested outcome and
  preserve all behavior outside it.
- For refactors and the post-change simplification pass, preserve functionality,
  outputs, and side effects exactly.

### Apply project standards

Follow repository guidance and established Laravel conventions:

- use proper namespaces and organize imports logically;
- prefer explicit return types;
- follow project patterns for controllers, models, actions, and focused classes;
- use established error-handling and naming conventions; and
- follow the installed framework and package versions.

### Enhance clarity

- Reduce unnecessary nesting, duplication, and abstraction.
- Reuse existing focused code before creating another layer.
- Use clear variable and method names.
- Consolidate related logic without combining unrelated concerns.
- Remove comments that merely restate obvious code.
- Avoid nested ternaries; prefer `match`, `switch`, or explicit conditionals.
- Choose clarity over fewer lines.

### Maintain balance

Do not:

- replace clear code with clever or dense expressions;
- combine too many concerns in one method or class;
- remove abstractions that provide a real boundary;
- create speculative extension points; or
- expand cleanup beyond the approved touched scope.

Document only significant changes needed to understand the result. Every new or
materially changed scope returns to Stage 1; a current approved audit proceeds
through Stage 2 without repeating the audit.
