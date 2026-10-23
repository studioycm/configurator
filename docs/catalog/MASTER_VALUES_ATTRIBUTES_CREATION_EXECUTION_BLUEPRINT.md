# Catalog creation implementation execution blueprint

> **Execution handoff superseded:** Use [the consolidated implementation specification](MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_SPEC.md) for the current complete contract and task sequence. This document preserves earlier planning/research context; its old authority statements/checklists do not add competing requirements. Application implementation still requires separate authorization.

> **For agentic workers:** This is a planning handoff, not implementation authorization. After explicit authorization, use executing-plans to deliver the linked implementation plan task by task. Preserve the agreed UX and the audit controls; do not reinterpret this document as permission for unrelated refactors, commits, migrations of live data, or deployment.

**Goal:** Implement the connected Master Values, shared Attributes and Configurator workflows with reusable native Filament components, explicit Laravel responsibility boundaries and auditable acceptance evidence.

**Architecture:** Native scalar schema state stages the complete graph. Four concrete application operations compose the existing canonical/aggregate writers. The Configurator retains its own local editor, default column and presentation controls. No generic CRUD, repository, DTO, provider or JavaScript form engine is required.

**Tech stack:** PHP 8.4; Laravel 13.35.0; Filament 5.9.0; Livewire 4.4.7; Pest 5.3.0; installed Blueprint/FilaCheck. Refresh installed versions before implementation; do not change dependencies.

**Specification:** [Implementation plan](MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_PLAN.md), sections 2–6, supplies the authoritative UX, field/action/API specifications, exact file maps, commands and tests. [Structure audit](MASTER_VALUES_ATTRIBUTES_CREATION_STRUCTURE_AUDIT.md) supplies CA01–CA09; the nested-reuse audit/research resolve CA10–CA15 and evidence limits. Read the linked documents together. The prototype is an interaction reference, not proof of app persistence.

**Planning baseline:** 2026-10-23, /Users/studioycm/Herd/configurator, HEAD 4e82c222da421d098e8d1a9b5e50ef5a442b6ecc. Concurrent README/image-assignment planning changes are excluded. Earlier audit snapshots remain historical. No implementation or app verification was performed to write this blueprint.

**Research resolution:** [Two independent investigations and the adversarial review](MASTER_VALUES_ATTRIBUTES_CREATION_CONTAINER_RESEARCH.md) retain the native Action slide-over baseline with explicit native new-row model/create-operation boundaries. A named creation/composer schema on the same host is the strongest alternative if a connected proof shows concrete lifecycle benefit. No new whole-workspace child, routed Page or custom close guard is approved. CA10–CA13/CA15 are adopted as scoped controls; CA14 is superseded because the primary plan already supplies native confirmed discard. The first real connected proof runs at the start of Package 2, before broader composition; runtime evidence is pending.

## Global constraints and settled decisions

- Implement only after separate authorization. Preserve concurrent work; use an isolated implementation checkout if necessary. Read AGENTS and all applicable path rules first. No new base folder, package or dependency change.
- Master UI Name and Attribute Label come first. IDs/Keys are editable suggestions; manual edits and deliberate clearing survive name changes and validation. Codes remain exact, case-sensitive, two ASCII letters/digits, globally unique.
- Master batch Tags and Specification start visible and may be hidden explicitly. Hide/show and resize preserve all state; hidden fields still validate/save. Ordinary single-record editors keep these fields visible.
- Add row/Add 5/Add 10 append stable blank native rows only, preserving old child state. Counts are exactly 1/5/10 and the total limit is 500; reject an overflowing call rather than partially appending.
- Picker row clicks toggle native selection once. Existing search, filters, pagination, columns, ordering, row actions and editor controls remain available.
- Shared Attributes may have no Values and have no stored default. A new Configurator inclusion requires selected eligible Values and an explicit default in its table, even with one Value. Add Values preserves the current saved default.
- Local value UI name and Local value subtitle share a preview immediately beneath them; Hint follows. Existing/new blank subtitle means None, typed text means Custom, and link action means a live Master name. Rules retain their independent effects and machine keys.
- One outer transaction, authoritative writers, explicit actor/ownership and fresh locked aggregate patches. No immediate relationship writes while staging. Retain exact IDs and serialized contracts.
- Groups, Attribute Value class/table renaming, durable browser-reload draft storage and broad architecture/security remediation remain separate scope.

