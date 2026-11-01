<?php

namespace App\Livewire\Catalog;

use App\Services\CatalogJourney;
use App\Services\CatalogNavigation;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Json;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

#[Layout('components.layouts.catalog')]
class Index extends Component
{
    /** @var array<string,mixed> */
    #[Locked]
    public array $initialQuery = [];

    #[Locked]
    public bool $invalidState = false;

    #[Locked]
    public ?array $journeyReturn = null;

    #[Locked]
    public ?string $journeyNotice = null;

    /** @var array<string,mixed>|null */
    protected ?array $preparedNavigation = null;

    public function mount(): void
    {
        try {
            $this->initialQuery = self::queryFromUrl(request()->query('g', []), null, (string) request()->server('QUERY_STRING', ''));
            $this->preparedNavigation = $this->prepareNavigation($this->initialQuery);
            $resolved = app(CatalogJourney::class)->resolve(auth()->user(), request()->query('r'));
            $this->journeyNotice = $resolved['notice'];
            $catalog = $resolved['state']['catalog'] ?? null;
            if ($catalog !== null && $catalog['query'] === $this->preparedNavigation['query']) {
                $this->journeyReturn = $catalog;
            }
        } catch (ValidationException) {
            $this->invalidState = true;
        }
    }

    /** @return array<string,mixed> */
    #[Json]
    public function loadNavigation(mixed $query): array
    {
        abort_unless(auth()->check(), 401);
        if (! is_array($query)) {
            throw ValidationException::withMessages(['query' => 'Choose a valid catalog query.']);
        }

        $navigation = $this->prepareNavigation($query);
        $navigation['query'] = app(CatalogNavigation::class)->queryForClient($navigation['query']);

        return $navigation;
    }

    /** @return array<string,mixed> */
    #[Json]
    public function loadTreeChildren(mixed $parentId, mixed $page, mixed $expectedTreeVersion, mixed $requestId): array
    {
        abort_unless(auth()->check(), 401);
        if (! is_string($expectedTreeVersion) || ! is_string($requestId)) {
            throw ValidationException::withMessages(['tree' => 'Choose a current tree version and request identity.']);
        }

        return $this->readProjection(fn (): array => app(CatalogNavigation::class)->children(auth()->user(), $parentId === null ? null : self::positiveInput($parentId), self::positiveInput($page), $expectedTreeVersion, $requestId));
    }

    /** @return array<string,mixed> */
    public static function queryFromUrl(mixed $state, ?int $branchOverride = null, ?string $rawQuery = null): array
    {
        return app(CatalogNavigation::class)->queryFromUrl($state, $branchOverride, $rawQuery);
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public static function urlState(array $query): array
    {
        $state = ['version' => 2];
        foreach (['branchId' => 'branch', 'search' => 'q', 'tagValueIds' => 'tags', 'attributeValues' => 'attributes', 'mode' => 'mode', 'page' => 'page'] as $key => $target) {
            $value = $query[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== [] && ! ($key === 'page' && $value === 1)) {
                $state[$target] = $value;
            }
        }

        return $state;
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    private function prepareNavigation(array $query): array
    {
        abort_unless(auth()->check(), 401);

        return $this->readProjection(fn (): array => app(CatalogNavigation::class)->get(auth()->user(), $query));
    }

    /** @return array<string,mixed> */
    private function readProjection(Closure $read): array
    {
        try {
            return $read();
        } catch (Throwable $exception) {
            if (! $exception instanceof ValidationException && ! $exception instanceof HttpExceptionInterface) {
                report($exception);
            }
            throw $exception;
        }
    }

    private static function positiveInput(mixed $value): int
    {
        if ((! is_int($value) && ! is_string($value)) || preg_match('/^[1-9][0-9]*$/D', (string) $value) !== 1 || (string) (int) $value !== (string) $value) {
            throw ValidationException::withMessages(['tree' => 'Use a canonical positive integer.']);
        }

        return (int) $value;
    }

    public function render(): View
    {
        $navigation = $this->invalidState ? null : ($this->preparedNavigation ?? $this->prepareNavigation($this->initialQuery));
        $journeyNotice = $this->journeyNotice;
        if ($navigation !== null) {
            foreach ($navigation['cards'] as &$card) {
                try {
                    $card['url'] = app(CatalogJourney::class)->groupUrl(['version' => 2, 'groupId' => (string) $card['id'], 'productId' => null,
                        'catalog' => ['query' => $navigation['query'], 'anchor' => 'model-'.$card['id']], 'products' => null]);
                } catch (ValidationException) {
                    $journeyNotice = __('catalog.journey.overflow');
                }
            }
            unset($card);
        }

        $journeyReturnForClient = $this->journeyReturn;
        if ($navigation !== null) {
            $navigation['query'] = app(CatalogNavigation::class)->queryForClient($navigation['query']);
        }
        if ($journeyReturnForClient !== null) {
            $journeyReturnForClient['query'] = app(CatalogNavigation::class)->queryForClient($journeyReturnForClient['query']);
        }

        return view('livewire.catalog.index', compact('navigation', 'journeyNotice', 'journeyReturnForClient'))->title(__('catalog.navigation.product_catalog'))
            ->layoutData(['heading' => __('catalog.browse.heading')]);
    }
}
