# Nested catalog form reuse audit

> **Current handoff:** [The consolidated implementation specification](MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_SPEC.md) owns current execution requirements. This report preserves evidence and finding history; it is not a competing implementation checklist.

**Scope:** Deep source review of the planned Master Values → shared Attributes → Configurator composition, specifically inherited Filament defaults, validation, action/state ownership and Livewire child lifecycles. This is a planning audit; no application implementation or runtime defect reproduction was performed.

**Snapshot:** 2026-10-23; /Users/studioycm/Herd/configurator; HEAD 4e82c222da421d098e8d1a9b5e50ef5a442b6ecc. Installed baseline: PHP 8.4, Filament 5.9.0, Livewire 4.4.7, Laravel 13.35.0. Existing catalog planning edits are this task's work; concurrent README/image-assignment documents remain excluded. Applicable .ai/rules directory is absent.

**Method:** Laravel Daily structure audit plus Laravel architecture/UI/input-persistence references, Filament development, form UX audit and Livewire guidance; package-scoped Boost documentation and installed framework source. Current schemas, selectors, application writers, global configuration, profile/error adapters and relevant existing test assertions were inspected. Native behavior is verified from source; future nested workflows are not implemented and their tests have not run.

**Status:** Reconciled with the [independent container research and adversarial review](MASTER_VALUES_ATTRIBUTES_CREATION_CONTAINER_RESEARCH.md). CA10–CA13/CA15 are retained as scoped controls; the unconditional custom pickerContext override is narrowed to a verified-need fallback. CA14's plan-gap/custom-guard claim is superseded because the primary plan already specifies native confirmation. Native new-row model/operation boundaries are preferred over custom creation flags. These are planning controls, not reproduced app defects or passed runtime checks.

## Findings

### 1. CA10 — Relative state access can reach the wrong row or action

**High; supported composition risk. Confidence: High.**

**Where:** The reusable AttributeValueWorkspace, root pending-Master pool, row reservation lookups, nested composer and error paths.

**Evidence:** Get::__invoke at vendor/filament/schemas/src/Components/Utilities/Get.php:23 and Set::__invoke at Utilities/Set.php:15 delegate to Components/Concerns/HasState.php:851, which resolves unqualified paths against the owning component's container. Action.php:581 creates schemaGet/schemaSet from the action's schema component, not necessarily from its modal's root. InteractsWithActions.php:803–812 assigns action state paths from their actual nesting index and uses action-name chains for operation context. Repeater::hydrateItems at Repeater.php:168–199 regenerates native item keys during a full fill; dehydrateItems at :207–219 returns indexed lists. These are separate representations of the same draft.

**Problem/impact:** Reusing a fixed relative path or assuming the first mounted action can mutate a sibling Attribute, miss the root Master pool, misaddress validation or reset native row/helper state when rebuilding a draft. Domain refs survive serialization; native UUIDs are not interchangeable with submitted list indices.

**Smallest direction:** The outer action/page owns a runtime rootStatePath and passes it plus the stable owner ref into shared factories. Resolve it from the actual schema, not a hardcoded mountedActions.0 path or a fixed number of ../ hops. Sibling fields may use relative Get/Set; cross-workspace access uses explicit absolute paths with isAbsolute: true. Retain raw keyed state for UI stashes and projection to indexed domain lists only at submission. Tab/Attribute switches do not refill/rekey the complete repeater. Scoped errors still use ConfiguratorFormErrors; no new state/payload/error Service is needed.

**Verification:** Two Attributes with different names, Codes, pending Masters and manual-ID flags; open an unrelated action first; modify the second Attribute's nested composer; remove an earlier row; switch back. Assert only the intended row/pool changed, original refs/UUIDs/helpers survived and a later validation error targets the correct actual schema path. Required in Tasks 1–3 and final review.

### 2. CA11 — Programmatic changes do not run normal change hooks automatically

**High; supported lifecycle risk. Confidence: High.**

**Where:** Regenerate ID, Create and use, link/unlink subtitle, and programmatically selected defaults.

**Evidence:** Utilities/Set.php:15 defaults shouldCallUpdatedHooks to false; :32–34 sets state and calls the target's update hooks only if requested. Components/Concerns/HasState.php:172–199 can bubble explicitly invoked hooks to parents. Current SuggestedIdentifier.php:25–28 already sets both identifier and its helper flag explicitly; retain that sound behavior.

