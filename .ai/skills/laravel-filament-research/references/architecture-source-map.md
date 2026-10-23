# Architecture research source map

Use this for targeted Laravel structure questions. It is a discovery map, not a claim of current subscription access or completed reading. Open the actual relevant body/source, record the access on this run and verify version-sensitive claims against Boost/official documentation.

## Primary framework questions

- [Laravel structure](https://laravel.com/framework/docs/13.x/structure): directories and allowed flexibility; do not turn a supported convention into a mandatory domain architecture.
- [Container](https://laravel.com/framework/docs/13.x/container), [providers](https://laravel.com/framework/docs/13.x/providers): concrete autowiring, genuine bindings, lifetimes, registration/boot and invocation. Confirm minor/PHP compatibility for newer attributes.
- [Validation](https://laravel.com/framework/docs/13.x/validation): Form Requests, accepted input types, normalization boundaries, Rule objects and nested key selection.
- [Eloquent](https://laravel.com/framework/docs/13.x/eloquent), [casts](https://laravel.com/framework/docs/13.x/eloquent-mutators), [relationships](https://laravel.com/framework/docs/13.x/eloquent-relationships): model responsibilities and bulk/event behavior.
- [Events](https://laravel.com/framework/docs/13.x/events), [queues](https://laravel.com/framework/docs/13.x/queues): discovery versus dispatch, sync/async and commit/retry semantics.
- [Filament code quality](https://filamentphp.com/docs/5.x/resources/code-quality-tips): supported static schema/table/component/action factories. Follow installed major; package docs govern signatures.
- [Livewire lifecycle](https://livewire.laravel.com/docs/4.x/lifecycle-hooks), [forms](https://livewire.laravel.com/docs/4.x/forms), [actions](https://livewire.laravel.com/docs/4.x/actions): format, hydration, validation and authorization differ from ordinary HTTP requests.

Version links above were relevant to an authoring run in October 2026. Select the target project's versions afresh.

## Laravel Daily / Povilas Korop

Start with [How to Structure Laravel 13 Projects](https://laraveldaily.com/course/laravel-projects-structure) (March 2026, written course). Select relevant lessons, not a subscription-library scrape:

| Question | Lesson |
| --- | --- |
| HTTP rules and admission | [Form Requests](https://laraveldaily.com/lesson/laravel-projects-structure/validation-to-form-request) |
| Typed payload boundary | [DTOs](https://laraveldaily.com/lesson/laravel-projects-structure/dtos-typed-data-between-layers) |
| Attribute or lifecycle behavior | [Mutators/observers](https://laraveldaily.com/lesson/laravel-projects-structure/before-saving-mutator-observer) |
| Operation or capability | [Services/actions](https://laraveldaily.com/lesson/laravel-projects-structure/save-data-service-action) |
| Configurable sequential steps | [Pipelines](https://laraveldaily.com/lesson/laravel-projects-structure/pipelines-chaining-multi-step-logic) |
| Deferred work | [Jobs](https://laraveldaily.com/lesson/laravel-projects-structure/dispatch-jobs-background-queue) |
| Independent reactions | [Events/listeners](https://laraveldaily.com/lesson/laravel-projects-structure/events-listeners-when-how) |
| Shared host behavior | [Base controller/traits](https://laraveldaily.com/lesson/laravel-projects-structure/repeating-responses-base-controller-traits) |
| Topic-specific stateless utility | [Helpers](https://laraveldaily.com/lesson/laravel-projects-structure/global-helpers-last-resort) |
| Larger reuse/team boundaries | [Modules/packages](https://laraveldaily.com/lesson/laravel-projects-structure/modules-packages) |
| Reuse outside controllers | [Cross-entry-point operations](https://laraveldaily.com/lesson/laravel-projects-structure/beyond-the-controller-reusing-logic-everywhere) |
| Cost of indirection | [When not to extract](https://laraveldaily.com/lesson/laravel-projects-structure/when-not-to-extract-avoiding-over-engineering) |

The course is instructional, and embedded examples can be older than its branding. Its reuse advice and anti-extraction advice need synthesis around actual complexity, invariants and reuse. Numeric heuristics and naming preferences are not framework requirements. Events need explicit queue configuration for background listeners even if a tutorial groups events under background work.

Supplementary author sources: [service injection](https://laraveldaily.com/post/laravel-service-classes-injection), [container introduction](https://laraveldaily.com/post/laravel-service-container-beginners), [providers](https://laraveldaily.com/post/service-providers-laravel-main-things-you-need-to-know), [Service examples](https://laraveldaily.com/post/service-classes-laravel-open-source-examples). Check publication/update dates, actual readable examples, older Rule contracts and outdated helper APIs. Reader comments are not author guidance.

## Filament Examples

Call the available `search_examples` tool first for relevant UI design, then inspect actual source/version. Useful targeted projects include [reusable wizard steps](https://filamentexamples.com/project/reusable-filament-wizard-steps-for-invoice-creation) and [construction management](https://filamentexamples.com/project/construction-management-system). A public snippet/architecture description establishes only what it actually shows; it does not establish access to the complete transition Service or project repository. Keep licensed code private and do not bulk export projects.

## Videos and other courses

LaravelDaily/FilamentDaily videos, older SOLID/DDD/modules courses and AI Coding Daily may supply useful leads. Record actual inspected transcripts/timestamps and versions. An available curriculum or subscription does not establish that any video was watched. AI-workflow guidance is not authority for framework APIs.
