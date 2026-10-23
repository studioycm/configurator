# Preview & Test Workspace — Reconciled Structure Audit

Date: 2026-10-23. Scope: the proposed Preview & Test implementation and the existing code it would extend. This is a source-backed plan reconciliation, not application implementation or a general application refactor.

## Scope, authority and source snapshot

- Checkout `/Users/studioycm/Herd/configurator`, branch `master`, HEAD `958af03ebcd66fcc5e8c0ecb4e1c420236089197`. Current working files include concurrent Product profile/exact-key consolidation, loader/compiler/runtime, Product UI/import/discovery, tests, skills and other planning/security documents. HEAD alone does not describe this dirty snapshot.
- Confirmed installed versions: PHP 8.4; Laravel 13.35.0; Filament 5.9.0; Livewire 4.4.7; Filament Blueprint 2.4.0; Pest 5.3.0. Existing Vite 8/Tailwind 4/Node test runner remain. No dependency installation is required.
- `AGENTS.md` applies; `.ai/rules` is absent. Applied planning-filament with installed Blueprint overview/actions/testing/checklist, laraveldaily-structure-audit and Laravel architecture responsibility/container/persistence/UI guidance. Boost application-info/search-docs and installed disputed modal/runtime source were inspected. Independent specialists also applied their relevant Livewire, Filament and Laravel guidance.
- Present authorization covers this plan/audit documentation and the separately owned standalone mockup. This coordinator owns only `docs/catalog/PREVIEW_TEST_WORKSPACE_IMPLEMENTATION_PLAN.md` and this file. No application code, database, dependency, index, branch, other plan, `docs/README.md`, commit, push or deployment was changed by this reconciliation.
- The [amended implementation plan](PREVIEW_TEST_WORKSPACE_IMPLEMENTATION_PLAN.md) is the future execution specification. It requires a separate instruction before application coding. All unchecked tasks remain future work.

## Reconciliation inputs and disposition

Separate auditor reports are preserved in the chat's attachment directory `/Users/studioycm/.codex/visualizations/2026/10/17/01a148a4-3c0d-76f2-a6ee-6e32957a6e22/`:

| Report | Role in this reconciliation |
| --- | --- |
| `preview-structure-audit-2026-10-23.md` | Original PVS-01–04 register and boundary inventory. IDs retained below; its PVS-02 recommendation to retain implicit render-time reevaluation is superseded. |
| `preview-framework-audit-2026-10-23.md` | Preliminary combined framework findings; replaced by the more precise independent reports where they overlap. |
| `preview-livewire-audit.md` | Hydration, operation admission, asynchronous reconciliation and existing isolated baseline evidence. |
| `preview-filament-audit.md` | Native sticky footer, local resize contract and state/query/DOM measurement requirements. |
| `preview-laravel-audit.md` | LB-01–05: opposing writers, post-commit failure, signed raw-state trust, literal Product keys and distinct freshness markers. |
| `preview-deep-architecture-audit.md` | Full-source/plan challenge, explicit snapshot and unavailable-definition correction; amended-plan review disposition recorded at the end of this document. |

The proposal is structurally suitable with the corrections below. Existing signed capture, compiler, evaluator, writer and editor contracts should remain. One pure inspection service is justified. The component owns its UI snapshot and orchestration; one browser controller owns display. No new persistence model, service provider, repository layer or generic workspace framework is justified.

## Findings register

“Plan corrected” means the specification now addresses the finding. It does not mean the application has been fixed or the proposed tests have passed. P1/P2/P3 rank implementation priority within this feature, not a claim of a reproduced production incident.

