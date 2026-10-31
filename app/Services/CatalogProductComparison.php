<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CatalogProductComparison
{
    public function __construct(private CatalogAvailability $availability) {}

    /**
     * @param  list<int|string>  $productIds
     * @return array{groupId: string, pairIds: list<string>, groupRevision: string, products: list<array{id: string, code: string}>, differences: list<array{key: string, label: string, values: list<?string>}>, same: list<array{key: string, label: string, values: list<?string>}>}
     */
    public function get(User $actor, int $groupId, array $productIds): array
    {
        if (! array_is_list($productIds) || count($productIds) !== 2) {
            $this->reject();
        }
        $ids = [];
        foreach ($productIds as $id) {
            if ((! is_int($id) && ! is_string($id)) || preg_match('/\A[1-9][0-9]*\z/D', (string) $id) !== 1
                || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $this->reject();
            }
            $ids[] = (int) $id;
        }
        if ($ids[0] === $ids[1]) {
            $this->reject();
        }

        return DB::transaction(function () use ($actor, $groupId, $ids): array {
            abort_unless($actor->exists && User::query()->whereKey($actor->id)->exists(), 401);
            $visible = $this->availability->group($groupId);
            abort_if($visible->children_exists, 404);
            $group = Group::with('filters')->findOrFail($groupId);
            $products = Product::query()->where('group_id', $groupId)->where('is_active', true)->whereIn('id', $ids)
                ->get(['id', 'group_id', 'product_code', 'profile_fields'])->keyBy('id');
            abort_unless($products->count() === 2, 404);
            $pair = array_map(fn (int $id): Product => $products[$id], $ids);
            $pair[0]->setRelation('group', $group);
            $fields = ProductEditorData::groupFields($pair[0]);
            $labels = array_column($fields, 'label', 'key');
            $keys = CatalogPolicy::resultSettings($group->result_settings)['card_properties'];
            if ($keys === []) {
                $keys = array_column($fields, 'key');
            }
            $result = ['groupId' => (string) $groupId, 'pairIds' => array_map(strval(...), $ids),
                'groupRevision' => (string) $group->catalog_revision,
                'products' => array_map(fn (Product $product): array => ['id' => (string) $product->id, 'code' => $product->product_code], $pair),
                'differences' => [], 'same' => []];
            $profiles = array_map(fn (Product $product): array => is_array($product->profile_fields) ? $product->profile_fields : [], $pair);
            foreach ($keys as $key) {
                $values = array_map(function (array $profile) use ($key): ?string {
                    $value = $profile[$key] ?? null;

                    return is_string($value) && $value !== '' ? $value : null;
                }, $profiles);
                $bucket = $values[0] === $values[1] ? 'same' : 'differences';
                $result[$bucket][] = ['key' => $key, 'label' => $labels[$key] ?? str_replace('_', ' ', $key), 'values' => $values];
            }

            return $result;
        }, attempts: 1);
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['productIds' => __('catalog.comparison.invalid_pair')]);
    }
}
