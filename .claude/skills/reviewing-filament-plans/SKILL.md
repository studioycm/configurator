---
name: reviewing-filament-plans
description:
  Reviews implementations against supplied Filament Blueprint plans, tracing
  each requirement to code and verification evidence. Use when asked to check
  whether a Filament application, panel, or feature conforms to its plan or to
  find missing or mismatched implementation requirements.
compatibility: Requires filament/blueprint to be installed through Composer.
---

# Reviewing Filament Plans

Review the supplied Blueprint against its implementation. Produce a conformance
report, not a new plan, design review, security audit, or code fix.

The actual Blueprint and agreed amendments are the acceptance contract. Do not
import the planning skill's checklist, security catalogue, or personal design
preferences as additional requirements. Explicit authorization and tenant
promises remain in scope, including direct enforcement, not just hidden UI.

## 1. Establish the review target

Require the actual Blueprint text or files and an identifiable implementation
target. Record:

- Blueprint source and revision, authoritative amendments, and requested phase.
- Application checkout/revision and dirty state, including relevant uncommitted
  changes; selected panel and feature/resource scope.
- Resolved installed Filament/Laravel versions and package/class paths when API
  behavior affects a verdict. Composer constraints alone do not prove installed
  versions; account for implicated path packages and symlinks.
- Any explicit deferrals or exclusions. These are out of scope, not passes.

A comparison base/diff is needed only to claim what changed, not to review the
current feature. Update Blueprints specify only **Add / Modify / Remove**
deltas: omission is not a deletion requirement.

Do not silently choose among competing plans, treat code comments as agreed
amendments, or guess a phase, panel, permission mapping, or schema value. Mark
dependent requirements **Blocked**, ask the precise question, and continue
reviewing independent requirements.

## 2. Extract requirements before searching code

Read the supplied plan's headings, tables, labelled/fenced blocks, and prose
without rerunning the planner or requiring a different format. Build a ledger
before implementation searches:

1. Retain supplied IDs; otherwise assign `BP-001` onwards in source order. Keep
   IDs stable throughout the report. Deduplicate repeated obligations while
   retaining every source reference.
2. Split independently falsifiable clauses. Preserve entity/context, explicit
   component FQCN, mechanism, location, method/API semantics, route, exact
   string, schema/validation constraint, ability, condition, negative
   requirement, and ordered behavior steps. Do not reduce explicit constraints
   to general intent.
3. Classify each item as behavior, explicit implementation constraint, required
   test, or process/reference material. Keep required tests as separate
   obligations linked to the behavior they exercise.
4. Link each obligation to a plan section/line and short quote. Record
   exclusions and process/reference material separately so they do not inflate
   verdict counts.
5. Group related IDs into bounded lifecycle paths where supplied promises share
   state or context: hydrate → edit/react → validate → save → reload, authorize
   → mutate → notify, or context change → query/validate → write. Keep
   individual obligations traceable, but check their rules together before
   marking any affected item Conforms. Do not invent transitions or acceptance
   requirements unrelated to those promises.

Compare normalization, derivation, and authorization rules across each affected
path, including unchanged saves or returning to an earlier selection/context
where relevant. Identify the trusted inputs and expected outcomes at each stage;
do not assume locally conforming hooks compose into consistent behavior. Check
relevant lifecycle ordering against resolved framework source/docs, not generic
Laravel assumptions.

If plan rules conflict, mark the affected obligations **Blocked**. Cite the
concrete quote pair and a discriminating sequence with expected versus observed
or source-derived outcomes, labelling the evidence level. Ask which rule should
govern; do not demand impossible/broken code or silently choose a domain rule.
Continue independent checks. Source can establish a contradiction without a
runtime probe; unresolved runtime behavior remains **Not verified**.

Accept semantic equivalence where no explicit constraint is violated: aliases,
equivalent syntax, effective defaults, and extracted schemas/services may
conform. An explicitly mandated component, mechanism, or location still matters.
Docs URLs help interpret APIs; they need not appear in implementation code.
Scaffold commands seed checks for the promised artifacts and options, not proof
that a command ran. Never rerun generators. Migration files do not prove that a
database was migrated.

If an exact API is obsolete and replacements change behavior, block that
constraint pending clarification and report observed behavior separately. Do not
silently modernize the contract or demand broken code.

## 3. Trace only requirement-relevant implementation paths

