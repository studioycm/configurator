# Research basis and maintenance

This skill synthesizes inspected framework behavior and teaching examples; it does not reproduce course materials. Verified on 2026-10-21 against Laravel 13.35, Livewire 4.4, Filament 5.9 and PHP 8.4. Recheck current package/PHP versions before applying APIs elsewhere. Framework docs/source govern syntax and lifecycle; Laravel Daily and Filament Examples inform design alternatives.

## Evidence ledger

| Source | Access actually inspected | Decision supported |
| --- | --- | --- |
| [Laravel 13 structure](https://laravel.com/framework/docs/13.x/structure) | Official documentation | Default folders and flexible application organization |
| [Container](https://laravel.com/framework/docs/13.x/container), [providers](https://laravel.com/framework/docs/13.x/providers) | Official docs plus installed v13.35 source | Concrete resolution, real bindings, lifecycle choice, registration versus boot |
| [Authorization](https://laravel.com/framework/docs/13.x/authorization), [events](https://laravel.com/framework/docs/13.x/events), [Artisan](https://laravel.com/framework/docs/13.x/artisan), [packages](https://laravel.com/framework/docs/13.x/packages) | Official relevant sections and installed discovery/generator source | Discovery differs by artifact; verify after moves |
| [Validation](https://laravel.com/framework/docs/13.x/validation), [casts](https://laravel.com/framework/docs/13.x/eloquent-mutators), [relationships](https://laravel.com/framework/docs/13.x/eloquent-relationships) | Official docs and relevant installed source | Input types, rule contracts and storage/model responsibilities |
| [Queues](https://laravel.com/framework/docs/13.x/queues#jobs-and-database-transactions) | Official docs / Boost | Queue work and after-commit semantics require explicit configuration |
| [Filament code quality](https://filamentphp.com/docs/5.x/resources/code-quality-tips) | Official full relevant sections / Boost | Static schema/table/component factories are supported and intentionally lack required parent/interface |
| [Filament v5 announcement](https://filamentphp.com/insights/danharrin-filament-v5-blueprint) | Official public body, published 16 January 2026; rechecked 23 October 2026 | v5 adds Livewire 4 support, otherwise shares v4 behavior/features; shared example paths are not a compatibility veto |
| [Livewire actions](https://livewire.laravel.com/docs/4.x/actions), [forms](https://livewire.laravel.com/docs/4.x/forms), [lifecycle](https://livewire.laravel.com/docs/4.x/lifecycle-hooks) | Official versioned guidance / Boost | UI state, validation, invocation and hydration have their own lifecycle |
| [Laravel 13 structure course](https://laraveldaily.com/course/laravel-projects-structure) | Public curriculum, March 2026; individual access below | Targeted discovery of design questions; course title is not evidence of all lesson bodies |
| [Form Requests](https://laraveldaily.com/lesson/laravel-projects-structure/validation-to-form-request) | Public body appeared complete | Substantial HTTP validation/authorization benefits from a focused request contract |
| [Mutators and observers](https://laraveldaily.com/lesson/laravel-projects-structure/before-saving-mutator-observer) | Public body appeared complete | Attribute transformation and lifecycle reactions have alternative placements |
| [Services and actions](https://laraveldaily.com/lesson/laravel-projects-structure/save-data-service-action) | Public body appeared complete | Plain classes can expose one operation or related capability; naming remains a convention |
| [DTOs](https://laraveldaily.com/lesson/laravel-projects-structure/dtos-typed-data-between-layers) | Full authenticated lesson, including examples and conclusion | Typed cross-layer data can justify a DTO; simple arrays remain valid |
| [Helpers](https://laraveldaily.com/lesson/laravel-projects-structure/global-helpers-last-resort) | Full authenticated lesson | Topic-specific stateless utility classes and global functions are alternatives; no blanket static/helper prohibition |
| [Base controllers and traits](https://laraveldaily.com/lesson/laravel-projects-structure/repeating-responses-base-controller-traits) | Full authenticated body, response and model-trait examples | Inheritance shares a base capability; traits attach selected host behavior |
| [Reuse across entry points](https://laraveldaily.com/lesson/laravel-projects-structure/beyond-the-controller-reusing-logic-everywhere) | Full authenticated lesson | Commands, controllers, Livewire and jobs can invoke the same operation |
| [When not to extract](https://laraveldaily.com/lesson/laravel-projects-structure/when-not-to-extract-avoiding-over-engineering) | Full authenticated lesson | Keep simple CRUD; justify event, DTO, pipeline and trait indirection |
| [Pipelines](https://laraveldaily.com/lesson/laravel-projects-structure/pipelines-chaining-multi-step-logic) | Authenticated lesson prose and examples' placement/links; code not copied | Configurable independently useful steps may justify a pipeline; fixed small sequences usually do not |
| [Jobs](https://laraveldaily.com/lesson/laravel-projects-structure/dispatch-jobs-background-queue) | Authenticated lesson prose and examples' placement/links | Queue concerns differ from ordinary operation classes; verify actual queue configuration |
| [Events/listeners](https://laraveldaily.com/lesson/laravel-projects-structure/events-listeners-when-how) | Authenticated lesson prose and examples' placement/links | Events enable reactions; official docs qualify dispatch/queue timing |
| [Modules/packages](https://laraveldaily.com/lesson/laravel-projects-structure/modules-packages) | Authenticated prose and example structure/links | Modules/internal packages/public packages solve distinct scale/reuse needs; no dependency installation is implied |
| [Service injection](https://laraveldaily.com/post/laravel-service-classes-injection), [container introduction](https://laraveldaily.com/post/laravel-service-container-beginners) | Public articles; 2022, revised examples may target Laravel 11 | Teaching examples of injection; validate current invocation behavior against official source |
| [Ten Service examples](https://laraveldaily.com/post/service-classes-laravel-open-source-examples) | First three public examples; remaining seven gated | A small one-method Service can be useful; unseen examples do not support claims |
| [Reusable wizard steps](https://filamentexamples.com/project/reusable-filament-wizard-steps-for-invoice-creation) | Public source snippet; full project gated; badge 4/5 | Static UI factories can be valid reuse with context-specific relationship behavior |
| [Construction management](https://filamentexamples.com/project/construction-management-system) | Public Money snippet and architecture description; transition implementation gated | Value objects and thin UI/Services are examples, not verified implementation guarantees |
| Filament Examples MCP `search_examples` | Returned actual `v4/forms/wizard-with-summary-step` source from the shared Filament 4/5 collection; service query supplied no useful matching implementation | Tool operation verified; source remains eligible for Filament 5 reuse. Judge actual custom Livewire/plugin/API compatibility, not the retained folder name; no whole-project dependency inspection is claimed |

## Reconcile examples instead of copying them

- Some course snippets quote older open-source APIs or use broad pedagogical wording. Events do not become asynchronous merely by being events. Current official queue/listener semantics are decisive.
- The reuse lesson encourages early extraction, while the anti-overengineering lesson cautions against speculative abstraction. Apply actual complexity, reuse and invariant ownership, rather than promoting either sentence into an absolute rule.
- Rule-of-three and line/property thresholds are teaching heuristics, not framework requirements. A critical single-use transaction can deserve an operation; repeated tiny formatting may remain local.
- Public previews and authenticated full bodies have separate access evidence. Video titles, comments and course listings are discovery leads. No video/transcript was inspected for this authoring run, and no claim depends on one.
- No SOLID/DDD course completion, complete subscription-library review, premium project-source inspection or exhaustive framework API coverage is claimed. This is a focused architecture knowledge base with current-source lookup for each real task.

## Maintain

When updating, refresh the affected sources through `laravel-filament-research`, record date/version/access, preserve project-specific exceptions outside the shared skill and test realistic decisions. Keep the entrypoint short; add focused references only where repeated mistakes justify them. New packages, generator defaults and PHP syntax require fresh verification. Do not copy paid lesson bodies or project source into this skill.