## Responsibility map: retain, extend, add

| Owner | Decision and contract |
| --- | --- |
| Value and SuggestedIdentifier | Retain the existing identifier algorithm/model query and UI suggestion factory. Extend pending reservations and absolute field targeting; native new-row model/operation boundaries supply creation context without extra global flags. Do not create another slug Service. |
| ValueForm and AttributeForm | Retain static UI factories; return fresh native fields for each schema. Extend batch composition and explicit unique-rule targets. No persistence or application dependency on these schemas. |
| WorkspaceTableSelect/WorkspaceTablePicker and selection-table factories | Retain native modelable state, query scoping, pagination, filters and limits. Add the opt-in row-click wrapper; no second selection array or public toggle writer. |
| AttributeValueWorkspace and AttributeBatchForm | Add static UI composition for selection, pending Masters, inline Master batch creation and association Codes. A single pending Master pool belongs to the outer action. Configurator supplies extra default/local fields without restricting its layout. |
| Resource-local Filament Actions | Adapt native validated state to explicit domain arrays, invoke one operation, map errors, then refresh/notify after success. Keep scalar helper flags and editor stashes out of writer inputs. |
| Four new App Actions | Add CreateMasterValues, CreateSharedAttributes, AddConfiguratorAttributes and AddConfiguratorValues at the exact paths/signatures in plan section 3. Concrete constructor injection; no interface/provider registration. |
| SaveCanonicalDefinition, SaveCanonicalOption and AddAttributeValues | Retain canonical validation, authorization, unique constraints and transaction/lock ownership. Extend AddAttributeValues::saveDefinition with the optional shared pending-Master pool; do not duplicate these writers. |
| SaveConfiguratorDefinition and SaveConfiguratorOption | Retain sole aggregate/narrow local-write ownership. Extend the change callback's context-lock prefix, additive patches and subtitle allowlists. Default actions use the existing aggregate boundary. |
| ConfiguratorInclusionDrafts, definition Loader/Compiler, EditorChanges and BatchCatalogChanges | Retain cohesive Services/operations and current wire/DTO/signed-capture contracts. Extend subtitle intent everywhere it is transported; do not split these classes merely for size or this feature. |
| ConfiguratorSubtitleMode | Add one root App enum for the genuine closed None/Custom/Master states, matching sibling enum conventions. Resolve text purely; no database/query/UI state inside the resolver. Keep existing raw-string model consumers compatible. |
| Frontend adapters | Add only scoped column presentation, keyboard/focus and native row-click targeting at the plan's named JS/Blade/theme files. Browser preferences store optional-column choices, never labels, IDs, Codes or record drafts. |

Keep existing relationships, model helpers, scopes and pure calculations where coherent. A Service is justified by a cohesive capability, an Action by an operation, and an enum by a stable closed set. No additional enum for arbitrary catalog vocabulary or interface around every class is proposed.

## Contracts that must survive every task

### State, identity and errors

Maintain one native outer draft. Master refs identify pending canonical Values; association refs identify an Attribute/Value join; local review refs identify Configurator inclusions. Persisted Master IDs, Option IDs, local inclusion IDs, Codes and refs never substitute for one another.

Use the full pool/lists and original positions from plan section 3. Project only documented keys in the action adapter without filtering rows. Operations reject unknown nested keys, duplicate/missing refs and foreign membership. Completely blank rows may be ignored only during indexed normalization; partially completed rows fail. Use the existing ConfiguratorFormErrors adapter to map original indices to actual UUID/state paths. A failure leaves selections, Codes, refs, manual IDs, defaults and unfinished composers intact.

