The deeper audit confirms that **responsibility boundaries deserve more attention than file moves**. The application already uses Laravel, Livewire, and Filament patterns effectively; the main problems arise where new workflows cross those boundaries.

I reviewed the clean checkout at `dd1e2e5`, including the newer Preview/Test, rule exchange, tag workspace, and table-profile code. Application PHP files increased from **188 to 227**. Installed versions remain PHP 8.4, Laravel 13.35, Livewire 4.4, and Filament 5.9.

This is a source-and-test review, supported by three parallel audits and a second pass challenging their recommendations. **No files changed, tests ran, or database operations occurred.**

The refreshed research supports these principles:

- Extract meaningful operations so controllers, Livewire components, imports, and commands can reuse the same behavior. Services and Actions are organizational choices, rather than special Laravel class types. [Laravel Daily: Service or Action](https://laraveldaily.com/lesson/laravel-projects-structure/save-data-service-action).
- Preserve cohesive static factories for Filament schemas, tables, fields, and actions. Both official documentation and the FilamentExamples source snippets use them. [Filament code-quality guidance](https://github.com/filamentphp/filament/blob/5.x/docs/03-resources/13-code-quality-tips.md), [reusable invoice wizard](https://filamentexamples.com/project/reusable-filament-wizard-steps-for-invoice-creation).
- Introduce abstractions when complexity, reuse, or independently testable behavior justifies them. [Laravel Daily: avoiding over-engineering](https://laraveldaily.com/lesson/laravel-projects-structure/when-not-to-extract-avoiding-over-engineering).
- Treat file locations as navigation conventions. Laravel permits custom organization when Composer can autoload it; the current namespaces are valid. [Laravel directory structure](https://github.com/laravel/docs/blob/13.x/structure.md).

I used accessible lesson material, official documentation, installed vendor source, and FilamentExamples MCP code results. I have not treated inaccessible course sections as reviewed evidence.

The following are the substantive recommendations for your approval. Priority describes the value of addressing the issue, not a claim that it caused a production incident.

1. **R01 — Give Group presentation edits a narrow write boundary. High priority.**

   [BatchCatalogChanges.php:141](/Users/studioycm/Herd/configurator/app/Actions/BatchCatalogChanges.php:141) still calls `GroupForm::settingsState()`, then submits filters, presets, and presentation settings through `SaveGroupSettings`.

   This is more consequential than the backwards dependency alone. The form includes saved vocabulary entries, while [SaveGroupSettings.php:82](/Users/studioycm/Herd/configurator/app/Actions/SaveGroupSettings.php:82) rejects values absent from current Products. A presentation-only batch can therefore fail because an untouched filter became stale after import.

   **Recommendation:** update only the reviewed presentation fields under the existing locking, validation, and revision contract. Keep form serialization in the UI adapter.

   My earlier suggestion to extract a general `GroupSettingsData` service was insufficient: moving the same round-trip would preserve the underlying problem. Retaining vanished saved values remains separate pending Product-data work.

2. **R02 — Establish consistent transaction ownership and lock ordering. High priority.**

   The new tested-save path locks the Configurator, canonical references, Product, Group, and global context. Product editing locks Groups before Product; assignment and status operations lock Groups before Configurators.

   See [SaveTestedConfiguratorChanges.php:22](/Users/studioycm/Herd/configurator/app/Actions/SaveTestedConfiguratorChanges.php:22), [ConfiguratorEditorChanges.php:178](/Users/studioycm/Herd/configurator/app/Services/ConfiguratorEditorChanges.php:178), and [SaveCatalogProduct.php:30](/Users/studioycm/Herd/configurator/app/Actions/SaveCatalogProduct.php:30).

   These opposing orders create possible lock cycles. Some outer operations retry; others do not.

   **Recommendation:** agree on the locking contract before extracting additional writers, and verify opposing writer combinations on MySQL. This is a concurrency risk identified from source; a deadlock was not reproduced.

3. **R03 — Finish extracting configurator business operations from relation managers. High priority.**

   The relation managers still allocate ordering positions, validate ownership and rule-kind changes, transform aggregate drafts, and include/remove nested records.

   Examples include [AttributesRelationManager.php:509](/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/RelationManagers/AttributesRelationManager.php:509) and [RulesRelationManager.php:359](/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/RelationManagers/RulesRelationManager.php:359).

   **Recommendation:** move these meaningful operations behind application Actions while retaining schema state, selection, notifications, and field-error translation in Filament. Continue routing persistence through `SaveConfiguratorDefinition`.

   `SaveConfiguratorOption` already demonstrates the useful boundary. The need is stronger now because ordinary editors and Preview/Test modify the same aggregate.

4. **R04 — Separate tested-change preparation from its presentation, and pass actor context explicitly. High priority.**

   [ConfiguratorEditorChanges.php:35](/Users/studioycm/Herd/configurator/app/Services/ConfiguratorEditorChanges.php:35) combines signed capture verification, normalization, three-way merging, compilation, persistence fingerprints, database label lookup, and formatted confirmation/error messages.

   Its caller accepts a `User`, but verification uses ambient `Gate` and signatures use `auth()->id()`.

   **Recommendation:** separate change/error formatting from preparation and conflict decisions; make signing and verification consistently actor-aware. A focused capture/result type may help, while Livewire payloads can remain serialized arrays.

   This is a hidden-context and maintainability problem. I found no confirmed cross-user or stale-fingerprint bypass.

5. **R05 — Remove the shared tag UI’s dependency on the Values resource. Medium priority.**

   [ValueTagFilter.php:15](/Users/studioycm/Herd/configurator/app/Filament/Resources/ValueTagFilter.php:15) and the shared search trait call `ValuesTable::tagFilterField()`.

   A reusable filter now depends on a particular resource table, and removed-tag fallback behavior is duplicated.

   **Recommendation:** extract one shared tag-choice field/options adapter, consumed by both the resource and reusable filters. A static factory is appropriate.

6. **R06 — Split the capabilities hidden inside the search trait. Medium priority.**

   [InteractsWithScopedTableSearch.php:10](/Users/studioycm/Herd/configurator/app/Filament/Resources/InteractsWithScopedTableSearch.php:10) has grown to include tag forms, AND/OR operators, deferred-filter reconciliation, batch-preview state, browser-profile restoration, session behavior, column reset, count previews, and search.

   Components resolve conflicts through increasingly long trait precedence declarations.

   **Recommendation:** separate cohesive table capabilities and make their host requirements clear. Preserve the distinction between applied quick filters and pending advanced filters.

7. **R07 — Reduce `TablePresentation` to explicit table composition and visual defaults. Medium priority.**

   [TablePresentation.php:28](/Users/studioycm/Herd/configurator/app/Filament/Resources/TablePresentation.php:28) configures business actions, selection, search, filters, widths, ordering, toolbars, and persistence. It also infers tag relationships from column names and list-key strings.

   **Recommendation:** let each table explicitly declare supported capabilities and relationships, then compose focused configurators. Retain a small common presentation preset.

   The objective is predictable behavior, rather than splitting one class into numerous equally coupled helpers.

8. **R08 — Narrow the application’s overrides of Filament internals. Medium priority.**

   Installed-source comparison found:

   - `WorkspaceTableSelect::toEmbeddedHtml()` substantially copies Filament’s implementation.
   - Column reset copies native behavior before dispatching an additional event.
   - Scoped search replaces a native method that also supports callbacks and extra constraints.
   - Two SQL search implementations maintain separate scope storage and tokenization.

   See [WorkspaceTableSelect.php:12](/Users/studioycm/Herd/configurator/app/Filament/Forms/Components/WorkspaceTableSelect.php:12).

   **Recommendation:** prefer native extension points where they satisfy the current contract, and isolate necessary compatibility code. Preserve intentional hidden-field search and owner scoping.

   These are upgrade risks; omitted native hooks have not been shown to break a current workflow.

9. **R09 — Separate drawer query ownership, presentation definitions, and ordering writes. Medium priority.**

   [ItemLists.php:27](/Users/studioycm/Herd/configurator/app/Services/ItemLists.php:27) combines queries, labels, preview formatting, and resource URLs. `ItemListOrdering` obtains authoritative IDs through that presentation definition. `ItemListDrawer` adds SQL/array adapters and embedded-editor policy.

   **Recommendation:** establish clear query and ordering boundaries, leaving Filament navigation and table configuration in the UI layer. Preserve the current finite list definitions; a plugin registry is unnecessary.

   `ItemListDefinition` is a UI/query configuration object containing a builder and URL. Its classification should reflect that responsibility.

10. **R10 — Extract common draft-transition mechanics where editor contracts genuinely align. Medium priority.**

    Dirty-state baselines, draft retention, confirmation, event reconciliation, and navigation interception appear across managers, shared traits, and inline Alpine objects.

    **Recommendation:** reuse common mechanics while keeping editor-specific decisions local. Configurator editors, ordinary split lists, drawers, and tag editors have different transition semantics.

    Browser checks remain UX protection; server-side conflict checks remain authoritative.

11. **R11 — Make reviewed status changes an explicit application workflow where useful. Medium priority.**

    [StatusActions.php:87](/Users/studioycm/Herd/configurator/app/Filament/Resources/StatusActions.php:87) owns review-token comparison and transaction orchestration around `ChangeCatalogStatus`.

    **Recommendation:** consider a separately named reviewed-status operation that composes the existing write Action. Keep impact rendering and interaction state in Filament.

    This recommendation is narrower than the initial review suggested: imports legitimately call the underlying status Action directly. Interactive confirmation is not a requirement of every status write.

12. **R12 — Separate CSV format ownership from runtime Product-field authority. Important, overlapping pending work.**

    `CatalogImportParser::propertyKeys()` still controls eligibility in discovery, snapshots, settings, rule compilation, and exchange.

    **Recommendation:** resolve that dependency through the existing [Product-data foundation plan](/Users/studioycm/Herd/configurator/docs/catalog/PRODUCT_DATA_IMPLEMENTATION_PLAN.md:1). Keep CSV positions, headers, and map hashes owned by the import adapter.

    A structural refactor should preserve existing accepted keys until the foundation’s behavioral changes are approved. A competing permanent property registry would create another authority.

13. **R13 — Decide the intended status of the former discovery pipeline. Medium priority decision.**

    Repository tracing found [CatalogDiscovery::prepare()](/Users/studioycm/Herd/configurator/app/Services/CatalogDiscovery.php:21) called only by tests. Current production browsing uses snapshots and card requests; the project guidelines explicitly identify `prepare()` as the former pipeline.

    **Recommendation:** classify that method as a retained reference implementation or a retirement candidate, then account for its distinct assertions.

    `CatalogDiscovery::vocabulary()` remains live, and `CatalogFilterReconciler` remains used by snapshot matching. Whole-class deletion would be incorrect.

14. **R14 — Move substantial rule-summary presentation out of the model. Medium priority.**

    [ConfiguratorRule.php:68](/Users/studioycm/Herd/configurator/app/Models/ConfiguratorRule.php:68) constructs detailed workspace descriptions and traverses or loads relationships.

    **Recommendation:** use a focused presenter with clear loading requirements. Retain relationships and domain predicates on the model.

    Small reused conventions such as `Option::displayName()` and `Value::labelWithSemanticId()` can remain where they are.

The review also found these narrower correctness and consistency candidates:

| ID | Finding | Recommended correction |
|---|---|---|
| **R15** | [SaveAdminAppearanceSettings.php:33](/Users/studioycm/Herd/configurator/app/Actions/SaveAdminAppearanceSettings.php:33) updates the scoped appearance memo after an inner transaction. An enclosing rollback can leave that instance holding rolled-back settings. | Make memoization transaction-aware. Add a regression using the same container-resolved instance; the existing rollback test repeatedly constructs fresh instances. This is a conditional composition defect. |
| **R16** | [JsonRuleCast.php:42](/Users/studioycm/Herd/configurator/app/Casts/JsonRuleCast.php:42) returns payload arrays from `set()`, which Eloquent interprets as raw attribute assignments. It supports legacy fixtures only. | Correct the single-column JSON write contract, cover a nonempty round trip, and relocate it with its fixture consumers. Preserve intentional lenient reads. |
| **R17** | [app.js:18](/Users/studioycm/Herd/configurator/resources/js/app.js:18) duplicates Alpine registrations; its already-present-Alpine branch omits `catalogShell`. Catalog filters register through only one startup path. | Use one registration function across supported startup paths. The code inconsistency is confirmed; a runtime failure was not reproduced. |
| **R18** | Tag choices and usage independently load and aggregate all Value tag arrays. | Consolidate the read model and request-level reuse, then measure representative workloads before adding caching or changing storage. No performance regression was measured. |

File locations and namespaces should follow those responsibility decisions. These remain lower-priority organization candidates:

| ID | Candidate | Direction |
|---|---|---|
| **O01** | Shared Filament factories mixed into the resource root | Group shared Actions, table configurators, columns, filters, form components, and concerns by their role. Keep resource-specific code beside its resource. |
| **O02** | A growing flat Services/Actions namespace | Group cohesive Catalog, Configurator, rule-exchange, and admin responsibilities after their boundaries are settled. Preserve recognizable class names during moves. |
| **O03** | Nine enums directly under `App` | An `App\Enums\Configurator` grouping would aid navigation. Their current locations are valid. |
| **O04** | Mixed enum/string handling | Adopt additional casts only together with loader, draft, import, hash, and serialization normalization. `MappingSet` already provides an example that explicitly emits backing values. |
| **O05** | `CatalogPolicy` and `ConfiguratorPolicy` names | Settings/runtime-specific names could improve discovery. These are valid business policies and do not automatically belong in authorization `App\Policies`. |
| **O06** | [AppServiceProvider.php](/Users/studioycm/Herd/configurator/app/Providers/AppServiceProvider.php) combines several registrations | A shared Filament provider could own UI bootstrapping. Preserve scoped lifetimes, global action behavior, export specialization, and transaction-aware cache hooks. |
| **O07** | Application service dependencies repeatedly resolved inside methods | Prefer explicit injection for stable dependencies in application services and Actions. Framework callbacks and dynamic UI factories can retain appropriate container resolution. |
| **O08** | Legacy-only or apparently unused package integration | Review runtime ownership of media/settings/lightbox/translatable dependencies separately. Dependency changes require their own approval and development/production validation. |
| **O09** | Some `tests/Unit` files boot Laravel or use the database | Align test placement when those files are otherwise touched, accounting for global Feature test traits. Preserve their behavioral coverage. |

There are also **verification corrections worth addressing before a broad refactor**:

| ID | Evidence | Recommendation |
|---|---|---|
| **V01** | [tests.yml:6](/Users/studioycm/Herd/configurator/.github/workflows/tests.yml:6) and [lint.yml:6](/Users/studioycm/Herd/configurator/.github/workflows/lint.yml:6) target `develop`/`main`; the current integration branch is `master`. | Align triggers with the actual integration branches. The master deployment check does not run the PHP suite. |
| **V02** | [CreatesApplication.php:16](/Users/studioycm/Herd/configurator/tests/CreatesApplication.php:16) activates database safety checks only for MySQL. | Allow only explicitly disposable test targets across drivers before migration traits run. An exported SQLite file path or other resolved driver currently bypasses that guard. This is a source-confirmed unsafe path, not evidence it occurred. |
| **V03** | The default suite excludes dedicated MySQL tests, and repository workflows do not run their configuration. | Automate an isolated MySQL job for transaction, locking, collation, and constraint-sensitive behavior. |
| **V04** | The lint workflow fixes formatting without ensuring the submitted source was already formatted. | Fail CI when formatting produces a diff. |
| **V05** | Most meaningful behavioral tests exist, but agreed dependency directions lack protection. | Add a small number of architecture guards after approval: application writes independent of UI definitions, engine/runtime DTOs independent of Eloquent queries, and production code independent of test fixtures. |

The existing coverage is a useful foundation. Workspace, tested-save, tag, drawer, batch, import, snapshot, and status tests already protect substantial behavior. The important missing cases include stale untouched vocabulary during presentation edits, opposing MySQL writer lock orders, rollback of the scoped appearance instance, nonempty legacy cast persistence, and the alternate Alpine startup path.

Several earlier recommendations should be **retained or softened deliberately**:

- Keep `SaveConfiguratorDefinition` as the authoritative aggregate writer.
- Keep compiler/engine separation, runtime DTOs, signed tested-change checks, snapshot boundaries, and transactional revision handling.
- Keep `ConfiguratorRuleDraft` as a bounded editor adapter.
- Keep `ConfiguratorPreviewNotes`: it already has a coherent presentation responsibility and no database or UI-framework dependency.
- Keep the class-based `ProductConfigurator`/`ConfiguratorPreview` inheritance.
- Keep focused tag components that already delegate writes to Actions.
- Keep cohesive static factories and small model conveniences.
- Treat provider separation, enum moves, suffix changes, and DTO naming as optional cleanup.
- Preserve `tableFilters` state paths and the FilaCheck exception that protects them.
- Preserve intentional browser-local display preferences and current snapshot/client-computation contracts.

**The recommendation set is ready for your review.** After you approve its scope and decisions, the next deliverable will be the detailed implementation plan with exact files, target namespaces, dependencies, protected contracts, and tests for every approved phase.

<oai-mem-citation>
<citation_entries>
MEMORY.md:476-486|note=[compared and revalidated the earlier architecture recommendations]
MEMORY.md:490-491|note=[preserved distinction between proposed structure and approved implementation]
</citation_entries>
<rollout_ids>
01a13a0e-95c0-7ed3-b9b6-95fe84d90a26
</rollout_ids>
</oai-mem-citation>