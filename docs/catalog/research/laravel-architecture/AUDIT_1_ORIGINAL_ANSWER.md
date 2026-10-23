The main work is **correcting responsibility boundaries**, followed by file locations and namespaces. Much of the application already uses suitable Laravel patterns.

I inventoried **188 application PHP files** against the installed Laravel 13.35, Livewire 4.4, and Filament 5.9 versions. There is no global helper autoload in Composer and no `app/Helpers` directory; the concerns are mostly classes acting as helpers under other names.

One distinction matters: **static factories are appropriate for Filament UI definitions**. Filament documents this pattern, and Povilas’s reusable wizard example uses it. Converting every factory into an injected service would add unnecessary complexity. [Filament code-quality guidance](https://github.com/filamentphp/filament/blob/5.x/docs/03-resources/13-code-quality-tips.md), [Filament Examples wizard](https://filamentexamples.com/project/reusable-filament-wizard-steps-for-invoice-creation).

These are the strongest findings, ordered by value.

| Priority | Current code | Finding | Proposed direction |
|---|---|---|---|
| First | [BatchCatalogChanges.php:141](/Users/studioycm/Herd/configurator/app/Actions/BatchCatalogChanges.php:141) → [GroupForm.php:180](/Users/studioycm/Herd/configurator/app/Filament/Resources/Groups/Schemas/GroupForm.php:180) | A domain write action depends on a Filament form class to prepare settings. | Extract a shared `App\Services\Catalog\GroupSettingsData` service. Keep Filament-specific field formatting in the form. Both editor and batch action consume the shared data. |
| First | [AttributesRelationManager.php:419](/Users/studioycm/Herd/configurator/app/Filament/Resources/Configurators/RelationManagers/AttributesRelationManager.php:419), Rules and Options relation managers | These components contain inclusion, ownership, draft transformation, priority, and ordering operations alongside UI state. | Extract focused application Actions such as `IncludeConfiguratorAttributes`, `SaveConfiguratorAttribute`, and `SaveConfiguratorRule`. Continue using `SaveConfiguratorDefinition` as the authoritative transactional writer. |
| First | [ItemLists.php:25](/Users/studioycm/Herd/configurator/app/Services/ItemLists.php:25), `ItemListDefinition`, `ItemListOrdering` | The drawer subsystem mixes queries, column labels, resource URLs, preview formatting, and database mutations. Ordering also obtains its data through the UI definition. | Put drawer definitions and navigation under `App\Filament\Tables\ItemLists`. Separate ordering writes into Actions that receive explicit owner/record identities rather than depending on a table definition. |
| Next | [InteractsWithScopedTableSearch.php:10](/Users/studioycm/Herd/configurator/app/Filament/Resources/InteractsWithScopedTableSearch.php:10) | A search trait also owns column-manager reset, item previews, and batch-preview state. Its name conceals unrelated responsibilities. | Keep search behavior in the search concern; move column management, preview behavior, and batch state to their relevant owners. |
| Next | [TablePresentation.php:28](/Users/studioycm/Herd/configurator/app/Filament/Resources/TablePresentation.php:28) | A presentation configurator also installs batch/status actions, search behavior, filters, ordering controls, and column management. | Retain one table configuration entry point, composed from focused Filament configuration classes. Avoid another general-purpose helper. |
| Next | [ConfiguratorRule.php:67](/Users/studioycm/Herd/configurator/app/Models/ConfiguratorRule.php:67) | The model constructs detailed workspace sentences and presentation summaries, including relationship loading. | Move substantial formatting into `App\Filament\Resources\Configurators\Presentation\RuleSummary`. Keep relationships and domain predicates on the model. |
| Later | [AppServiceProvider.php:37](/Users/studioycm/Herd/configurator/app/Providers/AppServiceProvider.php:37) | Application bootstrapping mixes model-event registration, authorization, Filament toolbar hooks, and dialog appearance. | Group shared Filament bootstrapping in a dedicated provider. Preserve its availability to Filament components outside the admin panel. Keep the existing scoped service lifetimes. |
| Later | `CatalogPolicy`, `ConfiguratorPolicy` | Their names resemble authorization Policies, but they implement result settings and selection behavior. | Rename `CatalogPolicy` to a settings-specific class. Give the configurator class an explicit runtime-policy name; neither belongs in authorization `App\Policies`. |
| Later | Nine enums directly under `app/` | Locations are valid, but organization is inconsistent with the growing application. Existing enums also coexist with repeated string comparisons. | Move them together into `App\Enums\Configurator`. Review enum casts separately, preserving database and serialized values. |
| Pending overlap | `CatalogImportParser::propertyKeys()` | Import-format metadata determines eligible fields in discovery, snapshots, settings, and rule compilation. | Resolve through the existing Product-data foundation plan. A namespace move alone cannot fix this dependency. |

The relation-manager extraction follows the approach described in the Filament Examples construction project: UI actions delegate business transitions to typed services that own authorization, validation, and locking. That example supports the direction; the proposed class boundaries above come from this application’s source. [Construction Management example](https://filamentexamples.com/project/construction-management-system).

For the shared Filament files, I recommend this concrete placement:

| Existing files | Proposed namespace |
|---|---|
| `BatchEditActions`, `DependencyActions`, `StatusActions`, `FormHints` | `App\Filament\Actions` |
| `FormOrderControls` | `App\Filament\Forms\Components` |
| `TablePresentation`, `AttributeSelectionTable`, `GroupSelectionTable`, `OptionSelectionTable` | `App\Filament\Tables` |
| `ItemCountColumn` | `App\Filament\Tables\Columns` |
| Shared `InteractsWith…` traits | `App\Filament\Concerns` |
| `SplitListRecords`, `SplitCreateRecord` | `App\Filament\Resources\Pages` |
| Drawer definitions and UI registry | `App\Filament\Tables\ItemLists` |

Resource-specific schemas, tables, and concerns should remain beside their resource. The small factories can retain their current implementation style.

For Laravel design types, I would apply these rules consistently:

| Type | Responsibility in this application |
|---|---|
| **Action** | A complete business operation: save, include, remove, assign, reorder, import. Owns or delegates authorization and transaction boundaries. |
| **Service** | A coherent capability: compilation, evaluation, discovery, snapshots, dependency analysis, settings preparation. |
| **Service provider** | Registration and bootstrapping. Concrete services usually need no explicit registration unless their lifetime or construction requires it. |
| **Trait / Concern** | Behavior tied to a host component’s state and lifecycle. Keep business operations in Actions or Services. |
| **Enum** | A bounded vocabulary, preserving existing backing values at persistence and JSON boundaries. |
| **DTO** | Typed input, output, or state passed between layers. Keep the existing engine DTOs; avoid wrapping every small array. |
| **Model / scope / cast** | Relationships, persistence representation, and reusable model-query behavior. |
| **Filament factory** | Builds or configures UI components and delegates business operations. |

Laravel supports automatic resolution of concrete dependencies; creating a provider or interface for every service is unnecessary. Livewire supports dependency injection and reusable trait lifecycle hooks, so those should be used where they clarify existing component dependencies. [Laravel container](https://github.com/laravel/docs/blob/13.x/container.md), [Livewire lifecycle hooks](https://livewire.laravel.com/docs/4.x/lifecycle-hooks).

Several existing classes should largely remain:

- `ConfiguratorEngine`, `ConfiguratorDefinitionCompiler`, and `ConfiguratorDefinitionLoader` already establish useful boundaries.
- `CatalogRevisions`, snapshot locking, and the save Actions contain important transaction guarantees.
- `CanonicalUsage` and `InclusionUsage` are legitimate cross-model query services.
- `StringFieldTransformer` has specialized replacement semantics; replacing it with a generic string helper would lose behavior.
- `ProductEditorData` protects exact keys and structured values. Clarify its presentation and update responsibilities only while preserving those contracts.
- Existing `App\DTO` naming is acceptable. Renaming every DTO or adding a `Service` suffix everywhere offers little value.

Povilas’s current structure course also treats helpers as a fallback and explicitly includes avoiding unnecessary extraction. It does not prescribe one mandatory folder layout. I reviewed the publicly available material and examples, not the private subscriber repositories. [Laravel Daily structure course](https://laraveldaily.com/course/laravel-projects-structure), [Global Helpers: Last Resort](https://laraveldaily.com/lesson/laravel-projects-structure/global-helpers-last-resort).

I recommend implementing the plan in four bounded phases:

1. **Remove the GroupForm dependency.** Extract shared settings preparation and update the editor and batch caller. Verify saved filters, presets, labels, order, and untouched presentation settings.
2. **Extract configurator operations.** Move business transformations from relation managers into Actions. Preserve ownership checks, aggregate locking, child identities, rollback behavior, error paths, and unsaved drafts.
3. **Separate drawer responsibilities and organize shared Filament code.** Preserve drawer membership checks, complete-list ordering, search scope, selection, and embedded editor behavior.
4. **Normalize namespaces and types.** Group related Actions, Services, and DTOs under Catalog, Configurator, and Admin subnamespaces; move enums; then review casts, provider organization, and presentation extraction independently.

Each phase should use the affected existing tests first. Add coverage only for newly exposed boundaries or missing failure cases, then run the required Pint/FilaCheck checks and browser checks for affected UI flows.

The Product-data work remains pending under the [existing plan](/Users/studioycm/Herd/configurator/docs/catalog/PRODUCT_DATA_IMPLEMENTATION_PLAN.md:1). **Phase 1 is the best first implementation scope.** This review was read-only: I changed no files and did not execute tests.

<oai-mem-citation>
<citation_entries>
MEMORY.md:46-51|note=[preserved pending Product data plan and planning boundaries]
</citation_entries>
<rollout_ids>
01a0dff8-a627-7210-9a92-998ab00122e3
</rollout_ids>
</oai-mem-citation>