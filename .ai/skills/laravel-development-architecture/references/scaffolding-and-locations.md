# Scaffolding, file locations, and namespaces

## Contents

- Pick a home
- Generate the right artifact
- Move without breaking discovery

## Pick a home

Laravel supports organization beyond its default folders; that freedom is not a recommendation to invent another architecture. Inspect Composer `autoload`/`autoload-dev`, nearby code, framework config, providers and generators first. Preserve the project's `Data`, `DTO`, or `DTOs` convention rather than adding a competing root. One class normally belongs in the matching PSR-4 file; casing matters on deployment filesystems.

These are conventional candidate homes, not required folders to create:

| Type | Candidate path and namespace | Responsibility |
| --- | --- | --- |
| Controller | `app/Http/Controllers/*`, `App\Http\Controllers` | HTTP adaptation |
| Form Request | `app/Http/Requests/*`, `App\Http\Requests` | HTTP validation/authorization |
| Rule | `app/Rules/*`, `App\Rules` | Reusable validation predicate |
| Application operation | `app/Actions/<Area>/*`, `App\Actions\<Area>` | One cohesive operation |
| Service | `app/Services/<Area>/*`, `App\Services\<Area>` | Related capability |
| DTO | Existing `app/DTO/*` or `app/Data/*` | Explicit input/output data contract |
| Enum | Existing `app/Enums/*` or established root | Finite stable choices |
| Contract | Existing `app/Contracts/*` or domain-adjacent namespace | Real implementation boundary |
| Provider | `app/Providers/*`, `App\Providers` | Wiring/boot configuration |
| Model / policy | `app/Models/*`, `app/Policies/*` | Persistence / model authorization |
| Cast / observer | `app/Casts/*`, `app/Observers/*` | Storage conversion / lifecycle reaction |
| Reusable host behavior | Domain/host-adjacent `Concerns`, or established `Traits` | Narrow composable behavior |
| Job / event / listener | `app/Jobs`, `app/Events`, `app/Listeners` | Queue work / fact / reaction |
| Command | `app/Console/Commands` | Console adapter |
| API Resource | `app/Http/Resources` | External response representation |
| Filament resource-local UI | Resource's `Schemas`, `Tables`, `Actions`, `Pages`, `RelationManagers` | Native UI configuration |
| Shared Filament UI | Existing `app/Filament` components/concerns conventions | UI reuse across actual consumers |

Keep generic business operations independent of `App\Filament`, `App\Livewire` and `App\Http`. UI callers may depend on application operations; reusable operations should not call a resource form/table to obtain domain state. Keep genuinely domain-specific helpers close to their owner. Avoid a new global `Support` dumping ground.

## Generate the right artifact

Recheck these examples with the **target installation's** `php artisan list` and `php artisan <command> --help`; packages and configuration can change generators. Examples were inspected on Laravel 13.35, Livewire 4.4, Filament 5.9, PHP 8.4 on 2026-10-21, not executed here.

```sh
php artisan make:class Actions/Orders/PlaceOrder --no-interaction
php artisan make:class Services/Orders/PriceCalculator --no-interaction
php artisan make:class DTO/Orders/PlaceOrderData --no-interaction
php artisan make:interface Contracts/PaymentGateway --no-interaction
php artisan make:trait Models/Concerns/HasReference --no-interaction
php artisan make:enum Enums/OrderStatus --string --no-interaction
php artisan make:provider PaymentsServiceProvider --no-interaction
php artisan make:request StoreOrderRequest --no-interaction
php artisan make:rule ValidOrderReference --no-interaction
php artisan make:policy OrderPolicy --model=Order --no-interaction
php artisan make:cast AsMoney --no-interaction
php artisan make:scope ActiveScope --no-interaction
php artisan make:event OrderPlaced --no-interaction
php artisan make:listener SendReceipt --event=OrderPlaced --no-interaction
php artisan make:command ReconcileOrders --command=orders:reconcile --no-interaction
php artisan make:test --pest PlaceOrderTest --no-interaction
```

`make:scope` makes a **global** scope class. Local scopes are methods on the model. No native `make:service`, `make:action`, or `make:dto` was installed; do not invent one. A helper class can also use `make:class`, even if a tutorial says there is no dedicated helper command.

Models, migrations, factories, seeders, controllers, middleware, jobs, resources, observers, notifications, mailables and Blade components have their own generators. Select only needed artifacts; do not default to `make:model --all`. Inspect schema through Boost before model/migration changes. Match factory and test conventions.

For Livewire, inspect `config/livewire.php`, component locations/namespaces and existing format first. Livewire 4 supports `--sfc`, `--mfc`, `--class`, `--type`, `--emoji`, and `--test` in this installation. Do not convert class components to SFC solely because SFC is the package default.

```sh
php artisan make:livewire Orders/EditOrder --class --no-interaction
```

For Filament, inspect panel IDs and discovered resource/page/widget namespaces. A candidate resource command is below; replace the model and panel placeholder with inspected values. The configured generator determines output paths. `--generate` reads database structure and is optional; `--force` can overwrite work and is not a default.

```sh
php artisan make:filament-resource Order --panel=<observed-panel-id> --no-interaction
```

Use Filament's native generators for supported resource/page/relation-manager/widget/component types. For plain resource-local configuration factory classes without a native generator, use `make:class`. Distinguish generated output from proposed output: do not promise paths before checking configuration.

## Move without breaking discovery

Before approving a move, list current FQCN -> target file -> target FQCN, purpose, all known consumers and registration changes. Inspect:

- Imports, fully qualified references, aliases, constructor types, string class references, config and tests.
- Composer namespace roots, route/controller binding, policy discovery or explicit registration.
- Event discovery paths and cached manifests, command registration, provider registration.
- Filament panel discovery paths/namespaces, Livewire aliases/component config and Blade tags.
- Jobs/events/model types serialized into queue payloads; old jobs may still contain old names. Plan draining/compatibility deliberately.
- Morph maps, stored class names, database/config/JSON identifiers, enum backing values, snapshot hashes and signed payloads.
- Published/stale caches and long-running workers. Plan safe rebuild/restart steps; do not run production commands during an audit.

PSR-4 loading does not guarantee framework discovery. Use relevant read-only `route:list`, `event:list`, `artisan list`, focused container resolution and runtime tests to verify actual invocation. Do not use `composer dump-autoload` as a magical fix for a namespace/path mismatch. Avoid compatibility wrappers unless existing external/runtime consumers need them; give any wrapper a bounded removal plan.

Sources: [Laravel structure](https://laravel.com/framework/docs/13.x/structure), [Artisan](https://laravel.com/framework/docs/13.x/artisan), [Filament code quality](https://filamentphp.com/docs/5.x/resources/code-quality-tips), [Livewire components](https://livewire.laravel.com/docs/4.x/components), [PSR-4](https://www.php-fig.org/psr/psr-4/).
