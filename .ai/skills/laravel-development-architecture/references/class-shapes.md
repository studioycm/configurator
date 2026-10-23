# Plain class shapes and invocation

These are teaching examples in a hypothetical inventory application, not files to add to every project. They illustrate method/dependency ownership; adapt model, policy, identifier, connection, domain limits and naming contracts to the real app. No base Service/Action class or package is required.

## Contents

- DTO: explicit operation data
- Service: a cohesive calculation
- Action: authoritative write
- Contract and provider: actual substitution

## DTO: explicit operation data

```php
namespace App\DTO\Inventory;

final readonly class ReserveStockData
{
    public function __construct(
        public int $inventoryId,
        public int $quantity,
    ) {}
}
```

The adapter validates and deliberately converts accepted transport values first. This constructor does not authorize, normalize string IDs, or check available stock. Keep actor/context explicit rather than reading it from `auth()` inside a reusable DTO. Do not use `int` identifiers if the actual domain requires exact strings.

## Service: a cohesive calculation

```php
namespace App\Services\Inventory;

use DomainException;

final class StockAvailability
{
    public function reservedAfterAllocation(int $onHand, int $reserved, int $requested): int
    {
        if ($onHand < 0 || $reserved < 0 || $reserved > $onHand || $requested < 1) {
            throw new DomainException('Invalid stock quantities.');
        }

        if ($requested > $onHand - $reserved) {
            throw new DomainException('Insufficient stock.');
        }

        return $reserved + $requested;
    }
}
```

This capability needs no registration and no framework parent. Its separation is useful when the same stock arithmetic/rules serve allocation, simulation or reconciliation; keep the calculation inside an existing operation if a separate class adds no useful boundary. Use domain-specific failure types/messages when the app has them.

## Action: authoritative write

```php
namespace App\Actions\Inventory;

use App\DTO\Inventory\ReserveStockData;
use App\Models\Inventory;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Inventory\StockAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ReserveStock
{
    public function __construct(private StockAvailability $availability) {}

    public function handle(User $actor, ReserveStockData $data): Reservation
    {
        return DB::transaction(function () use ($actor, $data): Reservation {
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($data->inventoryId);

            Gate::forUser($actor)->authorize('reserve', $inventory);

            $reserved = $this->availability->reservedAfterAllocation(
                $inventory->on_hand,
                $inventory->reserved,
                $data->quantity,
            );

            $inventory->reserved = $reserved;
            $inventory->save();

            return $inventory->reservations()->create([
                'quantity' => $data->quantity,
                'created_by' => $actor->getKey(),
            ]);
        });
    }
}
```

Example assumptions: integer-cast stock attributes; an appropriate `reserve` policy including ownership/tenant checks; both models on the same transactional connection; a typed `reservations()` relationship, intended fillable fields and database constraints; all competing stock writers obeying the same lock contract. This example protects one allocation but supplies **no replay/idempotency mechanism**. Add one if the actual caller can retry the same business request. It does not send external notifications or call a gateway inside a retried transaction.

An HTTP controller can receive `ReserveStock` through method/constructor injection; a Livewire action can use supported action injection. Both construct the DTO after their own input validation and call `handle($actor, $data)` explicitly. A command/job uses a deliberate actor/system policy and invokes the same operation. A normal call does not inject missing method arguments. Map domain errors/notifications to each adapter's feedback contract.

## Contract and provider: actual substitution

If two real payment implementations already exist behind `PaymentGateway`, an existing provider can choose a default in `register()`:

```php
public function register(): void
{
    $this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
}
```

The names above stand for the application's actual imported contract/implementation. A different consumer can receive an implementation through a contextual binding. A per-operation choice needs an explicit selection contract; do not rely on unrelated entry-point type by accident. Ordinary concrete classes above need no binding. Use scoped/singleton only for a justified lifetime, following the container reference. Do not generate a provider just because a new concrete Service exists.

Test operation success, invalid/insufficient stock, ownership denial and atomic rollback. Test competing allocation on the actual database engine when overselling is a risk. Test the calculation separately only if its distinct rules merit it; avoid asserting call counts or copying the arithmetic into a test. These are proposed tests for the example, not tests executed while authoring this skill.
