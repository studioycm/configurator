# Catalog creation: container and draft ownership research

> **Current handoff:** [The consolidated implementation specification](MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_SPEC.md) owns current execution requirements. This report preserves evidence and finding history; it is not a competing implementation checklist.

**Status:** Research complete, 2026-10-23. Two independent investigations and a third adversarial review were reconciled with the installed source and current plan. This is an architecture recommendation and later verification gate, not application implementation or runtime proof.

**Scope/baseline:** Master Value batches, shared Attribute batches/editing and Configurator Add Attributes/Add Values/local editing/defaults/subtitles. PHP 8.4, Laravel 13.35.0, Filament 5.9.0, Livewire 4.4.7; checkout /Users/studioycm/Herd/configurator, HEAD 4e82c222da421d098e8d1a9b5e50ef5a442b6ecc. Concurrent README/image-assignment documents are excluded. No app code, database data, dependencies or tests were changed/run for this research.

## Recommendation

Keep the resizable native Action slide-over, one scalar draft/pending-Master pool per outer workflow and the existing adjacent editors for saved records. Use explicit native model/operation boundaries for new canonical rows, native child-schema validation for Create and use, and the primary plan's existing confirmed-discard footer. The four application operations and existing authoritative writers remain unchanged.

The strongest alternative is a named native creation/composer schema on the **same existing** page/relation manager, displayed in Action modalContent. It offers stable root paths without a new child owner, but requires explicit initialization, submission, error/reset and discard cleanup. Keep it as a focused alternative if the first native lifecycle check demonstrates a concrete simplification; do not build both full implementations or introduce a new component merely because the data is nested.

No source proves either candidate has fewer runtime bugs. Prefer the existing lifecycle until evidence justifies replacing it. A centered modal changes presentation only; a routed Page changes the user journey; a new Livewire host adds a lifecycle boundary. Neither automatically removes the underlying rules.

## Research 1 — Native Filament first

**Question:** How much of this workflow can the installed native APIs handle before adding custom machinery?

The researcher inspected native Action schema/mount/validation/unmount source, model/operation propagation, Repeater internals, selector children, modal controls, actual Filament Examples source and public Laravel Daily material.

**Findings:**

- Modal and slide-over use the same Action engine and template. Changing the visual container does not change record/model defaults, validation scope or draft identity.
- A native new-row schema can declare creation operation and its correct model class. Set the model on the new Repeater or an inner Group: an unbound Repeater explicitly resets each cloned row Schema's model to null. Setting only the row Schema model is therefore insufficient. This native boundary can replace custom creation-context flags across factories, while explicit uniqueness targets/no-ignore remain.
- Native Repeater getItemState or a child Schema can validate the composer alone. Outer getStateOnly validates the whole schema before filtering. A separate Livewire composer is unnecessary for partial validation.
- The primary plan already has native Discard/Keep editing confirmation and disables passive exits. The new custom close-event guard was unnecessary.
- The inherited picker has Locked query props and one Modelable selection. A parent update does not refresh those immutable props. Native context-sensitive keying should be tried before introducing a wrapper override; same-request key caching requires actual verification.

**Preference:** Keep Action-owned scalar drafts because native initialization, validation failure retention and successful unmount already exist. The strongest argument against this preference is readability: a mixed graph under a dynamic mounted-action root may need more addressing logic than a stable named schema.

**Transferable lesson:** Configure supported schema context and lifecycle before inventing flags or component infrastructure. Reusing fields does not require creating another stateful owner.

## Research 2 — Explicit Livewire ownership

**Question:** Would a dedicated draft owner reduce context leakage and make validation and saves more predictable?

The researcher compared a standalone child host, Filament Page, Livewire Form object, plain arrays/Blade, and named schemas on the existing host. Current multiple-form precedents, native discovery/validation, Action wrappers, selector children and source examples were inspected.

**Findings:**

- A Filament Page is already a Livewire component. The meaningful choice is who owns the state and lifecycle, not “Filament versus Livewire.”
- A dedicated child can isolate the creation graph, but needs explicit shell/child save, close, refresh, authorization and session coordination. Keeping a full graph in both parent and Modelable child defeats that isolation.
- A smaller middle option uses creationForm/creationData on the existing component, plus an independently validated composer schema. This removes the dynamic Action root without adding parent-child synchronization. Existing Configurator editorForm/optionEditorForm and Catalog TagValues provide local named-form precedents.
- That named state is outside mountedActions. Native Action reset/unmount will not clear it. Fill once on open, preserve it through errors/switches, clear it only on confirmed Discard or successful save, and explicitly authorize before schema validation. Render the named schema without nesting another HTML form inside the native modal wrapper.
- A Livewire Form object can organize state but does not supply transactions, authorization, ref identity or picker synchronization. Keeping both an array graph and a Form-object copy would add another authority.
- Directly editing new Masters in the root pool could remove a separate staging step, but changes the agreed validated Create-and-use interaction. It is a product alternative, not an equivalent technical simplification.

