<?php

namespace Database\Factories;

use App\Models\ConfiguratorRule;
use App\Models\MappingSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MappingSet> */
class MappingSetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['rule_id' => ConfiguratorRule::factory(), 'sort_order' => 0];
    }
}