**Problem/impact:** Setting only the visible text and expecting its blur hook to set mode/manual flags can leave inconsistent hidden state. Conversely, enabling all hooks indiscriminately can recurse, rebuild child schemas or clear other work. A UI preview can look right while the stored subtitle mode remains wrong.

**Smallest direction:** Each user operation applies its complete state transition explicitly in its owning UI factory: identifier plus suggestion flag; subtitle mode plus custom override/proxy/custom draft; staged default plus local row ref. Reuse a small private UI method where these transitions repeat. Use normal on-blur hooks for typing, not as an implicit dependency of programmatic writes. Do not globally enable updated hooks; invoke only a required target hook once. Application writers still enforce normalized mode/text and identity independently.

**Verification:** Type Custom, link, rename Master, unlink, clear, save/reload; assert mode, custom draft and final text, not just preview. Regenerate a nested ID and then change its name; manual intent survives. Code/staged-default updates must not mutate unrelated fields or fire duplicate refreshes. Tasks 1–5 own the relevant cases.

### 3. CA12 — A stable picker key can retain obsolete locked query arguments

**High; confirmed framework behavior and supported inline-picker risk. Confidence: High.**

**Where:** Reusing the same embedded Master/Attribute/Value picker after changing owner, staged exclusions or availability context.

**Evidence:** WorkspaceTableSelect.php:15–25 passes query arguments, owner/model and limits to Livewire::mount at :31. The installed TableSelectLivewireComponent.php:32–57 marks these props Locked, not Reactive; only state is Modelable. Schemas/Components/Concerns/HasKey.php:129–138 builds the child key from component ID plus schema key, without arguments. Livewire SupportNestingComponents.php:15–35 returns a stub for an already rendered child key instead of remounting it. Existing AttributeValueBatchTest.php:69–80 inspects arguments after reopening an action; it does not exercise an already mounted child receiving changed arguments.

**Problem/impact:** The parent can show new staging state while the existing table still uses an earlier Attribute or exclusion list. Fetching fresh records on submit protects persistence but does not make this picker correct while editing.

**Smallest direction after challenge:** First use a deliberate native unmount/reopen or public context-sensitive leaf key for real query-context changes. The token covers trusted owner/scope, excluded persisted IDs and effective disabled/limit context, not ordinary selected IDs, names, tags, search, page or columns. Verify same-request key caching with the actual child before adopting a new pickerContext/getLivewireKey override. A narrow override on the existing wrapper is justified only if supported keying cannot satisfy the observed contract. Remount only the affected child and seed its single Modelable selection from the outer draft; preserve native Locked props and independent schema/state-path identity. No second selection store, wholesale Reactive conversion or random remount keys.

**Verification:** While the child remains open, stage a selected Master into Codes, then remove it; available results must exclude/reoffer it correctly. Switch A → B → A with distinct eligible records. Retain other selections, per-Attribute Code drafts and native profile controls. Prove simple checkbox/search/page/column changes do not remount the picker. Tasks 2–3 and actual browser acceptance own this case.

### 4. CA13 — Partial staging must not validate or submit the whole outer form

**High; confirmed API semantics and supported workflow risk. Confidence: High.**

**Where:** Inline Create and use for Master Values while another Attribute, Code or Configurator default is unfinished.

**Evidence:** Schemas/Concerns/HasState.php:592–594 implements getStateOnly by calling the full schema getState before selecting keys. It is not partial validation. Actions/Concerns/HasData.php:130–156 implements getValidatedData using getState(shouldCallHooksBefore: false); this path does not run the parent Action's beforeFormValidated/mutateDataUsing hooks. Repeater.php:2149–2151 provides item-scoped validation through getItemState. Full schema getState at Schemas/Concerns/HasState.php:494–547 can invoke relationship persistence; staged fields must remain unbound as CA07 already specifies.

**Problem/impact:** A naïve Create and use can demand unrelated Labels/Codes/defaults before allowing a new Master, or assume parent authorization/normalization hooks ran when they did not. Reading raw data is appropriate for UI preservation but is not validated input for persistence.