| ID | Priority / confidence | Classification and status | Plan correction / discriminating verification |
| --- | --- | --- | --- |
| PVS-01 | P2 / high | Confirmed trace limitation; plan corrected; app unchanged | Truthful `not_recorded` outcome; restriction skipped vs unmatched presentation-only tests. |
| PVS-02 | P1 / high | Confirmed request-local result lifecycle; plan corrected; app unchanged | One locked evaluated presentation snapshot; external DB mutation + hydration/display/Review preservation test, then explicit retest. Supersedes original PVS-02's implicit-reevaluation recommendation. |
| PVS-03 | P2 / high | Confirmed concurrent Product contract; preserved in plan | `profile_fields`/literal key compatibility and stale-save test; no old allowlist/query restoration. |
| PVS-04 | P2 / high source, runtime unverified | Supported lock inversion risk; no deadlock reproduced | Isolated two-connection actual-MySQL competing-writer gate; no speculative writer refactor. |
| PVS-05 | P1 / high | Confirmed unavailable-result provenance gap; plan corrected | Pass original compiled input to projection, unavailable rows without invented legality; inactive Attribute/default test. |
| PVS-06 | P2 / high | Confirmed independent Product admission path; plan corrected | Shared busy/phase contract for Product/source/Value/inspection/modal/navigation; delayed-export test. |
| PVS-07 | P1 / high | Source-supported post-commit/reconciliation failure risk; plan corrected | Receipt before optional reload; explicit acknowledgments/retry-only reconciliation; delayed/failed Rule remap and post-commit refresh-failure tests. |
| PVS-08 | P2 / high | Confirmed modal body/footer mismatch; plan corrected | `extraModalFooterActions` with scoped `alpineClickHandler`; long-content sticky Save/Close browser test. |
| PVS-09 | P2 / high | Required new resize behavior; no existing resize defect claimed | One browser-only divider, exact minima/ratio scopes/reset/cancel/RTL; zero-request resize and shared splitter regressions. |
| PVS-10 | P2 / high | Required tolerant-inventory trust clarification; plan corrected | HMAC provenance distinct from shape/content/compile/ownership checks; bounded signed-malformed/raw-invalid Rule tests. |
| PVS-11 | P3 / high | Freshness/invalidation contract clarification; plan corrected | Separate capture hash, evaluated snapshot, DB fingerprint and catalog revision; no assumed revision advance on every definition save. |

### PVS-01 — Do not fabricate Rule outcomes

`ConfiguratorEngine::settle()` records a skipped Rule only for a relevant restriction target (`app/Services/ConfiguratorEngine.php:90–95`). `presentation()` silently continues past inactive/unmatched Rules (`:266–269`), records matched candidates (`:277–282`) and structured final winners/ties (`:298–302`). The result DTO has no predicate-level Product fact snapshot. Missing trace is insufficient evidence of “conditions not matched.”

The plan now records `recorded_applied`, `recorded_candidate`, `recorded_skipped` or `not_recorded`, with definition-disabled/Preview-excluded facts separate. It shows defined predicates and actual structured trace only. Excluded Rules come from the active full source before filtering. No second condition evaluator, translated-message parser or additive engine trace change is part of this phase. Tests distinguish unmatched presentation-only, disabled presentation-only, recorded restriction skip, multiple effects and new/saved Preview-excluded Rules.

### PVS-02 — Rendering a modal or changing presentation must not retest implicitly

`ProductConfigurator::$preparedResult` is protected/request-local (`app/Livewire/Catalog/ProductConfigurator.php:25`). `result()` evaluates when it is absent (`:64–68`); `evaluate()` replaces runtime (`:49–53`). Current Preview `render()` calls result (`app/Livewire/Catalog/ConfiguratorPreview.php:371–380`). The loader reads current Product facts/canonical definition (`app/Services/ConfiguratorDefinitionLoader.php:41,66–83`). Avoiding a direct rebuildPreview call therefore does not preserve a tested result after hydration.

Plan sections 4.1/Task 1B now define one `#[Locked] public array $previewView`, assembled only at explicit evaluation boundaries. Its rows live once in `inspection.attributes`; notes/blockers/trace/code/source/Product/inventory/fingerprint describe that evaluation. Render/Review/notices/inspection never call result/loader/evaluator. Existing domain DTOs stay request-local; no Wireable, full definition serialization, persisted cache/draft or duplicated history. Display is browser-local. Review checks the existing fingerprint without replacing the snapshot. Silent external changes can only be detected at an event/server freshness check; display alone cannot discover them. The test mutates availability so A would become B, proves the exact old view/code/trace/runtimes survive, marks stale via Review, then proves explicit Rebuild applies B.

