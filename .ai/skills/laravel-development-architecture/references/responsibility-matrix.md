# Responsibility and boundary decisions

Use this reference when deciding where existing or new Laravel behavior belongs. It supports judgment; it does not prescribe a directory tree or require every pattern in every application. Confirm installed package versions and the project's established conventions before choosing APIs or names.

## Contents

- Choose by responsibility
- Input contracts and authoritative decisions
- Writer and transaction owner
- Dependencies and lifecycle
- DTO, enum and serialization boundaries
- Traits, helpers and factories
- Extraction evidence
- Sources

## Choose by responsibility

First identify the entry points, callers, data owner, persistence owner, side effects, and existing tests. A long file is a search lead, not proof that extraction improves the application. Prefer the smallest change that clarifies a real boundary, removes meaningful duplication, or makes an important contract independently testable.

| Responsibility | Usually appropriate | A useful reason to extract or change | Check before recommending |
| --- | --- | --- | --- |
| Straightforward local CRUD | Controller, resource page, component handler | Growing decisions, reuse, or effects | A create-and-redirect wrapper may add only indirection. |
| HTTP input and request admission | Form Request | Substantial conditional rules, messages, preparation, or a distinct HTTP contract | A Form Request is an HTTP adapter; do not require commands or Livewire to construct one. |
| Reusable validation predicate | Custom Rule | A named check missing from built-in rules, with meaningful reuse or complexity | A one-time closure may be sufficient. Current Rule APIs depend on the installed Laravel version. |
| Stored-attribute representation | Cast, accessor, mutator | Conversion consistently belongs to that attribute | Distinguish storage normalization from one screen's input/display format. |
| Eloquent lifecycle reaction | Observer or model hook | The reaction belongs to that lifecycle across its relevant callers | Bulk operations, event suppression, transaction timing, and hidden effects can change behavior. |
| One application operation | Action or an existing focused writer | An atomic workflow, reusable operation, or important tested decisions | Laravel does not require an Action base class, method name, or one-public-method limit. |
| Coherent capability | Focused Service | Related calculations, orchestration, external integration, or a useful shared query | A one-method Service can be legitimate; an entity-wide Service can become a dumping ground. |
| Reused model query constraint | Local scope or custom builder | Repeated model-specific filtering/composition | Preserve caller authorization, eager loading, ordering, and driver semantics. |
| Typed data between layers | Plain PHP DTO | Unclear array shape, identity distinctions, optionality, or boundary typing | Trivial CRUD may use arrays. Construction and readonly properties do not validate data automatically. |
| Domain value with invariants | Value object | Exact arithmetic, constrained identity, or meaningful operations | A DTO carries data; a value object may enforce invariants and implement domain behavior. |
| Shared host behavior | Focused trait/concern | Compatible hosts need the same cohesive methods | Identify required properties/methods, collisions, hooks, and state ownership. |
| Integration/bootstrap configuration | Service provider | Bindings, lifetimes, global configuration, integration bootstrapping | Providers configure the application; they are not operation handlers. |
| Interchangeable/system boundary | Interface/contract | Real implementations, package boundary, or an external dependency justify substitution | An interface for each concrete class or a repository mirroring Eloquent adds ceremony. |
| Small cross-context transformation | Named utility/helper, sometimes static | A stable operation has no better existing owner | Helpers should not conceal application workflows, authorization, or persistence. |
| Filament UI construction | Resource schema/table/component/action factories | Repeated or complex UI definitions | Static factories are supported. Keep UI concerns close to their actual consumers. |
| Independent reaction to a domain occurrence | Event/listener | Decoupling is useful and effects have clear delivery semantics | Do not obscure a required operation sequence merely to shorten the caller. |
| Deferred/durable work | Job | Execution time, retries, delivery, or queue isolation justify it | An application Action is not automatically a queue Job. Inspect payload and transaction boundaries. |

Services, Actions, traits, and DTO folders are application conventions, not competing Laravel frameworks. Consistency has value; a naming preference alone does not establish a defect.

## Separate input contracts from authoritative decisions

Trace a representative operation from every real entry point: HTTP controller, Filament action, Livewire method, command, import, or job. Record which checks run where and which inputs each caller can supply.

- **Transport input:** Which fields are accepted, normalized, rejected, or omitted? What are their explicit types and limits?
- **Identity:** Are IDs canonical, local membership IDs, external codes, or staged draft keys? Can strings contain leading zeros or exceed JavaScript's exact-integer range?
- **Admission:** Where is the actor authorized to invoke this operation? Does hiding a UI control merely improve presentation, or is server-side authorization enforced too?
- **Domain invariants:** Which checks must hold for every write, including imports and commands? Which depend on mutable data and therefore need rechecking under the writer's locks?
- **Reusable predicate:** Can a custom Rule express a validation check without taking ownership of the whole operation? Does a field-level Rule leave multi-record invariants to the writer?
- **Normalization:** Is conversion lossless? Distinguish missing, null, empty string, empty collection, false, and zero when the domain distinguishes them.
- **Output:** Does the caller need a persisted model, immutable result, diagnostics, or a transport response? Keep redirects, notifications, and UI error placement at an appropriate adapter boundary.

