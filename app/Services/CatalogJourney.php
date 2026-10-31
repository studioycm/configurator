<?php

namespace App\Services;

use App\Models\GroupTagValue;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use JsonException;
use stdClass;

/**
 * @phpstan-type ReturnQuery array{branchId:int|null,search:string,tagValueIds:list<int>,mode:'tree'|'tags',page:int}
 * @phpstan-type ProductCriteria array{version:1,filters:array<string|int,string>,subGroupId:string|null,precedence:list<string>}
 * @phpstan-type JourneyState array{version:1,groupId:string,productId:string|null,catalog:array{query:ReturnQuery,anchor:string|null}|null,products:array{criteria:ProductCriteria,anchor:string|null}|null}
 */
class CatalogJourney
{
    public function __construct(private CatalogAvailability $availability, private CatalogNavigation $navigation, private CatalogSnapshots $snapshots, private CatalogSnapshotMatcher $matcher) {}

    /** @return JourneyState Shape-checked transport; current ownership is checked by validate(). */
    public function decode(mixed $encoded): array
    {
        $this->require(is_string($encoded) && strlen($encoded) <= 8192 && preg_match('/^[A-Za-z0-9_-]+$/D', $encoded) === 1);
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $this->require(is_string($json) && $this->base64($json) === $encoded && mb_check_encoding($json, 'UTF-8'));
        try {
            $wire = json_decode($json, flags: JSON_THROW_ON_ERROR, depth: 12);
            $this->require($wire instanceof stdClass);
            if (($wire->catalog ?? null) !== null) {
                $this->require($wire->catalog instanceof stdClass && ($wire->catalog->query ?? null) instanceof stdClass
                    && is_array($wire->catalog->query->tagValueIds ?? null));
            }
            if (($wire->products ?? null) !== null) {
                $this->require($wire->products instanceof stdClass && ($wire->products->criteria ?? null) instanceof stdClass
                    && ($wire->products->criteria->filters ?? null) instanceof stdClass
                    && is_array($wire->products->criteria->precedence ?? null));
            }

            return $this->normalize(json_decode($json, true, 12, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw ValidationException::withMessages(['r' => __('catalog.journey.reset')]);
        }
    }

    /** @param array<string, mixed> $state @return JourneyState Current actor and owner checked. */
    public function validate(User $actor, array $state): array
    {
        $state = $this->normalize($state);
        $this->encode($state);
        $this->require($actor->exists && User::query()->whereKey($actor->id)->exists());
        $visible = $this->availability->visibleGroups()->keyBy('id');
        $group = $visible->get((int) $state['groupId']);
        $this->require($group !== null && ! $group->children_exists);
        if ($state['productId'] !== null) {
            $this->require(Product::query()->whereKey($state['productId'])->where('group_id', $state['groupId'])->where('is_active', true)->exists());
        }
        if ($state['catalog'] !== null) {
            $query = $state['catalog']['query'];
            $this->require($query['branchId'] === null || $visible->has($query['branchId']));
            $this->require(GroupTagValue::query()->whereKey($query['tagValueIds'])->count() === count($query['tagValueIds']));
        }
        if ($state['products'] !== null) {
            $this->matcher->ids($this->snapshots->get((int) $state['groupId']), $state['products']['criteria']);
        }

        return $state;
    }

    /** @return array{state: JourneyState|null, notice: string|null} */
    public function resolve(User $actor, mixed $encoded, ?int $groupId = null, ?int $productId = null): array
    {
        if ($encoded === null) {
            return ['state' => null, 'notice' => null];
        }
        try {
            $state = $this->validate($actor, $this->decode($encoded));
            $this->require($groupId === null || $state['groupId'] === (string) $groupId);
            $this->require($productId === null || $state['productId'] === (string) $productId);

            return ['state' => $state, 'notice' => null];
        } catch (ValidationException) {
            return ['state' => null, 'notice' => __('catalog.journey.reset')];
        }
    }

    /** @param array<string, mixed> $state */
    public function encode(array $state): string
    {
        try {
            $encoded = $this->base64(json_encode($this->forClient($state), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (JsonException) {
            throw ValidationException::withMessages(['r' => __('catalog.journey.reset')]);
        }
        $this->require(strlen($encoded) <= 8192);

        return $encoded;
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    public function forClient(array $state): array
    {
        $wire = $this->normalize($state);
        if ($wire['products'] !== null) {
            $wire['products']['criteria']['filters'] = (object) $wire['products']['criteria']['filters'];
        }

        return $wire;
    }

    /** @param array<string, mixed> $state */
    public function catalogUrl(array $state): string
    {
        $state = $this->normalize($state);
        if ($state['catalog'] === null) {
            return route('catalog.index');
        }

        return route('catalog.index', ['g' => $this->groupQuery($state['catalog']['query']), 'r' => $this->encode($state)]);
    }

    /** @param array<string, mixed> $state */
    public function groupUrl(array $state): string
    {
        $state = $this->normalize($state);

        return route('catalog.groups.show', ['group' => $state['groupId'], ...($state['products'] === null ? [] : ['d' => $state['products']['criteria']]), 'r' => $this->encode($state)]);
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function compatibilityQuery(User $actor, array $query, string $destination, int $id): array
    {
        $this->require(in_array($destination, ['group', 'product'], true));
        $result = [];
        if (array_key_exists('r', $query)) {
            $resolved = $this->resolve($actor, $query['r'], $destination === 'group' ? $id : null, $destination === 'product' ? $id : null);
            $result['r'] = $resolved['state'] === null ? 'invalid' : $this->encode($resolved['state']);
        }
        if ($destination === 'product' && array_key_exists('configuration', $query)) {
            $code = self::configurationCodeFromQuery($query, (string) request()->server('QUERY_STRING', ''));
            $this->require(is_string($code) && strlen($code) <= 32768);
            $result['configuration'] = $code;
        }
        if ($destination === 'group') {
            if (array_key_exists('g', $query)) {
                $this->require(is_array($query['g']) && in_array($query['g']['version'] ?? null, [1, '1'], true)
                    && array_diff(array_keys($query['g']), ['version', 'branch', 'q', 'tags', 'mode', 'page']) === []);
                $input = [];
                foreach (['branch' => 'branchId', 'q' => 'search', 'tags' => 'tagValueIds', 'mode' => 'mode', 'page' => 'page'] as $from => $to) {
                    if (array_key_exists($from, $query['g'])) {
                        $input[$to] = $query['g'][$from];
                    }
                }
                $result['g'] = $this->groupQuery($this->navigation->validateQuery($input));
            }
            if (array_key_exists('d', $query)) {
                $raw = $query['d'];
                $this->require(is_array($raw) && array_diff(array_keys($raw), ['version', 'filters', 'subGroupId', 'precedence']) === []);
                $criteria = $this->criteria(['version' => ($raw['version'] ?? null) === '1' ? 1 : ($raw['version'] ?? null),
                    'filters' => $raw['filters'] ?? [], 'subGroupId' => ($raw['subGroupId'] ?? null) === '' ? null : ($raw['subGroupId'] ?? null), 'precedence' => $raw['precedence'] ?? []]);
                $group = $this->availability->group($id);
                if (! $group->children_exists) {
                    $this->matcher->ids($this->snapshots->get($id), $criteria);
                    $result['d'] = $criteria;
                }
            }
        }
        $this->require(strlen(http_build_query($result)) <= 32768);

        return $result;
    }

    /** @param array<string, mixed> $query */
    public static function configurationCodeFromQuery(array $query, string $rawQuery): mixed
    {
        $values = [];
        foreach (explode('&', $rawQuery) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $key = urldecode($key);
            if (str_starts_with($key, 'configuration[')) {
                return [];
            }
            if ($key === 'configuration') {
                $values[] = urldecode($value);
            }
        }

        return count($values) > 1 ? [] : ($values[0] ?? ($query['configuration'] ?? null));
    }

    /** @param array<string,mixed> $state @return array<string,mixed> */
    private function normalize(array $state): array
    {
        $this->keys($state, ['version', 'groupId', 'productId', 'catalog', 'products']);
        $this->require($state['version'] === 1);
        $groupId = $this->id($state['groupId']);
        $productId = $state['productId'] === null ? null : $this->id($state['productId']);
        $catalog = $state['catalog'];
        if ($catalog !== null) {
            $this->require(is_array($catalog));
            $this->keys($catalog, ['query', 'anchor']);
            $this->require(is_array($catalog['query']));
            $this->keys($catalog['query'], ['branchId', 'search', 'tagValueIds', 'mode', 'page']);
            $query = $this->navigation->validateQuery($catalog['query']);
            unset($query['requestId']);
            $this->require($catalog['anchor'] === null || $catalog['anchor'] === 'model-'.$groupId);
            $catalog = ['query' => $query, 'anchor' => $catalog['anchor']];
        }
        $products = $state['products'];
        if ($products !== null) {
            $this->require(is_array($products));
            $this->keys($products, ['criteria', 'anchor']);
            $this->require(is_array($products['criteria']));
            $this->require($products['anchor'] === null || ($productId !== null && $products['anchor'] === 'product-'.$productId));
            $products = ['criteria' => $this->criteria($products['criteria']), 'anchor' => $products['anchor']];
        }

        return ['version' => 1, 'groupId' => $groupId, 'productId' => $productId, 'catalog' => $catalog, 'products' => $products];
    }

    /**
     * Numeric property keys can make a native PHP map list-shaped; decode() checks JSON object identity first.
     *
     * @param  array<string,mixed>  $raw  @return array<string,mixed>
     */
    private function criteria(array $raw): array
    {
        $this->keys($raw, ['version', 'filters', 'subGroupId', 'precedence']);
        $this->require($raw['version'] === 1 && is_array($raw['filters'])
            && is_array($raw['precedence']) && array_is_list($raw['precedence']));
        foreach ($raw['filters'] as $key => $value) {
            $this->require(is_string($value) && mb_check_encoding((string) $key.$value, 'UTF-8'));
        }
        foreach ($raw['precedence'] as $token) {
            $this->require(is_string($token) && mb_check_encoding($token, 'UTF-8'));
        }
        $this->require($raw['subGroupId'] === null || (is_string($raw['subGroupId']) && $this->id($raw['subGroupId']) === $raw['subGroupId']));

        return $raw;
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    private function groupQuery(array $query): array
    {
        return ['version' => 1, 'branch' => $query['branchId'], 'q' => $query['search'], 'tags' => $query['tagValueIds'], 'mode' => $query['mode'], 'page' => $query['page']];
    }

    /** @param array<string,mixed> $data @param list<string> $keys */
    private function keys(array $data, array $keys): void
    {
        $this->require(count($data) === count($keys) && array_diff(array_keys($data), $keys) === []);
    }

    private function id(mixed $id): string
    {
        $this->require((is_int($id) || is_string($id)) && preg_match('/^[1-9][0-9]*$/D', (string) $id) === 1 && (string) (int) $id === (string) $id);

        return (string) $id;
    }

    private function base64(string $json): string
    {
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function require(bool $condition): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['r' => __('catalog.journey.reset')]);
        }
    }
}
