# Preview & Test Workspace Implementation Plan

> **For agentic workers:** Implementation requires a separate operator instruction. Checkboxes describe future work, not completed implementation. This plan authorizes no application changes, database writes, commits, pushes or deployment. The operator authorized the present plan/audit reconciliation and standalone mockup work only.

**Goal:** Make Preview & Test a compact workspace for testing saved/current editor data, reviewing and immediately saving valid changes, and explaining every Attribute and Value state.

**Architecture:** Extend the existing signed editor captures, definition compiler/evaluator, and transactional SaveTestedConfiguratorChanges writer. Separate the reviewable change inventory from compilation and save eligibility. At explicit evaluation boundaries, assemble one locked plain-array presentation snapshot from the request-local evaluator result and original compiled input. All states, Catalog result, inspection, notes and native Review/notices render that snapshot without evaluating again. One browser display controller owns preferences and the primary resize divider.

**Tech Stack:** PHP 8.4; Laravel 13.35.0; Filament 5.9.0; Livewire 4.4.7; Pest 5.3.0; Alpine supplied by Livewire; existing Vite 8/Tailwind 4 tooling and Node test runner. No new dependencies.

**Spec:** Operator decisions in “Review Configurator preview UX”, through 2026-10-23; the approved interaction specification in section 2 below; [the shared mockup gallery](https://configurator-preview-workspace.brinymoon2.chatgpt.site/value-inspection/), published version 7, source commit 5309ec87b453545b9111efc342e2b5cc1d1e03bc. The operator selected **All Values with inspection** as the base and approved **Save changes saving immediately after validation, with Review changes optional**. This supersedes the earlier mandatory Preview save confirmation.

**Baseline:** Read-only source/browser review on 2026-10-23, primary checkout /Users/studioycm/Herd/configurator, branch master, HEAD 958af03ebcd66fcc5e8c0ecb4e1c420236089197. The dirty checkout also contains concurrent Product profile/exact-key consolidation, loader/compiler/runtime, Product editing/import/discovery, tests, skills, plans and a security audit. This is a snapshot, not a clean execution base. Reconcile exact overlapping ownership before coding. The locally ignored public/demo/D60--temp_images/ and all its originals remain outside scope.

**Audit disposition:** The reconciled findings and evidence are in [PREVIEW_TEST_WORKSPACE_STRUCTURE_AUDIT.md](PREVIEW_TEST_WORKSPACE_STRUCTURE_AUDIT.md). Original auditor reports remain separate evidence. In particular, the first structure audit's suggestion to retain implicit render-time reevaluation is superseded by the stable snapshot contract below. No application fix has been implemented by amending this plan.

## Global constraints

- Implement only after separate approval. Keep work local until publication is explicitly requested.
- Preserve existing manage-catalog authorization, Configurator/Product membership, capture signatures, owned-record checks, compiler validation, fingerprint checks and atomic writer behavior.
- Test/save existing Overview, Attribute, Value and Rule editors, including new Rules. Structural inclusion/removal, ordering and Group assignment continue saving immediately through their existing actions.
- Unsaved changes mean current temporary editor states on this page. Do not introduce a persisted draft/version/publication model or visitor saved configurations.
- Preview interactions and display preferences do not save definitions or Product choices. Save changes applies all gathered editor changes, not just the selected editor or current Product.
- Validation evaluates the current Product and current-edits test choices; it is not proof that every Product/selection combination is valid. Keep that limit visible in Review and Save help.
- Expected selection replacement, context repair and definition-change notices remain informational. Invalid editor data, unavailable/incomplete configuration, rejected interactions and presentation priority conflicts block saving according to the existing blockingIssues policy.
- Keep product-field, context, mixed-source and unconditional effects in diagnostics. Selection notes explain visitor choices and their effects, including automatic replacements.
- Reuse actual runtime labels, display values, hints, codes, semantic IDs, availability and trace. Do not embed the mockup's sample Rules or run a second Rules evaluator in JavaScript.
- No engine policy changes, schema changes, migrations, new roles, new framework packages or independent editor implementations.
- Preserve Product::profile_fields and ProductFields exact-key validation from the concurrent work. Do not restore obsolete properties allowlists, rename engine input properties, or normalize case/punctuation/spacing in keys for display.
- Preserve all other chats' edits. Do not modify their plans or the shared docs/README.md as part of this task.

## Review focus

1. An incomplete new Rule alongside valid Overview/Value edits must remain fully reviewable; validation failure cannot erase the inventory. Task 1 pins this case.
2. A legal saved preview can coexist with conflicting current edits. Save must test current edits and write nothing on failure. Task 2 pins this case.
3. Saved Product facts/definitions may change between evaluation and the write; editor text may change after collection. Reject stale persistence and retain later editor text. Tasks 2 and 7 pin these different races.
4. Initial availability, overlapping restrictions and presentation priorities mean different things. Show all contributors, and never claim the highest-priority restriction cancels another restriction. Task 5 pins this case.
5. Display changes, lazy editor navigation and rapid asynchronous requests must not discard captures, reset choices, duplicate writes or leak preferences between users. Tasks 3, 6 and 7 pin these cases.

## 1. Ground truth and authority

The existing app already has default/search-first Product selection, the inline catalog link and tooltip, saved/current test sources, independent test choices, signed capture collection, change confirmation, atomic saves, notes separated from diagnostics, persistent selected-ID disclosure, and draft-preserving Edit Rule/Edit Value navigation. Extend those capabilities.

The older Preview-placeholder statements in IMPLEMENTATION_GUIDELINES.md and contracts/FILAMENT_ADMIN.md are historical. Later operator instructions and the implemented Preview workflow supersede that deferral. Record the discrepancy in Task 8 and coordinate any later cross-document amendment with the owner; those other documents are outside the present two-file allowlist. Do not remove working Preview code based on the old placeholder.

The gallery is a simulation for interaction review. Design picker, example picker, Play/loop, reset-example controls, its temporary Rule/Value editors and hard-coded data are review-site facilities. The app keeps its existing editors and uses one selected design.

### Verified API and evidence ledger

- Installed package versions were inspected with Composer; no dependency installation is required.
- Boost search-docs was used for Filament 5 action modals/footers and Livewire 4 state/authorization. Installed CanOpenModal confirms slideOver, modalContent, modalSubmitAction(false), stickyModalHeader and stickyModalFooter; InteractsWithActions exposes getMountedAction and unmountAction.
- Native action-schema state can validate/dehydrate relationships before an action callback. The review/notice actions therefore have no persistence-bound schema or relationship fields; their content is read-only Blade and Save goes through the explicit application method.
- Installed InteractsWithActions::getMountedActionSchema calls makeSchema and uses the schema cache even for these native actions. Retain HasSchemas/InteractsWithSchemas when replacing the old choice form with Blade; remove only the obsolete choice form and its manual cache resets.
- Filament Examples returned actual v4 Live Content Preview For Editors source using a slide-over and ViewEntry. It supports the interaction pattern, not installed 5.9 syntax or its raw HTML rendering. All editor strings here remain escaped.
- [Filament actions/modals](https://filamentphp.com/docs/5.x/actions/modals), [Blade modal](https://filamentphp.com/docs/5.x/components/modal), [actions overview](https://filamentphp.com/docs/5.x/actions/overview), [testing](https://filamentphp.com/docs/5.x/testing/overview).
- [Livewire locked properties](https://livewire.laravel.com/docs/4.x/attribute-locked), [Alpine integration](https://livewire.laravel.com/docs/4.x/alpine), [validation](https://livewire.laravel.com/docs/4.x/validation).

## 2. Approved interaction specification

### 2.1 Workspace hierarchy

1. One reserved notice area above all Preview controls.
2. Three semantic action groups: Product; Testing; Unsaved changes.
3. A sticky preview bar showing the tested source, All states/Catalog result, and Display.
4. Configuration, selection notes, and optional diagnostics/inspection columns.

The default presentation is All states. Both saved/current sources are available within either presentation. Presentation, metadata, panel visibility, density, label size and resize changes are browser-local and never call Livewire, rebuildPreview or switch source. Any later server render reads the last evaluated previewView snapshot; it must not call result() as a hydration fallback. See section 4.1 for the exact lifecycle.

### 2.2 Complete action inventory

| Group/location | Control | Behavior |
| --- | --- | --- |
| Product | Search/change Product; clear | Reuse the current autocomplete and keyboard behavior. Default to the existing first eligible Product. Product changes reset both source runtimes as today, but preserve editor changes and display settings. |
| Product, same row | Open in catalog | Reuse the server-resolved destination and inline tooltip naming the selected Product or eligible fallback. Clearing the test Product must not imply the fallback is being tested. |
| Testing | Test saved; Test current edits | Gather editors, then explicitly rebuild the chosen source. Explain which data is included. Preserve independent source choices. |
| Testing, secondary icon | Rebuild preview | Retain an accessible manual rerun of the current source; do not add another save path. |
| Unsaved changes | Edited item/field count | Always show the current inventory, including when testing saved data or when compilation fails. Show “No unsaved changes” in the same reserved line. |
| Unsaved changes | Review changes | Optional read-only slide-over, available with invalid/conflicting edits. Opening/closing it saves nothing and preserves the tested source and choices. |
| Unsaved changes | Save changes | Immediately collect, validate, evaluate and attempt the atomic save. No confirmation modal or second confirmation click. |
| Review slide-over footer | Save changes; Close | Save uses exactly the same current-collection path as toolbar Save. Close only closes the review. No discard action is added. |
| Notice area | Contextual action; All notices | Link to Review, the relevant inspector or editor. All notices opens a read-only slide-over. No full-width conditional error block is inserted below this area. |
| Preview bar | All states; Catalog result | Presentation only. Keep the source, choices, code, drafts and inspected identity. |
| Preview bar | Display | One dropdown for metadata, panel visibility, density, label sizes and color mode. |
| Attribute/Value suffix | Explain | Open the relevant inspection section. Use the same icon for both levels; accessible label names the object. |
| Inspection/causes | Edit Rule; Edit Value | Reuse openSource/navigateConfiguratorEditor and existing editors. Preserve all pending edits. Do not add the mockup's simplified editor drawers. |

Custom controls use type=button and never submit the outer Configurator form. Native action Close uses Filament's own closing behavior. Save is the primary filled action. Test sources use a pressed/selected treatment; secondary actions use neutral styling. Use existing Filament Blade buttons/icon-buttons and Heroicons. Explain uses heroicon-o-viewfinder-circle; question-mark-circle is reserved for actual hints/help.

### 2.3 Save and review contract

- Save acts on the latest complete capture from all mounted editors, not the previously reviewed or tested capture. A failed/missing editor response aborts before calling the server save method.
- From Test saved, Save switches to/evaluates current edits before writing. Use the retained current-edits runtime, or the existing saved-runtime fallback if current edits have not been tested. On failure remain on current edits so the error is visible; the saved runtime remains available.
- Save requires a selected eligible Product and at least one actual change. During collection/save, prevent repeated Save, Product/source changes and editor jumps from starting a competing operation.
- Known errors/conflicts in the current-edits preview disable Save with a readable reason. A saved-source preview whose current edits have not been validated can attempt validation; a successful saved test is never its permission to write.
- Recollect immediately for Save from an open review. Do not persist the review's old before/after list if another editor changed afterwards.
- The existing writer rechecks the fingerprint under its transaction/locks, recompiles, evaluates and validates again before writing. No partial save on any failure.
- On successful writer return: retain and emit a bounded commit receipt containing the successful capture and temporary-to-saved Rule mapping before any fallible preview reload. Reconcile only those collected changes through configurator-preview-saved, even if saved-preview refresh fails. Keep newer editor text. Remain in reconcile_pending until every involved mounted editor acknowledges completion; only then refresh inventory/counts and enable another Save. Report reconciliation failure as “Saved; editor refresh needs retry”; retry with the same receipt, never the writer. Close Review only if it is still the mounted action. Section 4.3 specifies acknowledgments/retry.
- Review is a change inventory, not a validation token. Do not require a valid configuration, successful evaluation or reviewedFingerprint to open it.
- Keep inventory and evaluated-editor hashes distinct. If Review collects newer edits while current edits are displayed, mark that result “Preview needs rebuilding”; do not relabel the old result as the latest test. An old error must not prevent attempting validation of a newer inventory. Entering Preview still automatically gathers and tests the current editors as today.

### 2.4 All states and Catalog result

**All states:** retain every included Attribute in display order, including inapplicable ones with a grey row background. Show every included Value. Legal selected = green/check; other legal = blue/circle; disabled = red/disabled icon; hidden = grey/eye-slash with dimmed label content. Hidden Values sort last within their Attribute, preserving original display order within each partition. Attribute order and configuration-code order remain independent.

Use compact Value cards: state icon, effective label, optional mid-dot Code, optional Value ID Name, Explain suffix icon. Omit repetitive visible state words; retain a concise legend, accessible state text and selected/disabled semantics. Explain remains enabled for unavailable Values. It must be a sibling button, not a button nested inside a radio label/button. Hidden cards can be dimmed without dimming the Explain control.

Derive these states from the final evaluated state: inapplicable Attribute or Value in hidden -> hidden; otherwise legal selected Value -> selected; other legal Value -> available; remaining Value -> disabled. Attribute state is hidden, no_legal_values or available. Do not recalculate availability from display colors or from a subset of Rule effects. If current editor data cannot compile, show an explicit unavailable-current-preview state and the full change/error inventory; do not quietly render the saved definition as if it were current edits.

If no legal Values remain, show that status at the Attribute, retain all its cards and their explanations, withhold the configuration code as the engine requires, and block saving. Do not select a hidden/disabled Value to make the screen look complete.

**Catalog result:** reuse components/catalog/configuration-fields.blade.php and its actual effective labels, hints, display values, select/radio input types and selection behavior. Omit inapplicable Attributes and hidden Values; retain disabled choices. Hide inspection colors, legend and Explain icons. Add optional projectedRows plus showValueCodes/showValueIds props (metadata false by default). Existing callers keep their result input and default output; Preview supplies snapshot rows rather than calling result(). The adapter and exact row contract are specified in section 4.1.

This is the catalog **presentation of the same Preview evaluation**, not an added public-context test mode. The existing forPreview loader excludes Rules with global Territory/Application context predicates. Keep that policy, list those exclusions in diagnostics and show a concise scope hint in Catalog result when exclusions exist. An included Attribute named Territory remains an ordinary selectable Attribute; it is distinct from global public context. Derive exclusions from the active source data, including new unsaved Rules, rather than only querying saved Rules. Do not claim full public-context parity or add new context controls in this phase.

### 2.5 Display preferences

| Setting | Initial value | Scope |
| --- | --- | --- |
| Presentation | All states | Per user/browser and Configurator page |
| Value Code | On in All states; off in Catalog result | Remember separately for each presentation |
| Value ID Name (semantic ID) | Off in both | Remember separately; never show a numeric database ID instead |
| Rules & state notes | Shown | Per page |
| Diagnostics / Value trace | Hidden | Per page; an explicit Explain action may reveal it |
| Compact spacing | Off | Per page; Value cards are still compact at the normal setting |
| Attribute label size | 14 px; options 12/14/16 | Per page |
| Value label size | 12 px; options 11/12/14 | Per page |
| Color mode | Existing global app preference | Light/Dark controls dispatch the existing theme-changed event; do not create an independent Preview theme |

Use browserProfile with the existing page:<pathname>:preview user/browser scope. Migrate its old trace boolean. Store presentation preferences and primary split ratios only, never captures, Rule text, Product facts or selections. One Alpine display controller is authoritative; do not introduce setDisplaySettings, display entanglement or a mirrored server preference array. Reset display restores this table and section 2.7's layout defaults without discarding editor/test state or changing the global theme. Storage denial, corrupt/oversized JSON and unknown settings fall back to defaults.

The gallery's Actions layout switch and separate Compact Attributes / Changes-and-test-comparison designs are excluded from this implementation. Use the selected three-group arrangement; compact spacing and label controls supply the agreed variation without implementing four layouts.

### 2.6 Notices, notes and inspection

Reserve an 80 px desktop / 128 px narrow-screen notice slot as an initial layout value. Keep its outer size constant across empty, collecting, successful, informational and error states. Fit a short headline, at most two detail lines and the contextual action; full content remains in All notices or the relevant side panel. Verify large labels, long codes and narrow widths before accepting those values. Keep detailed lists scrollable rather than growing the slot.

Priority: collection/validation failure; unavailable/incomplete/conflicting test; stale saved data; selection replacement/effect; pending changes; successful save; neutral/no notices. Merge rather than hide lower-priority messages in All notices. Do not let an informational selection replacement imply saving is permitted when another Attribute has a conflict.

Rules & state notes contains user-selection effects and all choice-adjustment notices. It retains the selected Value ID Name disclosure and its open state. Product/mixed/system causes remain in diagnostics. “Explain effect” links identify the affected Attribute/Value.

Diagnostics has one stable header and these contextual sections: current inspected Attribute/Value and final state; initial availability; applied/overlapping restrictions and source conditions; presentation candidates/winner/ties; effects on other choices; relevant Rules with recorded outcomes or not-recorded status; ordered engine trace. Include Clear inspection for the Configurator-wide overview. Value inspection includes Attribute-level causes as well as Value-specific causes.

“Effects on other choices” describes active effects from the currently selected Value. For an unselected Value, show related Rule definitions as related, unevaluated possibilities; do not predict their outcome or run a counterfactual engine evaluation on every Explain click.

Restrictions accumulate. An overlapping restriction may affect zero *additional* Values but still contributes to the final state and must be listed. Presentation fields use the existing highest-priority winner logic; equal-priority differing values retain their existing conflict/base behavior. Show this distinction in plain language. Use recorded structured trace outcomes only. Related Rules absent trace have outcome=not_recorded; definition-disabled and Preview-excluded are separate provenance facts. Unmatched presentation-only Rules currently have no skipped trace. Show defined predicates without inventing matched/skipped, actual Product facts or a particular failing nested predicate. No additive engine trace is required in this phase.

### 2.7 Layout and accessibility

- At Preview container width >=1040 px, use one subtle vertical divider between configuration and one combined side group. Defaults: configuration/notes=2:1; configuration/diagnostics=2:1; configuration/notes/diagnostics=2:1:1. When both side panels are visible, divide the combined side group equally, with no second divider. Configuration alone is full width and has no divider.
- Configuration minimum is 440 px; each actually visible side panel minimum is 240 px. Account for real gaps and the divider's approximately 16 px hit region. Enable columns only when both the >=1040 threshold and all minima fit; otherwise stack without horizontal overflow. Use actual container width, not viewport width. At configuration width >=440 px use label/Value inner columns (approximately 30/70); below it stack label above cards.
- Persist a preferred primary configuration share separately for notes-only, diagnostics-only and notes+diagnostics in browserProfile. Defaults are 2/3, 2/3 and 1/2 of availablePaneWidth respectively. Define availablePaneWidth=actual container content width minus the divider track and actual gaps between primary tracks. Let internalSideGaps be zero for one side or the actual gap between two side panels. Compute min=440/availablePaneWidth and max=1-(240*visibleSideCount+internalSideGaps)/availablePaneWidth. Check feasibility using these same widths so extrema cannot reduce a side below240px. Clamp rendered share without overwriting preference while narrow/stacked. Normalize stored finite ratios to (0,1); ignore invalid values. Hide/show/reload restores each combination. Reset Display resets all three ratios.
- The divider is a thin hairline; reveal its small grip on hover/focus, keep the ~16 px hit region and visible focus. Use role=separator, aria-orientation=vertical, a label naming configuration/side panels, dynamic aria-valuemin/max/now and human-readable aria-valuetext. Pointer capture starts a drag; move changes local CSS only; release persists once. Pointer cancel/lost capture/Escape restores the starting preferred ratio and does not persist a cancelled move. Left/Right adjust 2 percentage points with RTL-aware direction, Home/End use feasible extrema, Enter and double-click reset the current combination. Clean up ResizeObserver, pointer capture, timers and listeners on destroy; preserve local state through unrelated morphs.
- Extend workspace-split.js/workspace-splitter.blade.php with narrowly scoped optional configuration for Preview and retain their existing Attributes/Rules defaults, storage and tests. Preview provides browserProfile read/write callbacks, dynamic side minima/count, 1040 threshold, 16 px hit region and wording; do not copy the existing hard-coded 65%, 980px, symmetric420px, 26px or table/editor label into Preview. No generic splitter framework or new JS package. All drag/keyboard resize remains available during a save and causes zero Livewire/evaluator/collector/writer calls or definition dirty state.
- The notice slot and action groups precede the configuration. Keep the compact preview bar sticky below the actual admin header; use existing shell offsets and no hard-coded offset copied from the gallery's lab toolbar.
- Explain reveals the third panel, scrolls only enough to reach it, and focuses its heading. Closing restores focus to the initiating control if still present. Product changes clear inspected identity but retain display preferences. Re-evaluation preserves a valid identity; unavailable targets produce a recoverable notice and Clear inspection.
- Preserve selected-ID disclosure state during Value choices, view/source changes and explanation refreshes. Native review/notice slide-overs own Escape, focus trap, return focus and sticky footer; reuse existing workspace dialog sizing.
- Every icon-only action has a named tooltip/accessible label. Color is supplemented by icons and programmatic state. Use at least 24 px desktop / 36 px narrow-screen action targets and visible focus; test wrap, zoom and actual contrast rather than assuming color names prove accessibility.

## 3. Data, authorization and file responsibilities

### Models and persistence

No new model, migration, table, column, relationship, queue or provider binding. SaveConfiguratorDefinition remains the authoritative definition writer. SaveTestedConfiguratorChanges retains its transaction, fingerprint, compiler/evaluator and CatalogRevisions batch. Existing per-editor Save and immediate structural actions remain available. Current lock orders contain a source-supported inversion across Configurator/Product/Group writers; their safety is not established by this plan. Require the isolated two-connection MySQL gate in Task 7 before asserting concurrency safety or changing shared writers. A batch is not itself an advance: do not require every definition Save to increment group catalog_revision unless an actual cached dependency warrants it.

Every new Livewire method reauthorizes manage-catalog. Resolve Product through this Configurator's existing eligible Product query; resolve inspected Attribute/Value/Rule through its current definition/owner. A locked ID alone is not authorization. A forged or foreign capture/inspection target fails before persistence; missing targets leave editors intact. Never expose a local Edit Value action for a shared-library/lifecycle cause that the local editor cannot fix.

### File map

| File | Planned responsibility |
| --- | --- |
| app/Services/ConfiguratorEditorChanges.php | Add tolerant read-only inspection of verified captures; retain strict prepare/fingerprint/save normalization. |
| app/Services/ConfiguratorDefinitionLoader.php | Expose the existing pure public-context-condition predicate for the exclusion summary; preserve filtering/evaluation behavior. |
| app/Livewire/Catalog/ConfiguratorPreview.php | Coordinate inventory/source/Save; assemble locked previewView only at explicit evaluation; render read-only native actions/inspection without result() fallback; own bounded commit receipt. |
| app/Actions/SaveTestedConfiguratorChanges.php | Reuse existing writer; change only if a failing concurrency/atomicity test demonstrates a necessary adjustment. |
| app/Services/ConfiguratorPreviewInspection.php (new) | Query-free projection of every Attribute/Value, Rules, causes, presentation outcomes and trace from result plus original compiled definition when unavailable. |
| app/Services/ConfiguratorPreviewNotes.php | Preserve selection-note classification; reuse the generalized inspection conflict projection instead of maintaining a second cause algorithm. |
| resources/views/livewire/catalog/configurator-preview.blade.php | Workspace composition and the single reserved notice slot. |
| resources/views/livewire/catalog/configurator-preview-controls.blade.php (new) | Three action groups and sticky presentation/Display controls. |
| resources/views/livewire/catalog/configurator-preview-values.blade.php (new) | All-state Attribute/Value cards and Explain triggers. |
| resources/views/livewire/catalog/configurator-preview-inspection.blade.php (new) | Contextual diagnostic sections, cause/editor links and trace. |
| resources/views/livewire/catalog/configurator-preview-changes.blade.php | Always-reviewable escaped grouped inventory and invalid/stale row markers; Save belongs in native extraModalFooterActions, not this body. |
| resources/views/livewire/catalog/configurator-preview-notices.blade.php (new) | Complete read-only notice list with contextual actions. |
| resources/views/components/catalog/configuration-fields.blade.php | Preserve existing result contract; optional projectedRows adapter and false-default showValueCodes/showValueIds; one shared input/render loop. |
| resources/views/components/catalog/configurator-preview-cause.blade.php | Reuse existing openSource targets; extend only for new projected cause details. |
| resources/views/livewire/catalog/configurator-preview-notes.blade.php | Reuse existing effective hint/display text where needed; no duplicated Rules logic. |
| resources/js/configurator-preview-workspace.js | Latest-editor collection, one shared Save entry, review/notice requests and race handling. |
| resources/js/configurator-product-search.js; resources/views/livewire/catalog/configurator-preview-product-search.blade.php | Integrate choose/clear/keyboard admission with workspace busy phase while retaining latest-search-result protection. |
| resources/js/configurator-preview-display.js (new) | One local preference owner, metadata/panel/density/size and primary ratio using existing browserProfile; no server settings route. |
| resources/js/workspace-split.js; resources/views/components/catalog/workspace-splitter.blade.php | Backward-compatible optional Preview sizing/storage/wording inputs; existing Attributes/Rules defaults retained. |
| resources/js/app.js | Register the new Preview display Alpine controller; retain existing workspace controller. |
| resources/js/admin-profile.js | Remove the Preview-only localTraceProfile adapter after migrating its one caller; keep browserProfile and unrelated profiles unchanged. |
| resources/css/filament/admin/theme.css | Scoped workspace, notice, card, panel, metadata, dark and responsive styling. |
| lang/en/configurator.php | Controls, states, concise notices, inspection and inventory fallback labels. |
| tests/Feature/ConfiguratorEditorChangesTest.php | Inventory, signature/ownership/stale handling and retained editor reconciliation. |
| tests/Feature/ConfiguratorPreviewWorkspaceTest.php | Sources, optional review, immediate Save, authorization and presentation interactions. |
| tests/Unit/ConfiguratorPreviewInspectionTest.php (new) | Pure projection/causality and sorting cases, using existing evaluator fixtures. |
| tests/Unit/ConfiguratorPreviewNotesTest.php | Preserve source classification and conflict/selection notice policy. |
| tests/Unit/ConfiguratorPreviewWorkspace.test.js | Collection, direct Save, navigation, cancellation and asynchronous races. |
| tests/Unit/ConfiguratorPreviewDisplay.test.js (new) | Display validation, migration, scope, storage failures and roundtrips. |
| tests/Unit/ConfiguratorProductSearch.test.js; tests/Unit/WorkspaceSizing.test.js | Shared busy admission and backward-compatible splitter behavior. |
| tests/Unit/AdminProfile.test.js | Retain shared profile behavior; adjust only Preview-specific adapter coverage if present. |
| tests/Feature/Catalog/ConfiguratorRuntimeTest.php | Prove the catalog's default presentation and legal interactions remain unchanged. |
| tests/Feature/Catalog/MySql/ConfiguratorPreviewSaveConcurrencyTest.php (new) | Guarded isolated two-connection actual-writer lock overlap/retry/stale rejection; no sequential SQLite substitute. |
| docs/catalog/PREVIEW_TEST_WORKSPACE_IMPLEMENTATION_PLAN.md; docs/catalog/PREVIEW_TEST_WORKSPACE_STRUCTURE_AUDIT.md | The only documentation files owned by this chat's present reconciliation. |
| app/Livewire/Catalog/ConfiguratorOverview.php; app/Filament/Resources/Configurators/RelationManagers/AttributesRelationManager.php; app/Filament/Resources/Configurators/RelationManagers/RulesRelationManager.php (conditional, coordinate first) | Minimal acknowledgment/idempotent reconciliation additions to existing acceptPreviewSave only, if using the explicit barrier contract in section 4.3; preserve exports, rebasing and existing editor UI. |

The existing Overview and Attributes/Rules exportPreviewChanges and acceptPreviewSave handlers remain the capture/reconciliation interfaces. Do not rewrite them merely to add inventory. The additive reconciliation acknowledgment is an actual lifecycle need; coordinate and allowlist these three files before that change. If another chat owns them, agree the patch or wait; do not silently replace their latest implementation. Other Product/canonical/writer files are inspection dependencies, not automatically editable files.

## 4. Planned interfaces

Use PHPDoc array shapes following current conventions; no DTO class is needed solely to wrap these projections.

- ConfiguratorEditorChanges::inspect(Configurator $configurator, array $captures): array returns {changes: list<Change>, errors: list<string>}. Change preserves current type/id/label/new/fields and adds invalid:bool, stale:bool and errors:list<string>. A field retains before, after, before_text, after_text. Validate bounded row/type/id/before/after container shapes and fallback field allowlists before rendering. HMAC establishes current actor provenance for intentionally raw editor state, not content validity. This method verifies envelopes/limits/unique identities/ownership; it does not compile, evaluate or write.
- ConfiguratorEditorChanges::captureHash(int $configuratorId, array $captures): string hashes verified rows sorted by type/id, excluding envelope signatures. Retain meaningful array/list order within editor state. Identical exports have the same hash; a changed field or new Rule identity changes it. This is a UI freshness marker, not a replacement for the existing database fingerprint or signature.
- For valid rows, inspect and prepare share the same field whitelist, normalization and display formatting. For incomplete Rule input that cannot normalize, inspect uses the verified raw editor state, explicitly allowed Rule editor fields (including condition_blocks), and escaped human-readable fallback descriptions; it keeps every changed row and adds its located error. Successful normalization is not proof of compilation: Review labels an unevaluated inventory as not yet validated, and later compilation errors remain visible beside the retained inventory. Signature/type/foreign-owner or missing-owner-row failures reject the collection rather than accepting a partial inventory. A changed but still-owned saved row remains reviewable as stale and cannot write. Invalid new Rules retain their temporary ID.
- ConfiguratorPreview::refreshEditorInventory(array $captures): void updates locked previewCaptures, previewChanges, inventoryErrors and inventoryHash without switching source or clearing the displayed valid saved result. Derive the evaluated hash from previewView.inventory_hash; no separate evaluatedInventoryHash property. editorPreviewStale compares current inventoryHash with that snapshot hash while current edits are displayed. Changed inventory invalidates testedFingerprint until reevaluation. rebuildPreview always refreshes inventory; compile failure may clear current previewData but not inventory.
- ConfiguratorPreview::reviewChanges(array $captures): void refreshes inventory and mounts reviewChangesAction without a valid-test prerequisite. ConfiguratorPreview::reviewChangesAction(): Filament\Actions\Action is read-only.
- ConfiguratorPreview::saveChanges(array $captures): array refreshes/rebuilds current edits, checks strict eligibility and invokes the existing writer once. Return a discriminated outcome: rejected with located errors/intact captures, or committed with bounded receipt identity and optional refresh error. A post-commit rebuild/projection failure is still committed and cannot suppress the receipt or reconciliation. Never classify it as a rejected transaction. See section 4.3.
- Review/notice content requests Save by dispatching configurator-preview-save-request with the owning componentId and configuratorId. The workspace's window listener matches both identities, checks it is active, and invokes its existing save() method. This avoids relying on a teleported modal's nearest Alpine scope and never supplies a client-trusted capture or permission token.
- Retire saveTestedChangesAction, reviewedFingerprint and reviewedTest after migrating tests/callers. Retain testedFingerprint for the evaluation-to-writer boundary. ConfiguratorPreview::canAttemptSave(): bool is the authorized display predicate: require a nonempty trusted inventory, no inventoryErrors and an eligible Product; a fresh current-edits result must have no compile/blocking issues, while a saved-source or stale-current result can attempt fresh validation. Busy state is additionally enforced by the JS controller. A private canSaveEvaluatedChanges(): bool retains the strict post-rebuild checks before invoking the writer. Never treat canAttemptSave as permission to skip validation.
- ConfiguratorPreview::inspectState(string $attributeId, ?string $optionId = null): void authorizes, validates membership against the locked evaluated snapshot and existing scoped editor target rules, stores one locked inspectionTarget, then sends a scoped local reveal/focus event. clearInspection(): void returns to overview. Neither method calls result(), loader or evaluator. Unavailable inventory is inspectable but never selectable. Current membership is checked again before an editor jump/write.
- ConfiguratorPreviewInspection::build(?ConfiguratorEvaluationResult $result, ?ConfiguratorDefinition $compiledDefinition = null, array $excludedRules = []): array returns {attributes: array<string,AttributeInspection>, rules: list<RuleInspection>, conflicts: list<existing conflict shape>, diagnostics: list<array>, trace: list<array>}. Use result.definition where present; otherwise use the original compiled input only for unavailable inventory/provenance. No result/definition returns an explicit unavailable report with empty rows. Each evaluated Attribute has id, effective label/hint/display_value, input_type, applicable, state, selected_id, ordered Values and cause/trace references. Each Value has id, effective label/hint/display_value, code, nullable semantic_id, legal, hidden, state and causes. No legal state or selection is fabricated if result.definition is null: row state=unavailable, legal=null, selected_id=null; retain original lifecycle flags/provenance. Disable every choice and withhold code. Cause source category is initial/selection/product/context/mixed/system; include Rule/set identity, priority only where meaningful, defined condition, effect, additional/overlapping impact and safe edit target or null. Rule outcome is recorded_applied, recorded_candidate, recorded_skipped or not_recorded; disabled/excluded are separate flags. Never parse translated messages to invent structured predicates.
- Move/generalize current conflict/condition-description construction into ConfiguratorPreviewInspection. ConfiguratorPreviewNotes::build(?ConfiguratorEvaluationResult $result, ?array $inspection = null): array consumes its conflicts and keeps its current report shape/default callers. Pass the once-built report during snapshot assembly, never build it again in render or per inspected Value; no circular dependency or SQL in projection.
- ConfiguratorDefinitionLoader::hasPublicContextCondition(array $conditions): bool becomes public without changing its recursive predicate. Use it once against the active saved/compiled-unsaved definition rows to produce excluded Rule labels for the scope hint and diagnostics. It is pure classification; it does not grant access or change Rule evaluation.
- configuratorPreviewWorkspace(...).save(): Promise<void> collects once, then calls $wire.saveChanges(captures). Toolbar, Review footer and notice Save all invoke this function. It shares the existing generation/collecting/pendingAutomatic protection; do not call a writer if any export rejects or the active operation was cancelled before submission.
- New JS exports normalizePreviewDisplay(value), previewDisplayProfileState(value), and configuratorPreviewDisplay({user, scope}). Normalize defaults/allowlists in sections 2.5–2.7, merge nested metadata patches, map legacy {trace:boolean}, ignore unknown stored fields and clean up listeners. Apply immediately via Alpine/CSS; never queue/send display-setting requests. Scoped inspection events may request local trace visibility. Global theme continues through theme-changed.

Removing the old Preview-only choice form is a bounded rendering change: replace its form method and formState, remove its form-fill calls and Preview's manual cachedSchemas resets, and render the new all-state Blade view or shared catalog component. **Keep both HasActions/InteractsWithActions and HasSchemas/InteractsWithSchemas** for native slide-overs; do not remove framework schema-cache properties or clear the mounted-action schema during a routine display change. ProductConfigurator's engine interaction methods and the public catalog's ordinary rendering remain intact.

### 4.1 Evaluated presentation snapshot and freshness

`App\Livewire\Catalog\ConfiguratorPreview` keeps `#[Livewire\Attributes\Locked] public array $previewView = []`. This is the single currently displayed projection, not a persisted draft, cache, engine DTO, history or a second copy for each source. Existing locked `runtime` and `sourceRuntimes` retain independent choices; captures, inventoryHash and testedFingerprint remain separate. Locking protects transport state; it does not authorize an actor or make old facts current.

Exact top-level shape: `{version:1, product_id:?int, source:'saved'|'unsaved', inventory_hash:?string, evaluation_fingerprint:?string, status:'ready'|'unavailable', is_complete:bool, configuration_code:?string, inspection:InspectionReport, notes:NotesReport, blocking_issues:list<string>, excluded_rules:list<RuleSummary>}`. Projected rows occur once, in `inspection.attributes`; trace/diagnostics occur once inside inspection. Notes retains its compact existing report, using references/identities where practical. Do not serialize the full compiled definition, Product models/facts, services, closures or `ConfiguratorEvaluationResult` alongside this array.

Keep a private `assemblePreviewView(ConfiguratorEvaluationResult $result, ?ConfiguratorDefinition $compiledDefinition, array $excludedRules): array` method on Preview, composing the concrete inspection and notes services. No extra assembler service is needed for this one UI owner. Keep request-local protected `?ConfiguratorDefinition $evaluationDefinition` and `array $evaluationExcludedRules`; clear them at the start of every `input()` including no-Product/invalid-editor paths. Override `evaluate(array $intent): void` narrowly to wrap inherited `parent::evaluate($intent)` in the existing snapshot-isolated read transaction. Within that same transaction, `input()` obtains the active full source data once (strict prepared current data or saved loader draft), derives exclusions and passes that exact data to `forPreview`; retain the returned compiled input before the engine can return definition=null. `evaluated()` assembles previewView from preparedResult plus that request-local metadata. Preserve inheritance and parent behavior for the public catalog; do not broaden parent render's lifecycle.

Fingerprint timing is explicit: clear request-local `?string $evaluationFingerprint` with the other metadata; for a resolvable eligible Product compute the existing read-only fingerprint once inside that same evaluation transaction, before evaluated()/snapshot assembly. Assign previewView.evaluation_fingerprint from this captured value, and use it as testedFingerprint for the strict pre-save evaluation. Remove the old later duplicate fingerprint assignment in rebuildPreview; never capture an earlier test's fingerprint or query again outside the snapshot. No-Product/unavailable data has null fingerprint and cannot save.

The evaluated editor hash identifies the dataset actually used: explicit Test current/Rebuild/pre-save prepares captures and sets request-local evaluationInventoryHash from that prepared inventory. Value choice or legacy refresh over retained previewData carries forward previewView.inventory_hash; Review only changes current inventory, never previewData. Saved source may use null because no unsaved dataset is evaluated. Preserve an established dataset/definition stale flag through choices; a fresh DB fingerprint on a choice must not relabel old previewData as current. Only explicit rebuild/reprepare clears that staleness. This transient hash is not another locked/public evaluated hash.

Explicit evaluation boundaries only: initial mount/Product choice, legal Value selection, explicit Test saved/current/Rebuild/refreshDefinition, fresh current-edits validation before Save, and post-commit saved-preview refresh. A source switch is an explicit test using its retained runtime, not a presentation toggle. Product clear empties both source runtimes and replaces previewView with no-Product status. Compilation failure replaces current previewView with explicit unavailable-current status and preserves full inventory; never retain a saved view labelled current. A newer Review inventory may retain the old test only with “Preview needs rebuilding.”

Preview `render()`, native modal content/footer, canAttemptSave, notices and inspection read previewView. They must never call result(), evaluate(), the definition loader, or rebuild a projection. Strict saving reads the result obtained by the explicit pre-save evaluation in that request, and the writer independently rechecks/recompiles/evaluates under locks. Can-attempt/blocker display and snapshot hashes are not write permission.

Freshness is independent of presentation: existing configurator-updated events mark definitionStale without replacing previewView. `refreshEditorInventory`/Review compares the existing read-only fingerprint against `previewView.evaluation_fingerprint` once per inventory collection when an eligible Product can still resolve; mark stale and invalidate testedFingerprint on mismatch without evaluation. Removed/unassigned Product or missing fingerprint dependency marks stale/unavailable, preserves full Review inventory and exposes a recoverable notice rather than propagating fingerprint()->findOrFail and aborting Review. Test both removed and reassigned Product. Display/resize is entirely local, so a silent external DB change cannot be detected until a relevant event or subsequent server freshness check. Do not imply polling or immediate detection. Rebuild and Save always read current authoritative data.

The shared catalog Blade component adds optional `projectedRows=null`, `inputNamespace='configuration'` and `previewWorkspace=false` while preserving existing `result` calls (`result=null` is accepted only when projectedRows is supplied). At its top, normalize the existing result into the same local row shape when no projectedRows is supplied; use one select/radio loop. Preview supplies `previewView.inspection.attributes`, its presentation-specific namespace and previewWorkspace=true; never construct a fake DTO. Normal catalog callers keep native interactions/default namespace/metadata=false. Projected rows preserve display order, input_type, labels/hints/display_value, legal/hidden/selected flags and stable IDs. Shared selection bindings call the same selectOption action; previewWorkspace routes through the workspace phase guard while ordinary catalog use retains existing behavior. Unavailable rows render an explanation inventory, not a catalog input form.

To switch presentation with zero requests, keep both Preview presentations available against the same snapshot, with distinct presentation-prefixed radio names, DOM IDs and wire keys. Keep the inactive view hidden and inert, exclude its controls from keyboard/accessibility interaction, and bind checked/selected state from the same snapshot so hidden duplicate radio groups cannot uncheck the active selection. No extra evaluator or independent selection owner. Test All→Catalog→All after each legal choice with both select/radio Attributes and assert exactly the correct active control, code and runtime without toggle requests. Existing public catalog keys/names remain unchanged.

### 4.2 One admission/serialization boundary

The existing `configuratorPreviewWorkspace(configuratorId)` remains the browser operation owner. Expose one scoped phase (`idle`, `collecting`, `request`, `reconciling`, `reconcile_failed`, `outcome_unknown`) and a generation counter; do not add a second unrelated choosing/save owner. Product search retains its search request counter and local choosing indicator but `choose`, clear, Enter selection, pointer selection and keyboard paths must obtain workspace admission before `chooseProduct`. Disable the visible controls and guard the methods; disabled markup alone is insufficient.

Serialize Product/source/Value changes, Test/Rebuild, Review inventory collection, native action mount/close updates, inspection updates and cause/editor navigation against collection/save/reconciliation. No delayed Product choice or earlier inspection response may overwrite a newer Save/source state. Block competing state changes while busy, preserve the newest requested automatic refresh for after the barrier, and invalidate pre-submission work when leaving Preview. Local display/theme/resize remains usable. Closing a modal may close locally, but queue its server unmount update until the active request settles. Once writer submission occurred, tab departure cannot cancel commit or reconciliation.

Discriminating race: hold one editor export promise unresolved, attempt Product clear/choose/Enter plus source/navigation/inspection requests, then release it. The accepted Product, source, captures and writer inputs remain those admitted for the collection; no second operation enters. Also test a Product choice started first, then Save: Save waits until that choice settles before collecting. A generation guard after export alone does not fix concurrent response overwrites.

### 4.3 Commit, acknowledgment and reconciliation

After the writer returns successfully, immediately retain `#[Locked] public ?array $saveReceipt` with one operation identity, committed captures, returned newRules map and status; overwrite/clear it only after all involved editor acknowledgments. The collector records the participating editor component IDs. Dispatch configurator-preview-saved with the existing configuratorId/captures/newRules and additive optional operationId. Emit/retain this receipt before any fallible post-save reload/projection. Separate “Saved; preview refresh failed” from rejected validation and continue reconciliation even if rebuilding saved preview fails. Retry view refresh independently of writing.

Minimal coordinated changes to the three existing `acceptPreviewSave` handlers: accept optional operationId, keep one locked last-applied operation ID, preserve existing normalization/rebase/new-Rule remap code, and dispatch scoped `configurator-preview-editor-reconciled` only after success. Repeated receipt identity acknowledges without applying/rebasing twice. No domain DTO/event class/queue/table is needed. Legacy callers without operationId keep current behavior. Workspace waits for acknowledgment from the collected mounted editors; unrelated/wrong Configurator/component/operation acknowledgments are ignored. An editor removed after collection is handled as an explicit refresh-needed failure, never silently treated as acknowledged.

On failed/missing acknowledgment, hold reconcile_failed, retain receipt, block Save/automatic recollection and offer Retry editor refresh. Retry invokes existing acceptPreviewSave only for pending editors with the same successful captures/map/operationId; never saveChanges/the writer. After all acknowledgments, recollect once, update inventory, release the barrier and process pending automatic refresh. Later typed text survives through existing rebasing. Any ambiguous transport failure after submission becomes outcome_unknown: halt automatic write retries and reconcile authoritative saved state before a new user-initiated Save. A locked Livewire receipt lives in the successful client snapshot; it cannot recover a response lost after commit. No durable receipt store is introduced or promised. This is bounded per-page coordination, not exactly-once semantics across reloads/lost commit responses.

### 4.4 Class, namespace and discovery decisions

| Kind | Exact decision and justification |
| --- | --- |
| New Service | `App\Services\ConfiguratorPreviewInspection`, final plain concrete class with `build()` above and private pure indexing/formatting helpers. Cohesive read projection; no SQL/Livewire/Filament dependency or mutable singleton cache. Existing array-shape conventions apply. |
| Existing Services | Retain `App\Services\ConfiguratorEditorChanges`, `ConfiguratorPreviewNotes`, `ConfiguratorDefinitionLoader`, `ConfiguratorDefinitionCompiler`, `ConfiguratorEngine`, `ProductFields`, `CatalogRevisions` and their contracts. Narrow additions only; no namespace moves. |
| Application Actions | Retain `App\Actions\SaveTestedConfiguratorChanges` → `SaveConfiguratorDefinition`; no new Save/Review application Action. These own writes/transactions; UI actions only coordinate them. Shared writer edits require demonstrated failing tests and owners' agreement. |
| Filament Actions | Keep two component-local `Filament\Actions\Action` factories for Review/notices, plus one footer Action configured below. Three small configurations do not justify separate classes, subclasses or a generic action framework. |
| Helper | Snapshot assembler and pure projection formatting stay private methods of their actual owner. No global Helper/ProductFacts utility or repository wrapper. |
| DTO/value object | Reuse existing `App\DTO\ConfiguratorEvaluationInput`, `ConfiguratorEvaluationResult`, `ConfiguratorDefinition` and Rule DTOs request-locally. No new DTO, Wireable implementation, synthesizer or persisted draft contract. PHPDoc shapes define UI projection/receipt boundaries. |
| Enum | Reuse installed engine enums. UI state/outcome/phase vocabularies stay bounded normalized strings in documented array/JS contracts; no database cast or new enum for every label. |
| Trait/inheritance | Retain `ConfiguratorPreview extends ProductConfigurator`; retain Filament HasActions/HasSchemas and their traits. No new trait, macro, base workspace or copied framework internals. |
| PHP attributes | Reuse `Livewire\Attributes\Locked` for snapshot/receipt/IDs/captures, existing `On` for events and `Renderless` for search/navigation-only methods. No custom PHP attribute, Laravel Bind/UsePolicy change or policy reorganization. |
| DI/container | Concrete services auto-resolve through the existing supported container/action pattern. No new interface/binding/provider/singleton/scoped lifetime. Do not store resolved service instances in public component state. |
| Discovery/registration | App\\ PSR-4 and existing Livewire alias/Filament panel/resource discovery remain unchanged. Register only the new Alpine display export in resources/js/app.js; reuse existing browserProfile/theme hooks. Blade partials are explicit includes. No routes/events/listeners/commands/jobs/providers/policies/resources/models to discover. |
| Model/schema | Existing Configurator/Product/Group/Attribute/Value/Rule models only; no new columns, relations, casts, factories or seeders. Disposable test fixtures use existing factories. |

## 5. Native action and display field specifications

All server actions below authorize manage-catalog at invocation. A visible/disabled control is not authorization.

| Action | Component / configuration | Location, visibility and behavior |
| --- | --- | --- |
| Review changes | Filament\Actions\Action::make('reviewChanges')->authorize('manage-catalog')->slideOver()->modalContent(...)->modalSubmitAction(false)->extraModalFooterActions([...])->modalCancelActionLabel('Close')->stickyModalHeader()->stickyModalFooter(); no schema | Unsaved group and notice CTA, always visible. Can show empty/invalid inventory. Opening, Escape and Close never save/discard. Extra footer `Action::make('savePreviewChanges')->label(...)->alpineClickHandler(developer-defined scoped dispatch)` requests the shared JS Save; default native Close remains. No action callback/submit writer. |
| All notices | Filament\Actions\Action::make('previewNotices')->slideOver()->modalContent(...)->modalSubmitAction(false)->modalCancelActionLabel('Close') | Reserved notice slot; visible when notices exist. Read-only ordered list, scoped editor/explanation links. |
| Save changes | Toolbar x-filament::button type=button primary x-on:click=save(); Review native extra footer Filament\Actions\Action as above | Disabled for no changes, no eligible Product, busy/reconciliation/unknown outcome or known fresh current-preview blockers. All locations invoke the shared collector. No requiresConfirmation or relationship-bound schema. |
| Test source / rebuild | Existing x-filament::button/icon-button with aria-pressed/accessible labels; custom Livewire methods through JS collection | Testing group. Disabled while collection/save is in flight. No writes. |
| Explain / clear inspection | Existing x-filament::icon-button/button; custom Livewire methods | Cards/Attribute labels/inspection header. Read-only scoped target; rejected target reports recoverably. |
| Edit cause | Existing components/catalog/configurator-preview-cause | Each fixable cause. Reuse existing openSource; whole-form capture happens before editor navigation. |

Docs for all native actions: https://filamentphp.com/docs/5.x/actions/modals and https://filamentphp.com/docs/5.x/components/button. Native action content is escaped Blade; no raw HTML from editor state or Rules.

Display fields are labelled native HTML inputs inside the existing Filament Blade dropdown pattern: checkboxes for Code, ID Name, notes, diagnostics and density; selects for exact label-size sets; two presentation buttons; global Light/Dark buttons. Component: native input/select/button (no Filament form schema); Docs: https://filamentphp.com/docs/5.x/components/dropdown; Validation: local strict allowlists/booleans/integer sizes from section 2.5, safe stored-data fallback; Config: Alpine bindings to the single display owner, type=button, no wire:model/live/setDisplaySettings or relationship binding. Preserve Escape, focus return, click-away/menu scrolling; density/panel changes keep the menu open. Review Save must appear in native footer actions: modalContent/modalContentFooter are scrollable body content even with stickyModalFooter().

## 6. Implementation tasks and checks

### Task 0 — Establish the execution boundary

**Files:** no application edits. Read this plan, AGENTS.md, current applicable .ai/rules, current scoped diffs and latest package versions.

- [ ] Record current HEAD, branch, worktrees and exact dirty paths. Coordinate any overlapping Preview/runtime edits before starting. This plan's snapshot is not permission to overwrite a newer checkout.
- [ ] Confirm the browser/Herd URL serves the checkout being edited. Do not run a development web server for this Laravel app.
- [ ] Run the existing affected PHP/JS baseline, using the commands in section 7. Report pre-existing failures separately. Do not install a browser-test plugin or point tests at the development database.
- [ ] Refresh relevant docs only if the installed versions/API assumptions changed. Keep the existing no-push boundary.

**Deliverable:** a reproducible baseline and protected file allowlist, before runtime work.

### Task 1 — Reviewable change inventory in every state

**Files:** ConfiguratorEditorChanges.php; ConfiguratorPreview.php; ConfiguratorEditorChangesTest.php; ConfiguratorPreviewWorkspaceTest.php.

**Consumes:** existing signed capture and exportPreviewChanges contracts. **Produces:** inspect and refreshEditorInventory from section 4.

- [ ] Add a failing feature test with changed Overview description, changed Value hint and an incomplete new Mapping Rule. Assert three review rows and a retained temporary Rule ID; a subsequent failed current-edits rebuild exposes the located Rule error while all three rows remain. Saved-source Review can show the inventory without claiming it is validated. Saved models remain byte-for-byte equivalent through the definition loader.
- [ ] Add cases for saved-source inventory, no-op normalized blanks, an external field already equal to the intended value, stale changed fields, invalid signature, duplicate identity, foreign Configurator, missing owner row and existing capture/row limits. Tampering cannot yield a partial accepted inventory. Test that collecting a different inventory marks an old current test stale and permits a new validation attempt; identical exports do not invalidate it.
- [ ] Run only those tests and confirm the new expectations fail for the missing inventory behavior, then implement the read-only split without weakening prepare.
- [ ] Make rebuildPreview retain the inventory after compilation failure. Keep saved-source rendering independent of inventory errors, and current-edits rendering explicit about invalid data.
- [ ] Rerun the two affected feature files; verify no database writes from inventory or Review. Check formatting/whitespace before treating the task as complete.

**Acceptance:** every gathered change can be reviewed even when it cannot compile; strict preparation and writer tests still pass.

### Task 1B — Make hydration preserve the tested presentation

**Files:** ConfiguratorPreview.php; new ConfiguratorPreviewInspection.php skeleton/projection; ConfiguratorPreviewNotes.php only as required; workspace/projection tests. **Prerequisite:** Task 1; implement before new modal/display requests depend on snapshot behavior.

- [ ] Add a failing behavioral hydration test: evaluate saved source with A selected and exact code/trace; mutate a saved Rule or Product availability so a new evaluation selects B; exercise display roundtrips and native Review/notices/inspection. Assert exact previewView, code, trace and both sourceRuntimes stay unchanged. Review's freshness check marks stale. Explicit Rebuild then applies B and replaces the snapshot. Browser-only display alone cannot discover the silent external mutation.
- [ ] Add current-source inventory freshness test: newer captures collected by Review retain old result only with stale indication; unchanged captures leave it fresh; new invalid current compilation replaces the view with unavailable while all inventory survives. An old current error must not block attempting validation of newer inventory.
- [ ] Evaluate current capture A, Review collects newer B, then make a legal choice/legacy refresh using retained previewData. Assert snapshot inventory hash still names A and the stale label remains; explicit Test current/Rebuild B then updates the hash/rows and clears only resolved staleness. No latest inventory hash may relabel old evaluated data.
- [ ] Add inactive included Attribute and inactive/hidden default tests where engine result.definition=null but input.definition exists. Inspection retains original identities/lifecycle provenance as unavailable, legal=null; no selected/legal state or configuration code. A subsequent no-Product/invalid-editor evaluation must not reuse prior request-local metadata.
- [ ] Implement the exact snapshot/read-transaction/metadata lifecycle in section 4.1. Assert repeated native actions do not call evaluate or append projections; use behavior/dependency instrumentation available in existing tests, not a new production interface merely for a spy. Keep parent public catalog behavior.

**Acceptance:** hydration cannot silently replace a test; unavailable lifecycle inventory is truthful and complete when compilation succeeded.

### Task 2 — Immediate validated Save through one path

**Files:** ConfiguratorPreview.php; configurator-preview-workspace.js; configurator-preview-changes.blade.php; ConfiguratorPreviewWorkspaceTest.php; ConfiguratorEditorChangesTest.php; ConfiguratorPreviewWorkspace.test.js. SaveTestedConfiguratorChanges.php is conditional on a demonstrated test failure.

**Consumes:** Task 1 inventory, Task 1B snapshot; current source runtimes and existing writer. **Produces:** one Save path plus commit/reconciliation barrier in sections 2.3/4.3.

- [ ] Replace obsolete required-confirmation behavior assertions without deleting regression coverage. Through Livewire::test(ConfiguratorPreview::class), call saveChanges with a changed description capture, assert committed outcome/receipt, fresh saved description, saved snapshot and one configurator-preview-saved event. Counts become fresh only after acknowledgments and recollection. Review is absent unless intentionally mounted; close it only if still the mounted action.

- [ ] Add discriminating cases: saved source legal/current edits all-Values-hidden; invalid current Rule; expected replacement but otherwise complete; no Product; no pending change; revoked permission; Product outside assigned eligible Groups. Every denied save has zero definition/revision writes and no success event.
- [ ] Retain the existing stale-fingerprint assertion at the writer boundary. Deterministically change a Product fact/definition after the pre-save evaluation but before handle; assert full rejection. Keep existing editor reconciliation tests proving a later typed hint/Rule label survives the successful collected save.
- [ ] Add JS tests that Save recollects after Review, never calls the server after one export fails, ignores double clicks, and does not write after leaving Preview during collection. Once the transactional method has been submitted, do not pretend leaving the tab cancels a committed operation; reconciliation must still run.
- [ ] Delay/fail a new Rule editor's reconciliation response after commit; attempt Save/reenter Preview. Assert no second writer call, transaction/new Rule or automatic recollection until acknowledgment. Retry only pending acceptPreviewSave calls with the returned mapping; one applied receipt, preserved later text and eventual current inventory. Include stale/wrong acknowledgment identities and an acknowledgment lost after a successful handler so an idempotent retry cannot rebase twice.
- [ ] Force post-commit saved-preview reload/projection failure. Assert the definition is written once, commit receipt/captures/map still reach reconciliation, later typed fields survive, and feedback says saved/refresh failed. Retrying preview/editor refresh never invokes the writer. A pre-commit validation error has no success event or revision mutation; no test assumes every definition Save advances catalog_revision.
- [ ] Implement the shared Save method and success/failure sequence. Remove the reviewed-fingerprint permission dependency and old confirmation entry, update stale helper copy and preserve existing new-Rule mapping/rebase behavior.
- [ ] Run the affected PHP/JS files and current editor-navigation tests. Confirm toolbar and Review Save use the same collector/writer path.

**Acceptance:** one click writes only a freshly validated complete capture, or writes nothing and retains every draft. Optional Review cannot authorize an old capture.

### Task 3 — Grouped controls, optional review and stable notices

**Files:** configurator-preview.blade.php; new controls/notices views; changes view; workspace JS; lang/en/configurator.php; theme.css; workspace PHP/JS tests.

**Consumes:** Tasks 1–2. **Produces:** the action inventory and notice hierarchy in section 2.

- [ ] Add focused tests for Review opening on invalid/conflicting data, source/choice preservation on open/close, and the notice headline remaining an error while a selection replacement is informational. Assert content/behavior, not Filament's generic modal implementation.
- [ ] Implement native reviewChanges and previewNotices slide-overs with read-only content. Review Save uses extraModalFooterActions with a developer-defined scoped alpineClickHandler dispatch and default Close. No relationship-bound schema or callback can persist editor data implicitly. Verify both actions remain sticky with a long invalid inventory at small viewport/zoom.
- [ ] Build Product/Testing/Unsaved groups, permanent count/status lines and the single top notice slot. Move detailed conditional full-width errors into the notice/inspection surfaces; remove the old separate reserved feedback gap.
- [ ] Reuse autocomplete, default Product, catalog destination tooltip, source toggles and Rebuild. Save from Test saved makes the current-edits validation transition explicit.
- [ ] Update Product search JS/partial and tests: hold an export promise; attempt clear, choose via pointer/Enter, source change and cause/editor navigation. Shared admission blocks all competing changes. Start a Product choice first, then Save; collection waits for it. Serialize native modal/inspection requests too; no old response overwrites a newer source/Save result.
- [ ] Visually check empty, pending, collecting, invalid, conflict, success, replacement and stale states at 390/768/960/1440 px, both themes. Confirm appearance/removal of notices does not move the configuration's initial top position at a fixed viewport.

**Acceptance:** actions are discoverable by purpose; errors and changes remain reviewable without introducing page jumps or a required confirmation.

### Task 4 — All-state cards and actual catalog presentation

**Files:** ConfiguratorPreview.php; new values view; configuration-fields.blade.php; preview composition; theme.css; workspace/runtime tests; initial projection tests in the new inspection test file.

**Consumes:** evaluated definition and current per-Attribute state. **Produces:** one runtime with two presentations.

- [ ] Add tests with one inapplicable Attribute; one selected, one other legal, one disabled and two hidden Values with distinct display orders. Assert the full inventory and stable hidden-last order in All states; Catalog result omits only hidden/inapplicable records and retains disabled choices.
- [ ] Test both native select and radio Catalog input types, dynamic labels/hints/display values, and absent semantic IDs. The shared catalog component's default props must still omit codes/IDs, and its existing runtime tests must pass unchanged in behavior. Add saved/new-unsaved global-context Rule exclusions and verify an ordinary Attribute named Territory is still evaluated as a selection source.
- [ ] Implement cards and the projectedRows adapter exactly as section 4.1. Remove only Preview choice form, formState/form-fill calls and manual cache resets. Retain native action schema contracts/traits, public engine methods and mounted-action state. Existing catalog result/default props/native select/radio tests remain unchanged in behavior; Preview never obtains a fresh result merely to render Catalog result.
- [ ] Add source × presentation roundtrip tests: change a choice in saved; change another in current edits; flip All/Catalog repeatedly. Each source's choice, code, captures and inventory remain the same as before its presentation switch.
- [ ] Verify disabled/hidden cards cannot submit a selection, while their sibling Explain actions remain keyboard reachable. Display actual hints with distinct help affordances.

**Acceptance:** all-state visibility is inspection only; catalog behavior and legal-selection rules do not change.

### Task 5 — Query-free contextual explanation projection

**Files:** new ConfiguratorPreviewInspection.php; ConfiguratorPreviewNotes.php; new inspection Blade; preview cause component; ConfiguratorPreview.php; new projection tests and existing notes tests.

**Consumes:** the current evaluation result/definition/trace; safe existing editor targets. **Produces:** the projection and inspector interface in section 4.

- [ ] Add pure fixture tests for initial hidden/disabled flags, lifecycle/canonical unavailability, whole-Attribute hiding, Mapping set restrictions, AllowOptions, selection replacement, unavailable configuration and no legal Values. Keep existing report contracts for conflict editor targets.
- [ ] Use a four-Value case: Metal Shell hides B/C/D; 1-inch also hides B/C/D; USA hides A/D. Assert no legal Values, three contributing causes, and zero additional removals for the overlapping 1-inch effect after Metal Shell. A priority change does not make any Value legal.
- [ ] For presentation rules, use priorities 3 and 8 with different hints, then two priority-8 different hints. Assert the winner in the first case, the existing tie/base outcome in the second, and saving blocked only for the conflict case. Include Attribute- and Value-scoped effects.
- [ ] Index trace by Attribute/Rule/Value once per build. Generalize existing conflict causes rather than reimplementing conditions. Test unmatched presentation-only Rule=not_recorded, definition-disabled separately, restriction skipped trace, several presentation effects, excluded saved/new unsaved global-context Rules. Produce truthful outcomes/overlap/safe editor links; no invented match/failing nested predicate or translated-message parsing.
- [ ] Keep selection-only explanatory notes separate from product/mixed/system causes. Add Explain effect links and render the inspector's ordered sections. Preserve selected-ID disclosure open state.
- [ ] Run projection, notes and workspace tests. Build inspection once at explicit evaluation and reuse snapshot rows/notes/trace. Assert build executes zero SQL, two different inspection targets add no per-Value queries, and repeated render/action interactions do not append histories/duplicate projections or grow public-state shape.

**Acceptance:** an operator can explain selected, available, disabled and hidden states, see every conflict contributor and open the correct existing editor without losing work.

### Task 6 — Display dropdown and responsive panel combinations

**Files:** new configurator-preview-display.js; app.js; admin-profile.js; workspace-split.js/splitter view; controls/composition/values views; ConfiguratorPreview.php compatibility wrappers only; theme.css; display/WorkspaceSizing/AdminProfile/workspace tests.

**Consumes:** Tasks 3–5 and browserProfile. **Produces:** the exact preferences/defaults/layout matrix in sections 2.5–2.7.

- [ ] Write JS tests for defaults, legacy trace migration, per-presentation metadata, nested patch merging, invalid keys/types/sizes/ratios, corrupt/oversized/blocked storage, user/page isolation, listener cleanup, display during Save and zero server requests for all display/resize controls.
- [ ] Implement one normalized local profile/controller. Remove the Preview-only trace adapter only after migrating its caller; do not change unrelated profiles. No Livewire setting patch/entanglement. Scoped Explain reveal events update that same local owner.
- [ ] Add notes/diagnostics show-hide, density and exact label sizes in Display. Global Light/Dark uses the existing theme event. Keep the menu open through density/layout changes; reposition or scroll it within the viewport.
- [ ] Implement section 2.7's single primary divider, exact minima/feasibility/defaults, equal combined side panels and independent stored ratios. Add Preview options to existing splitter while keeping Attributes/Rules default behavior/tests. Pointer release saves once, cancel/Escape restores, keyboard/RTL/Home/End/Enter/double-click work, observers/listeners clean up. No alternate Actions-layout/design switch.
- [ ] Test all four visible panel combinations, threshold/min-width clamping, temporarily stacked preference retention, hide/restore/reload, reset and unrelated morph. Long pointer/keyboard resize sequence proves zero Livewire/evaluator/capture/writer calls and unchanged source/choices/code/drafts. Include existing WorkspaceSizing tests when extending shared splitter.
- [ ] Verify 390/768/960/1440 px, 200% zoom, long labels/codes and open menus in both themes. Check horizontal overflow, heading/code wrapping, menu reachability, focus, contrast and stable selected-ID disclosure.

**Acceptance:** display changes persist safely and never become unsaved definition changes or reset test choices.

### Task 7 — Integrated editor navigation and stress verification

**Files:** workspace/editor-change/runtime tests; workspace JS tests; implementation files only for demonstrated regressions. Screenshots/results go in this chat's visualization attachment directory, not application data folders.

**Consumes:** all preceding tasks and existing editor collection/navigation. **Produces:** verified end-to-end behavior with documented limits.

- [ ] Extend existing navigation tests for Explain -> Edit Rule and Explain -> Edit Value, lazy editors, an unavailable target, manual tab changes during collection, a Product switch, and a successful Save while another editor retains a later change.
- [ ] Run the full checks in section 7, after focused checks pass. Do not claim the full suite or MySQL checks passed unless their actual command output confirms it.
- [ ] In the existing guarded disposable MySQL test setup, use two independent connections/processes and deterministic barriers to overlap tested Preview Save with actual SaveCatalogProduct and relevant Group/Configurator writers. Exercise the verified opposite lock acquisitions, not sequential stale calls. Require bounded retry success or complete stale rejection, no partial definition/revision writes, no duplicate new Rule/event and preserved authoritative Product profile. An unrun case remains a named gate. A reproduced failure is coordinated with writer owners before changing lock order; do not infer deadlock from source or claim SQLite proves safety.
- [ ] Browser-check both source modes × both presentations; Review open/Close; validation failure and direct Save; successful Save/reload; no legal Values; expected replacement; stale saved data; Product change/clear; and all panel combinations. Use a dedicated explicitly disposable local fixture for actual save/failure exercises, retain the existing real Configurator, and restore approved fixture state afterwards.
- [ ] Complete 100 presentation switches, 100 legal Value choices, 40 panel/metadata changes, 20 source switches, 20 Product switches, 20 Explain/editor roundtrips and 20 Review open/close operations. Add 10 rapid/double-click Save attempts split across valid, invalid and conflicting fixture states. Test that one in-flight click sequence produces at most one successful transaction/event; preserve later edits and no duplicate new Rules.
- [ ] Add 100 pointer/keyboard resize operations across visible combinations, including pointercancel/Escape/reset/RTL, reload/morph, storage failure and resizing during Save. Record browser timings, request counts/transferred bytes, hydrated/teleported DOM, listeners and public-state shape separately from PHP queries/render time. All states DOM grows linearly with inventory by design; per-click accumulation and repeated hidden database work are failures. Check no duplicates, uncaught errors, unexpected saves/overflow. Investigate reproducible p95 regression >20% against a named same-data baseline; no invented universal speed/heap cap.
- [ ] Report each remaining issue and its saved-data impact. Stop before committing/pushing or deploying unless separately authorized.

**Acceptance:** the new presentation remains usable under repeated interaction, and the existing safe editing/persistence guarantees hold.

### Task 8 — Reconcile the documentation and hand off

**Files:** this plan and PREVIEW_TEST_WORKSPACE_STRUCTURE_AUDIT.md only. Exclude docs/README.md and every other chat's plan.

- [ ] Record historical deferred/confirmation contract discrepancies here; coordinate any later edits to IMPLEMENTATION_GUIDELINES.md, contracts/FILAMENT_ADMIN.md or CONFIGURATOR_WORKSPACE_IMPLEMENTATION_PLAN.md separately with their owners. This task does not authorize those edits.
- [ ] Mark only completed tasks as complete. Attach exact checks, browser fixture identity/cleanup, known Rules conflicts, unrun checks and any local-only data; do not describe the mockup's simulation as application verification.
- [ ] Self-review requirements against tasks and report the exact implementation file allowlist. A future commit must be separately authorized, reviewed and scoped; no automatic push/deployment.

## 7. Commands and verification prerequisites

Only run these at implementation time. Existing tests use Livewire\Livewire::test and Tests\TestCase; Feature tests use RefreshDatabase. Standard PHP tests use SQLite :memory:, and the guarded MySQL suite uses its explicit local test database. No browser-test dependency is installed; use the available browser-use tools for real browser verification.

**Preflight:** composer show --direct; inspect package.json; git status --short --branch; git worktree list; git diff --name-only; re-read current AGENTS/path rules. Resolve the exact Herd URL with Boost get-absolute-url.

**New files:** use native generators where applicable, after inspecting command help. Existing-file changes need no resource/page scaffold.

```sh
php artisan make:class Services/ConfiguratorPreviewInspection --no-interaction
php artisan make:test ConfiguratorPreviewInspectionTest --unit --pest --no-interaction
php artisan make:test Catalog/MySql/ConfiguratorPreviewSaveConcurrencyTest --pest --no-interaction
```

Use Pest syntax and uses(Tests\TestCase::class) following adjacent Unit tests. The installed make:test --help confirms both --unit and --pest. Plain Blade/JS files use normal file creation in the paths above; do not generate new Livewire components or resources.

**Focused PHP:**

```sh
php artisan test --compact tests/Feature/ConfiguratorEditorChangesTest.php tests/Feature/ConfiguratorPreviewWorkspaceTest.php
php artisan test --compact tests/Unit/ConfiguratorPreviewNotesTest.php tests/Unit/ConfiguratorPreviewInspectionTest.php
php artisan test --compact tests/Feature/Catalog/ConfiguratorRuntimeTest.php
```

**Focused JavaScript:**

```sh
node --test tests/Unit/ConfiguratorPreviewWorkspace.test.js tests/Unit/ConfiguratorPreviewDisplay.test.js tests/Unit/AdminProfile.test.js tests/Unit/ConfiguratorProductSearch.test.js tests/Unit/WorkspaceSizing.test.js
```

**Final gates:**

```sh
php artisan test --compact
vendor/bin/pest --configuration=phpunit.mysql.xml --compact
npm test
npm run build
vendor/bin/pint --dirty --format agent
git diff --check
```

Run vendor/bin/filacheck --fix --dirty if implementation changes any file under app/Filament/. Inspect its diff, retain the existing tableFilters/FilaCheck exception and resolve remaining findings. Before any --dirty fixer, verify that its affected PHP files are within this task's protected scope; coordinate/isolate overlapping unrelated work rather than formatting it. Do not add unrelated code fixes to satisfy a baseline failure.

After affected feature checks pass, ask the operator to run the complete `php artisan test --compact` suite per AGENTS; report exactly which complete/focused checks actually ran. For the named concurrency case, first run `vendor/bin/pest --configuration=phpunit.mysql.xml --compact tests/Feature/Catalog/MySql/ConfiguratorPreviewSaveConcurrencyTest.php`, then the relevant guarded MySQL suite if its prerequisites are available.

Use the existing MySQL bootstrap safety guards and configured local test credentials. If unavailable, report the missing check and repair only an explicitly disposable test setup; never fall back to the development or remote database. Browser tests that save use an explicitly disposable local fixture, not the real review record or Forge. No remote production save is part of this plan.

## 8. Completion record and self-review

Planning checks completed on 2026-10-23: current Preview/source/editor-export/writer/notes/engine/profile/test interfaces inspected; native modal/lifecycle APIs checked against Boost documentation and installed source; installed Blueprint overview/actions/testing/checklist applied; independent structure/Livewire/Filament/Laravel/deep-architecture reports reconciled. The selected mockup/baseline browser review belongs to the preceding review, not proof of this unimplemented plan. The present repository edit allowlist contains this plan and its structure audit only.

Implementation, automated tests, fixture writes, new browser stress runs, commits, pushes and deployment have **not** been performed by writing this plan.

Plan review checklist:

- [x] Immediate Save and optional Review are separate interactions sharing one write path.
- [x] Invalid/stale inventory, saved/current asymmetry, Product membership and evaluation-to-write races have discriminating tests.
- [x] All Values inspection, Catalog result, metadata, notes, diagnostics, labels, stable notices and editor links map to tasks.
- [x] Restriction accumulation and presentation priorities retain the current evaluator policy.
- [x] File responsibilities, method names, native action configuration, authorization and test prerequisites are specified.
- [x] Hydration snapshot/unavailable inventory, truthful trace, Product admission, post-commit recovery/reconciliation and local resize have explicit discriminating checks.
- [x] Services/Actions/helpers/DTOs/enums/traits/attributes/container/discovery decisions are explicit; every retained/new boundary has a reason.
- [x] Structural actions remain immediate; alternative gallery designs and review-site controls are excluded.
- [x] Other chats' changes, local images, no-push boundary and the distinction between planning and implementation are explicit.

Next stage: operator review of this plan. Implementation requires a separate instruction.
