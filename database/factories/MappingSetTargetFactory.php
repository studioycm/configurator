<?php

namespace Database\Factories;

use App\Models\ConfiguratorOption;
use App\Models\MappingSet;
use App\Models\MappingSetTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MappingSetTarget> */
class MappingSetTargetFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['mapping_set_id' => MappingSet::factory(), 'configurator_option_id' => ConfiguratorOption::factory()];
    }
}
