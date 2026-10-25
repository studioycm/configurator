# Implementation capabilities

Use this optional reference when evaluating a UX recommendation against an
established implementation stack. Derive the user outcome and product contract
first. Assess implementation options without reshaping the user's goal merely
to fit a convenient component.

This is a read-only feasibility assessment. It does not authorize framework
selection, new architecture, scaffolding, dependencies, or implementation.

## Establish the available capabilities

Confirm the technologies and versions actually present. Inspect relevant
existing components, state ownership, save boundaries, and supported extension
points before recommending custom behavior.

Use only the technology-specific considerations relevant to the current
product:

- **Laravel:** Identify existing owners of authorization, validation, domain
  decisions, and persistence. Separate interface feedback from authoritative
  operations. Do not prescribe a new Service, Action, DTO, or writer layer
  merely because those patterns are available.
- **Livewire:** Review server interactions, reactive dependencies, component
  identity, and preservation of input and focus across updates. Distinguish
  request timing, validation timing, and persistence. Do not assume every
  reactive update validates or saves.
- **Alpine:** Consider local presentation, disclosure, focus, and transient
  interaction state. Keep purely local interactions local when appropriate.
  For server-backed operations, verify consistency with the authoritative
  server decisions and behavior across re-renders.
- **Filament:** Inspect suitable native fields, schema layouts, action forms,
  tables, pickers, and navigation capabilities before proposing replacements.
  Establish which product-specific decisions and gates still require
  application behavior. Field count alone does not justify a Wizard.

## Verify implementation advice narrowly

Use relevant maintained implementation or research guidance when available.
Treat existing project skills and historical research as context, not proof
of current correctness. Verify claims material to the recommendation against
current contracts, code, versions, and operator decisions. Report
contradictions instead of inheriting stale rules.
Check version-sensitive APIs against official documentation and installed
source. Report missing access or evidence. Do not copy framework manuals into
this skill or expand a UX review into an unrelated structure audit.

When consulting the shared Filament 4/5 examples collection, retained `v4/`
paths remain eligible. Check relevant custom Livewire 4 behavior, JavaScript
lifecycle, and plugin/API requirements. Ordinary shared patterns do not
require a separate v5 folder or Composer manifest.

## Use design and interaction references appropriately

Use relevant frontend-design guidance for visual hierarchy, typography,
density, and outcome-oriented copy after understanding the workflow.

Use relevant Alpine guidance when evaluating local interaction behavior.
External reference material does not become an amendment target or authorize
its modification.

Preserve the product's established visual language and the current request's
scope. Consulting a reference does not independently authorize visual
ideation, building, or sharing.

## Development boundaries

Concurrent editing sessions in one worktree, independent test runs sharing
resources, and parallel workers within one test suite are distinct concerns.
This UX skill does not define their guards or transfer another project's
execution restrictions.

## Handoff

For a material recommendation, report:
- the user outcome and protected product contract;
- suitable existing or native capabilities;
- necessary application-specific behavior and its current owner;
- version or evidence gaps;
- relevant interaction and failure-path checks.

Recommend the smallest supported implementation direction. Keep proposed
checks separate from verification actually performed.