This replaces the original structure report's suggestion to retain ordinary implicit reevaluation. It preserves the operator's approved “display/Review does not alter the test” contract.

### PVS-03 — Concurrent Product exact-key work is authoritative

The current loader feeds `Product::profile_fields` into existing evaluation input `$properties` (`app/Services/ConfiguratorDefinitionLoader.php:51,68,83`). The compiler uses `ProductFields::validKey` (`app/Services/ConfiguratorDefinitionCompiler.php:280–282`). `ProductFields` suggestions may query (`app/Services/ProductFields.php:39–67`); they are not a validity whitelist or query-free fact reader. Engine literal string matching remains authoritative (`app/Services/ConfiguratorEngine.php:241–244,253–258`). Fingerprinting already hashes raw Product attributes (`app/Services/ConfiguratorEditorChanges.php:178–186`).

Preserve custom keys, exact case/punctuation/intentional spacing and text values such as `0`/`01`; do not coerce storage keys for labels, reintroduce import-property allowlists or add ProductFacts. Tests cover a valid custom key outside the old allowlist in saved/current sources, missing/nonstring facts, and a profile change between evaluation and writer causing complete stale rejection. Any loader change must be based on the Product owner's latest diff.

### PVS-04 — Opposing lock paths require actual overlap verification

Tested Save locks Configurator, fingerprints with canonical/Product/Group/context locks and retries its outer transaction (`app/Actions/SaveTestedConfiguratorChanges.php:22–37,47`; `app/Services/ConfiguratorEditorChanges.php:178–186`). `SaveCatalogProduct` locks Groups before Product (`app/Actions/SaveCatalogProduct.php:30–32`); `AssignConfiguratorGroups` locks Groups before Configurator (`app/Actions/AssignConfiguratorGroups.php:31–33,49–51`). `SaveConfiguratorDefinition` takes Configurator/context/canonical in another order (`app/Actions/SaveConfiguratorDefinition.php:39–44`). These are source-supported inversions, not a reproduced deadlock.

Task 7 requires two independent connections/processes with deterministic interleaving through real writer methods in `tests/Feature/Catalog/MySql/ConfiguratorPreviewSaveConcurrencyTest.php`, using guarded `phpunit.mysql.xml`/`tests/bootstrap-mysql.php`. Expected outcomes are bounded successful retry or full stale rejection, with no partial definition/revision write or duplicate Rule/event. SQLite/sequential stale tests do not prove row-lock behavior. Correct a demonstrated failure only after coordinating shared ownership. Omission of a direct canonical Value lock is not independently a confirmed race: `SaveCanonicalDefinition`/`CanonicalUsage` lock owning Configurators before used Value changes. Do not broaden the finding without an actual violating writer.

### PVS-05 — An unavailable result can discard its original compiled definition

For inactive included Attribute or inactive/hidden default, the engine returns `ConfiguratorEvaluationResult(definition:null, attributes:[], ...)` even though input.definition exists (`app/Services/ConfiguratorEngine.php:27–32`). A result-only projector cannot enumerate that unavailable inventory. This is an impossible original plan promise, not evidence that the engine should change policy.

The plan now passes the original compiled input explicitly to `Inspection::build(result, compiledDefinition, excludedRules)`. It is provenance only when the result definition is absent: retained identities/lifecycle flags, state unavailable, legal=null, no selected Value/code, disabled choice controls. Null compilation has empty unavailable inventory plus full editor/error inventory. Request-local metadata resets for every path. A narrow child evaluate transaction ensures raw source/exclusions/compiled input belong to the same snapshot, including Product/choice evaluations; no second post-load source query can silently race the source being explained.

### PVS-06 — Product choosing and other state-changing requests need one admission rule

Product search checks only its own choosing flag before directly invoking chooseProduct (`resources/js/configurator-product-search.js:55–72`); its readonly/clear/option UI likewise knows only that flag (`resources/views/livewire/catalog/configurator-preview-product-search.blade.php:9,17,27`). Workspace collection uses another guard/generation (`resources/js/configurator-preview-workspace.js:111–149`). Delayed export therefore allows a Product/source request to race collection unless explicitly integrated.

