---
name: ux-design-thinking
description: "Use when Codex must understand, audit, or redesign a product, service workflow, interface family, information architecture, or terminology before implementation, especially when surfaces mix user jobs, important questions are unanswered, product contracts constrain the design, or more stakeholder and user discovery is needed."
---

# UX Design Thinking

Determine what should exist before designing screens or implementation. Derive
the design from operational contracts, observed evidence, focused operator
inquiry, and the questions users actually bring to each surface.

The full process is a read-only design stage. It ends with an evidence-backed
design dossier and an explicit request for operator review or phase selection.
It does not authorize implementation.

## Domain-neutrality guard

- Begin every task with an empty domain vocabulary. Derive objects, actors,
  posture verbs, state axes, and surface boundaries from current evidence.
- Do not import nouns, lenses, lifecycle stages, status models, or screen
  patterns from another product, a prior task, or the worked example.
- Require a concept only when the current product actually has it. Read-only
  products need no invented commit, pending, cancellation, recovery,
  destructive-action, or lifecycle model.
- Treat persistence, staged changes, asynchronous work, permissions,
  destructive effects, and recovery as conditional branches, not universal
  characteristics of an interface.
- If an example makes a candidate concept easier to imagine, return to the
  current evidence and prove that the concept exists before using it.

## Non-negotiable boundaries

- Treat the operator's kickoff as the task contract.
- Follow active domain contracts, then current source and tests, then observed
  product behavior, then this method.
- Stop and expose conflicts rather than designing around them.
- Keep Stages 0–8 read-only: inspect, browse, open, and cancel; never submit,
  mutate data, change files, install dependencies, or begin implementation.
- Use isolated, already-safe evidence only when repository rules permit it.
- Record an evidence gap when a state cannot be inspected safely. Never fake
  coverage.
- Keep design artifacts outside the repository unless the operator asks to
  write or commit them.
- After operator approval, route the chosen phase into the normal research,
  planning, TDD, simplification, and verification workflow.

For a full redesign or design audit, complete every stage and pass every exit
test. For a narrow implementation review, run the minimum Stage 0–2
orientation needed for evidence, apply the Stage 4 lens linter, and label the
result as a lens-lint review rather than a complete design audit.

## Operator inquiry loop

Treat operator questions as a first-class evidence source, not a preliminary
questionnaire. Run this loop before each stage:

1. Record what is **confirmed**, **observed**, **operator-reported**,
   **inferred**, and **unknown**.
2. Inspect available artifacts before asking. Never ask the operator for
   something Codex can discover safely.
3. Identify only unknowns whose answers could change a contract, user model,
   lens, screen, or phase.
4. Ask one focused, non-leading question at a time by default. Use a grouped
   interview packet only when the operator requests asynchronous questions.
5. Prefer a concrete recent incident over a preference:
   "Walk me through the last time this happened" is stronger than
   "Would you like a dashboard?"
6. State briefly why the answer matters or which decision it will affect.
7. Update the relevant artifact after every answer. If the answer contradicts
   source or observed behavior, record the contradiction instead of silently
   choosing a side.
8. Do not pass an exit test with a critical unknown. For a non-critical unknown,
   state a conservative, reversible assumption and its risk.

Questions expand understanding; they do not outsource synthesis. Do not ask
the operator to invent the information architecture, lenses, or solution.

### High-leverage question families

Select questions from these families only when the evidence leaves a relevant
gap:

| Area | Questions to adapt |
|---|---|
| People | Who actually performs this work? Who is affected but absent? How experienced are they? |
| Jobs | What outcome brought the person here? What happened the last time? How often, and what is at stake? |
| Operations | What triggers the work? Where does it start and end? What changes, verifies, hands off, or returns? If state changes, what commits, cancels, fails, or recovers it? |
| Interfaces | What question is in the person's head on arrival? What do they ignore, avoid, or open next? |
| Language | What words do people use aloud? Which labels are confused or dangerously similar? |
| Context | Which locale, direction, device, interruptions, accessibility needs, and real data shapes matter? |
| Scale | How many people, records, roles, and exceptions exist now? Which growth is real rather than hypothetical? |
| Success | How would the operator verify improvement? Which failure would make the redesign unacceptable? |
| Delivery | What can ship independently? What capacity, risk, or contract change constrains the first phase? |

Do not ask every question. Stop when another answer would no longer change the
design materially.

## Evidence and traceability

Assign stable evidence IDs such as
`E###__SURFACE__STATE__locale__size`. Attach each material finding to evidence
or mark it as an inference. Phrase friction as an operator consequence, not a
visual adjective.

Use these confidence labels consistently:

