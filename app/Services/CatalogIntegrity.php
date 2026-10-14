<?php

namespace App\Services;

use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CatalogIntegrity
{
    /** @return Collection<int, Group> */
    public function lockGroups(): Collection
    {
        return Group::query()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    public function assertLeaf(Group $group): void
    {
        if ($group->children()->exists()) {
            throw ValidationException::withMessages(['group_id' => __('catalog.validation.products_and_configurators_belong_to_leaf_groups')]);
        }
    }

    /** @param Collection<int, Group> $groups */
    public function validateGroup(Group $group, Collection $groups): void
    {
        if ($group->exists && $group->configurator_id !== null) {
            $this->assertLeaf($group);
        }
        $visited = [];
        $parentId = $group->parent_id;
        while ($parentId !== null) {
            if ($parentId === $group->id || isset($visited[$parentId])) {
                throw ValidationException::withMessages(['parent_id' => __('catalog.validation.a_group_cannot_become_its_own_ancestor')]);
            }
            $visited[$parentId] = true;
            $parent = $groups->get($parentId);
            if ($parent === null) {
                throw ValidationException::withMessages(['parent_id' => __('catalog.validation.the_selected_parent_group_no_longer_exists')]);
            }
            if ($parentId === $group->parent_id && ($parent->configurator_id !== null || $parent->products()->exists())) {
                throw ValidationException::withMessages(['parent_id' => __('catalog.validation.a_group_containing_products_or_a_configurator')]);
            }
            $parentId = $parent->parent_id;
        }
    }
}