Section 4.2 names one workspace phase/generation owner. Product pointer/Enter/clear, source/Value testing, Review, native modal updates, inspection and cause navigation acquire admission and serialize against collection/save/reconciliation. Search retains its latest-response suppression. Local display/resize stays responsive. Tests hold an editor export unresolved and attempt all competing operations; a Product choice admitted first must settle before Save collects. Disabled controls alone are insufficient, and generation checking only after export does not prevent old response overwrites.

### PVS-07 — Committed persistence is separate from optional refresh and editor reconciliation

Current success sequence writes, clears captures, rebuilds saved Preview, then dispatches reconciliation (`app/Livewire/Catalog/ConfiguratorPreview.php:180–187`). A failure after writer return may skip the mapping event despite a committed write. Native Livewire event listeners also reconcile asynchronously; a dispatch is not their completed acknowledgment. New Rule mapping is returned by the writer (`app/Actions/SaveTestedConfiguratorChanges.php:38–46`) and applied in the Rules handler (`app/Filament/Resources/Configurators/RelationManagers/RulesRelationManager.php:89–143`). An unresolved temporary identity must not enter another writer call.

Sections 2.3/4.3 now distinguish rejected, committed/reconcile-pending, refresh-failed and unknown outcome. Emit/retain the successful capture/map receipt before optional refresh; reconcile regardless of a display refresh failure. Add minimal optional operation identity/acknowledgment to existing Overview/Attributes/Rules handlers after coordinating ownership. Retry only pending handlers with the same mapping; one last-applied identity avoids duplicate rebase. Hold Save and automatic recollection until acknowledgments settle. The locked receipt belongs to the successful client snapshot and cannot recover a response lost after commit; no durable exactly-once promise/store is invented. An ambiguous response halts automatic write retry pending saved-state reconciliation.

Tests delay/fail acknowledgment after a new Rule commit, retry reconciliation, preserve later typed fields, reject wrong acknowledgments and force post-commit projection failure. Assert one Rule/transaction and truthful “saved; refresh needs retry” feedback. These are required future tests, not reproduced failures in this audit.

### PVS-08 — Native footer actions are required for sticky Review Save

Installed `action-modal.blade.php:48,56` passes native footer actions to the sticky footer; modalContent/modalContentFooter render in the scrollable body (`:65,79`). A custom Save in modalContent therefore scrolls away. `CanOpenModal.php:394–407` puts extraModalFooterActions beside native cancel; `Action.php:323–329` makes a developer-defined Alpine handler disable the default Livewire click handler.

Plan section 5 uses component-local `Filament\Actions\Action`, `modalSubmitAction(false)`, `extraModalFooterActions` and scoped `alpineClickHandler`, retaining default Close. One shared JS Save collects fresh editor state. No relationship-bound read-only schema, callback writer or second submit path. HasSchemas/InteractsWithSchemas remain because native mounted actions still construct schemas. Browser-check long invalid inventory/small viewport/zoom: both Save and Close stay visible, Save dispatches once, Close/Escape never writes, and scope survives morph/another modal.

### PVS-09 — Resize is a browser-only presentation behavior

Existing `workspace-split.js:3,16–24,47` hard-codes 65%, symmetric420px panes, 26px divider, table/editor storage and 980px stacking. The splitter view has hard-coded table/editor wording. These defaults cannot simply be applied to Preview.

Section 2.7 now specifies one subtle divider between configuration and the combined visible side group; both side panels split equally. Defaults 2:1 or 2:1:1, >=1040 actual container threshold, configuration440px and each visible side240px, stack when infeasible, ~16px hit region, accessible values/label, pointer capture/release-save/cancel/Escape restore, RTL-aware arrows/Home/End/Enter and double-click reset. Separate browserProfile ratios per side combination retain preferred values through clamp/stack/hide/reload. Reset Display resets layout while preserving choices/drafts/theme. Extend shared splitter with optional Preview configuration; retain Attributes/Rules defaults and WorkspaceSizing tests. No new package/framework, server preference patch, engine/capture call or dirty definition.

