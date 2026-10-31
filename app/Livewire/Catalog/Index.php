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
            $this->preparedNavigation = $this->loadNavigation($this->initialQuery);
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

        return $this->readProjection(fn (): array => app(CatalogNavigation::class)->get(auth()->user(), $query));
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
        if ($rawQuery !== null) {
            $state = self::rawUrlState($rawQuery);
        }
        if (! is_array($state) || ($state !== [] && (! in_array($state['version'] ?? null, [1, '1'], true)
            || array_diff(array_keys($state), ['version', 'branch', 'q', 'tags', 'mode', 'page']) !== []))) {
            throw ValidationException::withMessages(['g' => 'This catalog link is invalid or out of date.']);
        }
        $query = [];
        foreach (['branch' => 'branchId', 'q' => 'search', 'tags' => 'tagValueIds', 'mode' => 'mode', 'page' => 'page'] as $key => $target) {
            if (array_key_exists($key, $state)) {
                $query[$target] = $state[$key];
            }
        }
        if ($branchOverride !== null) {
            $query['branchId'] = $branchOverride;
        }

        return $query;
    }

    /** @return array<string,mixed> */
    private static function rawUrlState(string $rawQuery): array
    {
        $invalid = static fn (): ValidationException => ValidationException::withMessages(['g' => 'This catalog link is invalid or out of date.']);
        $state = [];
        $tags = [];
        $tagStyle = null;
        foreach (explode('&', $rawQuery) as $part) {
            $pair = explode('=', $part, 2);
            $key = urldecode($pair[0]);
            if ($key !== 'g' && ! str_starts_with($key, 'g[')) {
                continue;
            }
            $rawValue = $pair[1] ?? '';
            $value = urldecode($rawValue);
            if ($key === 'g' || preg_match('/%(?![0-9a-f]{2})/i', $pair[0].$rawValue) === 1 || ! mb_check_encoding($key.$value, 'UTF-8')) {
                throw $invalid();
            }
            if (preg_match('/^g\[tags\]\[([0-9]*)\]$/D', $key, $match) === 1) {
                $index = $match[1];
                $style = $index === '' ? 'append' : 'indexed';
                if (($tagStyle !== null && $style !== $tagStyle) || count($tags) >= 200
                    || ($style === 'indexed' && (preg_match('/^(?:0|[1-9][0-9]*)$/D', $index) !== 1 || (int) $index >= 200 || array_key_exists((int) $index, $tags)))) {
                    throw $invalid();
                }
                $tagStyle = $style;
                if ($style === 'append') {
                    $tags[] = $value;
                } else {
                    $tags[(int) $index] = $value;
                }

                continue;
            }
            $map = ['g[version]' => 'version', 'g[branch]' => 'branch', 'g[q]' => 'q', 'g[mode]' => 'mode', 'g[page]' => 'page'];
            if (! isset($map[$key]) || array_key_exists($map[$key], $state)) {
                throw $invalid();
            }
            $state[$map[$key]] = $value;
        }
        if ($tagStyle !== null) {
            ksort($tags, SORT_NUMERIC);
            if (array_keys($tags) !== range(0, count($tags) - 1)) {
                throw $invalid();
            }
            $state['tags'] = array_values($tags);
        }

        return $state;
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public static function urlState(array $query): array
    {
        $state = ['version' => 1];
        foreach (['branchId' => 'branch', 'search' => 'q', 'tagValueIds' => 'tags', 'mode' => 'mode', 'page' => 'page'] as $key => $target) {
            $value = $query[$key] ?? null;
            if ($value !== null && $value !== '' && $value !== [] && ! ($key === 'page' && $value === 1)) {
                $state[$target] = $value;
            }
        }

        return $state;
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
        $navigation = $this->invalidState ? null : ($this->preparedNavigation ?? $this->loadNavigation($this->initialQuery));
        $journeyNotice = $this->journeyNotice;
        if ($navigation !== null) {
            foreach ($navigation['cards'] as &$card) {
                try {
                    $card['url'] = app(CatalogJourney::class)->groupUrl(['version' => 1, 'groupId' => (string) $card['id'], 'productId' => null,
                        'catalog' => ['query' => $navigation['query'], 'anchor' => 'model-'.$card['id']], 'products' => null]);
                } catch (ValidationException) {
                    $journeyNotice = __('catalog.journey.overflow');
                }
            }
            unset($card);
        }

        return view('livewire.catalog.index', compact('navigation', 'journeyNotice'))->title(__('catalog.navigation.product_catalog'))
            ->layoutData(['heading' => __('catalog.browse.heading')]);
    }
}