**Preference:** Investigate same-owner named schemas before choosing a new component. This might simplify root addressing and composer validation, but is not a runtime-proven winner. A dedicated host is a fallback only if measured editor interference or real lifecycle coupling justifies its close/completion protocol.

**Transferable lesson:** One authoritative draft matters more than the number of reusable classes. Share stateless schemas and authoritative operations freely; make a new independently hydrated component earn its synchronization cost.

## Research 3 — Adversarial review

The third reviewer read both full reports, checked their claims against current source/plan, and asked for counterexamples rather than choosing a framework by reputation. It distinguished five independent kinds of nesting: visual containment, Action context, Repeater rows, child-component islands and the business graph.

**What the challenge changed:**

1. **Rejected an actual false positive in my audit.** CA14 claimed the plan lacked safe cancellation and proposed new JavaScript. The reviewer found the existing native footer/disabled-passive-exit policy in the primary plan; the primary agent re-read it and confirmed the contradiction. The source fact that unload warnings are not close confirmation remains true. The inference that a new custom guard was required was wrong.
2. **Challenged the native-first proposal.** Native context APIs and a public key method are eligible source-backed choices, not proof of successful suffix-action context or same-request picker remounts. Both need real connected checks. A native label does not excuse implicit ownership.
3. **Challenged the independent-component proposal.** It does not remove Repeater UUID/list mapping, locked picker props, graph refs, writer locks, default identity or subtitle transport. An extra component can simply move complexity into closing/events/refresh. The named-schema alternative is materially smaller, but still owes explicit cleanup and validation.
4. **Rejected a misleading simplification.** If an unfinished Master has a deliberately blank ID under Attribute A, exposing it directly as reusable under B changes the current staging contract. Completion/ownership flags and discard cleanup may recreate the boundary the simplification claims to remove.
5. **Required equal, falsifiable comparisons.** Judge one editable root, real state transitions, validation scope, cleanup and evidence. Compare configured implementations against identical scenarios, not a complete native form against an empty hypothetical component.

**Adjudication:** Retain the native Action baseline; same-owner named schemas are the strongest alternative. Do not add a child host, routed Page or custom close guard without demonstrated benefit. Source research establishes eligibility and obligations; it cannot guarantee a runtime winner or eliminate all future defects.

## Container comparison

| Option | Effect on reliability concerns | Decision for this scope |
| --- | --- | --- |
| Native Action slide-over | Owns draft/validation/unmount natively; explicit new-row and partial-validation contexts still required. | Preferred baseline; preserves approved workspace. |
| Centered Action modal | Same state, validation and lifecycle as the slide-over. | No architecture benefit; less room for batch entry. |
| Existing edit pane | Can own a named form, but current single-record lifecycle/selected editor behavior still needs adaptation. | Keep saved editing there; do not replace an unsaved editor to create batches. |
| Same owner + named creation schema in modalContent | Stable root without a new whole-workspace child. Adds explicit begin/submit/reset/discard responsibilities. | Best fallback if it demonstrably simplifies the lifecycle. |
| Dedicated Livewire host with native schemas | Creation-only requests/context, plus new shell/child lifecycle and authorization boundaries. | Not justified by current evidence. |
| Routed Filament Page | Explicit root, plus navigation/return/editor-context requirements. | Requires a different user journey; not an implicit safety fix. |
| Custom Blade/array inputs replacing native fields | Avoids native Repeater internals but requires recreating tags, errors, focus, limits and accessibility. | More custom work than warranted. |

## Controls adopted and narrowed

| Audit item | Final research disposition |
| --- | --- |
| CA01–CA08 | Preserve existing interaction, identity, lock/default/subtitle/writer and safe-test contracts. A container change does not resolve them. |
| CA09 | Retain explicit create uniqueness targets/no-ignore. Prefer native model/create-operation boundaries over custom flag plumbing. Verify ordinary blur and suffix Actions plus trusted real edits. |
| CA10 | Adopt runtime root/owner/ref discipline, relative sibling access and absolute cross-workspace access. Preserve raw keyed UI state; project domain lists only at submission. No state-path Service. |
| CA11 | Adopt explicit complete programmatic state transitions. Do not assume Set runs typing hooks or enable all bubbling globally. |
| CA12 | Adopt a picker-context verification gate. Prefer adequate public leaf keying or deliberate unmount/reopen; a narrow existing-wrapper adapter is conditional on demonstrated need, not required automatically. |
| CA13 | Adopt composer-only native validation with explicit authorization; final Save validates the complete graph. No additional component is needed merely to scope validation. |
| CA14 | Superseded as a missing mechanism/custom-JS requirement. Keep the already specified native explicit discard policy and verify meaningful empty/dirty behavior. |
| CA15 | Adopt nonreserved names for typed application collaborators in Filament callbacks. No DI framework/provider/binding layer. |

