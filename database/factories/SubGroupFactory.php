<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\SubGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubGroup> */
class SubGroupFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['group_id' => Group::factory(), 'label' => 'Test preset', 'property_key' => 'Working_Pressure', 'allowed_values' => ['10 bar'], 'force_hide' => false, 'sort_order' => 0];
    }
}