No callback calls relationship persistence/createOptionUsing to save staged data. A nested Create and use stages rows; only the outer Save creates referenced Masters, once each. A nonempty unfinished composer must be staged or explicitly discarded. Unreferenced staged Masters are visible but not created.

### Nested creation validation

Apply CA09 even when the outer resource/action edits a different model:

- New Master semantic_id validates values.semantic_id with ignoreRecord: false.
- New Attribute key validates attributes.key with ignoreRecord: false.
- New Code validates options.code with ignoreRecord: false and preserves case.
- New-row suggestion/regeneration uses explicit creation context, not merely the outer operation name.
- Existing Value edit ignores only its actual trusted Value, never the inherited Attribute/Configurator record or a submitted ignore ID.

The canonical writers still repeat authoritative invariants and handle unique races. UI validation provides feedback; it does not replace those checks.

### Write order, side effects and local saves

Authorize before selector reads, draft mutations and database-backed action validation, then reauthorize at operation entry. Validate submitted ownership/eligibility again against current data. A new Attribute inclusion requires an active Attribute; a new Value inclusion requires an active/nonhidden Option of that Attribute. Retain existing inactive-inclusion repair rules rather than silently broadening restrictions.

Configurator operations start through SaveConfiguratorDefinition::change: lock owner → global context → canonical records, with context acquired before the callback that may create associations. Use the fresh full definition as the additive base. Existing shared-definition edits must not be nested into that creation callback. Keep unrelated IDs, defaults, order, rules and mappings. No partial-graph retry strategy is introduced; a conflict rolls back the whole operation. Notify only after the outer operation returns.

A saved table default changes only default intent. Remove default from the target general settings payload/hash; reconcile only saved default metadata, leaving all editor drafts intact. A staged table default remains draft-only until Save and add. The sole subtitle schema change is the additive mode/backfill specified in the plan; do not copy a live Master name into custom text.

## Implementation work packages and gates

Each package includes its tests, implementation and narrow verification. Check off the detailed steps in the primary plan; do not create duplicate completion records. After full implementation is authorized, progress through the dependency order without repeated phase approvals. Stop only for a real contract conflict, required unavailable evidence or a necessary scope change.

### Package 1 — Native Master batch foundation

**Uses:** existing ValueForm, SuggestedIdentifier, Value, canonical writer, slide-over/profile/focus conventions.

**Produces:** CreateMasterValues::handle(User, array, ?array): array ref-to-Value map; ValueForm reusable single/batch fields; labeled list Create action; scoped column/keyboard and picker adapters.

- [ ] First add the plan's meaningful Master batch/suggestion/action regression cases. Include CA09's unchanged real-Value edit versus duplicate nested ID under a coincident-ID parent; assert exact error owner and zero rows on failure.
- [ ] Implement the plan's exact native raw-state/new-child append lifecycle and schema-bound hint Actions. Initialize only new children. Keep hidden columns mounted; reveal hidden validation errors.
- [ ] Verify 10 populated rows plus appended blanks, manual/cleared IDs, collisions, original-index errors, tag trimming/distinctness, 495 + 5 success and forged 496 + 5 rejection. Browser/Node checks prove keyboard, columns, resize, one native checkbox toggle and helper-state retention.

**Gate:** existing single-record editing remains valid, native batch creation is atomic, and the same factory is usable under a parent edit without inherited-model mistakes. No downstream Attribute implementation is needed to pass the direct factory/action harness.

### Package 2 — Shared Attributes and one pending-Master pool

**Uses:** Package 1 fields/operation, native selector, AttributeForm and AddAttributeValues.

**Produces:** CreateSharedAttributes::handle(User, array, array): array ref-to-Attribute map; shared AttributeValueWorkspace/AttributeBatchForm; optional pool support in AddAttributeValues::saveDefinition.