- **Confirmed:** guaranteed by a contract, source, or test.
- **Observed:** directly visible in a safe walkthrough.
- **Operator-reported:** stated by the operator or another identified user.
- **Inferred:** synthesis from evidence; state the reasoning.
- **Unknown:** unresolved and material enough to track.

## Stage 0 — Ground truth

**Goal:** Know what the system guarantees before judging the interface.

**Work:**

- Identify the applicable source-of-truth, decision, and state boundaries for
  each job. Do not assume every job changes state or has a separate authority.
- Record only behavior that exists in the current product. For read-only jobs,
  this may be source, freshness, availability, fallback, and navigation
  behavior. For state-changing jobs, it may additionally include proposed
  state, commit timing, cancellation, failure, reversibility, permissions, and
  safety.
- Mark an inapplicable contract dimension as absent; do not invent a workflow
  merely to fill a template.
- Separate behavior the interface may clarify from behavior that would require
  a contract change.
- Do not critique screens yet.

**Operator inquiry:** Ask only about undocumented operational behavior, real
exceptions, or conflicting expectations. Match the question to the observed
job. For a read-only surface, ask which source makes an answer true and how
staleness or unavailability appears. For a state-changing surface, ask exactly
what changes, when it becomes authoritative, and what happens if the attempt
is abandoned or fails.

**Artifact:** Contracts table:

| Job | Object or question | Source of truth or decision owner | Read or transition contract | Timing or freshness | Exit or failure behavior | Evidence |
|---|---|---|---|---|---|---|

**Exit test:** Every later design decision cites a contract row or is flagged
as contract drift requiring separate approval.

## Stage 1 — Understand people, jobs, and operations

**Goal:** Model specific humans and working rhythms rather than an abstract
"admin."

**Work:**

- Build the model from kickoff wording, real product data, observed
  verification routines, navigation, support history, locale, and operator
  answers.
- Distinguish materially different user groups. Do not compress them into one
  persona merely because they share a role name.
- Describe jobs as outcomes in the user's language, never as features.
- Weight jobs by frequency and stakes. Give the highest-value jobs the most
  design attention.
- Map each current surface to at least one job.

**Operator inquiry:** Ask for the most recent real example, trigger,
interruptions, handoffs, verification habit, and consequence of failure.

**Artifacts:**

- Evidence-based user/operator profile; label gaps rather than inventing
  biography.
- Weighted jobs table:

| Job in the person's words | User | Trigger | Frequency | Stakes | Entry point | Verification |
|---|---|---|---|---|---|---|

**Exit test:** Every in-scope surface maps to a job. A surface mapping to none
is a finding.

## Stage 2 — Walk the product and audit its questions

**Goal:** Capture observed reality in the user's context.

**Work:**

- Walk the product read-only in the primary locale first, then supported
  alternatives.
- Inspect resting states, primary transitions, desktop, and relevant narrow
  sizes using real content shapes.
- Capture screenshots or precise textual evidence for each state.
- At every surface, compare the user's incoming question with what the
  interface actually answers.

**Operator inquiry:** Ask what brought the person to the surface, what they
need to know before acting, what they check next, and which surfaces they avoid
or work around.

**Artifacts:**

- Evidence atlas.
- Question audit:

| Surface/state | Question the user brings | What the interface answers | Consequence | Evidence |
|---|---|---|---|---|

- Friction list tied to evidence IDs and operator consequences.

**Exit test:** A reader unfamiliar with the product can reconstruct each
screen's information and action hierarchy from the atlas alone.

## Stage 3 — Audit language as behavior

**Goal:** Find words that create ambiguity, conceal impact, or expose system
vocabulary.

**Work:** Inventory every in-scope user-facing term in every supported locale.
Find:

1. one word naming several things;
2. near-twin words naming actions with different blast radii;
3. internal, storage, status, or cross-context vocabulary leaking where user
   language belongs.

**Operator inquiry:** Ask what people call the objects and actions aloud,
which terms require explanation, and which similar verbs have caused mistakes.

**Artifact:** Vocabulary defect table:

| Term | Surface | Locale | Defect class | Consequence | Proposed distinction |
|---|---|---|---|---|---|

**Exit test:** Every high- or medium-impact finding fixable by naming alone is
marked `language-first`.

## Stage 4 — Derive the product lenses

**Goal:** Separate domain objects from user postures, then compress the
postures into a coherent mental model.

**Work:**

1. List the real domain objects. Do not let the current navigation invent
   objects that the domain does not contain.
2. Derive posture verbs directly from the weighted jobs and question audit.
   Do not seed the list from stock verbs or a worked example.