Tests cover four panel combinations, minima/thresholds, 390/768/960/1440 and zoom, storage denial/corruption/isolation, morph/remount cleanup and long pointer/keyboard sequences proving zero Livewire/evaluator/collector/writer calls.

### PVS-10 — A signed incomplete editor state is still untrusted content

Exports intentionally sign raw unvalidated current editor state (`app/Livewire/Catalog/ConfiguratorOverview.php:69–74`; Rules manager `:146–163`). HMAC uses the current actor (`app/Services/ConfiguratorEditorChanges.php:385–387`); strict prepare adds ownership/normalization/compiler invariants (`:57–146`). A valid signature is provenance, not valid shape, compiler eligibility or authorization.

Tolerant inspect must validate bounded row/type/id/before/after containers and raw fallback allowlists before escaping/formatting. Invalid owned Rule fields remain located review rows; signature/type/foreign-owner/missing-owner/duplicate-identity errors reject the collection atomically. Do not infer valid from normalized. Keep current authenticated Livewire actor context; this task adds no nonambient command/job API or actor wrapper. Tests cover signed malformed containers, nested foreign Rule IDs, capture/row limits and revoked permission along with incomplete Rule + valid Overview/Value inventory.

### PVS-11 — Freshness markers and revision invalidation answer different questions

`CatalogRevisions::batch` accumulates advance calls; it is not itself an advance (`app/Services/CatalogRevisions.php:19–35,39–64`). The inspected SaveConfiguratorDefinition has no advance call, and catalog snapshots cache Product/filter/preset/card metadata rather than Rule definitions (`app/Services/BuildCatalogSnapshot.php:18–82`). No stale catalog cache incident was established here.

Preserve existing scoped revision accumulator/invalidation. Capture hash marks collected editor content; snapshot hash identifies evaluated content; DB fingerprint protects the current write boundary; catalog_revision tracks its existing cached dependency contract. Rejected writes leave definitions/revisions unchanged. Do not add blanket “every Preview Save increments catalog_revision,” an extra fingerprint service or cache reset without a demonstrated dependency. Keep existing Product/Group revision tests intact.

## Responsibility and discovery decisions

The plan's section 4.4 specifies each class type and wiring decision so an implementer need not invent architecture:

| Responsibility | Owner / decision |
| --- | --- |
| Read-only state/cause projection | New concrete final `App\Services\ConfiguratorPreviewInspection`, pure array-shape output; no SQL/UI/writer. Index once per evaluation, reuse. |
| Tolerant review vs strict preparation | Existing `App\Services\ConfiguratorEditorChanges`; share allowed fields/formatting, keep strict prepare authoritative. |
| UI orchestration and presentation snapshot | Existing `App\Livewire\Catalog\ConfiguratorPreview`; private assembler; retain inheritance and native schema/action traits. |
| Domain evaluation types and policy | Existing compiler/loader/engine/DTOs/enums; no moves/Wireable/domain serialization or JavaScript evaluator. |
| Persistence | Existing `App\Actions\SaveTestedConfiguratorChanges` → `SaveConfiguratorDefinition`; no new transaction owner or save operation wrapper. |
| Native actions | Small component-local Filament Action factories; no subclasses or separate application Actions for Review/Save buttons. |
| Selection notes | Existing `ConfiguratorPreviewNotes` consumes projection and retains its report/default callers; no circular dependency. |
| Display and primary ratio | One Alpine display owner + browserProfile and backward-compatible shared splitter options. |
| Helpers/interfaces/DTOs/enums/traits/providers | No new global helper, repository, interface family, DTO family, enum, trait, macro, base workspace or provider; private functions/plain arrays are sufficient. |
| Attributes / DI | Existing Locked/On/Renderless; concrete resolution through existing supported framework points. No Bind/UsePolicy changes, singleton cache or public service property. |
| Namespaces / discovery | Existing App PSR-4, Livewire aliases and Filament resource/panel discovery unchanged. Only explicit Alpine registration/Blade includes added; no policy/route/job/listener/command/model discovery changes. |