- [ ] Add/extend the exact shared batch and existing-editor tests from Task 2. Assert an empty Attribute, valid exact Codes and one pending Master reused by two Attributes with different Codes.
- [ ] Compose inline selection/creation and project only domain keys. Create the referenced pool once before Attribute loops. Preserve outer draft state on switching and Code/Key errors.
- [ ] Complete the primary plan's first connected proof gate using native model/operation scopes, actual runtime roots, composer-only validation, complete programmatic transitions, original-index errors, live picker exclusions and native discard/reopen. Test public leaf-key/unmount behavior before adding a picker wrapper override. A stable named schema on the same host is an alternative to compare only if it removes more lifecycle glue than its explicit begin/reset/submit work adds.
- [ ] Verify late duplicate Code rolls back all new Masters, Attributes and associations; persisted duplicate Master/Key checks use the right tables in actual mounted new/edit Attribute actions. Existing shared editor definition-plus-additions stays atomic.

**Gate:** no extra selector modal, no Configurator default control in shared creation, and native Master column/Add 5/Add 10 behavior is demonstrably reused.

### Package 3 — Configurator Add Attributes and Add Values

**Uses:** Packages 1–2, inclusion-draft Service and aggregate writer.

**Produces:** AddConfiguratorAttributes::handle(User, Configurator, array): Configurator and AddConfiguratorValues::handle(User, Configurator, int, array): Configurator; resource-local action/form composition.

- [ ] Add the Task 3 mixed-existing/new, ownership, authorization, default-ref and rollback tests before composition. Establish the context-lock prefix before nested canonical writes.
- [ ] Implement both action flows as fresh additive patches. Keep existing/new tabs and review stashes, one pool, native row selection and explicit staged defaults. Add Values stays available when all persisted shared Values are already included.
- [ ] Verify the complete Master → coded association → Attribute → local inclusion graph. Exercise revoked permission, stale status, forged foreign membership, late Code conflict, nested creation context and repeated Modelable morph/remount behavior. Assert unrelated local drafts/defaults/rules/mappings are preserved by identity, not just record count.

**Gate:** mixed creation/inclusion saves once or rolls back completely; Add Values preserves the stored default and existing editor work.

### Package 4 — Saved and staged defaults

**Uses:** Package 3 review tables and existing local workspace state/writer.

**Produces:** accessible Default icon actions; one default control per target workspace; no stale default in ordinary settings saves.

- [ ] Add the Task 4 sequence: unsaved local text/settings → choose another Default → switch rows/back → save the original draft. Assert both the chosen default and edited data after reload.
- [ ] Implement narrow saved-default patches, no-op for the current default, membership rejection and staged-default reselection. Replace positional review-component composition explicitly; keep standalone legacy input where no table replaces it.
- [ ] Verify no full editor reload or hash reset silently discards drafts or reverts the saved default.

**Gate:** saved default identity is a local inclusion ID; stored-default availability under runtime Rules follows existing policy.

### Package 5 — Subtitle storage, transports and preview

**Uses:** current local writer, Loader/Compiler/DTO, signed captures, batch edits and runtime Rules.

**Produces:** ConfiguratorSubtitleMode and additive column/backfill; reusable local text fields/preview; explicit mode preserved across all named transports.

- [ ] Add Task 5 migration/roundtrip/partial-patch/runtime cases, including whitespace, valid text "0", legacy missing mode and invalid explicit mode.
- [ ] Add mode to every strict allowlist, normalized result, local draft, dirty comparison, signed capture, tested save, duplication and batch path. Keep wire/Rule backing values and existing DTO string type.
- [ ] Verify blank historical rows become None; linking resolves the current Master name; unlink restores the custom draft. Rename a shared Master and assert linked subtitle follows while local main name/custom subtitle stay unchanged. Check final-label suppression after Rules and no empty second line for select/toggle rendering.

**Gate:** unchanged saves, Preview & Test and batch edits preserve linked intent; helper/resolved-name changes do not create dirtiness or enter signed/persisted payloads.

