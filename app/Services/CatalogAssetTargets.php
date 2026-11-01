<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CatalogAssetTargets
{
    public function __construct(private ItemListMembers $members) {}

    /** @return array{model: class-string<Product|Group>, entity: string, role: string, reference_field: array{column: string, key: string}|null, mime_types: list<string>, max_bytes: int, max_pixels: int, conversion_sizes: list<int>} */
    public function profile(string $targetKey): array
    {
        $target = match ($targetKey) {
            'product.main_image' => ['model' => Product::class, 'entity' => 'product', 'role' => 'main_image', 'reference_field' => ['column' => 'profile_fields', 'key' => 'product_pic']],
            'group.main_image' => ['model' => Group::class, 'entity' => 'group', 'role' => 'main_image', 'reference_field' => null],
            default => throw ValidationException::withMessages(['image.target' => __('validation.in', ['attribute' => 'image target'])]),
        };

        return [...$target, ...config('catalog-assets.image')];
    }

    /** @param array{list_key?: string, parent_id?: int} $ownerContext */
    public function eligibleQuery(User $actor, string $targetKey, array $ownerContext): Builder
    {
        Gate::forUser($actor)->authorize('manage-catalog');
        $profile = $this->profile($targetKey);
        $model = $profile['model'];
        if ($ownerContext === []) {
            return $model::query();
        }

        $allowedLists = $profile['entity'] === 'product'
            ? ['group-products', 'configurator-products'] : ['configurator-groups', 'group-card-properties'];
        if (count($ownerContext) !== 2 || ! isset($ownerContext['list_key'], $ownerContext['parent_id'])
            || ! is_string($ownerContext['list_key']) || ! in_array($ownerContext['list_key'], $allowedLists, true)
            || ! is_int($ownerContext['parent_id']) || $ownerContext['parent_id'] < 1) {
            throw ValidationException::withMessages(['image.context' => __('validation.in', ['attribute' => 'image owner context'])]);
        }

        if ($ownerContext['list_key'] === 'group-card-properties') {
            Group::query()->findOrFail($ownerContext['parent_id']);

            return Group::query()->whereKey($ownerContext['parent_id'])->doesntHave('children');
        }

        $query = $this->members->query($actor, $ownerContext['list_key'], $ownerContext['parent_id']);
        if (! $query instanceof Builder || ! $query->getModel() instanceof $model) {
            throw ValidationException::withMessages(['image.context' => __('validation.in', ['attribute' => 'image owner context'])]);
        }

        return clone $query;
    }
}
