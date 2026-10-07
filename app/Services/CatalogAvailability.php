<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class CatalogAvailability
{
    /** @return Collection<int, Group> */
    public function visibleGroups(): Collection
    {
        $groups = Group::query()->leftJoin('configurators', 'configurators.id', '=', 'groups.configurator_id')
            ->select(['groups.id', 'groups.parent_id', 'groups.is_active', 'groups.catalog_revision', 'configurators.is_active as configurator_active', 'configurators.disabled_group_behavior'])
            ->withExists('children')->get();
        $visible = [];
        do {
            $before = count($visible);
            foreach ($groups as $group) {
                if (! $group->is_active || ($group->configurator_active !== null && ! $group->configurator_active && $group->disabled_group_behavior === 'hide')) {
                    continue;
                }
                if ($group->parent_id === null || isset($visible[$group->parent_id])) {
                    $visible[$group->id] = true;
                }
            }
        } while (count($visible) !== $before);

        return $groups->filter(fn (Group $group): bool => isset($visible[$group->id]));
    }

    /** @return list<int> */
    public function visibleGroupIds(): array
    {
        return $this->visibleGroups()->modelKeys();
    }

    public function group(int $groupId): Group
    {
        $group = $this->visibleGroups()->firstWhere('id', $groupId);
        abort_if($group === null, 404);

        return $group;
    }

    public function assertGroup(int $groupId): void
    {
        $this->group($groupId);
    }

    public function productIsVisible(Product $product): bool
    {
        return $product->is_active && in_array($product->group_id, $this->visibleGroupIds(), true);
    }

    public function assertProduct(Product $product): void
    {
        abort_unless($this->productIsVisible($product), 404);
    }
}