Duplicated syntax does not always mean duplicated responsibility. A form may give immediate validation feedback while the authoritative writer independently protects a changing invariant. Explain that distinction before deleting either check.

Form Requests, Livewire validation/Form objects, and Filament schema validation are different adapters. Share a domain validator or operation when several adapters need the same decision; do not force one adapter's lifecycle into another.

## Locate the writer and transaction owner

Before splitting a writer, draw its read/lock/write/effect sequence. Preserve one clear owner for each atomic business outcome; focused collaborators can work inside that owner's transaction.

Ask:

1. Which rows and related records constitute one successful outcome? What must remain unchanged on rejection or failure?
2. Who opens the transaction? Can this operation run inside an existing transaction or nested operation?
3. Which reads must be consistent, and which rows must be locked before decisions? Does lock order remain deterministic?
4. Does validation run against the same connection and snapshot as persistence? Do constraints backstop uniqueness and relationship invariants?
5. Which writes use Eloquent lifecycle events, bulk queries, raw SQL, or event suppression? Will an observer cover every intended path?
6. When do cache invalidation, revision advancement, memo updates, exports, notifications, and dispatched work become visible?
7. Does an after-commit callback wait for the enclosing transaction? What happens to in-memory memoized state if an outer transaction rolls back?
8. Are retries possible? Can the operation repeat external effects, consume a one-use token, duplicate an import, or allocate another identifier?
9. Are concurrency checks optimistic, pessimistic, or both? Does extraction preserve the exact revision/hash input and failure behavior?
10. What proves rollback and concurrent behavior on the actual production database engine? SQLite coverage may not establish another driver's locking/collation behavior.

Keep critical workflow transitions explicit when sequence and atomicity matter. Observers can own lifecycle-wide reactions, but their visibility, bulk-write limitations, and commit behavior must match the operation. Event/listener extraction must preserve required synchronous decisions and define delivery/failure semantics for asynchronous effects.

## Dependencies and lifecycle

Prefer constructor injection for dependencies needed throughout an object's lifetime and method injection at container-invoked entry points. Ordinary PHP method calls still require arguments; automatic method injection is not universal. Container invocation mechanisms and framework utility injection must be checked for the installed version.

- Concrete classes with resolvable concrete dependencies usually need no explicit provider binding.
- Use bindings for interfaces, primitive configuration, contextual selection, integration customization, or a deliberate lifetime.
- Distinguish transient, singleton, and scoped state. Request/user/tenant-sensitive mutable data must not leak between long-lived worker requests/jobs.
- Preserve scoped collectors and memoization when they coordinate nested operations. Moving code into a provider or changing its lifetime can alter correctness.
- In modern Laravel skeletons, provider registration lives in `bootstrap/providers.php`; upgraded projects may retain their existing supported skeleton. Do not restructure a working skeleton merely to match a tutorial.
- Keep provider `register()` focused on container bindings. Bootstrap functionality that depends on registered services in `boot()`.

An interface earns its cost through a real boundary. A separate repository may be useful for a justified aggregate/storage contract, but not because an arbitrary architecture diagram requires one above Eloquent. Show actual callers, substitution needs, and benefits before adding it.

## DTO, enum, and serialization boundaries

Document what a data object represents: validated input, draft, compiled runtime definition, result, query specification, snapshot, or wire state. Those roles have different contracts; sharing a folder does not make them interchangeable.

- A DTO may contain documented arrays or framework objects when its real boundary calls for them. Do not impose a universal framework-free DTO rule on a paginator or query definition.
- A value object should make its invariant visible. A readonly wrapper alone does not prove immutability of nested objects or validity of its contents.
- Introducing model enum casts changes in-process types. Trace strict comparisons, array keys, `only()`/attribute reads, drafts, hashes, exports, UI state, and compiler input before changing casts.
- Preserve enum backing values, identifier representations, version fields, nullable distinctions, ordering, and plain-array wire/cache contracts.
- A namespace move can affect native serialized payloads and queued work even when JSON/database values are unchanged. Establish whether such payloads actually exist before prescribing compatibility machinery.
- Test observable round trips and rejected inputs. Namespace/declaration tests cannot establish persistence, runtime, or serialization compatibility.

## Traits, helpers, and static UI factories

A trait should describe one behavior and make its host requirements discoverable. Avoid unrelated state, rendering, authorization, and write workflows accumulating in a concern whose name describes only one of them. Extract a collaborator when it owns its own decisions; keep genuinely host-owned behavior in a trait when that is clearer.

