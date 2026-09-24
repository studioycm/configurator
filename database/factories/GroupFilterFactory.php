<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupFilter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GroupFilter> */
class GroupFilterFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['group_id' => Group::factory(), 'property_key' => 'Working_Pressure', 'label' => 'Working pressure', 'sort_order' => 0, 'value_order' => [], 'value_labels' => []];
    }
}
