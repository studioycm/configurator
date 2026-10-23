---
name: laravel-development-architecture
description: Use when designing, writing, reviewing, or refactoring Laravel application responsibilities, services, actions, providers, helpers, traits, contracts, DTOs, enums, validation, Eloquent boundaries, file locations, namespaces, or framework discovery. Also use for Laravel structure audits involving Livewire and Filament. Ordinary styling and unrelated framework work do not require this skill.
---

# Laravel development and architecture

Make responsibilities explicit while preserving Laravel's useful defaults. Prefer the smallest structure that makes the actual behavior clear, reusable where needed, and safe for its callers. This is an architecture companion to the existing Laravel, Livewire, Filament, and testing skills, not a replacement for their API guidance.

## Start with scope and evidence

1. Read the target checkout's AGENTS instructions, applicable path rules, sibling files, and requested scope. Record the checkout, HEAD/dirty state and affected behavior. An audit or plan does not authorize runtime refactoring. Do not stage, reset, or change concurrent work.
2. Confirm PHP and installed package versions (`composer show --direct`, relevant `composer show`, package manifest/lockfile). Use Boost `application-info` when available. A framework major alone does not establish PHP syntax compatibility.
3. Use Boost `search-docs` before API-dependent decisions or edits, scoped by package with broad topic queries. Reuse sufficient results already in context. If unavailable, use official versioned docs, then locked source for disputed behavior; disclose the fallback. Boost is a research/inspection aid, not permission for database writes, migrations, or production operations.
4. Inventory affected entry points, callers, state, queries, authorization, invariants, transaction/lock ownership, serialization and UI dependencies. Find existing collaborators before adding another abstraction. Existing convention is a default, not a reason to preserve a demonstrated correctness defect.
5. For unsettled architecture/source comparisons, use `laravel-filament-research`. For a requested structure audit, read [the audit workflow](references/audit-workflow.md) and use `laraveldaily-structure-audit` if installed. Historical reports are leads requiring fresh verification.

## Choose a responsibility before a namespace

Read [the responsibility matrix](references/responsibility-matrix.md). Keep simple local CRUD and small presentation helpers when they are already clear. Line count, a `Helper` suffix, static methods, a single caller, or a folder name alone do not establish a defect.

- **Entry points** adapt transport/UI state, validate and authorize, invoke an operation, then format feedback. HTTP Form Requests, Livewire forms, and Filament schemas serve different invocation contexts.
- **Actions** express cohesive operations; **Services** express cohesive capabilities/calculations. Both are ordinary PHP classes. `handle()`, `execute()`, or `__invoke()` follows local convention; none is a mandatory Laravel base contract.
- **Authoritative writes** retain ownership, business invariants, transaction boundaries, lock order and concurrency checks for every caller. Extracting a method out of UI does not establish those guarantees. An operation must not hydrate a Filament form to decide what persisted fields survive.
- **DTOs/value objects/enums** make meaningful data/value boundaries explicit. They do not automatically validate, authorize, normalize input, or preserve persisted wire formats. Do not introduce them for every small array/string.
- **Models/scopes/casts/relationships** retain coherent persistence semantics. Query objects can own complex read projections. Repositories wrapping Eloquent method-for-method add no automatic benefit.
- **Providers** configure bindings/lifetimes/integrations. **Traits** compose narrow host behavior. **Macros** extend a framework API globally and require collision/lifecycle review. Neither traits nor providers are destinations for arbitrary extracted business methods.
- **Filament configuration factories** may correctly use static `configure()`/`make()` without inheritance/interfaces. A Filament Action configures UI; an application Action performs a business operation. Keep these responsibilities distinct.

## Use container and discovery benefits deliberately

Read [container and discovery](references/container-and-discovery.md) before changing wiring, lifetimes, or class locations. Concrete constructor dependencies usually resolve without bindings. Bind real contracts/primitives, choose contextual bindings when needed, and justify every scoped/singleton lifetime. Do not capture actor/tenant/request state in a long-lived singleton.

Injection occurs through container/framework construction and supported invocation points. A normal `$operation->handle()` call does not inject omitted arguments, and `new Operation` does not autowire its constructor. Prefer explicit dependencies and operation inputs; `app()` and Laravel facades are supported tools, not blanket violations.

Autoloading, policy discovery, listener discovery, command discovery, application provider registration, package discovery, Livewire discovery and Filament panel discovery are separate mechanisms. A PSR-4-correct move can still break one of them.

## Implement within the authorized scope

Read only the references relevant to the work:

| Work | Reference / companion skill |
| --- | --- |
| Select a class type or remove helper ambiguity | [Responsibility matrix](references/responsibility-matrix.md) |
| Binding, lifetime, registration or discovery | [Container and discovery](references/container-and-discovery.md) |
| Generate files, select namespace, migrate references | [Scaffolding and locations](references/scaffolding-and-locations.md) |
| Plain Service/Action/DTO methods and invoking them | [Class shapes](references/class-shapes.md) |
| Validation, DTOs, casts, transaction ownership | [Input and persistence boundaries](references/input-and-persistence.md) |
| Livewire/Filament state, factories, overrides | [UI boundaries](references/livewire-and-filament.md); `livewire-development`, `filament-development` |
| Laravel implementation details | `laravel-best-practices`, then its affected rule files |
| Test design or review | `testing-best-practices` before writing tests |
| Detailed Filament implementation plan requested | `planning-filament` |
| Performance/security/API/queue problem in scope | The relevant specialist skill; avoid unrelated whole-app sweeps |
| Research provenance and evidence gaps | `laravel-filament-research`; [research basis](references/research-basis.md) |

Generate supported Laravel/Livewire/Filament artifacts with installed Artisan `make:` commands; inspect `list` and `--help`, pass `--no-interaction`, and select needed flags explicitly. Generic Services/Actions/DTOs use `make:class` unless a verified project generator exists. Respect configured component formats, panels and namespace roots. Do not introduce dependencies or new base folders without required authorization.

Find named companion skills in the active catalog rather than another project's hard-coded paths. If unavailable, disclose that gap and use the target project's instructions and verified official documentation; do not invent missing skill rules.

## Verify the benefit

Use meaningful behavior tests at the real entry point and operation boundary: success, failure/authorization, unchanged fields, serialization compatibility, rollback and concurrency where relevant. Do not add tests that merely assert filenames or duplicate the implementation. Verify native discovery/registration after moves; format and run project checks for changed files. Use the actual database engine for locking claims in an isolated environment.

For audits, report evidence and recommendations without edits. For implementation, finish the authorized change and relevant checks. Clearly distinguish what was inspected, tested, inferred and left unresolved. Documentation-only skill work does not require the application's full test suite.