**Smallest direction:** The Master composer is a dedicated native child schema inside the outer state, identified by its actual component/state path. Its staging action explicitly authorizes manage-catalog first and validates only that composer's populated rows through their native child schemas/getItemState, using shouldCallHooksBefore: false for schema validation. Preserve raw UUID/helper state separately; map only validated domain fields into the staged pool. Ignore wholly blank extras while retaining original error paths; incomplete populated rows fail. Do not use outer getStateOnly/getValidatedData as a partial validator or call a creation writer to stage data. The outer Save revalidates the complete graph, ownership and duplicates and performs the only write. Nonempty unstaged composers still block final submission until staged or discarded.

**Verification:** Leave Attribute B's Label/Code and a Configurator default unfinished; valid Master composition under A stages successfully with zero DB inserts. Invalid composer data stays on its own row. Revoked permission blocks staging before DB-backed validation. Final Save still rejects the unfinished required outer fields and preserves all input. Tasks 2–3 own these tests.

### 5. CA14 — Custom close guard rejected; retain the existing native discard policy

**Superseded finding; native mechanism already specified. Confidence: High after independent review.**

**Where:** Outer action footer and the native modal exit policy.

**Evidence retained:** CanOpenModal.php:742–744 and filament/resources/js/unsaved-changes-alert.js:72–105 provide browser-unload protection, not modal-discard confirmation. The default footer Cancel calls close() through Action.php:424–431. These source facts are correct, but they do not establish a missing mechanism in this plan.

**Correction:** Primary plan section 4, immediately before Task 1, already specifies modalCancelAction(false), modalCloseButton(false), closeModalByClickingAway(false), closeModalByEscaping(false), and a native discardDraft footer Action using requiresConfirmation(condition) and cancelParentActions(parentName), with Discard/Keep editing labels. The original CA14 missed this and falsely described an absent guard module as already listed in the plan. The native researcher and independent challenger both identified the contradiction; the primary agent re-read the plan and confirmed it.

**Disposition:** Retain and verify that explicit native cancellation policy. Reject the proposed catalog-creation-drafts.js module and global close-event capture as unnecessary scope under the current contract. Preserving all implicit X/Escape/backdrop exits with bespoke confirmation would be a separate UX choice; it is not an existing requirement. Existing editor-drafts.js remains for its adjacent editor consumers.

The native confirmation condition should consider meaningful root draft changes, including inactive/deselected stashes and unfinished composers, excluding blank generated metadata, helper flags, display proxies and column preferences. This is UI-only decision logic, not another record store.

**Verification:** Filled versus blank draft cancellation; Keep editing restores unchanged state; Discard cancels only the intended parent action; repeated reopen; failed saves; unrelated adjacent editor drafts; native filter/column modals; resize and focus restoration. Assert the disabled implicit paths cannot silently discard data. These checks verify the already planned native flow, not a new custom adapter. Tasks 1–3 and 6 retain acceptance ownership.

### 6. CA15 — Framework utility parameter names can override container injection

**Medium; confirmed resolver semantics and supported callback-wiring risk. Confidence: High.**

**Where:** Closures in shared schemas and resource-local Filament Action factories that call the four application operations.

**Evidence:** Support/Concerns/EvaluatesClosures.php:43–95 checks named default utilities before falling back to container resolution by class. Actions/Action.php:568–586 reserves names including operation, state, component, model, record, schema, data, parentAction and schemaGet/schemaSet. For example, naming a CreateMasterValues-typed callback parameter operation can supply the schema operation string rather than the application object.

**Problem/impact:** A reasonable application-service variable name can unexpectedly bind to a framework utility or the wrong owner, causing a type error or context mistake. Normal PHP constructor injection is unaffected; this is about Filament-evaluated closures.

**Smallest direction:** Use nonreserved capability names for typed application collaborators: createMasterValues, createSharedAttributes, addConfiguratorAttributes, addConfiguratorValues. Reserve the native parameter names for the actual documented utilities/types. Bind hint Actions to their real component as CA02 requires. No new container binding or helper lookup layer is needed.

**Verification:** Invoke the real mounted actions and schema hint/suffix actions, rather than testing only application classes. Assert the expected operation is called and state changes in the correct component. Tasks 1–3 own wiring coverage; final review checks all affected callbacks.

## Coverage and retained boundaries