3. Compress related postures. Use `3±1` only as a pressure toward simplicity,
   never as a quota. Keep two or five lenses when the evidence demands them.
4. Map every job to one primary lens plus named transitions.
5. Name lenses with short, parallel human verbs.
6. Write the smallest container sentence that makes the shared object and
   distinct postures obvious. "One X, N lenses" is a possible test form, not
   required copy.
7. Assign each surface to exactly one primary lens. Treat a surface serving
   incompatible lenses as a finding.

**Operator inquiry:** Test the emerging model without leading:
"When you do these two jobs, do they feel like one sitting or two?"
"What would you call this action without using the interface label?"
Ask the operator to challenge missing jobs, wrong boundaries, and unnatural
verbs—not to choose from a prewritten taxonomy.

**Artifact:** Lens map:

| Lens verb | Primary question | Jobs | Surfaces | Success or exit condition | Transitions and return paths |
|---|---|---|---|---|---|

**Exit test — lens linter:**

- Each lens answers one primary question and has an explicit success or exit
  condition.
- Every action serves the verb of its containing lens.
- Each surface has one primary lens.
- A read-only lens does not acquire invented persistence or commit semantics.
- When staged state really exists, it is not represented by competing commit
  paths.
- Transitions and return paths are explicit.
- No lens name or count was copied from a worked example without fresh
  evidence.

## Stage 5 — Derive traceable principles

**Goal:** Turn recurring friction into product-specific design principles.

**Work:**

- Cluster Stage 2 frictions.
- Derive six to eight principles when the evidence supports that many.
- Name each principle and phrase it as an imperative.
- Tie every principle to at least one friction/evidence ID and one protected
  contract.
- Delete or sharpen anything that could be pasted unchanged into any product.

**Operator inquiry:** Ask which consequences are intolerable, which trade-offs
are acceptable, and where clarity, speed, reversibility, or safety must win.

**Artifact:** Principle cards:

| Principle | Frictions/evidence | Contract protected | Design consequence |
|---|---|---|---|

**Exit test:** Principles and findings form a coverage matrix with no orphan
finding.

## Stage 6 — Design answer-first screens in product fidelity

**Goal:** Make screens answer the lens's primary question before asking for
action.

**Per-screen recipe:**

1. Lead with what is showing now and why.
2. Order information by the user's question sequence, not schema order.
3. Match the interaction model to the actual contract: read-only navigation,
   immediate change, staged change, delegated decision, or asynchronous work.
   Do not invent persistence, confirmation, retry, or recovery.
4. When staged change exists, represent proposed state visibly and provide one
   authoritative commit path.
5. When an action is consequential, destructive, or difficult to reverse,
   separate it from routine work and explain impact before commitment.
6. When facts have independent sources or meanings, present them separately
   rather than compressing them into one ambiguous status.
7. Preserve named transitions, exits, and return context where the job needs
   continuity.

Use the product's actual language, direction, visual theme, density, content
shapes, devices, and accessibility constraints. Wrong-locale or generic
grayscale mockups can hide the defects being tested. Number annotations and
tie them to findings and principles.

**Operator inquiry:** Confirm real content examples, device and environment
constraints, accessibility needs, unavoidable density, and how the operator
would run the top job through the proposal.

**Artifacts:** Annotated product-fidelity screens and a payoff comparison for
the highest-weighted jobs.

**Exit test:** Re-run the lens linter on every screen. Walk the top jobs and
compare questions answered, interactions, applicable state transitions, and
exit or failure behavior against today.

## Stage 7 — Run an adversarial and right-sizing pass

**Goal:** Attack the proposal before recommending it.

**Work:**

- Compare any parallel audit or alternative honestly.
- Record what to adopt, what to steer away from, and why.
- Ask what was overbuilt for the actual team, data, permissions, and frequency.
- Ask which user question still lacks an answer.
- Preserve future seams through naming or information architecture; do not add
  fake controls or build hypothetical end states.

**Operator inquiry:** Test scale assumptions, operational appetite, failure
cost, and what would feel like excessive machinery.

**Artifact:** Adopt/steer table plus applied design deltas:

| Proposal | Adopt or steer | Evidence/contract/scale reason | Applied delta |
|---|---|---|---|

**Exit test:** Every steer-away has a reason grounded in evidence, contract, or
actual scale.

## Stage 8 — Sequence by impact over effort

**Goal:** Produce independently valuable phases that teach the next phase.

**Work:**

- Sequence by weighted user impact, dependency, risk, and learning value; do
  not reuse a stock build order.
- Put language first only when wording is itself blocking or dangerous. Put
  interaction-state corrections first only when the current workflow has
  those states.
