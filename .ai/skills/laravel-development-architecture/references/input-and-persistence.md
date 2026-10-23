# Input, data, and persistence boundaries

## Contents

- Validation and normalization
- DTOs, enums, and values
- Operations and writes
- Eloquent and side effects

## Validation and normalization

Use built-in validation first. A small endpoint may keep inline validation. A substantial HTTP contract benefits from a Form Request's `rules()`, `authorize()`, messages and supported preparation/after-validation hooks. It is container-resolved by HTTP handling; manually constructing a Form Request elsewhere does not reproduce that lifecycle. CLI/import/job/Livewire paths should use their appropriate adapters and shared invariants instead of manufacturing an HTTP request.

For a reusable field predicate, use generated `ValidationRule::validate(string $attribute, mixed $value, Closure $fail): void`. Use `DataAwareRule` for sibling data where justified. A one-use closure is also supported. Read the installed docs before choosing implicit rules or hooks. Do not replace input checks with a DTO constructor by assumption.

Validation, normalization, authorization and persistence are distinct. `validated()` returns selected values; `safe()` returns `ValidatedInput`. They do not generally coerce transport strings into PHP scalars. A validated integer may still be `"12"`, and a boolean may be `"0"`. Explicitly normalize accepted forms before a strict typed DTO, or use supported strict validation if that is the intended transport contract. Distinguish missing, explicit null, empty string, empty list and false. Narrow nested array keys and persist only fields the operation owns; avoid passing `$request->all()` to writes.

Validation can query availability/uniqueness for useful feedback, but a racing write needs an authoritative invariant and database constraint/transaction where appropriate. Never use a client-selected `ignore()` identifier without trusted model ownership. UI visibility and disabled controls are not authorization.

## DTOs, enums, and values

A DTO is an ordinary class for a meaningful payload across layers. Follow existing constructor/named-factory conventions; prefer typed properties and readonly data when immutability is intended. A typed constructor is not a full input validator, and readonly does not deeply freeze mutable nested objects. Operation input DTOs should avoid carrying requests, components, query builders or container instances. Other existing data types may legitimately represent a query specification or framework result; assess their actual contract. Do not add a DTO package without authorization.

Example **shape**, not a required base class or universal payload:

```php
namespace App\DTO\Orders;

final readonly class PlaceOrderData
{
    public function __construct(
        public int $customerId,
        public int $quantity,
        public string $requestKey,
    ) {}
}
```

An adapter constructs this after deliberate validation/normalization. A separate explicit actor argument or established context contract identifies the initiating actor. A queue may carry stable identifiers and resolve/check current authoritative state when executing; do not serialize an entire request-scoped graph.

Enums fit closed sets of stable values. Cast model attributes where it improves the domain contract, use supported enum validation, and map `->value` deliberately at external boundaries. Coordinate adoption with raw strings, database null/unknown values, imports, API payloads, snapshots/hashes, queued data, and UI options. Dynamic vocabulary is not an enum merely because it currently has a few values. A `HasLabel`/color/icon implementation couples an enum to Filament; decide whether that enum belongs to UI or the shared domain before adding that dependency.

A value object combines a domain value with invariants/behavior (for example Money arithmetic). Prefer existing domain types and built-in casts. Avoid incidental wrappers and float-based monetary arithmetic where exact units are required. A custom cast translates storage; it must return the representation required by its contract. For Eloquent `CastsAttributes::set`, an array can map to multiple columns; it is not automatically JSON for a single column. Test a nonempty single-column roundtrip when relevant.

## Operations and writes

An operation may be a final plain class with constructor-injected stable collaborators and a typed `handle()`/`execute()`/`__invoke()` using explicit operation data. No empty base service/action, framework inheritance or universal interface is necessary. A Service can have one method; an Action may have private helpers. Select method names from local conventions and actual responsibilities.

Before extraction, specify:

1. Inputs, output/result/exception contract and actor/tenant context.
2. Allowed callers, authorization location, ownership and invariant enforcement for every entry point.
3. Fields owned by this operation; untouched settings remain untouched. Separate patch/update from full replacement; do not reconstruct unchanged state through current UI options.
4. Authoritative writer and transaction boundary. Do not duplicate a compiler/signed-save/revision pipeline to make a new UI action simpler.
5. Actual lock acquisition order across all competing writers. Locks and fresh reads must protect decisions in the transaction, not merely earlier UI feedback. Concurrency risks need isolated actual-engine verification.
6. Retry/idempotency policy, stale state/version checks, and error mapping for adapters.
7. External effects and cache invalidation after commit. A transaction does not roll back an external API call. Retried closures may run more than once. An inner savepoint completion is not necessarily an outer transaction commit.

For important permission/invariant boundaries, test the non-UI caller as well as the UI flow. Make explicit whether an operation checks Gate/policy itself or requires a trusted authorized caller, and ensure every entry point satisfies that contract. Do not let an undocumented precondition become a bypass.

## Eloquent and side effects

Keep relationships and coherent local scopes on models; local scopes express reusable query constraints, not full use cases. A focused query/read-model class may coordinate complex queries or projections. Global scopes change every query and require deliberate removal/administrative behavior. Avoid an additional repository layer that repeats Eloquent without an actual boundary.

Mutators/casts apply through supported model attribute assignment/serialization paths; builder mass updates and raw writes bypass normal model assignment and may bypass these transformations. Inspect every relevant writer before treating a cast/mutator as universal enforcement. Do not impose a UI-specific input format on imports already using canonical data. Accessors/small display methods can be appropriate; a model presenter should earn extraction through substantial representation logic or hidden query cost.

Observers/model events suit reactions to Eloquent lifecycle events. Mass updates/deletes and quiet operations can bypass per-model events, so they cannot alone guarantee all database writes satisfy a universal invariant. Keep multi-model critical workflows explicit. Avoid recursive observers and invisible network effects.

Events are synchronous unless a listener is configured to queue. An event is a fact/decoupling mechanism, not automatic background execution. Jobs express real asynchronous/retry concerns. If queued work depends on committed data, use supported connection/dispatch/listener after-commit behavior; distinguish delaying dispatch from making the whole workflow durable. Verify failure/retry/idempotency and current model state. Use an outbox only when delivery requirements justify it, not as a default layer.

Configuration belongs in config files; code reads `config()`. `config:cache` stops normal `.env` loading; externally supplied environment variables can still exist. Therefore avoid the inaccurate blanket claim that `env()` always returns null outside config. Macros/global hooks belong to intentional boot configuration; review global effects, collisions, tests and long-running processes before introducing them.

Sources: [Validation](https://laravel.com/framework/docs/13.x/validation), [Eloquent casts](https://laravel.com/framework/docs/13.x/eloquent-mutators), [Eloquent events](https://laravel.com/framework/docs/13.x/eloquent#events), [Transactions](https://laravel.com/framework/docs/13.x/database#database-transactions), [Queues and transactions](https://laravel.com/framework/docs/13.x/queues#jobs-and-database-transactions), [Configuration cache](https://laravel.com/framework/docs/13.x/configuration#configuration-caching).
