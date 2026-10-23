# Container, providers, discovery, and data boundaries

Verified 2026-10-21 using Boost search-docs, official Laravel 13 documentation,
and installed `laravel/framework` v13.35.0, commit
`a413a9da94884e5e9d58f4e6612884f712827804`, on PHP 8.4. Generator options were
checked with Artisan `list` and `--help`; generators were not executed.
Recheck the target project's installed versions and runtime before using examples.
These rules guide decisions; they do not prescribe new layers or namespaces.

## Contents

- Dependency injection and lifetime
- Providers and package registration
- Autoloading is not discovery

## Dependency injection and lifetime

| Need | Preferred starting point | Tradeoff to check |
| --- | --- | --- |
| Concrete dependency with resolvable constructor arguments | Constructor injection; no binding | Required scalars or unbound abstractions need explicit resolution |
| Dependency used by one framework-invoked operation | Method injection where supported | Ordinary direct method calls do not inject arguments |
| Interface or abstract dependency | Existing provider binding or supported binding attribute | Introduce the contract only when the boundary earns it |
| Consumer-specific implementation | Contextual `when()->needs()->give()` | Keep the selection visible; avoid environment logic scattered through consumers |
| Ordinary operation/service | Transient resolution | Do not assume repeated `app(Foo::class)` returns one instance |
| Shared request/job accumulator or memoized state | `scoped()` or `#[Scoped]` | Verify lifecycle reset and nested callers use the same instance |
| Container/process-wide reusable instance | `singleton()` or `#[Singleton]` | Check mutable state and captured actor, request, configuration, and tenant data |

- Laravel resolves concrete constructor dependencies recursively without manual
  registration. A separate provider is not required for every action or service.
- Constructor injection makes required collaborators visible. Explicit runtime
  parameters suit operation data and actors. Do not pass the entire container
  merely to hide dependencies behind `make()` calls.
- Injection requires container/framework construction or invocation. `new Foo`
  does not autowire, and `$foo->handle()` does not fill missing method arguments.
  Use explicit arguments, constructor injection, or deliberate `Container::call()`.
- An interface is not required just to mock a concrete dependency in tests.
- `scoped()` shares within a request/job lifecycle and is flushed by supporting
  worker lifecycles; it is not a general-purpose cross-operation cache.
- A singleton lasts for its container's lifetime, which can span requests/jobs
  in long-running workers. Pass current actor/request data at operation time.
- `#[Bind(Implementation::class)]` can map an interface without provider wiring;
  environment-specific `Bind` attributes are supported. Prefer established local
  wiring conventions instead of introducing a second registration style.
- Contextual parameter attributes are available for concerns such as named
  storage/configuration. They are alternatives, not mandatory modernization.
- **PHP trap:** current docs show `#[BindWhen(... static fn ...)]`, but that syntax
  requires PHP 8.5+. Its attribute class existing in Laravel does not make the
  closure attribute syntax usable on PHP 8.4.

Sources: [container](https://laravel.com/framework/docs/13.x/container),
[method invocation](https://laravel.com/framework/docs/13.x/container#method-invocation-and-injection),
[installed-version resolution source](https://github.com/laravel/framework/blob/a413a9da94884e5e9d58f4e6612884f712827804/src/Illuminate/Container/Container.php#L907).

## Providers and package registration

- Put container bindings in `register()`. Put integrations that need registered
  services, such as listeners, view composers, and macros, in `boot()`.
- All providers register before booting. Avoid eagerly resolving another
  provider's services during registration.
- `make:provider` automatically adds the provider to `bootstrap/providers.php`.
  A manually created provider needs registration there. Verify the target app's
  bootstrap conventions; do not copy an older `config/app.php` provider recipe.
- Split providers when distinct wiring responsibilities justify it, not to match
  every folder. A provider is application bootstrapping, not an operation layer.
- A binding-only provider may implement `DeferrableProvider` with `provides()`;
  use this only when deferral fits its actual boot responsibilities.
- Package discovery uses package Composer `extra.laravel.providers` metadata.
  Consumers can exclude named packages or `*` via `extra.laravel.dont-discover`.
  This differs from application provider registration; do not disable it globally
  as an incidental architecture cleanup.

Sources: [providers](https://laravel.com/framework/docs/13.x/providers),
[provider generator source](https://github.com/laravel/framework/blob/a413a9da94884e5e9d58f4e6612884f712827804/src/Illuminate/Foundation/Console/ProviderMakeCommand.php#L40),
[package discovery](https://laravel.com/framework/docs/13.x/packages#package-discovery).

## Autoloading is not discovery

PSR-4 maps namespaces to files; it does not register listeners, commands,
policies, or interface bindings. Preserve Composer namespace roots and exact
filename/class casing. Laravel conventionally maps `App` to `app/`; alternate
organization is supported, but needs a concrete benefit and discovery checks.

| Concern | Default | Deliberate exception / verification |
| --- | --- | --- |
| Policies | Matching model name plus `Policy`, in a conventional `Policies` directory | `Gate::policy()`, `#[UsePolicy]`, or `Gate::guessPolicyNamesUsing()` |
| Event listeners | Discover `app/Listeners` public `handle*` / `__invoke` methods with event parameter types | `withEvents(discover: [...])`; inspect `event:list` |
| Commands | Register `app/Console/Commands` | `withCommands([...])` accepts additional directories or classes; inspect `artisan list` |

- Gates and policies can coexist. Policies group model/resource authorization;
  gates are useful for operations such as dashboard access. Do not invent a
  `withPolicies(discover: false)` bootstrap API.
- Event discovery can be disabled with `withEvents(discover: false)` in
  `bootstrap/app.php`. A listener can implement `ShouldBeDiscovered` and return
  a boolean from `public static function shouldBeDiscovered(): bool`.
- Discovery decisions can be captured in the event manifest. They are not
  per-dispatch or per-user checks. Put runtime eligibility in listener execution.
- Avoid unintentionally registering the same listener manually and through
  discovery. `event:cache`, `event:clear`, and `optimize` change cached state;
  inspect first and run them only within the authorized deployment/workflow.
- **Command trap:** `withCommands([])` selects the default directory; it does
  not disable discovery. A custom console kernel can alter discovery, but is
  an advanced exception rather than a reason to add one.

Sources: [PSR-4](https://www.php-fig.org/psr/psr-4/),
[directory structure](https://laravel.com/framework/docs/13.x/structure#the-app-directory),
[policy registration](https://laravel.com/framework/docs/13.x/authorization#registering-policies),
[event discovery](https://laravel.com/framework/docs/13.x/events#event-discovery),
[event cache source](https://github.com/laravel/framework/blob/a413a9da94884e5e9d58f4e6612884f712827804/src/Illuminate/Foundation/Support/Providers/EventServiceProvider.php#L105),
[command registration](https://laravel.com/framework/docs/13.x/artisan#registering-commands).