Static methods are a design choice, not an automatic defect. A stateless transformation or UI factory may fit them well. Stateful integrations and replaceable dependencies often benefit from instances and injection. Inspect what the method actually does rather than banning `static` by name.

Filament explicitly supports reusable schema/table `configure()` methods and component/action `make()` factories. These classes intentionally need no common parent/interface, so extra configuration arguments remain possible. Resource-local UI factories can stay local; shared UI code can be organized by actual reuse.

Distinguish a Filament `Action` object from an application operation class. The UI factory can configure fields, labels, visibility, and presentation, then invoke an authoritative operation for a complex workflow. A domain writer should not depend on a Filament schema merely to obtain shared data normalization.

## Assess a proposed extraction

For each candidate, provide:

- **Evidence:** exact file/line and real callers; identify the responsibility crossing a boundary.
- **Classification:** runtime defect, concrete structural risk, local cleanup, or optional organization.
- **Consequence:** a reachable inconsistency, repeated change burden, hidden effect, or unclear ownership; mark unverified risks.
- **Smallest direction:** retain, move a focused responsibility, compose an existing class, or introduce one justified collaborator.
- **Contract:** input/output shape, actor, transaction owner, lifecycle, ordering, identity, and serialized behavior to preserve.
- **Verification:** existing coverage, distinct gaps, and the narrowest meaningful validation for the proposed change.

Treat fixed line counts, property counts, occurrence counts, and directory names as heuristics. Do not score them as architecture violations without the consequence and proposed benefit. Keep a sound existing boundary when extraction only adds forwarding files.

## Evidence and source interpretation

Framework documentation establishes supported APIs and lifecycles. Tutorials demonstrate choices; project examples demonstrate their own implementation. Recheck versions/access for consequential reuse. The links below were inspected on 21 October 2026; access can change.

- [Laravel Daily: Validation to Form Request](https://laraveldaily.com/lesson/laravel-projects-structure/validation-to-form-request) — Laravel 13 course; public body with HTTP validation/authorization and examples.
- [Laravel Daily: Mutator or Observer](https://laraveldaily.com/lesson/laravel-projects-structure/before-saving-mutator-observer) — Laravel 13 course; public body comparing lifecycle placements.
- [Laravel Daily: Service or Action](https://laraveldaily.com/lesson/laravel-projects-structure/save-data-service-action) — Laravel 13 course; public body on operations, grouping, injection, and invocation choices.
- [Laravel Daily: DTOs](https://laraveldaily.com/lesson/laravel-projects-structure/dtos-typed-data-between-layers) — Laravel 13 course; full authenticated body verified in the authoring session; plain PHP DTOs remain optional.
- [Laravel Daily: When NOT to Extract](https://laraveldaily.com/lesson/laravel-projects-structure/when-not-to-extract-avoiding-over-engineering) — full authenticated lesson inspected, including event/DTO/pipeline/trait tradeoffs; numeric thresholds remain heuristics.
- [Laravel Daily: Helpers](https://laraveldaily.com/lesson/laravel-projects-structure/global-helpers-last-resort), [Traits](https://laraveldaily.com/lesson/laravel-projects-structure/repeating-responses-base-controller-traits), and [Reuse Across Entry Points](https://laraveldaily.com/lesson/laravel-projects-structure/beyond-the-controller-reusing-logic-everywhere) — full authenticated lesson bodies inspected during authoring; reuse and host-behavior guidance synthesized with anti-extraction tradeoffs.
- [Laravel Daily: Ten Service Examples](https://laraveldaily.com/post/service-classes-laravel-open-source-examples) — September 2024; first three examples readable, remaining seven gated; historical code is not current API authority.
- [Filament Examples: Reusable Wizard Steps](https://filamentexamples.com/project/reusable-filament-wizard-steps-for-invoice-creation) — labelled Filament 4/5; public static-factory source snippet, complete repository gated.
- [Filament Examples: Construction Management](https://filamentexamples.com/project/construction-management-system) — labelled Filament 4/5; public value-object snippet and workflow description; transition-Service implementation gated.
- [Filament 5: Code Quality Tips](https://filamentphp.com/docs/5.x/resources/code-quality-tips) — official support for static schema/table/component/action factories; suggested organization is flexible.
- [Laravel 13: Container](https://laravel.com/framework/docs/13.x/container), [Providers](https://laravel.com/framework/docs/13.x/providers), and [Validation](https://laravel.com/framework/docs/13.x/validation#custom-validation-rules) — official authority for resolution, lifetime, bootstrap, and Rule/closure APIs.

Do not infer premium access from a listing, subscription badge, search extract, or another service's login. Do not turn course-reader comments into framework requirements. A course labelled Laravel 13 can still quote older project code; verify signatures before implementing it.