### Package 6 — Connected actual-app acceptance

**Uses:** completed Packages 1–5 and the isolated test targets in plan section 6.

- [ ] Run the affected PHP and Node checks, Pint, FilaCheck and frontend build as specified. Use a clean implementation checkout so dirty-file tools do not format unrelated work. Ask for the full-suite run after focused tests as AGENTS requires.
- [ ] Exercise all three flows and existing editors in the actual app with isolated approved data at desktop, 1024px and phone widths; capture relevant visual states. Verify keyboard, validation retention, row selection, controls, column choices, cancellation and focus restoration.
- [ ] Measure the named 10-row/multi-Attribute workloads: browser requests/payload and server queries separately. Use guarded disposable MySQL for locks, races and full rollback; label unavailable checks unrun. SQLite is not concurrency evidence.

**Gate:** an evidence record distinguishes source inspection, assertions inspected, executed tests and browser/runtime observations. Do not substitute prototype results or a build for app behavior.

### Package 7 — Deep conformance and dependency audit, then corrections

**Uses:** the exact implementation revision/dirty state, these three agreed documents and executed evidence. This package is required by the user; it is not an optional cosmetic review.

**Produces, after implementation:**

- /Users/studioycm/Herd/configurator/docs/catalog/MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_REVIEW.md — complete reviewing-filament-plans requirement/evidence ledger.
- /Users/studioycm/Herd/configurator/docs/catalog/MASTER_VALUES_ATTRIBUTES_CREATION_POST_IMPLEMENTATION_STRUCTURE_AUDIT.md — connected Laravel Daily/Laravel architecture review and finding dispositions.

- [ ] Extract individually falsifiable behavior, explicit mechanism and required-test obligations from the plan and amendments. Assign stable BP IDs in source order; retain CA finding links. Exclusions/process notes do not count as passes.
- [ ] Trace active registration and each lifecycle: hydrate → react → validate → write → reload; select → resolve refs → include → default; local draft → signed/tested save → runtime text. Compare asymmetric inputs so wrong parent identity, stale defaults, copied subtitles or reindexed errors cannot pass accidentally.
- [ ] Expand the architecture inventory from real callers/dependencies. Follow related Services, operations, enums, model helpers/scopes, validation, providers/DI, compiler/loader/DTO/capture serializers, revision/cache/locks, UI state and runtime consumers where they affect a promise. Review unchanged dependencies too. Record the verified boundary where expansion stops, or the exact unresolved frontier.
- [ ] Classify each candidate as retain, extend, move/extract or separate proposal, with a concrete consequence. Keep sound static factories/pure helpers/cohesive Services. Propose an enum/DTO/Service only for a real value/responsibility boundary; verify discovery/wire/actor/lifetime consequences of any suggested move.
- [ ] Reconcile CA01–CA15 individually as satisfied, still open, narrowed, superseded or unverified, citing code and executed evidence. Preserve historical findings. Keep CA14 superseded; assign CA16 onward only to genuinely new causes; consolidate duplicates rather than inflating counts.
- [ ] Correct feature deviations within the later authorized implementation scope, rerun the smallest affected checks and re-review connected paths. Present wider refactors separately with evidence/benefit; do not perform them automatically. Repeat impacted review until dispositions and evidence agree.

**Gate:** complete reviewed coverage, no confirmed unresolved feature deviation, and no claim of full conformance with a Blocked/Not verified/unreviewed obligation. Any unavailable runtime/MySQL/full-suite evidence remains a visible acceptance limitation, never a pass. Present the actual app and final review to the user; integration/deployment still needs its applicable authorization.

## Audit-control handoff ledger

This is the implementation checklist for existing findings, not a claim that they are fixed. Keep the final BP requirement ledger in the implementation review rather than creating a fourth tracking framework.

