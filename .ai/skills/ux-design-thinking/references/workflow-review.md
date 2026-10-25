# Workflow review

Use this reference for a bounded workflow review and for applicable decisions
within the full design process. Preserve the current product contract and
task authorization. Derive terminology, controls, and state from current
evidence rather than importing another product's workflow.

Apply only checks supported by the reviewed product. Mark material unknowns
explicitly. Every finding cites evidence or is labeled as an inference.

Preserve the skill's operator inquiry loop. Ask only questions whose answers
could materially change the interpretation or recommendation.

## 1. Name the outcome

Check whether names explain the user's object, decision, or intended result.
Use consistent verbs for the same action and distinct language for materially
different effects.

"Selected" can describe inclusion in a set. It does not, by itself, explain
what will happen to that set. Name the intended action separately when the
product has one.

Distinguish proposing, checking, preparing, and applying only when those
operations actually exist.

## 2. Establish supported treatment policies

When the product offers a consequential choice, establish:
- the affected scope;
- the supported treatment;
- the material content retained, added, replaced, removed, or omitted;
- dependencies and prerequisites;
- when the result takes effect.

Distinguish supported capability, current permission, unavailable capability,
and unknown behavior. Offer only established choices. An unsupported remedy
is a capability gap or contract-change proposal, not an available resolution.

Use single-choice controls for mutually exclusive treatments and multiple-
choice controls for independent inclusions. Explain material defaults and
what leaving a choice unset means. A single supported remedy may be a named
action rather than an artificial menu.

## 3. Group by the user's decision

Group controls that answer one question or jointly determine an outcome.
Use descriptive headings and appropriate semantic grouping. Preserve logical
reading and keyboard order across relevant screen sizes and locales.

Keep dependencies understandable when content is collapsed or placed in
another container. When choices become hidden or inapplicable, establish
whether their values are retained, cleared, or excluded.

Treat field counts and visual balance as inspection prompts. Do not move
controls away from their conceptual group merely to equalize height.

## 4. Distinguish interaction and state meanings

When inspection and batch selection coexist, provide distinguishable controls.
Opening details, moving focus, or navigating must not silently change batch
inclusion or treatment. Preserve useful return context.

When they exist, distinguish inclusion, intended action, unresolved decisions,
check results, and execution state. These are meanings to clarify, not
mandatory columns or a universal status model.

A successful check does not explain the planned action. An approved replacement
or removal is not automatically an unresolved conflict.

## 5. Explain blockers and supported ways forward

For each blocking issue, establish:
- the affected object or scope and precise cause;
- evidence supporting the finding;
- the consequence if the proposed action proceeds;
- supported resolutions, or an explicit statement that none is available;
- the exact action or transition blocked and the condition that releases it.

Name a blocking rule only when the contract or observed behavior supports it.
Label a newly recommended blocking rule as a proposal.

Distinguish input errors, incompatible choices, missing evidence, permission
restrictions, and execution failures. A generic "Conflict" badge or disabled
button is insufficient explanation.

Keep the reason and supported next action discoverable through keyboard and
assistive technology. Communicate important dynamic status changes accessibly.
Identify interaction checks that were not performed.

## 6. Review stages and approval dependencies

When a staged process exists, establish each stage's purpose, prerequisites,
output, forward gate, and return path. Explain what navigation, cancellation,
and relevant interruptions preserve.

When checks or approvals exist, identify what they cover and which relevant
changes invalidate them. Preserve unaffected decisions and require renewed
review or validation for affected results. Browsing or inspecting alone does
not invalidate approval.

Keep prepared state separate from applied state when the product makes that
distinction. Do not invent approval gates, recovery promises, or a fixed
number of stages.

Product approval controls and the operator's authorization to implement a
recommendation are separate decisions.

## Deliverable

Report:
1. Reviewed scope and evidence.
2. Material findings, each with the user consequence, evidence or inference,
   exact proposed correction, and an observable acceptance check.
3. Unknowns, unsupported remedies, and coverage limits.
4. Checks actually performed, separately from proposed verification.
5. Read-only status and recommendations awaiting operator review.

Report a clean result when no material issue is supported. Do not manufacture
findings to fill the checklist.