- Give each phase one user-visible outcome over existing authorities.
- Prefer the smallest surface that can validate the proposed model. Delay a
  custom workspace or structural rebuild unless current evidence shows it is
  already necessary.
- Estimate the actual seams in honest hours. Reduce or split a phase whose
  estimate signals that the outcome is not bounded.
- Name schema, dependency, authority, security, or contract changes as drift
  and gate them separately.

**Operator inquiry:** Confirm delivery capacity, unacceptable regressions,
review checkpoints, and which independent outcome should be proven first.

**Artifacts:** Phased plan, top-job payoff comparison, assumptions register,
and drift register.

**Exit test:** Removing any later phase leaves every earlier phase useful.

## Lens linter quick reference

Use this during the full process and during later implementation reviews:

| Check | Failure signal |
|---|---|
| One primary lens per surface | A page combines unrelated jobs with different success conditions |
| One primary question | The heading and first content answer different jobs |
| Outcome semantics match the contract | A read-only job gains a fake commit, or a real state change has no authoritative completion path |
| Actions serve the lens verb | Secondary administration dominates the surface's primary job |
| Conditional state change stays coherent | A real staged change has competing commit paths or hides proposed state |
| Independent facts stay separate | Facts with different sources or meanings are collapsed into one ambiguous status |
| Named transition and return | A user leaves context and cannot resume the original job |
| Contract-aligned language | Copy implies persistence or safety the engine does not guarantee |
| Evidence-backed scope | A control exists only for hypothetical scale or roles |

## Anti-patterns

1. **Feature-first research:** Inventorying controls instead of user questions.
2. **Questionnaire dumping:** Asking every generic UX question before reading
   available evidence.
3. **Asking discoverable questions:** Making the operator restate source,
   documentation, or visible interface facts.
4. **Leading for agreement:** Asking whether the operator likes the model
   Codex already chose.
5. **Invented persona:** Filling evidence gaps with biography or preferences.
6. **Critical-unknown bypass:** Drawing screens while a contract, user, or
   operational fork remains unresolved.
7. **Collapsed facts:** Combining independent facts into one status merely
   because they appear on the same surface.
8. **Invented or competing completion:** Adding save/submit semantics to a
   read-only job, or offering multiple ways to commit one real staged change.
9. **Verb collision:** Giving similar labels to actions with different impact.
10. **Aggregate-first entrance:** Leading with a broad combined view before
    evidence shows it answers the primary job.
11. **Enterprise entrance:** Designing a workbench for hypothetical scale
    instead of the present operation.
12. **Padded estimates:** Estimating fear rather than bounded seams.
13. **Wrong-fidelity mockups:** Using the wrong locale, direction, content, or
    device and therefore missing real defects.
14. **Placeholder futures:** Showing disabled or fake capabilities instead of
    reserving clean seams.
15. **Evidence inflation:** Claiming a state or user need that the evidence
    does not show.
16. **Example copying:** Reusing another product's lenses, labels, principles,
    or screen structure as if they were universal.

## Deliverable contract

The operator receives:

- [ ] contracts table;
- [ ] user/operator model and weighted jobs table;
- [ ] operator inquiry and assumptions register;
- [ ] evidence atlas, question audit, and friction list;
- [ ] vocabulary defect table;
- [ ] product-specific lens map and container sentence;
- [ ] traceable principle cards and coverage matrix;
- [ ] annotated product-fidelity screens and payoff comparison;
- [ ] adversarial adopt/steer table;
- [ ] phased plan with honest hours and drift register;
- [ ] unresolved questions and evidence gaps;
- [ ] explicit statement that the work was read-only, nothing was implemented,
      and operator phase selection is required.

## Worked example

The optional
[Media Operations worked example](references/media-operations-worked-example.md)
is intentionally domain-specific. Do not read it as startup context for a new
task. Read it only after the current task's objects, jobs, questions, and
candidate model have already been derived and a concrete contrast would
clarify the reasoning sequence. It is not a source of reusable lenses, labels,
state axes, principles, screens, or phase order.

## Common mistakes

- Treating `3±1` as a required number instead of a compression heuristic.
- Naming lenses before the jobs and question audit exist.
- Treating lenses as navigation tabs automatically.
- Confusing operator statements with verified system behavior.
- Asking preference questions when an incident would reveal the real need.
- Stopping inquiry because the first answer supports the current idea.
- Producing polished screens before contracts, questions, and language are
  understood.
- Calling an implementation review a complete design audit.
- Turning the read-only result directly into code without a chosen phase and
  the repository's implementation gates.