## First implementation proof gate

After separate implementation authorization, at the start of shared-Attribute composition, wire the smallest real Action using the reused Master fields and two asymmetric Attribute drafts. Do not implement two complete alternative workspaces.

Required observations:

- Native new-row model/operation reaches normal blur and regenerate suffix; a coincident-ID parent never becomes a uniqueness exception, and a real existing Value edit still succeeds unchanged.
- Valid composer staging works while the other Attribute's Label/Code/default is incomplete, performs zero inserts and preserves raw refs/manual-ID flags. Final Save still rejects unfinished required state.
- A leading blank/deleted row followed by an invalid row retains the correct input error and all draft data after failure/switching.
- While a picker is mounted, stage/remove exclusions and switch A → B → A. Results and selections follow the correct owner. Simple checkbox/search/page/column changes do not remount it. Check public keying and same-request cache behavior before approving a custom override.
- Empty cancel closes directly; dirty cancel opens native confirmation; Keep editing preserves everything; Discard clears only its parent batch; failed Save and reopen preserve/reset the intended state. Passive exits remain disabled. Do not use cancelParentActionsOnClose on the confirmation. Use a conditional modal predicate if headings/descriptions would otherwise force an empty-draft confirmation.
- One pending Master reused twice inserts once; late conflict rolls back the graph. Saved default/local drafts and subtitle transport remain independent.
- Measure the actual deepest state path, request size and active picker count for ten Masters and several Attributes. No automatic payload-limit increase or claimed performance gain.

If the native baseline requires disproportionate glue or fails its scoped lifecycle despite supported configuration, compare the same-owner named-schema alternative with these exact cases and count the actual extra begin/reset/submit work. Reconcile the file/method handoff before adopting it. A new child/Page is a later, separately justified choice. All runtime checks remain unrun now.

## Lessons for other Configurator work and applications

- Separate a container's appearance from its state authority and save lifecycle. A drawer, modal and pane are not themselves transaction or validation boundaries.
- Make defaults explicit at reuse boundaries: model, current record, operation, state root, scope and caller. Defaults that are correct for ordinary editing can be wrong for nested creation.
- Reuse field/schema definitions without assuming a separately stateful component is required. Keep one editable graph and one final write operation.
- Keep UI composition in resource schemas/Action factories and state in its actual page/component owner. Put cohesive business operations in application Actions and shared capabilities in existing Services. A pure/static schema factory is not misplaced merely because it is not called a Service.
- A child component's locked inputs and modelable output are different contracts. Stable keys preserve identity; changed query context may require a deliberate lifecycle change.
- Test connected transitions with asymmetric data, not only helpers/final payloads. Source inspection, an asserted test and an executed browser flow are different evidence.
- Treat audit findings as claims to challenge. Keep sound boundaries, correct disproved findings openly, and make an extraction earn its cost. Closed subtitle modes justify an enum; arbitrary catalog labels do not.
- A third reviewer is most useful when checking counterexamples, actual contracts and evidence levels. Agreement or finding counts alone do not establish correctness.

## Evidence and access

Installed source inspected includes Actions InteractsWithActions/HasSchema/CanOpenModal/HasParentActions, action-modal Blade, Schemas model/operation/child schema/Get/Set/key/validation code, Repeater item hydration/validation, native TableSelect child props, Livewire nesting reuse, current selection tables, ConfiguratorFormErrors, editor/profile owners and relevant existing test assertions.

Primary references: [Filament 5 action modals](https://filamentphp.com/docs/5.x/actions/modals), [schema context](https://filamentphp.com/docs/5.x/schemas/overview), [forms and multiple schemas](https://filamentphp.com/docs/5.x/components/form), [custom pages](https://filamentphp.com/docs/5.x/navigation/custom-pages), [Livewire 4 nesting](https://livewire.laravel.com/docs/4.x/nesting). Boost/source supported the disputed APIs; one direct Livewire page request was rate limited, not treated as missing framework support.

Actual Filament Examples MCP snippets inspected by the researchers included reusable Invoice field factories, quote Modelable/Locked picker composition and context keys, a form-and-table Page, and a two-form profile Page. Shared v4 paths remain valid 4/5 examples. These support composition/ownership patterns, not this app's atomic graph persistence or a claim that complete example dependencies/runtime were verified. No example was installed or copied into the app.

The native researcher could read the public [Service or Action lesson](https://laraveldaily.com/lesson/laravel-projects-structure/save-data-service-action). The other researcher checked visible Laravel Daily Beyond the Controller/When Not to Extract previews in Chrome and encountered premium gates. No gated body, authenticated full lesson, video/transcript or paid repository access is claimed. Recommendations are original application-specific synthesis, with current framework source as API authority.