The default real catalog result input/native select-radio behavior remains authoritative. Its optional projectedRows adapter permits Preview to reuse one rendering loop without reconstructing a fake DTO or triggering result(). Metadata defaults false. No model/schema/migration, new role, registration behavior or dependency changes are needed.

## Verification evidence and gaps

| Evidence | Status / limit |
| --- | --- |
| Current plan and named runtime/editor/loader/engine/writer/profile/splitter/modal sources | Inspected by coordinator and independent specialists. Installed source resolves disputed protected-state/footer APIs. |
| Boost package-scoped docs / installed Blueprint planning requirements | Inspected. Source verification is not browser conformance. Laravel specialist's Boost timeout used official Laravel13 + installed source fallback, explicitly recorded in that report. |
| Existing JS Preview/Product search baseline | Independent Livewire report records 16 passing tests from its isolated audit run. Coordinator did not rerun; this does not cover planned new behavior. |
| Existing isolated PHP Preview/editor baseline | Independent Livewire report records 31 tests /186 assertions using SQLite :memory:. Coordinator did not rerun. No MySQL locking or future implementation coverage follows from it. |
| Proposed new tests, application browser Save/reload, actual competing-writer MySQL case, resize/stress and query/state budgets | Not run; feature is unimplemented. Prior mockup browser checks are simulation evidence only. |
| Present documentation verification | Consistency/allowlist/whitespace checks recorded after amended-plan final review below. No application formatter/test run is required for these two Markdown files. |

Future implementation should proceed in bounded stages: protect current dirty ownership and isolated test setup; tolerant inventory; stable snapshot/unavailable provenance; direct Save with commit/reconciliation barrier; truthful projection/shared presentations/native modals; local display/splitter; real browser/query/state/actual-MySQL validation. Use the plan's narrow test commands first, then justified broader gates. Record exact fixture identity and cleanup; no development/remote DB fallback. The 100 presentation/choice/resize operations and other repeated flows measure requests/bytes/DOM/listeners separately from PHP timing/SQL. Required linear All states inventory is expected; per-interaction accumulation is a failure.

Before application coding, obtain a separate implementation instruction and agree exact file ownership with concurrent Product/editor/writer owners. Stop before any commit/push/deployment unless separately authorized. No approval is needed merely to finish this authorized documentation reconciliation.

## Amended-plan review record

The deep architecture reviewer reread the complete amended plan and concluded its structure is implementable and proportionate. DA-01/02/03/04/06/07 are addressed by the specification; DA-05 remains an explicit unrun actual-MySQL implementation gate. The review accepted the narrowed not_recorded Rule outcome instead of requiring broader engine instrumentation. No source finding was mislabelled as an implemented fix.

Final clarifications from that review are included: snapshot.inventory_hash is the sole persisted evaluated hash; fingerprint assignment happens once in the evaluation read snapshot before assembly; resize feasibility reserves actual primary/internal gaps; removed/reassigned Products preserve Review inventory recoverably. The reviewer then confirmed the final dataset-provenance case (evaluate A → Review B → choice/legacy refresh keeps A hash/stale label → explicit rebuild B) and distinct/inert All/Catalog control namespaces, preserving public defaults. No additional abstraction is needed.

Final plan: **507 lines**, SHA-256 `a2e2c407eba97769871410cbe61675432e5249cf5fe67acc9bc92cde5dd33d48`. The final deep-review supplement confirms this exact version at 12:11:31 UTC; its report SHA-256 is `82b8060f4f77d680bef840e7ea8deeb5318c83e8623a8fd4996da1653e0ffbae`. Final disposition: no remaining architecture-plan blocker; implementation and runtime proof remain future work.

The plan and this audit passed newline/trailing-whitespace/control-character/fenced-block checks; both remain untracked local documents. Git diff --check returned no issues, but untracked files also required explicit content checks. No application tests, formatter, browser save/stress or MySQL contention run was performed by this coordinator. Application coding still requires a separate instruction; the remaining runtime gates stay open.