For each requirement or group sharing an owner, start with its named location,
FQCN, entity, action, state path, or ability. Map Composer `autoload`,
`autoload-dev`, and classmap roots, then the selected panel/provider's explicit
registrations and discovery paths/namespaces. A matching class or label alone is
not proof that the required component is active in that panel.

Use this fallback sequence: supplied location → resolved imports/registrations →
filename lookup within mapped roots → exact declaration/reference search in
those roots. Confirm each candidate's namespace, model, and panel; do not select
the first filename hit. Use exact searches such as `rg -n -F` scoped to the
resolved roots/files. Never perform whole-app generic scans for terms such as
`status`, `form`, or `Action::make`, inspect `.env` or secret files, or expand
into an unrelated configuration/security audit.

Follow a dependency only when it can affect a named requirement:

| Owner                | Relevant effective path                                                                                                                                                                                                          |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Resource/schema      | Panel registration/discovery → resource → `form()`, `infolist()`, `table()` → delegated `configure()`/builders, nested callbacks, spreads, and state paths.                                                                      |
| Page                 | `getPages()` route/key/class or standalone page registration/discovery → relevant Create/Edit/View/List/custom-page overrides and reachability.                                                                                  |
| Action               | Planned placement and runtime name → record/header/toolbar/form/schema registration → action/subclass configuration → callback or invoked service.                                                                               |
| Relationship         | `getRelations()` or specified relationship field → manager/parent relationship and page context/visibility → relevant schema, table, and actions. Do not require a relation manager when an equivalent field satisfies the plan. |
| Authorization        | Planned model/ability → policy registration/discovery or custom mapping → ability, parents/traits, before/after hooks, resource overrides, and direct enforcement. Visibility is not authorization.                              |
| Model/data           | Resource model and relationship target → relevant casts, accessors/mutators, schema/migrations, scopes, observers, and read/write services.                                                                                      |
| Shared configuration | Relevant parent methods, traits/aliases, imported helpers, closures/captures, bindings, and provider-level `configureUsing()`/macros that change effective behavior or defaults.                                                 |
| Test                 | Explicit path, else owner FQCN/basename in configured test roots → exact action/state/ability/scenario tokens → setup, datasets, helpers, and assertions.                                                                        |

Reuse shared evidence. Stop when the effective path establishes the obligation
or a concrete discrepancy, or further dependencies cannot affect it. Do not
impose arbitrary file/depth limits. Batch large plans and retain the complete
ledger, inspected boundaries, and unresolved dependency frontier across batches.
Never silently truncate, sample, or claim unreviewed scope was covered.

A search miss is **Not verified**, not automatically Missing. Before confirming
absence, resolve ownership and check relevant registration, composition,
inheritance, and delegation paths. If an unread observer, override, dynamic
binding, or unavailable package could supply the behavior, record the last
verified edge, searches attempted, and exact next evidence needed.

## 4. Assess evidence and verify safely

Keep the conformance verdict separate from its evidence: **source inspected**,
**test assertions inspected (not run)**, **executed tests**, and
**browser/runtime observed**. State which supports each conclusion. Passing
tests do not override a confirmed source mismatch; test/bootstrap failure is not
itself a behavior deviation.

For every required test, inspect **setup → entry point → assertion**, including
panel, user, records, datasets, and mocks. Ask: would a plausible wrong
implementation pass?

- Notifications, redirects, page loads, and form state do not prove persistence.
- Inclusion does not prove exclusion; use mixed-owner records for scoping.
- Aggregate record counts do not prove the correct owner/relationship identity.
- Any validation error does not prove the named rule. Check discriminating
  boundaries, such as 255 accepted and 256 rejected for a maximum of 255.
- Symmetric data can conceal wrong context, ownership, derivation, or timing.
  Check whether distinct values and times would distinguish the promised rule
  from the plausible wrong implementation.
- Filling only final form state does not exercise intermediate reactive updates;
  a helper result does not establish preview/save/reload consistency. Recommend
  a bounded lifecycle check only where connected plan promises require it.
- A test name, coverage percentage, mocked-away service, or expectation copied
  from the implementation does not prove the promised outcome.

A demonstrably weak explicitly required test is **Deviates — Mismatched**; a
required test confirmed absent after resolving its relevant paths is **Deviates
— Missing**. An unresolved test search is **Not verified**. Neither test
deviation automatically proves the behavior absent. Recommendations for
additional verification do not become new acceptance requirements.

