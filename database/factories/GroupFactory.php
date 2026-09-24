<?php

namespace Database\Factories;

use App\Models\Group;
use App\Services\CatalogPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Group> */
class GroupFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['legacy_id' => null, 'name' => fake()->words(2, true), 'description' => null, 'parent_id' => null, 'configurator_id' => null, 'sort_order' => 0, 'result_settings' => CatalogPolicy::RESULT_SETTINGS];
    }
}
