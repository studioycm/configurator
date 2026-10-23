# Livewire and Filament responsibility boundaries

## Livewire

Read `livewire-development` and installed-version docs. Preserve the project's class/SFC/MFC format, aliases and configured paths. Component generation defaults are not migration requirements.

Components own interactive state, validation feedback and invocation of authorized operations. Public properties and public action arguments are client-controllable; server checks must establish ownership and allowed changes on each relevant invocation. A mount-time check alone does not cover future calls. Locked properties help protect state but do not replace policy checks. Do not place service instances in dehydrated public state.

Use supported action/boot injection or explicit resolution for collaborators needed on subsequent requests. Do not assume a dependency stored in a private property during `mount()` is automatically restored after hydration. Protected/private properties do not form a persisted cross-request cache.

Livewire Form objects can group state/rules; they are not HTTP Form Requests or authoritative transaction owners. Share meaningful rules/invariants with imports/commands without coupling those callers to a component. Preserve frontend state paths and event contracts when extracting code. Validate browser-controlled IDs and requery current state when saving.

## Filament

Read `filament-development` before design/implementation. Use Boost package-scoped docs and Filament Examples `search_examples` for actual implementations before new Filament UI code. Inspect returned code and version, not merely a matching title. A v4 source path is v4 evidence even if its catalog badge says 4/5. If the tool is unavailable, use the website and document the evidence limit.

Native resource-local schema/table classes with static `configure(Schema|Table)` and component/action factories with static `make()` are explicitly supported. They intentionally require no common parent/interface. Do not convert them into injected Services solely for being static or living in a custom directory. Prefer keeping resource-specific UI near its resource; share only components actually used across consumers.

Distinguish:

| Code | Owns |
| --- | --- |
| Resource/schema/table factory | Fields, columns, filters, labels, component configuration |
| Filament Action factory | UI trigger, schema, confirmation, adapter feedback |
| Resource page / relation manager | UI context, relationship query, framework lifecycle, invoking operations |
| Application Action/Service | Reusable operation/capability and its explicit write contract |
| Query/read model | Cohesive read projection or query composition |
| Presenter | Already-authorized data representation without hidden writes |

Native relationship CRUD may stay in page/relation-manager handlers when simple. Extract genuine inclusion, assignment, ordering, draft, status, or multi-model workflows when they need shared correctness and transaction ownership. Keep relationship ownership checks after moving code. When custom save paths replace native persistence, inspect lifecycle hooks, authorization, relation persistence, validation, return values and transaction behavior; do not write the record twice.

Reusable UI code should not depend on a particular resource to construct shared field choices. Conversely, a general write operation should not obtain canonical data from a resource Form. Stateful helpers/traits must document required host methods/properties, table IDs, schema paths, deferred filter behavior and session/local storage ownership. Avoid a trait accumulating unrelated table search, column persistence, batch operations and domain queries merely to shorten resources.

Review framework overrides/copied internals before changing them. Compare exact installed parent signatures and hooks; a copy can bypass native authorization, optional hooks or future behavior without producing an immediate failure. Prefer supported extension points, but do not remove a compatibility adapter without reproducing its intended behavior. Clearly distinguish an upgrade risk from an observed runtime bug.

Global `configureUsing`, render hooks, macro registration and panel setup belong to deliberate bootstrap/provider configuration. Scope per-panel/user state correctly; global static configuration must not capture one current user as a process-wide default.

## Verification

Use meaningful component/resource/action tests plus operation tests. Cover ownership, tampered state, invalid/stale data, deliberate clearing versus unchanged values and important UI lifecycle hooks. Browser acceptance is separate from PHP tests: inspect state roundtrips, selection, modals, hydration and actual errors when those behaviors change. Use Boost recent `browser-logs` and `get-absolute-url` for project URLs. Do not claim UI verification from static code.

Use the relevant specialist skills for form UX/performance/security when that work is in scope, not as automatic gates for every factory extraction. Apply project-required Pint/FilaCheck after relevant PHP/Filament edits and review automated changes in the diff.

Sources: [Livewire actions](https://livewire.laravel.com/docs/4.x/actions), [properties](https://livewire.laravel.com/docs/4.x/properties), [lifecycle hooks](https://livewire.laravel.com/docs/4.x/lifecycle-hooks), [forms](https://livewire.laravel.com/docs/4.x/forms), [Filament code quality](https://filamentphp.com/docs/5.x/resources/code-quality-tips), [Filament resource editing](https://filamentphp.com/docs/5.x/resources/editing-records).