Run the smallest relevant **existing** tests only after inspecting their
bootstrap, effective test database/configuration, and side-effect integrations.
Establish an isolated disposable database/data and isolated or faked mail,
queues, storage, and external APIs without reading secrets. If safety cannot be
established from non-secret configuration and available evidence, do not run;
request an isolated harness or confirmation of its configuration.

Review does not authorize application edits, temporary code probes, generators,
dependency installation/update, standalone database resets/migrations, shared
database writes, real notifications/payments, or other external side effects.
Existing test lifecycle resets/migrations (such as `RefreshDatabase`) are
allowed only against the verified isolated disposable test database under the
safeguards above. Do not perform otherwise unauthorized actions to unblock
verification. Preserve static findings and report the narrower execution
limitation.

Use browser/runtime checks when JS/reactivity, interaction, appearance, or
uncertain runtime wiring determines the verdict. Exercise affected non-default
states using the same safety checks. Inspect rendered captures for appearance;
use DOM/interaction assertions for behavior. Screenshots do not prove
persistence. If runtime is unavailable or unsafe, mark the dependent obligation
**Not verified** rather than extrapolating from a helper test.

Record exact executed commands, selected cases, outcomes/counts/skips, decisive
output, and relevant environment/version. Distinguish proposed commands from
executed ones; do not claim execution from reading tests.

## 5. Assign verdicts and report

| Verdict               | Meaning                                                                                                                                            |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Conforms              | Evidence establishes the requirement, including explicit constraints, for the reviewed target. State the evidence level; execution is not implied. |
| Deviates — Missing    | A required element is absent after ownership and relevant effective paths have been resolved and checked.                                          |
| Deviates — Mismatched | The effective implementation or required test contradicts the requirement.                                                                         |
| Blocked               | Contract ambiguity, conflict, or stale requirements prevent a verdict.                                                                             |
| Not verified          | Implementation/environment evidence is insufficient, including unresolved dependencies or unreviewed scope.                                        |

Return these sections:

1. **Scope and result:** Blueprint identity/revision/amendments/phase,
   implementation revision/dirty state, panel and coverage, exclusions, and
   counts of in-scope requirements by verdict (Missing/Mismatched are deviation
   subtypes). Counts must reconcile with the ledger; count consolidated findings
   separately. Lead with user-visible behavior defects and contract blockers,
   then required-test gaps and mechanical details. Distinguish these categories
   prominently; a behaviorally equivalent but explicitly prohibited structure,
   hook, or API is still a contract deviation, not automatically a behavior bug
   or something to waive. Identify any batches or scope still unreviewed.
2. **Findings and unresolved requirements:** consolidate shared causes into
   stable finding IDs linked to all affected requirement IDs. Label each as a
   behavior defect, required-test weakness/absence, explicit structural/API
   contract deviation, or Blocked/Not verified with the reason. Include the plan
   quote/reference, expected versus observed outcome, precise locations or
   documented absence, and smallest corrective direction or clarification.
   Suggest safe, independently derived discriminating verification without
   expanding review authority. Do not write full remediation code or redesigns.
   For Blocked/Not verified, state the unresolved frontier and exact next
   evidence/question; include conflicting quote pairs for contract conflicts.
3. **Complete requirement traceability:** use the compact ledger below,
   optionally as an appendix. Preserve plan references and linked behavior/test
   IDs. Cite code/test `file:line` evidence and concise verdict reasoning, or
   reference the consolidated finding instead of repeating it. For absence, cite
   searched roots/symbols and checked effective paths. Include execution
   commands/results once and reference them. Do not duplicate the full ledger in
   prose or omit obligations to shorten the summary.

| ID / kind / linked behavior     | Blueprint reference and requirement                | Verdict               | Code/test evidence and reasoning                                 | Verification or next evidence                                    |
| ------------------------------- | -------------------------------------------------- | --------------------- | ---------------------------------------------------------------- | ---------------------------------------------------------------- |
| BP-001 / behavior               | Blueprint §Approve: persist current `confirmed_at` | Not verified          | Approval service inspected; model observer write path unresolved | Inspect the registered observer before judging absence           |
| BP-002 / required test / BP-001 | Blueprint §Tests: assert saved approval timestamp  | Deviates — Mismatched | Cited test invokes approval but asserts only notification        | Assert the refreshed record's timestamp using a controlled clock |

Do not claim complete conformance while any scope Deviates, is Blocked, is Not
verified, or remains unreviewed. “No confirmed deviations in reviewed scope” is
not a full pass.