| Finding | Required control | Package and decisive evidence |
| --- | --- | --- |
| CA01 | One picker-scoped click invokes native enabled checkbox; no duplicate selection state. | 1–3, 6–7: actual picker text/checkbox/Space/search/page/remount and interactive-control exclusions. |
| CA02 | Concrete scoped column UI; bound hint Actions; append raw UUID state and fill only new children. | 1, reused 2–3: hidden invalid field, unchanged old rows, exact 1/5/10 limit guards, morph/focus checks. |
| CA03 | Full indexed pool, stable refs, correct error paths and one insertion per referenced Master. | 1–3: blank earlier row plus later error; two Attributes reuse one Master; late rollback. |
| CA04 | Owner → context → canonical lock order before nested creation, fresh aggregate patch. | 3, 6–7: source lock trace plus isolated MySQL contention/rollback; no reproduced deadlock assumed. |
| CA05 | Default owns its narrow save and leaves ordinary settings payload/hash. | 4: unsaved edit/default/switch/save sequence and exact local membership checks. |
| CA06 | Explicit subtitle mode survives every transport and resolves final text correctly. | 5–7: migration, unchanged/reload/partial/batch/signed/tested-save/live-rename/Rules checks. |
| CA07 | Static schemas remain UI; four operations reuse authoritative writers; no staging writes. | 1–5, 7: real callbacks, direct operation authorization, no relationship-save leakage and DI ownership trace. |
| CA08 | Tags normalize at new creation boundary; every test run targets proven disposable state. | 1, 6–7: trimming/distinctness cases and actual effective test-target checks. Global S07/S11 fixes remain separate. |
| CA09 | Explicit new-field table/column/no-ignore and explicit creation context; trusted actual edit record only. | 1–3, 7: mounted nested actions under coincident-ID parents reject existing identifiers; genuine edit succeeds. |
| CA10 | Actual root/owner/ref boundaries, raw UI state distinct from projected domain lists. | 1–3, 7: asymmetric drafts, nonfirst action, blank/deleted row and correctly targeted later error. |
| CA11 | Complete explicit programmatic mode/helper/default transitions. | 1–5, 7: typing versus regenerate/link/unlink/clear, no unintended bubbling or draft reset. |
| CA12 | Refresh locked picker query context deliberately; native leaf-key/unmount first, override only if needed. | 2–3, 6–7: mounted stage/remove/switch; no remount on ordinary checkbox/search/page/column work. |
| CA13 | Staging validates composer only and writes nothing; final Save validates full graph. | 2–3, 7: unrelated required fields unfinished, invalid composer row and revoked permission. |
| CA14 | Superseded custom-JS claim; prove the already specified native discard policy. | 1–3, 6–7: empty/dirty, Keep editing, actual Discard, failure/reopen, disabled passive exits. |
| CA15 | Nonreserved collaborator names in Filament callbacks; no extra DI layer. | 1–3, 7: real mounted action/hint/suffix callback and correct state owner. |

For every final finding record: ID, affected BP requirements, trigger/caller, exact owner/line, evidence level, expected versus observed outcome, smallest correction, test/command/result, disposition and any wider-scope decision. For retained healthy boundaries record the owner, callers and reason no extraction is needed. Do not call a source risk a reproduced bug or an unrun check passed.

## Remaining work and decisions

No additional product decision is required to follow this blueprint. The custom work is the scoped column/focus/row-click adapters, pending-ref/error orchestration, the four operations, default-save reconciliation and explicit subtitle transport. Native fields, selectors, action lifecycle, existing writers and existing Services are reused.

Implementation and all app/database/browser/concurrency verification remain pending. During execution, resolve a genuine schema/authorization/retention conflict with the user before changing the contract; otherwise use the settled decisions and existing conventions. Do not reopen Add 5/Add 10, blank subtitle behavior or configurator-specific layout as undecided choices.

Planning self-review checked task dependencies, operation signatures, CA01–CA15 dispositions, connected lifecycle rules, file ownership and the required final-audit boundaries. This is documentation evidence only.