| Boundary inspected | Disposition |
| --- | --- |
| Model/record/operation inheritance and unique ignores | CA09 retained. The original Value form's default is valid in its own resource; nested new rows need explicit context. |
| Relative state access, mounted-action paths, raw/dehydrated indices and full fills | CA03 retained; CA10 specifies runtime anchors and UI-state preservation. |
| Callback utility ownership/name/type and schema Actions | CA02 retained; CA15 adds name-collision control. |
| On-blur versus programmatic updates and parent bubbling | CA11 specifies explicit transitions and avoids blanket hook execution. |
| Child Modelable state versus locked query props and stable keys | CA12 narrows earlier blanket key-stability wording; intentional query-context remounts are required. |
| Full/partial validation and action hooks | CA13 adds composer-scoped validation; CA07's no-relationship-write rule remains. |
| Hidden/disabled/read-only/saved fields | Existing plan retained: CSS-only optional columns; read-only labels are nondehydrating proxies, not trusted identity; writer allowlists remain authoritative. No claim that readOnly enforces authorization. |
| Empty rows, missing/null/empty/zero, trim and exact Code | Existing rules retained. Installed CanTrimState defaults false; Tags explicitly trim; Code keeps trim(false). No unsupported global trim assumption is introduced. |
| Relationship-save ordering, transaction/lock ownership and post-save feedback | CA04/CA07 retained; no staging relationship hooks or second writer added. Source review is not concurrency evidence. |
| Cancellation, action stack and global modal/resize settings | CA14's custom guard is rejected; verify the primary plan's existing native confirmed-discard policy and preserve resize/profile configuration. |
| Selector authorization, stale membership and model restoration | Existing query Gate checks and write-time ownership/eligibility rechecks retained. Locked/modelable props do not replace those checks. No bypass was reproduced. |
| Stateful global configuration and browser profile scope | Existing providers/factories retained. Preferences remain user-scoped presentation data; selection and record drafts are not stored there. Do not capture current actor in long-lived shared state. |
| Payload depth and component fanout | Config/livewire.php changes only layout; installed defaults specify 1 MiB request size, depth 10, 50 calls and 200 components. Keep one flat Master pool and only active pickers mounted. Add a real deepest-path/request-size check in Task 6; source inspection does not establish effective runtime configuration or maximum-batch performance. Do not raise guards automatically. |
| Existing regression coverage | Inspected SuggestedIdentifiersTest, AttributeValueBatchTest and relevant ConfiguratorWorkspaceTest assertions. They cover their current entry points; they do not prove the future always-mounted inline picker, composer or close guard. No tests ran here. |

## Integration into the handoff

The [primary plan](MASTER_VALUES_ATTRIBUTES_CREATION_IMPLEMENTATION_PLAN.md) and [execution blueprint](MASTER_VALUES_ATTRIBUTES_CREATION_EXECUTION_BLUEPRINT.md) now reconcile CA10–CA15 with the independent research. Keep native Action-owned scalar drafts and native confirmed discard. Prefer native creation boundaries and scoped child-schema validation; try native picker lifecycle controls before a custom override. Retain the same-owner named-schema approach as the strongest technical alternative if the first connected proof demonstrates a concrete benefit. No new child/Page or final-only staging behavior is silently adopted.

The approved UX and four application operations remain unchanged. No additional Service/DTO/interface/provider is justified by these findings: the additions concern UI lifecycle adapters and explicit inputs to existing boundaries. App code, test files, DB data, dependencies and deployed behavior remain untouched by this audit.

## Source references and limits

- [Filament 5 Repeater parent state access](https://filamentphp.com/docs/5.x/forms/repeater#using-get-to-access-parent-field-values), [form field lifecycle](https://filamentphp.com/docs/5.x/forms/overview), [action modals](https://filamentphp.com/docs/5.x/actions/modals), [validation](https://filamentphp.com/docs/5.x/forms/validation).
- [Livewire 4 nested component keys and reactive props](https://livewire.laravel.com/docs/4.x/nesting). Custom inherited behavior was checked against installed source; documentation search excerpts alone were not treated as runtime proof.

This is not a complete security/performance/whole-application audit, a passed app review or a claim that all reuse risks are eliminated. After implementation, Task 7 must inspect the actual connected paths, run the planned discriminating checks safely and record remaining evidence gaps.
